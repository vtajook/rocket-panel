<?php

$accountRoutes =  $app->group('/account', function () use ($app) {
    $app->get('/settings',      'App\Controllers\UserAccount\Users:settings');
    $app->post('/login',        'App\Controllers\UserAccount\Users:login');
    $app->get('/{token}',       'App\Controllers\UserAccount\Users:userInfoByToken');
});
