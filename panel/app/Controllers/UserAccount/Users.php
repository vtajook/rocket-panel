<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Controllers\UserAccount;

use \App\Libraries\UserShell;

class Users
{

    /** what the customer page needs: nothing from the admin's private side (notes, owner, raw token) */
    private const PUBLIC_FIELDS = [
        "id", "username", "password", "email", "mobile", "format_traffic", "start_date", "end_date",
        "expiry_days", "expiry_type", "limit_users", "format_consumer_traffic", "end_date_jd",
        "start_date_jd", "remaining_days", "netmod_qr_url", "npv_qr_url", "status", "array_config", "diffrence_date",
    ];

    public function userInfoByToken($request, $response, $args)
    {
        // links are 6 characters long: stop anybody who tries many wrong ones
        if (tooManyFails("acct", 30)) {
            return $response->withStatus(429);
        }

        $uModel = new \App\Models\Users();
        $result = $uModel->getInfoByToken($args["token"]);

        if (!empty($result)) {
            $result = (array) $result;
            return $response->withStatus(200)->withJson(array_intersect_key($result, array_flip(self::PUBLIC_FIELDS)));
        }

        noteFail("acct");
        return $response->withStatus(404);
    }

    /** subscription for apps: configs + (in the header) usage, total and expiry, so the app can show them */
    public function subscription($request, $response, $args)
    {
        if (tooManyFails("acct", 30)) {
            return $response->withStatus(429);
        }
        $uModel = new \App\Models\Users();
        $info   = $uModel->getInfoByToken($args["token"]);
        if (empty($info)) {
            noteFail("acct");
            return $response->withStatus(404);
        }

        $remark = subRemark($info);
        $body   = base64_encode(netmodLink($info, $remark) . "\n" . npvLink($info, $remark) . "\n");

        $t = db("traffics")->where("username", $info->username)->first();
        $mb = 1048576;
        $userinfo = sprintf(
            "upload=%d; download=%d; total=%d; expire=%d",
            $t ? (float) $t->upload * $mb : 0,
            $t ? (float) $t->download * $mb : 0,
            (float) $info->traffic * $mb,
            (int) $info->end_date
        );

        // opened in a browser: a readable page (days, traffic, links, QR); apps get the subscription text
        $params = $request->getQueryParams();
        $accept = $request->getHeaderLine("Accept");
        if (empty($params["raw"]) && stripos($accept, "text/html") !== false) {
            $e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, "UTF-8"); };
            $days = (int) $info->remaining_days;
            $used = $t ? (float) $t->total : 0;
            $cap  = (float) $info->traffic;
            $nm   = netmodLink($info);
            $np   = npvLink($info);
            $pct  = $cap > 0 ? min(100, round($used / $cap * 100)) : 0;
            $dayT = $days >= 0 ? $days . " روز" : "منقضی";
            $left = $cap > 0 ? formatTraffice(max(0, $cap - $used)) : "نامحدود";
            $total = $cap > 0 ? formatTraffice($cap) : "نامحدود";
            $bar  = $pct >= 90 ? "#ef4444" : ($pct >= 70 ? "#f59e0b" : "#22c55e");
            $card = function ($id, $title, $qr, $link) use ($e) {
                return '<section class="app" id="s-' . $id . '"><h3>' . $title . '</h3><img alt="QR" src="' . $e($qr) . '">'
                    . '<div class="lk" id="l-' . $id . '">' . $e($link) . '</div>'
                    . '<button onclick="cp(\'l-' . $id . '\',this)">کپی لینک</button></section>';
            };
            $html = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($info->username) . '</title>'
                . '<style>*{box-sizing:border-box}body{margin:0;font-family:Vazirmatn,Tahoma,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;padding:18px 14px}'
                . '.w{max-width:480px;margin:0 auto}.hd{text-align:center;padding:10px 0 18px}.av{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#06b6d4);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 10px;color:#fff}'
                . '.hd h1{font-size:20px;margin:0}.st{display:inline-block;margin-top:8px;padding:3px 12px;border-radius:99px;font-size:12px;background:' . ($info->status === "active" ? "#14532d;color:#86efac" : "#7f1d1d;color:#fca5a5") . '}'
                . '.g{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px}.b{background:#1e293b;border-radius:14px;padding:14px;text-align:center}.b small{display:block;color:#94a3b8;font-size:12px;margin-bottom:4px}.b strong{font-size:18px}'
                . '.tr{background:#1e293b;border-radius:14px;padding:14px;margin-bottom:14px}.tr .t{display:flex;justify-content:space-between;font-size:13px;color:#94a3b8;margin-bottom:8px}.pb{height:10px;background:#334155;border-radius:9px;overflow:hidden}.pb i{display:block;height:100%;width:' . $pct . '%;background:' . $bar . ';border-radius:9px}'
                . '.tabs{display:flex;gap:8px;margin-bottom:10px}.tabs button{flex:1;padding:10px;border:0;border-radius:12px;background:#1e293b;color:#cbd5e1;font:inherit;cursor:pointer}.tabs button.on{background:#6366f1;color:#fff}'
                . '.app{display:none;background:#1e293b;border-radius:14px;padding:16px;text-align:center}.app.on{display:block}.app h3{margin:0 0 10px;font-size:15px}.app img{width:220px;height:220px;max-width:100%;background:#fff;padding:8px;border-radius:12px}'
                . '.lk{direction:ltr;text-align:left;font:11px/1.5 monospace;background:#0f172a;border-radius:10px;padding:10px;margin:12px 0;word-break:break-all;max-height:84px;overflow:auto;color:#94a3b8}'
                . '.app button{width:100%;padding:12px;border:0;border-radius:12px;background:linear-gradient(135deg,#6366f1,#06b6d4);color:#fff;font:inherit;font-weight:600;cursor:pointer}</style></head><body><div class="w">'
                . '<div class="hd"><div class="av">' . $e(strtoupper(substr($info->username, 0, 1))) . '</div><h1>' . $e($info->username) . '</h1><span class="st">' . ($info->status === "active" ? "فعال" : "غیرفعال") . '</span></div>'
                . '<div class="g"><div class="b"><small>زمان باقی‌مانده</small><strong>' . $e($dayT) . '</strong></div><div class="b"><small>حجم باقی‌مانده</small><strong>' . $e($left) . '</strong></div></div>'
                . '<div class="tr"><div class="t"><span>مصرف: ' . $e(formatTraffice($used)) . '</span><span>کل: ' . $e($total) . '</span></div><div class="pb"><i></i></div></div>'
                . '<div class="tabs"><button class="on" data-t="nm">NetMod</button><button data-t="npv">NPV Tunnel</button></div>'
                . str_replace('class="app"', 'class="app on"', $card("nm", "اسکن یا کپی در NetMod", generateNetmodQR($info), $nm)) . $card("npv", "اسکن یا کپی در NPV Tunnel", generateNpvQR($info), $np)
                . '<script>document.querySelectorAll(".tabs button").forEach(function(b){b.onclick=function(){document.querySelectorAll(".tabs button").forEach(function(x){x.classList.remove("on")});b.classList.add("on");document.querySelectorAll(".app").forEach(function(a){a.classList.remove("on")});document.getElementById("s-"+b.dataset.t).classList.add("on")}});'
                . 'function cp(i,btn){var t=document.getElementById(i).textContent;function ok(){var o=btn.textContent;btn.textContent="کپی شد ✓";setTimeout(function(){btn.textContent=o},1500)}if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(t).then(ok)}else{var x=document.createElement("textarea");x.value=t;document.body.appendChild(x);x.select();try{document.execCommand("copy");ok()}catch(e){}document.body.removeChild(x)}}</script></div></body></html>';
            $response->getBody()->write($html);
            return $response->withStatus(200)
                ->withHeader("Content-Type", "text/html; charset=utf-8")
                ->withHeader("Subscription-Userinfo", $userinfo)
                ->withHeader("Cache-Control", "no-store");
        }

        $response->getBody()->write($body);
        return $response->withStatus(200)
            ->withHeader("Content-Type", "text/plain; charset=utf-8")
            ->withHeader("Subscription-Userinfo", $userinfo)
            ->withHeader("Profile-Title", "base64:" . base64_encode($info->username))
            ->withHeader("Profile-Update-Interval", "1")
            ->withHeader("Cache-Control", "no-store");
    }

    public function settings($request, $response, $args)
    {

        $sModel     = new \App\Models\Settings();
        $settings   = $sModel->getSettings();
        $result     = [];

        if (!empty($settings)) {
            $usersPanel = getArrayValue($settings, "users_panel", "[]");
            if (!empty($usersPanel)) {
                $usersPanel = json_decode($usersPanel, true);
                $result     = array_merge($result, $usersPanel);
            }
        }

        return $response->withStatus(200)->withJson($result);
    }

    public function Login($request, $response, $args)
    {

        if (tooManyFails("acct", 30)) {
            return $response->withStatus(429);
        }

        $pdata      = $request->getParsedBody();
        if (!empty($pdata["username"]) && !empty($pdata["password"])) {
            $password   = $pdata["password"];
            $usename    = $pdata["username"];

            $uModel = new \App\Models\Users();
            $token = $uModel->getUserToken($usename, $password);
            if ($token) {
                return $response->withStatus(200)->withJson(["token" => $token]);
            }
        }
        noteFail("acct");
        return $response->withStatus(404);
    }
}
