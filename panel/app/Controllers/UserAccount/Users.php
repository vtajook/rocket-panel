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
        "start_date_jd", "remaining_days", "netmod_qr_url", "status", "array_config", "diffrence_date",
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
