<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Libraries;

if (!defined('PATH')) die();


class UserShell
{
    /** how many accounts are connected: taken from the snapshot the cron keeps fresh (no lsof, no directory listing) */
    public static function totalOnlineUsers()
    {
        $file = PATH_STORAGE . DS . "online.json";
        if (is_file($file) && time() - filemtime($file) < 120) {
            $list = json_decode((string) @file_get_contents($file), true);
            return is_array($list) ? count($list) : 0;
        }
        return count(self::onlineUsers());
    }

    /**
     * Online accounts. Fast path: one `ss` call, the account of each sshd process is read from
     * /proc (lsof walks every open file of every process and was the heaviest part of the cron).
     * Falls back to the lsof method when ss is not allowed.
     */
    public static function onlineUsers()
    {
        $port = (int) getenv("PORT_SSH");
        if ($port >= 1) {
            $text = self::shellExec("sudo -n ss -H -tnp state established '( sport = :$port )' 2>/dev/null; echo __RC:\$?");
            if (is_string($text) && preg_match('/__RC:0\s*$/', $text)) {
                return self::parseOnlineSs($text);
            }
        }
        return self::onlineUsersLsof();
    }

    public static function parseOnlineSs($text, $uidResolver = null)
    {
        $online = [];
        $skip   = ["sshd" => 1, "SSHD" => 1];
        foreach (preg_split("/\r\n|\n|\r/", (string) $text) as $line) {
            if (strpos($line, 'users:') === false || !preg_match_all('/pid=(\d+)/', $line, $m)) {
                continue;
            }
            $cols = preg_split('/\s+/', trim(substr($line, 0, strpos($line, 'users:'))));
            $peer = end($cols);
            if ($peer === false || ($c = strrpos($peer, ':')) === false) {
                continue;
            }
            $ip = trim(substr($peer, 0, $c), '[]');
            if (stripos($ip, '::ffff:') === 0) {
                $ip = substr($ip, 7);
            }
            foreach (array_unique($m[1]) as $pid) {
                $name = $uidResolver ? call_user_func($uidResolver, $pid) : self::pidUser($pid);
                if ($name === null || $name === 'root' || isset($skip[$name])) {
                    continue;
                }
                $online[$name][] = ["ip" => $ip, "pid" => (string) $pid];
                break;   // the unprivileged sshd of this connection found
            }
        }
        return $online;
    }

    private static function pidUser($pid)
    {
        $status = @file_get_contents("/proc/" . (int) $pid . "/status");
        if ($status === false || !preg_match('/^Uid:\s+(\d+)/m', $status, $m)) {
            return null;
        }
        if ((int) $m[1] === 0) {
            return 'root';
        }
        $pw = function_exists('posix_getpwuid') ? @posix_getpwuid((int) $m[1]) : false;
        return $pw ? $pw['name'] : null;
    }

    public static function onlineUsersLsof()
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
        $result = [
            "total"         => 0,
            "free"          => 0,
            "available"     => 0,
            "usage_percent" => 0,
            "usage_color"   => "",
            "usage_text_color" => "",
        ];

        $info = @file_get_contents('/proc/meminfo');
        if ($info && preg_match('/MemTotal:\s+(\d+)/', $info, $t) && preg_match('/MemAvailable:\s+(\d+)/', $info, $a) && $t[1] > 0) {
            $total     = (int) $t[1] * 1024;
            $available = (int) $a[1] * 1024;
            $used      = $total - $available;
            $usagePercent = round(($used / $total) * 100);

            $result["total"]            = convertToPrettyUnit($total);
            $result["used"]             = convertToPrettyUnit($used);
            $result["available"]        = convertToPrettyUnit($available);
            $result["usage_percent"]    = $usagePercent;
            $result["usage_color"]      = getUsageColor($usagePercent);
            $result["usage_text_color"] = getContrastTextColor($result["usage_color"]);
        }

