<?php

/**
 * Funções Auxiliares Globais
 * 
 * Funções utilitárias que podem ser usadas em toda a aplicação
 */

if (!function_exists('config')) {
    /**
     * Obtém um valor de configuração
     *
     * @param string $key Chave da configuração (usando notação de ponto)
     * @param mixed $default Valor padrão se não encontrado
     * @return mixed
     */
    function config(string $key, $default = null)
    {
        static $config = null;

        if ($config === null) {
            $config = require __DIR__ . '/../config/config.php';
        }

        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }
}

if (!function_exists('env')) {
    /**
     * Obtém uma variável de ambiente
     *
     * @param string $key Chave da variável
     * @param mixed $default Valor padrão
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        return $_ENV[$key] ?? $default;
    }
}

if (!function_exists('sanitize')) {
    /**
     * Sanitiza uma string para prevenir XSS
     *
     * @param string $value Valor a ser sanitizado
     * @return string
     */
    function sanitize(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    /**
     * Redireciona para uma URL
     *
     * @param string $url URL de destino
     * @param int $statusCode Código de status HTTP
     */
    function redirect(string $url, int $statusCode = 302): void
    {
        header('Location: ' . $url, true, $statusCode);
        exit;
    }
}

if (!function_exists('back')) {
    /**
     * Redireciona para a página anterior
     */
    function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        redirect($referer);
    }
}

if (!function_exists('old')) {
    /**
     * Obtém o valor antigo de um campo (útil após validação falhar)
     *
     * @param string $key Chave do campo
     * @param mixed $default Valor padrão
     * @return mixed
     */
    function old(string $key, $default = '')
    {
        return $_SESSION['_old'][$key] ?? $default;
    }
}

if (!function_exists('flash')) {
    /**
     * Define uma mensagem flash
     *
     * @param string $key Chave da mensagem
     * @param mixed $value Valor da mensagem
     */
    function flash(string $key, $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }
}

if (!function_exists('getFlash')) {
    /**
     * Obtém e remove uma mensagem flash
     *
     * @param string $key Chave da mensagem
     * @param mixed $default Valor padrão
     * @return mixed
     */
    function getFlash(string $key, $default = null)
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}

if (!function_exists('formatDate')) {
    /**
     * Formata uma data para o padrão brasileiro
     *
     * @param string $date Data no formato Y-m-d
     * @return string Data no formato d/m/Y
     */
    function formatDate(string $date): string
    {
        return date('d/m/Y', strtotime($date));
    }
}

if (!function_exists('formatDateTime')) {
    /**
     * Formata uma data e hora para o padrão brasileiro
     *
     * @param string $datetime Data e hora
     * @return string Data e hora formatada
     */
    function formatDateTime(string $datetime): string
    {
        return date('d/m/Y H:i', strtotime($datetime));
    }
}

if (!function_exists('formatTime')) {
    /**
     * Formata um horário
     *
     * @param string $time Horário no formato H:i:s
     * @return string Horário no formato H:i
     */
    function formatTime(string $time): string
    {
        return date('H:i', strtotime($time));
    }
}

if (!function_exists('formatPhone')) {
    /**
     * Formata um telefone brasileiro
     *
     * @param string $phone Telefone
     * @return string Telefone formatado
     */
    function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $phone);
        } elseif (strlen($phone) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $phone);
        }

        return $phone;
    }
}

if (!function_exists('asset')) {
    /**
     * Gera URL para um asset público
     *
     * @param string $path Caminho do asset
     * @return string URL completa
     */
    function asset(string $path): string
    {
        $baseUrl = rtrim(config('app.url'), '/');
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Gera uma URL completa
     *
     * @param string $path Caminho
     * @return string URL completa
     */
    function url(string $path = ''): string
    {
        $baseUrl = rtrim(config('app.url'), '/');
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and Die - exibe variável e para execução (debug)
     *
     * @param mixed ...$vars Variáveis a serem exibidas
     */
    function dd(...$vars): void
    {
        echo '<pre>';
        foreach ($vars as $var) {
            var_dump($var);
        }
        echo '</pre>';
        die();
    }
}

if (!function_exists('logger')) {
    /**
     * Obtém a instância do logger
     *
     * @return \Psr\Log\LoggerInterface
     */
    function logger(): \Psr\Log\LoggerInterface
    {
        static $logger = null;

        if ($logger === null) {
            $logger = new \Monolog\Logger('app');
            $handler = new \Monolog\Handler\StreamHandler(
                config('paths.root') . '/' . config('logging.file'),
                \Monolog\Logger::DEBUG
            );
            $handler->setFormatter(new \Monolog\Formatter\LineFormatter(
                config('logging.format'),
                'Y-m-d H:i:s'
            ));
            $logger->pushHandler($handler);
        }

        return $logger;
    }
}

if (!function_exists('isProduction')) {
    /**
     * Verifica se está em ambiente de produção
     *
     * @return bool
     */
    function isProduction(): bool
    {
        return config('app.env') === 'production';
    }
}

if (!function_exists('isDevelopment')) {
    /**
     * Verifica se está em ambiente de desenvolvimento
     *
     * @return bool
     */
    function isDevelopment(): bool
    {
        return config('app.env') === 'development';
    }
}
