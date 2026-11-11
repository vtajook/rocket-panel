<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */
$toEnqueueArr = array();

function container()
{
    global $app;
    return $app->getContainer();
}

function db($table = null)
{

    $db = container()->db;
    if (!empty($table)) {
        return $db->table($table);
    }
    return $db;
}

function validator()
{
    return container()->validator;
}


function session()
{
    return container()->session;
}


function baseUrl($uri = '', $protocol = NULL)
{
    $base_url = slashItem('url');
    if (isset($protocol)) {
        if ($protocol === '') {
            $base_url = substr($base_url, strpos($base_url, '//'));
        } else {
            $base_url = $protocol . substr($base_url, strpos($base_url, '://'));
        }
    }
    return $base_url . uriString($uri);
}

function assets($path = '')
{
    return baseUrl("assets/$path");
}

function getMainAppEnqueue()
{
    $manifestPath = PATH_ASSETS . DS . "mix-manifest.json";
    $manifestFile = file_get_contents($manifestPath);
    $manifestData = json_decode($manifestFile, true);

    return [
        "js_url"    => assets($manifestData["/js/app.js"]),
        "css_url"   => assets($manifestData["/css/app.css"]),
    ];
}

function enqueueStyle($fileAddr, $inFooter = false)
{
    if (!empty($fileAddr)) {
        global $toEnqueueArr;
        if ($inFooter) {
            $toEnqueueArr['styles']['footer'][] = trim($fileAddr);
        } else {
            $toEnqueueArr['styles']['header'][] = trim($fileAddr);
        }
    }
}

function enqueueStyleHeader($fileAddr)
{
    enqueueStyle($fileAddr, false);
}

function enqueueStyleFooter($fileAddr)
{
    enqueueStyle($fileAddr, true);
}

function enqueueScript($fileAddr, $inFooter = false)
{
    if (!empty($fileAddr)) {
        global $toEnqueueArr;
        if ($inFooter) {
            $toEnqueueArr['scripts']['footer'][] = trim($fileAddr);
        } else {
            $toEnqueueArr['scripts']['header'][] = trim($fileAddr);
        }
    }
}

function enqueueScriptHeader($fileAddr)
{
    enqueueScript($fileAddr, false);
}

function enqueueScriptFooter($fileAddr)
{
    enqueueScript($fileAddr, true);
}

function printStyleTag($fileAddr)
{
    if (!empty($fileAddr)) {
        echo '<link rel="stylesheet" href="' . trim($fileAddr) . '" type="text/css" />' . "\n";
    }
}

function printScriptTag($fileAddr)
{
    if (!empty($fileAddr)) {
        echo '<script type="text/javascript" src="' . trim($fileAddr) . '"></script>' . "\n";
    }
}

function headerFiles()
{
    global $toEnqueueArr;
    if (!empty($toEnqueueArr['styles']['header'])) {
        foreach ($toEnqueueArr['styles']['header'] as $styleFile) {
            printStyleTag($styleFile);
        }
    }
    if (!empty($toEnqueueArr['scripts']['header'])) {
        foreach ($toEnqueueArr['scripts']['header'] as $scriptFile) {
            printScriptTag($scriptFile);
        }
    }
}

function footerFiles()
{
    global $toEnqueueArr;
    if (!empty($toEnqueueArr['styles']['footer'])) {
        foreach ($toEnqueueArr['styles']['footer'] as $styleFile) {
            printStyleTag($styleFile);
        }
    }
    if (!empty($toEnqueueArr['scripts']['footer'])) {
        foreach ($toEnqueueArr['scripts']['footer'] as $scriptFile) {
            printScriptTag($scriptFile);
        }
    }
}

function slashItem($item)
{
    $configValue = getConfig($item);
    if (!$configValue) {
        return NULL;
    } elseif (trim($configValue) === '') {
        return '';
    }

    return rtrim($configValue, '/') . '/';
}

function uriString($uri)
{

    if (is_array($uri)) {
        return http_build_query($uri);
    }

    is_array($uri) && $uri = implode('/', $uri);
    return ltrim($uri, '/');
}


