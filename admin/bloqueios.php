<?php
// FINAL COM BOTÕES CORRIGIDOS: Gerenciamento de Bloqueios
session_start();
require '../conexao.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: login.php');
    exit;
}
// Pega o ID da agenda que o admin está gerenciando no momento.
// O valor '1' (INSS) é usado como padrão por segurança, caso a sessão não esteja definida.
$id_agenda_admin = $_SESSION['admin_id_agenda_selecionada'] ?? 1;

$pagina_atual_menu = basename($_SERVER['PHP_SELF']);
$mensagem = '';
// Lógica para adicionar bloqueio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo_bloqueio'])) {
    $tipo = $_POST['tipo_bloqueio'];
    $motivo = $_POST['motivo_bloqueio'] ?? '';

    if ($tipo === 'data') {
        $data_inicio = $_POST['data_inicio'];
        $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : $data_inicio;
        $sql = "INSERT INTO bloqueios (id_agenda, tipo, data_bloqueio, data_fim_bloqueio, motivo_bloqueio) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_agenda_admin, 'data', $data_inicio, $data_fim, $motivo]);
    } elseif ($tipo === 'horario') {
        $data = $_POST['data_bloqueio'];
        $hora_inicio = $_POST['hora_inicio'];
        $hora_fim = $_POST['hora_fim'];
        $sql = "INSERT INTO bloqueios (id_agenda, tipo, data_bloqueio, hora_inicio_bloqueio, hora_fim_bloqueio, motivo_bloqueio) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_agenda_admin, 'horario', $data, $hora_inicio, $hora_fim, $motivo]);
    }
    $mensagem = "Bloqueio adicionado com sucesso!";
}

// Lógica para remover bloqueio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover_id'])) {
    $id_remover = $_POST['remover_id'];
    $stmt = $pdo->prepare("DELETE FROM bloqueios WHERE id = ? AND id_agenda = ?");
    $stmt->execute([$id_remover, $id_agenda_admin]);
    $mensagem = "Bloqueio removido com sucesso!";
}

