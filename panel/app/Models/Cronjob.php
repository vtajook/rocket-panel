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
            'onlineUsers'       => 15,
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
                $this->$key();

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
            $where  = ["name" => "app_last_version"];
            $values = ["name" => "app_last_version", "value" => $lastVersion];
            \App\Models\Settings::updateOrCreate($where, $values);
        }
    }

    public function onlineUsers()
    {
        $onlineUsers    = UserShell::onlineUsers();

        //save to json file
        setLocalOnlienUsers($onlineUsers);
    }

    private function multiUser()
    {

        $multiuser      = Settings::getSetting("multiuser");
        $onlineUsers    = UserShell::onlineUsers();
        $uModel         = new Users();

        if (!empty($onlineUsers)) {
            $onUsers     = [];

            foreach ($onlineUsers as $username => $users) {
                $userInfo =  $uModel->getByUsername($username);
                if ($userInfo) {
                    $expiryDays   = $userInfo->expiry_days;

                    if (!$userInfo->start_date) {
                        $startDate  = date("Y/m/d");
                        $endDate    = date('Y/m/d', strtotime($startDate . " + $expiryDays days"));
                        $uModel->updateExpirydates($username, $startDate, $endDate);
                    }

                    $onUsers[$username] = ["users" => $users, "userInfo" => $userInfo];
                }
            }

           
            if ($multiuser) {

                foreach ($onUsers as $username => $value) {
                    $userInfo       = $value["userInfo"];
                    $limitUsers     = $userInfo->limit_users;
                    $users          = $value["users"];

                    if ($userInfo) {
                        if ($multiuser && count($users) > $limitUsers) {
                            UserShell::disableMultiUser($username, $users, $limitUsers);
                        }
                    }
                }
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
