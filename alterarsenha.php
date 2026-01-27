<?php
session_start();
require 'conexao.php';

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: login.php');
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$nome_usuario = $_SESSION['nome_usuario'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Alterar Senha</title>
    <link href="css/font-face.css" rel="stylesheet" media="all">
    <link href="vendor/font-awesome-5/css/fontawesome-all.min.css" rel="stylesheet" media="all">
    <link href="vendor/mdi-font/css/material-design-iconic-font.min.css" rel="stylesheet" media="all">
    <link href="vendor/bootstrap-4.1/bootstrap.min.css" rel="stylesheet" media="all">
    <link href="vendor/animsition/animsition.min.css" rel="stylesheet" media="all">
    <link href="css/theme.css" rel="stylesheet" media="all">
    <style>
        /* ============================================= */
        /*         ESTILOS PARA O NOVO CABEÇALHO         */
        /* ============================================= */
        .user-info-header {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-name-header {
            font-weight: bold;
            color: #333;
            margin-right: 10px;
            white-space: nowrap;
        }
        .user-info-header .au-btn--red {
            background-color: #dc3545;
            color: #ffffff;
        }
        .user-info-header .au-btn--blue {
            background-color: #007bff;
            color: #ffffff;
        }
        .user-info-header .au-btn i {
            margin-right: 5px;
        }
    </style>
</head>

<body class="animsition">
    <div class="page-wrapper">
        <aside class="menu-sidebar d-none d-lg-block">
            <div class="logo"> <a href="#"><img src="images/icon/logo.png" alt="Logo" /></a> </div>
            <div class="menu-sidebar__content js-scrollbar1">
                <nav class="navbar-sidebar">
                    <ul class="list-unstyled navbar__list">
                        <li><a href="agendar.php"><i class="fas fa-calendar-alt"></i>Painel de Agenda</a></li>
                        <li><a href="meus_agendamentos.php"><i class="fas fa-calendar-check"></i>Meus Agendamentos</a></li>
                    </ul>
                </nav>
            </div>
        </aside>

        <div class="page-container">
            <header class="header-desktop">
                <div class="section__content section__content--p30">
                    <div class="container-fluid">
                        <div class="header-wrap">
                            <form class="form-header"></form>
                            <!-- CÓDIGO NOVO E CORRIGIDO -->
                            <div class="header-button">
                                <div class="account-wrap">
                                    <div class="account-item clearfix">
                                        <div class="user-info-header">
                                            <span class="user-name-header">Olá, <?php echo htmlspecialchars($nome_usuario); ?></span>
                                            <a href="alterarsenha.php" class="au-btn au-btn--small au-btn--blue">
                                                <i class="zmdi zmdi-lock"></i> Alterar Senha
                                            </a>
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

            <div class="main-content">
                <div class="section__content section__content--p30">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h4>Alteração de Senha</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-warning" role="alert">
                                            <h4 class="alert-heading">Atenção!</h4>
                                            <p>Este sistema utiliza o serviço de autenticação da <strong>OAB-SC</strong>. Portanto, a sua senha não é armazenada aqui e não pode ser alterada por este painel.</p>
                                            <hr>
                                            <p class="mb-0">Para alterar sua senha, você deve acessar o sistema oficial da OAB-SC onde sua conta foi criada.</p>
                                        </div>

                                        <div class="mt-4">
                                             <a href="agendar.php" class="au-btn au-btn--green">Voltar para a Agenda</a>
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

    <script src="vendor/jquery-3.2.1.min.js"></script>
    <script src="vendor/bootstrap-4.1/popper.min.js"></script>
    <script src="vendor/bootstrap-4.1/bootstrap.min.js"></script>
    <script src="vendor/animsition/animsition.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>