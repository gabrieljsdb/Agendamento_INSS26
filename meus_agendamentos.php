<?php
// ===================================================================
// BLOCO PHP COMPLETO E CORRIGIDO PARA meus_agendamentos.php
// ===================================================================
session_start();
require 'conexao.php';
require 'funcao_email.php'; // Garante que a função de e-mail esteja disponível

// 1. VERIFICA SE O USUÁRIO ESTÁ LOGADO
if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: login.php');
    exit;
}

// 2. DEFINE AS VARIÁVEIS INICIAIS
$id_usuario = $_SESSION['id_usuario'];
$nome_usuario = $_SESSION['nome_usuario'];
$mensagem = []; // Inicializa a variável de mensagem

// 3. PROCESSA O PEDIDO DE CANCELAMENTO (SE HOUVER)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_id'])) {

    $id_agendamento_cancelar = $_POST['cancelar_id'];
    $motivo_cancelamento = $_POST['motivo_cancelamento'] ?? 'Motivo não informado pelo usuário.';

    // --- VERIFICAR SE PODE CANCELAR (12 HORAS ANTES) ---
    $stmt_verificar = $pdo->prepare(
        "SELECT data_agendamento, hora_inicio, status 
         FROM agendamentos 
         WHERE id = ? AND id_usuario = ?"
    );
    $stmt_verificar->execute([$id_agendamento_cancelar, $id_usuario]);
    $agendamento = $stmt_verificar->fetch(PDO::FETCH_ASSOC);

    if ($agendamento) {
        // Verifica se já passou do prazo de cancelamento (12 horas antes)
        $data_hora_agendamento = new DateTime($agendamento['data_agendamento'] . ' ' . $agendamento['hora_inicio']);
        $data_hora_atual = new DateTime();
        $data_hora_limite_cancelamento = (clone $data_hora_agendamento)->modify('-12 hours');
        
        // Se já passou do prazo de cancelamento
        if ($data_hora_atual > $data_hora_limite_cancelamento) {
            $mensagem = ['tipo' => 'erro', 'texto' => 'Não é possível cancelar o agendamento. O prazo de cancelamento (12 horas antes) já expirou.'];
        } 
        // Se ainda está dentro do prazo, permite o cancelamento
        else {
            // Prepara a query para atualizar o status no banco COM DATA DO CANCELAMENTO
            $stmt_update = $pdo->prepare(
                "UPDATE agendamentos 
                 SET status = 'Cancelado', 
                     motivo_cancelamento = ?, 
                     data_cancelamento = NOW() 
                 WHERE id = ? AND id_usuario = ?"
            );
            
            // Executa a query e verifica se foi bem-sucedida
            if ($stmt_update->execute([$motivo_cancelamento, $id_agendamento_cancelar, $id_usuario])) {
                
                $mensagem = ['tipo' => 'sucesso', 'texto' => 'Agendamento cancelado com sucesso. Você não poderá fazer novos agendamentos por 2 horas.'];
                
                // --- INÍCIO DA LÓGICA DE E-MAIL ---
                $stmt_info = $pdo->prepare(
                    "SELECT a.data_agendamento, a.hora_inicio, u.nome, u.email
                     FROM agendamentos a JOIN usuarios u ON a.id_usuario = u.id
                     WHERE a.id = ?"
                );
                $stmt_info->execute([$id_agendamento_cancelar]);
                $info = $stmt_info->fetch(PDO::FETCH_ASSOC);

                if ($info) {
                    $data_formatada = date('d/m/Y', strtotime($info['data_agendamento']));
                    $hora_formatada = date('H:i', strtotime($info['hora_inicio']));

                    // E-mail para o Usuário
                    $assunto_usuario = "OAB/SC - Agendamento Cancelado ";

                    $corpo_usuario = "
                        <h1>Olá, " . htmlspecialchars($info['nome']) . ".</h1>
                        <p>Informamos que o seu agendamento com o servidor do <strong>INSS</strong> foi <strong>cancelado</strong>.</p>
                        <p>Confira abaixo os detalhes do agendamento cancelado:</p>
                        <ul>
                            <li><strong>Data:</strong> {$data_formatada}</li>
                            <li><strong>Horário:</strong> {$hora_formatada}</li>
                            <li><strong>Motivo:</strong> " . htmlspecialchars($motivo_cancelamento) . "</li>
                        </ul>
                        <p><strong>Importante:</strong> Após um cancelamento, você não poderá fazer novos agendamentos por 2 horas. Esta medida visa garantir fair play entre todos os usuários do sistema.</p>
                        <p>Atenciosamente,<br>Ordem dos Advogados de Santa Catarina</p>
                    ";

                    enviar_email($info['email'], $info['nome'], $assunto_usuario, $corpo_usuario);

                    // E-mail para a Lista de Administradores
                    $assunto_admin = "Agendamento Cancelado: " . htmlspecialchars($info['nome']);

                    $corpo_admin = "
                        <h1>Um agendamento foi cancelado pelo usuário.</h1>
                        <p><strong>Usuário:</strong> " . htmlspecialchars($info['nome']) . "</p>
                        <p><strong>E-mail:</strong> " . htmlspecialchars($info['email']) . "</p>
                        <p><strong>Data:</strong> {$data_formatada}</p>
                        <p><strong>Horário:</strong> {$hora_formatada}</p>
                        <p><strong>Motivo do Cancelamento:</strong> " . htmlspecialchars($motivo_cancelamento) . "</p>
                        <p><strong>Data/Hora do Cancelamento:</strong> " . date('d/m/Y H:i:s') . "</p>
                    ";
                    
                    if (defined('ADMIN_NOTIFICATION_LIST') && is_array(ADMIN_NOTIFICATION_LIST)) {
                        foreach (ADMIN_NOTIFICATION_LIST as $admin_email) {
                            enviar_email($admin_email, 'Administrador do Sistema', $assunto_admin, $corpo_admin);
                        }
                    }
                } // Fim do if($info)

            } else {
                $mensagem = ['tipo' => 'erro', 'texto' => 'Não foi possível cancelar o agendamento.'];
            } // Fim do if/else do $stmt_update->execute()
        }
    } else {
        $mensagem = ['tipo' => 'erro', 'texto' => 'Agendamento não encontrado.'];
    }

} // Fim do if($_SERVER['REQUEST_METHOD']...)

