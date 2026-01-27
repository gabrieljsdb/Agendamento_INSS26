<?php
// --- TODA A LÓGICA PHP DO SEU LOGIN ANTIGO VEM AQUI ---
session_start();
require 'conexao.php'; 

if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    header('Location: agendar.php');
    exit;
}

$erro_login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_login = $_POST['usuario'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if (empty($usuario_login) || empty($senha)) {
        $erro_login = "Usuário e senha são obrigatórios.";
    } else {
        // Lógica de Autenticação SOAP... (a mesma que você já tem)
        $wsdl = 'https://servicos.oab-sc.org.br/WSAutenticar/WSAutenticar.asmx?WSDL';
        $options = ['cache_wsdl' => WSDL_CACHE_NONE, 'trace' => 1];

        try {
            $client = new SoapClient($wsdl, $options);
            $params = ['Usuario' => $usuario_login, 'Senha'   => $senha];
            $result = $client->Autenticar($params);
            $autenticacaoBemSucedida = false;
            if (isset($result->AutenticarResult) && is_string($result->AutenticarResult)) {
                $xml = simplexml_load_string(html_entity_decode($result->AutenticarResult));
                if ($xml !== false && isset($xml->Status) && trim((string)$xml->Status) === 'OK') {
                    $autenticacaoBemSucedida = true;
                    $cadastro = $xml->Cadastro;
                    $nome = (string)$cadastro->Nome;
                    $email = (string)$cadastro->EMail;
                    $oab = (string)$cadastro->RegistroConselho;
					$telefone = (string)($cadastro->Telefone ?? $cadastro->Celular ?? ''); 

                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE cpf_oab = ?");
                    $stmt->execute([$usuario_login]);
                    $user = $stmt->fetch();

                	if ($user) {
					// ATUALIZADO: Inclui o telefone no UPDATE
					$stmt_update = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, oab = ?, telefone = ? WHERE cpf_oab = ?");
					$stmt_update->execute([$nome, $email, $oab, $telefone, $usuario_login]);
					$id_usuario = $user['id'];
					} else {
					// ATUALIZADO: Inclui o telefone no INSERT
					$stmt_insert = $pdo->prepare("INSERT INTO usuarios (cpf_oab, nome, email, oab, telefone) VALUES (?, ?, ?, ?, ?)");
					$stmt_insert->execute([$usuario_login, $nome, $email, $oab, $telefone]);
					$id_usuario = $pdo->lastInsertId();
					}
                    
                    $_SESSION['autenticado'] = true;
                    $_SESSION['id_usuario'] = $id_usuario;
                    $_SESSION['nome_usuario'] = $nome;
                    $_SESSION['usuario_login'] = $usuario_login;
                    header('Location: agendar.php');
                    exit;
                } else {
                     $statusValue = isset($xml->Status) ? (string)$xml->Status : 'Resposta inválida';
                     $erro_login = "Falha na autenticação: " . htmlspecialchars($statusValue);
                }
            } else {
                 $erro_login = "Resposta inesperada do serviço de autenticação.";
            }
        } catch (SoapFault $e) {
            $erro_login = "Erro técnico ao autenticar. Contate o suporte.";
            error_log("SOAP Fault: (faultcode: {$e->faultcode}, faultstring: {$e->faultstring})");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
	<title>Login - Sistema de Agendamento</title>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="icon" type="image/png" href="images/icons/favicon.ico"/>
	<link rel="stylesheet" type="text/css" href="vendor/bootstrap/css/bootstrap.min.css">
	<link rel="stylesheet" type="text/css" href="fonts/font-awesome-4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" type="text/css" href="vendor/animate/animate.css">
	<link rel="stylesheet" type="text/css" href="vendor/css-hamburgers/hamburgers.min.css">
	<link rel="stylesheet" type="text/css" href="vendor/select2/select2.min.css">
	<link rel="stylesheet" type="text/css" href="css/util.css">
	<link rel="stylesheet" type="text/css" href="css/main.css">
</head>
<body>
	
	<div class="limiter">
		<div class="container-login100">
			<div class="wrap-login100">
				<div class="login100-pic js-tilt" data-tilt>
					<img src="images/img-01.png" alt="IMG">
				</div>

				<!-- MODIFICADO: action e method adicionados -->
				<form class="login100-form validate-form" method="POST" action="login.php">
					<span class="login100-form-title">
						Autenticação de Usuário
					</span>

					<!-- MODIFICADO: Bloco para exibir mensagem de erro -->
					<?php if (!empty($erro_login)): ?>
						<div class="text-center p-b-12" style="color: #dc3545; font-weight: bold;">
							<?php echo $erro_login; ?>
						</div>
					<?php endif; ?>

					<!-- MODIFICADO: Campo 'usuario' (CPF/OAB) -->
					<div class="wrap-input100 validate-input" data-validate = "Usuário é obrigatório">
						<input class="input100" type="text" name="usuario" placeholder="Usuário (Numero de CPF)" required>
						<span class="focus-input100"></span>
						<span class="symbol-input100">
							<i class="fa fa-user" aria-hidden="true"></i>
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

					<!-- MODIFICADO: Link para o login de administrador -->
					<div class="text-center p-t-136">
						<a class="txt2" href="admin/login.php">
							Acesso Administrador
							<i class="fa fa-long-arrow-right m-l-5" aria-hidden="true"></i>
						</a>
					</div>
				</form>
			</div>
		</div>
	</div>
	
	<script src="vendor/jquery/jquery-3.2.1.min.js"></script>
	<script src="vendor/bootstrap/js/popper.js"></script>
	<script src="vendor/bootstrap/js/bootstrap.min.js"></script>
	<script src="vendor/select2/select2.min.js"></script>
	<script src="vendor/tilt/tilt.jquery.min.js"></script>
	<script >
		$('.js-tilt').tilt({
			scale: 1.1
		})
	</script>
	<script src="js/main.js"></script>

</body>
</html>