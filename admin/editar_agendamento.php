<?php
// admin/editar_agendamento.php --- VERSÃO FINAL COM POP-UP DE USUÁRIO

session_start();
require '../conexao.php';
require '../funcao_email.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$id_agendamento = $_GET['id'];
$mensagem = '';
$tipo_mensagem = '';
$pagina_atual_menu = basename($_SERVER['PHP_SELF']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_data = $_POST['data_agendamento'];
    $nova_hora = $_POST['hora_inicio'];
    $novo_motivo = $_POST['motivo'];
    $nova_observacao = $_POST['observacao'];
    
    $hora_fim = date('H:i:s', strtotime($nova_hora . ' +30 minutes'));

    $sql_update = "UPDATE agendamentos SET data_agendamento = ?, hora_inicio = ?, hora_fim = ?, motivo = ?, observacao = ? WHERE id = ?";
    
    $stmt_update = $pdo->prepare($sql_update);
    if ($stmt_update->execute([$nova_data, $nova_hora, $hora_fim, $novo_motivo, $nova_observacao, $id_agendamento])) {
        $mensagem = 'Agendamento atualizado com sucesso!';
        $tipo_mensagem = 'success';

        $stmt_info = $pdo->prepare("SELECT u.nome, u.email FROM agendamentos a JOIN usuarios u ON a.id_usuario = u.id WHERE a.id = ?");
        $stmt_info->execute([$id_agendamento]);
        $usuario = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            $data_formatada = date('d/m/Y', strtotime($nova_data));
            $hora_formatada = date('H:i', strtotime($nova_hora));
            $assunto = "Atenção: Seu Agendamento foi Alterado";
            $corpo_html = "<h1>Olá, " . htmlspecialchars($usuario['nome']) . "!</h1><p>Informamos que seu agendamento foi alterado pela administração. Confira os novos detalhes:</p><p><strong>Nova Data:</strong> " . $data_formatada . "</p><p><strong>Novo Horário:</strong> " . $hora_formatada . "</p><p>Por favor, verifique os detalhes completos no painel de agendamentos.</p>";
            enviar_email($usuario['email'], $usuario['nome'], $assunto, $corpo_html);
        }
    } else {
        $mensagem = 'Erro ao atualizar o agendamento.';
        $tipo_mensagem = 'danger';
    }
}

// ATUALIZADO: A consulta agora busca TODOS os dados do usuário para o pop-up
$stmt_select = $pdo->prepare("SELECT a.*, u.nome, u.email, u.cpf_oab, u.oab FROM agendamentos a JOIN usuarios u ON a.id_usuario = u.id WHERE a.id = ?");
$stmt_select->execute([$id_agendamento]);
$agendamento = $stmt_select->fetch(PDO::FETCH_ASSOC);

if (!$agendamento) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Editar Agendamento</title>
    
    <link href="../css/font-face.css" rel="stylesheet" media="all">
    <link href="../vendor/font-awesome-5/css/fontawesome-all.min.css" rel="stylesheet" media="all">
    <link href="../vendor/mdi-font/css/material-design-iconic-font.min.css" rel="stylesheet" media="all">
    <link href="../vendor/bootstrap-4.1/bootstrap.min.css" rel="stylesheet" media="all">
    <link href="../vendor/animsition/animsition.min.css" rel="stylesheet" media="all">
    <link href="../css/theme.css" rel="stylesheet" media="all">
</head>

