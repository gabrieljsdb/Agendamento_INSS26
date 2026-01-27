<?php
// admin/enviar_notificacao.php - VERSÃO COM DEBUG
session_start();
header('Content-Type: application/json');
require '../conexao.php';
require '../funcao_email.php';

// Log para debug
error_log("=== INICIANDO ENVIO DE NOTIFICAÇÃO ===");

// Proteção para garantir que apenas administradores logados possam usar esta função
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    error_log("ACESSO NEGADO: Admin não logado");
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado.']);
    exit;
}

// Verifica se a requisição é do tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("MÉTODO NÃO PERMITIDO: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

// Pega e valida os dados recebidos do formulário
$id_agendamento = $_POST['id_agendamento'] ?? null;
$mensagem_admin = trim($_POST['mensagem'] ?? '');

error_log("Dados recebidos - ID: $id_agendamento, Mensagem: " . substr($mensagem_admin, 0, 50));

if (empty($id_agendamento) || empty($mensagem_admin)) {
    error_log("DADOS INCOMPLETOS: ID ou mensagem vazios");
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID do agendamento e mensagem são obrigatórios.']);
    exit;
}

// Busca os dados do usuário e do agendamento no banco de dados
$stmt_info = $pdo->prepare(
    "SELECT u.nome, u.email, a.data_agendamento, a.hora_inicio
     FROM agendamentos a 
     JOIN usuarios u ON a.id_usuario = u.id 
     WHERE a.id = ?"
);
$stmt_info->execute([$id_agendamento]);
$info = $stmt_info->fetch(PDO::FETCH_ASSOC);

error_log("Resultado da busca no BD: " . ($info ? "ENCONTRADO" : "NÃO ENCONTRADO"));

if (!$info) {
    error_log("AGENDAMENTO NÃO ENCONTRADO: ID $id_agendamento");
    http_response_code(404);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Agendamento não encontrado no sistema.']);
    exit;
}

error_log("Dados do usuário: " . $info['nome'] . " - " . $info['email']);

// Prepara os dados para o e-mail
$data_formatada = date('d/m/Y', strtotime($info['data_agendamento']));
$hora_formatada = date('H:i', strtotime($info['hora_inicio']));
$assunto = "Uma Notificação Sobre o Seu Agendamento";

// Monta o corpo do e-mail em HTML
$corpo_html = "
    <h1>Olá, " . htmlspecialchars($info['nome']) . "!</h1>
    <p>Você recebeu uma mensagem da administração sobre o seu agendamento do dia <strong>{$data_formatada} às {$hora_formatada}</strong>.</p>
    <hr>
    <p><strong>Mensagem do Administrador:</strong></p>
    <div style='padding: 15px; background-color: #f2f2f2; border-left: 5px solid #ccc; font-style: italic;'>
        " . nl2br(htmlspecialchars($mensagem_admin)) . "
    </div>
    <hr>
    <p>Para mais detalhes ou para responder, por favor, acesse o painel de agendamentos ou entre em contato pelos nossos canais oficiais.</p>
";

error_log("Tentando enviar e-mail para: " . $info['email']);

// Tenta enviar o e-mail e retorna uma resposta JSON
if (enviar_email($info['email'], $info['nome'], $assunto, $corpo_html)) {
    error_log("E-MAIL ENVIADO COM SUCESSO");
    echo json_encode(['sucesso' => true, 'mensagem' => 'E-mail enviado com sucesso!']);
} else {
    error_log("FALHA NO ENVIO DO E-MAIL");
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Ocorreu um erro interno ao tentar enviar o e-mail.']);
}

error_log("=== FINALIZANDO ENVIO DE NOTIFICAÇÃO ===");
?>