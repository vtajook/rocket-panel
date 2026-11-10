<?php

/**
 * Agency (reseller) pages.
 * Manager pages live under /agency/...; a reseller's own pages under /agency/my...
 * (the route allow-list in PanelPerms keeps resellers out of everything else).
 */

namespace App\Controllers;

use \App\Agency\Agency as Core;
use \App\Agency\AgencyException;

class Agency extends BaseController
{
    const PAGE_SIZE = 50;

    private function ok($response, $data = [])
    {
        return $response->withStatus(200)->withJson(array_merge(["status" => "success"], $data));
    }

    private function fail($response, $message, $status = 400)
    {
        return $response->withStatus($status)->withJson(["status" => "error", "messages" => [$message]]);
    }

    private function page($view, $title, $data = [])
    {
        enqueueStyleHeader(assets("css/agency.css"));
        $data["pageTitle"]   = $title;
        $data["viewContent"] = "agency/" . $view . ".php";
        $data["activeMenu"]  = isset($data["activeMenu"]) ? $data["activeMenu"] : "agency";
        $data["activePage"]  = "agency";
        $data["settings"]    = Core::settings();
        return $this->render($data);
    }

    private function num($value)
    {
        $value = convertToEnNum(str_replace([",", " "], "", (string) $value));
        return is_numeric($value) ? $value + 0 : null;
    }

    /* ------------------------------------------------------------------ */
    /* manager: resellers list                                             */
    /* ------------------------------------------------------------------ */

    public function index($request, $response, $args)
    {
        $agents   = Core::agents();
        $balances = Core::balances();
        $since    = time() - 30 * 86400;

        $total  = [];
        $active = [];
        $rows = db('users')->selectRaw("admin_uname, COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active")
            ->groupBy('admin_uname')->get();
        foreach ($rows as $row) {
            $total[$row->admin_uname]  = (int) $row->total;
            $active[$row->admin_uname] = (int) $row->active;
        }

        $sales = [];
        $rows = db('agency_ledger')
            ->selectRaw("seller_id, COUNT(*) as cnt, SUM(-amt) as paid, MAX(t) as last_t")
            ->whereIn('kind', Core::$CHARGE_KINDS)->whereColumn('agent_id', 'seller_id')
            ->where('t', '>=', $since)->groupBy('seller_id')->get();
        foreach ($rows as $row) {
            $sales[(int) $row->seller_id] = ['cnt' => (int) $row->cnt, 'paid' => (int) $row->paid, 'last' => (int) $row->last_t];
        }

        $online = [];
        $onlineUsers = getLocalOnlienUsers();
        if ($onlineUsers) {
            $owners = [];
            foreach (db('users')->select('username', 'admin_uname')->whereIn('username', array_keys($onlineUsers))->get() as $row) {
                $owners[$row->username] = $row->admin_uname;
            }
            foreach ($onlineUsers as $username => $sessions) {
                if (isset($owners[$username])) {
                    $owner = $owners[$username];
                    $online[$owner] = (isset($online[$owner]) ? $online[$owner] : 0) + 1;
                }
            }
        }

        $list = [];
        foreach ($agents as $agent) {
            $uname  = $agent['username'];
            $list[] = [
                'agent'   => $agent,
                'depth'   => Core::depth($agent),
                'parent'  => Core::parentOf($agent),
                'balance' => isset($balances[$agent['id']]) ? $balances[$agent['id']] : 0,
                'total'   => isset($total[$uname]) ? $total[$uname] : 0,
                'active'  => isset($active[$uname]) ? $active[$uname] : 0,
                'online'  => isset($online[$uname]) ? $online[$uname] : 0,
                'sales'   => isset($sales[$agent['id']]) ? $sales[$agent['id']] : ['cnt' => 0, 'paid' => 0, 'last' => 0],
                'badges'  => Core::statusBadges($agent),
            ];
        }

        return $this->page("index", "نمایندگان", ["list" => $list]);
    }

