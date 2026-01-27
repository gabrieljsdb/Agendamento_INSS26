<?php

/**
 * API de Eventos para o Calendário
 */

$app = require __DIR__ . '/../src/bootstrap.php';

$controller = new \App\Controllers\AgendamentoController($app['pdo']);
$controller->buscarEventos();