function getArrayValue($array, $key, $defaultVal = "")
{

    if (is_object($array)) {
        $array = (array)$array;
    }
    if (!empty($array) && is_array($array)) {
        if (isset($array[$key])) {
            return $array[$key];
        }
    }
    return $defaultVal;
}

function trimArrayValues($array)
{
    foreach ($array as $key => $value) {
        if (!is_array($value) && !is_object($value)) {
            $value = trim((string) $value);
        }
        $array[$key] = $value;
    }
    return $array;
}

function trafficToGB($traffic)
{
    return round($traffic / 1024, 3);
}

function userIPAddress()
{
    $ip = "";
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

function getServerIp()
{
    return !empty($_SERVER["SERVER_ADDR"]) ? $_SERVER["SERVER_ADDR"] : "";
}

function convertToPrettyUnit($value, $unit = "")
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $base = 1024;
    $index = 0;

    // Find the appropriate unit to use
    while ($value >= $base && $index < count($units) - 1) {
        $value /= $base;
        $index++;
    }

    // Construct the pretty unit format
    if (empty($unit)) {
        $prettyUnit = $units[$index];
    } else {
        $prettyUnit = $unit . ' ' . $units[$index];
    }

    // Return the formatted value with the appropriate unit
    return number_format($value, 2) . ' ' . $prettyUnit;
}

function getUsageColor($usagePercent)
{
    // Convert the percentage to a numeric value without the percentage sign
    $usageValue = floatval(str_replace('%', '', $usagePercent));

    // Define color ranges and their corresponding usage percentage
    $colorRanges = array(
        10 => '#00FF00',   // Green - Less than 10% usage
        30 => '#FFFF00',   // Yellow - Less than 30% usage
        70 => '#FFA500',   // Orange - Less than 70% usage
        100 => '#FF0000',  // Red - 100% usage or more
    );

    // Find the appropriate color for the usage percentage
    foreach ($colorRanges as $range => $color) {
        if ($usageValue <= $range) {
            return $color;
        }
    }

    // Return the last color if the usage percentage is higher than the highest range
    return end($colorRanges);
}

function getContrastTextColor($hexcolor)
{
    list($red, $green, $blue) = sscanf($hexcolor, "#%02x%02x%02x");
    $luma = ($red + $green + $blue) / 3;

    if ($luma < 128) {
        $textcolour = "white";
    } else {
        $textcolour = "black";
    }
    return $textcolour;
}

function truncateStr($string, $maxLength = 20)
{
    if (strlen($string) <= $maxLength) {
        return $string;
    } else {
        return substr($string, 0, $maxLength) . '...';
    }
}

function getSessionUser()
{
    if (session()->get("userInfo")) {
        return  (array) session()->get('userInfo');
    }

    return false;
}

function viewContentPath($viewFile)
{
    return PATH_APP . DS . "Views" . DS . $viewFile;
}

function loadViewSection($path)
{
    $fullPath = PATH_APP . DS . "Views" . DS . $path;
    if (file_exists($fullPath)) {
        include $fullPath;
    }
}

function inlineIcon($iconName, $customClass = "")
{
    $html = "<i class='far fa-$iconName icon $customClass'></i>";
    return  $html;
}

function getAdminRole()
{
    return  container()->userInfo->role;
}

function getAdminUsername()
{
    return  container()->userInfo->username;
}

function getCurrentDate()
{
    return  jdate()->format("d F Y");
}

function generateNetmodQR($userInfo)
{

    $username   = $userInfo->username;
    $password   = $userInfo->password;
    $serverName = getServerName();
    $port       = getenv("PORT_SSH");

    $str = "chl=ssh://$username:$password@$serverName:$port";
    $url = 'https://chart.googleapis.com/chart?cht=qr&chs=160x160&chld=L|0&' . $str;
    return $url;
}

function convertToEnNum($string)
{
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    $num = range(0, 9);
    $convertedPersianNums = str_replace($persian, $num, $string);
    $englishNumbersOnly = str_replace($arabic, $num, $convertedPersianNums);

    return $englishNumbersOnly;
}

