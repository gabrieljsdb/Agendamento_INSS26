<?php

namespace App\Models;

use PDO;
use DateTime;
use DateInterval;

/**
 * Classe Agendamento
 * 
 * Modelo responsável pelas operações relacionadas a agendamentos
 */
class Agendamento
{
    private PDO $pdo;
    private array $config;

    /**
     * Construtor
     *
     * @param PDO $pdo Instância PDO
     * @param array $config Configurações de agendamento
     */
    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    /**
     * Cria um novo agendamento
     *
     * @param array $data Dados do agendamento
     * @return int|false ID do agendamento criado ou false em caso de erro
     */
    public function criar(array $data)
    {
        try {
            $this->pdo->beginTransaction();

            // Verifica conflito de horário com lock
            if ($this->verificarConflito($data['id_agenda'], $data['data_agendamento'], $data['hora_inicio'])) {
                $this->pdo->rollBack();
                return false;
            }

            // Calcula hora de término
            $horaFim = $this->calcularHoraFim($data['hora_inicio']);

            $sql = "INSERT INTO agendamentos (
                        id_agenda, id_usuario, motivo, observacao, 
                        data_agendamento, hora_inicio, hora_fim, 
                        status, telefone_contato, data_criacao
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Confirmado', ?, NOW())";

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $data['id_agenda'],
                $data['id_usuario'],
                $data['motivo'],
                $data['observacao'] ?? '',
                $data['data_agendamento'],
                $data['hora_inicio'],
                $horaFim,
                $data['telefone_contato'] ?? '',
            ]);

            if (!$result) {
                $this->pdo->rollBack();
                return false;
            }

            $id = $this->pdo->lastInsertId();
            $this->pdo->commit();

            return (int)$id;

        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Erro ao criar agendamento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica se existe conflito de horário
     *
     * @param int $idAgenda ID da agenda
     * @param string $data Data do agendamento
     * @param string $hora Hora do agendamento
     * @return bool
     */
    private function verificarConflito(int $idAgenda, string $data, string $hora): bool
    {
        $sql = "SELECT id FROM agendamentos 
                WHERE id_agenda = ? 
                AND data_agendamento = ? 
                AND hora_inicio = ? 
                AND status = 'Confirmado' 
                FOR UPDATE";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idAgenda, $data, $hora]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Calcula a hora de término baseado na duração configurada
     *
     * @param string $horaInicio Hora de início
     * @return string Hora de término
     */
    private function calcularHoraFim(string $horaInicio): string
    {
        $duracao = $this->config['duracao_minutos'];
        return date('H:i:s', strtotime($horaInicio . " +{$duracao} minutes"));
    }

    /**
     * Busca agendamentos por usuário
     *
     * @param int $idUsuario ID do usuário
     * @param string $status Status do agendamento (opcional)
     * @return array
     */
    public function buscarPorUsuario(int $idUsuario, ?string $status = null): array
    {
        $sql = "SELECT a.*, ag.nome as agenda_nome 
                FROM agendamentos a
                LEFT JOIN agendas ag ON a.id_agenda = ag.id
                WHERE a.id_usuario = ?";

        $params = [$idUsuario];

        if ($status !== null) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY a.data_agendamento DESC, a.hora_inicio DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Busca próximos agendamentos do usuário
     *
     * @param int $idUsuario ID do usuário
     * @param int $idAgenda ID da agenda
     * @param int $limite Limite de resultados
     * @return array
     */
    public function buscarProximos(int $idUsuario, int $idAgenda, int $limite = 5): array
    {
        $sql = "SELECT data_agendamento, hora_inicio, motivo 
                FROM agendamentos 
                WHERE id_usuario = ? 
                AND id_agenda = ? 
                AND status = 'Confirmado' 
                AND CONCAT(data_agendamento, ' ', hora_inicio) >= NOW() 
                ORDER BY data_agendamento ASC, hora_inicio ASC 
                LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idUsuario, $idAgenda, $limite]);

        return $stmt->fetchAll();
    }

    /**
     * Conta agendamentos confirmados do usuário no mês
     *
     * @param int $idUsuario ID do usuário
     * @param int $idAgenda ID da agenda
     * @param int $ano Ano
     * @param int $mes Mês
     * @return int
     */
    public function contarPorMes(int $idUsuario, int $idAgenda, int $ano, int $mes): int
    {
        $sql = "SELECT COUNT(*) 
                FROM agendamentos 
                WHERE id_agenda = ? 
                AND id_usuario = ? 
                AND status = 'Confirmado' 
                AND YEAR(data_agendamento) = ? 
                AND MONTH(data_agendamento) = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idAgenda, $idUsuario, $ano, $mes]);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Verifica se usuário pode fazer novo agendamento após cancelamento
     *
     * @param int $idUsuario ID do usuário
     * @return bool
     */
    public function podeFazerNovoAgendamento(int $idUsuario): bool
    {
        $sql = "SELECT data_cancelamento 
                FROM agendamentos 
                WHERE id_usuario = ? 
                AND status = 'Cancelado' 
                ORDER BY data_cancelamento DESC 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idUsuario]);
        $resultado = $stmt->fetch();

        if (!$resultado || !$resultado['data_cancelamento']) {
            return true;
        }

        $dataCancelamento = new DateTime($resultado['data_cancelamento']);
        $dataAtual = new DateTime();
        $intervalo = $dataCancelamento->diff($dataAtual);
        
        $horasPassadas = ($intervalo->days * 24) + $intervalo->h + ($intervalo->i / 60);
        $horasBloqueio = $this->config['bloqueio_cancelamento_horas'];

        return $horasPassadas >= $horasBloqueio;
    }

    /**
     * Cancela um agendamento
     *
     * @param int $id ID do agendamento
     * @param int $idUsuario ID do usuário (para validação)
     * @return bool
     */
    public function cancelar(int $id, int $idUsuario): bool
    {
        $sql = "UPDATE agendamentos 
                SET status = 'Cancelado', 
                    data_cancelamento = NOW() 
                WHERE id = ? 
                AND id_usuario = ? 
                AND status = 'Confirmado'";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id, $idUsuario]);
    }

    /**
     * Busca agendamentos para uma data específica
     *
     * @param string $data Data no formato Y-m-d
     * @param int $idAgenda ID da agenda
     * @return array
     */
    public function buscarPorData(string $data, int $idAgenda): array
    {
        $sql = "SELECT a.*, u.nome, u.cpf_oab, u.oab, u.telefone
                FROM agendamentos a
                JOIN usuarios u ON a.id_usuario = u.id
                WHERE a.data_agendamento = ? 
                AND a.id_agenda = ?
                AND a.status = 'Confirmado'
                ORDER BY a.hora_inicio ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$data, $idAgenda]);

        return $stmt->fetchAll();
    }

    /**
     * Busca todos os agendamentos confirmados (para calendário)
     *
     * @param int $idAgenda ID da agenda
     * @return array
     */
    public function buscarConfirmados(int $idAgenda): array
    {
        $sql = "SELECT data_agendamento, hora_inicio, hora_fim 
                FROM agendamentos 
                WHERE status = 'Confirmado' 
                AND id_agenda = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idAgenda]);

        return $stmt->fetchAll();
    }

    /**
     * Busca um agendamento por ID
     *
     * @param int $id ID do agendamento
     * @return array|false
     */
    public function buscarPorId(int $id)
    {
        $sql = "SELECT a.*, u.nome, u.email 
                FROM agendamentos a
                JOIN usuarios u ON a.id_usuario = u.id
                WHERE a.id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch();
    }
}
