<?php
// admin/ajax_status_atendimentos.php
session_start();
require '../conexao.php';

if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

$periodo = $_GET['periodo'] ?? 30;
$agenda = $_GET['agenda'] ?? 'todas';

// Calcular datas
$dataFim = date('Y-m-d');
$dataInicio = date('Y-m-d', strtotime("-$periodo days"));

// Construir query base
$whereAgenda = $agenda !== 'todas' ? "AND a.id_agenda = " . intval($agenda) : "";

// Buscar status dos atendimentos
$sql = "SELECT 
    COALESCE(a.status_atendimento, 'Aguardando') as status,
    COUNT(*) as total
    FROM agendamentos a
    WHERE a.data_agendamento BETWEEN ? AND ?
    AND a.status = 'Confirmado'
    $whereAgenda
    GROUP BY a.status_atendimento
    ORDER BY total DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$dataInicio, $dataFim]);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar arrays para o gráfico
$labels = [];
$data = [];

// Definir ordem dos status
$ordemStatus = ['Atendido', 'Não Compareceu', 'Remarcou', 'Aguardando'];

foreach ($ordemStatus as $status) {
    $encontrado = false;
    foreach ($dados as $row) {
        if ($row['status'] == $status) {
            $labels[] = $status;
            $data[] = (int)$row['total'];
            $encontrado = true;
            break;
        }
    }
    if (!$encontrado) {
        $labels[] = $status;
        $data[] = 0;
    }
}

echo json_encode([
    'labels' => $labels,
    'data' => $data
]);
?>