function formatTraffice($traffic)
{
    if ($traffic < 1024) {
        return number_format(round($traffic, 2)) . " MB";
    }
    return number_format(round($traffic / 1024, 2)) . " GB";
}

function calcDifferenceDate($startDate, $endDate)
{
    $startDate  = new DateTime(date("Y-m-d", $startDate));
    $endDate    = new DateTime(date("Y-m-d", $endDate));

    $interval   = $startDate->diff($endDate);
    $diffDyas   = $interval->days;
    $result     = "";


    if ($diffDyas >= 365) {
        $year       = floor($diffDyas / 365);
        $diffDyas   = floor($diffDyas % 365);
        $result .= $year . ($diffDyas > 0 ? " سال و " : " ساله ");
    }


    if ($diffDyas >= 29) {
        $month = 30;
        if ($diffDyas == 29) {
            $month = 29;
        } else if ($diffDyas == 30) {
            $month = 30;
        } else if ($diffDyas == 31) {
            $month = 31;
        }
        $month      = floor($diffDyas / $month);
        $diffDyas   = floor($diffDyas % $month);
        $result     .= $month . ($diffDyas ? " ماه و " : " ماهه ");
    }

    if ($diffDyas && $diffDyas < 29) {
        $result .= $diffDyas . (!empty($result) ? " روزه" : " روزه");
    }
    return  $result;
}

function getEndOfDate($date)
{
    if (!is_numeric($date)) {
        $date = strtotime($date);
    }
    $date = strtotime("today", $date);
    return strtotime("tomorrow", $date) - 1;
}

function  getStartOfDate($date)
{
    if (!is_numeric($date)) {
        $date = strtotime($date);
    }
    return  strtotime("today", $date);
}


function userStatusLabel($status)
{
    $arrayStats = [
        "active"            => "فعال",
        "de_active"         => "غیر فعال",
        "expiry_traffic"    => "انقضای ترافیک",
        "expiry_date"       => "انقضای تاریخ",
    ];

    if (!empty($arrayStats[$status])) {
        return $arrayStats[$status];
    }

    return $status;
}


function panelVersion()
{
    $v = @trim((string) @file_get_contents(PATH . DS . "VERSION"));
    return preg_match('/^[0-9][0-9A-Za-z.\-]*$/', $v) ? $v : "2.0.0";
}

function githubLastVersion()
{
    $url = getConfig("versionUrl");
    if (!$url) {
        return false;
    }
    $context = stream_context_create(array("http" => array("timeout" => 8, "header" => "User-Agent: rocket-panel\r\nCache-Control: no-cache\r\n")));
    // raw.githubusercontent caches each exact URL for minutes: a changing query string gets the latest file
    $text = trim((string) @file_get_contents($url . (strpos($url, '?') === false ? '?' : '&') . 't=' . time(), false, $context));
    return preg_match('/^[0-9][0-9A-Za-z.\-]*$/', $text) ? $text : false;
}

function getServerName()
{
    return  !empty($_SERVER["SERVER_NAME"]) ? $_SERVER["SERVER_NAME"] : getServerIp();
}

function getUserConfig($user)
{
    $sshPort      = getenv("PORT_SSH");
    $udpPort      = getenv("PORT_UDP");

    $host         = getServerName();
    $baseUrl      = (isset($_SERVER['HTTPS']) ? "https://$host" : "http://$host");
    $userLink     = "$baseUrl/account/" . $user->token;
    return [
        "host"          => $host,
        "public_link"   => $userLink,
        "ssh_port"      => $sshPort,
        "udp_port"      => $udpPort,
        "username"      => $user->username,
        "password"      => $user->password,
        "start_date"    => !empty($user->start_date_jd) ? $user->start_date_jd : "-",
        "end_date"      => !empty($user->end_date_jd) ? $user->end_date_jd : "-",
    ];
}

function getLocalOnlienUsers()
{
    $onlinePath = PATH_STORAGE . DS . "online.json";

    if (file_exists($onlinePath)) {
        $content =  file_get_contents($onlinePath);
        if (!empty($content)) {
            try {
                return json_decode($content, true);
            } catch (\Exception $err) {
            }
        }
    }

    return [];
}

