<?php
// ===================================================================
// INÍCIO DA LÓGICA PHP DO SEU SISTEMA ORIGINAL
// ===================================================================
session_start();
require 'conexao.php'; 
require 'funcao_email.php';

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: login.php');
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$nome_usuario = $_SESSION['nome_usuario'];
$id_agenda = 1; // ID fixo da agenda (conforme seu código original)
// NOVO: Busca o telefone atual do usuário para pré-preencher o formulário
$stmt_tel = $pdo->prepare("SELECT telefone FROM usuarios WHERE id = ?");
$stmt_tel->execute([$id_usuario]);
$telefone_padrao = $stmt_tel->fetchColumn() ?: ''; // Se não tiver, fica vazio

// --- NOVA FUNÇÃO: VERIFICAR SE PODE FAZER NOVO AGENDAMENTO APÓS CANCELAMENTO ---
function podeFazerNovoAgendamento($id_usuario, $pdo) {
    // Busca o último cancelamento do usuário
    $stmt = $pdo->prepare(
        "SELECT data_cancelamento 
         FROM agendamentos 
         WHERE id_usuario = ? AND status = 'Cancelado' 
         ORDER BY data_cancelamento DESC 
         LIMIT 1"
    );
    $stmt->execute([$id_usuario]);
    $ultimo_cancelamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ultimo_cancelamento || !$ultimo_cancelamento['data_cancelamento']) {
        return true; // Nunca cancelou, pode agendar
    }
    
    // Calcula se passaram 2 horas desde o último cancelamento
    $data_cancelamento = new DateTime($ultimo_cancelamento['data_cancelamento']);
    $data_atual = new DateTime();
    $intervalo = $data_cancelamento->diff($data_atual);
    
    // Converte o intervalo para horas
    $horas_passadas = ($intervalo->days * 24) + $intervalo->h + ($intervalo->i / 60);
    
    return $horas_passadas >= 2;
}

