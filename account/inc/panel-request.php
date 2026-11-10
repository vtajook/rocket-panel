<?php

function makeCurlReq($endpoint = "", $method = "POST", $data = [])
{

    $url = getConfig("panelUrl") . ":" . getPanelPort();
    $url .= "/account/$endpoint";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    if ($method === 'POST' || $method === 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);

    if ($response === false) {
        return false;
    }
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch); // Close cURL session

    if ($httpStatus == 200) {
        $response = json_decode($response, true);
        return  $response;
    }

    return false;
}


function getUserInfoByToken($token)
{
    $response = makeCurlReq("$token", "GET");
    if ($response) {
        return $response;
    }
    return false;
}


function loginUser($username, $password)
{
    $url = "login";
    $pdata = [
        "username" => $username,
        "password" => $password,
    ];

    $response = makeCurlReq($url, "POST", $pdata);
    if ($response) {
        return $response["token"];
    }
    return false;
}

function getSettings()
{
    $url        = "settings";
    $response   = makeCurlReq($url, "GET");
    return $response;
}
