<?php
// admin/ajax_agendamentos_dia.php
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

// Buscar agendamentos por dia
$sql = "SELECT 
    DATE(a.data_agendamento) as data,
    COUNT(CASE WHEN a.status = 'Confirmado' THEN 1 END) as confirmados,
    COUNT(CASE WHEN a.status = 'Cancelado' THEN 1 END) as cancelados
    FROM agendamentos a
    WHERE a.data_agendamento BETWEEN ? AND ?
    $whereAgenda
    GROUP BY DATE(a.data_agendamento)
    ORDER BY data ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$dataInicio, $dataFim]);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar arrays para o gráfico
$labels = [];
$confirmados = [];
$cancelados = [];

// Preencher todos os dias do período (mesmo sem agendamentos)
$currentDate = $dataInicio;
while ($currentDate <= $dataFim) {
    $labels[] = date('d/m', strtotime($currentDate));
    
    // Buscar dados para esta data
    $dadosDia = array_filter($dados, function($item) use ($currentDate) {
        return $item['data'] == $currentDate;
    });
    
    if (!empty($dadosDia)) {
        $dadosDia = reset($dadosDia);
        $confirmados[] = (int)$dadosDia['confirmados'];
        $cancelados[] = (int)$dadosDia['cancelados'];
    } else {
        $confirmados[] = 0;
        $cancelados[] = 0;
    }
    
    $currentDate = date('Y-m-d', strtotime("+1 day", strtotime($currentDate)));
}

echo json_encode([
    'labels' => $labels,
    'confirmados' => $confirmados,
    'cancelados' => $cancelados
]);
?>