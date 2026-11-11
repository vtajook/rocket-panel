<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Controllers;

class Login extends BaseController
{

    public function index($request, $response, $args)
    {
        if (session()->get("admin_login")) {
            return $response->withStatus(302)->withHeader("location", baseUrl("/dashboard"));
        }
        enqueueScriptFooter(assets("vendor/jquery-validate/jquery.validate.min.js"));

        $this->templateName = "layouts/login-layout.php";

        $viewData = [];
        $viewData["pageTitle"]      = "ورود به پنل";
        $viewData["viewContent"]    = "login.php";
        $viewData["activePage"]     = "login";
        $this->render($viewData);
    }

    public function ajaxLogin($request, $response, $args)
    {
        $pdata      = $request->getParsedBody();
        $validate   = \App\Validations\Admins::login($pdata);
        if ($validate["status"] == "error") {
            return $response->withStatus(400)->withJson($validate);
        }

        // too many wrong passwords from one address: stop guessing for a while
        $failFile = PATH_STORAGE . DS . 'login_fail_' . md5((string) userIPAddress()) . '.json';
        $fails    = is_file($failFile) ? json_decode((string) @file_get_contents($failFile), true) : null;
        if (is_array($fails) && time() - (int) $fails['t'] < 900 && (int) $fails['c'] >= 8) {
            return $response->withStatus(429)->withJson(["status" => "error", "messages" => ["تعداد تلاش‌های ناموفق زیاد بود؛ ۱۵ دقیقه دیگر دوباره امتحان کنید"]]);
        }

        $aModel   = new \App\Models\Admins($this);
        $result   = $aModel->checkAdminLogin($pdata);
        if ($result) {
            @unlink($failFile);

            $userId       = $result->id;
            $sessionName  = "admin_login";

            // a disabled / banned reseller (or one under a disabled parent) may not sign in
            $agent  = \App\Agency\Agency::find($userId);
            $reason = \App\Agency\Agency::blockReason($agent);
            if ($reason !== '') {
                return $response->withStatus(403)->withJson(["status" => "error", "messages" => [$reason]]);
            }

            session()->set($sessionName, $userId);
            try {
                \App\Libraries\TgNotify::panelLogin($result->username, true, $result->role === 'admin' ? 'مدیر' : 'نماینده');
            } catch (\Throwable $e) {
            }
            session()->delete("imp_admin");
            session()->set("token_ver", (int) $agent['token_ver']);
            db('admins')->where('id', $userId)->update(['last_login' => time(), 'last_ip' => substr((string) userIPAddress(), 0, 45)]);
            if ($agent['role'] !== 'admin') {
                $this->cont["userInfo"] = $result;
                \App\Agency\Agency::log('login', $agent['username']);
            }

            if (isset($pdata['remember'])) {
                $sessName = getConfig("session")["name"];
                setcookie($sessName, session()::id(), [
                    'expires'  => time() + (50 * 86400),
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
           return $response->withStatus(200)->withJson(["next_url" => baseUrl("dashboard")]);
        }

        $count = (is_array($fails) && time() - (int) $fails['t'] < 900) ? (int) $fails['c'] + 1 : 1;
        $first = (is_array($fails) && time() - (int) $fails['t'] < 900) ? (int) $fails['t'] : time();
        @file_put_contents($failFile, json_encode(["c" => $count, "t" => $first]), LOCK_EX);

        try {
            \App\Libraries\TgNotify::panelLogin(isset($pdata['username']) ? $pdata['username'] : '', false);
        } catch (\Throwable $e) {
        }
        return $response->withStatus(404);
    }
}
