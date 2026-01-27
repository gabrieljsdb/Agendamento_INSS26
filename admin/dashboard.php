<?php
session_start();
require '../conexao.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: login.php');
    exit;
}

// ==========================================================
//          INÍCIO DA LÓGICA DE SELEÇÃO DE AGENDA
// ==========================================================
$agendas_disponiveis = $pdo->query("SELECT id, nome FROM agendas WHERE ativa = 1 ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_agenda_selecionada'])) {
    $_SESSION['admin_id_agenda_selecionada'] = (int)$_POST['id_agenda_selecionada'];
    header('Location: dashboard.php');
    exit;
}

$id_agenda_selecionada = $_SESSION['admin_id_agenda_selecionada'] ?? ($agendas_disponiveis[0]['id'] ?? 1);
// ==========================================================
//          FIM DA LÓGICA DE SELEÇÃO DE AGENDA
// ==========================================================

// --- Lógica de Filtros e Paginação ---
$registros_por_pagina = 25;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$status_filtro = $_GET['status'] ?? 'Todos';

$where_clauses = [];
$params = [];

// ADICIONA O FILTRO DA AGENDA SELECIONADA À CONSULTA
$where_clauses[] = "a.id_agenda = :id_agenda";
$params[':id_agenda'] = $id_agenda_selecionada;

if ($data_inicio && $data_fim) {
    $where_clauses[] = "a.data_agendamento BETWEEN :data_inicio AND :data_fim";
    $params[':data_inicio'] = $data_inicio;
    $params[':data_fim'] = $data_fim;
}
if ($status_filtro && $status_filtro !== 'Todos') {
    $where_clauses[] = "a.status = :status";
    $params[':status'] = $status_filtro;
}
$where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";

$sql_total = "SELECT COUNT(a.id) FROM agendamentos a" . $where_sql;
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute($params);
$total_registros = $stmt_total->fetchColumn();
$total_paginas = ceil($total_registros / $registros_por_pagina);

$sql_pagina = "SELECT a.id, a.data_agendamento, a.hora_inicio, a.status, a.motivo, u.nome, u.email, u.oab, u.cpf_oab 
               FROM agendamentos a JOIN usuarios u ON a.id_usuario = u.id"
               . $where_sql .
               " ORDER BY a.data_agendamento DESC, a.hora_inicio DESC
               LIMIT :limit OFFSET :offset";
