<?php

use Core\Router;
use Apps\Controllers\DbCodeGeneratorController;
use Apps\Controllers\DatabaseController;
use Apps\Controllers\ContactController;
use Apps\Controllers\AboutController;
use Apps\Controllers\HomeController;
use Apps\Controllers\UserController;
use Apps\Controllers\TestController;

$router = new Router();

$router->get('/', [HomeController::class, 'index']);

$router->get('/shit', [HomeController::class, 'shit']);

$router->get('/about', [AboutController::class, 'index']);

$router->get('/test', [TestController::class, 'index']);

$router->get('/contact', [ContactController::class, 'index']);

$router->get('/database', [DatabaseController::class, 'index']);

$router->post('/database/ajax', [DatabaseController::class, 'ajax']);

$router->get('/dbcodegenerator', [DbCodeGeneratorController::class, 'index']);

$router->get('/database/codegen', [DatabaseController::class, 'codegen']);

// Users
$router->get('/users', [UserController::class, 'index']);
$router->get('/users/{id}', [UserController::class, 'find']);

$router->get('/users/create', [UserController::class, 'create']);

$router->post('/users', [UserController::class, 'store']);

$router->get('/users/edit/{id}', [UserController::class, 'edit']);

$router->post('/users/update/{id}', [UserController::class, 'update']);

$router->post('/users/delete/{id}', [UserController::class, 'delete']);

return $router;