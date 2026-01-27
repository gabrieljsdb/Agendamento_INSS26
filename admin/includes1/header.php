<?php
// Este arquivo assume que a sessão já foi iniciada na página que o incluiu.
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: login.php');
    exit;
}

// Pega o nome da página atual para marcar o menu como "ativo"
$pagina_atual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Painel Administrativo</title>
    
    <!-- Caminhos corrigidos para acessar a partir da pasta /admin -->
    <link href="../css/font-face.css" rel="stylesheet" media="all">
    <link href="../vendor/font-awesome-5/css/fontawesome-all.min.css" rel="stylesheet" media="all">
    <link href="../vendor/mdi-font/css/material-design-iconic-font.min.css" rel="stylesheet" media="all">
    <link href="../vendor/bootstrap-4.1/bootstrap.min.css" rel="stylesheet" media="all">
    <link href="../vendor/animsition/animsition.min.css" rel="stylesheet" media="all">
    <link href="../css/theme.css" rel="stylesheet" media="all">
    
    <style>
        /* Estilos para o cabeçalho do admin */
        .admin-header-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .admin-header-info .user-name {
            font-weight: bold;
            color: #333;
            white-space: nowrap;
        }
        .admin-header-info .au-btn--red {
            background-color: #dc3545;
            color: #ffffff;
        }
    </style>
</head>

<body class="animsition">
    <div class="page-wrapper">
        <!-- MENU LATERAL -->
        <aside class="menu-sidebar d-none d-lg-block">
            <div class="logo">
                <a href="dashboard.php">
                    <img src="../images/icon/logo.png" alt="Cool Admin" />
                </a>
            </div>
            <div class="menu-sidebar__content js-scrollbar1">
                <nav class="navbar-sidebar">
                    <ul class="list-unstyled navbar__list">
                        <li class="<?php echo ($pagina_atual == 'dashboard.php') ? 'active' : ''; ?>">
                            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
                        </li>
                        <li class="<?php echo ($pagina_atual == 'bloqueios.php') ? 'active' : ''; ?>">
                            <a href="bloqueios.php"><i class="fas fa-calendar-times"></i>Gerenciar Bloqueios</a>
                        </li>
                        <li class="<?php echo ($pagina_atual == 'gerenciar_admins.php') ? 'active' : ''; ?>">
                            <a href="gerenciar_admins.php"><i class="fas fa-users-cog"></i>Gerenciar Admins</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <!-- CONTAINER DA PÁGINA -->
        <div class="page-container">
            <!-- CABEÇALHO SUPERIOR -->
            <header class="header-desktop">
                <div class="section__content section__content--p30">
                    <div class="container-fluid">
                        <div class="header-wrap">
                            <form class="form-header"></form> <!-- Mantém o espaçamento -->
                            <div class="header-button">
                                <div class="account-wrap">
                                    <div class="account-item clearfix">
                                        <div class="admin-header-info">
                                            <span class="user-name">Admin: <?php echo htmlspecialchars($_SESSION['admin_usuario']); ?></span>
                                            <a href="logout.php" class="au-btn au-btn--small au-btn--red">
                                                <i class="zmdi zmdi-power"></i> Sair
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- CONTEÚDO PRINCIPAL (início) -->
            <div class="main-content">
                <div class="section__content section__content--p30">
                    <div class="container-fluid">