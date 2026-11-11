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
            $html = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($info->username) . '</title>'
                . '<style>body{font-family:tahoma,sans-serif;background:#f3f5f9;margin:0;padding:16px;color:#222}.c{max-width:520px;margin:0 auto;background:#fff;border-radius:14px;padding:18px;box-shadow:0 2px 10px #0001}'
                . 'h2{margin:0 0 12px}.r{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee}h3{margin:18px 0 6px;font-size:15px}'
                . 'textarea{width:100%;box-sizing:border-box;height:70px;direction:ltr;font-size:11px}img{display:block;margin:8px auto;width:230px;height:230px}button{padding:8px 14px;border:0;border-radius:8px;background:#0d6efd;color:#fff}</style></head><body><div class="c">'
                . '<h2>' . $e($info->username) . '</h2>'
                . '<div class="r"><span>روز باقی‌مانده</span><b>' . ($days >= 0 ? $days : "منقضی") . '</b></div>'
                . '<div class="r"><span>حجم مصرفی</span><b>' . $e(formatTraffice($used)) . '</b></div>'
                . '<div class="r"><span>حجم باقی‌مانده</span><b>' . ($cap > 0 ? $e(formatTraffice(max(0, $cap - $used))) : "نامحدود") . '</b></div>'
                . '<h3>NetMod</h3><img alt="" src="' . $e(generateNetmodQR($info)) . '"><textarea id="a" readonly>' . $e($nm) . '</textarea><button onclick="c(\'a\')">کپی</button>'
                . '<h3>NPV Tunnel</h3><img alt="" src="' . $e(generateNpvQR($info)) . '"><textarea id="b" readonly>' . $e($np) . '</textarea><button onclick="c(\'b\')">کپی</button>'
                . '<script>function c(i){var t=document.getElementById(i);t.select();try{document.execCommand("copy")}catch(e){}}</script></div></body></html>';
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
