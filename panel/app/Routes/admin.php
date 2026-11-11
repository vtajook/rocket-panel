<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

$app->get('/login',                             'App\Controllers\Login:index');
$app->post('/ajax/login',                       'App\Controllers\Login:ajaxLogin');

$adminPanel = $app->group('', function () use ($app) {

    $app->get('/[dashboard]',                   'App\Controllers\Dashboard:index');
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
        $app->get('/tg',                        'App\Controllers\Settings:tg');
        $app->get('/account',                   'App\Controllers\Settings:account');
    });

    $app->group('/pages', function () use ($app) {
        $app->get('/filtering',                  'App\Controllers\Pages:filtering');
    });

    $app->get('/update',                        'App\Controllers\Updater:index');

    // agency (resellers). Manager-only except /agency/my... and /agency/back
    $app->group('/agency', function () use ($app) {
        $app->get('[/]',                        'App\Controllers\Agency:index');
        $app->get('/new',                       'App\Controllers\Agency:agentPage');
        $app->get('/agent/{id:[0-9]+}',         'App\Controllers\Agency:agentPage');
        $app->post('/save[/{id:[0-9]+}]',       'App\Controllers\Agency:saveAgent');
        $app->post('/agent/{id:[0-9]+}/wallet', 'App\Controllers\Agency:walletAdjust');
        $app->post('/agent/{id:[0-9]+}/ban',    'App\Controllers\Agency:banAgent');
        $app->post('/agent/{id:[0-9]+}/freeze', 'App\Controllers\Agency:freezeAgent');
        $app->get('/requests',                  'App\Controllers\AgencyExtras:requestsPage');
        $app->post('/requests/{id:[0-9]+}/decide', 'App\Controllers\AgencyExtras:decideRequest');
        $app->get('/apilog',                    'App\Controllers\AgencyExtras:apiLogPage');
        $app->get('/my/customers',              'App\Controllers\AgencyExtras:customersPage');
        $app->post('/my/customers/save',        'App\Controllers\AgencyExtras:saveCustomer');
        $app->post('/my/customers/{id:[0-9]+}/delete', 'App\Controllers\AgencyExtras:deleteCustomer');
        $app->post('/my/accounts/customer',     'App\Controllers\AgencyExtras:assignCustomer');
        $app->get('/my/requests',               'App\Controllers\AgencyExtras:myRequests');
        $app->post('/my/requests/new',          'App\Controllers\AgencyExtras:createRequest');
        $app->get('/my/keys',                   'App\Controllers\AgencyExtras:keysPage');
        $app->post('/my/keys/new',              'App\Controllers\AgencyExtras:newKey');
        $app->post('/my/keys/{id:[0-9]+}/delete', 'App\Controllers\AgencyExtras:deleteKey');
        $app->post('/agent/{id:[0-9]+}/delete', 'App\Controllers\Agency:deleteAgent');
        $app->post('/agent/{id:[0-9]+}/transfer', 'App\Controllers\Agency:transferAccounts');
        $app->post('/agent/{id:[0-9]+}/login',  'App\Controllers\Agency:impersonate');
        $app->get('/back',                      'App\Controllers\Agency:impersonateBack');
        $app->get('/plans',                     'App\Controllers\Agency:plansPage');
        $app->post('/plans/save',               'App\Controllers\Agency:savePlan');
        $app->post('/plans/{id:[0-9]+}/delete', 'App\Controllers\Agency:deletePlan');
        $app->post('/settings',                 'App\Controllers\Agency:saveSettings');
        $app->get('/ledger',                    'App\Controllers\Agency:ledgerPage');
        $app->get('/activity',                  'App\Controllers\Agency:activityPage');
        $app->get('/reports',                   'App\Controllers\Agency:reportsPage');
        $app->get('/my',                        'App\Controllers\Agency:myPage');
        $app->get('/my/ledger',                 'App\Controllers\Agency:myLedger');
        $app->post('/my/password',              'App\Controllers\Agency:myPassword');
        $app->post('/my/quote',                 'App\Controllers\Agency:myQuote');
    });

    $app->group('/ajax', function () use ($app) {
        $app->post('/update/run',               'App\Controllers\Updater:ajaxRun');
        $app->get('/update/log',                'App\Controllers\Updater:ajaxLog');
        $app->post('/reboot-server',            'App\Controllers\Pages:ajaxRebootServer');
        $app->get('/is-ready',                  'App\Controllers\Pages:ajaxIsReady');

        $app->group('/users', function () use ($app) {
            $app->post('',                      'App\Controllers\Users:ajaxAddUser');
            $app->post('/purge-expired',        'App\Controllers\Users:ajaxPurgeExpired');
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


        $app->group('/pages', function () use ($app) {
            $app->get('/filtering',              'App\Controllers\Pages:ajaxFilteringDate');
        });

        $app->group('/settings', function () use ($app) {

            $app->post('',                      'App\Controllers\Settings:ajaxSaveSettings');
            $app->post('/users-panel',          'App\Controllers\Settings:ajaxSaveUsersPanel');
            $app->post('/sync-users',           'App\Controllers\Settings:ajaxSyncUsers');
            $app->post('/tg',                   'App\Controllers\Settings:ajaxSaveTg');
            $app->post('/account',              'App\Controllers\Settings:ajaxSaveAccount');
            $app->post('/tg-test',              'App\Controllers\Settings:ajaxTgTest');
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
    });
});

$adminPanel->add(new \App\Middlewares\PanelPerms($container))
    ->add(new \App\Middlewares\PanelAuth($container));
