<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Controllers;

use \App\Libraries\UserShell;
use \App\Agency\Agency;
use \App\Agency\Sales;
use \App\Agency\AgencyException;

class Users extends BaseController
{


    public function logout($request, $response, $args)
    {
        container()->session->delete('admin_login');
        container()->session->delete('userInfo');

        return $response->withHeader('Location', baseUrl('login'));
    }

    public function notFoundPage($request, $response)
    {
        enqueueStyleHeader(assets("css/404.css"));

        $response = new \Slim\Http\Response(404);
        $this->setResponse($response);
        $this->templateName = "layouts/404-layout.php";
        $viewData = array();
        $viewData["pageTitle"] = "404";
        return $this->render($viewData);
    }


    public function index($request, $response, $args)
    {
        enqueueScriptFooter(assets("vendor/jquery-validate/jquery.validate.min.js"));
        enqueueScriptFooter(assets("vendor/datepicker/datepicker.min.js"));
        enqueueScriptFooter(assets("vendor/datatable/datatables.js"));

        enqueueStyleHeader(assets("vendor/datepicker/datepicker.min.css"));
        enqueueStyleHeader(assets("vendor/datatable/datatables.css"));

        $status     = $request->getQueryParam("status");
        if (getAdminRole() == "admin" && $request->getQueryParam("owner") !== null) {
            $owner = trim((string) $request->getQueryParam("owner"));
            if ($owner === "" || !db('admins')->where('username', $owner)->exists()) {
                session()->delete("users_owner");
            } else {
                session()->set("users_owner", $owner);
            }
        }

        $viewData   = [];
        $viewData["activeStatus"]   = $status;
        $viewData["ownerFilter"]    = getAdminRole() == "admin" ? (string) session()->get("users_owner") : "";
        $viewData["ownerList"]      = getAdminRole() == "admin" ? db('admins')->orderBy('username')->pluck('username')->toArray() : [];
        $viewData["pageTitle"]      = "مدیریت کاربران";
        $viewData["viewContent"]    = "users/index.php";
        $viewData["activeMenu"]     = "users";
        $viewData["activePage"]     = "users";
        $this->render($viewData);
    }

    public function online($request, $response, $args)
    {
        enqueueScriptFooter(assets("vendor/jquery-validate/jquery.validate.min.js"));
        enqueueScriptFooter(assets("vendor/datepicker/datepicker.min.js"));
        enqueueScriptFooter(assets("vendor/datatable/datatables.js"));

        enqueueStyleHeader(assets("vendor/datepicker/datepicker.min.css"));
        enqueueStyleHeader(assets("vendor/datatable/datatables.css"));


        $uModel      = new \App\Models\Users();
        $accessUsers = $uModel->adminAccessUsers();
        $onlineUsers = UserShell::onlineUsers();
        $adminRole   = getAdminRole();

        if ($adminRole !== "admin") {
            if (!empty($accessUsers)) {
                foreach ($onlineUsers  as $username => $users) {
                    if (!in_array($username, $accessUsers)) {
                        unset($onlineUsers[$username]);
                    }
                }
            } else {
                $onlineUsers = [];
            }

            ksort($onlineUsers);
        }

        $viewData = [];
        $viewData["pageTitle"]      = "کاربران آنلاین";
        $viewData["viewContent"]    = "users/online.php";
        $viewData["activeMenu"]     = "online-users";
        $viewData["activePage"]     = "online-users";
        $viewData["onlineUsers"]    = $onlineUsers;



        $this->render($viewData);
    }


    public function ajaxViewAdd($request, $response, $args)
    {
        $viewData = [];
        $viewData['viewContent']   = 'users/form.php';
        return $this->renderAjxView($viewData);
    }

    public function ajaxViewBulkAdd($request, $response, $args)
    {
        $viewData = [];
        $viewData['viewContent']   = 'users/bulk-form.php';
        return $this->renderAjxView($viewData);
    }

