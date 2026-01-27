<?php

/**
 * Ponto de Entrada Principal (Dashboard)
 */

$app = require __DIR__ . '/../src/bootstrap.php';

$controller = new \App\Controllers\AgendamentoController($app['pdo']);
$controller->index();
