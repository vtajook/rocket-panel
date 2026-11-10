<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Models;

use \App\Libraries\UserShell;
use \App\Models\Settings;
use \App\Models\Users;
use \App\Models\Traffics;

class Cronjob extends \App\Models\BaseModel
{


    public function init()
    {

        //secound values
        $schedules = [
            'multiUser'         => 1,
            'expiredUsers'      => 120,
            'syncTraffic'       => 1,
            'syncSysUsers'      => 60,
            'limitedTraffic'    => 5,
            'appLastVersion'    => 86400
        ];


        $calcTraffic = Settings::getSetting("calc_traffic");
        if ($calcTraffic == 0) {
            unset($schedules["syncTraffic"]);
        }

        $lastExecutionFile =  PATH_STORAGE . DS . "cron.json";

        foreach ($schedules as $key => $interval) {

            $lastExecutionTime = 0;
            if (file_exists($lastExecutionFile)) {
                $fileContent        = file_get_contents($lastExecutionFile);
                if (!empty($fileContent)) {
                    $data               = json_decode($fileContent, true);
                    $lastExecutionTime  = $data[$key] ?? 0;
                }
            }

            $currentTime = time();

            // Check if it's time to execute based on the interval
            if ($currentTime - $lastExecutionTime >= $interval) {
                $data[$key] = $currentTime;
                try {
                    $this->$key();
                } catch (\Throwable $e) {
                    // one failing task must not stop the others
                    @error_log("cron {$key}: " . $e->getMessage());
                }

                //save
                @file_put_contents($lastExecutionFile, json_encode($data));

                echo "Process '{$key}' executed at " . date('Y-m-d H:i:s') . "\n";
            }
        }
    }


    public function appLastVersion()
    {
        $lastVersion = githubLastVersion();

        if ($lastVersion) {
            @file_put_contents(PATH_STORAGE . DS . "last_version.txt", $lastVersion);
        }
    }

    /** accounts that exist in the database but not on the server (e.g. after a restore) */
    public function syncSysUsers()
    {
        (new \App\Models\Backup())->syncSysUsers(100);
    }

    public function onlineUsers()
    {
        $onlineUsers    = UserShell::onlineUsers();

        //save to json file
        setLocalOnlienUsers($onlineUsers);
    }

    private function multiUser()
    {
        $multiuser   = Settings::getSetting("multiuser");
        $onlineUsers = UserShell::onlineUsers();
        $uModel      = new Users();

        // the same snapshot feeds the "online users" pages (no second lsof run)
        setLocalOnlienUsers($onlineUsers);

        if (empty($onlineUsers)) {
            return;
        }

        // one query for every online account instead of one per account
        $rows = db('users')->whereIn('username', array_keys($onlineUsers))
            ->select('username', 'start_date', 'expiry_days', 'limit_users')->get();

        foreach ($rows as $userInfo) {
            $username = $userInfo->username;
            $users    = $onlineUsers[$username];

            if (!$userInfo->start_date) {
                $startDate = date("Y/m/d");
                $endDate   = date('Y/m/d', strtotime($startDate . " + {$userInfo->expiry_days} days"));
                $uModel->updateExpirydates($username, $startDate, $endDate);
            }

            // keep the oldest connections, drop the extra ones; the account itself is never disabled
            if ($multiuser && count($users) > $userInfo->limit_users) {
                UserShell::disableMultiUser($username, $users, $userInfo->limit_users);
            }
        }
    }

    public function syncTraffic()
    {

        $trafficFilePath = PATH_STORAGE . DS . "traffics.json";
        $tModel          = new Traffics();

        if (file_exists($trafficFilePath)) {
            $tcontent   = file_get_contents($trafficFilePath);
            $trafficlog = preg_split("/\r\n|\n|\r/", $tcontent);
            $trafficlog = array_filter($trafficlog);
            $lastdata   = end($trafficlog);
            $jsonData   = json_decode($lastdata, true);


            if (is_array($jsonData)) {

                $serverUsers    = UserShell::allUsers();
                $userTraffics   = [];
                foreach ($jsonData as $value) {
                    $TX         = round($value["TX"], 0);
                    $RX         = round($value["RX"], 0);
                    $username   = preg_replace("/\\s+/", "", $value["name"]);
                    $username   = str_replace("sshd:", "", $username);
                    if (in_array($username, $serverUsers)) {
                        if (isset($userTraffics[$username])) {
                            $userTraffics[$username]["TX"] + $TX;
                            $userTraffics[$username]["RX"] + $RX;
                        } else {
                            $userTraffics[$username] = ["RX" => $RX, "TX" => $TX, "Total" => $RX + $TX];
                        }
                    }
                }

                $trafficBase = 12;
                foreach ($userTraffics as $username => $data) {

                    $userTraffic = $tModel->getUserTraffic($username);

                    $rx     = round($data["RX"]);
                    $rx     = $rx / 10;
                    $rx     = round(($rx / $trafficBase) * 100);

                    $tx     = round($data["TX"]);
                    $tx     = $tx / 10;
                    $tx     = round(($tx / $trafficBase) * 100);
                    $total = $rx + $tx;

                    $trafficColumn = [
                        "upload"    => $tx,
                        "download"  => $rx,
                        "total"     => $total,
                        "username"  => $username,
                    ];
                    if ($userTraffic) {
                        $trafficColumn["upload"]    += $userTraffic->upload;
                        $trafficColumn["download"]  += $userTraffic->download;
                        $trafficColumn["total"]     += $userTraffic->total;
                        $trafficColumn["utime"]     = time();
                    } else {
                        $trafficColumn["ctime"]     = time();
                        $trafficColumn["utime"]     = 0;
                    }

                    Traffics::updateOrCreate(["username" => $username], $trafficColumn);
                }
            }
        }


        UserShell::createTrfficsLogFile($trafficFilePath);
    }

    /** accounts with a traffic cap (e.g. 50 MB tests): check often so they cannot overshoot */
    public function limitedTraffic()
    {
        $rows = db('users')
            ->join('traffics', 'traffics.username', '=', 'users.username')
            ->where('users.status', 'active')
            ->where('users.traffic', '>', 0)
            ->where('users.start_date', '>', 0)
            ->whereRaw('cp_traffics.total > cp_users.traffic')
            ->select('users.id', 'users.username')
            ->limit(200)->get();
        if (!$rows || !count($rows)) {
            return;
        }
        $uModel = new Users();
        foreach ($rows as $row) {
            UserShell::deactivateUser($row->username);
            $uModel->updateStatus($row->id, "expiry_traffic");
        }
    }

    public function expiredUsers()
    {
        $uModel         = new Users();
        $activeUsers    = $uModel->activeUsers();

        if ($activeUsers) {
            foreach ($activeUsers as $user) {
                $userId         = $user->id;
                $username       = $user->username;
                $totalTraffic   = $user->traffic;
                $cTraffic       = $user->consumer_traffic;
                $cTraffic       = $cTraffic ? $cTraffic : 0;

                $endDate        = $user->end_date;
                $startDate      = $user->start_date;

                if ($startDate) {
                    $isReset = false;
                    if (time() > $endDate) {
                        $isReset = true;
                    }

                    if (!$isReset) {
                        if ($totalTraffic && ($cTraffic > $totalTraffic)) {
                            $isReset = true;
                        }
                    }

                    if ($isReset) {
                        UserShell::deactivateUser($username);
                        if ($cTraffic >= $totalTraffic) {
                            $uModel->updateStatus($userId, "expiry_traffic");
                        } else {
                            $uModel->updateStatus($userId, "expiry_date");
                        }
                    }
                }
            }
        }
    }
}
