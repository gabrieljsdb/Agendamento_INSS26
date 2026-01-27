<?php
session_start();
require '../conexao.php';

// Apenas admins podem exportar
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    exit('Acesso negado.');
}

// Define os cabeçalhos para forçar o download do arquivo
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=agendamentos_' . date('Y-m-d') . '.csv');

// Abre o fluxo de saída do PHP
$output = fopen('php://output', 'w');

// Escreve o cabeçalho do CSV, agora com a coluna CPF
fputcsv($output, [
    'Data', 'Hora', 'Nome', 'CPF', 'OAB', 'Email', 'Motivo Agendamento', 'Observacao', 'Status', 'Motivo Cancelamento'
], ';');

// Pega os mesmos filtros do dashboard para garantir consistência
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$status_filtro = $_GET['status'] ?? 'Todos';

$sql = "SELECT
            a.data_agendamento, a.hora_inicio, u.nome, u.cpf_oab, u.oab, u.email,
            a.motivo, a.observacao, a.status, a.motivo_cancelamento
        FROM agendamentos a
        JOIN usuarios u ON a.id_usuario = u.id";

// Montagem dinâmica da cláusula WHERE (idêntica ao dashboard)
$where_clauses = [];
$params = [];

if ($data_inicio && $data_fim) {
    $where_clauses[] = "a.data_agendamento BETWEEN ? AND ?";
    $params[] = $data_inicio;
    $params[] = $data_fim;
}

if ($status_filtro && $status_filtro !== 'Todos') {
    $where_clauses[] = "a.status = ?";
    $params[] = $status_filtro;
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY a.data_agendamento ASC, a.hora_inicio ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Escreve cada linha do resultado no CSV
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Formata a data e hora para o padrão brasileiro
    $row['data_agendamento'] = date('d/m/Y', strtotime($row['data_agendamento']));
    $row['hora_inicio'] = date('H:i', strtotime($row['hora_inicio']));
    fputcsv($output, $row, ';');
}

fclose($output);
exit;