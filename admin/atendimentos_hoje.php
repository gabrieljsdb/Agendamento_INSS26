<?php
// admin/atendimentos_hoje.php --- VERSÃO FINAL COM NOVOS STATUS
session_start();
require '../conexao.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: login.php');
    exit;
}

// --- LÓGICA PARA ATUALIZAR O STATUS DO ATENDIMENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_agendamento'])) {
    $id_agendamento = $_POST['id_agendamento'];
    $novo_status = $_POST['novo_status'];

    // ADICIONADO: Valida se o status enviado é um dos permitidos (incluindo os novos)
    if (in_array($novo_status, ['Atendido', 'Não Compareceu', 'Remarcou', 'Aguardando'])) {
        $stmt = $pdo->prepare("UPDATE agendamentos SET status_atendimento = ? WHERE id = ?");
        $stmt->execute([$novo_status, $id_agendamento]);
        
        header('Location: atendimentos_hoje.php?update=success');
        exit;
    }
}

// --- BUSCA OS AGENDAMENTOS CONFIRMADOS PARA O DIA ATUAL ---
$sql = "SELECT a.id, a.hora_inicio, a.status_atendimento, u.nome, u.cpf_oab, a.motivo
        FROM agendamentos a
        JOIN usuarios u ON a.id_usuario = u.id
        WHERE a.data_agendamento = CURDATE() AND a.status = 'Confirmado'
        ORDER BY a.hora_inicio ASC";
