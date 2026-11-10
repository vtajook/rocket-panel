<?php
/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

if (!defined('PATH')) die();

if (getConfig('session', 'enabled')) {
  $container['session'] = function ($c) {
    return new \SlimSession\Helper;
  };
}