// 4. BUSCA TODOS OS AGENDAMENTOS DO USUÁRIO PARA EXIBIR NA TABELA
$stmt_agendamentos = $pdo->prepare(
    "SELECT id, data_agendamento, hora_inicio, status, motivo, observacao
     FROM agendamentos WHERE id_usuario = ?
     ORDER BY data_agendamento DESC, hora_inicio DESC"
);
$stmt_agendamentos->execute([$id_usuario]);
$meus_agendamentos = $stmt_agendamentos->fetchAll(PDO::FETCH_ASSOC);

// --- FUNÇÃO PARA VERIFICAR SE PODE CANCELAR ---
function podeCancelar($data_agendamento, $hora_inicio) {
    date_default_timezone_set('America/Sao_Paulo');
    
    $data_hora_agendamento = new DateTime($data_agendamento . ' ' . $hora_inicio);
    $data_hora_atual = new DateTime();
    $data_hora_limite_cancelamento = (clone $data_hora_agendamento)->modify('-12 hours');
    
    return $data_hora_atual <= $data_hora_limite_cancelamento;
}

// --- NOVA FUNÇÃO: VERIFICAR SE PODE FAZER NOVO AGENDAMENTO APÓS CANCELAMENTO ---
function podeFazerNovoAgendamento($id_usuario) {
    global $pdo;
    
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

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <!-- Required meta tags-->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Title Page-->
    <title>Meus Agendamentos</title>

    <!-- Fontfaces CSS-->
    <link href="css/font-face.css" rel="stylesheet" media="all">
    <link href="vendor/fontawesome-7.0.1/css/all.min.css" rel="stylesheet" media="all">
    <link href="vendor/mdi-font/css/material-design-iconic-font.min.css" rel="stylesheet" media="all">

    <!-- Bootstrap CSS-->
    <link href="vendor/bootstrap-5.3.8.min.css" rel="stylesheet" media="all">

    <!-- Vendor CSS-->
    <link href="vendor/animsition/animsition.min.css" rel="stylesheet" media="all">
    <link href="vendor/perfect-scrollbar/perfect-scrollbar-1.5.6.css" rel="stylesheet" media="all">

    <!-- Main CSS-->
    <link href="css/theme.css" rel="stylesheet" media="all">

    <style type="text/css">
        /* Estilos para o Modal de Cancelamento */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); display: none; justify-content: center; align-items: center; z-index: 1050; padding: 15px; }
        .modal-content { position: relative; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); width: 100%; max-width: 500px; text-align: left; }
        .modal-content h2 { margin-top: 0; }
        .modal-buttons { margin-top: 25px; display: flex; justify-content: flex-end; gap: 15px; }
        .modal-close-btn { position: absolute; top: 10px; right: 15px; font-size: 2.5rem; font-weight: bold; color: #aaa; background: none; border: none; cursor: pointer; }

        /* Estilos para a tabela e status (do seu código original) */
        .table-data2 .status--process { color: #00ad5f; }
        .table-data2 .status--denied { color: #fa4251; }
        .table-responsive-data2 { overflow-x: auto; }
        
        /* NOVO: Estilo para botão desabilitado */
        .btn-disabled { 
            background-color: #6c757d !important; 
            border-color: #6c757d !important;
            opacity: 0.6;
            cursor: not-allowed !important;
        }
        .btn-disabled:hover {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
        }
    </style>

</head>

<body>
    <div class="page-wrapper">
        <!-- MENU LATERAL -->
        <aside class="menu-sidebar d-none d-lg-block">
            <div class="logo">
                <a href="#"><img src="images/logo1.png" alt="Logo" /></a>
            </div>
            <div class="menu-sidebar__content js-scrollbar1">
                <nav class="navbar-sidebar">
                    <ul class="list-unstyled navbar__list">
                        <li><a href="agendar.php"><i class="fas fa-calendar-alt"></i>Painel de Agenda</a></li>
                        <li class="active"><a href="meus_agendamentos.php"><i class="fas fa-calendar-check"></i>Meus Agendamentos</a></li>
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
                        <div class="row">
                            <div class="col-md-12">
                                <!-- TÍTULO E BOTÃO DE NOVO AGENDAMENTO -->
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h2 class="title-1">Meus Agendamentos</h2>
                                    <?php if (podeFazerNovoAgendamento($id_usuario)): ?>
                                        <a href="agendar.php" class="btn btn-primary">
                                            <i class="zmdi zmdi-plus me-1"></i>Fazer Novo Agendamento
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled data-bs-toggle="tooltip" title="Você não pode fazer novos agendamentos por 2 horas após um cancelamento">
                                            <i class="zmdi zmdi-block me-1"></i>Agendamento Bloqueado (2h)
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <!-- MENSAGENS DE STATUS -->
                                    <?php if (!empty($mensagem) && isset($mensagem['tipo']) && isset($mensagem['texto'])): ?>
                                        <div class="alert alert-<?php echo $mensagem['tipo'] === 'sucesso' ? 'success' : 'danger'; ?>" role="alert">
                                            <?php echo htmlspecialchars($mensagem['texto']); ?>
                                        </div>
                                    <?php endif; ?>

                                <!-- TABELA DE AGENDAMENTOS -->
                                <div class="card">
                                    <div class="card-body">
                                        <?php if (empty($meus_agendamentos)): ?>
                                            <p class="text-center my-4">Você ainda não possui agendamentos.</p>
                                        <?php else: ?>
                                        <div class="table-responsive table-responsive-data2">
                                            <table class="table table-data2 table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Data & Hora</th>
                                                        <th>Motivo</th>
                                                        <th>Observação</th>
                                                        <th>Status</th>
                                                        <th>Ação</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($meus_agendamentos as $ag): ?>
                                                    <tr>
                                                        <td>
                                                            <?php echo date('d/m/Y', strtotime($ag['data_agendamento'])); ?> às <?php echo date('H:i', strtotime($ag['hora_inicio'])); ?>
                                                            <?php 
                                                                // Exibir informação sobre prazo de cancelamento
                                                                if ($ag['status'] === 'Confirmado') {
                                                                    $pode_cancelar = podeCancelar($ag['data_agendamento'], $ag['hora_inicio']);
                                                                    if (!$pode_cancelar) {
                                                                        echo '<br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Prazo de cancelamento expirado</small>';
                                                                    } else {
                                                                        $data_limite = (new DateTime($ag['data_agendamento'] . ' ' . $ag['hora_inicio']))->modify('-12 hours');
                                                                        echo '<br><small class="text-muted">Cancelar até: ' . $data_limite->format('d/m/Y H:i') . '</small>';
                                                                    }
                                                                }
                                                            ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($ag['motivo']); ?></td>
                                                        <td><?php echo htmlspecialchars($ag['observacao']); ?></td>
                                                        <td>
                                                            <span class="fw-bold <?php echo $ag['status'] === 'Confirmado' ? 'status--process' : 'status--denied'; ?>">
                                                                <?php echo htmlspecialchars($ag['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($ag['status'] === 'Confirmado'): ?>
                                                                <?php
                                                                    // Verifica se ainda pode cancelar (12 horas antes)
                                                                    $pode_cancelar = podeCancelar($ag['data_agendamento'], $ag['hora_inicio']);
                                                                ?>
                                                                <?php if ($pode_cancelar): ?>
                                                                    <button class="btn btn-danger btn-sm" data-bs-toggle="tooltip" title="Cancelar Agendamento" onclick="showCancelModal(<?php echo $ag['id']; ?>)">
                                                                        <i class="fas fa-times"></i> Cancelar
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button class="btn btn-secondary btn-sm btn-disabled" data-bs-toggle="tooltip" title="Prazo de cancelamento expirado (12 horas antes)" disabled>
                                                                        <i class="fas fa-clock"></i> Cancelamento Bloqueado
                                                                    </button>
                                                                <?php endif; ?>
                                                                <!-- Formulário oculto para cada linha -->
                                                                <form method="POST" id="form-cancel-<?php echo $ag['id']; ?>" class="d-none">
                                                                    <input type="hidden" name="cancelar_id" value="<?php echo $ag['id']; ?>">
                                                                    <input type="hidden" name="motivo_cancelamento" class="motivo-cancelamento-input">
                                                                </form>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

   <!-- MODAL DE CANCELAMENTO -->
   <div id="cancelModal" class="modal-overlay">
       <div class="modal-content">
           <button type="button" class="modal-close-btn" onclick="hideCancelModal()">&times;</button>
           <h2>Motivo do Cancelamento</h2>
           <p>Por favor, informe o motivo pelo qual você está cancelando este agendamento.</p>
           <p class="text-muted"><small>Lembre-se: O cancelamento deve ser feito com até 12 horas de antecedência.</small></p>
           <p class="text-warning"><small><strong>Atenção:</strong> Após o cancelamento, você não poderá fazer novos agendamentos por 2 horas.</small></p>
           <form id="modalCancelForm" class="mt-3">
               <input type="hidden" id="modal_cancelar_id">
               <textarea id="motivoCancelamentoText" class="form-control" rows="4" placeholder="Digite o motivo aqui..." required></textarea>
               <div class="modal-buttons">
                   <button type="button" class="btn btn-secondary" onclick="hideCancelModal()">Voltar</button>
                   <button type="submit" class="btn btn-primary">Confirmar Cancelamento</button>
               </div>
           </form>
       </div>
   </div>

    <!-- JAVASCRIPT -->
    <script src="vendor/jquery-3.2.1.min.js"></script>
    <script src="vendor/bootstrap-5.3.8.bundle.min.js"></script>
    <script src="vendor/animsition/animsition.min.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar-1.5.6.min.js"></script>
    <script src="js/main.js"></script> <!-- JS do Template CoolAdmin -->

    <!-- SCRIPT DO MODAL -->
    <script>
        const modal = document.getElementById('cancelModal');
        const modalForm = document.getElementById('modalCancelForm');
        const motivoTextarea = document.getElementById('motivoCancelamentoText');
        const modalHiddenIdInput = document.getElementById('modal_cancelar_id');

        function showCancelModal(id) {
            modalHiddenIdInput.value = id;
            modal.style.display = 'flex';
            motivoTextarea.focus();
        }

        function hideCancelModal() {
            modal.style.display = 'none';
            motivoTextarea.value = '';
            modalHiddenIdInput.value = '';
        }

        modalForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const motivo = motivoTextarea.value.trim();
            const idParaCancelar = modalHiddenIdInput.value;

            if (motivo !== '' && idParaCancelar !== '') {
                const formDaTabela = document.getElementById('form-cancel-' + idParaCancelar);
                const motivoInputDaTabela = formDaTabela.querySelector('.motivo-cancelamento-input');
                
                motivoInputDaTabela.value = motivo;
                formDaTabela.submit();
            } else {
                alert('O motivo do cancelamento é obrigatório.');
            }
        });

        window.addEventListener('click', function(event) {
            if (event.target == modal) {
                hideCancelModal();
            }
        });

        // Inicializa os tooltips do Bootstrap 5
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    </script>
</body>
</html>