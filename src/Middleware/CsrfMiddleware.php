<?php

namespace App\Middleware;

/**
 * Classe CsrfMiddleware
 * 
 * Middleware para proteção contra ataques CSRF (Cross-Site Request Forgery)
 */
class CsrfMiddleware
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_LENGTH = 32;

    /**
     * Gera um novo token CSRF
     *
     * @return string
     */
    public static function generateToken(): string
    {
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }

        return $_SESSION[self::TOKEN_NAME];
    }

    /**
     * Obtém o token CSRF atual
     *
     * @return string|null
     */
    public static function getToken(): ?string
    {
        return $_SESSION[self::TOKEN_NAME] ?? null;
    }

    /**
     * Valida o token CSRF
     *
     * @param string|null $token Token a ser validado
     * @return bool
     */
    public static function validateToken(?string $token): bool
    {
        if ($token === null || !isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }

        return hash_equals($_SESSION[self::TOKEN_NAME], $token);
    }

    /**
     * Verifica o token CSRF da requisição POST
     *
     * @return bool
     */
    public static function check(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true; // Não valida em requisições que não são POST
        }

        $token = $_POST[self::TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        return self::validateToken($token);
    }

    /**
     * Requer validação de token CSRF (aborta se inválido)
     *
     * @param string $redirectTo URL de redirecionamento em caso de falha
     */
    public static function require(string $redirectTo = '/'): void
    {
        if (!self::check()) {
            http_response_code(403);
            header('Location: ' . $redirectTo . '?error=csrf');
            exit('Token CSRF inválido.');
        }
    }

    /**
     * Gera um campo hidden HTML com o token CSRF
     *
     * @return string
     */
    public static function field(): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Gera uma meta tag HTML com o token CSRF
     *
     * @return string
     */
    public static function metaTag(): string
    {
        $token = self::generateToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
    }

    /**
     * Regenera o token CSRF
     */
    public static function regenerate(): void
    {
        unset($_SESSION[self::TOKEN_NAME]);
        self::generateToken();
    }
}
