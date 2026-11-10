<?php
include "inc/includes.php";

$userToken  = getTokenInUrl();
$settings   = getSettings();
$pdata      = $_POST;

$showLogin = true;
if (!empty($userToken)) {
    $userInfo = getUserInfoByToken($userToken);

    if ($userInfo) {
        $showLogin = false;

        $viewContent = "details.php";
        include "views/master.php";
    }
}

if ($showLogin) {
    $error      = "";
    $username   = "";
    $password   = "";
    if (!empty($pdata["username"]) && !empty($pdata["password"])) {
        $username = $pdata["username"];
        $password = $pdata["password"];
        $userToken = loginUser($username, $password);
        if ($userToken) {
            $redirectUrl = baseUrl("$userToken");
            header("Location: $redirectUrl");
            exit;
        }else{
            $error = "نام کاربری یا رمز ورود اشتباه است";
        }
    }

    $viewContent = "login.php";
    include "views/master.php";
}
exit;
