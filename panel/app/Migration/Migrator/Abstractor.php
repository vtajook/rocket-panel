<?php

namespace App\Migration\Migrator;

abstract class Abstractor
{
    protected $cont;
    protected $dbConfig;

    function __construct($cont)
    {
        $this->cont = $cont;
        $this->dbConfig = $this->cont->settings["db"];
    }

    public abstract function check();
    public abstract function run();
    public abstract function verify();
    public abstract function cleanUp();
    public abstract function description();
}
