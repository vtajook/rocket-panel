<?php

/**
 * Agency module – database schema.
 *
 * Every step is idempotent (create-if-missing / add-column-if-missing), so it is
 * safe to run on a fresh install, on an old panel, or twice in a row. It only
 * ever ADDS tables and columns; nothing existing is altered or dropped, which is
 * why current resellers keep their username and password untouched.
 */

namespace App\Agency;

use Illuminate\Database\Capsule\Manager as Capsule;

if (!defined('PATH')) die();

class Schema
{
    /** bump when a migration step is added */
    const VERSION = 3;

    private static $checked = false;

    private static function markerPath()
    {
        return PATH_STORAGE . DS . "agency_schema.ver";
    }

    /**
     * Cheap per-request guard: a marker file says which schema version is in
     * place, so normal requests cost one tiny file read and no query.
     */
    public static function ensure()
    {
        if (self::$checked) {
            return true;
        }
        self::$checked = true;

        $marker = self::markerPath();
        if (@file_get_contents($marker) === (string) self::VERSION) {
            return true;
        }
        return self::migrate();
    }

    /** Forget the marker (e.g. after a database restore) so the next request re-checks. */
    public static function invalidate()
    {
        self::$checked = false;
        @unlink(self::markerPath());
    }

    /** True only when the tables the module needs really exist. */
    public static function isReady()
    {
        try {
            db();
            $schema = Capsule::schema();
            return $schema->hasTable('agency_ledger') && $schema->hasColumn('admins', 'perms');
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function migrate()
    {
        $lock = @fopen(PATH_STORAGE . DS . "agency_schema.lock", "c");
        if ($lock) {
            @flock($lock, LOCK_EX);
        }

        try {
            db();
            $schema = Capsule::schema();

            self::addColumns($schema, 'admins', [
                'parent_id'   => function ($t) { $t->integer('parent_id')->default(0); },
                'phone'       => function ($t) { $t->string('phone', 32)->default(''); },
                'tg_chat'     => function ($t) { $t->string('tg_chat', 32)->default(''); },
                'brand'       => function ($t) { $t->string('brand', 100)->default(''); },
                'note'        => function ($t) { $t->string('note', 500)->default(''); },
                'ban_until'   => function ($t) { $t->integer('ban_until')->default(0); },
                'ban_reason'  => function ($t) { $t->string('ban_reason', 255)->default(''); },
                'frozen'      => function ($t) { $t->tinyInteger('frozen')->default(0); },
                'frozen_note' => function ($t) { $t->string('frozen_note', 255)->default(''); },
                'expiry'      => function ($t) { $t->integer('expiry')->default(0); },
                'perms'       => function ($t) { $t->text('perms')->nullable(); },
                'limits'      => function ($t) { $t->text('limits')->nullable(); },
                'pricing'     => function ($t) { $t->text('pricing')->nullable(); },
                'api_allow'   => function ($t) { $t->string('api_allow', 500)->default(''); },
                'api_rate'    => function ($t) { $t->integer('api_rate')->default(0); },
                'token_ver'   => function ($t) { $t->integer('token_ver')->default(0); },
                'created_by'  => function ($t) { $t->integer('created_by')->default(0); },
                'last_login'  => function ($t) { $t->integer('last_login')->default(0); },
                'last_ip'     => function ($t) { $t->string('last_ip', 45)->default(''); },
            ]);

            self::addColumns($schema, 'users', [
                'customer_id' => function ($t) { $t->integer('customer_id')->default(0); },
            ]);

            if (!$schema->hasTable('agency_ledger')) {
                $schema->create('agency_ledger', function ($t) {
                    $t->bigIncrements('id');
                    $t->integer('t')->default(0);
                    $t->integer('agent_id')->default(0);
                    $t->bigInteger('amt')->default(0);
                    $t->bigInteger('bal')->default(0);
                    $t->string('kind', 20)->default('');
                    $t->string('sale_id', 32)->default('');
                    $t->integer('seller_id')->default(0);
                    $t->integer('user_id')->default(0);
                    $t->string('acct', 100)->default('');
                    $t->integer('days')->default(0);
                    $t->float('gb')->default(0);
                    $t->integer('devices')->default(0);
                    $t->integer('plan_id')->default(0);
                    $t->bigInteger('profit')->default(0);
                    $t->string('svc', 20)->default('ssh');
                    $t->string('ref', 100)->default('');
                    $t->integer('by_id')->default(0);
                    $t->string('by_name', 100)->default('');
                    $t->string('ip', 45)->default('');
                    $t->string('note', 500)->default('');
                    $t->index('agent_id');
                    $t->index('sale_id');
                    $t->index('user_id');
                    $t->index('seller_id');
                    $t->index('t');
                });
            }

            if (!$schema->hasTable('agency_plans')) {
                $schema->create('agency_plans', function ($t) {
                    $t->increments('id');
                    $t->string('name', 100)->default('');
                    $t->integer('days')->default(30);
                    $t->float('gb')->default(0);
                    $t->integer('devices')->default(1);
                    $t->bigInteger('price')->default(0);
                    $t->bigInteger('renew')->default(0);
                    $t->tinyInteger('enable')->default(1);
                    $t->integer('sort')->default(0);
                    $t->integer('ctime')->default(0);
                });
            }

            if (!$schema->hasTable('agency_customers')) {
                $schema->create('agency_customers', function ($t) {
                    $t->increments('id');
                    $t->integer('owner_id')->default(0);
                    $t->string('name', 100)->default('');
                    $t->string('phone', 32)->default('');
                    $t->string('tg_id', 64)->default('');
                    $t->string('note', 500)->default('');
                    $t->integer('ctime')->default(0);
                    $t->index('owner_id');
                });
            }

            if (!$schema->hasTable('agency_activity')) {
                $schema->create('agency_activity', function ($t) {
                    $t->bigIncrements('id');
                    $t->integer('t')->default(0);
                    $t->integer('actor_id')->default(0);
                    $t->string('actor_name', 100)->default('');
                    $t->string('as_admin', 100)->default('');
                    $t->string('action', 40)->default('');
                    $t->string('target', 150)->default('');
                    $t->text('detail')->nullable();
                    $t->string('ip', 45)->default('');
                    $t->index('actor_id');
                    $t->index('t');
                });
            }

            if (!$schema->hasTable('agency_requests')) {
                $schema->create('agency_requests', function ($t) {
                    $t->increments('id');
                    $t->integer('t')->default(0);
                    $t->integer('agent_id')->default(0);
                    $t->string('acct', 100)->default('');
                    $t->string('note', 500)->default('');
                    $t->string('status', 20)->default('pending');
                    $t->string('answer', 500)->default('');
                    $t->integer('decided_by')->default(0);
                    $t->integer('decided_at')->default(0);
                    $t->index('agent_id');
                    $t->index('status');
                });
            }

            if (!$schema->hasTable('agency_api_keys')) {
                $schema->create('agency_api_keys', function ($t) {
                    $t->increments('id');
                    $t->integer('agent_id')->default(0);
                    $t->string('name', 100)->default('');
                    $t->string('key_hash', 64)->default('');
                    $t->string('prefix', 16)->default('');
                    $t->tinyInteger('enable')->default(1);
                    $t->integer('ctime')->default(0);
                    $t->integer('last_used')->default(0);
                    $t->string('last_ip', 45)->default('');
                    $t->index('agent_id');
                    $t->index('key_hash');
                });
            }

            if (!$schema->hasTable('agency_api_log')) {
                $schema->create('agency_api_log', function ($t) {
                    $t->bigIncrements('id');
                    $t->integer('t')->default(0);
                    $t->integer('agent_id')->default(0);
                    $t->integer('key_id')->default(0);
                    $t->string('ip', 45)->default('');
                    $t->string('method', 10)->default('');
                    $t->string('path', 200)->default('');
                    $t->integer('status')->default(0);
                    $t->index('agent_id');
                    $t->index('t');
                });
            }

            self::seedDefaults();

            @file_put_contents(self::markerPath(), (string) self::VERSION);
            $ok = true;
        } catch (\Exception $e) {
            // never take the whole panel down because the module could not prepare its tables
            self::logError("agency schema migration failed: " . $e->getMessage());
            $ok = false;
        }

        if ($lock) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
        return $ok;
    }

    private static function addColumns($schema, $table, $columns)
    {
        foreach ($columns as $name => $definition) {
            if (!$schema->hasColumn($table, $name)) {
                $schema->table($table, function ($t) use ($definition) {
                    $definition($t);
                });
            }
        }
    }

    /**
     * Existing resellers could do everything in the old panel, so give them the
     * same abilities here; nothing changes for them until the manager decides so.
     */
    private static function seedDefaults()
    {
        $rows = db('admins')->where('role', '!=', 'admin')->get();
        foreach ($rows as $row) {
            if ($row->perms === null || $row->perms === '') {
                db('admins')->where('id', $row->id)->update([
                    'perms' => json_encode(Agency::legacyPerms()),
                ]);
            }
        }
    }

    private static function logError($message)
    {
        try {
            container()->logger->error($message);
        } catch (\Exception $e) {
            @error_log($message);
        }
    }
}
