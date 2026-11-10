<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Controllers\UserAccount;

use \App\Libraries\UserShell;

class Users
{

    public function userInfoByToken($request, $response, $args)
    {
        $token  = $args["token"];

        $uModel = new \App\Models\Users();
        $result = $uModel->getInfoByToken($token);

        if (!empty($result)) {
            return $response->withStatus(200)->withJson($result);
        }

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
        return $response->withStatus(404);
    }
}
