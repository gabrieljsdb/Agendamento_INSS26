<?php
header('Content-Type: application/json');
require 'conexao.php';

$id_agenda = 1; // ID fixo da agenda (conforme seu código original)
$eventos = [];


// 1. Busca agendamentos feitos por usuários
$query_agendamentos = "SELECT data_agendamento, hora_inicio, hora_fim FROM agendamentos WHERE status = 'Confirmado' AND id_agenda = ?";
$stmt_agendamentos = $pdo->prepare($query_agendamentos);
$stmt_agendamentos->execute([$id_agenda]); // Adicione $id_agenda
while ($row = $stmt_agendamentos->fetch(PDO::FETCH_ASSOC)) {
    $eventos[] = [
        'title' => 'Reservado',
        'start' => $row['data_agendamento'] . 'T' . $row['hora_inicio'],
        'end'   => $row['data_agendamento'] . 'T' . $row['hora_fim'],
        'backgroundColor' => '#dc3545',
        'borderColor' => '#dc3545'
    ];
}

// 2. Busca bloqueios feitos pelo administrador
$query_bloqueios = "SELECT data_bloqueio, data_fim_bloqueio, hora_inicio_bloqueio, hora_fim_bloqueio, motivo_bloqueio FROM bloqueios WHERE id_agenda = ?";
$stmt_bloqueios = $pdo->prepare($query_bloqueios);
$stmt_bloqueios->execute([$id_agenda]); // Adicione $id_agenda
while ($row = $stmt_bloqueios->fetch(PDO::FETCH_ASSOC)) {
    $data_inicio = new DateTime($row['data_bloqueio']);
    $data_fim = $row['data_fim_bloqueio'] ? new DateTime($row['data_fim_bloqueio']) : clone $data_inicio;
    
    for ($data = $data_inicio; $data <= $data_fim; $data->modify('+1 day')) {
        if ($row['hora_inicio_bloqueio']) {
            $eventos[] = [
                'title' => $row['motivo_bloqueio'] ?: 'Bloqueado',
                'start' => $data->format('Y-m-d') . 'T' . $row['hora_inicio_bloqueio'],
                'end'   => $data->format('Y-m-d') . 'T' . $row['hora_fim_bloqueio'],
                'backgroundColor' => '#007bff',
                'borderColor' => '#6c757d'
            ];
        } else {
             $eventos[] = [
                'title' => $row['motivo_bloqueio'] ?: 'Dia Bloqueado',
                'start' => $data->format('Y-m-d'),
                'allDay' => true,
                'display' => 'background',
                'backgroundColor' => '#ffc107'
            ];
        }
    }
}

echo json_encode($eventos);