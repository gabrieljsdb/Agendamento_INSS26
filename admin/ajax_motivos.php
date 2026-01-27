<?php
// admin/ajax_motivos.php
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

// Buscar distribuição por motivo
$sql = "SELECT 
    a.motivo,
    COUNT(*) as total
    FROM agendamentos a
    WHERE a.data_agendamento BETWEEN ? AND ?
    AND a.status = 'Confirmado'
    $whereAgenda
    GROUP BY a.motivo
    ORDER BY total DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$dataInicio, $dataFim]);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar arrays para o gráfico
$labels = [];
$data = [];

foreach ($dados as $row) {
    $labels[] = $row['motivo'];
    $data[] = (int)$row['total'];
}

// Se não houver dados, mostrar mensagem
if (empty($dados)) {
    $labels = ['Sem dados'];
    $data = [1];
}

echo json_encode([
    'labels' => $labels,
    'data' => $data
]);
?>