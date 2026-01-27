<?php

/**
 * Arquivo de Configuração Central
 * 
 * Este arquivo carrega as variáveis de ambiente e define as configurações
 * da aplicação de forma centralizada e segura.
 */

// Carrega as variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

return [
    
    // Configurações da Aplicação
    'app' => [
        'name' => 'Sistema de Agendamento INSS - OAB/SC',
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'url' => $_ENV['APP_URL'] ?? 'http://localhost',
        'timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo',
    ],
    
    // Configurações do Banco de Dados
    'database' => [
        'driver' => 'mysql',
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => (int)($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_DATABASE'] ?? 'sistema_agendamento',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],
    
    // Configurações de Email
    'mail' => [
        'mailer' => $_ENV['MAIL_MAILER'] ?? 'smtp',
        'host' => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
        'port' => (int)($_ENV['MAIL_PORT'] ?? 587),
        'username' => $_ENV['MAIL_USERNAME'] ?? '',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from' => [
            'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@oab-sc.org.br',
            'name' => $_ENV['MAIL_FROM_NAME'] ?? 'OAB/SC - Sistema de Agendamento',
        ],
        'admin_emails' => array_map('trim', explode(',', $_ENV['ADMIN_EMAILS'] ?? '')),
        'timeout' => 30,
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 2 : 0,
    ],
    
    // Configurações de Sessão
    'session' => [
        'name' => 'OABSC_SESSION',
        'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 120),
        'path' => '/',
        'domain' => '',
        'secure' => filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'httponly' => filter_var($_ENV['SESSION_HTTP_ONLY'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'samesite' => $_ENV['SESSION_SAME_SITE'] ?? 'Lax',
    ],
    
    // Configurações de Segurança
    'security' => [
        'csrf_token_name' => $_ENV['CSRF_TOKEN_NAME'] ?? 'csrf_token',
        'hash_algo' => $_ENV['HASH_ALGO'] ?? 'bcrypt',
        'password_min_length' => 8,
    ],
    
    // Configurações de Agendamento
    'agendamento' => [
        'agenda_id_padrao' => (int)($_ENV['AGENDA_ID_PADRAO'] ?? 1),
        'limite_mensal' => (int)($_ENV['AGENDAMENTO_LIMITE_MENSAL'] ?? 2),
        'bloqueio_cancelamento_horas' => (int)($_ENV['AGENDAMENTO_BLOQUEIO_CANCELAMENTO_HORAS'] ?? 2),
        'horario_inicio' => $_ENV['AGENDAMENTO_HORARIO_INICIO'] ?? '08:00:00',
        'horario_fim' => $_ENV['AGENDAMENTO_HORARIO_FIM'] ?? '12:00:00',
        'duracao_minutos' => (int)($_ENV['AGENDAMENTO_DURACAO_MINUTOS'] ?? 30),
        'dias_antecedencia_maxima' => (int)($_ENV['AGENDAMENTO_DIAS_ANTECEDENCIA_MAXIMA'] ?? 30),
        'bloqueio_horario_limite' => $_ENV['AGENDAMENTO_BLOQUEIO_HORARIO_LIMITE'] ?? '19:00:00',
        'endereco_atendimento' => [
            'nome' => 'Seccional do INSS',
            'rua' => 'Rua Paschoal Apóstolo Pítsica, 4860',
            'bairro' => 'Agronômica',
            'cidade' => 'Florianópolis',
            'estado' => 'SC',
        ],
    ],
    
    // Configurações de Log
    'logging' => [
        'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
        'file' => $_ENV['LOG_FILE'] ?? 'logs/app.log',
        'max_files' => (int)($_ENV['LOG_MAX_FILES'] ?? 30),
        'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
    ],
    
    // Configurações de SOAP (Autenticação OAB)
    'soap' => [
        'wsdl_url' => $_ENV['SOAP_WSDL_URL'] ?? 'https://servicos.oab-sc.org.br/WSAutenticar/WSAutenticar.asmx?WSDL',
        'cache_wsdl' => (int)($_ENV['SOAP_CACHE_WSDL'] ?? WSDL_CACHE_NONE),
        'trace' => filter_var($_ENV['SOAP_TRACE'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'exceptions' => true,
        'connection_timeout' => 30,
    ],
    
    // Configurações de Upload
    'upload' => [
        'max_size' => (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 5242880), // 5MB
        'allowed_extensions' => array_map('trim', explode(',', $_ENV['UPLOAD_ALLOWED_EXTENSIONS'] ?? 'jpg,jpeg,png,pdf')),
        'storage_path' => __DIR__ . '/../storage/uploads',
    ],
    
    // Caminhos da Aplicação
    'paths' => [
        'root' => dirname(__DIR__),
        'public' => dirname(__DIR__) . '/public',
        'storage' => dirname(__DIR__) . '/storage',
        'logs' => dirname(__DIR__) . '/logs',
        'templates' => dirname(__DIR__) . '/templates',
        'database' => dirname(__DIR__) . '/database',
    ],
    
];
