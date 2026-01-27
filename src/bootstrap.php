<?php

/**
 * Bootstrap da Aplicação
 * 
 * Este arquivo inicializa a aplicação, carregando dependências,
 * configurações e serviços necessários.
 */

// Define o caminho raiz da aplicação
define('ROOT_PATH', dirname(__DIR__));

// Carrega o autoloader do Composer
require ROOT_PATH . '/vendor/autoload.php';

// Carrega as variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
$dotenv->load();

// Carrega as funções auxiliares
require ROOT_PATH . '/src/helpers.php';

// Configura o timezone
date_default_timezone_set(config('app.timezone'));

// Configura o tratamento de erros
if (config('app.debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', config('paths.logs') . '/php_errors.log');
}

// Configura a sessão de forma segura
$sessionConfig = config('session');

ini_set('session.cookie_httponly', $sessionConfig['httponly'] ? '1' : '0');
ini_set('session.cookie_secure', $sessionConfig['secure'] ? '1' : '0');
ini_set('session.cookie_samesite', $sessionConfig['samesite']);
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_lifetime', (string)($sessionConfig['lifetime'] * 60));

session_name($sessionConfig['name']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpa mensagens flash antigas
if (isset($_SESSION['_flash_old'])) {
    unset($_SESSION['_flash_old']);
}
if (isset($_SESSION['_flash'])) {
    $_SESSION['_flash_old'] = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
}

// Limpa valores antigos de formulário
if (isset($_SESSION['_old'])) {
    unset($_SESSION['_old']);
}

// Registra um handler de exceções não capturadas
set_exception_handler(function (Throwable $e) {
    logger()->error('Exceção não capturada', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    if (config('app.debug')) {
        echo '<h1>Erro</h1>';
        echo '<p><strong>Mensagem:</strong> ' . $e->getMessage() . '</p>';
        echo '<p><strong>Arquivo:</strong> ' . $e->getFile() . ':' . $e->getLine() . '</p>';
        echo '<pre>' . $e->getTraceAsString() . '</pre>';
    } else {
        http_response_code(500);
        echo '<h1>Erro Interno do Servidor</h1>';
        echo '<p>Ocorreu um erro inesperado. Por favor, tente novamente mais tarde.</p>';
    }
    exit(1);
});

// Registra um handler de erros fatais
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        logger()->critical('Erro fatal', $error);

        if (!config('app.debug')) {
            http_response_code(500);
            echo '<h1>Erro Interno do Servidor</h1>';
            echo '<p>Ocorreu um erro crítico. Por favor, tente novamente mais tarde.</p>';
        }
    }
});

// Retorna as instâncias dos serviços principais
return [
    'pdo' => \App\Services\Database::getInstance(config('database')),
    'logger' => logger(),
];
