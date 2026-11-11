<?php
/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

$cronRoutes =  $app->group('/cron', function () use ($app) {
    $app->get('/master',            'App\Controllers\Cronjob:master');
    $app->get('/tg-backup',         'App\Controllers\Cronjob:tgBackup');
});

