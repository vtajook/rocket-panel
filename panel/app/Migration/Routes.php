<?php

$app->group('/migrate', function () use ($app, $container) {

    $app->get('[/]',   function () use ($app, $container) {
        $migrator = new \App\Migration\Migrator\Lib($container);
        $migrator->run();
    });
});
