<?php
$configs = [];

$configs["panelUrl"] = "http://" . $_SERVER['HTTP_HOST'];

$root = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] .  str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
$configs['url'] = $root;
