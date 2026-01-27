<?php

namespace App\Middleware;

/**
 * Classe AuthMiddleware
 * 
 * Middleware para verificar autenticação do usuário
 */
class AuthMiddleware
{
    /**
     * Verifica se o usuário está autenticado
     *
     * @return bool
     */
    public static function check(): bool
    {
        return isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true;
    }

    /**
     * Requer autenticação (redireciona se não autenticado)
     *
     * @param string $redirectTo URL de redirecionamento
     */
    public static function require(string $redirectTo = '/login.php'): void
    {
        if (!self::check()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    /**
     * Obtém o ID do usuário autenticado
     *
     * @return int|null
     */
    public static function getUserId(): ?int
    {
        return $_SESSION['id_usuario'] ?? null;
    }

    /**
     * Obtém o nome do usuário autenticado
     *
     * @return string|null
     */
    public static function getUserName(): ?string
    {
        return $_SESSION['nome_usuario'] ?? null;
    }

    /**
     * Obtém o login do usuário autenticado
     *
     * @return string|null
     */
    public static function getUserLogin(): ?string
    {
        return $_SESSION['usuario_login'] ?? null;
    }

    /**
     * Verifica se o usuário é administrador
     *
     * @return bool
     */
    public static function isAdmin(): bool
    {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }

    /**
     * Requer permissão de administrador
     *
     * @param string $redirectTo URL de redirecionamento
     */
    public static function requireAdmin(string $redirectTo = '/index.php'): void
    {
        if (!self::isAdmin()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    /**
     * Faz login do usuário
     *
     * @param array $userData Dados do usuário
     */
    public static function login(array $userData): void
    {
        $_SESSION['autenticado'] = true;
        $_SESSION['id_usuario'] = $userData['id'];
        $_SESSION['nome_usuario'] = $userData['nome'];
        $_SESSION['usuario_login'] = $userData['cpf_oab'];
        $_SESSION['is_admin'] = $userData['is_admin'] ?? false;
        
        // Regenera o ID da sessão para prevenir session fixation
        session_regenerate_id(true);
    }

    /**
     * Faz logout do usuário
     */
    public static function logout(): void
    {
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        session_destroy();
    }
}
