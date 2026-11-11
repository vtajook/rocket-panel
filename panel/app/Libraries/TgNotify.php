<?php
/**
 * Telegram: automatic database backup + usage / login / expiry alerts.
 * Settings live in the settings table (tgb_* keys), edited at settings/tg.
 */

namespace App\Libraries;

if (!defined('PATH')) die();

class TgNotify
{
    const KEYS = ['tgb_token', 'tgb_chat', 'tgb_backup', 'tgb_interval', 'tgb_usage', 'tgb_usage_pct', 'tgb_login', 'tgb_expiry', 'tgb_expiry_days'];

    public static function cfg()
    {
        static $cfg = null;
        if ($cfg === null) {
            $cfg = [];
            foreach (db('settings')->whereIn('name', self::KEYS)->get() as $r) {
                $cfg[$r->name] = $r->value;
            }
        }
        return $cfg;
    }

    public static function reset()
    {
        // cfg() caches per request; the cron is a fresh request each tick
    }

    public static function ready()
    {
        $c = self::cfg();
        return !empty($c['tgb_token']) && !empty($c['tgb_chat']);
    }

    private static function call($method, array $fields, $timeout = 20)
    {
        $c = self::cfg();
        $ch = curl_init('https://api.telegram.org/bot' . trim($c['tgb_token']) . '/' . $method);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_POSTFIELDS => $fields]);
        $out  = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        $json = $out ? json_decode($out, true) : null;
        if (is_array($json) && !empty($json['ok'])) {
            return [true, ''];
        }
        return [false, is_array($json) && isset($json['description']) ? $json['description'] : ($err ?: 'no response')];
    }

    /** one chat id, or several separated by comma / space */
    private static function chats()
    {
        $c = self::cfg();
        return array_filter(preg_split('/[\s,;]+/', isset($c['tgb_chat']) ? $c['tgb_chat'] : ''), function ($v) {
            return preg_match('/^-?[0-9]+$/', $v);
        });
    }

    public static function send($text)
    {
        if (!self::ready()) {
            return [false, 'token / chat id'];
        }
        $res = [false, ''];
        foreach (self::chats() as $chat) {
            $res = self::call('sendMessage', ['chat_id' => $chat, 'text' => mb_substr($text, 0, 4000)], 5);
        }
        return $res;
    }

    public static function sendFile($path, $caption)
    {
        if (!self::ready()) {
            return [false, 'token / chat id'];
        }
        $res = [false, ''];
        foreach (self::chats() as $chat) {
            $res = self::call('sendDocument', ['chat_id' => $chat, 'caption' => mb_substr($caption, 0, 1000),
                'document' => new \CURLFile($path)], 120);
        }
        return $res;
    }

    /** public IP of this server, so the backup says which panel it came from */
    public static function serverIp()
    {
        $file = PATH_STORAGE . DS . 'serverip.txt';
        if (is_file($file) && time() - filemtime($file) < 86400) {
            $ip = trim((string) @file_get_contents($file));
            if ($ip !== '') {
                return $ip;
            }
        }
        $ip = '';
        $ch = curl_init('https://api.ipify.org');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 3]);
        $out = trim((string) curl_exec($ch));
        curl_close($ch);
        if (filter_var($out, FILTER_VALIDATE_IP)) {
            $ip = $out;
        } else {
            $out = trim((string) @shell_exec("hostname -I 2>/dev/null | awk '{print $1}'"));
            if (filter_var($out, FILTER_VALIDATE_IP)) {
                $ip = $out;
            }
        }
        if ($ip !== '') {
            @file_put_contents($file, $ip);
        }
        return $ip !== '' ? $ip : 'unknown';
    }

    private static function header()
    {
        $s = \App\Models\Settings::getSetting('domain_url');
        return '🖥 ' . self::serverIp() . ($s ? ' (' . $s . ')' : '');
    }

    /* ----------------------------------------------------------- backup */

    public static function makeBackup(&$error = '')
    {
        $dir = PATH_STORAGE . DS . 'tgbackup';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        foreach (glob($dir . DS . '*') ?: [] as $old) {
            @unlink($old);
        }
        // same file name and .sql extension as the panel's own backup (e.g. Rocket_SSH-1405-08-20_02-55.sql)
        $name = (getenv('APP_NAME') ?: 'rocket') . '-' . jdate()->format('Y-m-d_H-i');
        $sql  = $dir . DS . $name . '.sql';
        \App\Libraries\UserShell::createMysqlBackup($sql);
        if (!is_file($sql) || filesize($sql) < 200) {
            $error = 'mysqldump failed';
            @unlink($sql);
            return false;
        }
        if (filesize($sql) <= 49 * 1048576) {
            return $sql;
        }
        // too big for Telegram as plain text: compress (only then the extension changes)
        $gz  = $sql . '.gz';
        $in  = fopen($sql, 'rb');
        $out = gzopen($gz, 'wb6');
        while (!feof($in)) {
            gzwrite($out, fread($in, 1 << 20));
        }
        fclose($in);
        gzclose($out);
        @unlink($sql);
        return $gz;
    }

    public static function sendBackup(&$error = '')
    {
        $file = self::makeBackup($error);
        if (!$file) {
            return false;
        }
        $users = (int) db('users')->count();
        $size  = round(filesize($file) / 1048576, 2);
        $cap   = "💾 بکاپ پنل SSH\n" . self::header() . "\n👥 کاربران: $users\n📦 {$size} MB\n🕒 " . date('Y-m-d H:i:s')
               . "" . (substr($file, -3) === '.gz' ? "\n(فایل فشرده است؛ اول با gunzip باز شود)" : "");
        if (filesize($file) > 49 * 1048576) {
            $error = 'file too big for Telegram (>49MB)';
            self::send("⚠️ بکاپ " . self::header() . " بیش از ۴۹ مگابایت است و ارسال نشد.");
            @unlink($file);
            return false;
        }
        list($ok, $err) = self::sendFile($file, $cap);
        @unlink($file);
        if (!$ok) {
            $error = $err;
        }
        return $ok;
    }

    /** due now? marks the time before sending, so a slow upload never starts a second backup */
    public static function backupDue()
    {
        $c = self::cfg();
        if (empty($c['tgb_backup']) || !self::ready()) {
            return false;
        }
        $minutes = max(1, min(1440, (int) (isset($c['tgb_interval']) ? $c['tgb_interval'] : 60)));
        $last    = PATH_STORAGE . DS . 'tgbackup.last';
        $prev    = is_file($last) ? (int) @file_get_contents($last) : 0;
        if (time() - $prev < $minutes * 60 - 5) {
            return false;
        }
        @file_put_contents($last, time());
        return true;
    }

    /** runs the upload in its own request so the 1-second cron work is never held up */
    public static function cronBackup()
    {
        if (!self::backupDue()) {
            return;
        }
        $port = getenv('PORT_PANEL') ?: '8081';
        @shell_exec("setsid nohup curl -s -m 300 http://127.0.0.1:" . (int) $port . "/cron/tg-backup >/dev/null 2>&1 &");
    }

    /* ----------------------------------------------------------- alerts */

    private static function state($name)
    {
        $d = @json_decode((string) @file_get_contents(PATH_STORAGE . DS . $name . '.json'), true);
        return is_array($d) ? $d : [];
    }

    private static function saveState($name, $d)
    {
        @file_put_contents(PATH_STORAGE . DS . $name . '.json', json_encode($d));
    }

    /** accounts that crossed the usage percentage (once per renewal / reset) */
    public static function cronUsage()
    {
        $c = self::cfg();
        if (empty($c['tgb_usage']) || !self::ready()) {
            return;
        }
        $pct  = max(1, min(100, (int) (isset($c['tgb_usage_pct']) ? $c['tgb_usage_pct'] : 80)));
        $seen = self::state('tg_usage');
        $rows = db('users')->join('traffics', 'traffics.username', '=', 'users.username')
            ->where('users.status', 'active')->where('users.traffic', '>', 0)
            ->whereRaw('traffics.total >= users.traffic * ?', [$pct / 100])
            ->select('users.username', 'users.traffic', 'traffics.total', 'users.admin_uname')->limit(300)->get();
        $lines = [];
        $now   = [];
        foreach ($rows as $r) {
            $now[$r->username] = 1;
            if (!isset($seen[$r->username])) {
                $used = round($r->total / 1024, 2);
                $all  = round($r->traffic / 1024, 2);
                $lines[] = "• {$r->username} ({$r->admin_uname}): {$used} / {$all} GB (" . round($r->total * 100 / $r->traffic) . "%)";
            }
        }
        if ($lines) {
            foreach (array_chunk($lines, 40) as $chunk) {
                self::send("📊 هشدار مصرف (بالای {$pct}٪)\n" . self::header() . "\n" . implode("\n", $chunk));
            }
        }
        self::saveState('tg_usage', $now);   // anyone who dropped below (renewed / reset) can be alerted again
    }

    /** accounts expiring within N days (once each) */
    public static function cronExpiry()
    {
        $c = self::cfg();
        if (empty($c['tgb_expiry']) || !self::ready()) {
            return;
        }
        $days = max(1, min(30, (int) (isset($c['tgb_expiry_days']) ? $c['tgb_expiry_days'] : 3)));
        $seen = self::state('tg_expiry');
        $rows = db('users')->where('status', 'active')->where('start_date', '>', 0)
            ->where('end_date', '>', time())->where('end_date', '<=', time() + $days * 86400)
            ->select('username', 'end_date', 'admin_uname')->limit(300)->get();
        $lines = [];
        $now   = [];
        foreach ($rows as $r) {
            $key = $r->username . ':' . $r->end_date;
            $now[$key] = 1;
            if (!isset($seen[$key])) {
                $lines[] = "• {$r->username} ({$r->admin_uname}): " . max(0, (int) ceil(($r->end_date - time()) / 86400)) . " روز مانده";
            }
        }
        if ($lines) {
            foreach (array_chunk($lines, 40) as $chunk) {
                self::send("⏳ نزدیک انقضا (تا {$days} روز)\n" . self::header() . "\n" . implode("\n", $chunk));
            }
        }
        self::saveState('tg_expiry', $now);
    }

    /** new connections since the previous snapshot, sent as one message per tick */
    public static function loginAlert($before, $after)
    {
        $c = self::cfg();
        if (empty($c['tgb_login']) || !self::ready() || !is_array($after)) {
            return;
        }
        $before = is_array($before) ? $before : [];
        $lines  = [];
        foreach ($after as $user => $conns) {
            if (isset($before[$user])) {
                continue;
            }
            $ips = [];
            foreach ($conns as $cn) {
                $ips[$cn['ip']] = 1;
            }
            $lines[] = "• $user — " . implode(', ', array_keys($ips));
        }
        if ($lines) {
            $more = count($lines) > 25 ? "\n… و " . (count($lines) - 25) . " اتصال دیگر" : '';
            self::send("🔔 ورود کاربر\n" . self::header() . "\n" . implode("\n", array_slice($lines, 0, 25)) . $more);
        }
    }
}
