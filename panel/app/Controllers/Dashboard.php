<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Controllers;

use \App\Libraries\UserShell;


class Dashboard extends BaseController
{


    public function index($request, $response, $args)
    {

        $viewData = [];
        $viewData["pageTitle"]      = "داشبورد";
        $viewData["activePage"]     = "dashboard";
        $viewData["viewContent"]    = "dashboard.php";

        enqueueScriptFooter(assets("vendor/datatable/datatables.js"));
        enqueueStyleHeader(assets("vendor/datatable/datatables.css"));

        $uModel     = new \App\Models\Users();
        $tModel     = new \App\Models\Traffics();
        $userInfo   = $request->getAttribute('userInfo');
        $userRole   = $userInfo->role;
        $userName   = $userInfo->username;

        $totalUsers                 = $uModel->totalUsers(null, $userRole, $userName);
        $totalActiveUsers           = $uModel->totalUsers("active", $userRole, $userName);
        $totalInActiveUsers         = $uModel->totalUsers("de_active", $userRole, $userName);
        $totalExpiryTrafficUsers    = $uModel->totalUsers("expiry_traffic", $userRole, $userName);
        $totalExpiryDateUsers       = $uModel->totalUsers("expiry_date", $userRole, $userName);

        $viewData["totalData"] = [
            "users" => [
                "all"               => $totalUsers,
                "active"            => $totalActiveUsers,
                "inActive"          => $totalInActiveUsers,
                "expiryTraffic"     => $totalExpiryTrafficUsers,
                "expiryDate"        => $totalExpiryDateUsers,
                "online"            => UserShell::totalOnlineUsers(),
            ],

        ];
        $viewData["ramData"]        = UserShell::ramData();
        $viewData["cpuData"]        = UserShell::cpuData();
        $viewData["diskData"]       = UserShell::diskData();
        $viewData["uptime"]         = UserShell::serverUptime();
        $viewData["serverTraffic"]  = UserShell::serverTraffic();
        $viewData["userTraffic"]    = $tModel->totalData();

        $this->render($viewData);
    }
}