$stmt = $pdo->query($sql);
$atendimentos_hoje = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pagina_atual_menu = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Atendimentos do Dia</title>
    
    <link href="../css/font-face.css" rel="stylesheet" media="all">
    <link href="../vendor/font-awesome-5/css/fontawesome-all.min.css" rel="stylesheet" media="all">
    <link href="../vendor/mdi-font/css/material-design-iconic-font.min.css" rel="stylesheet" media="all">
    <link href="../vendor/bootstrap-4.1/bootstrap.min.css" rel="stylesheet" media="all">
    <link href="../vendor/animsition/animsition.min.css" rel="stylesheet" media="all">
    <link href="../css/theme.css" rel="stylesheet" media="all">
    <style>
        .admin-header-info { display: flex; align-items: center; gap: 15px; }
        .admin-header-info .user-name { font-weight: bold; color: #333; white-space: nowrap; }
        .admin-header-info .au-btn--red { background-color: #dc3545; color: #ffffff; }
        
        /* Estilos para as etiquetas de status */
        .status-badge { padding: 5px 10px; color: #fff; border-radius: 3px; font-size: 0.8rem; font-weight: bold; text-align: center; }
        .status-aguardando { background-color: #ffc107; } /* Amarelo */
        .status-atendido { background-color: #28a745; } /* Verde */
        .status-nao-compareceu { background-color: #dc3545; } /* Vermelho */
        .status-remarcou { background-color: #17a2b8; } /* <!-- NOVO --> Azul Ciano */
    </style>
</head>

<body class="animsition">
    <div class="page-wrapper">
        <!-- MENU LATERAL (sem alterações) -->
        <aside class="menu-sidebar d-none d-lg-block">
            <div class="logo"><a href="dashboard.php"><img src="../images/icon/logo.png" alt="CoolAdmin" /></a></div>
            <div class="menu-sidebar__content js-scrollbar1">
                <nav class="navbar-sidebar">
                    <ul class="navbar__list">
                        <li class="<?php echo ($pagina_atual_menu == 'dashboard.php') ? 'active' : ''; ?>"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
                        <li class="<?php echo ($pagina_atual_menu == 'atendimentos_hoje.php') ? 'active' : ''; ?>"><a href="atendimentos_hoje.php"><i class="fas fa-clipboard-list"></i>Atendimentos do Dia</a></li>
                        <li class="<?php echo ($pagina_atual_menu == 'bloqueios.php') ? 'active' : ''; ?>"><a href="bloqueios.php"><i class="fas fa-calendar-times"></i>Gerenciar Bloqueios</a></li>
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
            <!-- CABEÇALHO (sem alterações) -->
            <header class="header-desktop">
                <div class="section__content section__content--p30"><div class="container-fluid"><div class="header-wrap"><form class="form-header"></form><div class="header-button"><div class="account-wrap"><div class="account-item clearfix"><div class="admin-header-info"><span class="user-name">Admin: <?php echo htmlspecialchars($_SESSION['admin_usuario']); ?></span><a href="logout.php" class="au-btn au-btn--small au-btn--red"><i class="zmdi zmdi-power"></i> Sair</a></div></div></div></div></div></div></div>
            </header>

            <!-- CONTEÚDO PRINCIPAL -->
            <div class="main-content">
                <div class="section__content section__content--p30">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-md-12">
                                <h3 class="title-5 m-b-35">Atendimentos do Dia - <?php echo date('d/m/Y'); ?></h3>
                                
                                <?php if (isset($_GET['update']) && $_GET['update'] == 'success'): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        Status do atendimento atualizado com sucesso!
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                    </div>
                                <?php endif; ?>

                                <div class="table-responsive table-responsive-data2">
                                    <table class="table table-data2">
                                        <thead>
                                            <tr><th>Hora</th><th>Nome</th><th>CPF/OAB</th><th>Motivo</th><th>Status</th><th>Ações</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($atendimentos_hoje)): ?>
                                                <tr class="tr-shadow"><td colspan="6" class="text-center p-5">Nenhum agendamento confirmado para hoje.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($atendimentos_hoje as $ag): ?>
                                                <tr class="tr-shadow">
                                                    <td><?php echo date('H:i', strtotime($ag['hora_inicio'])); ?></td>
                                                    <td><?php echo htmlspecialchars($ag['nome']); ?></td>
                                                    <td><?php echo htmlspecialchars($ag['cpf_oab']); ?></td>
                                                    <td><?php echo htmlspecialchars($ag['motivo']); ?></td>
                                                    <td>
                                                        <?php
                                                            // ADICIONADO: Lógica para incluir a nova cor do status 'Remarcou'
                                                            $status_class = 'status-aguardando'; // Padrão
                                                            if ($ag['status_atendimento'] == 'Atendido') $status_class = 'status-atendido';
                                                            elseif ($ag['status_atendimento'] == 'Não Compareceu') $status_class = 'status-nao-compareceu';
                                                            elseif ($ag['status_atendimento'] == 'Remarcou') $status_class = 'status-remarcou';
                                                        ?>
                                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $ag['status_atendimento']; ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="table-data-feature">
                                                            <!-- Botão Atendido -->
                                                            <form method="POST" class="d-inline-block"><input type="hidden" name="id_agendamento" value="<?php echo $ag['id']; ?>"><input type="hidden" name="novo_status" value="Atendido"><button type="submit" class="item" data-toggle="tooltip" data-placement="top" title="Marcar como Atendido"><i class="zmdi zmdi-check-circle" style="color: green;"></i></button></form>
                                                            
                                                            <!-- Botão Não Compareceu -->
                                                            <form method="POST" class="d-inline-block"><input type="hidden" name="id_agendamento" value="<?php echo $ag['id']; ?>"><input type="hidden" name="novo_status" value="Não Compareceu"><button type="submit" class="item" data-toggle="tooltip" data-placement="top" title="Marcar como Não Compareceu"><i class="zmdi zmdi-close-circle" style="color: red;"></i></button></form>
                                                            
                                                            <!-- <!-- NOVO --> Botão Remarcou -->
                                                            <form method="POST" class="d-inline-block"><input type="hidden" name="id_agendamento" value="<?php echo $ag['id']; ?>"><input type="hidden" name="novo_status" value="Remarcou"><button type="submit" class="item" data-toggle="tooltip" data-placement="top" title="Marcar como Remarcou"><i class="zmdi zmdi-time-restore" style="color: #17a2b8;"></i></button></form>
                                                            
                                                            <!-- <!-- NOVO --> Botão para resetar para Aguardando -->
                                                            <form method="POST" class="d-inline-block"><input type="hidden" name="id_agendamento" value="<?php echo $ag['id']; ?>"><input type="hidden" name="novo_status" value="Aguardando"><button type="submit" class="item" data-toggle="tooltip" data-placement="top" title="Marcar como Aguardando"><i class="zmdi zmdi-hourglass-alt" style="color: #ffc107;"></i></button></form>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <tr class="spacer"></tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
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