    public function agentPage($request, $response, $args)
    {
        $id    = isset($args["id"]) ? intval($args["id"]) : 0;
        $agent = $id ? Core::find($id) : null;
        if ($id && (!$agent || Core::isAdmin($agent))) {
            return $response->withStatus(302)->withHeader("location", baseUrl("agency"));
        }

        $data = [
            "agent"   => $agent,
            "plans"   => Core::plans(),
            "parents" => Core::agents(),
        ];
        if ($agent) {
            $data["balance"]  = Core::balance($agent['id']);
            $data["accounts"] = Core::countAccounts($agent['id']);
            $data["activeAccounts"] = Core::countAccounts($agent['id'], true);
            $data["ledger"]   = db('agency_ledger')->where('agent_id', $agent['id'])->orderBy('id', 'desc')->limit(15)->get();
            $data["activity"] = db('agency_activity')->where('actor_id', $agent['id'])->orderBy('id', 'desc')->limit(15)->get();
            $data["badges"]   = Core::statusBadges($agent);
        }
        return $this->page("agent", $agent ? "نماینده: " . $agent['fullname'] : "نماینده جدید", $data);
    }

    public function saveAgent($request, $response, $args)
    {
        $id    = isset($args["id"]) ? intval($args["id"]) : 0;
        $pdata = $request->getParsedBody();
        $pdata = is_array($pdata) ? $pdata : [];
        $agent = $id ? Core::find($id) : null;
        if ($id && (!$agent || Core::isAdmin($agent))) {
            return $this->fail($response, "نماینده یافت نشد");
        }

        try {
            $columns  = [];
            $fullname = trim(getArrayValue($pdata, "fullname"));
            if ($fullname === "") {
                throw new AgencyException("نام نماینده را وارد کنید");
            }
            $columns["fullname"] = mb_substr($fullname, 0, 100);

            if (!$agent) {
                $username = trim(getArrayValue($pdata, "username"));
                if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{2,31}$/', $username)) {
                    throw new AgencyException("نام کاربری باید ۳ تا ۳۲ حرف انگلیسی، عدد یا _ . - باشد");
                }
                if (db('admins')->where('username', $username)->count()) {
                    throw new AgencyException("این نام کاربری قبلاً استفاده شده است");
                }
                $columns["username"]   = $username;
                $columns["role"]       = Core::ROLE_AGENT;
                $columns["ctime"]      = time();
                $columns["utime"]      = 0;
                $columns["credit"]     = 0;
                $columns["created_by"] = (int) $request->getAttribute('uid');
            } else {
                $columns["utime"] = time();
            }

            $password = (string) getArrayValue($pdata, "password");
            if (!$agent && strlen($password) < 6) {
                throw new AgencyException("رمز عبور باید حداقل ۶ حرف باشد");
            }
            if ($password !== "") {
                if (strlen($password) < 6) {
                    throw new AgencyException("رمز عبور باید حداقل ۶ حرف باشد");
                }
                $columns["password"] = password_hash($password, PASSWORD_BCRYPT);
            }

            $columns["is_active"] = getArrayValue($pdata, "is_active", "1") == "1" ? 1 : 0;
            $columns["phone"]     = mb_substr(trim(getArrayValue($pdata, "phone")), 0, 32);
            $columns["tg_chat"]   = mb_substr(trim(getArrayValue($pdata, "tg_chat")), 0, 32);
            $columns["note"]      = mb_substr(trim(getArrayValue($pdata, "note")), 0, 500);
            $columns["api_allow"] = mb_substr(preg_replace('/[^0-9a-fA-F:., ]/', '', getArrayValue($pdata, "api_allow")), 0, 500);
            $columns["api_rate"]  = max(0, (int) getArrayValue($pdata, "api_rate", 0));
            $columns["expiry"]    = 0;
            $expiry = trim(getArrayValue($pdata, "expiry"));
            if ($expiry !== "") {
                $stamp = Core::parseJDate($expiry);
                if (!$stamp) {
                    throw new AgencyException("تاریخ پایان نمایندگی را به شکل ۱۴۰۵/۰۱/۳۱ وارد کنید");
                }
                $columns["expiry"] = $stamp + 86399;
            }

            // place in the tree
            $parentId = intval(getArrayValue($pdata, "parent_id", 0));
            $settings = Core::settings();
            if ($parentId) {
                $parent = Core::find($parentId);
                if (!$parent || Core::isAdmin($parent)) {
                    throw new AgencyException("نماینده بالادست نامعتبر است");
                }
                if ($agent && Core::inSubtree($agent['id'], $parentId)) {
                    throw new AgencyException("یک نماینده نمی‌تواند زیرمجموعه خودش یا زیرنماینده خودش شود");
                }
                if (Core::depth($parent) + 1 > (int) $settings['max_levels']) {
                    throw new AgencyException("حداکثر تعداد سطح نمایندگی " . (int) $settings['max_levels'] . " است");
                }
            }
            $columns["parent_id"] = $parentId;

            // permissions
            $perms = [];
            $sent  = isset($pdata["perms"]) && is_array($pdata["perms"]) ? $pdata["perms"] : [];
            foreach (array_keys(Core::$PERM_LABELS) as $perm) {
                $perms[$perm] = !empty($sent[$perm]);
            }
            $columns["perms"] = json_encode($perms);

            // limits (empty or 0 = no limit)
            $limits = [];
            $sent   = isset($pdata["limits"]) && is_array($pdata["limits"]) ? $pdata["limits"] : [];
            foreach (array_keys(Core::$LIMIT_LABELS) as $name) {
                $value = isset($sent[$name]) ? $this->num($sent[$name]) : null;
                $limits[$name] = ($value !== null && $value > 0) ? $value : 0;
            }
            $columns["limits"] = json_encode($limits);

            // prices (empty = inherit)
            $pricing = ['plans' => []];
            $sent    = isset($pdata["pricing"]) && is_array($pdata["pricing"]) ? $pdata["pricing"] : [];
            foreach (['per_day', 'per_gb', 'add_day', 'add_gb'] as $field) {
                $value = isset($sent[$field]) ? $this->num($sent[$field]) : null;
                if ($value !== null) {
                    if ($value < 0) {
                        throw new AgencyException("قیمت نمی‌تواند منفی باشد");
                    }
                    $pricing[$field] = $value;
                }
            }
            $discount = isset($sent['discount']) ? $this->num($sent['discount']) : null;
            $pricing['discount'] = ($discount !== null) ? max(0, min(100, $discount)) : 0;

            $sentPlans = isset($pdata["plan_price"]) && is_array($pdata["plan_price"]) ? $pdata["plan_price"] : [];
            foreach (Core::plans() as $planId => $plan) {
                $row  = isset($sentPlans[$planId]) && is_array($sentPlans[$planId]) ? $sentPlans[$planId] : [];
                $item = [];
                foreach (['sell', 'renew'] as $key) {
                    $value = isset($row[$key]) ? $this->num($row[$key]) : null;
                    if ($value !== null) {
                        if ($value < 0) {
                            throw new AgencyException("قیمت نمی‌تواند منفی باشد");
                        }
                        $item[$key] = $value;
                    }
                }
                if (!empty($row['hide'])) {
                    $item['hide'] = 1;
                }
                if ($item) {
                    $pricing['plans'][(string) $planId] = $item;
                }
            }

            // a parent may not sell to its child below what the parent itself pays
            if ($parentId) {
                $parent = Core::find($parentId);
                foreach (['per_day', 'per_gb', 'add_day', 'add_gb'] as $field) {
                    if (isset($pricing[$field]) && $pricing[$field] < Core::unitPrice($parent, $field)) {
                        throw new AgencyException("قیمت این نماینده نمی‌تواند کمتر از قیمت خرید نماینده بالادستش باشد");
                    }
                }
                foreach ($pricing['plans'] as $planId => $item) {
                    $plan = Core::plan($planId);
                    foreach (['sell', 'renew'] as $key) {
                        if ($plan && isset($item[$key]) && $item[$key] < Core::planPrice($parent, $plan, $key)) {
                            throw new AgencyException("قیمت پلن «" . $plan['name'] . "» نمی‌تواند کمتر از قیمت خرید نماینده بالادست باشد");
                        }
                    }
                }
            }
            $columns["pricing"] = json_encode($pricing);

            if ($agent) {
                if (isset($columns["password"]) || (!$columns["is_active"] && $agent['is_active'])) {
                    $columns["token_ver"] = (int) $agent['token_ver'] + 1;
                }
                db('admins')->where('id', $agent['id'])->update($columns);
                $newId = $agent['id'];
                Core::log('agent.edit', $agent['username']);
                if (isset($columns["password"])) {
                    Core::log('agent.password', $agent['username']);
                }
            } else {
                $newId = db('admins')->insertGetId($columns);
                Core::log('agent.create', $columns["username"]);
            }
            Core::flush();
        } catch (AgencyException $err) {
            return $this->fail($response, $err->getMessage());
        }