function setLocalOnlienUsers($onlineUsers)
{
    $onlinePath = PATH_STORAGE . DS . "online.json";
    @file_put_contents($onlinePath, json_encode($onlineUsers));
}

function adjustDateTime($dateTimeString)
{
    // Create a DateTime object from the given date and time string
    $dateTime = new DateTime($dateTimeString);

    // Get the year from the DateTime object
    $year = (int)$dateTime->format("Y");

    if ($year > 2025) {
        // Change the year to 2025
        $dateTime->setDate(2025, $dateTime->format("m"), $dateTime->format("d"));
        return $dateTime->format("Y-m-d");
    } else {
        return $dateTime->format("Y-m-d");
    }
}

function generateRandomPassword($length, $type)
{
    $characters = '';

    if ($type == 'number') {
        $characters = '0123456789';
    } elseif ($type == 'letter') {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    } elseif ($type == 'number_letter') {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }

    $password = '';
    $charactersLength = strlen($characters);

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, $charactersLength - 1)];
    }

    return $password;
}

function isDevMode()
{
    $serverName = $_SERVER['SERVER_NAME'];

    if ($serverName === 'localhost' || $serverName === '127.0.0.1') {
        return true;
    }
    return false;
}

function makeCurlRequest($url, $method = 'GET', $headers = [], $data = [], $sleeptime = 0)
{
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
    if ($sleeptime) {
        sleep($sleeptime);
    }

    $response = curl_exec($ch);
    if ($response === false) {
        return false;
    }

    curl_close($ch); // Close cURL session

    return $response;
}

function getFilteringData()
{
    $requestId = getCheckHostReqId();
    if (!empty($requestId)) {
        $result = getCheckHostTcp($requestId);
        if (!empty($result)) {
            return  $result;
        }
    }
    return  false;
}

function getCheckHostReqId()
{
    $serverip   = getServerName();
    $port       = getenv("PORT_SSH");
    $host       = "$serverip:$port";
    $host       = preg_replace('/\s+/', '', $host);
    $url        = "https://check-host.net/check-tcp?host=$host&max_nodes=50";
    $headers    = [
        "Accept: application/json",
        "Cache-Control: no-cache"
    ];

    $response = makeCurlRequest($url, "POST", $headers, [], 4);
    if (!empty($response)) {
        $response = json_decode($response, true);
        if (!empty($response["request_id"])) {
            $reqId = $response["request_id"];
            return $reqId;
        }
    }
    return false;
}

function convertFlagName($name)
{
    $countries = [
        "ir" => "ایران",
        "us" => "آمریکا",
        "fr" => "فرانسه",
        "de" => "آلمان"
    ];
    return !empty($countries[$name]) ? $countries[$name] : "";
}

function getCheckHostTcp($reqId)
{

    $url        = "https://check-host.net/check-result/$reqId";

    $headers    = [
        "Accept: application/json",
        "Cache-Control: no-cache"
    ];

    $response = makeCurlRequest($url, "POST", $headers, [], 4);
    if (!empty($response)) {
        $response = json_decode($response, true);
        if (!empty($response)) {
            $result = [];

            $countries = ["ir", "us", "fr", "de"];
            foreach ($response as $key => $value) {

                $flag = str_replace(".node.check-host.net", "", $key);
                $flag = preg_replace("/[0-9]+/", "", $flag);

                if (in_array($flag, $countries)) {
                    if (isset($value[0]["time"])) {
                        $status = "online";
                    } else {
                        $status = "filter";
                    }

                    $result[] = [
                        "flag"      => $flag,
                        "flag_img"  => assets("images/flags/$flag.png"),
                        "status"    => $status,
                        "name"      => convertFlagName($flag)
                    ];
                }
            }
            return  $result;
        }
    }
    return false;
}

function generateUserToken($user_id, $length = 6)
{
    $timestamp = time(); // Current timestamp

    // Combine user ID and timestamp
    $dataToHash = $user_id . $timestamp . random_bytes($length);

    $hashedData = hash('sha256', $dataToHash); // Hash the combined data

    return substr($hashedData, 0, $length); // Return a substring of the hash 
}