$bloqueios = $pdo->query("SELECT * FROM bloqueios WHERE id_agenda = $id_agenda_admin ORDER BY data_bloqueio DESC, hora_inicio_bloqueio ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Gerenciar Bloqueios</title>
    
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
            <div class="logo">
                <a href="dashboard.php"><img src="../images/icon/logo.png" alt="CoolAdmin" /></a>
            </div>
            <div class="menu-sidebar__content js-scrollbar1">
                <nav class="navbar-sidebar">
                    <ul class="navbar__list">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
                        <li><a href="atendimentos_hoje.php"><i class="fas fa-tachometer-alt"></i>Atendimentos do Dia</a></li>
                        <li class="active"><a href="bloqueios.php"><i class="fas fa-ban"></i>Gerenciar Bloqueios</a></li>
                        <?php if (isset($_SESSION['admin_cargo']) && $_SESSION['admin_cargo'] === 'SuperAdmin'): ?>
                            <li class="<?php echo ($pagina_atual_menu == 'gerenciar_admins.php') ? 'active' : ''; ?>">
                                <a href="gerenciar_admins.php"><i class="fas fa-users-cog"></i>Gerenciar Admins</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </aside>

        <div class="page-container">
            <!-- HEADER -->
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
                                    <div class="alert alert-success" role="alert"><?php echo $mensagem; ?></div>
                                <?php endif; ?>
                                <!-- Formulários de Bloqueio -->
                                <div class="card">
                                    <div class="card-header"><strong>Bloquear Período de Dias</strong></div>
                                    <div class="card-body card-block">
                                        <form method="POST" class="form-horizontal">
                                            <input type="hidden" name="tipo_bloqueio" value="data">
                                            <div class="row form-group"><div class="col col-md-3"><label for="data_inicio" class="form-control-label">Data Início</label></div><div class="col-12 col-md-9"><input type="date" name="data_inicio" class="form-control" required></div></div>
                                            <div class="row form-group"><div class="col col-md-3"><label for="data_fim" class="form-control-label">Data Fim (opcional)</label></div><div class="col-12 col-md-9"><input type="date" name="data_fim" class="form-control"></div></div>
                                            <div class="row form-group"><div class="col col-md-3"><label for="motivo_bloqueio" class="form-control-label">Motivo</label></div><div class="col-12 col-md-9"><input type="text" name="motivo_bloqueio" placeholder="Ex: Feriado prolongado" class="form-control" required></div></div>
                                            <div class="card-footer text-center">
                                                <!-- BOTÃO MODIFICADO -->
                                                <button type="submit" class="au-btn au-btn--radius au-btn--green">
                                                    <i class="fas fa-lock"></i> Bloquear Período
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <div class="card">
                                    <div class="card-header"><strong>Bloquear Período de Horários</strong></div>
                                    <div class="card-body card-block">
                                        <form method="POST" class="form-horizontal">
                                            <input type="hidden" name="tipo_bloqueio" value="horario">
                                            <div class="row form-group"><div class="col col-md-3"><label for="data_bloqueio" class="form-control-label">Data</label></div><div class="col-12 col-md-9"><input type="date" name="data_bloqueio" class="form-control" required></div></div>
                                            <div class="row form-group"><div class="col col-md-3"><label for="hora_inicio" class="form-control-label">Hora Início</label></div><div class="col-12 col-md-9"><input type="time" name="hora_inicio" step="1800" class="form-control" required></div></div>
                                            <div class="row form-group"><div class="col col-md-3"><label for="hora_fim" class="form-control-label">Hora Fim</label></div><div class="col-12 col-md-9"><input type="time" name="hora_fim" step="1800" class="form-control" required></div></div>
                                            <div class="row form-group"><div class="col col-md-3"><label for="motivo_bloqueio" class="form-control-label">Motivo</label></div><div class="col-12 col-md-9"><input type="text" name="motivo_bloqueio" placeholder="Ex: Reunião interna" class="form-control"></div></div>
                                            <div class="card-footer text-center">
                                                 <!-- BOTÃO MODIFICADO -->
                                                <button type="submit" class="au-btn au-btn--radius au-btn--blue">
                                                    <i class="fas fa-clock"></i> Bloquear Horários
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <!-- Tabela de Bloqueios Ativos -->
                                <h3 class="title-5 m-b-35">Bloqueios Ativos</h3>
                                <div class="table-responsive table-responsive-data2">
                                    <table class="table table-data2">
                                        <thead><tr><th>Tipo</th><th>Período</th><th>Horário</th><th>Motivo</th><th>Ação</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($bloqueios as $b): ?>
                                            <tr class="tr-shadow">
                                                <td><?php echo ucfirst($b['tipo']); ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($b['data_bloqueio'])); if (!empty($b['data_fim_bloqueio']) && $b['data_fim_bloqueio'] != $b['data_bloqueio']) { echo ' até ' . date('d/m/Y', strtotime($b['data_fim_bloqueio'])); } ?></td>
                                                <td class="desc"><?php if ($b['hora_inicio_bloqueio']) { echo date('H:i', strtotime($b['hora_inicio_bloqueio'])) . ' - ' . date('H:i', strtotime($b['hora_fim_bloqueio'])); } else { echo 'Dia todo'; } ?></td>
                                                <td><?php echo htmlspecialchars($b['motivo_bloqueio']); ?></td>
                                                <td>
                                                    <div class="table-data-feature">
                                                        <form method="POST" onsubmit="return confirm('Deseja remover este bloqueio?');">
                                                            <input type="hidden" name="remover_id" value="<?php echo $b['id']; ?>">
                                                            <button class="item" data-toggle="tooltip" data-placement="top" title="Remover"><i class="zmdi zmdi-delete"></i></button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr class="spacer"></tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../vendor/jquery-3.2.1.min.js"></script>
    <script src="../vendor/bootstrap-4.1/popper.min.js"></script>
    <script src="../vendor/bootstrap-4.1/bootstrap.min.js"></script>
    <script src="../vendor/animsition/animsition.min.js"></script>
    <script src="../js/main.js"></script>
</body>
</html>