    /** the logged-in reseller, or null when a manager is logged in */
    private function agent()
    {
        $me = Agency::me();
        return ($me && !Agency::isAdmin($me)) ? $me : null;
    }

    private function fail($response, $message, $status = 400)
    {
        return $response->withStatus($status)->withJson(["status" => "error", "messages" => [$message]]);
    }

    /** the account, but only when the logged-in person is allowed to touch it */
    private function ownedUser($id)
    {
        $uModel   = new \App\Models\Users();
        $userInfo = $uModel->getInfo(intval($id));
        if ($userInfo && Agency::canAccessOwner($userInfo->admin_uname)) {
            return $userInfo;
        }
        return false;
    }

    public function ajaxViewEdit($request, $response, $args)
    {
        $viewData   = [];
        $userInfo   = $this->ownedUser($args["id"]);

        if ($userInfo) {
            $viewData['userValues']    = $userInfo;
            $viewData['viewContent']   = 'users/form.php';
            $viewData['refrence']      = $request->getQueryParam("ref");
            return $this->renderAjxView($viewData);
        } else {
            $viewData['viewContent']   = 'notfound-modal.php';
        }
        return $this->renderAjxView($viewData);
    }

    public function ajaxViewDetails($request, $response, $args)
    {
        $viewData   = [];
        $userInfo   = $this->ownedUser($args["id"]);
        if ($userInfo) {
            $viewData['userValues']    = $userInfo;
            $viewData['viewContent']   = 'users/details.php';
            return $this->renderAjxView($viewData);
        } else {
            $viewData['viewContent']   = 'notfound-modal.php';
        }
        return $this->renderAjxView($viewData);
    }

    public function ajaxAddUser($request, $response, $args)
    {
        $uid       = $request->getAttribute('uid');
        $pdata     = $request->getParsedBody();
        $agent     = $this->agent();

        try {
            if ($agent) {
                $pdata = Sales::applyPlan($agent, $pdata);
            }
            $result = \App\Validations\Users::save($pdata);
            if ($result["status"] == "error") {
                return $response->withStatus(400)->withJson($result);
            }

            $uModel = new \App\Models\Users();
            if ($agent) {
                Sales::create($agent, $pdata, $uModel, $uid);
            } else {
                $uModel->saveUsers($pdata, $uid);
            }
        } catch (AgencyException $err) {
            return $this->fail($response, $err->getMessage());
        } catch (\Exception $err) {
            return $this->fail($response, "در افزودن کاربر خطایی رخ داد. لطفا دوباره امتحان کنید");
        }
    }

    public function ajaxSaveRenewal($request, $response, $args)
    {
        $editId    = $args["id"];
        $pdata     = $request->getParsedBody();
        $agent     = $this->agent();

        try {
            if ($agent && !empty($pdata["plan_id"])) {
                $plan = Agency::plan($pdata["plan_id"]);
                if ($plan) {
                    $pdata["renewal_days"] = $plan["days"];
                }
            }
            $result = \App\Validations\Users::renewal($pdata, $editId);
            if ($result["status"] == "error") {
                return $response->withStatus(400)->withJson($result);
            }

            $uModel = new \App\Models\Users();
            if ($agent) {
                Sales::renew($agent, $uModel->getInfo($editId), $pdata, $uModel);
            } else {
                $uModel->renewalUsers($pdata, $editId);
            }
        } catch (AgencyException $err) {
            return $this->fail($response, $err->getMessage());
        } catch (\Exception $err) {
            return $this->fail($response, "در تمدید کاربر خطایی رخ داد. لطفا دوباره امتحان کنید");
        }
    }

