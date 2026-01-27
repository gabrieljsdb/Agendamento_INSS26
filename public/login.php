<?php

/**
 * Rota de Login
 */

$app = require __DIR__ . '/../src/bootstrap.php';

$controller = new \App\Controllers\AuthController($app['pdo']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->login();
} else {
    $controller->showLogin();
}
