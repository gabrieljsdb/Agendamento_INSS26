<!DOCTYPE html>
<html lang="pt-br">
<head>
    <title>Login - <?php echo config('app.name'); ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { background: #f8f9fa; display: flex; align-items: center; height: 100vh; }
        .login-container { max-width: 400px; margin: auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .logo { text-align: center; margin-bottom: 20px; }
        .logo img { max-width: 150px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo">
                <img src="images/logo1.png" alt="Logo OAB/SC" onerror="this.src='https://via.placeholder.com/150x50?text=OAB/SC'">
            </div>
            <h4 class="text-center mb-4">Autenticação</h4>
            
            <?php if ($error = getFlash('error')): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <?php echo \App\Middleware\CsrfMiddleware::field(); ?>
                <div class="mb-3">
                    <label class="form-label">CPF / Usuário</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-user"></i></span>
                        <input type="text" name="usuario" class="form-control" placeholder="Digite seu CPF" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Senha</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-lock"></i></span>
                        <input type="password" name="senha" class="form-control" placeholder="Digite sua senha" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Entrar</button>
            </form>
            
            <div class="text-center mt-4">
                <small class="text-muted">Acesso restrito a advogados inscritos.</small>
            </div>
        </div>
    </div>
</body>
</html>