        return $result;
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
        $info = @file_get_contents('/proc/cpuinfo');
        if ($info && preg_match('/^model name\s*:\s*(.+)$/m', $info, $m)) {
            return trim($m[1]);
        }
        return "";
    }

    public static function cpuCores()
    {
        $info = @file_get_contents('/proc/cpuinfo');
        $n    = $info ? preg_match_all('/^processor\s*:/m', $info) : 0;
        return $n > 0 ? $n : 1;
    }

    public static function serverUptime()
    {
        $raw = @file_get_contents('/proc/uptime');
        if (!$raw) {
            return "";
        }
        $sec   = (int) floatval($raw);
        $days  = intdiv($sec, 86400);
        $hours = intdiv($sec % 86400, 3600);
        $mins  = intdiv($sec % 3600, 60);
        $parts = [];
        if ($days)  { $parts[] = $days . " روز"; }
        if ($hours) { $parts[] = $hours . " ساعت"; }
        $parts[] = $mins . " دقیقه";
        return " " . implode("، ", $parts);
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

    /** total bytes received by the server's network cards since boot (loopback excluded) */
    public static function traffixRx($convert = false)
    {
        $v = self::netBytes(0);
        return $convert ? convertToPrettyUnit($v) : $v;
    }


    private static function netBytes($col)
    {
        $sum = 0;
        foreach ((array) @file('/proc/net/dev', FILE_IGNORE_NEW_LINES) as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }
            list($iface, $rest) = explode(':', $line, 2);
            if (trim($iface) === 'lo') {
                continue;
            }
            $f = preg_split('/\s+/', trim($rest));
            if (isset($f[$col]) && is_numeric($f[$col])) {
                $sum += (float) $f[$col];
            }
        }
        return $sum;
    }

    public static function traffixTx($convert = false)
    {
        $v = self::netBytes(8);
        return $convert ? convertToPrettyUnit($v) : $v;
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

        // --single-transaction: a consistent copy while the panel keeps working
        self::shellExec("mysqldump -u '$dbUsername' --password='$dbPassword' --single-transaction --skip-lock-tables $dbName > '$filePath' 2>/dev/null");
    }

    /** a finished mysqldump ends with "-- Dump completed"; a cut-off file must never be trusted */
    public static function isCompleteDump($filePath)
    {
        if (!is_file($filePath) || filesize($filePath) < 200) {
            return false;
        }
        $fh = fopen($filePath, 'rb');
        fseek($fh, max(0, filesize($filePath) - 400));
        $tail = (string) fread($fh, 400);
        fclose($fh);
        return strpos($tail, '-- Dump completed') !== false;
    }

    public static function restoreMysqlBackup($filePath = "")
    {

        $dbUsername = getenv("DB_USERNAME");
        $dbPassword = getenv("DB_PASSWORD");
        $dbName     = getenv("DB_DATABASE");

        $out = self::shellExec("mysql -u '$dbUsername' --password='$dbPassword' $dbName < '$filePath' 2>&1; echo \"__EXIT:\$?\"");
        return is_string($out) && strpos($out, '__EXIT:0') !== false;
    }

    /**
     * Established SSH client sockets with the kernel's byte counters.
     * Returns a list of ["peer" => "ip:port", "pids" => [..], "acked" => bytes sent to the client, "recv" => bytes from it],
     * or null when ss cannot be run.
     */
    public static function connectionCounters($port)
    {
        $port = (int) $port;
        $text = self::shellExec("sudo ss -H -tinp state established '( sport = :$port )' 2>/dev/null");
        if (!is_string($text) || $text === '') {
            return null;
        }
        return self::parseCounters($text);
    }

    public static function parseCounters($text)
    {
        $result = [];
        $cur    = null;
        foreach (preg_split("/\r\n|\n|\r/", (string) $text) as $line) {
            if (trim($line) === '') {
                continue;
            }
            if ($line[0] !== ' ' && $line[0] !== "\t") {
                // header line of one socket: ... local peer users:(("sshd",pid=1,fd=4),...)
                $cur = null;
                $pos = strpos($line, 'users:');
                if ($pos !== false && preg_match_all('/pid=(\d+)/', $line, $m)) {
                    $cols = preg_split('/\s+/', trim(substr($line, 0, $pos)));
                    $peer = end($cols);
                    if ($peer !== false && strpos($peer, ':') !== false) {
                        $cur = ["peer" => $peer, "pids" => array_values(array_unique($m[1])), "acked" => 0, "recv" => 0];
                    }
                }
                continue;
            }
            if ($cur !== null) {
                if (preg_match('/bytes_acked:(\d+)/', $line, $x)) {
                    $cur["acked"] = (float) $x[1];
                }
                if (preg_match('/bytes_received:(\d+)/', $line, $y)) {
                    $cur["recv"] = (float) $y[1];
                }
                $result[] = $cur;
                $cur = null;
            }
        }
        return $result;
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
        // an expired / never-created account has no server user: skip five useless sudo calls
        if (function_exists('posix_getpwnam') && posix_getpwnam($username) === false) {
            return;
        }
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

    private static function validPort($port)
    {
        return ctype_digit((string) $port) && (int) $port >= 1 && (int) $port <= 65535;
    }

    public static function updateSshPort($newPort)
    {
        if (!self::validPort($newPort)) {
            return;
        }
        $newPort = (int) $newPort;
        $command = "sudo sed -i 's/Port [0-9]*/Port $newPort/' /etc/ssh/sshd_config";
        self::shellExec($command);

        $command = "sudo sed -i 's/PORT_SSH=[0-9]*/PORT_SSH=$newPort/' /var/www/html/panel/.env";
        self::shellExec($command);

        $command = "sudo systemctl restart sshd";
        self::shellExec($command);
    }


    public static function updateUdpPort($newPort)
    {
        if (!self::validPort($newPort)) {
            return;
        }
        $newPort = (int) $newPort;
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
        // written by PHP itself: the text never reaches a shell command line
        @file_put_contents($filePath, (string) $text . "\n");

        $filePath = preg_quote($filePath, '/');
        $command = "sudo sed -i 's/^\(Banner\s*\).*$/\\1$filePath/' /etc/ssh/sshd_config";
        self::shellExec($command);
    }

    public static function updateDomainUrl($newUrl)
    {
        // The cron loop keeps calling the panel on 127.0.0.1 (always reachable, no DNS / CDN / TLS in the way);
        // the domain is only used for links shown to users, so nothing needs rewriting here.
    }

    public static function rebootServer()
    {
        $command = 'sudo systemctl reboot';
        self::shellExec($command);
    }


    /** what port 80 shows: "local" (built-in website) or an http(s) address; read by /var/www/html/index.php */
    public static function updateFakeUrl($newSite)
    {
        $newSite = trim((string) $newSite);
        if ($newSite === "" || strtolower($newSite) === "local") {
            $newSite = "local";
        } elseif (!preg_match('~^https?://[A-Za-z0-9._:/?=%+-]+$~', $newSite)) {
            return;
        }
        if (!isDevMode()) {
            @file_put_contents(PATH_STORAGE . DS . "fake_site.txt", $newSite);
        }
    }


    private static function shellExec($command)
    {
        if (!isDevMode()) {
            return shell_exec($command);
        }

        return "";
    }
}