$stmt_pagina = $pdo->prepare($sql_pagina);
foreach ($params as $key => $value) { $stmt_pagina->bindValue($key, $value); }
$stmt_pagina->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt_pagina->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_pagina->execute();
$todos_agendamentos = $stmt_pagina->fetchAll(PDO::FETCH_ASSOC);
$pagina_atual_menu = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Dashboard do Administrador</title>
    
    <link href="../css/font-face.css" rel="stylesheet" media="all">
    <link href="../vendor/font-awesome-5/css/fontawesome-all.min.css" rel="stylesheet" media="all">
    <link href="../vendor/mdi-font/css/material-design-iconic-font.min.css" rel="stylesheet" media="all">
    <link href="../vendor/bootstrap-4.1/bootstrap.min.css" rel="stylesheet" media="all">
    <link href="../vendor/animsition/animsition.min.css" rel="stylesheet" media="all">
    <link href="../css/theme.css" rel="stylesheet" media="all">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css' rel='stylesheet' />
    
    <style>
        .admin-header-info { display: flex; align-items: center; gap: 15px; }
        .admin-header-info .user-name { font-weight: bold; color: #333; white-space: nowrap; }
        .pagination-container .pagination .page-item .page-link { color: #555; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 4px; margin: 0 3px; padding: 6px 12px; }
        .form-filters { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        #custom-popup-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); z-index: 1050; }
        #custom-popup-box { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: #fff; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); width: 90%; max-width: 600px; z-index: 1051; }
        #popup-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #eee; }
        #popup-title { margin: 0; font-size: 1.25rem; color: #333; }
        #popup-close-btn { background: none; border: none; font-size: 2rem; cursor: pointer; color: #666; }
        #popup-body { padding: 20px; max-height: 60vh; overflow-y: auto; }
        #popup-body p { margin: 0 0 10px 0; color: #555; } #popup-body hr { margin: 15px 0; }
        #popup-footer { padding: 15px 20px; text-align: right; border-top: 1px solid #eee; display: flex; gap: 10px; justify-content: flex-end; }
        #notification-popup-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.75); z-index: 1060; }
        #notification-popup-box { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: #fff; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); width: 90%; max-width: 500px; z-index: 1061; }
        #notification-popup-box #popup-body { max-height: none; }
    </style>
</head>

<body class="animsition">
    <div class="page-wrapper">
        <aside class="menu-sidebar d-none d-lg-block"><div class="logo"><a href="dashboard.php"><img src="../images/icon/logo.png" alt="CoolAdmin" /></a></div><div class="menu-sidebar__content js-scrollbar1"><nav class="navbar-sidebar"><ul class="navbar__list"><li class="<?php echo ($pagina_atual_menu == 'dashboard.php') ? 'active' : ''; ?>"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li><li class="<?php echo ($pagina_atual_menu == 'atendimentos_hoje.php') ? 'active' : ''; ?>"><a href="atendimentos_hoje.php"><i class="fas fa-clipboard-list"></i>Atendimentos do Dia</a></li><li class="<?php echo ($pagina_atual_menu == 'bloqueios.php') ? 'active' : ''; ?>"><a href="bloqueios.php"><i class="fas fa-calendar-times"></i>Gerenciar Bloqueios</a></li><?php if (isset($_SESSION['admin_cargo']) && $_SESSION['admin_cargo'] === 'SuperAdmin'): ?><li class="<?php echo ($pagina_atual_menu == 'gerenciar_admins.php') ? 'active' : ''; ?>"><a href="gerenciar_admins.php"><i class="fas fa-users-cog"></i>Gerenciar Admins</a></li><?php endif; ?></ul></nav></div></aside>
        
        <div class="page-container">
            <header class="header-desktop"><div class="section__content section__content--p30"><div class="container-fluid"><div class="header-wrap"><form class="form-header"></form><div class="header-button"><div class="account-wrap"><div class="account-item clearfix"><div class="admin-header-info"><span class="user-name">Admin: <?php echo htmlspecialchars($_SESSION['admin_usuario']); ?></span><a href="logout.php" class="au-btn au-btn--small au-btn--red"><i class="zmdi zmdi-power"></i> Sair</a></div></div></div></div></div></div></div></header>
            
            <div class="main-content">
                <div class="section__content section__content--p30">
                    <div class="container-fluid">

                        <!-- ================================================== -->
                        <!--          SELETOR DE AGENDAS (NOVO BLOCO)           -->
                        <!-- ================================================== -->
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header"><strong>Gerenciar Agenda</strong></div>
                                    <div class="card-body">
                                        <form action="dashboard.php" method="POST" class="form-inline">
                                            <div class="form-group mb-0">
                                                <label for="id_agenda_selecionada" class="mr-3">Visualizando a agenda:</label>
                                                <select name="id_agenda_selecionada" id="id_agenda_selecionada" class="form-control" onchange="this.form.submit()">
                                                    <?php if (empty($agendas_disponiveis)): ?>
                                                        <option>Nenhuma agenda encontrada</option>
                                                    <?php else: ?>
                                                        <?php foreach ($agendas_disponiveis as $agenda): ?>
                                                            <option value="<?php echo $agenda['id']; ?>" <?php echo ($agenda['id'] == $id_agenda_selecionada) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($agenda['nome']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <noscript><button type="submit" class="btn btn-primary ml-2">Trocar Agenda</button></noscript>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div id="admin-calendar"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h3 class="title-5 m-b-35">Monitoramento de Agendamentos</h3>
                        <div class="table-data__tool">
                            <div class="table-data__tool-left">
                                <form method="GET" action="dashboard.php" class="form-filters">
                                    <div class="filter-group"><label for="data_inicio">De:</label><input type="date" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>" class="form-control"></div>
                                    <div class="filter-group"><label for="data_fim">Até:</label><input type="date" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>" class="form-control"></div>
                                    <div class="filter-group"><label for="status">Status:</label><select name="status" id="status" class="form-control"><option value="Todos" <?php if ($status_filtro === 'Todos') echo 'selected'; ?>>Todos</option><option value="Confirmado" <?php if ($status_filtro === 'Confirmado') echo 'selected'; ?>>Confirmado</option><option value="Cancelado" <?php if ($status_filtro === 'Cancelado') echo 'selected'; ?>>Cancelado</option></select></div>
                                    <button class="au-btn-filter" type="submit"><i class="zmdi zmdi-filter-list"></i> Filtrar</button>
                                </form>
                            </div>
                            <div class="table-data__tool-right">
                                <button class="au-btn au-btn-icon au-btn--green au-btn--small" type="submit" form="form-export"><i class="zmdi zmdi-download"></i> Exportar CSV</button>
                                <form id="form-export" method="GET" action="exportar.php" target="_blank" class="d-none"><input type="hidden" name="data_inicio" value="<?php echo htmlspecialchars($data_inicio); ?>"><input type="hidden" name="data_fim" value="<?php echo htmlspecialchars($data_fim); ?>"><input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filtro); ?>"></form>
                            </div>
                        </div>
                        <div class="table-responsive table-responsive-data2">
                            <table class="table table-data2">
                                <thead><tr><th>Data/Hora</th><th>Nome</th><th>CPF</th><th>OAB</th><th>Email</th><th>Motivo</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php if (empty($todos_agendamentos)): ?>
                                        <tr><td colspan="7" class="text-center p-5">Nenhum agendamento encontrado para a agenda selecionada.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($todos_agendamentos as $ag): ?>
                                            <tr class="tr-shadow"><td><?php echo date('d/m/Y H:i', strtotime($ag['data_agendamento'] . ' ' . $ag['hora_inicio'])); ?></td><td><?php echo htmlspecialchars($ag['nome']); ?></td><td><?php echo htmlspecialchars($ag['cpf_oab']); ?></td><td><?php echo htmlspecialchars($ag['oab']); ?></td><td><?php echo htmlspecialchars($ag['email']); ?></td><td><?php echo htmlspecialchars($ag['motivo']); ?></td><td><span class="<?php echo $ag['status'] === 'Confirmado' ? 'status--process' : 'status--denied'; ?>"><?php echo $ag['status']; ?></span></td></tr>
                                            <tr class="spacer"></tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($total_paginas > 1): ?>
                            <div class="mt-4 d-flex justify-content-center pagination-container"><nav><ul class="pagination"><?php $query_string = http_build_query(['data_inicio' => $data_inicio, 'data_fim' => $data_fim, 'status' => $status_filtro]); $adjacentes = 2; if ($pagina_atual > 1) { echo "<li class='page-item'><a class='page-link' href='?pagina=1&{$query_string}'>Primeira</a></li>"; } if ($pagina_atual > 1) { echo "<li class='page-item'><a class='page-link' href='?pagina=".($pagina_atual - 1)."&{$query_string}'>Anterior</a></li>"; } $inicio = max(1, $pagina_atual - $adjacentes); $fim = min($total_paginas, $pagina_atual + $adjacentes); for ($i = $inicio; $i <= $fim; $i++) { echo "<li class='page-item ".($i == $pagina_atual ? 'active' : '')."'><a class='page-link' href='?pagina={$i}&{$query_string}'>{$i}</a></li>"; } if ($pagina_atual < $total_paginas) { echo "<li class='page-item'><a class='page-link' href='?pagina=".($pagina_atual + 1)."&{$query_string}'>Próxima</a></li>"; } if ($pagina_atual < $total_paginas) { echo "<li class='page-item'><a class='page-link' href='?pagina={$total_paginas}&{$query_string}'>Última</a></li>"; } ?></ul></nav></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div id="custom-popup-overlay"></div>
    <div id="custom-popup-box">
        <div id="popup-header"><h5 id="popup-title">Detalhes do Agendamento</h5><button id="popup-close-btn">&times;</button></div>
        <div id="popup-body"></div>
        <div id="popup-footer"><button id="popup-notify-btn" class="btn btn-info"><i class="zmdi zmdi-email"></i> Notificar</button><a href="#" id="popup-edit-link" class="btn btn-primary"><i class="zmdi zmdi-edit"></i> Editar Agendamento</a></div>
    </div>

    <div id="notification-popup-overlay"></div>
    <div id="notification-popup-box">
        <div id="popup-header"><h5 id="notification-popup-title">Enviar Notificação</h5><button id="notification-popup-close-btn">&times;</button></div>
        <div id="popup-body"><p>Escreva a mensagem que será enviada por e-mail para o usuário:</p><textarea id="notification-message" class="form-control" rows="5" placeholder="Digite sua mensagem aqui..."></textarea><input type="hidden" id="notification-agendamento-id" value=""></div>
        <div id="popup-footer"><button id="notification-cancel-btn" class="btn btn-secondary">Cancelar</button><button id="send-notification-btn" class="btn btn-success">Enviar E-mail</button></div>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="../vendor/bootstrap-4.1/popper.min.js"></script>
    <script src="../vendor/bootstrap-4.1/bootstrap.min.js"></script>
    <script src="../vendor/animsition/animsition.min.js"></script>
    <script src="../js/main.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('admin-calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'pt-br',
            initialView: 'dayGridMonth',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
            buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana', list: 'Lista' },
            events: 'admin_buscar_agendamentos.php',

            eventClick: function(info) {
                info.jsEvent.preventDefault();
                let props = info.event.extendedProps;
                
                $('#popup-title').text('Detalhes de: ' + info.event.title);
                $('#popup-body').html(
                    '<p><strong>Nome:</strong> ' + props.nome + '</p>' +
                    '<p><strong>Telefone:</strong> ' + (props.telefone || 'Não informado') + '</p>' + // ADICIONE EST
                    '<p><strong>CPF/OAB:</strong> ' + (props.cpf || 'Não informado') + '</p>' +
                    '<p><strong>Nº OAB:</strong> ' + (props.oab || 'Não informado') + '</p>' +
                    '<p><strong>Email:</strong> ' + (props.email || 'Não informado') + '</p>' +
                    '<hr>' +
                    '<p><strong>Motivo:</strong> ' + (props.motivo || 'Não informado') + '</p>' +
                    '<p><strong>Status:</strong> ' + (props.status || 'Não informado') + '</p>' +
                    '<hr>' +
                    '<p><strong>Data:</strong> ' + info.event.start.toLocaleDateString("pt-BR") + '</p>' +
                    '<p><strong>Hora:</strong> ' + info.event.start.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }) + '</p>'
                );
                $('#popup-edit-link').attr('href', 'editar_agendamento.php?id=' + props.id);
                
                $('#notification-agendamento-id').val(props.id);
                $('#notification-popup-title').text('Notificar: ' + info.event.title);

                $('#custom-popup-overlay, #custom-popup-box').fadeIn(200);
            }
        });
        calendar.render();

        $('#popup-close-btn, #custom-popup-overlay').on('click', function() {
            $('#custom-popup-overlay, #custom-popup-box').fadeOut(200);
        });

        $('#popup-notify-btn').on('click', function() {
            $('#custom-popup-box').fadeOut(100);
            $('#notification-popup-overlay, #notification-popup-box').fadeIn(200);
        });

        $('#notification-popup-close-btn, #notification-cancel-btn, #notification-popup-overlay').on('click', function() {
            $('#notification-popup-overlay, #notification-popup-box').fadeOut(200, function() {
                $('#custom-popup-overlay').fadeOut(100);
            });
            $('#notification-message').val('');
        });

        $('#send-notification-btn').on('click', function() {
            let agendamentoId = $('#notification-agendamento-id').val();
            let mensagem = $('#notification-message').val().trim();

            if (mensagem === '') {
                alert('Por favor, escreva uma mensagem antes de enviar.');
                return;
            }

            const sendButton = $(this);
            sendButton.prop('disabled', true).text('Enviando...');

            $.ajax({
                url: 'enviar_notificacao.php',
                type: 'POST',
                data: { id_agendamento: agendamentoId, mensagem: mensagem },
                dataType: 'json',
                success: function(response) {
                    alert(response.mensagem);
                    if (response.sucesso) {
                        $('#notification-popup-overlay, #notification-popup-box').fadeOut(200);
                        $('#notification-message').val('');
                    }
                },
                error: function() {
                    alert('Ocorreu um erro de comunicação. Tente novamente.');
                },
                complete: function() {
                    sendButton.prop('disabled', false).text('Enviar E-mail');
                }
            });
        });
    });
    </script>
</body>
</html>