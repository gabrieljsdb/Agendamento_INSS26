<?php
// FINAL COM LAYOUT HÍBRIDO: CoolAdmin + Formulário Loop Nerd
session_start();
require '../conexao.php';

// ==========================================================
//          NOVO BLOCO DE VERIFICAÇÃO DE PERMISSÃO
// ==========================================================
if (!isset($_SESSION['admin_cargo']) || $_SESSION['admin_cargo'] !== 'SuperAdmin') {
    // Se não tiver o cargo na sessão ou se o cargo não for SuperAdmin,
    // redireciona para o painel principal com uma mensagem de erro.
    header('Location: dashboard.php?acesso=negado');
    exit;
}
// ==========================================================

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: login.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

// Lógica para criar novo administrador (sem alterações)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_usuario'])) {
    $novo_usuario = trim($_POST['novo_usuario']);
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    if (empty($novo_usuario) || empty($senha)) { $mensagem = 'Nome de usuário e senha são obrigatórios.'; $tipo_mensagem = 'erro'; }
    elseif ($senha !== $confirmar_senha) { $mensagem = 'As senhas não coincidem.'; $tipo_mensagem = 'erro'; }
    else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM administradores WHERE usuario = ?");
        $stmt->execute([$novo_usuario]);
        if ($stmt->fetchColumn() > 0) { $mensagem = 'Este nome de usuário já está em uso.'; $tipo_mensagem = 'erro'; }
        else {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt_insert = $pdo->prepare("INSERT INTO administradores (usuario, senha) VALUES (?, ?)");
            if ($stmt_insert->execute([$novo_usuario, $senha_hash])) { $mensagem = 'Administrador criado com sucesso!'; $tipo_mensagem = 'sucesso'; }
        }
    }
}

// Lógica para alterar senha (sem alterações)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_senha'])) {
    $admin_id = $_POST['admin_id'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_nova_senha = $_POST['confirmar_nova_senha'];
    if (empty($nova_senha) || empty($admin_id)) { $mensagem = 'Selecione um usuário e digite a nova senha.'; $tipo_mensagem = 'erro'; }
    elseif ($nova_senha !== $confirmar_nova_senha) { $mensagem = 'As senhas não coincidem.'; $tipo_mensagem = 'erro'; }
    else {
        $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $stmt_update = $pdo->prepare("UPDATE administradores SET senha = ? WHERE id = ?");
        if ($stmt_update->execute([$nova_senha_hash, $admin_id])) { $mensagem = 'Senha alterada com sucesso!'; $tipo_mensagem = 'sucesso'; }
    }
}

