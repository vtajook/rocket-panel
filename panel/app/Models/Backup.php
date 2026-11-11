<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Models;

use Morilog\Jalali\Jalalian;
use \App\Libraries\UserShell;

class Backup extends \App\Models\BaseModel
{

    public function __construct()
    {
        ini_set('memory_limit', '256M');
        ini_set('max_execution_time', '0');
    }

    /** where the panel keeps the backups it creates: outside anything the web server serves */
    public static function backupDir()
    {
        $dir = PATH_STORAGE . DS . "backups";
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        // older versions kept them in assets/backup, which is public: move them in
        $old = PATH_ASSETS . DS . "backup";
        foreach ((array) glob($old . "/*.sql") as $file) {
            if (basename($file) !== "db.sql") {
                @rename($file, $dir . DS . basename($file));
            }
        }
        return $dir;
    }

    /** full path of one of our backup files, or null for any name that is not one */
    public static function backupFile($name)
    {
        $name = basename((string) $name);
        if (!preg_match('/^[A-Za-z0-9_.-]+\.sql$/', $name) || strpos($name, "..") !== false) {
            return null;
        }
        $path = self::backupDir() . DS . $name;
        return is_file($path) ? $path : null;
    }

    public function getUserBackups()
    {
        $backupPath = self::backupDir();
        $sqlFiles = array();

        $files = glob($backupPath . '/*.sql');
        foreach ((array) $files as $file) {
            $fileTime   = filectime($file);
            $jdate      = Jalalian::forge($fileTime)->format('Y/m/d - H:i');
            $filename   =  basename($file);
            $sqlFiles[$fileTime] = [
                "name"  =>  $filename,
                "url"   =>  baseUrl("settings/backup/download/" . rawurlencode($filename)),
                "date"  =>  $jdate,
            ];
        }
        krsort($sqlFiles);

        return array_values($sqlFiles);
    }

    public function importFromDragon($content)
    {
        if (!empty($content)) {
            $arrRows = explode("\n", $content);

            if (!empty($arrRows)) {
                $arrInsert      = [];
                $adminUsername  = getAdminUsername();

                foreach ($arrRows  as $row) {
                    $arrColumns = explode("•", $row);
                    if (count($arrColumns) == 4) {
                        $username   = trim($arrColumns[0]);
                        $password   = trim($arrColumns[1]);
                        $limitUsers = trim($arrColumns[2]);
                        $expiryDays = trim($arrColumns[3]);

                        if (stripos($expiryDays, 'DIAS') !== false) {
                            $expiryDays = str_replace("DIAS", "", $expiryDays);
                            $expiryDays = intval($expiryDays);
                        } else {
                            $expiryDays = 30;
                        }

                        $startDate  = time();
                        $endDate    = time() + ($expiryDays * 86400);

                        $status     = "active";

                        $arrInsert[$username] = [
                            "username"          => $username,
                            "admin_uname"       => $adminUsername,
                            "password"          => $password,
                            "email"             => "",
                            "mobile"            => "",
                            "desc"              => "",
                            "start_date"        => $startDate,
                            "end_date"          => $endDate,
                            "status"            => $status,
                            "expiry_days"       => $expiryDays,
                            "traffic"           => 0,
                            "limit_users"       => $limitUsers,
                            "ctime"             => time(),
                            "utime"             =>  0,
                        ];
                    }
                }



                try {
                    $totalInsert =  db()::transaction(function () use ($arrInsert) {

                        $totalInsert = 0;

                        $uModle      = new \App\Models\Users();
                        $nextUserId  = $uModle->getNextUserId();

                        foreach ($arrInsert as $key => $user) {
                            $username       =  $user["username"];
                            $checkExistUser =  db("users")->where("username",  $username)->count();

                            if (!$checkExistUser) {
                                $token          = generateUserToken($nextUserId);
                                $user["token"]  = $token;

                                $userId         = db("users")->insertGetId($user);
                                $nextUserId     = $userId + 1;

                                $totalInsert++;
                                db("traffics")->insert([
                                    "username"  => $username,
                                    "download"  => 0,
                                    "upload"    => 0,
                                    "total"     => 0,
                                    "ctime"     => time(),
                                    "utime"     => 0,
                                ]);
                            }
                        }

                        $this->createSysUsers();
                        return $totalInsert;
                    });

                    return $totalInsert;
                } catch (\Exception $err) {
                    db()::rollback();
                    throw $err->getMessage();
                }
            }
        }
    }

