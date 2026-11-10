<?php

/**
 * Agency extras: Telegram notices, customers, transfer requests, API keys.
 */

namespace App\Agency;

if (!defined('PATH')) die();

class Extras
{
    /* ------------------------------------------------------------ Telegram */

    public static function notify($chat, $text)
    {
        $s = Agency::settings();
        $token = isset($s['tg_token']) ? trim($s['tg_token']) : '';
        $chat  = trim((string) $chat);
        if ($token === '' || $chat === '' || !preg_match('/^-?[0-9]+$/', $chat)) {
            return false;
        }
        try {
            $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_POSTFIELDS => ['chat_id' => $chat, 'text' => $text]]);
            curl_exec($ch);
            curl_close($ch);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** tell the manager (and the agent, if they gave a chat id) */
    public static function notifyBoth($agent, $text, $managerOnly = false)
    {
        $s = Agency::settings();
        self::notify(isset($s['tg_chat']) ? $s['tg_chat'] : '', $text);
        if (!$managerOnly && $agent && !empty($agent['tg_chat'])) {
            self::notify($agent['tg_chat'], $text);
        }
    }

    /** at most once every 6 hours per agent */
    public static function lowBalanceCheck($agentId)
    {
        $s = Agency::settings();
        $limit = isset($s['low_balance']) ? (int) $s['low_balance'] : 0;
        if ($limit <= 0) {
            return;
        }
        $balance = Agency::balance($agentId);
        if ($balance >= $limit) {
            return;
        }
        $file = PATH_STORAGE . DS . "lowbal_" . (int) $agentId . ".txt";
        if (is_file($file) && time() - filemtime($file) < 21600) {
            return;
        }
        @file_put_contents($file, (string) time());
        $agent = Agency::find($agentId);
        if ($agent) {
            self::notifyBoth($agent, "⚠️ موجودی کیف پول «" . $agent['username'] . "» کم است: " . Agency::money($balance));
        }
    }

    /* ------------------------------------------------------------ customers */

    public static function customers($ownerId)
    {
        return db('agency_customers')->where('owner_id', (int) $ownerId)->orderBy('id', 'desc')->get()->toArray();
    }

    public static function saveCustomer($ownerId, $id, $data)
    {
        $row = [
            'name'  => mb_substr(trim((string) $data['name']), 0, 100),
            'phone' => mb_substr(trim((string) $data['phone']), 0, 32),
            'tg_id' => mb_substr(trim((string) $data['tg_id']), 0, 64),
            'note'  => mb_substr(trim((string) $data['note']), 0, 500),
        ];
        if ($row['name'] === '') {
            throw new AgencyException('نام مشتری را وارد کنید');
        }
        if ($id) {
            $n = db('agency_customers')->where('id', (int) $id)->where('owner_id', (int) $ownerId)->update($row);
            if (!$n && !db('agency_customers')->where('id', (int) $id)->where('owner_id', (int) $ownerId)->exists()) {
                throw new AgencyException('مشتری یافت نشد');
            }
            return (int) $id;
        }
        $row['owner_id'] = (int) $ownerId;
        $row['ctime']    = time();
        return db('agency_customers')->insertGetId($row);
    }

    public static function deleteCustomer($ownerId, $id)
    {
        if (db('agency_customers')->where('id', (int) $id)->where('owner_id', (int) $ownerId)->delete()) {
            db('users')->where('customer_id', (int) $id)->update(['customer_id' => 0]);
        }
    }

    public static function assignCustomer($ownerId, $username, $customerId)
    {
        $user = db('users')->where('username', $username)->first();
        if (!$user || !Agency::canAccessOwner($user->admin_uname)) {
            throw new AgencyException('اکانت متعلق به شما نیست');
        }
        if ($customerId && !db('agency_customers')->where('id', (int) $customerId)->where('owner_id', (int) $ownerId)->exists()) {
            throw new AgencyException('مشتری یافت نشد');
        }
        db('users')->where('id', $user->id)->update(['customer_id' => (int) $customerId]);
    }

    /* ------------------------------------------------------------ transfer requests */

    /** a reseller asks the manager to move one of their accounts to another reseller */
    public static function createRequest($agent, $acct, $targetUsername, $text)
    {
        $user = db('users')->where('username', $acct)->first();
        if (!$user || !Agency::canAccessOwner($user->admin_uname)) {
            throw new AgencyException('اکانت متعلق به شما نیست');
        }
        $target = db('admins')->where('username', $targetUsername)->first();
        if (!$target) {
            throw new AgencyException('نماینده مقصد یافت نشد');
        }
        if (db('agency_requests')->where('agent_id', $agent['id'])->where('acct', $acct)->where('status', 'pending')->exists()) {
            throw new AgencyException('برای این اکانت درخواست باز دارید');
        }
        db('agency_requests')->insert([
            't' => time(), 'agent_id' => (int) $agent['id'], 'acct' => $acct,
            'note' => mb_substr($target->username . '|' . trim((string) $text), 0, 500), 'status' => 'pending',
        ]);
        Agency::log('request.create', $acct, ['to' => $target->username]);
        self::notifyBoth($agent, "📨 درخواست انتقال اکانت {$acct} از «{$agent['username']}» به «{$target->username}»", true);
    }

