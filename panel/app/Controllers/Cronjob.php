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
        $cModel = new \App\Models\Cronjob();
        $cModel->init();

    }
}
