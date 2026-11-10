<?php

function baseUrl($seg = "")
{
    $url = getConfig("url");
    if (!empty($seg)) {
        $url .= "$seg";
    }
    return $url;
}

function parseEnvFile()
{
    $envContents = file_get_contents("./../panel/.env");
    $lines       = explode("\n", $envContents);
    $envArray = [];
    foreach ($lines as $line) {
        // Remove comments and empty lines
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        // Split the line into key and value
        list($key, $value) = explode('=', $line, 2);

        // Remove surrounding quotes (if any)
        $value = trim($value, '"');

        // Store in the array
        $envArray[$key] = $value;
    }
    return $envArray;
}

function getPanelPort()
{
    $envArray = parseEnvFile();
    if (isset($envArray["PORT_PANEL"])) {
        return $envArray["PORT_PANEL"];
    }

    return false;
}

function getServerIp()
{
    return !empty($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : "";
}

function getTokenInUrl()
{
    $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    $parts = explode('/', $path);
    $token = end($parts);

    if (!empty($token)) {
        return $token;
    }
    return false;
}

function getConfig($name)
{
    global $configs;

    if (isset($configs[$name])) {
        return $configs[$name];
    }

    return "";
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

