<?php
/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

$cronRoutes =  $app->group('/cron', function () use ($app) {
    $app->get('/master',            'App\Controllers\Cronjob:master');
    $app->get('/multi-users',       'App\Controllers\Cronjob:multiUser');
    $app->get('/expire-users',      'App\Controllers\Cronjob:expireUsers');
    $app->get('/tg-backup',         'App\Controllers\Cronjob:tgBackup');
    $app->get('/sync-traffic',      'App\Controllers\Cronjob:syncTraffic');
});

