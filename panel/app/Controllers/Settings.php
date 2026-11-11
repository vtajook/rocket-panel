<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Controllers;

class Settings extends BaseController
{

    public function __construct()
    {
        $this->data["activeMenu"]     = "settings";
        parent::__construct($this);
    }

    public function index($request, $response, $args)
    {
        enqueueScriptFooter(assets("vendor/jquery-validate/jquery.validate.min.js"));

        $sModel     = new \App\Models\Settings($this);
        $settings   = $sModel->getSettings();


        $viewData = [];
        $viewData["pageTitle"]      = "تنظیمات اصلی";
        $viewData["viewContent"]    = "settings/index.php";
        $viewData["activeTab"]      = "main";
        $viewData["activePage"]     = "settings";
        $viewData["settings"]       = $settings;
        $this->render($viewData);
    }

    public function backup($request, $response, $args)
    {
        enqueueScriptFooter(assets("vendor/jquery-validate/jquery.validate.min.js"));
        enqueueScriptFooter(assets("vendor/datatable/datatables.js"));
        enqueueStyleHeader(assets("vendor/datatable/datatables.css"));

        $bModel         = new \App\Models\Backup($this);
        $backupFiles    = $bModel->getUserBackups();

        $viewData = [];
        $viewData["pageTitle"]      = "پشتیبان گیری";
        $viewData["viewContent"]    = "settings/index.php";
        $viewData["activeTab"]      = "backup";
        $viewData["activePage"]     = "backup";
        $viewData["backupFiles"]    = $backupFiles;
        $this->render($viewData);
    }

    public function api($request, $response, $args)
    {
        enqueueScriptFooter(assets("vendor/jquery-validate/jquery.validate.min.js"));
        enqueueScriptFooter(assets("vendor/datatable/datatables.js"));
        enqueueStyleHeader(assets("vendor/datatable/datatables.css"));

        $viewData = [];
        $viewData["pageTitle"]      = "مدیریت API";
        $viewData["viewContent"]    = "settings/index.php";
        $viewData["activeTab"]      = "api";
        $viewData["activePage"]     = "public_api";
        $this->render($viewData);
    }


    public function usersPanel($request, $response, $args)
    {
        enqueueScriptFooter(assets("vendor/jquery-validate/jquery.validate.min.js"));

        $sModel     = new \App\Models\Settings($this);
        $settings   = $sModel->getSetting("users_panel");
        if (!empty($settings)) {
            $settings = json_decode($settings, true);
        } else {
            $settings = [];
        }


        $viewData = [];
        $viewData["pageTitle"]      = "تنظیمات پنل کاربران";
        $viewData["viewContent"]    = "settings/index.php";
        $viewData["activeTab"]      = "users_panel";
        $viewData["activePage"]     = "users_panel";
        $viewData["settings"]       = $settings;
        $this->render($viewData);
    }




    public function account($request, $response, $args)
    {
        $viewData = [];
        $viewData["pageTitle"]      = "حساب مدیر";
        $viewData["viewContent"]    = "settings/index.php";
        $viewData["activeTab"]      = "account";
        $viewData["activePage"]     = "account";
        $this->render($viewData);
    }

