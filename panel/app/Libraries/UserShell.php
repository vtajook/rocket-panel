<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Libraries;

if (!defined('PATH')) die();


class UserShell
{
    public static function totalOnlineUsers()
    {
        $sshPort = getenv("PORT_SSH");
        $online = 0;

        $output = self::shellExec("sudo lsof -i :$sshPort -nP | grep -v root | grep ESTABLISHED |awk '{print $3}' |sort -u");

        if (!empty($output)) {
            $list = preg_split("/\r\n|\n|\r/", $output);
            $list = array_filter($list);

            $serverUsers = self::allUsers();
            foreach ($list  as $item) {
                if (in_array($item, $serverUsers)) {
                    $online++;
                }
            }
        }

        return   $online;
    }

    public static function onlineUsers()
    {
        $onlineUsers = [];
        $sshPort = getenv("PORT_SSH");

        $output  = self::shellExec("sudo lsof -i :$sshPort -nP | grep -v root | grep ESTABLISHED");

        if (!empty($output)) {
            $usersList      = preg_split("/\r\n|\n|\r/", $output);
            $validIpTypes   = ["http", "https", "ssh", $sshPort];
            $invlidUsers    = ["sshd", "SSHD"];

            foreach ($usersList as $user) {
                if (preg_match('/->([^ ]+) /', $user, $matches)) {
                    $user       = preg_replace("/\\s+/", " ", $user);
                    $userArr    = [];
                    if (strpos($user, ":AAAA") !== false) {
                        $userArr = explode(":", $user);
                    } else {
                        $userArr = explode(" ", $user);
                    }
                    if (count($userArr) == 10) {
                        $pid        = $userArr[1];
                        $username   = $userArr[2];
                        $ipVersion  = $userArr[4];
                        
                        if (!in_array($username, $invlidUsers)) {

                            $ipText         = $userArr[8];
                            $ipParts        = explode("->", $ipText);
                            $ipLeftParts    = $ipParts[0];
                            $ipRightParts   = $ipParts[1];

                            $userIp         = "";
                            $connType       = "";

                            if ($ipVersion == "IPv4") {
                                $connType       = explode(":", $ipLeftParts)[1];
                                $userIp         = explode(':', $ipRightParts)[0];
                            } else {
                                $ipv6Pattern = '/\[([a-fA-F0-9:]+)\]:[a-zA-Z0-9]+/';
                                if (preg_match($ipv6Pattern, $ipRightParts, $matches)) {
                                    $userIp     = $matches[1];
                                    $connType   = "https";
                                }
                            }

                            if (in_array($connType, $validIpTypes)) {
                                $userData = [
                                    "ip"        => $userIp,
                                    "pid"       => $pid
                                ];
                                $onlineUsers[$username][] = $userData;
                            }
                        }
                    }
                }
            }
        }

        return $onlineUsers;
    }

    public static function ramData()
    {
        $output = self::shellExec("free | grep Mem");

        $result = [
            "total"         => 0,
            "free"          => 0,
            "available"     => 0,
            "usage_percent" => 0,
            "usage_color"   => "",
            "usage_text_color" => "",
        ];

        if (!empty($output)) {
            $parts = preg_split('/\s+/', $output);

            if (!empty($parts) && count($parts) == 8) {
                $total      = intval($parts[1]) * 1024;
                $used       = intval($parts[2]) * 1024;
                $available  = intval($parts[6]) * 1024;

                $usagePercent = round(($used / $total) * 100);

                $result["total"]            = convertToPrettyUnit($total);
                $result["used"]             = convertToPrettyUnit($used);
                $result["available"]        = convertToPrettyUnit($available);
                $result["usage_percent"]    = $usagePercent;
                $result["usage_color"]      = getUsageColor($usagePercent);
                $result["usage_text_color"] = getContrastTextColor($result["usage_color"]);
            }
        }

        return $result;
    }

    public function uptimeDate()
    {
    }

    public static function cpuData()
    {

        $result         = [];
        $totalCores     = self::cpuCores();
        $totalCores     = $totalCores ? $totalCores : 1;

        $loadAvg        = sys_getloadavg();

        $cpuLoadAvg     = ($loadAvg[0] / ($totalCores + 1)) * 100;
        $cpuLoadAvg     = round($cpuLoadAvg, 2);

        $result["totalCores"]       = $totalCores;
        $result["loadAvg"]          = $cpuLoadAvg;
        $result["name"]             = self::cpuName();
        $result["usage_color"]      = getUsageColor($cpuLoadAvg);
        $result["usage_text_color"] = getContrastTextColor($result["usage_color"]);

        return $result;
    }

    public static function cpuName()
    {
        $cpuName = self::shellExec('grep "model name" /proc/cpuinfo | uniq');
        $cpuName = trim(str_replace("model name\t: ", "", $cpuName));
        return $cpuName;
    }

