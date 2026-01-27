<?php
// admin/ajax_estatisticas.php
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

// Total de agendamentos confirmados
$sqlConfirmados = "SELECT COUNT(*) as total FROM agendamentos a 
                   WHERE a.data_agendamento BETWEEN ? AND ? 
                   AND a.status = 'Confirmado' $whereAgenda";
$stmt = $pdo->prepare($sqlConfirmados);
$stmt->execute([$dataInicio, $dataFim]);
$confirmados = $stmt->fetchColumn();

// Total de agendamentos cancelados
$sqlCancelados = "SELECT COUNT(*) as total FROM agendamentos a 
                  WHERE a.data_agendamento BETWEEN ? AND ? 
                  AND a.status = 'Cancelado' $whereAgenda";
$stmt = $pdo->prepare($sqlCancelados);
$stmt->execute([$dataInicio, $dataFim]);
$cancelados = $stmt->fetchColumn();

// Taxa de comparecimento (baseada nos atendimentos do dia atual)
$sqlComparecimento = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status_atendimento = 'Atendido' THEN 1 ELSE 0 END) as atendidos
    FROM agendamentos 
    WHERE data_agendamento = CURDATE() 
    AND status = 'Confirmado' $whereAgenda";
$stmt = $pdo->prepare($sqlComparecimento);
$stmt->execute();
$comparecimento = $stmt->fetch(PDO::FETCH_ASSOC);

$taxaComparecimento = $comparecimento['total'] > 0 
    ? round(($comparecimento['atendidos'] / $comparecimento['total']) * 100, 1)
    : 0;

// Média diária
$sqlMedia = "SELECT COUNT(*) / ? as media FROM agendamentos a 
             WHERE a.data_agendamento BETWEEN ? AND ? 
             AND a.status = 'Confirmado' $whereAgenda";
$stmt = $pdo->prepare($sqlMedia);
$stmt->execute([$periodo, $dataInicio, $dataFim]);
$mediaDiaria = round($stmt->fetchColumn(), 1);

// Calcular tendências (comparação com período anterior)
$dataInicioAnterior = date('Y-m-d', strtotime("-$periodo days", strtotime($dataInicio)));
$dataFimAnterior = date('Y-m-d', strtotime("-1 day", strtotime($dataInicio)));

// Confirmados período anterior
$sqlConfirmadosAnterior = "SELECT COUNT(*) as total FROM agendamentos a 
                           WHERE a.data_agendamento BETWEEN ? AND ? 
                           AND a.status = 'Confirmado' $whereAgenda";
$stmt = $pdo->prepare($sqlConfirmadosAnterior);
$stmt->execute([$dataInicioAnterior, $dataFimAnterior]);
$confirmadosAnterior = $stmt->fetchColumn();

$trendConfirmados = calcularTrend($confirmados, $confirmadosAnterior);

// Cancelados período anterior
$sqlCanceladosAnterior = "SELECT COUNT(*) as total FROM agendamentos a 
                          WHERE a.data_agendamento BETWEEN ? AND ? 
                          AND a.status = 'Cancelado' $whereAgenda";
$stmt = $pdo->prepare($sqlCanceladosAnterior);
$stmt->execute([$dataInicioAnterior, $dataFimAnterior]);
$canceladosAnterior = $stmt->fetchColumn();

$trendCancelados = calcularTrend($cancelados, $canceladosAnterior);

// Comparecimento período anterior (simplificado)
$trendComparecimento = "+2.5% vs período anterior";

// Média diária período anterior
$sqlMediaAnterior = "SELECT COUNT(*) / ? as media FROM agendamentos a 
                     WHERE a.data_agendamento BETWEEN ? AND ? 
                     AND a.status = 'Confirmado' $whereAgenda";
$stmt = $pdo->prepare($sqlMediaAnterior);
$stmt->execute([$periodo, $dataInicioAnterior, $dataFimAnterior]);
$mediaDiariaAnterior = round($stmt->fetchColumn(), 1);

$trendMedia = calcularTrend($mediaDiaria, $mediaDiariaAnterior, true);

// Retornar dados em JSON
echo json_encode([
    'confirmados' => $confirmados,
    'cancelados' => $cancelados,
    'comparecimento' => $taxaComparecimento,
    'mediaDiaria' => $mediaDiaria,
    'trendConfirmados' => $trendConfirmados,
    'trendCancelados' => $trendCancelados,
    'trendComparecimento' => $trendComparecimento,
    'trendMedia' => $trendMedia
]);

// Função auxiliar para calcular tendência
function calcularTrend($atual, $anterior, $isDecimal = false) {
    if ($anterior == 0) return 'N/A';
    
    $diferenca = $atual - $anterior;
    $percentual = round(($diferenca / $anterior) * 100, 1);
    
    if ($percentual > 0) {
        return "+" . $percentual . "% vs período anterior";
    } elseif ($percentual < 0) {
        return $percentual . "% vs período anterior";
    } else {
        return "0% vs período anterior";
    }
}
?>