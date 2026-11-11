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

        if ($userRole != "admin") {
            return $this->resellerDashboard($viewData, $userInfo);
        }
        $viewData["agencySummary"] = $this->agencySummary();

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

    /** how many of these resellers' accounts are connected right now */
    private function onlineOf($ownerUsernames)
    {
        $online = getLocalOnlienUsers() ?: [];
        if (!$online || !$ownerUsernames) {
            return 0;
        }
        return (int) db('users')->whereIn('admin_uname', $ownerUsernames)
            ->whereIn('username', array_keys($online))->count();
    }

    private function resellerDashboard($viewData, $userInfo)
    {
        $me    = \App\Agency\Agency::me($userInfo);
        $scope = \App\Agency\Agency::scopeUsernames($userInfo);
        $scope = $scope === null ? [] : $scope;
        $now   = time();
        $users = db('users')->whereIn('admin_uname', $scope ?: ['']);

        $sales = function ($since) use ($me) {
            $q = db('agency_ledger')->where('agent_id', $me['id'])
                ->whereIn('kind', ['sale', 'renew', 'adddays', 'addgb', 'adddev'])->where('t', '>=', $since);
            return ['n' => (int) $q->count(), 'sum' => -1 * (int) $q->sum('amt')];
        };
        $viewData["reseller"] = [
            "me"        => $me,
            "balance"   => \App\Agency\Agency::balance($me['id']),
            "total"     => (clone $users)->count(),
            "active"    => (clone $users)->where('status', 'active')->count(),
            "expired"   => (clone $users)->whereIn('status', ['expiry_date', 'expiry_traffic'])->count(),
            "soon"      => (clone $users)->where('status', 'active')->where('end_date', '>', $now)->where('end_date', '<', $now + 3 * 86400)->count(),
            "online"    => $this->onlineOf($scope),
            "today"     => $sales(strtotime('today')),
            "week"      => $sales($now - 7 * 86400),
            "month"     => $sales($now - 30 * 86400),
            "recent"    => db('agency_ledger')->where('agent_id', $me['id'])->orderBy('id', 'desc')->limit(8)->get()->toArray(),
            "currency"  => \App\Agency\Agency::settings()['currency'],
        ];
        $viewData["viewContent"] = "agency/dashboard.php";
        $this->render($viewData);
    }

    private function agencySummary()
    {
        try {
            $balances = \App\Agency\Agency::balances();
            return [
                "agents"   => count($balances),
                "sum"      => array_sum($balances),
                "pending"  => (int) db('agency_requests')->where('status', 'pending')->count(),
                "sales30"  => (int) db('agency_ledger')->whereIn('kind', ['sale', 'renew', 'adddays', 'addgb', 'adddev'])->where('t', '>=', time() - 30 * 86400)->count(),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