    public function importBackupFromShahan($values)
    {
        $usersValues    = !empty($values["users"])      ? $values["users"] : [];
        $traficValues   = !empty($values["Traffic"])    ? $values["Traffic"] : [];

        $insertUsers     = [];
        $insetTraffics  = [];
        $adminUsername  = getAdminUsername();

        $invalidUsers = ["root"];


        foreach ($traficValues as $traffic) {
            $username   = "";
            $download = $upload  = $total = 0;
            if (!empty($traffic["username"])) {
                $username = $traffic["username"];
            } else if (!empty($traffic["user"])) {
                $username = $traffic["user"];
            }
            if (!empty($traffic["download"])) {
                $download = intval($traffic["download"]);
            }
            if (!empty($traffic["upload"])) {
                $upload = intval($traffic["upload"]);
            }
            if (!empty($traffic["total"])) {
                $total = intval($traffic["total"]);
            }
            if ($username) {
                $insetTraffics[$username] = [
                    "username"  => $username,
                    "download"  => $download,
                    "upload"    => $upload,
                    "total"     => $total,
                    "ctime"     => time(),
                    "utime"     => 0,
                ];
            }
        }

        foreach ($usersValues  as $user) {
            $username =  $password = $email = $mobile = $info = $status = "";
            $multiuser = 1;
            $startDate = 0;
            $endDate = 0;
            $traffic = 0;
            $days = 0;
            if (!empty($user["username"])) {
                $username = $user["username"];
            }
            if (!empty($user["password"])) {
                $password = $user["password"];
            }

            if (!empty($user["email"]) && $user["email"] != "NULL") {
                $email = $user["email"];
            }

            if (!empty($user["mobile"]) && $user["mobile"] != "NULL") {
                $mobile = $user["mobile"];
            }

            if (!empty($user["multiuser"])) {
                $multiuser = $user["multiuser"];
            }

            if (!empty($user["startdate"])) {
                $startDate = $user["startdate"];
            }

            if (!empty($user["finishdate"])) {
                $endDate = $user["finishdate"];
            }

            if (!empty($user["traffic"])) {
                $traffic = $user["traffic"];
            }

            if (!empty($user["days"]) && $user["days"] != "NULL") {
                $days = $user["days"];
            }

            if (!empty($user["info"])) {
                $info = $user["info"];
            } else if (!empty($user["desc"]) && $user["desc"] != "NULL") {
                $info = $user["desc"];
            }

            if (isset($user["enable"])) {
                $status = $user["enable"];
                if ($status == "true") {
                    $status = "active";
                } else if ($status == "false" || $status == "expired") {
                    $status = "de_active";
                }
            }

            if (!empty($username) && !empty($password) && !in_array($username, $invalidUsers)) {

                $status      = !empty($status) ? $status : "active";
                $endDate     = $endDate && strtotime($endDate) ? strtotime(adjustDateTime($endDate)) : 0;
                $startDate   = $startDate && strtotime($startDate) ? strtotime(adjustDateTime($startDate)) : 0;

                if ($days) {
                    $days = convertToEnNum($days);
                } else {
                    if ($startDate && $endDate) {
                        $days = floor(($endDate -  $startDate) / 86400);
                    }
                }

                if ($endDate && !$startDate) {
                    if (isset($insetTraffics[$username])) {
                        $ـdays = time() - 86400;
                        if (time() < $endDate) {
                            $ـdays = floor(($endDate -  time()) / 86400);
                        }
                        if ($ـdays > 365) {
                            $ـdays = 365;
                        }
                        $startDate = strtotime("-$ـdays days", $endDate);
                    }
                }


                $traffic = intval($traffic);

                $insertUsers[$username] = [
                    "username"          => $username,
                    "admin_uname"       => $adminUsername,
                    "password"          => $password,
                    "email"             => $email,
                    "mobile"            => $mobile,
                    "desc"              => $info,
                    "start_date"        => $startDate,
                    "end_date"          => $endDate,
                    "status"            => $status,
                    "expiry_days"       => $days ? abs($days) : 0,
                    "traffic"           => $traffic ? $traffic * 1024 : $traffic,
                    "limit_users"       => $multiuser,
                    "ctime"             => time(),
                    "utime"             => 0,
                ];
            }
        }


        try {

            $totalInsert =  db()::transaction(function () use ($insertUsers, $insetTraffics) {

                $insertUsers = array_values($insertUsers);
                $totalInsert = 0;

                $uModle      = new \App\Models\Users();
                $nextUserId  = $uModle->getNextUserId();
                foreach ($insertUsers as $key => $user) {
                    $username = $user["username"];
                    $checkExistUser =  db("users")->where("username",  $username)->count();

                    if (!$checkExistUser) {

                        $token          = generateUserToken($nextUserId);
                        $user["token"]  = $token;

                        $userId         = db("users")->insertGetId($user);
                        $nextUserId     = $userId + 1;

                        $totalInsert++;

                        $userTraffic = isset($insetTraffics[$username]) ? $insetTraffics[$username] : false;
                        if ($userTraffic) {
                            db("traffics")->insert($userTraffic);
                        } else {
                            db("traffics")->insert([
                                "username"  => $username,
                                "download"  => 0,
                                "upload"    => 0,
                                "total"     => 0,
                                "ctime"     => time(),
                                "utime"     => 0,
                            ]);
                        }
                    }
                }
                $this->createSysUsers();

                return $totalInsert;
            });

            return $totalInsert;
        } catch (\Exception $err) {
            echo $err->getMessage();
            db()::rollback();
            throw  $err;
        }
    }

