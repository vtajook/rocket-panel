<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

$app->get('/login',                             'App\Controllers\Login:index');
$app->post('/ajax/login',                       'App\Controllers\Login:ajaxLogin');

$adminPanel = $app->group('', function () use ($app) {

    $app->get('/[dashboard]',                   'App\Controllers\Dashboard:index');
    $app->get('/admins',                        'App\Controllers\Admins:index');
    $app->get('/logout',                        'App\Controllers\Users:logout');


    $app->group('/users', function () use ($app) {
        $app->get('[/]',                        'App\Controllers\Users:index');
        $app->get('/online',                    'App\Controllers\Users:online');
    });

    $app->group('/settings', function () use ($app) {
        $app->get('[/]',                        'App\Controllers\Settings:index');
        $app->get('/backup',                    'App\Controllers\Settings:backup');
        $app->get('/api',                       'App\Controllers\Settings:api');
        $app->get('/users-panel',               'App\Controllers\Settings:usersPanel');
    });

    $app->group('/pages', function () use ($app) {
        $app->get('/filtering',                  'App\Controllers\Pages:filtering');
    });

    $app->group('/ajax', function () use ($app) {
        $app->post('/reboot-server',            'App\Controllers\Pages:ajaxRebootServer');
        $app->get('/is-ready',                  'App\Controllers\Pages:ajaxIsReady');

        $app->group('/users', function () use ($app) {
            $app->post('',                      'App\Controllers\Users:ajaxAddUser');
            $app->post('/kill-pid',             'App\Controllers\Users:ajaxKillPidUsers');
            $app->post('/list',                 'App\Controllers\Users:ajaxUsersList');
            $app->post('/online-list',          'App\Controllers\Users:ajaxUsersOnlinesList');

            $app->group('/bulk', function () use ($app) {
                $app->post('/create',            'App\Controllers\Users:ajaxAddBulkUsers');
                $app->post('/delete',            'App\Controllers\Users:ajaxDeleteBulkUsers');
            });

            $app->group('/{id}', function () use ($app) {
                $app->put('[/]',                'App\Controllers\Users:ajaxEditUser');
                $app->delete('[/]',             'App\Controllers\Users:ajaxDeleteUser');
                $app->put('/toggle-active',     'App\Controllers\Users:ajaxToggleUserActive');
                $app->put('/reset-traffic',     'App\Controllers\Users:ajaxResetUserTraffic');
                $app->put('/renewal',           'App\Controllers\Users:ajaxSaveRenewal');
            });
        });

        $app->group('/admins', function () use ($app) {
            $app->post('',                      'App\Controllers\Admins:ajaxAddUser');
            $app->post('/list',                 'App\Controllers\Admins:ajaxUsersList');
            $app->put('/{id}',                  'App\Controllers\Admins:ajaxEditUser');
            $app->delete('/{id}',               'App\Controllers\Admins:ajaxDeleteUser');
        });

        $app->group('/pages', function () use ($app) {
            $app->get('/filtering',              'App\Controllers\Pages:ajaxFilteringDate');
        });

        $app->group('/settings', function () use ($app) {

            $app->post('',                      'App\Controllers\Settings:ajaxSaveSettings');
            $app->post('/users-panel',          'App\Controllers\Settings:ajaxSaveUsersPanel');
            $app->group('/public-api', function () use ($app) {
                $app->post('',                  'App\Controllers\Settings:ajaxAaddPublicApi');
                $app->post('/list',             'App\Controllers\Settings:ajaxListPublicApi');
                $app->delete('/{id}',           'App\Controllers\Settings:ajaxDeletePublicApi');
            });

            $app->group('/backup', function () use ($app) {
                $app->post('/import',           'App\Controllers\Settings:ajaxImportBackup');
                $app->post('/export',           'App\Controllers\Settings:ajaxCreateBackup');
                $app->delete('/export',         'App\Controllers\Settings:ajaxDeleteExportFile');
            });
        });
    });


    $app->group('/ajax-views', function () use ($app) {
        $app->group('/users', function () use ($app) {
            $app->get('/add',                       'App\Controllers\Users:ajaxViewAdd');
            $app->get('/bulk-add',                  'App\Controllers\Users:ajaxViewBulkAdd');
            $app->get('/{id}/edit',                 'App\Controllers\Users:ajaxViewEdit');
            $app->get('/{id}/info',                 'App\Controllers\Users:ajaxViewDetails');
        });
        $app->group('/admins', function () use ($app) {
            $app->get('/add',                       'App\Controllers\Admins:ajaxViewAdd');
            $app->get('/{id}/edit',                 'App\Controllers\Admins:ajaxViewEdit');
        });
    });
});

$adminPanel->add(new \App\Middlewares\PanelPerms($container))
    ->add(new \App\Middlewares\PanelAuth($container));
