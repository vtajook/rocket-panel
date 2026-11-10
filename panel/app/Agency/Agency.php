<?php

/**
 * Agency (reseller) module – core rules.
 *
 * - Resellers live in the existing `admins` table (role "employee"), so their
 *   logins keep working. `parent_id` builds the tree (0 = directly under the manager).
 * - Money lives in an append-only ledger. A balance is always the SUM of the
 *   reseller's rows; a mistake is fixed with an opposite row, never by editing.
 * - Money is taken BEFORE the account is touched; if the account step fails the
 *   charge is reversed with a "reversal" row.
 * - Everything that changes money or counts toward a limit runs behind one
 *   global lock, so two simultaneous sales can never overspend a wallet.
 */

namespace App\Agency;

if (!defined('PATH')) die();

class Agency
{
    const ROLE_AGENT = 'employee';

    /** ledger row kinds that take money for a service */
    public static $CHARGE_KINDS = ['sale', 'renew', 'adddays', 'addgb', 'adddev'];

    public static $KIND_LABELS = [
        'sale'     => 'فروش',
        'renew'    => 'تمدید',
        'adddays'  => 'افزایش روز',
        'addgb'    => 'افزایش حجم',
        'adddev'   => 'افزایش کاربر همزمان',
        'refund'   => 'برگشت پول',
        'reversal' => 'برگشت خطا',
        'credit'   => 'شارژ کیف پول',
        'debit'    => 'کسر از کیف پول',
        'adjust'   => 'اصلاح حساب',
    ];

    public static $PERM_LABELS = [
        'create'     => 'ساخت اکانت',
        'renew'      => 'تمدید اکانت',
        'edit'       => 'ویرایش اکانت',
        'suspend'    => 'فعال / غیرفعال کردن اکانت',
        'delete'     => 'حذف اکانت',
        'custom'     => 'فروش سفارشی (بدون پلن)',
        'unlimited'  => 'فروش اکانت حجم نامحدود',
        'bulk'       => 'ساخت گروهی',
        'sub_agents' => 'داشتن زیرنماینده',
        'transfer'   => 'درخواست انتقال اکانت',
        'api'        => 'کلید API (اتصال ربات)',
    ];

    public static $LIMIT_LABELS = [
        'max_accounts'   => 'حداکثر تعداد کل اکانت',
        'max_active'     => 'حداکثر اکانت فعال',
        'max_daily'      => 'حداکثر ساخت در روز',
        'max_days'       => 'حداکثر روز هر اکانت',
        'max_devices'    => 'حداکثر کاربر همزمان هر اکانت',
        'max_gb'         => 'حداکثر حجم هر اکانت (گیگ)',
        'max_sold_gb'    => 'حداکثر مجموع حجم فروخته‌شده (گیگ)',
        'max_sub_agents' => 'حداکثر زیرنماینده',
        'credit_limit'   => 'سقف اعتبار (بدهی مجاز)',
    ];

    private static $agents = null;
    private static $settings = null;
    private static $lockDepth = 0;
    private static $lockHandle = null;

    /* ------------------------------------------------------------------ */
    /* settings                                                            */
    /* ------------------------------------------------------------------ */

    public static function defaultSettings()
    {
        return [
            'currency'        => 'تومان',
            'per_day'         => 0,
            'per_gb'          => 0,
            'add_day'         => null,
            'add_gb'          => null,
            'device_multiply' => 1,
            'max_levels'      => 3,
            'refund'          => ['mode' => 'none', 'hours' => 24, 'max_used_pct' => 10, 'percent' => 100],
            'low_balance'     => 0,
            'notify_sale'     => 0,
            'tg_token'        => '',
            'tg_chat'         => '',
            'api_rate'        => 60,
        ];
    }

    public static function settings()
    {
        if (self::$settings === null) {
            $saved = [];
            $row = db('settings')->where('name', 'agency')->first();
            if ($row && $row->value) {
                $decoded = json_decode($row->value, true);
                if (is_array($decoded)) {
                    $saved = $decoded;
                }
            }
            $settings = array_merge(self::defaultSettings(), $saved);
            if (!is_array($settings['refund'])) {
                $settings['refund'] = [];
            }
            $defaults = self::defaultSettings();
            $settings['refund'] = array_merge($defaults['refund'], $settings['refund']);
            self::$settings = $settings;
        }
        return self::$settings;
    }

    public static function saveSettings($settings)
    {
        $value = json_encode($settings, JSON_UNESCAPED_UNICODE);
        if (db('settings')->where('name', 'agency')->count()) {
            db('settings')->where('name', 'agency')->update(['value' => $value]);
        } else {
            db('settings')->insert(['name' => 'agency', 'value' => $value]);
        }
        self::$settings = null;
    }

    public static function money($amount)
    {
        return number_format((float) $amount) . ' ' . self::settings()['currency'];
    }

    /* ------------------------------------------------------------------ */
    /* resellers and the tree                                              */
    /* ------------------------------------------------------------------ */

    public static function legacyPerms()
    {
        return [
            'create' => true, 'renew' => true, 'edit' => true, 'suspend' => true, 'delete' => true,
            'custom' => true, 'unlimited' => true, 'bulk' => true,
            'sub_agents' => false, 'transfer' => false, 'api' => false,
        ];
    }

    public static function defaultPerms()
    {
        return self::legacyPerms();
    }