    public function importBackupFromXpanel($values)
    {
        $usersValues    = !empty($values["users"])      ? $values["users"] : [];
        $traficValues1  = !empty($values["traffic"])    ? $values["traffic"] : [];
        $traficValues2  = !empty($values["Traffic"])    ? $values["Traffic"] : [];
        $traficValues   = !empty($traficValues1) ? $traficValues1 : $traficValues2;



        $insertUsers    = [];
        $insetTraffics  = [];
        $adminUsername  = getAdminUsername();

        foreach ($usersValues  as $user) {
            $username =  $password = $email = $mobile = $info = "";
            $multiuser = 1;
            $startDate = 0;
            $endDate = 0;
            $traffic = 0;
            $days = 0;
            $status = "";
            $createdAt = 0;
            $updatedAt = 0;

            if (!empty($user["username"])) {
                $username = $user["username"];
            }

            if (!empty($user["password"])) {
                $password = $user["password"];
            }

            if (!empty($user["email"]) && $user["email"] != "NULL") {
                $email = $user["email"];
            }

            if (!empty($user["mobile"]) && $user["mobile"] != "NULL") {
                $mobile = $user["mobile"];
            }

            if (!empty($user["multiuser"])) {
                $multiuser = $user["multiuser"];
            }

            if (!empty($user["start_date"])) {
                $startDate = $user["start_date"];
            } else if (!empty($user["startdate"])) {
                $startDate = $user["startdate"];
            }

            if (!empty($user["end_date"])) {
                $endDate = $user["end_date"];
            } else if (!empty($user["finishdate"])) {
                $endDate = $user["finishdate"];
            }

            if (!empty($user["traffic"])) {
                $traffic = $user["traffic"];
            }

            if (!empty($user["date_one_connect"]) && $user["date_one_connect"] != "NULL") {
                $days = $user["date_one_connect"];
            }

            if (!empty($user["desc"]) && $user["desc"] != "NULL") {
                $info = $user["desc"];
            }

            if (!empty($user["created_at"]) && strtotime($user["created_at"])) {
                $createdAt = strtotime($user["created_at"]);
            }

            if (!empty($user["updated_at"]) && strtotime($user["updated_at"])) {
                $updatedAt = strtotime($user["updated_at"]);
            }
            if (!empty($user["status"])) {
                $status = $user["status"];
                if ($status == "deactive" || $status == "expired") {
                    $status = "de_active";
                }
            } else if (isset($user["enable"])) {
                $status = $user["enable"];
                if ($status == "true") {
                    $status = "active";
                } else {
                    $status = "de_active";
                }
            }

            if (!empty($username) && !empty($password)) {

                $endDate     = $endDate && strtotime($endDate) ? strtotime(adjustDateTime($endDate)) : 0;
                $startDate   = $startDate && strtotime($startDate) ? strtotime(adjustDateTime($startDate)) : 0;

                if ($days) {
                    $days = convertToEnNum($days);
                } else {
                    if ($startDate && $endDate) {
                        $days = floor(($endDate -  $startDate) / 86400);
                    }
                }

                $traffic = intval($traffic);

                $insertUsers[$username] = [
                    "username"          => $username,
                    "admin_uname"       => $adminUsername,
                    "password"          => $password,
                    "email"             => $email,
                    "mobile"            => $mobile,
                    "desc"              => $info,
                    "start_date"        => $startDate,
                    "end_date"          => $endDate,
                    "status"            => $status,
                    "expiry_days"       => $days ? $days : 0,
                    "traffic"           => $traffic ? $traffic * 1024 : $traffic,
                    "limit_users"       => $multiuser,
                    "ctime"             => $createdAt ? $createdAt : time(),
                    "utime"             => $updatedAt ? $updatedAt : 0,
                ];
            }
        }

        foreach ($traficValues as $traffic) {
            $username   = "";
            $download = $upload  = $total = 0;
            if (!empty($traffic["username"])) {
                $username = $traffic["username"];
            } else if (!empty($traffic["user"])) {
                $username = $traffic["user"];
            }

            if (!empty($traffic["download"])) {
                $download = intval($traffic["download"]);
            }
            if (!empty($traffic["upload"])) {
                $upload = intval($traffic["upload"]);
            }
            if (!empty($traffic["total"])) {
                $total = intval($traffic["total"]);
            }
            if ($username) {
                $insetTraffics[$username] = [
                    "username"  => $username,
                    "download"  => $download,
                    "upload"    => $upload,
                    "total"     => $total,
                    "ctime"     => time(),
                    "utime"     => 0,
                ];
            }
        }

        try {
            $totalInsert =  db()::transaction(function () use ($insertUsers, $insetTraffics) {

                $insertUsers = array_values($insertUsers);
                $totalInsert = 0;

                $uModle      = new \App\Models\Users();
                $nextUserId  = $uModle->getNextUserId();

                foreach ($insertUsers as $key => $user) {
                    $username   =  $user["username"];

                    $checkExistUser =  db("users")->where("username",  $username)->count();
                    if (!$checkExistUser) {
                        $token          = generateUserToken($nextUserId);
                        $user["token"]  = $token;

                        $userId         = db("users")->insertGetId($user);
                        $nextUserId     = $userId + 1;

                        $totalInsert++;

                        $userTraffic = isset($insetTraffics[$username]) ? $insetTraffics[$username] : false;
                        if ($userTraffic) {
                            db("traffics")->insert($userTraffic);
                        } else {
                            db("traffics")->insert([
                                "username"  => $username,
                                "download"  => 0,
                                "upload"    => 0,
                                "total"     => 0,
                                "ctime"     => time(),
                                "utime"     => 0,
                            ]);
                        }
                    }
                }

                $this->createSysUsers();
                return $totalInsert;
            });

            return $totalInsert;
        } catch (\Exception $err) {
            db()::rollback();
            throw $err->getMessage();
        }
    }


