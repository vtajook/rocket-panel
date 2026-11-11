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
            'appLastVersion'    => 86400,
            'tgBackup'          => 10,
            'tgUsage'           => 60,
            'tgExpiry'          => 900
        ];


        $calcTraffic = Settings::getSetting("calc_traffic");
        if ($calcTraffic == 0) {
            unset($schedules["syncTraffic"]);
        }

        $lastExecutionFile =  PATH_STORAGE . DS . "cron.json";

        // read the schedule state once per tick (was once per task)
        $data = [];
        if (is_file($lastExecutionFile)) {
            $decoded = json_decode((string) @file_get_contents($lastExecutionFile), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $currentTime = time();
        $changed     = false;

        foreach ($schedules as $key => $interval) {
            $lastExecutionTime = isset($data[$key]) ? (int) $data[$key] : 0;

            if ($currentTime - $lastExecutionTime >= $interval) {
                $data[$key] = $currentTime;
                $changed    = true;
                try {
                    $this->$key();
                } catch (\Throwable $e) {
                    // one failing task must not stop the others
                    @error_log("cron {$key}: " . $e->getMessage());
                }
                echo "Process '{$key}' executed at " . date('Y-m-d H:i:s') . "\n";
            }
        }

        if ($changed) {
            @file_put_contents($lastExecutionFile, json_encode($data), LOCK_EX);
        }
    }


    public function tgBackup()
    {
        \App\Libraries\TgNotify::cronBackup();
    }

    public function tgUsage()
    {
        \App\Libraries\TgNotify::cronUsage();
    }

    public function tgExpiry()
    {
        \App\Libraries\TgNotify::cronExpiry();
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
            ->select('username', 'admin_uname', 'start_date', 'expiry_days', 'limit_users')->get();
        $frozen = array_flip(\App\Agency\Agency::frozenOwnerUsernames());

        foreach ($rows as $userInfo) {
            $username = $userInfo->username;
            $users    = $onlineUsers[$username];

            if (!$userInfo->start_date) {
                $startDate = date("Y/m/d");
                $endDate   = date('Y/m/d', strtotime($startDate . " + {$userInfo->expiry_days} days"));
                $uModel->updateExpirydates($username, $startDate, $endDate);
            }

            // a frozen reseller's accounts may not stay connected (accounts themselves are untouched)
            if (isset($frozen[$userInfo->admin_uname])) {
                foreach ($users as $u) {
                    UserShell::killUser(isset($u["pid"]) ? $u["pid"] : 0);
                }
                continue;
            }

            // keep the oldest connections, drop the extra ones; the account itself is never disabled
            if ($multiuser && count($users) > $userInfo->limit_users) {
                UserShell::disableMultiUser($username, $users, $userInfo->limit_users);
            }
        }
    }

    /**
     * Traffic accounting from the kernel's own per-connection counters (`ss -tinp`):
     * bytes sent to / received from each SSH client socket. One cheap command per run,
     * instead of nethogs sniffing every packet for 5 seconds (that was the CPU hog).
     * Counted in MB like the rest of the panel. The first run only records a baseline.
     */
    public function syncTraffic()
    {
        $port = (int) getenv("PORT_SSH");
        if ($port < 1) {
            return;
        }
        $out = UserShell::connectionCounters($port);
        if ($out === null) {
            return;
        }

        // pid -> account name, from the online snapshot taken by multiUser a moment ago
        $pidUser = [];
        foreach ((array) getLocalOnlienUsers() as $username => $sessions) {
            foreach ((array) $sessions as $session) {
                if (!empty($session["pid"])) {
                    $pidUser[(string) $session["pid"]] = $username;
                }
            }
        }

        $stateFile = PATH_STORAGE . DS . "traffic_state.json";
        $hadState  = is_file($stateFile);
        $old       = $hadState ? json_decode((string) @file_get_contents($stateFile), true) : [];
        $old       = is_array($old) ? $old : [];
        $new       = [];
        $perUser   = [];

        foreach ($out as $conn) {
            $user = null;
            foreach ($conn["pids"] as $pid) {
                if (isset($pidUser[$pid])) {
                    $user = $pidUser[$pid];
                    break;
                }
            }
            $key = $conn["peer"] . "|" . implode(",", $conn["pids"]);
            $new[$key] = [$conn["acked"], $conn["recv"]];
            if ($user === null || !$hadState) {
                continue;
            }
            $before = isset($old[$key]) ? $old[$key] : [0, 0];
            $dl = $conn["acked"] - $before[0];
            $ul = $conn["recv"]  - $before[1];
            if ($dl < 0 || $ul < 0) {      // counters restarted (reused port): count what is there now
                $dl = $conn["acked"];
                $ul = $conn["recv"];
            }
            if ($dl > 0 || $ul > 0) {
                if (!isset($perUser[$user])) {
                    $perUser[$user] = [0, 0];
                }
                $perUser[$user][0] += $dl;
                $perUser[$user][1] += $ul;
            }
        }
        @file_put_contents($stateFile, json_encode($new), LOCK_EX);

        $mb  = 1048576;
        $now = time();
        foreach ($perUser as $username => $d) {
            $dl = $d[0] / $mb;
            $ul = $d[1] / $mb;
            if ($dl + $ul < 0.0005) {
                continue;
            }
            $n = db("traffics")->where("username", $username)->update([
                "download" => new \Illuminate\Database\Query\Expression(sprintf("download + %.6F", $dl)),
                "upload"   => new \Illuminate\Database\Query\Expression(sprintf("upload + %.6F", $ul)),
                "total"    => new \Illuminate\Database\Query\Expression(sprintf("total + %.6F", $dl + $ul)),
                "utime"    => $now,
            ]);
            if (!$n) {
                try {
                    db("traffics")->insert(["username" => $username, "download" => $dl, "upload" => $ul, "total" => $dl + $ul, "ctime" => $now, "utime" => 0]);
                } catch (\Throwable $e) {
                    // row created by a parallel run: add to it instead
                    db("traffics")->where("username", $username)->update([
                        "download" => new \Illuminate\Database\Query\Expression(sprintf("download + %.6F", $dl)),
                        "upload"   => new \Illuminate\Database\Query\Expression(sprintf("upload + %.6F", $ul)),
                        "total"    => new \Illuminate\Database\Query\Expression(sprintf("total + %.6F", $dl + $ul)),
                    ]);
                }
            }
        }
    }

    /** the old nethogs based accounting (kept for reference, no longer scheduled) */
    public function syncTrafficNethogs()
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
                            // one account can show up several times (one line per ssh process)
                            $userTraffics[$username]["TX"] += $TX;
                            $userTraffics[$username]["RX"] += $RX;
                            $userTraffics[$username]["Total"] += $RX + $TX;
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
        // only the accounts that really are over (date or traffic), picked by the database
        $rows = db('users')
            ->leftJoin('traffics', 'traffics.username', '=', 'users.username')
            ->where('users.status', 'active')
            ->where('users.start_date', '>', 0)
            ->where(function ($q) {
                $q->where('users.end_date', '<', time())
                  ->orWhere(function ($q2) {
                      $q2->where('users.traffic', '>', 0)->whereRaw('cp_traffics.total > cp_users.traffic');
                  });
            })
            ->select('users.id', 'users.username', 'users.traffic', 'traffics.total as consumer_traffic')
            ->limit(500)->get();

        if (!$rows || !count($rows)) {
            return;
        }
        $uModel = new Users();
        foreach ($rows as $user) {
            $totalTraffic = $user->traffic;
            $cTraffic     = $user->consumer_traffic ? $user->consumer_traffic : 0;
            UserShell::deactivateUser($user->username);
            $uModel->updateStatus($user->id, ($totalTraffic && $cTraffic >= $totalTraffic) ? "expiry_traffic" : "expiry_date");
        }
    }
}
