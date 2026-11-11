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