    public function ajaxAddBulkUsers($request, $response, $args)
    {
        $pdata     = $request->getParsedBody();
        $agent     = $this->agent();

        try {
            if ($agent) {
                $pdata = Sales::applyPlan($agent, $pdata);
            }
            $result = \App\Validations\Users::bulkUsers($pdata);
            if ($result["status"] == "error") {
                return $response->withStatus(400)->withJson($result);
            }

            $uModel = new \App\Models\Users();
            $added  = $agent ? Sales::bulk($agent, $pdata, $uModel) : $uModel->createBulkUsers($pdata);
        } catch (AgencyException $err) {
            return $this->fail($response, $err->getMessage());
        }

        if ($added) {
            $message = "تعداد $added کاربر با موفقیت افزوده شد";
            return $response->withStatus(200)->withJson(["messages" => $message]);
        }
        return $response->withStatus(400)->withJson(["messages" => "متاسفانه هیچ کاربری اضافه نشد"]);
    }

    public function ajaxEditUser($request, $response, $args)
    {
        $editId    = $args["id"];
        $uid       = $request->getAttribute('uid');
        $pdata     = $request->getParsedBody();
        $agent     = $this->agent();
        $result    = \App\Validations\Users::save($pdata, $editId);
        if ($result["status"] == "error") {
            return $response->withStatus(400)->withJson($result);
        }

        $uModel = new \App\Models\Users();
        try {
            if ($agent) {
                Sales::edit($agent, $uModel->getInfo($editId), $pdata, $uModel, $uid);
            } else {
                $uModel->saveUsers($pdata, $uid, $editId);
            }
        } catch (AgencyException $err) {
            return $this->fail($response, $err->getMessage());
        } catch (\Exception $err) {
            return $this->fail($response, "در ویرایش کاربر خطایی رخ داد. لطفا دوباره امتحان کنید");
        }
    }

    public function ajaxDeleteUser($request, $response, $args)
    {
        $uid       = $request->getAttribute('uid');
        $userInfo  = $this->ownedUser($args["id"]);
        if (!$userInfo) {
            return $this->fail($response, "اطلاعات کاربر یافت نشد");
        }

        $uModel = new \App\Models\Users();
        $agent  = $this->agent();
        try {
            if ($agent) {
                Sales::delete($agent, $userInfo, $uModel, $uid);
            } else {
                Agency::refundOnDelete($userInfo);
                $uModel->deleteUser($userInfo->id, $uid);
            }
        } catch (AgencyException $err) {
            return $this->fail($response, $err->getMessage());
        }

        return $response->withStatus(200);
    }

    public function ajaxDeleteBulkUsers($request, $response, $args)
    {
        $uid       = $request->getAttribute('uid');
        $pdata     = $request->getParsedBody();
        $result    = \App\Validations\Users::deleteBulk($pdata);
        if ($result["status"] == "error") {
            return $response->withStatus(400)->withJson($result);
        }

        $uModel = new \App\Models\Users();
        $agent  = $this->agent();
        try {
            // check everything first so a refused batch changes nothing
            $targets = [];
            foreach ($pdata["users"] as $userId) {
                $userInfo = $this->ownedUser($userId);
                if (!$userInfo) {
                    return $this->fail($response, "یکی از اکانت‌های انتخابی متعلق به شما نیست");
                }
                $targets[] = $userInfo;
            }
            if ($agent) {
                Agency::requirePerm($agent, 'delete');
            }
            foreach ($targets as $userInfo) {
                if ($agent) {
                    Sales::delete($agent, $userInfo, $uModel, $uid);
                } else {
                    Agency::refundOnDelete($userInfo);
                    $uModel->deleteUser($userInfo->id, $uid);
                }
            }
        } catch (AgencyException $err) {
            return $this->fail($response, $err->getMessage());
        }
        return $response->withStatus(200);
    }

