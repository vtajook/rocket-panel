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

    public function getUserBackups()
    {
        $backupPath = PATH_ASSETS . DS . "backup";
        $sqlFiles = array();

        $folderName = str_replace(PATH, "", $backupPath);


        $files = glob($backupPath . '/*.sql');
        foreach ($files as $file) {
            $fileTime   = filectime($file);
            $jdate      = Jalalian::forge($fileTime)->format('Y/m/d - H:i');
            $filename   =  basename($file);
            $sqlFiles[$fileTime] = [
                "name"  =>  $filename,
                "url"   =>  baseUrl("$folderName/$filename"),
                "date"  =>  $jdate,
            ];
        }
        arsort($sqlFiles);

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
            $backupPath     = PATH_STORAGE . DS . "backup";
            if (!is_dir($backupPath)) {
                mkdir($backupPath);
            }
            $backupFilePath = $backupPath . DS . "temp.sql";
            // create temp file
            file_put_contents($backupFilePath, $sqlContent);

            \App\Libraries\UserShell::restoreMysqlBackup($backupFilePath);
            unlink($backupFilePath);

            $this->createSysUsers();
            return count($usersValues);
        }

        return 0;
    }


    private function createSysUsers()
    {
        //run migrate
        $migrateUrl = baseUrl("migrate");
        @file_get_contents($migrateUrl);

        //create server users 
        $uModel        = new \App\Models\Users();
        $activeUsers   = $uModel->activeUsers();

        if ($activeUsers) {
            foreach ($activeUsers as $user) {
                $username = $user->username;
                $password = $user->password;
                usleep(500);
                UserShell::createUser($username, $password);
            }
        }
    }
}