// Processa o envio do formulário do modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['data_selecionada'])) {
    $data_agendamento = $_POST['data_selecionada'];
    $hora_selecionada = $_POST['hora_selecionada'];
    $motivo = $_POST['motivo'];
    $telefone_contato = $_POST['telefone'] ?? ''; 
    $observacao = $_POST['observacao'];
    
    // --- NOVA VALIDAÇÃO: BLOQUEIO APÓS CANCELAMENTO (2 HORAS) ---
    if (!podeFazerNovoAgendamento($id_usuario, $pdo)) {
        header('Location: agendar.php?status=bloqueio_cancelamento');
        exit;
    }
    
    // --- NOVA VALIDAÇÃO: BLOQUEIO APÓS 19H DO DIA ANTERIOR ---
    date_default_timezone_set('America/Sao_Paulo');
    $data_hoje = new DateTime();
    $hora_atual = $data_hoje->format('H:i:s');
    $data_agendamento_obj = new DateTime($data_agendamento);
    
    // Calcula a diferença em dias
    $diferenca_dias = $data_hoje->diff($data_agendamento_obj)->days;
    
    // Se for para amanhã (1 dia de diferença) e depois das 19h, bloquear
    if ($diferenca_dias === 1 && $hora_atual > '19:00:00') {
        header('Location: agendar.php?status=bloqueio_19h');
        exit;
    }
    
    // Validação 1: Horário limite
    if ($hora_selecionada > '12:00:00') {
        header('Location: agendar.php?status=horario_invalido');
        exit;
    }
    
    // Validação 2: Limite de agendamentos por mês
    $data_obj = new DateTime($data_agendamento);
    $ano_selecionado = $data_obj->format('Y');
    $mes_selecionado = $data_obj->format('m');

    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE id_agenda = ? AND id_usuario = ? AND status = 'Confirmado' AND YEAR(data_agendamento) = ? AND MONTH(data_agendamento) = ?");
    $stmt_check->execute([$id_agenda, $id_usuario, $ano_selecionado, $mes_selecionado]);
    if ($stmt_check->fetchColumn() >= 2) {
        header('Location: agendar.php?status=limite_excedido');
        exit;
    }
    
    // Início da Transação para evitar duplicidade
    try {
        $pdo->beginTransaction();

        // Validação 3: Conflito de horário (race condition)
        $sql_check_conflito = "SELECT id FROM agendamentos WHERE id_agenda = ? AND data_agendamento = ? AND hora_inicio = ? AND status = 'Confirmado' FOR UPDATE";
        $stmt_check_conflito = $pdo->prepare($sql_check_conflito);
        $stmt_check_conflito->execute([$id_agenda, $data_agendamento, $hora_selecionada]);

        if ($stmt_check_conflito->rowCount() > 0) {
            $pdo->rollBack();
            header('Location: agendar.php?status=horario_ocupado');
            exit;
        }

        // Inserção no banco
        $hora_fim = date('H:i:s', strtotime($hora_selecionada . ' +30 minutes'));
        $stmt_insert = $pdo->prepare("INSERT INTO agendamentos (id_agenda, id_usuario, motivo, observacao, data_agendamento, hora_inicio, hora_fim, status, telefone_contato) VALUES (?, ?, ?, ?, ?, ?, ?, 'Confirmado', ?)");
if (!empty($telefone_contato)) {
             $stmt_upd_tel = $pdo->prepare("UPDATE usuarios SET telefone = ? WHERE id = ?");
             $stmt_upd_tel->execute([$telefone_contato, $id_usuario]);
        }
        if (!$stmt_insert->execute([$id_agenda, $id_usuario, $motivo, $observacao, $data_agendamento, $hora_selecionada, $hora_fim, $telefone_contato])) {
            throw new Exception("Falha ao inserir o agendamento no banco de dados.");
        }

        $pdo->commit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro durante a transação de agendamento: " . $e->getMessage());
        header('Location: agendar.php?status=erro');
        exit;
    }

    // LÓGICA DE E-MAIL (Executada apenas após o sucesso da transação)
    $stmt_user = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
    $stmt_user->execute([$id_usuario]);
    $usuario = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        $data_formatada = date('d/m/Y', strtotime($data_agendamento));
        $hora_formatada = date('H:i', strtotime($hora_selecionada));

        // E-mail para o Usuário
        $assunto_usuario = "OAB/SC - Agendamento Confirmado!";
        $corpo_usuario = "<h1>Olá, " . htmlspecialchars($usuario['nome']) . "!</h1><p>Seu agendamento com o servidor do <strong>INSS</strong> foi <strong>confirmado</strong> com sucesso. Abaixo estão os detalhes do seu atendimento:</p><ul><li><strong>Data:</strong> {$data_formatada}</li><li><strong>Horário:</strong> {$hora_formatada}</li><li><strong>Motivo:</strong> " . htmlspecialchars($motivo) . "</li></ul><p><strong>Importante:</strong> O atendimento será <strong>presencial</strong> e acontecerá na seguinte localização:</p><p style='margin-left: 20px;'>Seccional do INSS<br>Rua Paschoal Apóstolo Pítsica, 4860<br>Bairro Agronômica – Florianópolis/SC</p><p>Por favor, compareça com antecedência e leve todos os documentos necessários.</p><p>Atenciosamente,<br>Ordem dos Advogados de Santa Catarina</p>";
        enviar_email($usuario['email'], $usuario['nome'], $assunto_usuario, $corpo_usuario);
    }

    // Redirecionamento final após tudo (banco e e-mail)
    header('Location: agendar.php?status=sucesso');
    exit;

}
// Busca os próximos 5 agendamentos do usuário
$stmt_proximos = $pdo->prepare("SELECT data_agendamento, hora_inicio, motivo FROM agendamentos WHERE id_usuario = ? AND id_agenda = ? AND status = 'Confirmado' AND CONCAT(data_agendamento, ' ', hora_inicio) >= NOW() ORDER BY data_agendamento ASC, hora_inicio ASC LIMIT 5");
$stmt_proximos->execute([$id_usuario, $id_agenda]); // Adicione $id_agenda  
$proximos_agendamentos = $stmt_proximos->fetchAll(PDO::FETCH_ASSOC);