        return $this->ok($response, ["next_url" => baseUrl("agency/agent/" . $newId)]);
    }

    public function walletAdjust($request, $response, $args)
    {
        $agent = Core::find(intval($args["id"]));
        if (!$agent || Core::isAdmin($agent)) {
            return $this->fail($response, "نماینده یافت نشد");
        }
        $pdata  = $request->getParsedBody();
        $amount = $this->num(getArrayValue($pdata, "amount"));
        $kind   = getArrayValue($pdata, "kind", "credit");
        $note   = trim(getArrayValue($pdata, "note"));
        try {
            if ($amount === null || $amount == 0) {
                throw new AgencyException("مبلغ را وارد کنید");
            }
            $row = Core::walletAdjust($agent['id'], (int) round($amount), $kind, $note);
            Core::log('wallet.' . $kind, $agent['username'], ['amount' => (int) $row['amt'], 'note' => $note]);
            \App\Agency\Extras::notifyBoth($agent, "💰 کیف پول «" . $agent['username'] . "»: " . ((int) $row['amt'] > 0 ? "+" : "") . Core::money((int) $row['amt']) . " — موجودی: " . Core::money(Core::balance($agent['id'])));
        } catch (AgencyException $err) {
            return $this->fail($response, $err->getMessage());
        }
        return $this->ok($response, ["balance" => Core::balance($agent['id'])]);
    }

    public function freezeAgent($request, $response, $args)
    {
        $agent = Core::find(intval($args["id"]));
        if (!$agent || Core::isAdmin($agent)) {
            return $this->fail($response, "نماینده یافت نشد");
        }
        $freeze = getArrayValue($request->getParsedBody(), "mode", "freeze") === "freeze";
        db('admins')->where('id', $agent['id'])->update(['frozen' => $freeze ? 1 : 0]);
        Core::log($freeze ? 'agent.freeze' : 'agent.unfreeze', $agent['username']);
        \App\Agency\Extras::notifyBoth($agent, ($freeze ? "⏸ اکانت‌های «" : "▶️ اکانت‌های «") . $agent['username'] . ($freeze ? "» متوقف شد" : "» دوباره فعال شد"));
        Core::flush();
        return $this->ok($response);
    }

    public function banAgent($request, $response, $args)
    {
        $agent = Core::find(intval($args["id"]));
        if (!$agent || Core::isAdmin($agent)) {
            return $this->fail($response, "نماینده یافت نشد");
        }
        $pdata = $request->getParsedBody();
        $mode  = getArrayValue($pdata, "mode", "ban");
        if ($mode === "unban") {
            db('admins')->where('id', $agent['id'])->update(['ban_until' => 0, 'ban_reason' => '']);
            Core::log('agent.unban', $agent['username']);
        } else {
            $days  = $this->num(getArrayValue($pdata, "days"));
            $until = ($days !== null && $days > 0) ? time() + (int) ($days * 86400) : 1;
            db('admins')->where('id', $agent['id'])->update([
                'ban_until'  => $until,
                'ban_reason' => mb_substr(trim(getArrayValue($pdata, "reason")), 0, 250),
                'token_ver'  => (int) $agent['token_ver'] + 1,
            ]);
            Core::log('agent.ban', $agent['username'], ['days' => $days, 'reason' => getArrayValue($pdata, "reason")]);
        }
        Core::flush();
        return $this->ok($response);
    }

    public function deleteAgent($request, $response, $args)
    {
        $agent = Core::find(intval($args["id"]));
        if (!$agent || Core::isAdmin($agent)) {
            return $this->fail($response, "نماینده یافت نشد");
        }
        if (db('users')->where('admin_uname', $agent['username'])->count()) {
            return $this->fail($response, "این نماینده هنوز اکانت دارد. اول اکانت‌هایش را حذف یا منتقل کنید");
        }
        if (Core::childrenOf($agent['id'])) {
            return $this->fail($response, "این نماینده زیرنماینده دارد");
        }
        if (Core::balance($agent['id']) != 0) {
            return $this->fail($response, "موجودی کیف پول این نماینده صفر نیست. اول آن را صفر کنید");
        }
        db('admins')->where('id', $agent['id'])->delete();
        Core::log('agent.delete', $agent['username']);
        Core::flush();
        return $this->ok($response, ["next_url" => baseUrl("agency")]);
    }

    /** move every account of one reseller to another owner */
    public function transferAccounts($request, $response, $args)
    {
        $agent = Core::find(intval($args["id"]));
        $pdata = $request->getParsedBody();
        $to    = Core::find(intval(getArrayValue($pdata, "to")));
        if (!$agent || Core::isAdmin($agent) || !$to || $to['id'] === $agent['id']) {
            return $this->fail($response, "مقصد انتقال را انتخاب کنید");
        }
        $moved = db('users')->where('admin_uname', $agent['username'])->update(['admin_uname' => $to['username']]);
        Core::log('user.transfer', $agent['username'] . ' → ' . $to['username'], ['count' => $moved]);
        return $this->ok($response, ["moved" => $moved]);
    }

    /* manager signs in as a reseller, and comes back */
    public function impersonate($request, $response, $args)
    {
        $agent = Core::find(intval($args["id"]));
        if (!$agent || Core::isAdmin($agent)) {
            return $this->fail($response, "نماینده یافت نشد");
        }
        $adminId = (int) $request->getAttribute('uid');
        Core::log('agent.impersonate', $agent['username']);
        session()->set("imp_admin", $adminId);
        session()->set("admin_login", $agent['id']);
        session()->set("token_ver", (int) $agent['token_ver']);
        return $this->ok($response, ["next_url" => baseUrl("dashboard")]);
    }

    public function impersonateBack($request, $response, $args)
    {
        $adminId = session()->get("imp_admin");
        if ($adminId) {
            $admin = Core::find($adminId);
            session()->delete("imp_admin");
            if ($admin && Core::isAdmin($admin)) {
                session()->set("admin_login", $admin['id']);
                session()->set("token_ver", (int) $admin['token_ver']);
                return $response->withStatus(302)->withHeader("location", baseUrl("agency"));
            }
        }
        return $response->withStatus(302)->withHeader("location", baseUrl("dashboard"));
    }

    /* ------------------------------------------------------------------ */
    /* manager: plans and settings                                         */
    /* ------------------------------------------------------------------ */

    public function plansPage($request, $response, $args)
    {
        return $this->page("plans", "پلن‌ها و قیمت پایه", ["plans" => Core::plans(), "activeMenu" => "agency-settings"]);
    }

    public function savePlan($request, $response, $args)
    {
        $pdata = $request->getParsedBody();
        $id    = intval(getArrayValue($pdata, "id", 0));
        $name  = trim(getArrayValue($pdata, "name"));
        $days  = $this->num(getArrayValue($pdata, "days"));
        $price = $this->num(getArrayValue($pdata, "price"));
        if ($name === "" || $days === null || $days < 1 || $price === null || $price < 0) {
            return $this->fail($response, "نام، تعداد روز و قیمت پلن را درست وارد کنید");
        }
        $gb      = $this->num(getArrayValue($pdata, "gb"));
        $devices = $this->num(getArrayValue($pdata, "devices"));
        $renew   = $this->num(getArrayValue($pdata, "renew"));
        $columns = [
            'name'    => mb_substr($name, 0, 100),
            'days'    => (int) $days,
            'gb'      => ($gb !== null && $gb > 0) ? $gb : 0,
            'devices' => ($devices !== null && $devices >= 1) ? (int) $devices : 1,
            'price'   => (int) round($price),
            'renew'   => ($renew !== null && $renew > 0) ? (int) round($renew) : 0,
            'enable'  => getArrayValue($pdata, "enable", "1") == "1" ? 1 : 0,
            'sort'    => intval(getArrayValue($pdata, "sort", 0)),
        ];
        if ($id) {
            db('agency_plans')->where('id', $id)->update($columns);
        } else {
            $columns['ctime'] = time();
            db('agency_plans')->insert($columns);
        }
        Core::log('plan.save', $columns['name']);
        return $this->ok($response);
    }

    public function deletePlan($request, $response, $args)
    {
        $plan = Core::plan(intval($args["id"]));
        if ($plan) {
            db('agency_plans')->where('id', $plan['id'])->delete();
            Core::log('plan.delete', $plan['name']);
        }
        return $this->ok($response);
    }

    public function saveSettings($request, $response, $args)
    {
        $pdata    = $request->getParsedBody();
        $settings = Core::settings();

        $currency = trim(getArrayValue($pdata, "currency"));
        if ($currency !== "") {
            $settings['currency'] = mb_substr($currency, 0, 20);
        }
        foreach (['per_day', 'per_gb'] as $field) {
            $value = $this->num(getArrayValue($pdata, $field));
            $settings[$field] = ($value !== null && $value > 0) ? $value : 0;
        }
        foreach (['add_day', 'add_gb'] as $field) {
            $value = $this->num(getArrayValue($pdata, $field));
            $settings[$field] = ($value !== null && $value >= 0) ? $value : null;
        }
        $settings['device_multiply'] = getArrayValue($pdata, "device_multiply", "0") == "1" ? 1 : 0;
        $levels = $this->num(getArrayValue($pdata, "max_levels"));
        $settings['max_levels'] = ($levels !== null && $levels >= 1) ? min(10, (int) $levels) : 3;

        $mode = getArrayValue($pdata, "refund_mode", "none");
        $settings['refund']['mode'] = in_array($mode, ['none', 'window', 'prorated'], true) ? $mode : 'none';
        foreach (['hours' => 'refund_hours', 'max_used_pct' => 'refund_max_used', 'percent' => 'refund_percent'] as $key => $field) {
            $value = $this->num(getArrayValue($pdata, $field));
            if ($value !== null && $value >= 0) {
                $settings['refund'][$key] = $value;
            }
        }

        $settings['tg_token']   = trim(getArrayValue($pdata, "tg_token"));
        $settings['tg_chat']    = preg_replace('/[^0-9-]/', '', getArrayValue($pdata, "tg_chat"));
        $lowBal = $this->num(getArrayValue($pdata, "low_balance"));
        $settings['low_balance'] = ($lowBal !== null && $lowBal > 0) ? (int) $lowBal : 0;
        $settings['notify_sale'] = getArrayValue($pdata, "notify_sale", "0") == "1" ? 1 : 0;

        Core::saveSettings($settings);
        Core::log('settings.save');
        return $this->ok($response);
    }

    /* ------------------------------------------------------------------ */
    /* ledger, activity, reports (manager sees all; reseller sees its own)  */
    /* ------------------------------------------------------------------ */

    private function paginate($query, $request)
    {
        $page  = max(1, intval($request->getQueryParam("page", 1)));
        $total = (clone $query)->count();
        $rows  = $query->orderBy('id', 'desc')->offset(($page - 1) * self::PAGE_SIZE)->limit(self::PAGE_SIZE)->get();
        return ['rows' => $rows, 'page' => $page, 'total' => $total, 'pages' => max(1, (int) ceil($total / self::PAGE_SIZE))];
    }

    private function agentNames()
    {
        $names = [];
        foreach (Core::all() as $agent) {
            $names[$agent['id']] = $agent['fullname'] . ' (' . $agent['username'] . ')';
        }
        return $names;
    }

    private function ledgerQuery($request, $onlyAgentIds = null)
    {
        $query = db('agency_ledger');
        if ($onlyAgentIds !== null) {
            $query->whereIn('agent_id', $onlyAgentIds);
        }
        $agentId = intval($request->getQueryParam("agent", 0));
        if ($agentId) {
            $query->where('agent_id', $agentId);
        }
        $kind = $request->getQueryParam("kind", "");
        if ($kind !== "" && isset(Core::$KIND_LABELS[$kind])) {
            $query->where('kind', $kind);
        }
        $search = trim($request->getQueryParam("q", ""));
        if ($search !== "") {
            $query->where('acct', 'LIKE', '%' . $search . '%');
        }
        return $query;
    }

    private function csv($response, $filename, $header, $lines)
    {
        $out = "\xEF\xBB\xBF" . implode(",", $header) . "\r\n";
        foreach ($lines as $line) {
            $cells = [];
            foreach ($line as $cell) {
                $cells[] = '"' . str_replace('"', '""', (string) $cell) . '"';
            }
            $out .= implode(",", $cells) . "\r\n";
        }
        $response->getBody()->write($out);
        return $response->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function ledgerCsv($response, $query, $names)
    {
        $lines = [];
        foreach ($query->orderBy('id', 'desc')->limit(20000)->get() as $row) {
            $lines[] = [
                Core::jdate($row->t, true),
                isset($names[$row->agent_id]) ? $names[$row->agent_id] : $row->agent_id,
                isset(Core::$KIND_LABELS[$row->kind]) ? Core::$KIND_LABELS[$row->kind] : $row->kind,
                $row->amt, $row->bal, $row->acct, $row->days, $row->devices, $row->profit, $row->by_name, $row->note,
            ];
        }
        return $this->csv($response, "ledger.csv",
            ["زمان", "نماینده", "نوع", "مبلغ", "موجودی بعد", "اکانت", "روز", "کاربر همزمان", "سود", "توسط", "توضیح"], $lines);
    }

    public function ledgerPage($request, $response, $args)
    {
        $query = $this->ledgerQuery($request);
        $names = $this->agentNames();
        if ($request->getQueryParam("csv")) {
            return $this->ledgerCsv($response, $query, $names);
        }
        return $this->page("ledger", "دفتر مالی", [
            "list" => $this->paginate($query, $request), "names" => $names, "agents" => Core::agents(),
            "filters" => $request->getQueryParams(), "baseLink" => "agency/ledger", "isAdminView" => true,
            "activeMenu" => "agency-ledger",
        ]);
    }

    public function activityPage($request, $response, $args)
    {
        $query   = db('agency_activity');
        $agentId = intval($request->getQueryParam("agent", 0));
        if ($agentId) {
            $query->where('actor_id', $agentId);
        }
        $action = $request->getQueryParam("action", "");
        if ($action !== "" && isset(Core::$ACTION_LABELS[$action])) {
            $query->where('action', $action);
        }
        return $this->page("activity", "لاگ فعالیت نماینده‌ها", [
            "list" => $this->paginate($query, $request), "agents" => Core::agents(),
            "filters" => $request->getQueryParams(), "activeMenu" => "agency-activity",
        ]);
    }

    private function reportData($request, $agentIds = null)
    {
        $days = intval($request->getQueryParam("days", 30));
        if (!in_array($days, [1, 7, 30, 90, 365], true)) {
            $days = 30;
        }
        $from = strtotime("today") - ($days - 1) * 86400;

        $base = db('agency_ledger')->where('t', '>=', $from);
        if ($agentIds !== null) {
            $base->whereIn('seller_id', $agentIds);
        }

        $perAgent = [];
        $rows = (clone $base)->whereColumn('agent_id', 'seller_id')
            ->selectRaw("seller_id, kind, COUNT(*) as cnt, SUM(amt) as total")->groupBy('seller_id', 'kind')->get();
        foreach ($rows as $row) {
            $id = (int) $row->seller_id;
            if (!isset($perAgent[$id])) {
                $perAgent[$id] = ['sale' => 0, 'renew' => 0, 'other' => 0, 'paid' => 0, 'refund' => 0];
            }
            if ($row->kind === 'sale' || $row->kind === 'renew') {
                $perAgent[$id][$row->kind] += (int) $row->cnt;
            } elseif (in_array($row->kind, ['adddays', 'addgb', 'adddev'], true)) {
                $perAgent[$id]['other'] += (int) $row->cnt;
            }
            if (in_array($row->kind, Core::$CHARGE_KINDS, true)) {
                $perAgent[$id]['paid'] += -1 * (int) $row->total;
            } elseif ($row->kind === 'refund' || $row->kind === 'reversal') {
                $perAgent[$id]['refund'] += (int) $row->total;
            }
        }
        // a reversed sale is not a sale
        $reversed = (clone $base)->where('kind', 'reversal')->whereColumn('agent_id', 'seller_id')
            ->selectRaw("seller_id, COUNT(*) as cnt")->groupBy('seller_id')->get();
        $reversedBy = [];
        foreach ($reversed as $row) {
            $reversedBy[(int) $row->seller_id] = (int) $row->cnt;
        }

        $daily = [];
        $rows  = (clone $base)->whereColumn('agent_id', 'seller_id')->whereIn('kind', array_merge(Core::$CHARGE_KINDS, ['refund', 'reversal']))
            ->select('t', 'kind', 'amt')->orderBy('t')->get();
        foreach ($rows as $row) {
            $day = Core::jdate($row->t);
            if (!isset($daily[$day])) {
                $daily[$day] = ['count' => 0, 'net' => 0];
            }
            if ($row->kind === 'sale' || $row->kind === 'renew') {
                $daily[$day]['count']++;
            }
            $daily[$day]['net'] += -1 * (int) $row->amt;
        }

        $profit = (clone $base)->sum('profit');

        return ['days' => $days, 'from' => $from, 'perAgent' => $perAgent, 'reversed' => $reversedBy,
                'daily' => array_reverse($daily, true), 'profit' => (int) $profit];
    }

    public function reportsPage($request, $response, $args)
    {
        $report = $this->reportData($request);
        $names  = $this->agentNames();
        if ($request->getQueryParam("csv")) {
            $lines = [];
            foreach ($report['perAgent'] as $id => $row) {
                $lines[] = [isset($names[$id]) ? $names[$id] : $id, $row['sale'], $row['renew'], $row['other'], $row['paid'], $row['refund'], $row['paid'] - $row['refund']];
            }
            return $this->csv($response, "report.csv", ["نماینده", "فروش", "تمدید", "افزایش", "پرداختی", "برگشتی", "خالص"], $lines);
        }
        return $this->page("reports", "گزارش فروش", [
            "report" => $report, "names" => $names, "balances" => Core::balances(), "activeMenu" => "agency-reports",
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* reseller: own pages                                                 */
    /* ------------------------------------------------------------------ */

    public function myPage($request, $response, $args)
    {
        $me = Core::me();
        if (!$me || Core::isAdmin($me)) {
            return $response->withStatus(302)->withHeader("location", baseUrl("agency"));
        }
        $plans = [];
        foreach (Core::plansFor($me) as $plan) {
            $plan['my_sell']  = Core::quote($me, ['type' => 'create', 'plan_id' => $plan['id'], 'days' => 0, 'devices' => 1])['cost'];
            $plan['my_renew'] = (int) round(Core::planPrice($me, $plan, 'renew') * (100 - Core::discountOf($me)) / 100);
            $plans[] = $plan;
        }
        return $this->page("my", "کیف پول و حساب من", [
            "me" => $me, "balance" => Core::balance($me['id']), "plans" => $plans,
            "perDay" => Core::unitPrice($me, 'per_day'), "perGb" => Core::unitPrice($me, 'per_gb'),
            "accounts" => Core::countAccounts($me['id']), "activeAccounts" => Core::countAccounts($me['id'], true),
            "sellBlock" => Core::sellBlockReason($me), "activeMenu" => "agency-my",
        ]);
    }

    public function myLedger($request, $response, $args)
    {
        $me = Core::me();
        if (!$me || Core::isAdmin($me)) {
            return $response->withStatus(302)->withHeader("location", baseUrl("agency/ledger"));
        }
        $ids   = Core::subtreeIds($me['id']);
        $query = $this->ledgerQuery($request, $ids);
        $names = $this->agentNames();
        if ($request->getQueryParam("csv")) {
            return $this->ledgerCsv($response, $query, $names);
        }
        $agents = [];
        foreach ($ids as $id) {
            $agents[$id] = Core::find($id);
        }
        return $this->page("ledger", "گردش حساب", [
            "list" => $this->paginate($query, $request), "names" => $names, "agents" => $agents,
            "filters" => $request->getQueryParams(), "baseLink" => "agency/my/ledger", "isAdminView" => false,
            "activeMenu" => "agency-my-ledger",
        ]);
    }

    public function myPassword($request, $response, $args)
    {
        $me    = Core::me();
        $pdata = $request->getParsedBody();
        if (session()->get("imp_admin")) {
            return $this->fail($response, "در حالت ورود مدیر، رمز را از صفحه نماینده عوض کنید");
        }
        $current = (string) getArrayValue($pdata, "current");
        $new     = (string) getArrayValue($pdata, "password");
        $row     = db('admins')->where('id', $me['id'])->first();
        if (!$row || !password_verify($current, $row->password)) {
            return $this->fail($response, "رمز فعلی درست نیست");
        }
        if (strlen($new) < 6) {
            return $this->fail($response, "رمز جدید باید حداقل ۶ حرف باشد");
        }
        $version = (int) $me['token_ver'] + 1;
        db('admins')->where('id', $me['id'])->update(['password' => password_hash($new, PASSWORD_BCRYPT), 'token_ver' => $version, 'utime' => time()]);
        session()->set("token_ver", $version);
        Core::flush();
        Core::log('profile.password', $me['username']);
        return $this->ok($response);
    }

    /** price preview for the sell / renew forms */
    public function myQuote($request, $response, $args)
    {
        $me = Core::me();
        if (!$me || Core::isAdmin($me)) {
            return $this->ok($response, ["cost" => 0, "text" => ""]);
        }
        $pdata = $request->getParsedBody();
        try {
            $op = [
                'type'    => getArrayValue($pdata, "type", "create") === "renew" ? "renew" : "create",
                'days'    => intval($this->num(getArrayValue($pdata, "days", 0))),
                'gb'      => (float) $this->num(getArrayValue($pdata, "gb", 0)),
                'devices' => max(1, intval($this->num(getArrayValue($pdata, "devices", 1)))),
                'plan_id' => intval(getArrayValue($pdata, "plan_id", 0)),
                'count'   => max(1, intval($this->num(getArrayValue($pdata, "count", 1)))),
            ];
            $quote   = Core::quote($me, $op);
            $balance = Core::balance($me['id']);
            return $this->ok($response, [
                "cost"    => $quote['cost'],
                "balance" => $balance,
                "enough"  => Core::spendable($me) >= $quote['cost'],
                "text"    => "هزینه: " . Core::money($quote['cost']) . " — موجودی: " . Core::money($balance),
            ]);
        } catch (AgencyException $err) {
            return $this->fail($response, $err->getMessage());
        }
    }
}
