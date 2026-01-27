<?php
// admin/ajax_horarios.php
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

// Buscar horários mais populares
$sql = "SELECT 
    HOUR(a.hora_inicio) as hora,
    COUNT(*) as total
    FROM agendamentos a
    WHERE a.data_agendamento BETWEEN ? AND ?
    AND a.status = 'Confirmado'
    $whereAgenda
    GROUP BY HOUR(a.hora_inicio)
    ORDER BY hora ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$dataInicio, $dataFim]);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar arrays para o gráfico
$labels = [];
$data = [];

// Preencher todas as horas do dia (8h às 17h)
for ($hora = 8; $hora <= 17; $hora++) {
    $labels[] = sprintf('%02d:00', $hora);
    
    // Buscar dados para esta hora
    $dadosHora = array_filter($dados, function($item) use ($hora) {
        return $item['hora'] == $hora;
    });
    
    if (!empty($dadosHora)) {
        $dadosHora = reset($dadosHora);
        $data[] = (int)$dadosHora['total'];
    } else {
        $data[] = 0;
    }
}

echo json_encode([
    'labels' => $labels,
    'data' => $data
]);
?>