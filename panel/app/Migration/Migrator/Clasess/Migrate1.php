<?php

namespace App\Migration\Migrator\Clasess;

use App\Migration\Migrator\Abstractor;

class Migrate1 extends Abstractor
{

    function __construct($cont)
    {
        parent::__construct($cont);
    }

    public function description()
    {
        $desc = "Migrate 1 desc";
        return $desc;
    }

    public function check()
    {
        $this->checkExistMigrate();
        return $this;
    }

    public function run()
    {
        try {
            db()::transaction(function () {
                $this->addUserTokenField();
            });
        } catch (\Exception $err) {
            db()::rollback();
            throw $err->getMessage();
        }
        return $this;
    }

    public function verify()
    {
        return $this;
    }

    public function cleanUp()
    {
        return $this;
    }

    /** Private Methods */
    private function checkExistMigrate()
    {
        //
        $prefix = getConfig("db")["prefix"];
        $table  = $prefix . "users";

        $query = db()::select("SHOW COLUMNS FROM  $table  LIKE 'token' ");
        if (!empty($query)) {
            throw new \Exception("Migrate1 has already been implemented");
        }
        return true;
    }

    private function addUserTokenField()
    {
        $prefix     = getConfig("db")["prefix"];
        $table      = $prefix . "users";
        $sql        = "ALTER TABLE `$table` ADD `token` VARCHAR(20) NOT NULL AFTER `admin_uname`;";
        db()::select($sql);


        //add token
        $query = db()->table("users")->get();
        if ($query->count()) {
            $rows = $query->toArray();

            foreach ($rows as $row) {
                db()->table("users")->where("id", $row->id)->update([
                    "token" => generateUserToken($row->id),
                ]);
                usleep(500);
            }
        }
    }
}