    private static function decodeJson($text)
    {
        if (is_array($text)) {
            return $text;
        }
        $decoded = $text ? json_decode($text, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    private static function hydrate($row)
    {
        $agent = (array) $row;
        $agent['id']        = (int) $agent['id'];
        $agent['parent_id'] = isset($agent['parent_id']) ? (int) $agent['parent_id'] : 0;
        $agent['perms']     = self::decodeJson(isset($agent['perms']) ? $agent['perms'] : null);
        $agent['limits']    = self::decodeJson(isset($agent['limits']) ? $agent['limits'] : null);
        $agent['pricing']   = self::decodeJson(isset($agent['pricing']) ? $agent['pricing'] : null);
        if (!isset($agent['pricing']['plans']) || !is_array($agent['pricing']['plans'])) {
            $agent['pricing']['plans'] = [];
        }
        foreach (['ban_until', 'frozen', 'expiry', 'is_active', 'token_ver'] as $key) {
            $agent[$key] = isset($agent[$key]) ? (int) $agent[$key] : 0;
        }
        return $agent;
    }

    /** every panel login (managers and resellers), keyed by id */
    public static function all()
    {
        if (self::$agents === null) {
            self::$agents = [];
            foreach (db('admins')->orderBy('id')->get() as $row) {
                $agent = self::hydrate($row);
                self::$agents[$agent['id']] = $agent;
            }
        }
        return self::$agents;
    }

    public static function flush()
    {
        self::$agents = null;
    }

    public static function find($id)
    {
        $all = self::all();
        $id  = (int) $id;
        return isset($all[$id]) ? $all[$id] : null;
    }

    public static function findByUsername($username)
    {
        foreach (self::all() as $agent) {
            if ($agent['username'] === $username) {
                return $agent;
            }
        }
        return null;
    }

    public static function isAdmin($agent)
    {
        $role = is_array($agent) ? $agent['role'] : (is_object($agent) ? $agent->role : '');
        return $role === 'admin';
    }

    /** resellers only (no managers) */
    public static function agents()
    {
        $list = [];
        foreach (self::all() as $agent) {
            if (!self::isAdmin($agent)) {
                $list[$agent['id']] = $agent;
            }
        }
        return $list;
    }

    /** the reseller's parent, or null when it sits directly under the manager */
    public static function parentOf($agent)
    {
        if (empty($agent['parent_id'])) {
            return null;
        }
        $parent = self::find($agent['parent_id']);
        if (!$parent || self::isAdmin($parent)) {
            return null;
        }
        return $parent;
    }

    /** [the reseller, its parent, ..., the top-level reseller] */
    public static function chain($agentOrId)
    {
        $agent = is_array($agentOrId) ? $agentOrId : self::find($agentOrId);
        $chain = [];
        $guard = 0;
        while ($agent && !self::isAdmin($agent) && $guard++ < 20) {
            $chain[] = $agent;
            $agent = self::parentOf($agent);
        }
        return $chain;
    }

    public static function depth($agentOrId)
    {
        return count(self::chain($agentOrId));
    }

    public static function childrenOf($id)
    {
        $id = (int) $id;
        $children = [];
        foreach (self::agents() as $agent) {
            $parent = self::parentOf($agent);
            $parentId = $parent ? $parent['id'] : 0;
            if ($parentId === $id) {
                $children[$agent['id']] = $agent;
            }
        }
        return $children;
    }

    /** ids of the reseller and everybody below it */
    public static function subtreeIds($id)
    {
        $ids   = [(int) $id];
        $queue = [(int) $id];
        $guard = 0;
        while ($queue && $guard++ < 5000) {
            $current = array_shift($queue);
            foreach (self::childrenOf($current) as $child) {
                if (!in_array($child['id'], $ids, true)) {
                    $ids[]   = $child['id'];
                    $queue[] = $child['id'];
                }
            }
        }
        return $ids;
    }

    public static function subtreeUsernames($id)
    {
        $names = [];
        foreach (self::subtreeIds($id) as $agentId) {
            $agent = self::find($agentId);
            if ($agent) {
                $names[] = $agent['username'];
            }
        }
        return $names;
    }

    /** true when $ancestorId is $agentId itself or one of its ancestors */
    public static function inSubtree($ancestorId, $agentId)
    {
        return in_array((int) $agentId, self::subtreeIds($ancestorId), true);
    }

    /**
     * Which account owners the logged-in person may see.
     * null = everything (manager); otherwise a list of owner usernames.
     */
    public static function scopeUsernames($userInfo = null)
    {
        $me = self::me($userInfo);
        if (!$me || self::isAdmin($me)) {
            return null;
        }
        return self::subtreeUsernames($me['id']);
    }

    /** may the logged-in person touch an account owned by $ownerUsername? */
    public static function canAccessOwner($ownerUsername, $userInfo = null)
    {
        $scope = self::scopeUsernames($userInfo);
        return $scope === null || in_array($ownerUsername, $scope, true);
    }

    /** the logged-in panel user as a hydrated array */
    public static function me($userInfo = null)
    {
        if ($userInfo === null) {
            try {
                $userInfo = container()->userInfo;
            } catch (\Exception $e) {
                return null;
            }
        }
        if (!$userInfo) {
            return null;
        }
        $id = is_array($userInfo) ? $userInfo['id'] : $userInfo->id;
        return self::find($id);
    }

    /* ------------------------------------------------------------------ */
    /* state: disabled / banned / expired / frozen                         */
    /* ------------------------------------------------------------------ */

    public static function isBanned($agent)
    {
        $until = (int) $agent['ban_until'];
        return $until === 1 || $until > time();
    }

    public static function isExpired($agent)
    {
        return (int) $agent['expiry'] > 0 && (int) $agent['expiry'] < time();
    }

    /**
     * Why this reseller may not use the panel at all ('' = allowed).
     * A disabled or banned ancestor takes the whole branch down with it.
     */
    public static function blockReason($agent)
    {
        if (!$agent) {
            return 'حساب یافت نشد';
        }
        if (self::isAdmin($agent)) {
            return $agent['is_active'] ? '' : 'حساب شما غیرفعال است';
        }
        foreach (self::chain($agent) as $index => $member) {
            $who = $index === 0 ? 'حساب شما' : 'حساب نماینده بالادست شما';
            if (!$member['is_active']) {
                return $who . ' غیرفعال است';
            }
            if (self::isBanned($member)) {
                $reason = ($index === 0 && $member['ban_reason']) ? ' (' . $member['ban_reason'] . ')' : '';
                return $who . ' مسدود است' . $reason;
            }
        }
        return '';
    }

    /** Why this reseller may not sell right now ('' = may sell). */
    public static function sellBlockReason($agent)
    {
        $block = self::blockReason($agent);
        if ($block !== '') {
            return $block;
        }
        foreach (self::chain($agent) as $index => $member) {
            if (self::isExpired($member)) {
                return $index === 0
                    ? 'مهلت نمایندگی شما تمام شده است؛ فقط می‌توانید اطلاعات را ببینید'
                    : 'مهلت نمایندگی بالادست شما تمام شده است';
            }
        }
        return '';
    }

    /** frozen = every account of this reseller (and its branch) is paused */
    public static function isFrozen($agent)
    {
        foreach (self::chain($agent) as $member) {
            if (!empty($member['frozen'])) {
                return true;
            }
        }
        return false;
    }

    public static function statusBadges($agent)
    {
        $badges = [];
        if (!$agent['is_active']) {
            $badges[] = ['غیرفعال', 'secondary'];
        }
        if (self::isBanned($agent)) {
            $badges[] = ['مسدود', 'danger'];
        }
        if (!empty($agent['frozen'])) {
            $badges[] = ['اکانت‌ها متوقف', 'warning'];
        }
        if (self::isExpired($agent)) {
            $badges[] = ['منقضی', 'dark'];
        }
        if (!$badges) {
            $badges[] = ['فعال', 'success'];
        }
        return $badges;
    }

    /* ------------------------------------------------------------------ */
    /* permissions and limits                                              */
    /* ------------------------------------------------------------------ */

    public static function can($agent, $perm)
    {
        if (!$agent) {
            return false;
        }
        if (self::isAdmin($agent)) {
            return true;
        }
        return !empty($agent['perms'][$perm]);
    }

    public static function limit($agent, $name)
    {
        return isset($agent['limits'][$name]) ? (float) $agent['limits'][$name] : 0;
    }

    public static function requirePerm($agent, $perm)
    {
        if (!self::can($agent, $perm)) {
            $label = isset(self::$PERM_LABELS[$perm]) ? self::$PERM_LABELS[$perm] : $perm;
            throw new AgencyException("شما دسترسی «{$label}» را ندارید");
        }
    }

    public static function requireCanSell($agent)
    {
        $reason = self::sellBlockReason($agent);
        if ($reason !== '') {
            throw new AgencyException($reason);
        }
    }

    public static function countAccounts($agentId, $onlyActive = false)
    {
        $query = db('users')->whereIn('admin_uname', self::subtreeUsernames($agentId));
        if ($onlyActive) {
            $query->where('status', 'active');
        }
        return (int) $query->count();
    }

    /**
     * Check the per-account caps and the running totals for the seller and for
     * every reseller above it (a sub-reseller can never exceed its parent).
     */
    public static function checkLimits($agent, $op)
    {
        $count   = isset($op['count']) ? max(1, (int) $op['count']) : 1;
        $days    = isset($op['days']) ? (int) $op['days'] : 0;
        $gb      = isset($op['gb']) ? (float) $op['gb'] : 0;
        $devices = isset($op['devices']) ? (int) $op['devices'] : 1;
        $type    = $op['type'];
        $isNew   = $type === 'create';
        $todayStart = strtotime('today');

        foreach (self::chain($agent) as $index => $member) {
            $who = $index === 0 ? '' : ' (سقف نماینده بالادست)';

            $maxDays = self::limit($member, 'max_days');
            if ($maxDays > 0 && $days > $maxDays) {
                throw new AgencyException("حداکثر مدت مجاز هر اکانت {$maxDays} روز است" . $who);
            }

            $maxDevices = self::limit($member, 'max_devices');
            if ($maxDevices > 0 && $devices > $maxDevices) {
                throw new AgencyException("حداکثر کاربر همزمان مجاز {$maxDevices} است" . $who);
            }

            $maxGb = self::limit($member, 'max_gb');
            if ($maxGb > 0 && $gb > $maxGb) {
                throw new AgencyException("حداکثر حجم مجاز هر اکانت {$maxGb} گیگ است" . $who);
            }

            if ($gb <= 0 && in_array($type, ['create', 'renew'], true) && !self::can($member, 'unlimited')) {
                throw new AgencyException("فروش اکانت با حجم نامحدود برای شما مجاز نیست" . $who);
            }

            if ($isNew) {
                $maxAccounts = self::limit($member, 'max_accounts');
                if ($maxAccounts > 0 && self::countAccounts($member['id']) + $count > $maxAccounts) {
                    throw new AgencyException("به سقف تعداد اکانت ({$maxAccounts}) رسیده‌اید" . $who);
                }

                $maxDaily = self::limit($member, 'max_daily');
                if ($maxDaily > 0) {
                    $today = (int) db('agency_ledger')
                        ->where('kind', 'sale')
                        ->where('t', '>=', $todayStart)
                        ->whereIn('seller_id', self::subtreeIds($member['id']))
                        ->whereColumn('agent_id', 'seller_id')
                        ->count();
                    if ($today + $count > $maxDaily) {
                        throw new AgencyException("به سقف ساخت روزانه ({$maxDaily}) رسیده‌اید" . $who);
                    }
                }
            }

            if ($isNew || $type === 'renew' || $type === 'activate') {
                $maxActive = self::limit($member, 'max_active');
                $adding    = $isNew ? $count : (empty($op['was_active']) ? 1 : 0);
                if ($maxActive > 0 && $adding > 0 && self::countAccounts($member['id'], true) + $adding > $maxActive) {
                    throw new AgencyException("به سقف اکانت فعال ({$maxActive}) رسیده‌اید" . $who);
                }
            }

            $maxSold = self::limit($member, 'max_sold_gb');
            if ($maxSold > 0 && $gb > 0 && $type !== 'activate') {
                $sold = (float) db('agency_ledger')
                    ->whereIn('kind', ['sale', 'renew', 'addgb'])
                    ->whereIn('seller_id', self::subtreeIds($member['id']))
                    ->whereColumn('agent_id', 'seller_id')
                    ->sum('gb');
                if ($sold + $gb * $count > $maxSold) {
                    throw new AgencyException("به سقف مجموع حجم قابل فروش ({$maxSold} گیگ) رسیده‌اید" . $who);
                }
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* plans and prices                                                    */
    /* ------------------------------------------------------------------ */

    public static function plans($onlyEnabled = false)
    {
        $query = db('agency_plans')->orderBy('sort')->orderBy('id');
        if ($onlyEnabled) {
            $query->where('enable', 1);
        }
        $plans = [];
        foreach ($query->get() as $row) {
            $plan = (array) $row;
            $plan['id'] = (int) $plan['id'];
            $plans[$plan['id']] = $plan;
        }
        return $plans;
    }

    public static function plan($id)
    {
        $row = db('agency_plans')->where('id', (int) $id)->first();
        if (!$row) {
            return null;
        }
        $plan = (array) $row;
        $plan['id'] = (int) $plan['id'];
        return $plan;
    }

    /** plans this reseller may sell (enabled and not hidden anywhere above it) */
    public static function plansFor($agent)
    {
        $plans = self::plans(true);
        foreach (self::chain($agent) as $member) {
            foreach ($member['pricing']['plans'] as $planId => $override) {
                if (!empty($override['hide'])) {
                    unset($plans[(int) $planId]);
                }
            }
        }
        return $plans;
    }

    private static function hasValue($value)
    {
        return $value !== null && $value !== '' && is_numeric($value);
    }

    /**
     * What this reseller pays per unit for a field (per_day, per_gb, add_day,
     * add_gb). A price that is not set is inherited from the reseller above;
     * the base price in the settings is the last fallback.
     */
    public static function unitPrice($agent, $field)
    {
        foreach (self::chain($agent) as $member) {
            if (isset($member['pricing'][$field]) && self::hasValue($member['pricing'][$field])) {
                return (float) $member['pricing'][$field];
            }
        }
        $settings = self::settings();
        if (isset($settings[$field]) && self::hasValue($settings[$field])) {
            return (float) $settings[$field];
        }
        if ($field === 'add_day') {
            return self::unitPrice($agent, 'per_day');
        }
        if ($field === 'add_gb') {
            return self::unitPrice($agent, 'per_gb');
        }
        return 0;
    }

    /** What this reseller pays for a plan. $kind is 'sell' or 'renew'. */
    public static function planPrice($agent, $plan, $kind = 'sell')
    {
        $planId = (string) $plan['id'];
        foreach (self::chain($agent) as $member) {
            $override = isset($member['pricing']['plans'][$planId]) ? $member['pricing']['plans'][$planId] : null;
            if (!$override) {
                continue;
            }
            if ($kind === 'renew' && isset($override['renew']) && self::hasValue($override['renew'])) {
                return (float) $override['renew'];
            }
            if (isset($override['sell']) && self::hasValue($override['sell'])) {
                return (float) $override['sell'];
            }
        }
        if ($kind === 'renew' && (float) $plan['renew'] > 0) {
            return (float) $plan['renew'];
        }
        return (float) $plan['price'];
    }

    public static function discountOf($agent)
    {
        $discount = isset($agent['pricing']['discount']) ? (float) $agent['pricing']['discount'] : 0;
        return max(0, min(100, $discount));
    }

    private static function applyDiscount($agent, $amount)
    {
        $discount = self::discountOf($agent);
        if ($discount > 0) {
            $amount = $amount * (100 - $discount) / 100;
        }
        return (int) round($amount);
    }

    private static function deviceFactor($devices)
    {
        $settings = self::settings();
        return !empty($settings['device_multiply']) ? max(1, (int) $devices) : 1;
    }

    /** price of one account for one reseller, before it is multiplied by the count */
    private static function memberUnitCost($member, $op, $plan)
    {
        $type = $op['type'];

        if ($plan && ($type === 'create' || $type === 'renew')) {
            return self::applyDiscount($member, self::planPrice($member, $plan, $type === 'renew' ? 'renew' : 'sell'));
        }

        $days    = isset($op['days']) ? (float) $op['days'] : 0;
        $gb      = isset($op['gb']) ? (float) $op['gb'] : 0;
        $devices = isset($op['devices']) ? (int) $op['devices'] : 1;

        if ($type === 'create' || $type === 'renew') {
            $cost = $days * self::unitPrice($member, 'per_day') * self::deviceFactor($devices)
                  + $gb * self::unitPrice($member, 'per_gb');
            return self::applyDiscount($member, $cost);
        }

        if ($type === 'edit') {
            $extraDays    = isset($op['extra_days']) ? max(0, (float) $op['extra_days']) : 0;
            $extraGb      = isset($op['extra_gb']) ? max(0, (float) $op['extra_gb']) : 0;
            $extraDevices = isset($op['extra_devices']) ? max(0, (int) $op['extra_devices']) : 0;
            $remaining    = isset($op['remaining_days']) ? max(0, (float) $op['remaining_days']) : 0;

            $cost = $extraDays * self::unitPrice($member, 'add_day') * self::deviceFactor($devices)
                  + $extraGb * self::unitPrice($member, 'add_gb');

            $settings = self::settings();
            if ($extraDevices > 0 && !empty($settings['device_multiply'])) {
                $cost += $extraDevices * $remaining * self::unitPrice($member, 'per_day');
            }
            return self::applyDiscount($member, $cost);
        }

        return 0;
    }

    /** enabled plans that are exactly this days / volume / devices combination */
    private static function matchPlan($agent, $days, $gb, $devices, $kind)
    {
        $best = null;
        $bestPrice = null;
        foreach (self::plansFor($agent) as $plan) {
            if ((int) $plan['days'] === (int) $days
                && abs((float) $plan['gb'] - (float) $gb) < 0.0001
                && (int) $plan['devices'] === (int) $devices) {
                $price = self::planPrice($agent, $plan, $kind);
                if ($bestPrice === null || $price < $bestPrice) {
                    $best = $plan;
                    $bestPrice = $price;
                }
            }
        }
        return $best;
    }

    /**
     * Work out what an operation costs for the seller and for each reseller
     * above it. Nothing is written.
     *
     * $op: type (create|renew|edit), days, gb, devices, plan_id, count,
     *      and for edit: extra_days, extra_gb, extra_devices, remaining_days.
     */
    public static function quote($agent, $op)
    {
        $type  = $op['type'];
        $count = isset($op['count']) ? max(1, (int) $op['count']) : 1;
        $plan  = null;

        if ($type === 'create' || $type === 'renew') {
            $kind = $type === 'renew' ? 'renew' : 'sell';

            if (!empty($op['plan_id'])) {
                $available = self::plansFor($agent);
                $planId = (int) $op['plan_id'];
                if (!isset($available[$planId])) {
                    throw new AgencyException('پلن انتخاب‌شده در دسترس نیست');
                }
                $plan = $available[$planId];
                if ($type === 'create') {
                    $op['days']    = (int) $plan['days'];
                    $op['gb']      = (float) $plan['gb'];
                    $op['devices'] = (int) $plan['devices'];
                } else {
                    if ((int) $plan['devices'] !== (int) $op['devices']) {
                        throw new AgencyException('این پلن با تعداد کاربر همزمان این اکانت جور نیست');
                    }
                    $op['days'] = (int) $plan['days'];
                }
            } elseif (!self::can($agent, 'custom')) {
                $plan = self::matchPlan($agent, $op['days'], isset($op['gb']) ? $op['gb'] : 0, $op['devices'], $kind);
                if (!$plan) {
                    throw new AgencyException('شما فقط می‌توانید با پلن‌های تعریف‌شده بفروشید');
                }
            }
        }

        $rows  = [];
        $previousUnit = null;
        foreach (self::chain($agent) as $index => $member) {
            $unit = self::memberUnitCost($member, $op, $plan);
            $rows[] = [
                'agent'  => $member,
                'unit'   => $unit,
                'cost'   => $unit * $count,
                'profit' => $index === 0 ? 0 : ($previousUnit - $unit) * $count,
            ];
            $previousUnit = $unit;
        }

        return [
            'type'    => $type,
            'count'   => $count,
            'plan'    => $plan,
            'days'    => isset($op['days']) ? (int) $op['days'] : 0,
            'gb'      => isset($op['gb']) ? (float) $op['gb'] : 0,
            'devices' => isset($op['devices']) ? (int) $op['devices'] : 1,
            'unit'    => $rows ? $rows[0]['unit'] : 0,
            'cost'    => $rows ? $rows[0]['cost'] : 0,
            'rows'    => $rows,
            'op'      => $op,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* wallet and ledger                                                   */
    /* ------------------------------------------------------------------ */

    public static function balance($agentId)
    {
        return (int) db('agency_ledger')->where('agent_id', (int) $agentId)->sum('amt');
    }

    public static function balances()
    {
        $balances = [];
        $rows = db('agency_ledger')->selectRaw('agent_id, SUM(amt) as total')->groupBy('agent_id')->get();
        foreach ($rows as $row) {
            $balances[(int) $row->agent_id] = (int) $row->total;
        }
        return $balances;
    }

    /** what the reseller can still spend (balance plus allowed debt) */
    public static function spendable($agent)
    {
        return self::balance($agent['id']) + (int) self::limit($agent, 'credit_limit');
    }

    /**
     * Run $fn while holding the module's single global lock. Re-entrant inside
     * one request.
     */
    public static function withLock($fn)
    {
        if (self::$lockDepth === 0) {
            self::$lockHandle = @fopen(PATH_STORAGE . DS . "agency.lock", "c");
            if (self::$lockHandle) {
                @flock(self::$lockHandle, LOCK_EX);
            }
        }
        self::$lockDepth++;
        try {
            return $fn();
        } finally {
            self::$lockDepth--;
            if (self::$lockDepth === 0 && self::$lockHandle) {
                @flock(self::$lockHandle, LOCK_UN);
                @fclose(self::$lockHandle);
                self::$lockHandle = null;
            }
        }
    }

    public static function newSaleId()
    {
        return date('ymdHis') . bin2hex(random_bytes(5));
    }

    /** who is acting right now (for the "by" columns and the activity log) */
    public static function actor()
    {
        $actor = ['id' => 0, 'name' => 'system', 'as_admin' => '', 'ip' => ''];
        try {
            $actor['ip'] = (string) userIPAddress();
        } catch (\Exception $e) {
        }
        try {
            $userInfo = container()->userInfo;
            if ($userInfo) {
                $actor['id']   = (int) $userInfo->id;
                $actor['name'] = (string) $userInfo->username;
            }
        } catch (\Exception $e) {
        }
        try {
            $impersonator = session()->get('imp_admin');
            if ($impersonator) {
                $admin = self::find($impersonator);
                $actor['as_admin'] = $admin ? $admin['username'] : ('#' . $impersonator);
            }
        } catch (\Exception $e) {
        }
        return $actor;
    }

    private static function insertRow($row)
    {
        $actor = self::actor();
        $defaults = [
            't' => time(), 'agent_id' => 0, 'amt' => 0, 'bal' => 0, 'kind' => '', 'sale_id' => '',
            'seller_id' => 0, 'user_id' => 0, 'acct' => '', 'days' => 0, 'gb' => 0, 'devices' => 0,
            'plan_id' => 0, 'profit' => 0, 'svc' => 'ssh', 'ref' => '',
            'by_id' => $actor['id'],
            'by_name' => $actor['as_admin'] !== '' ? $actor['as_admin'] . ' → ' . $actor['name'] : $actor['name'],
            'ip' => $actor['ip'], 'note' => '',
        ];
        $row = array_merge($defaults, $row);
        $row['bal']  = self::balance($row['agent_id']) + (int) $row['amt'];
        $row['note'] = mb_substr((string) $row['note'], 0, 480);
        db('agency_ledger')->insert($row);
        return $row;
    }

    /**
     * Take the money for a quote. Must run inside withLock(). Fails without
     * writing anything when any wallet in the chain cannot cover its share.
     *
     * $meta: kind, acct, user_id, note
     * Returns the sale id that ties the rows together.
     */
    public static function charge($quote, $meta)
    {
        foreach ($quote['rows'] as $index => $row) {
            if ($row['cost'] <= 0) {
                continue;
            }
            if (self::spendable($row['agent']) < $row['cost']) {
                if ($index === 0) {
                    throw new AgencyException(
                        'موجودی کیف پول کافی نیست. هزینه: ' . self::money($row['cost'])
                        . ' — موجودی: ' . self::money(self::balance($row['agent']['id']))
                    );
                }
                throw new AgencyException('موجودی کیف پول نماینده بالادست شما کافی نیست؛ با او تماس بگیرید');
            }
        }

        $saleId = self::newSaleId();
        $seller = $quote['rows'][0]['agent'];

        db()->getConnection()->transaction(function () use ($quote, $meta, $saleId, $seller) {
            foreach ($quote['rows'] as $index => $row) {
                // the seller always gets a row (even for a free sale) so limits and reports stay right
                if ($index > 0 && $row['cost'] == 0 && $row['profit'] == 0) {
                    continue;
                }
                self::insertRow([
                    'agent_id'  => $row['agent']['id'],
                    'amt'       => -1 * (int) $row['cost'],
                    'kind'      => $meta['kind'],
                    'sale_id'   => $saleId,
                    'seller_id' => $seller['id'],
                    'user_id'   => isset($meta['user_id']) ? (int) $meta['user_id'] : 0,
                    'acct'      => isset($meta['acct']) ? (string) $meta['acct'] : '',
                    'days'      => $quote['days'],
                    'gb'        => $quote['gb'],
                    'devices'   => $quote['devices'],
                    'plan_id'   => $quote['plan'] ? $quote['plan']['id'] : 0,
                    'profit'    => (int) $row['profit'],
                    'note'      => isset($meta['note']) ? $meta['note'] : '',
                ]);
            }
        });

        return $saleId;
    }

    /** attach the account to the rows of a sale once the account exists */
    public static function tagSale($saleId, $userId, $acct)
    {
        db('agency_ledger')->where('sale_id', $saleId)->update(['user_id' => (int) $userId, 'acct' => (string) $acct]);
    }

    /** The account step failed after the money was taken: write the opposite rows. */
    public static function reverse($saleId, $note = '')
    {
        return self::withLock(function () use ($saleId, $note) {
            $rows = db('agency_ledger')->where('sale_id', $saleId)->where('kind', '!=', 'reversal')->get();
            foreach ($rows as $row) {
                self::insertRow([
                    'agent_id'  => (int) $row->agent_id,
                    'amt'       => -1 * (int) $row->amt,
                    'kind'      => 'reversal',
                    'sale_id'   => $saleId,
                    'seller_id' => (int) $row->seller_id,
                    'user_id'   => (int) $row->user_id,
                    'acct'      => $row->acct,
                    'plan_id'   => (int) $row->plan_id,
                    'profit'    => -1 * (int) $row->profit,
                    'ref'       => $saleId,
                    'note'      => $note !== '' ? $note : 'برگشت خودکار: عملیات کامل نشد',
                ]);
            }
            return count($rows);
        });
    }

    /** manual wallet change by the manager or by a parent reseller */
    public static function walletAdjust($agentId, $amount, $kind, $note = '')
    {
        $amount = (int) $amount;
        if (!in_array($kind, ['credit', 'debit', 'adjust'], true)) {
            throw new AgencyException('نوع عملیات کیف پول نامعتبر است');
        }
        if ($amount === 0) {
            throw new AgencyException('مبلغ را وارد کنید');
        }
        if ($kind === 'credit') {
            $amount = abs($amount);
        } elseif ($kind === 'debit') {
            $amount = -1 * abs($amount);
        }
        return self::withLock(function () use ($agentId, $amount, $kind, $note) {
            return self::insertRow([
                'agent_id'  => (int) $agentId,
                'amt'       => $amount,
                'kind'      => $kind,
                'seller_id' => (int) $agentId,
                'note'      => $note,
            ]);
        });
    }

    /**
     * Refund the last paid sale of an account that is being deleted, following
     * the refund rule in the settings. Returns the amount given back to the seller.
     */
    public static function refundOnDelete($user)
    {
        $settings = self::settings();
        $refund   = $settings['refund'];
        $mode     = isset($refund['mode']) ? $refund['mode'] : 'none';
        if ($mode === 'none') {
            return 0;
        }

        return self::withLock(function () use ($user, $refund, $mode) {
            $last = db('agency_ledger')
                ->where('user_id', (int) $user->id)
                ->whereIn('kind', ['sale', 'renew'])
                ->whereColumn('agent_id', 'seller_id')
                ->orderBy('id', 'desc')
                ->first();
            if (!$last) {
                return 0;
            }
            $saleId = $last->sale_id;
            $undone = db('agency_ledger')->where('ref', $saleId)->whereIn('kind', ['refund', 'reversal'])->count();
            if ($undone) {
                return 0;
            }

            $now       = time();
            $timeRatio = 1.0;
            if (!empty($user->start_date) && !empty($user->end_date) && $user->end_date > $user->start_date) {
                $timeRatio = ($user->end_date - $now) / ($user->end_date - $user->start_date);
                $timeRatio = max(0, min(1, $timeRatio));
            }
            $trafficRatio = 1.0;
            $traffic = isset($user->traffic) ? (float) $user->traffic : 0;
            if ($traffic > 0) {
                $used = isset($user->consumer_traffic) ? (float) $user->consumer_traffic : 0;
                $trafficRatio = max(0, min(1, ($traffic - $used) / $traffic));
            }
            $remainingRatio = min($timeRatio, $trafficRatio);
            $usedPct        = (1 - $remainingRatio) * 100;
            $percent        = max(0, min(100, (float) $refund['percent'])) / 100;

            if ($mode === 'window') {
                $hours = max(0, (float) $refund['hours']);
                if ($now - (int) $last->t > $hours * 3600) {
                    return 0;
                }
                if ($usedPct > (float) $refund['max_used_pct']) {
                    return 0;
                }
                $ratio = $percent;
            } else {
                $ratio = $remainingRatio * $percent;
            }
            if ($ratio <= 0) {
                return 0;
            }

            $sellerRefund = 0;
            $rows = db('agency_ledger')->where('sale_id', $saleId)->whereIn('kind', ['sale', 'renew'])->get();
            foreach ($rows as $row) {
                $paid   = -1 * (int) $row->amt;
                $back   = (int) round($paid * $ratio);
                $profit = -1 * (int) round((int) $row->profit * $ratio);
                if ($back == 0 && $profit == 0) {
                    continue;
                }
                self::insertRow([
                    'agent_id'  => (int) $row->agent_id,
                    'amt'       => $back,
                    'kind'      => 'refund',
                    'sale_id'   => self::newSaleId(),
                    'seller_id' => (int) $row->seller_id,
                    'user_id'   => (int) $row->user_id,
                    'acct'      => $row->acct,
                    'plan_id'   => (int) $row->plan_id,
                    'profit'    => $profit,
                    'ref'       => $saleId,
                    'note'      => 'برگشت پول بابت حذف اکانت',
                ]);
                if ((int) $row->agent_id === (int) $row->seller_id) {
                    $sellerRefund = $back;
                }
            }
            return $sellerRefund;
        });
    }

    /* ------------------------------------------------------------------ */
    /* activity log                                                        */
    /* ------------------------------------------------------------------ */

    public static $ACTION_LABELS = [
        'login'            => 'ورود به پنل',
        'user.create'      => 'ساخت اکانت',
        'user.bulk'        => 'ساخت گروهی',
        'user.renew'       => 'تمدید اکانت',
        'user.edit'        => 'ویرایش اکانت',
        'user.delete'      => 'حذف اکانت',
        'user.purge'       => 'حذف گروهی منقضی‌ها',
        'user.toggle'      => 'فعال / غیرفعال کردن اکانت',
        'user.kill'        => 'قطع اتصال کاربر',
        'user.transfer'    => 'انتقال اکانت',
        'wallet.credit'    => 'شارژ کیف پول',
        'wallet.debit'     => 'کسر از کیف پول',
        'wallet.adjust'    => 'اصلاح کیف پول',
        'agent.create'     => 'ساخت نماینده',
        'agent.edit'       => 'ویرایش نماینده',
        'agent.delete'     => 'حذف نماینده',
        'agent.password'   => 'تغییر رمز نماینده',
        'agent.ban'        => 'مسدود کردن نماینده',
        'agent.unban'      => 'رفع مسدودی نماینده',
        'agent.freeze'     => 'توقف اکانت‌های نماینده',
        'agent.unfreeze'   => 'ادامه کار اکانت‌های نماینده',
        'agent.impersonate' => 'ورود مدیر به پنل نماینده',
        'plan.save'        => 'ذخیره پلن',
        'plan.delete'      => 'حذف پلن',
        'settings.save'    => 'تغییر تنظیمات فروش',
        'request.create'   => 'درخواست انتقال',
        'request.decide'   => 'پاسخ به درخواست',
        'apikey.create'    => 'ساخت کلید API',
        'apikey.delete'    => 'حذف کلید API',
        'customer.save'    => 'ذخیره مشتری',
        'customer.delete'  => 'حذف مشتری',
        'profile.password' => 'تغییر رمز خود',
        'panel.update'     => 'بروزرسانی پنل',
    ];

    public static function log($action, $target = '', $detail = null)
    {
        try {
            $actor = self::actor();
            db('agency_activity')->insert([
                't'          => time(),
                'actor_id'   => $actor['id'],
                'actor_name' => $actor['name'],
                'as_admin'   => $actor['as_admin'],
                'action'     => $action,
                'target'     => mb_substr((string) $target, 0, 140),
                'detail'     => $detail === null ? null : (is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE)),
                'ip'         => $actor['ip'],
            ]);
        } catch (\Exception $e) {
            // the log must never break the action it describes
        }
    }

    /* ------------------------------------------------------------------ */
    /* connection limits file (read by the login gate and the cron)        */
    /* ------------------------------------------------------------------ */

    public static function limitsFilePath()
    {
        return PATH_STORAGE . DS . "limits.txt";
    }

    /** usernames of resellers whose accounts are paused (own flag or an ancestor's) */
    public static function frozenOwnerUsernames()
    {
        $names = [];
        foreach (self::agents() as $agent) {
            if (self::isFrozen($agent)) {
                $names[] = $agent['username'];
            }
        }
        return $names;
    }

    /**
     * Write "username limit" lines for every account.
     *   N  = at most N simultaneous connections
     *   0  = may not connect (owner's accounts are paused)
     *  -1  = no limit enforced (limit checking is switched off in the settings)
     * The file is replaced atomically so a reader never sees half of it.
     */
    public static function writeLimitsFile()
    {
        try {
            $enforce = \App\Models\Settings::getSetting('multiuser');
            $enforce = ($enforce === false) ? true : (bool) (int) $enforce;
            $frozen  = array_flip(self::frozenOwnerUsernames());

            $lines = ["# rocket-panel connection limits – generated " . date('Y-m-d H:i:s')];
            $rows  = db('users')->select('username', 'limit_users', 'admin_uname')->orderBy('id')->get();
            foreach ($rows as $row) {
                if (!preg_match('/^[A-Za-z0-9._-]+$/', $row->username)) {
                    continue;
                }
                if (isset($frozen[$row->admin_uname])) {
                    $limit = 0;
                } elseif (!$enforce) {
                    $limit = -1;
                } else {
                    $limit = max(1, (int) $row->limit_users);
                }
                $lines[] = $row->username . ' ' . $limit;
            }

            $path = self::limitsFilePath();
            $tmp  = $path . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, implode("\n", $lines) . "\n") !== false) {
                @chmod($tmp, 0644);
                @rename($tmp, $path);
            }
            return count($lines) - 1;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /* ------------------------------------------------------------------ */
    /* small helpers                                                       */
    /* ------------------------------------------------------------------ */

    public static function jdate($timestamp, $withTime = false)
    {
        if (!$timestamp) {
            return '—';
        }
        try {
            return \Morilog\Jalali\Jalalian::forge((int) $timestamp)->format($withTime ? 'Y/m/d H:i' : 'Y/m/d');
        } catch (\Exception $e) {
            return date($withTime ? 'Y-m-d H:i' : 'Y-m-d', (int) $timestamp);
        }
    }

    /** accept "1405/08/19" (Jalali) and return a unix timestamp for the start of that day, or 0 */
    public static function parseJDate($text)
    {
        $text = trim(convertToEnNum((string) $text));
        if ($text === '') {
            return 0;
        }
        if (!preg_match('/^\d{4}\/\d{1,2}\/\d{1,2}$/', $text)) {
            return 0;
        }
        try {
            return (int) \Morilog\Jalali\Jalalian::fromFormat('Y/n/j', $text)->toCarbon()->startOfDay()->getTimestamp();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