    /** the manager changes their own login: old username + password required */
    public function ajaxSaveAccount($request, $response, $args)
    {
        $fail = function ($msg) use ($response) {
            return $response->withStatus(400)->withJson(["status" => "error", "messages" => $msg]);
        };
        if (session()->get("imp_admin")) {
            return $fail("در حالت ورود به‌جای نماینده نمی‌توان حساب مدیر را عوض کرد");
        }
        $uid = (int) $request->getAttribute('uid');
        $p   = $request->getParsedBody();
        $p   = is_array($p) ? $p : [];
        $row = db('admins')->where('id', $uid)->first();
        if (!$row || $row->role !== 'admin') {
            return $fail("فقط مدیر می‌تواند این بخش را تغییر دهد");
        }
        $curUser = trim((string) getArrayValue($p, 'current_username'));
        $curPass = (string) getArrayValue($p, 'current_password');
        if ($curUser !== $row->username || !password_verify($curPass, $row->password)) {
            return $fail("نام کاربری یا رمز فعلی درست نیست");
        }
        $newUser = trim((string) getArrayValue($p, 'new_username'));
        $newPass = (string) getArrayValue($p, 'new_password');
        if ($newUser === '' && $newPass === '') {
            return $fail("نام کاربری یا رمز جدید را وارد کنید");
        }
        $update = ['utime' => time()];
        if ($newUser !== '' && $newUser !== $row->username) {
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{2,31}$/', $newUser)) {
                return $fail("نام کاربری جدید: ۳ تا ۳۲ حرف انگلیسی، عدد، نقطه، خط تیره یا زیرخط");
            }
            if (db('admins')->where('username', $newUser)->where('id', '!=', $uid)->count()) {
                return $fail("این نام کاربری قبلا استفاده شده است");
            }
            $update['username'] = $newUser;
        }
        if ($newPass !== '') {
            if (strlen($newPass) < 6) {
                return $fail("رمز جدید باید حداقل ۶ حرف باشد");
            }
            if ($newPass !== (string) getArrayValue($p, 'new_password2')) {
                return $fail("تکرار رمز جدید یکسان نیست");
            }
            $update['password'] = password_hash($newPass, PASSWORD_BCRYPT);
        }
        $update['token_ver'] = (int) $row->token_ver + 1;
        db('admins')->where('id', $uid)->update($update);
        if (isset($update['username'])) {
            db('users')->where('admin_uname', $row->username)->update(['admin_uname' => $update['username']]);
        }
        session()->set("token_ver", $update['token_ver']);
        \App\Agency\Agency::flush();
        return $response->withStatus(200)->withJson(["status" => "success", "messages" => "ذخیره شد"]);
    }

    public function tg($request, $response, $args)
    {
        $viewData = [];
        $viewData["pageTitle"]      = "اعلان و بکاپ تلگرام";
        $viewData["viewContent"]    = "settings/index.php";
        $viewData["activeTab"]      = "tg";
        $viewData["activePage"]     = "tg";
        $viewData["tg"]             = \App\Libraries\TgNotify::cfg();
        $this->render($viewData);
    }

    public function ajaxSaveTg($request, $response, $args)
    {
        $p = $request->getParsedBody();
        $p = is_array($p) ? $p : [];
        $clean = [
            'tgb_token'       => trim(isset($p['tgb_token']) ? $p['tgb_token'] : ''),
            'tgb_chat'        => trim(isset($p['tgb_chat']) ? $p['tgb_chat'] : ''),
            'tgb_backup'      => !empty($p['tgb_backup']) ? 1 : 0,
            'tgb_interval'    => max(1, min(1440, (int) (isset($p['tgb_interval']) ? $p['tgb_interval'] : 60))),
            'tgb_usage'       => !empty($p['tgb_usage']) ? 1 : 0,
            'tgb_usage_pct'   => max(1, min(100, (int) (isset($p['tgb_usage_pct']) ? $p['tgb_usage_pct'] : 80))),
            'tgb_login'       => !empty($p['tgb_login']) ? 1 : 0,
            'tgb_expiry'      => !empty($p['tgb_expiry']) ? 1 : 0,
            'tgb_expiry_days' => max(1, min(30, (int) (isset($p['tgb_expiry_days']) ? $p['tgb_expiry_days'] : 3))),
        ];
        if ($clean['tgb_token'] !== '' && !preg_match('/^[0-9]{5,}:[A-Za-z0-9_-]{20,}$/', $clean['tgb_token'])) {
            return $response->withStatus(400)->withJson(["messages" => "توکن ربات معتبر نیست (مثل 123456:ABC...)"]);
        }
        if ($clean['tgb_chat'] !== '' && !preg_match('/^-?[0-9]+([\s,;]+-?[0-9]+)*$/', $clean['tgb_chat'])) {
            return $response->withStatus(400)->withJson(["messages" => "آیدی عددی باید فقط عدد باشد (چند آیدی با کاما)"]);
        }
        $sModel = new \App\Models\Settings($this);
        foreach ($clean as $k => $v) {
            $sModel->updateOrCreate(['name' => $k], ['name' => $k, 'value' => (string) $v]);
        }
        @unlink(PATH_STORAGE . DS . 'tgbackup.last');   // new interval: first backup soon
        return $response->withStatus(200)->withJson(["messages" => "ذخیره شد"]);
    }

    public function ajaxTgTest($request, $response, $args)
    {
        $p    = $request->getParsedBody();
        $what = is_array($p) && isset($p['what']) ? $p['what'] : 'message';
        if (!\App\Libraries\TgNotify::ready()) {
            return $response->withStatus(400)->withJson(["messages" => "اول توکن و آیدی عددی را ذخیره کنید"]);
        }
        if ($what === 'backup') {
            @set_time_limit(120);
            $ok = \App\Libraries\TgNotify::sendBackup($err);
        } else {
            list($ok, $err) = \App\Libraries\TgNotify::send("✅ اتصال پنل به تلگرام برقرار است\n🖥 " . \App\Libraries\TgNotify::serverIp());
        }
        if ($ok) {
            return $response->withStatus(200)->withJson(["messages" => "ارسال شد"]);
        }
        return $response->withStatus(400)->withJson(["messages" => "ارسال نشد: " . $err . " (اگر سرور به تلگرام دسترسی ندارد خطا طبیعی است؛ ربات را در تلگرام Start کرده باشید)"]);
    }

    public function ajaxSaveSettings($request, $response, $args)
    {
        $validator = new  \App\Validations\Settings();

        $uid        = $request->getAttribute('uid');
        $pdata      = $request->getParsedBody();
        $validate   = $validator->saveMainSettings($pdata);
        if ($validate["status"] == "error") {
            return $response->withStatus(400)->withJson($validate);
        }

        $sModel = new \App\Models\Settings($this);
        $sModel->saveMainSettings($pdata, $uid);

        return $response->withStatus(200);
    }


    public function ajaxSaveUsersPanel($request, $response, $args)
    {

        $uid        = $request->getAttribute('uid');
        $pdata      = $request->getParsedBody();

        $sModel = new \App\Models\Settings($this);
        $sModel->saveUsersPanel($pdata, $uid);
        return $response->withStatus(200);
    }

    public function ajaxAaddPublicApi($request, $response, $args)
    {
        $validator = new  \App\Validations\Settings();

        $uid        = $request->getAttribute('uid');
        $pdata      = $request->getParsedBody();
        $validate   = $validator->addPublicApi($pdata);
        if ($validate["status"] == "error") {
            return $response->withStatus(400)->withJson($validate);
        }

        $pModel = new \App\Models\PublicApis($this);
        $result = $pModel->saveApi($pdata, $uid);
        return $response->withStatus(200)->withJson($result);
    }


    public function ajaxListPublicApi($request, $response, $args)
    {
        $pdata      = $request->getParsedBody();
        $pModel = new \App\Models\PublicApis($this);
        $result = $pModel->dataTableList($pdata);
        return $response->withStatus(200)->withJson($result);
    }


    public function ajaxDeletePublicApi($request, $response, $args)
    {
        $validator  = new  \App\Validations\Settings();

        $apiId      = $args["id"];
        $uid        = $request->getAttribute('uid');
        $validate   = $validator->publicApiInfo($apiId);
        if ($validate["status"] == "error") {
            return $response->withStatus(400)->withJson($validate);
        }

        $pModel = new \App\Models\PublicApis($this);
        $pModel->deleteApi($apiId, $uid);

        return $response->withStatus(200);
    }


    public function ajaxImportBackup($request, $response, $args)
    {
        $validator      = new  \App\Validations\Settings();
        $pdata          = $request->getParsedBody();

        $upFiles        = $request->getUploadedFiles();
        $pdata["file"]  = null;
        if (!empty($upFiles["sql_file"])) {
            $pdata["file"]  = $upFiles["sql_file"];
        }

        $validate   = $validator->importBackup($pdata);
        if ($validate["status"] == "error") {
            return $response->withStatus(400)->withJson($validate);
        }

        $sqlContent = $pdata["file"]->getStream()->getContents();
 
        $importFrom = $pdata["import_from"];

        $bkModel = new \App\Models\Backup();


        $parser = new \App\Libraries\MySQLExportParser($sqlContent);
        //import from shahan
        if ($importFrom == "shahan") {
            try {

                $values      = $parser->getTablesData(["users", "Traffic"]);
                $totalInsert =  $bkModel->importBackupFromShahan($values);

                return $response->withStatus(200)->withJson(["total_insert" => $totalInsert]);
            } catch (\Exception $err) {
                return $response->withStatus(400)->withJson([
                    "messages" => "خطایی رخ داد لطفا دوباره تلاش کنید"
                ]);
            }
            //import from xpanel
        } else if ($importFrom == "xpanel") {

            try {
                $values      = $parser->getTablesData(["users", "traffic", "Traffic"]);
                $totalInsert = $bkModel->importBackupFromXpanel($values);
                return $response->withStatus(200)->withJson(["total_insert" => $totalInsert]);
            } catch (\Exception $err) {
                return $response->withStatus(400)->withJson([
                    "messages" => "خطایی رخ داد لطفا دوباره تلاش کنید"
                ]);
            }
        } else if ($importFrom == "dragon") {
            try {
              
                $totalInsert = $bkModel->importFromDragon($sqlContent);
                return $response->withStatus(200)->withJson(["total_insert" => $totalInsert]);
            } catch (\Exception $err) {
                return $response->withStatus(400)->withJson([
                    "messages" => "خطایی رخ داد لطفا دوباره تلاش کنید"
                ]);
            }
        } else if ($importFrom == "current") {
            try {
                $values      = $parser->getTablesData(["cp_users", "cp_traffics"]);
                $totalInsert =  $bkModel->importSelfBackup($sqlContent, $values);
            } catch (\Throwable $err) {
                return $response->withStatus(400)->withJson(["messages" => $err->getMessage()]);
            }
            if (!$totalInsert) {
                return $response->withStatus(400)->withJson(["messages" => "در این فایل جدول کاربران پنل (cp_users و cp_traffics) پیدا نشد. نوع پنل را درست انتخاب کنید."]);
            }
            return $response->withStatus(200)->withJson(["total_insert" => $totalInsert]);
        }
    }

    public function ajaxSyncUsers($request, $response, $args)
    {
        $result = (new \App\Models\Backup())->syncSysUsers(150);
        return $response->withStatus(200)->withJson(["status" => "success"] + $result);
    }

    public function ajaxCreateBackup($request, $response, $args)
    {
        $appName        = getenv("APP_NAME");
        $date           = jdate()->format("Y-m-d_H-i");
        $backupName     = "$appName-$date";

        $backupPath     = PATH_ASSETS . DS . "backup";
        $backupFilePath = $backupPath . DS . "$backupName.sql";

        if (!is_dir($backupPath)) {
            @mkdir($backupPath, 0777, true);
        }

        \App\Libraries\UserShell::createMysqlBackup($backupFilePath);
        if (!\App\Libraries\UserShell::isCompleteDump($backupFilePath)) {
            @unlink($backupFilePath);
            return $response->withStatus(500)->withJson(["messages" => "ساخت بکاپ کامل نشد"]);
        }
    }

    public function ajaxDeleteExportFile($request, $response, $args)
    {
        $pdata      = $request->getParsedBody();
        if (!empty($pdata["filename"])) {

            $filename = $pdata["filename"];

            $filePath = PATH_ASSETS . DS . "backup" . DS . $filename;
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }
}
