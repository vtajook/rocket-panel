<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Controllers;


class Cronjob extends BaseController
{

    public function master($request, $response, $arg)
    {
        // the job runner is for the server itself only: nobody outside may trigger it
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        if ($ip !== '127.0.0.1' && $ip !== '::1') {
            return $response->withStatus(403);
        }
        $cModel = new \App\Models\Cronjob();
        $cModel->init();

    }

    /** local only: the cron starts it in the background */
    public function tgBackup($request, $response, $arg)
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        if ($ip !== '127.0.0.1' && $ip !== '::1') {
            return $response->withStatus(403);
        }
        @set_time_limit(280);
        \App\Libraries\TgNotify::sendBackup($err);
    }
}