    public static function cpuCores()
    {
        $cpuCores = self::shellExec('nproc');
        return trim($cpuCores);
    }

    public static function serverUptime()
    {
        $uptime = self::shellExec('uptime -p');
        $uptime = str_replace("up", "", $uptime);
        $uptime = str_replace("hour", "ساعت", $uptime);
        $uptime = str_replace("hours", "ساعت", $uptime);
        $uptime = str_replace("minute", "دقیقه", $uptime);
        $uptime = str_replace("minutes", "دقیقه", $uptime);
        $uptime = str_replace("year", "سال", $uptime);
        $uptime = str_replace("years", "سال", $uptime);
        $uptime = str_replace("day", "روز", $uptime);
        $uptime = str_replace("s", "", $uptime);
        return $uptime;
    }

    public static function diskData()
    {
        $freeSpace  = disk_free_space('/');
        $totalSpace = disk_total_space('/');

        $usagePercent = round((1 - $freeSpace / $totalSpace) * 100);

        $color          = getUsageColor($usagePercent);
        $textColor      = getContrastTextColor($color);

        $result = [
            "free"              => convertToPrettyUnit($freeSpace),
            "total"             => convertToPrettyUnit($totalSpace),
            "usage_percent"     => $usagePercent,
            "usage_color"       => $color,
            "usage_text_color"  => $textColor
        ];

        return $result;
    }

    public static function serverTraffic()
    {
        $download   = self::traffixRx();
        $upload     = self::traffixTx();

        return [
            "download"  => convertToPrettyUnit($download),
            "upload"    => convertToPrettyUnit($upload),
            "total"     => convertToPrettyUnit($download + $upload),
        ];
    }

    public static function traffixRx($convert = false)
    {
        $download = 0;
        $output = self::shellExec("netstat -e -n -i |  grep \"RX packets\" | grep -v \"RX packets 0\" | grep -v \" B)\"");

        if (!empty($output)) {
            $output = preg_split("/\r\n|\n|\r/", $output);
            foreach ($output as $parts) {
                $partsArr = explode(" ", $parts);
                if (!isset($parts[13])) {
                    $partsArr[13] = null;
                }
                if (is_numeric($partsArr[13])) {
                    $download += $partsArr[13];
                }
            }
        }
        if ($convert) {
            return convertToPrettyUnit($download);
        }
        return ($download);
    }

    public static function traffixTx($convert = false)
    {
        $upload = 0;
        $output = self::shellExec("netstat -e -n -i |  grep \"TX packets\" | grep -v \"TX packets 0\" | grep -v \" B)\"");
        if (!empty($output)) {
            $output = preg_split("/\r\n|\n|\r/", $output);
            foreach ($output as $parts) {
                $partsArr = explode(" ", $parts);
                if (!isset($parts[13])) {
                    $partsArr[13] = null;
                }
                if (is_numeric($partsArr[13])) {
                    $upload += $partsArr[13];
                }
            }
        }
        if ($convert) {
            return convertToPrettyUnit($upload);
        }
        return ($upload);
    }

    public static function killUsers($pids = [])
    {
        foreach ($pids as $pid) {
            self::killUser($pid);
        }
    }

    public static function killUser($pid)
    {
        $pid = (int) $pid;
        if ($pid > 1) {
            self::shellExec("sudo kill -9 {$pid}");
        }
    }