// ===================================================================
// FIM DA LÓGICA PHP - INÍCIO DO HTML
// ===================================================================
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <!-- Required meta tags-->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Title Page-->
    <title>Painel de Agendamentos</title>

    <!-- Fontfaces CSS-->
    <link href="css/font-face.css" rel="stylesheet" media="all">
    <link href="vendor/fontawesome-7.0.1/css/all.min.css" rel="stylesheet" media="all">
    <link href="vendor/mdi-font/css/material-design-iconic-font.min.css" rel="stylesheet" media="all">

    <!-- Bootstrap CSS-->
    <link href="vendor/bootstrap-5.3.8.min.css" rel="stylesheet" media="all">

    <!-- Vendor CSS-->
    <link href="vendor/animsition/animsition.min.css" rel="stylesheet" media="all">
    <link href="vendor/perfect-scrollbar/perfect-scrollbar-1.5.6.css" rel="stylesheet" media="all">

    <!-- FullCalendar v6+ CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css' rel='stylesheet' />

    <!-- Main CSS-->
    <link href="css/theme.css" rel="stylesheet" media="all">

    <style type="text/css">
        /* Estilos para o FullCalendar (do template) */
        .fc .fc-button-primary { background-color: var(--bs-primary); border-color: var(--bs-primary); }
        .fc .fc-button-primary:hover { background-color: var(--bs-primary); border-color: var(--bs-primary); opacity: 0.9; }
        
        /* ===== CÓDIGO CSS ADICIONADO PARA FIXAR A LATERAL ===== */
        .sidebar-sticky {
            position: -webkit-sticky;
            position: sticky;
            top: 85px; /* Distância do topo. Ajuste se o seu header tiver altura diferente */
        }

        /* ===== ESTILOS DOS SEUS MODAIS E ITENS (DO CÓDIGO ORIGINAL) ===== */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); display: none; justify-content: center; align-items: center; z-index: 1050; padding: 15px; }
        .modal-content { position: relative; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); width: 100%; max-width: 500px; text-align: left; }
        .modal-content h2 { margin-top: 0; }
        .modal-content label { display: block; margin-top: 15px; font-weight: bold; }
        .modal-content select, .modal-content textarea { width: 100%; padding: 12px; border: 1px solid #dee2e6; border-radius: 5px; }
        .modal-buttons { margin-top: 25px; text-align: right; }
        .modal-buttons .btn { margin-left: 10px; }
        .modal-close-btn { position: absolute; top: 10px; right: 15px; font-size: 2.5rem; font-weight: bold; color: #aaa; background: none; border: none; cursor: pointer; }
        
        /* Estilo para dias bloqueados no calendário */
        .fc-day-blocked {
            background-color: #f8f9fa !important;
            opacity: 0.5;
        }
        .fc-day-blocked .fc-daygrid-day-number {
            color: #6c757d !important;
        }
    </style>

</head>

<body>
    <div class="page-wrapper">
        <!-- MENU LATERAL - Simplificado para o exemplo -->
        <aside class="menu-sidebar d-none d-lg-block">
            <div class="logo">
                <a href="#"><img src="images/logo1.png" alt="Logo" /></a>
            </div>
            <div class="menu-sidebar__content js-scrollbar1">
                <nav class="navbar-sidebar">
                    <ul class="list-unstyled navbar__list">
                        <li class="active"><a href="agendar.php"><i class="fas fa-calendar-alt"></i>Painel de Agenda</a></li>
                        <li><a href="meus_agendamentos.php"><i class="fas fa-calendar-check"></i>Meus Agendamentos</a></li>
                    </ul>
                </nav>
            </div>
        </aside>
        <!-- FIM DO MENU LATERAL -->

        <!-- PAGE CONTAINER-->
        <div class="page-container">
            <!-- HEADER DESKTOP-->
            <header class="header-desktop">
                <div class="section__content section__content--p30">
                    <div class="container-fluid">
                        <div class="header-wrap">
                            <form class="form-header" action="" method="POST"></form>
                            <div class="header-button">
                                <div class="account-wrap">
                                    <div class="account-item clearfix">
                                        <div class="content d-flex align-items-center">
                                            <span class="me-3">Olá, <?php echo htmlspecialchars($nome_usuario); ?></span>
                                            <a href="logout.php" class="btn btn-danger btn-sm">
                                                <i class="zmdi zmdi-power me-1"></i> Sair
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            <!-- END HEADER DESKTOP-->

            <!-- MAIN CONTENT-->
            <div class="main-content">
                <div class="section__content section__content--p30">
                    <div class="container-fluid">
                        <!-- MENSAGENS DE STATUS (ATUALIZADAS) -->
                        <?php if (isset($_GET['status'])): ?>
                        <?php if ($_GET['status'] === 'sucesso'): ?><div class="alert alert-success" role="alert">Agendamento realizado com sucesso!</div><?php endif; ?>
                        <?php if ($_GET['status'] === 'erro'): ?><div class="alert alert-danger" role="alert">Ocorreu um erro ao tentar agendar.</div><?php endif; ?>
                        <?php if ($_GET['status'] === 'horario_ocupado'): ?><div class="alert alert-danger" role="alert">Desculpe, este horário acabou de ser ocupado. Por favor, escolha outro.</div><?php endif; ?>
                        <?php if ($_GET['status'] === 'bloqueio_19h'): ?><div class="alert alert-danger" role="alert">Para agendar para amanhã, o agendamento deve ser feito até às 19h de hoje. Por favor, selecione uma data a partir de depois de amanhã.</div><?php endif; ?>
                        <?php if ($_GET['status'] === 'bloqueio_cancelamento'): ?><div class="alert alert-danger" role="alert">Você não pode fazer novos agendamentos por 2 horas após um cancelamento. Esta medida visa evitar abuso do sistema e garantir vagas para outros usuários.</div><?php endif; ?>
                        <?php endif; ?>

                        <div class="row m-t-25">
                            <div class="col-lg-9">
                                <div class="card">
                                    <div class="card-body">
                                        <div id="calendar"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <!-- DIV ADICIONADA PARA APLICAR O EFEITO STICKY -->
                                <div class="sidebar-sticky">
                                    <!-- PRÓXIMOS AGENDAMENTOS (LÓGICA DO SEU PHP) -->
                                    <div class="card mb-4">
                                        <div class="card-header"><strong>Próximos Agendamentos</strong></div>
                                        <div class="card-body">
                                            <?php if (empty($proximos_agendamentos)): ?>
                                                <p>Nenhum agendamento futuro.</p>
                                            <?php else: ?>
                                                <?php foreach ($proximos_agendamentos as $ag): ?>
                                                    <div class="d-flex align-items-start mb-3 p-3 border rounded">
                                                        <div class="text-center me-3">
                                                            <div class="fs-6 fw-bold text-primary"><?php echo strtoupper(date('M', strtotime($ag['data_agendamento']))); ?></div>
                                                            <div class="fs-4 fw-bold"><?php echo date('d', strtotime($ag['data_agendamento'])); ?></div>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($ag['motivo']); ?></h6>
                                                            <small class="text-muted"><?php echo date('H:i', strtotime($ag['hora_inicio'])); ?></small>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- LEGENDA (ATUALIZADA) -->
                                    <div class="card">
                                        <div class="card-header"><strong>Legenda</strong></div>
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="rounded me-2" style="width: 16px; height: 16px; background-color: #dc3545;"></div>
                                                <span>Horário agendado</span>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="rounded me-2" style="width: 16px; height: 16px; background-color: #007bff;"></div>
                                                <span>Período bloqueado</span>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="rounded me-2" style="width: 16px; height: 16px; background-color: #ffc107;"></div>
                                                <span>Dia indisponível</span>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="rounded me-2" style="width: 16px; height: 16px; background-color: #f8f9fa; border: 1px solid #dee2e6;"></div>
                                                <span>Bloqueado após 19h</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded me-2" style="width: 16px; height: 16px; background-color: #6c757d;"></div>
                                                <span>Bloqueio pós-cancelamento (2h)</span>
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
    </div>

    <!-- MODAIS (DO SEU CÓDIGO ORIGINAL) -->
    <div id="agendamentoModal" class="modal-overlay">
        <div class="modal-content">
            <button type="button" class="modal-close-btn" onclick="hideAgendamentoModal()">&times;</button>
            <h2>Novo Agendamento</h2>
            <form id="agendamentoForm" method="POST" action="agendar.php">
                <input type="hidden" id="data_selecionada" name="data_selecionada">
                <input type="hidden" id="hora_selecionada" name="hora_selecionada">
                <p>Você está agendando para: <strong id="info_data_hora"></strong></p>
                <label for="motivo">Motivo</label>
                <select name="motivo" id="motivo" class="form-control" required>
                    <option value="Atendimento">Atendimento</option>
                    <option value="Problemas com Senha">Problemas com Senha</option>
                    <option value="Outros">Outros</option>
                </select>
                <label for="telefone" style="margin-top: 15px; display: block; font-weight: bold;">Telefone para Contato</label>
                <input type="tel" name="telefone" id="telefone" class="form-control" required placeholder="(DDD) 99999-9999" value="<?php echo htmlspecialchars($telefone_padrao); ?>">
                <label for="observacao">Observação</label>
                <textarea name="observacao" id="observacao" rows="3" class="form-control" placeholder="Breve relato..."></textarea>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="hideAgendamentoModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
    <div id="limiteModal" class="modal-overlay">
        <div class="modal-content">
            <button type="button" class="modal-close-btn" onclick="hideLimiteModal()">&times;</button>
            <h2 style="color: #842029;">Limite Atingido</h2>
            <p style="font-size: 1.1rem; margin-top: 15px;">Você já atingiu o limite de 2 agendamentos confirmados para este mês.</p>
            <div class="modal-buttons">
                <button type="button" class="btn btn-primary" onclick="hideLimiteModal()">Entendi</button>
            </div>
        </div>
    </div>

    <!-- MODAL DE BLOQUEIO POR CANCELAMENTO -->
    <div id="bloqueioCancelamentoModal" class="modal-overlay">
        <div class="modal-content">
            <button type="button" class="modal-close-btn" onclick="hideBloqueioCancelamentoModal()">&times;</button>
            <h2 style="color: #842029;">Aguardando Período de Bloqueio</h2>
            <p style="font-size: 1.1rem; margin-top: 15px;">
                Você cancelou um agendamento recentemente. Para evitar abuso do sistema e garantir que 
                as vagas sejam disponibilizadas para outros usuários, você não poderá fazer novos 
                agendamentos por <strong>2 horas</strong> após um cancelamento.
            </p>
            <p style="font-size: 1rem; color: #6c757d;">
                Esta medida garante a fair play entre todos os usuários do sistema.
            </p>
            <div class="modal-buttons">
                <button type="button" class="btn btn-primary" onclick="hideBloqueioCancelamentoModal()">Entendi</button>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script src="vendor/jquery-3.2.1.min.js"></script>
    <script src="vendor/bootstrap-5.3.8.bundle.min.js"></script>
    <script src="vendor/animsition/animsition.min.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar-1.5.6.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script src="js/main.js"></script> <!-- JS do Template CoolAdmin -->
    
    <!-- SEU SCRIPT DO CALENDÁRIO E MODAIS -->
    <script>
        // Funções de controle dos modais (do seu código original)
        const modal = document.getElementById('agendamentoModal');
        const infoDataHora = document.getElementById('info_data_hora');
        const inputData = document.getElementById('data_selecionada');
        const inputHora = document.getElementById('hora_selecionada');
        function showAgendamentoModal(data, hora) {
            const dataObj = new Date(data + 'T' + hora);
            infoDataHora.textContent = dataObj.toLocaleDateString('pt-BR') + ' às ' + hora.substring(0, 5);
            inputData.value = data;
            inputHora.value = hora;
            modal.style.display = 'flex';
        }
        function hideAgendamentoModal() { modal.style.display = 'none'; }
        
        const limiteModal = document.getElementById('limiteModal');
        function showLimiteModal() { limiteModal.style.display = 'flex'; }
        function hideLimiteModal() { limiteModal.style.display = 'none'; }

        // Funções para o modal de bloqueio por cancelamento
        const bloqueioCancelamentoModal = document.getElementById('bloqueioCancelamentoModal');
        function showBloqueioCancelamentoModal() { bloqueioCancelamentoModal.style.display = 'flex'; }
        function hideBloqueioCancelamentoModal() { bloqueioCancelamentoModal.style.display = 'none'; }

        // --- NOVAS FUNÇÕES PARA BLOQUEIO APÓS 19H ---
        
        // Função para determinar se uma data deve ser bloqueada
        function isDataBloqueada(date) {
            const hoje = new Date();
            const dataTeste = new Date(date);
            
            // Calcula diferença em dias
            const diffTime = dataTeste - hoje;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            // Se for amanhã e já passou das 19h, bloquear
            if (diffDays === 1) {
                const horaAtual = hoje.getHours();
                return horaAtual >= 19;
            }
            
            return false;
        }

        // Função para validar data de agendamento
        function validarDataAgendamento(dataSelecionada) {
            const hoje = new Date();
            const dataAgendamento = new Date(dataSelecionada);
            
            // Calcula diferença em dias
            const diffTime = dataAgendamento - hoje;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            // Se for para amanhã, verifica se já passou das 19h
            if (diffDays === 1) {
                const horaAtual = hoje.getHours();
                const minutoAtual = hoje.getMinutes();
                
                // Se já passou das 19h, bloquear agendamento para amanhã
                if (horaAtual >= 19) {
                    return false;
                }
            }
            
            return true;
        }

        // Inicialização do FullCalendar (ATUALIZADA)
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var hoje = new Date();
            var dataLimite = new Date();
            dataLimite.setDate(hoje.getDate() + 30);

            var calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'pt-br',
                initialView: 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
                buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana', day: 'Dia' },
                slotMinTime: '08:00:00',
                slotMaxTime: '12:30:00',
                allDaySlot: false,
                nowIndicator: true,
                weekends: false,
                events: 'buscar_agendamentos.php', 
                validRange: { start: hoje, end: dataLimite },
                
                // NOVO: Bloquear visualmente as datas após 19h
                dayCellDidMount: function(arg) {
                    if (isDataBloqueada(arg.date)) {
                        arg.el.classList.add('fc-day-blocked');
                        arg.el.title = 'Agendamento bloqueado - Disponível apenas a partir de depois de amanhã após às 19h';
                    }
                },
                
                dateClick: function(info) {
                    if (calendar.view.type === 'dayGridMonth') {
                        calendar.changeView('timeGridDay', info.dateStr);
                        return;
                    }
                    if (!info.jsEvent.target.closest('.fc-event')) {
                        const dataClicada = new Date(info.dateStr);
                        if (dataClicada < new Date()) {
                            alert('Não é possível agendar em um horário que já passou.');
                            return;
                        }

                        // --- NOVA VERIFICAÇÃO DO BLOQUEIO 19H ---
                        if (!validarDataAgendamento(info.dateStr)) {
                            alert('Para agendar para amanhã, o agendamento deve ser feito até às 19h de hoje. Por favor, selecione uma data a partir de depois de amanhã.');
                            return;
                        }

                        // --- VERIFICAÇÃO DE HORÁRIO ---
                        const hora = info.dateStr.substring(11, 19);
                        if (hora > '12:00:00') {
                            alert('O último horário para agendamento é às 12:00. Por favor, selecione um horário válido.');
                            return;
                        }

                        const data = info.dateStr.substring(0, 10);
                        showAgendamentoModal(data, hora);
                    }
                }
            });
            calendar.render();

            // Lógica para exibir os modais
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('status') === 'limite_excedido') {
                showLimiteModal();
            }
            if (urlParams.get('status') === 'bloqueio_cancelamento') {
                showBloqueioCancelamentoModal();
            }
        });
    </script>
</body>

</html>