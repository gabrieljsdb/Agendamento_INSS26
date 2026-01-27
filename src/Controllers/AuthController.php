<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Models\Usuario;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

class AuthController
{
    private AuthService $authService;
    private Usuario $usuarioModel;

    public function __construct($pdo)
    {
        $this->authService = new AuthService(config('soap'), logger());
        $this->usuarioModel = new Usuario($pdo);
    }

    public function showLogin()
    {
        if (AuthMiddleware::check()) {
            redirect('index.php');
        }
        
        require config('paths.templates') . '/auth/login.php';
    }

    public function login()
    {
        CsrfMiddleware::require('login.php');

        $usuario = $_POST['usuario'] ?? '';
        $senha = $_POST['senha'] ?? '';

        if (empty($usuario) || empty($senha)) {
            flash('error', 'Usuário e senha são obrigatórios.');
            redirect('login.php');
        }

        $userData = $this->authService->authenticate($usuario, $senha);

        if ($userData) {
            $id = $this->usuarioModel->criarOuAtualizar($userData);
            $userData['id'] = $id;
            
            AuthMiddleware::login($userData);
            redirect('index.php');
        } else {
            flash('error', 'Falha na autenticação. Verifique seus dados.');
            redirect('login.php');
        }
    }

    public function logout()
    {
        AuthMiddleware::logout();
        redirect('login.php');
    }
}