    public static function decideRequest($id, $approve, $answer)
    {
        return Agency::withLock(function () use ($id, $approve, $answer) {
            $req = db('agency_requests')->where('id', (int) $id)->where('status', 'pending')->first();
            if (!$req) {
                throw new AgencyException('درخواست یافت نشد یا قبلاً بررسی شده');
            }
            if ($approve) {
                $parts  = explode('|', $req->note, 2);
                $target = db('admins')->where('username', $parts[0])->first();
                if (!$target) {
                    throw new AgencyException('نماینده مقصد دیگر وجود ندارد');
                }
                db('users')->where('username', $req->acct)->update(['admin_uname' => $target->username]);
            }
            db('agency_requests')->where('id', $req->id)->update([
                'status' => $approve ? 'approved' : 'rejected', 'answer' => mb_substr((string) $answer, 0, 500),
                'decided_by' => (int) Agency::actor()['id'], 'decided_at' => time(),
            ]);
            Agency::log($approve ? 'request.approve' : 'request.reject', $req->acct);
            $agent = Agency::find($req->agent_id);
            if ($agent) {
                self::notify($agent['tg_chat'], ($approve ? "✅ درخواست انتقال تأیید شد: " : "❌ درخواست انتقال رد شد: ") . $req->acct);
            }
        });
    }

    /* ------------------------------------------------------------ API keys */

    const MAX_KEYS = 5;

    public static function keys($agentId)
    {
        return db('agency_api_keys')->where('agent_id', (int) $agentId)->orderBy('id')->get()->toArray();
    }

    /** returns the plain key; it is shown once and only its hash is stored */
    public static function createKey($agentId, $name)
    {
        if (db('agency_api_keys')->where('agent_id', (int) $agentId)->count() >= self::MAX_KEYS) {
            throw new AgencyException('حداکثر ' . self::MAX_KEYS . ' کلید مجاز است');
        }
        $plain = 'vl_' . bin2hex(random_bytes(24));
        db('agency_api_keys')->insert([
            'agent_id' => (int) $agentId, 'name' => mb_substr(trim((string) $name), 0, 100),
            'key_hash' => hash('sha256', $plain), 'prefix' => substr($plain, 0, 9), 'enable' => 1, 'ctime' => time(),
        ]);
        return $plain;
    }

    public static function deleteKey($agentId, $keyId)
    {
        db('agency_api_keys')->where('id', (int) $keyId)->where('agent_id', (int) $agentId)->delete();
    }

    /** returns the agent id owning a valid key, or 0. Checks enable flag, allowed IPs and the per-minute rate. */
    public static function authenticate($plain, $ip, $method, $path)
    {
        if (!preg_match('/^vl_[0-9a-f]{48}$/', (string) $plain)) {
            return 0;
        }
        $key = db('agency_api_keys')->where('key_hash', hash('sha256', $plain))->where('enable', 1)->first();
        if (!$key) {
            return 0;
        }
        $agent = Agency::find($key->agent_id);
        if (!$agent || Agency::blockReason($agent) !== '') {
            return 0;
        }
        $allow = trim((string) $agent['api_allow']);
        if ($allow !== '') {
            $ips = preg_split('/[\s,]+/', $allow, -1, PREG_SPLIT_NO_EMPTY);
            if (!in_array($ip, $ips, true)) {
                self::apiLog($agent['id'], $key->id, $ip, $method, $path, 403);
                return 0;
            }
        }
        $rate = (int) $agent['api_rate'];
        if ($rate > 0 && db('agency_api_log')->where('agent_id', $agent['id'])->where('t', '>', time() - 60)->count() >= $rate) {
            self::apiLog($agent['id'], $key->id, $ip, $method, $path, 429);
            return 0;
        }
        db('agency_api_keys')->where('id', $key->id)->update(['last_used' => time(), 'last_ip' => mb_substr($ip, 0, 45)]);
        self::apiLog($agent['id'], $key->id, $ip, $method, $path, 200);
        return (int) $agent['id'];
    }

    private static function apiLog($agentId, $keyId, $ip, $method, $path, $status)
    {
        db('agency_api_log')->insert([
            't' => time(), 'agent_id' => (int) $agentId, 'key_id' => (int) $keyId, 'ip' => mb_substr($ip, 0, 45),
            'method' => mb_substr($method, 0, 10), 'path' => mb_substr($path, 0, 200), 'status' => $status,
        ]);
        if (mt_rand(1, 200) === 1) {
            db('agency_api_log')->where('t', '<', time() - 30 * 86400)->delete();
        }
    }
}
