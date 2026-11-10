<?php

namespace App\Migration\Migrator;

class Lib
{

    public $cont;
    public $currentVersion;
    public $newVersion;
    public $miogrator;

    function __construct($cont)
    {
        $this->cont = $cont;
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    }

    public function run()
    {
        $classesArr = glob(PATH_APP . DS . "Migration" . DS . "Migrator" . DS . "Clasess/Mig*.php");
        if (!empty($classesArr)) {
            $resultArray = [];
            foreach ($classesArr as $className) {
                $pathinfo = pathinfo($className);
                $filename = $pathinfo["filename"];
                $class = __NAMESPACE__ . "\Clasess\\" . $filename;
                $newClass = new $class($this->cont);
                $resultArray[$filename][] = "=> run";
                $resultArray[$filename][] = "=> " . $newClass->description();
                try {
                    $newClass->check()
                        ->run()
                        ->verify()
                        ->cleanUp();
                    $resultArray[$filename][] = "=> Success";
                } catch (\Exception $e) {
                    $resultArray[$filename][] = "=> Error: " . $e->getMessage();
                }
            };
            foreach ($resultArray as $key => $value) {

                echo "<h4>$key</h4>";
                if (is_array($value)) {
                    echo implode("<br/>", $value);
                } else {
                    echo $value . "<br/>";
                }
                echo "<hr/>";
            }
        }
    }
}