    public function importSelfBackup($sqlContent, $values)
    {


        $usersValues    = !empty($values["cp_users"])       ? $values["cp_users"] : [];
        $traficValues   = !empty($values["cp_traffics"])    ? $values["cp_traffics"] : [];


        if (!empty($usersValues) && !empty($traficValues)) {
            if (strpos($sqlContent, '-- Dump completed') === false) {
                throw new \Exception("فایل بکاپ ناقص است (انتهای فایل بریده شده). دوباره بکاپ بگیرید.");
            }
            $backupPath     = PATH_STORAGE . DS . "backup";
            if (!is_dir($backupPath)) {
                mkdir($backupPath);
            }

            // settings that belong to THIS server (ports, Telegram) survive the restore
            $keep = [];
            foreach (db('settings')->where(function ($q) {
                $q->whereIn('name', ['ssh_port', 'udp_port'])->orWhere('name', 'like', 'tgb\\_%');
            })->get() as $r) {
                $keep[$r->name] = $r->value;
            }
            $myId = (int) (container()->userInfo->id ?? 0);

            // safety copy of the current database, restored automatically if the import fails
            $safe = $backupPath . DS . "before-import.sql";
            \App\Libraries\UserShell::createMysqlBackup($safe);
            $safeOk = \App\Libraries\UserShell::isCompleteDump($safe);

            $backupFilePath = $backupPath . DS . "temp.sql";
            file_put_contents($backupFilePath, $sqlContent);
            $ok = \App\Libraries\UserShell::restoreMysqlBackup($backupFilePath);
            @unlink($backupFilePath);

            if (!$ok) {
                if ($safeOk) {
                    \App\Libraries\UserShell::restoreMysqlBackup($safe);
                }
                throw new \Exception("بازگردانی با خطا روبرو شد" . ($safeOk ? " و اطلاعات قبلی برگردانده شد" : ""));
            }

            $this->createSysUsers();

            foreach ($keep as $name => $value) {
                $this->setSettingValue($name, $value);
            }
            @unlink($safe);
            // the restored admin table starts with token_ver 0: keep this session valid
            if ($myId) {
                $row = db('admins')->where('id', $myId)->first();
                if ($row && isset($row->token_ver)) {
                    session()->set("token_ver", (int) $row->token_ver);
                }
            }
            return count($usersValues);
        }

        return 0;
    }