    /** account names and passwords reach the shell: refuse anything unusual */
    private static function safeName($username)
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,31}$/', (string) $username)) {
            throw new \InvalidArgumentException("invalid username");
        }
        return $username;
    }

    private static function passwordCommand($username, $password)
    {
        $password = (string) $password;
        if ($password === '' || preg_match('/[\r\n\0]/', $password)) {
            throw new \InvalidArgumentException("invalid password");
        }
        // quoted here-doc: nothing inside is expanded by the shell
        return "sudo passwd $username <<'ROCKET_PW_END'\n$password\n$password\nROCKET_PW_END";
    }

    public static function createMysqlBackup($filePath = "")
    {
        $dbUsername = getenv("DB_USERNAME");
        $dbPassword = getenv("DB_PASSWORD");
        $dbName     = getenv("DB_DATABASE");

        self::shellExec("mysqldump -u '$dbUsername' --password='$dbPassword'  $dbName > '$filePath' ");
    }

    public static function restoreMysqlBackup($filePath = "")
    {

        $dbUsername = getenv("DB_USERNAME");
        $dbPassword = getenv("DB_PASSWORD");
        $dbName     = getenv("DB_DATABASE");

        self::shellExec("mysql -u '$dbUsername' --password='$dbPassword' $dbName < $filePath");
    }

    public static function createTrfficsLogFile($filePath)
    {
        $output = self::shellExec("pgrep nethogs");
        $pids   = preg_replace("/\\s+/", "", $output);
        self::shellExec("sudo kill -9 {$pids}");
        self::shellExec("sudo killall -9 nethogs");

        self::shellExec("sudo rm -rf $filePath");
        self::shellExec("sudo nethogs -v3 -c5 -j > $filePath");
        self::shellExec("sudo pkill nethogs");
    }

    public static function allUsers()
    {
        $usersList  = [];
        $output     = self::shellExec("ls /home");
        if (!empty($output)) {
            $usersList = preg_split("/\r\n|\n|\r/", $output);
            $usersList = array_filter($usersList);
        }

        return $usersList;
    }

    public static function createUser($username, $password)
    {
        self::safeName($username);
        $addUserCommand = "sudo adduser $username --force-badname --shell /usr/sbin/nologin &";
        $setPasswordCommand = self::passwordCommand($username, $password);
        $fullCommand = "$addUserCommand\nwait\n$setPasswordCommand";

        self::shellExec($fullCommand);
    }

    public static function updateUserPassword($username, $password)
    {
        self::safeName($username);
        $setPasswordCommand = self::passwordCommand($username, $password);
        self::shellExec($setPasswordCommand);
        self::userKill($username);
    }

    public static function deleteUser($username, $permanentDel = true)
    {
        self::safeName($username);
        self::userKill($username);
        self::shellExec("sudo userdel -r {$username}");
    }

    public static function userKill($username)
    {
        self::shellExec("sudo killall -u {$username}");
        self::shellExec("sudo pkill -u {$username}");
        self::shellExec("sudo timeout 10 pkill -u {$username}");
        self::shellExec("sudo timeout 10 killall -u {$username}");
    }

    public static function activateUser($username, $password)
    {
        self::createUser($username, $password);
    }

    public static function deactivateUser($username)
    {
        self::deleteUser($username);
    }

    public static function disableMultiUser($username, $users = [], $limitUsers = 1)
    {
        // Keep the oldest connections (up to the limit) and kill only the extra ones
        $pids = [];
        foreach ((array) $users as $u) {
            $pid = intval($u["pid"] ?? 0);
            if ($pid > 1) {
                $pids[$pid] = $pid;
            }
        }

        $limitUsers = max(1, intval($limitUsers));
        if (count($pids) <= $limitUsers) {
            if (empty($pids)) {
                self::userKill($username);
            }
            return;
        }

        sort($pids, SORT_NUMERIC);
        $extra = array_slice($pids, $limitUsers);
        foreach ($extra as $pid) {
            self::shellExec("sudo kill -9 {$pid}");
        }
    }

    public static function updateSshPort($newPort)
    {
        $command = "sudo sed -i 's/Port [0-9]*/Port $newPort/' /etc/ssh/sshd_config";
        self::shellExec($command);

        $command = "sudo sed -i 's/PORT_SSH=[0-9]*/PORT_SSH=$newPort/' /var/www/html/panel/.env";
        self::shellExec($command);

        $command = "sudo systemctl restart sshd";
        self::shellExec($command);
    }


    public static function updateUdpPort($newPort)
    {
        $command = "sudo sed -E -i 's/(127.0.0.1:[0-9]+)/127.0.0.1:$newPort/' /etc/systemd/system/videocall.service";
        self::shellExec($command);

        $command = "sudo sed -i 's/PORT_UDP=[0-9]*/PORT_UDP=$newPort/' /var/www/html/panel/.env";
        self::shellExec($command);

        $command = "sudo systemctl daemon-reload";
        self::shellExec($command);

        $command = "sudo systemctl restart videocall";
        self::shellExec($command);
    }


    public static function updateConnectedText($text)
    {
        $filePath = PATH  . DS . "banner.txt";
        $command = "echo $text > $filePath";
        self::shellExec($command);

        $filePath = preg_quote($filePath, '/');
        $command = "sudo sed -i 's/^\(Banner\s*\).*$/\\1$filePath/' /etc/ssh/sshd_config";
        self::shellExec($command);
    }

    public static function updateDomainUrl($newUrl)
    {
        $newUrl     = trim($newUrl, "/");
        $newUrl     = "$newUrl/cron/master";
        $scriptPath = "/var/www/html/cronjob.sh";

        $command = "sudo sed -i 's|curlUrl=\"[^\"]*\"|curlUrl=\"$newUrl\"|' $scriptPath";
        echo  $command;
        self::shellExec($command);
    }

    public static function rebootServer()
    {
        $command = 'sudo systemctl reboot';
        self::shellExec($command);
    }


    public static function updateFakeUrl($newSite)
    {
        $phpFilePath = "/var/www/html/index.php"; // Replace with your PHP file path

        // Prepare the sed command with sudo to modify the PHP file
        $sedCommand = "sudo sed -i 's#\\\$site = \".*\";#\\\$site = \"$newSite\";#' $phpFilePath";
        self::shellExec($sedCommand);
    }


    private static function shellExec($command)
    {
        if (!isDevMode()) {
            return shell_exec($command);
        }

        return "";
    }
}