$admins = $pdo->query("SELECT id, usuario FROM administradores ORDER BY usuario ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Gerenciar Administradores</title>
    
    <!-- CSS do Painel CoolAdmin (para a estrutura) -->
    <link href="../css/font-face.css" rel="stylesheet" media="all">
    <link href="../vendor/font-awesome-5/css/fontawesome-all.min.css" rel="stylesheet" media="all">
    <link href="../vendor/mdi-font/css/material-design-iconic-font.min.css" rel="stylesheet" media="all">
    <link href="../vendor/bootstrap-4.1/bootstrap.min.css" rel="stylesheet" media="all">
    <link href="../vendor/animsition/animsition.min.css" rel="stylesheet" media="all">
    <link href="../css/theme.css" rel="stylesheet" media="all">

    <!-- CSS do Formulário Loop Nerd (para o conteúdo) -->
    <link rel="stylesheet" type="text/css" href="assets_form/css/reset.css">
    <link rel="stylesheet" type="text/css" href="assets_form/css/style.css">
    <link rel="stylesheet" type="text/css" href="assets_form/css/fonts-icones.css">
    
    <style>
        /* Ajustes para a combinação de estilos */
        .feedback { padding: 10px; margin-bottom: 20px; border-radius: 5px; color: #fff; text-align: center; }
        .feedback.sucesso { background-color: #28a745; }
        .feedback.erro { background-color: #dc3545; }
        /* Remove o fundo branco padrão do card para que o estilo do form apareça */
        .card-body { background-color: transparent; }
    </style>
</head>

<body class="animsition">
    <div class="page-wrapper">
        <!-- MENU LATERAL DO COOLADMIN -->
        <aside class="menu-sidebar d-none d-lg-block">
            <div class="logo">
                <a href="dashboard.php"><img src="../images/icon/logo.png" alt="CoolAdmin" /></a>
            </div>
            <div class="menu-sidebar__content js-scrollbar1">
                <nav class="navbar-sidebar">
                    <ul class="navbar__list">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
                        <li><a href="atendimentos_hoje.php"><i class="fas fa-tachometer-alt"></i>Atendimentos do Dia</a></li>
                        <li><a href="bloqueios.php"><i class="fas fa-ban"></i>Gerenciar Bloqueios</a></li>
                        <li class="active"><a href="gerenciar_admins.php"><i class="fas fa-users-cog"></i>Gerenciar Admins</a></li>
                    </ul>
                </nav>
            </div>
        </aside>

        <div class="page-container">
            <!-- HEADER DO COOLADMIN -->
            <header class="header-desktop">
                <div class="section__content section__content--p30"><div class="container-fluid"><div class="header-wrap"><form class="form-header"></form><div class="header-button"><div class="account-wrap"><div class="account-item clearfix js-item-menu"><div class="content"><a class="js-acc-btn" href="#"><?php echo htmlspecialchars($_SESSION['admin_usuario']); ?></a></div><div class="account-dropdown js-dropdown"><div class="account-dropdown__footer"><a href="logout.php"><i class="zmdi zmdi-power"></i>Sair</a></div></div></div></div></div></div></div></div>
            </header>

            <!-- CONTEÚDO PRINCIPAL -->
            <div class="main-content">
                <div class="section__content section__content--p30">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-lg-12">
                                <?php if ($mensagem): ?>
                                    <div class="feedback <?php echo $tipo_mensagem; ?>"><?php echo $mensagem; ?></div>
                                <?php endif; ?>
                                
                                <div class="card">
                                    <div class="card-body">
                                        <!-- FORMULÁRIO "CRIAR NOVO" COM ESTILO LOOP NERD -->
                                        <div class="container_form">
                                            <h1>Criar Novo Administrador</h1>
                                            <form class="form" action="gerenciar_admins.php" method="post">
                                                <input type="hidden" name="criar_usuario" value="1">
                                                <div class="form_grupo">
                                                    <label for="novo_usuario" class="form_label">Nome de Usuário</label>
                                                    <input type="text" name="novo_usuario" class="form_input" id="novo_usuario" placeholder="Nome de Usuário" required>
                                                </div>
                                                <div class="form_grupo">
                                                    <label for="senha" class="form_label">Senha</label>
                                                    <input type="password" name="senha" class="form_input" id="senha" placeholder="Senha" required>
                                                </div>
                                                <div class="form_grupo">
                                                    <label for="confirmar_senha" class="form_label">Confirmar Senha</label>
                                                    <input type="password" name="confirmar_senha" class="form_input" id="confirmar_senha" placeholder="Confirme a senha" required>
                                                </div>
                                                <div class="submit">
                                                  <button type="submit" name="Submit" class="submit_btn">Criar</button>
                                                </div>
                                            </form>
                                        </div>

                                        <hr style="margin: 40px 0; border: 1px solid #eee;">

                                        <!-- FORMULÁRIO "ALTERAR SENHA" COM ESTILO LOOP NERD -->
                                        <div class="container_form">
                                            <h1>Alterar Senha de um Administrador</h1>
                                            <form class="form" action="gerenciar_admins.php" method="post">
                                                <input type="hidden" name="alterar_senha" value="1">
                                                <div class="form_grupo">
                                                    <label for="admin_id" class="text">Selecione o Administrador</label>
                                                    <select name="admin_id" class="dropdown" required>
                                                        <option selected disabled value="">Selecione um usuário</option>
                                                        <?php foreach ($admins as $admin): ?>
                                                            <option value="<?php echo $admin['id']; ?>" class="form_select_option"><?php echo htmlspecialchars($admin['usuario']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="form_grupo">
                                                    <label for="nova_senha" class="form_label">Nova Senha</label>
                                                    <input type="password" name="nova_senha" class="form_input" id="nova_senha" placeholder="Digite a nova senha" required>
                                                </div>
                                                <div class="form_grupo">
                                                    <label for="confirmar_nova_senha" class="form_label">Confirmar Nova Senha</label>
                                                    <input type="password" name="confirmar_nova_senha" class="form_input" id="confirmar_nova_senha" placeholder="Confirme a nova senha" required>
                                                </div>
                                                <div class="submit">
                                                  <button type="submit" name="Submit" class="submit_btn">Alterar Senha</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts do Painel CoolAdmin -->
    <script src="../vendor/jquery-3.2.1.min.js"></script>
    <script src="../vendor/bootstrap-4.1/popper.min.js"></script>
    <script src="../vendor/bootstrap-4.1/bootstrap.min.js"></script>
    <script src="../vendor/animsition/animsition.min.js"></script>
    <script src="../js/main.js"></script>
</body>
</html>