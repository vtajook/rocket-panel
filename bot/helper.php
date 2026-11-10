<?php

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


function panelUrl()
{
    $panelUrl   = "http://" . $_SERVER['HTTP_HOST'];
    $panelPort  = getPanelPort();
    if ($panelPort) {
        $panelUrl .= ":$panelPort";
    }
    return  $panelUrl;
}