    /** Delete every expired account (date or traffic) inside the caller's scope. dry=1 only counts. */
    public function ajaxPurgeExpired($request, $response, $args)
    {
        $uid   = $request->getAttribute('uid');
        $pdata = (array) $request->getParsedBody();
        $dry   = !empty($pdata['dry']);
        $days  = isset($pdata['older_days']) ? max(0, (int) $pdata['older_days']) : 0;
        $agent = $this->agent();

        $query = db('users')->whereIn('status', ['expiry_date', 'expiry_traffic']);
        if ($days > 0) {
            $query->where('end_date', '<', time() - $days * 86400);
        }
        $scope = Agency::scopeUsernames(container()->userInfo);
        if ($scope !== null) {
            if (empty($scope)) {
                return $response->withStatus(200)->withJson(["status" => "success", "count" => 0]);
            }
            $query->whereIn('admin_uname', $scope);
        }
        $rows = $query->select('id')->limit(5000)->get();

        if ($dry) {
            return $response->withStatus(200)->withJson(["status" => "success", "count" => count($rows)]);
        }

        $uModel = new \App\Models\Users();
        $done   = 0;
        try {
            if ($agent) {
                Agency::requirePerm($agent, 'delete');
            }
            foreach ($rows as $row) {
                $userInfo = $uModel->getInfo($row->id);
                if (!$userInfo || !Agency::canAccessOwner($userInfo->admin_uname)) {
                    continue;
                }
                $uModel->deleteUser($userInfo->id, $uid);
                $done++;
            }
        } catch (AgencyException $err) {
            return $this->fail($response, $err->getMessage());
        }
        Agency::log('user.purge', null, ['deleted' => $done]);
        return $response->withStatus(200)->withJson(["status" => "success", "count" => $done]);
    }

    public function ajaxUsersList($request, $response, $args)
    {
        $pdata    = $request->getParsedBody();
        $uModel   = new \App\Models\Users($this);
        $uid      = $request->getAttribute('uid');

        $result   = $uModel->dataTableList($pdata, $uid);
        return $response->withStatus(200)->withJson($result);
    }


    public function ajaxToggleUserActive($request, $response, $args)
    {
        $uid       = $request->getAttribute('uid');
        $userInfo  = $this->ownedUser($args["id"]);
        if (!$userInfo) {
            return $this->fail($response, "اطلاعات کاربر یافت نشد");
        }

        $uModel = new \App\Models\Users();
        $agent  = $this->agent();
        try {
            if ($agent) {
                Sales::toggle($agent, $userInfo, $uModel, $uid);
            } else {
                $uModel->toggleActive($userInfo->id, $uid);
            }
        } catch (AgencyException $err) {
            return $this->fail($response, $err->getMessage());
        }
    }

    public function ajaxResetUserTraffic($request, $response, $args)
    {
        $uid       = $request->getAttribute('uid');
        $userInfo  = $this->ownedUser($args["id"]);
        if (!$userInfo) {
            return $this->fail($response, "اطلاعات کاربر یافت نشد");
        }

        $uModel = new \App\Models\Users();
        $uModel->resetTraffic($userInfo->id, $uid);
    }

    public function ajaxUsersOnlinesList($request, $response, $args)
    {
        $pdata    = $request->getParsedBody();
        $uModel   = new \App\Models\Users($this);
        $uid      = $request->getAttribute('uid');

        $result   = $uModel->dataTableList($pdata, $uid);
        return $response->withStatus(200)->withJson($result);
    }

    public function ajaxKillPidUsers($request, $response, $args)
    {
        $pdata    = $request->getParsedBody();
        if (empty($pdata["pids"]) || !is_array($pdata["pids"])) {
            return;
        }

        // only connections that really belong to an account this person may manage
        $scope   = Agency::scopeUsernames();
        $owners  = [];
        if ($scope !== null) {
            $uModel = new \App\Models\Users();
            $owners = array_flip($uModel->adminAccessUsers());
        }
        $allowed = [];
        foreach (UserShell::onlineUsers() as $username => $sessions) {
            if ($scope !== null && !isset($owners[$username])) {
                continue;
            }
            foreach ($sessions as $session) {
                $allowed[intval($session["pid"])] = $username;
            }
        }

        $pids = [];
        foreach ($pdata["pids"] as $pid) {
            $pid = intval($pid);
            if ($pid > 1 && isset($allowed[$pid])) {
                $pids[] = $pid;
                if ($this->agent()) {
                    Agency::log('user.kill', $allowed[$pid]);
                }
            }
        }
        if ($pids) {
            UserShell::killUsers($pids);
        }
    }
}
