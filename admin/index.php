<?php
// --- LÓGICA PHP DO SEU LOGIN DE ADMIN ANTIGO ---
session_start();
require '../conexao.php'; // Caminho corrigido para a conexão

$erro_login = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'];
    $senha = $_POST['senha'];

    $stmt = $pdo->prepare("SELECT * FROM administradores WHERE usuario = ?");
    $stmt->execute([$usuario]);
    $admin = $stmt->fetch();

    	if ($admin && password_verify($senha, $admin['senha'])) {
    	$_SESSION['admin_logado'] = true;
    	$_SESSION['admin_usuario'] = $admin['usuario'];
    	$_SESSION['admin_cargo'] = $admin['cargo']; // <-- ADICIONE ESTA LINHA
    	header('Location: dashboard.php');
    	exit;
    } else {
        $erro_login = 'Usuário ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
	<title>Login Administrador - Sistema de Agendamento</title>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- MODIFICADO: ../ adicionado em todos os caminhos -->
	<link rel="icon" type="image/png" href="../images/icons/favicon.ico"/>
	<link rel="stylesheet" type="text/css" href="../vendor/bootstrap/css/bootstrap.min.css">
	<link rel="stylesheet" type="text/css" href="../fonts/font-awesome-4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" type="text/css" href="../vendor/animate/animate.css">
	<link rel="stylesheet" type="text/css" href="../vendor/css-hamburgers/hamburgers.min.css">
	<link rel="stylesheet" type="text/css" href="../vendor/select2/select2.min.css">
	<link rel="stylesheet" type="text/css" href="../css/util.css">
	<link rel="stylesheet" type="text/css" href="../css/main.css">
</head>
<body>
	
	<div class="limiter">
		<div class="container-login100">
			<div class="wrap-login100">
				<div class="login100-pic js-tilt" data-tilt>
                    <!-- MODIFICADO: ../ adicionado no caminho da imagem -->
					<img src="../images/img-01.png" alt="IMG">
				</div>

				<!-- MODIFICADO: action e method adicionados -->
				<form class="login100-form validate-form" method="POST" action="login.php">
					<span class="login100-form-title">
						Login do Administrador
					</span>

					<!-- MODIFICADO: Bloco para exibir mensagem de erro -->
					<?php if (!empty($erro_login)): ?>
						<div class="text-center p-b-12" style="color: #dc3545; font-weight: bold;">
							<?php echo $erro_login; ?>
						</div>
					<?php endif; ?>

					<!-- MODIFICADO: Campo 'usuario' -->
					<div class="wrap-input100 validate-input" data-validate = "Usuário é obrigatório">
						<input class="input100" type="text" name="usuario" placeholder="Usuário Administrador" required>
						<span class="focus-input100"></span>
						<span class="symbol-input100">
							<i class="fa fa-user-secret" aria-hidden="true"></i>
						</span>
					</div>

					<!-- MODIFICADO: Campo 'senha' -->
					<div class="wrap-input100 validate-input" data-validate = "Senha é obrigatória">
						<input class="input100" type="password" name="senha" placeholder="Senha" required>
						<span class="focus-input100"></span>
						<span class="symbol-input100">
							<i class="fa fa-lock" aria-hidden="true"></i>
						</span>
					</div>
					
					<div class="container-login100-form-btn">
						<button type="submit" class="login100-form-btn">
							Entrar
						</button>
					</div>

					<!-- MODIFICADO: Link para o login de usuário -->
					<div class="text-center p-t-136">
						<a class="txt2" href="../login.php">
							Acesso de Usuário
							<i class="fa fa-long-arrow-right m-l-5" aria-hidden="true"></i>
						</a>
					</div>
				</form>
			</div>
		</div>
	</div>
	
    <!-- MODIFICADO: ../ adicionado em todos os caminhos -->
	<script src="../vendor/jquery/jquery-3.2.1.min.js"></script>
	<script src="../vendor/bootstrap/js/popper.js"></script>
	<script src="../vendor/bootstrap/js/bootstrap.min.js"></script>
	<script src="../vendor/select2/select2.min.js"></script>
	<script src="../vendor/tilt/tilt.jquery.min.js"></script>
	<script >
		$('.js-tilt').tilt({
			scale: 1.1
		})
	</script>
	<script src="../js/main.js"></script>

</body>
</html>