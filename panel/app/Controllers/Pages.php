<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

namespace App\Controllers;
use \App\Libraries\UserShell;

class Pages extends BaseController
{

    public function __construct()
    {

        parent::__construct($this);
    }


    public function filtering($request, $response, $args)
    {
        $this->data["activeMenu"]     = "filtering";

        $viewData = [];
        $viewData["pageTitle"]      = "وضعیت فیلترینگ";
        $viewData["viewContent"]    = "pages/filtering.php";
        $viewData["activePage"]     = "filtering";
        $this->render($viewData);
    }



    public function ajaxFilteringDate($request, $response, $args)
    {
        $data = getFilteringData();
        if (!empty($data)) {
            return $response->withStatus(200)->withJson($data);
        }
        
        return $response->withStatus(400);
    }

    public function ajaxRebootServer($request, $response, $args)
    {
        UserShell::rebootServer();
    }


    public function ajaxIsReady($request, $response, $args)
    {
        return $response->withStatus(200);
    }
}
