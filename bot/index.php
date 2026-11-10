<?php
include "helper.php";

$panelUrl =  panelUrl();
$telegramRouteUrl = "$panelUrl/telegram";

// Get the incoming Telegram webhook data as a JSON string
$webhookData = file_get_contents('php://input');


if (!empty($webhookData)) {

    // Initialize a cURL session
    $ch = curl_init($telegramRouteUrl);

    // Set cURL options
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $webhookData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($webhookData)
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the cURL request
    $response = curl_exec($ch);

    // Check the HTTP response code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Check for cURL errors
    if (curl_errno($ch)) {
        die('Curl error: ' . curl_error($ch));
    }

    // Close the cURL session
    curl_close($ch);
    
    // Output the response received from your Slim Framework project (optional)
    if ($httpCode === 200) {
        echo $response;
        die;
    }
}

echo "Not found";
die;