<body class="animsition">
    <div class="page-wrapper">
        <!-- MENU LATERAL -->
        <aside class="menu-sidebar d-none d-lg-block">
            <!-- ... (seu menu lateral aqui, sem alterações) ... -->
            <div class="logo"><a href="dashboard.php"><img src="../images/icon/logo.png" alt="CoolAdmin" /></a></div><div class="menu-sidebar__content js-scrollbar1"><nav class="navbar-sidebar"><ul class="list-unstyled navbar__list"><li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li><li><a href="atendimentos_hoje.php"><i class="fas fa-clipboard-list"></i>Atendimentos do Dia</a></li><li><a href="bloqueios.php"><i class="fas fa-ban"></i>Gerenciar Bloqueios</a></li><?php if (isset($_SESSION['admin_cargo']) && $_SESSION['admin_cargo'] === 'SuperAdmin'): ?><li><a href="gerenciar_admins.php"><i class="fas fa-users-cog"></i>Gerenciar Admins</a></li><?php endif; ?></ul></nav></div>
        </aside>

        <div class="page-container">
            <!-- HEADER -->
            <header class="header-desktop">
                <!-- ... (seu cabeçalho aqui, sem alterações) ... -->
                <div class="section__content section__content--p30"><div class="container-fluid"><div class="header-wrap"><form class="form-header"></form><div class="header-button"><div class="account-wrap"><div class="account-item clearfix js-item-menu"><div class="content"><a class="js-acc-btn" href="#"><?php echo htmlspecialchars($_SESSION['admin_usuario']); ?></a></div><div class="account-dropdown js-dropdown"><div class="account-dropdown__footer"><a href="logout.php"><i class="zmdi zmdi-power"></i>Sair</a></div></div></div></div></div></div></div></div>
            </header>

            <!-- CONTEÚDO PRINCIPAL -->
            <div class="main-content">
                <div class="section__content section__content--p30">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-lg-12">
                                <?php if ($mensagem): ?>
                                    <div class="alert alert-<?php echo $tipo_mensagem; ?>" role="alert"><?php echo $mensagem; ?></div>
                                <?php endif; ?>
                                
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Editar Agendamento de <?php echo htmlspecialchars($agendamento['nome']); ?></strong>
                                        
                                        <!-- BOTÃO PARA ABRIR O POP-UP -->
                                        <button type="button" class="btn btn-info btn-sm float-right" data-toggle="modal" data-target="#userInfoModal">
                                            <i class="fa fa-user"></i> Ver Dados do Usuário
                                        </button>
                                    </div>
                                    <div class="card-body card-block">
                                        <form method="POST" action="editar_agendamento.php?id=<?php echo $id_agendamento; ?>" class="form-horizontal">
                                            <!-- ... (seu formulário de edição aqui, sem alterações) ... -->
                                            <div class="row form-group"><div class="col col-md-3"><label for="data_agendamento" class="form-control-label">Nova Data</label></div><div class="col-12 col-md-9"><input type="date" name="data_agendamento" class="form-control" value="<?php echo htmlspecialchars($agendamento['data_agendamento']); ?>" required></div></div>
                                            <div class="row form-group"><div class="col col-md-3"><label for="hora_inicio" class="form-control-label">Novo Horário</label></div><div class="col-12 col-md-9"><input type="time" name="hora_inicio" step="1800" class="form-control" value="<?php echo htmlspecialchars($agendamento['hora_inicio']); ?>" required></div></div>
                                            <div class="row form-group"><div class="col col-md-3"><label for="motivo" class="form-control-label">Motivo</label></div><div class="col-12 col-md-9"><select name="motivo" class="form-control" required><option value="Atendimento" <?php if($agendamento['motivo'] == 'Atendimento') echo 'selected'; ?>>Atendimento</option><option value="Problemas com Senha" <?php if($agendamento['motivo'] == 'Problemas com Senha') echo 'selected'; ?>>Problemas com Senha</option><option value="Outros" <?php if($agendamento['motivo'] == 'Outros') echo 'selected'; ?>>Outros</option></select></div></div>
                                            <div class="row form-group"><div class="col col-md-3"><label for="observacao" class="form-control-label">Observação</label></div><div class="col-12 col-md-9"><textarea name="observacao" class="form-control" style="height: 166px; width: 502px;"><?php echo htmlspecialchars($agendamento['observacao']); ?></textarea></div></div>
                                            <div class="card-footer text-center">
                                                <button type="submit" class="au-btn au-btn--radius au-btn--green"><i class="fa fa-save"></i> Salvar Alterações</button>
                                                <a href="dashboard.php" class="au-btn au-btn--radius au-btn--blue"><i class="fa fa-ban"></i> Voltar para o Dashboard</a>
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

    <!-- NOVO: MODAL (POP-UP) COM AS INFORMAÇÕES DO USUÁRIO -->
    <div class="modal fade" id="userInfoModal" tabindex="-1" role="dialog" aria-labelledby="userInfoModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userInfoModalLabel">Informações do Usuário</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p><strong>Nome:</strong> <?php echo htmlspecialchars($agendamento['nome']); ?></p>
                    <p><strong>E-mail:</strong> <?php echo htmlspecialchars($agendamento['email']); ?></p>
                    <p><strong>CPF/OAB (Login):</strong> <?php echo htmlspecialchars($agendamento['cpf_oab']); ?></p>
                    <p><strong>Nº OAB:</strong> <?php echo htmlspecialchars($agendamento['oab']); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../vendor/jquery-3.2.1.min.js"></script>
    <script src="../vendor/bootstrap-4.1/popper.min.js"></script>
    <script src="../vendor/bootstrap-4.1/bootstrap.min.js"></script>
    <script src="../vendor/animsition/animsition.min.js"></script>
    <script src="../js/main.js"></script>
</body>
</html>