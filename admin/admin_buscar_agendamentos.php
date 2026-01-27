<?php
session_start();
require '../conexao.php';

// Proteção: Apenas administradores logados podem acessar estes dados.
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    echo json_encode([]);
    exit;
}

// Pega o ID da agenda que está armazenado na sessão. Usa 1 como padrão para segurança.
$id_agenda_selecionada = $_SESSION['admin_id_agenda_selecionada'] ?? 1;

$eventos = [];

// 1. Busca todos os agendamentos com os dados do usuário, FILTRANDO PELA AGENDA SELECIONADA
$query_agendamentos = "SELECT a.id, a.data_agendamento, a.hora_inicio, a.hora_fim, a.status, a.telefone_contato, a.motivo, a.observacao, u.nome, u.email, u.oab, u.cpf_oab
                       FROM agendamentos a 
                       JOIN usuarios u ON a.id_usuario = u.id
                       WHERE a.id_agenda = ?";

try {
    $stmt_agendamentos = $pdo->prepare($query_agendamentos);
    $stmt_agendamentos->execute([$id_agenda_selecionada]);

    while ($row = $stmt_agendamentos->fetch(PDO::FETCH_ASSOC)) {
        $cor = '#28a745';
        if ($row['status'] === 'Cancelado') {
            $cor = '#6c757d'; 
        }

        $eventos[] = [
            'id'    => $row['id'],
            'title' => $row['nome'],
            'start' => $row['data_agendamento'] . 'T' . $row['hora_inicio'],
            'end'   => $row['data_agendamento'] . 'T' . $row['hora_fim'],
            'backgroundColor' => $cor,
            'borderColor' => $cor,
            'extendedProps' => [
                'id' => $row['id'],
                'nome' => $row['nome'],
                'status' => $row['status'],
                'motivo' => $row['motivo'],
                'observacao' => $row['observacao'],
                'telefone' => $row['telefone_contato'],
                'email' => $row['email'],
                'oab' => $row['oab'],
                'cpf' => $row['cpf_oab']
            ]
        ];
    }

    // 2. Busca os bloqueios, FILTRANDO PELA AGENDA SELECIONADA
    $query_bloqueios = "SELECT data_bloqueio, data_fim_bloqueio, hora_inicio_bloqueio, hora_fim_bloqueio, motivo_bloqueio 
                        FROM bloqueios
                        WHERE id_agenda = ?";
    $stmt_bloqueios = $pdo->prepare($query_bloqueios);
    $stmt_bloqueios->execute([$id_agenda_selecionada]);

    while ($row = $stmt_bloqueios->fetch(PDO::FETCH_ASSOC)) {
        $data_inicio = new DateTime($row['data_bloqueio']);
        $data_fim = $row['data_fim_bloqueio'] ? new DateTime($row['data_fim_bloqueio']) : clone $data_inicio;
        
        for ($data = $data_inicio; $data <= $data_fim; $data->modify('+1 day')) {
            if ($row['hora_inicio_bloqueio']) {
                $eventos[] = [ 'title' => $row['motivo_bloqueio'] ?: 'Bloqueado', 'start' => $data->format('Y-m-d') . 'T' . $row['hora_inicio_bloqueio'], 'end' => $data->format('Y-m-d') . 'T' . $row['hora_fim_bloqueio'], 'backgroundColor' => '#007bff', 'borderColor' => '#007bff' ];
            } else {
                 $eventos[] = [ 'title' => $row['motivo_bloqueio'] ?: 'Dia Bloqueado', 'start' => $data->format('Y-m-d'), 'allDay' => true, 'display' => 'background', 'backgroundColor' => '#ffc107' ];
            }
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Erro na consulta de agendamentos do admin: " . $e->getMessage());
    echo json_encode(['error' => 'Erro ao buscar dados do banco.']);
    exit;
}

echo json_encode($eventos);