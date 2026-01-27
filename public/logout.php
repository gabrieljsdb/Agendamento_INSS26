<?php

/**
 * Rota de Logout
 */

$app = require __DIR__ . '/../src/bootstrap.php';

$controller = new \App\Controllers\AuthController($app['pdo']);
$controller->logout();
