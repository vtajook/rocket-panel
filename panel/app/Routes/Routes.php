<?php

/**
 * By RocketAp
 * Github: https://github.com/rocket-ap
 */

if (!defined('PATH')) die();

loadHelpers("main");

$app->add(new \App\Middlewares\OptionsMethodCheck);

$uController = new \App\Controllers\Users($container);

$container['notFoundHandler']   = function ($container) use ($uController) {
	return function ($request, $response) use ($uController) {
		return $uController->notFoundPage($request, $response);
	};
};

$container['notAllowedHandler']   = function ($container) use ($uController) {
	return function ($request, $response) use ($uController) {
		return $uController->notFoundPage($request, $response);
	};
};

$container['errorHandler'] = function ($container) {
	return new \App\Handlers\Error($container['logger']);
};

$container['phpErrorHandler'] = function ($container) {
	return new \App\Handlers\PhpError($container['logger']);
};



require_once "admin.php";
require_once "api.php";
require_once "cron.php";
require_once "userAccount.php";
require_once "telegram.php";


$migrationRoutesPath = PATH_APP . DS . "Migration" . DS . "Routes.php";
if (file_exists($migrationRoutesPath)) {
	require_once PATH_APP . DS . "Migration" . DS . "Routes.php";
}
