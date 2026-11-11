<?php

namespace App\Controllers;

use \App\Agency\Agency;

/**
 * In-panel "update panel" page: compares the installed version with the one on
 * GitHub and starts the root-owned updater script, which only replaces panel
 * code (users, settings and SSH are never touched).
 */
class Updater extends BaseController
{
    const TOOL = "/usr/local/sbin/rocket-panel";

    private function logFile()
    {
        return PATH_STORAGE . DS . "update.log";
    }

    private function remote($force = false)
    {
        $file = PATH_STORAGE . DS . "last_version.txt";
        if ($force || !is_file($file) || time() - filemtime($file) > 600) {
            $v = githubLastVersion();
            if ($v) {
                @file_put_contents($file, $v);
            }
        }
        return @trim((string) @file_get_contents($file));
    }

    public function index($request, $response, $args)
    {
        $remote = $this->remote(true);
        $viewData = [
            "pageTitle"   => "بروزرسانی پنل",
            "viewContent" => "pages/update.php",
            "activeMenu"  => "update",
            "activePage"  => "update",
            "current"     => panelVersion(),
            "remote"      => $remote,
            "hasUpdate"   => $remote !== '' && version_compare($remote, panelVersion(), '>'),
            "toolReady"   => is_file(self::TOOL),
            "repo"        => getConfig("repoUrl"),
        ];
        return $this->render($viewData);
    }

    public function ajaxRun($request, $response, $args)
    {
        if (!is_file(self::TOOL)) {
            return $response->withStatus(400)->withJson(["status" => "error", "messages" => ["ابزار بروزرسانی روی سرور نصب نیست. یک بار install.sh را اجرا کنید."]]);
        }
        $lock = $this->logFile() . ".run";
        if (is_file($lock) && time() - filemtime($lock) < 600) {
            // a lock left behind by a finished / dead run must not block the next one
            $log      = (string) @file_get_contents($this->logFile());
            $finished = strpos($log, "UPDATE-FINISHED") !== false || strpos($log, "UPDATE-FAILED") !== false;
            $running  = trim((string) @shell_exec("pgrep -f 'rocket-panel --update' 2>/dev/null")) !== '';
            if ($finished || !$running) {
                @unlink($lock);
            } else {
                return $response->withStatus(409)->withJson(["status" => "error", "messages" => ["بروزرسانی در حال انجام است (چند دقیقه صبر کنید و صفحه را باز کنید)"]]);
            }
        }
        @file_put_contents($lock, (string) time());
        @file_put_contents($this->logFile(), "[" . date("Y-m-d H:i:s") . "] شروع بروزرسانی\n");
        // fixed command, no user input
        $cmd = "setsid nohup sudo -n " . self::TOOL . " --update >> " . escapeshellarg($this->logFile()) . " 2>&1 < /dev/null &";
        @exec($cmd);
        Agency::log('panel.update', null, ['from' => panelVersion()]);
        return $response->withStatus(200)->withJson(["status" => "success"]);
    }

    public function ajaxLog($request, $response, $args)
    {
        $text = @file_get_contents($this->logFile());
        $text = $text === false ? "" : substr($text, -6000);
        $done = strpos($text, "UPDATE-FINISHED") !== false || strpos($text, "UPDATE-FAILED") !== false;
        if ($done) {
            @unlink($this->logFile() . ".run");
        }
        return $response->withStatus(200)->withJson([
            "status" => "success", "log" => $text, "done" => $done,
            "ok" => strpos($text, "UPDATE-FINISHED") !== false, "version" => panelVersion(),
        ]);
    }
}
