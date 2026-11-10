<?php
/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

if (!defined('PATH')) die();

$depPath = PATH_BOOTSTRAP . DS . "dependencies";

foreach (glob("$depPath/*.php") as $filename) {
    require_once($filename);
}
