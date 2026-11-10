<?php
/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

$telegramRoutes =  $app->group('/telegram', function () use ($app) {
    $app->post('', 'App\Controllers\Telegram\Main:handleRequest');
});