    private function setSettingValue($name, $value)
    {
        $q = db('settings')->where('name', $name);
        if ($q->count()) {
            $q->update(['value' => $value]);
        } else {
            db('settings')->insert(['name' => $name, 'value' => $value]);
        }
    }

    /**
     * Names with spaces around them (seen in old backups) can never be a server user:
     * trim them in the database when the trimmed name is free. Returns how many were fixed.
     */
    private function fixWhitespaceNames()
    {
        $fixed = 0;
        $rows  = db('users')->whereRaw("username <> TRIM(username)")->select('id', 'username')->limit(200)->get();
        foreach ($rows as $r) {
            $new = trim($r->username);
            if ($new === '' || db('users')->where('username', $new)->count()) {
                continue;
            }
            db('users')->where('id', $r->id)->update(['username' => $new]);
            db('traffics')->where('username', $r->username)->update(['username' => $new]);
            $fixed++;
        }
        return $fixed;
    }

    private function createSysUsers()
    {
        // the restored database may lack the agency tables: rebuild them
        \App\Agency\Schema::invalidate();
        \App\Agency\Schema::ensure();

        //run migrate
        $migrateUrl = baseUrl("migrate");
        @file_get_contents($migrateUrl);

        // create the first batch now; the cron job finishes the rest within minutes
        $this->syncSysUsers(200);
    }

    /**
     * Make sure every active account of the database exists as a server user.
     * Only the missing ones are created, one failing account never stops the
     * others, and a lock keeps two runs from working at the same time.
     * Returns ["created" => n, "missing" => accounts still missing after this run].
     */
    public function syncSysUsers($limit = 100)
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $lock = @fopen(PATH_STORAGE . DS . "sync_users.lock", "c");
        if ($lock && !@flock($lock, LOCK_EX | LOCK_NB)) {
            return ["created" => 0, "missing" => -1];
        }

        $existing = [];
        foreach ((array) @file("/etc/passwd", FILE_IGNORE_NEW_LINES) as $line) {
            $existing[strstr($line, ":", true)] = true;
        }

        try {
            $this->fixWhitespaceNames();
        } catch (\Throwable $e) {
        }

        $created = 0;
        $missing = 0;
        $invalid = [];
        $budget  = microtime(true) + 22;     // one cron run never works longer than this
        // only the two columns needed, no join: this runs every minute
        foreach (db('users')->where('status', 'active')->select('username', 'password')->get() as $user) {
            if (isset($existing[$user->username]) || (string) $user->password === '') {
                continue;
            }
            if (($limit > 0 && $created >= $limit) || microtime(true) > $budget) {
                $missing++;
                continue;
            }
            try {
                UserShell::createUser($user->username, $user->password);
                $created++;
            } catch (\Exception $e) {
                // unusual name or password: skip this account only
                $invalid[] = $user->username;
            }
        }

        if ($lock) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
        return ["created" => $created, "missing" => $missing, "invalid" => array_slice($invalid, 0, 20)];
    }
}
