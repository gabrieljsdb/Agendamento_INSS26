<?php

namespace App\Validators;

use DateTime;
use DateInterval;

/**
 * Classe AgendamentoValidator
 * 
 * Responsável por validar dados de agendamento
 */
class AgendamentoValidator
{
    private array $config;
    private array $errors = [];

    /**
     * Construtor
     *
     * @param array $config Configurações de agendamento
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Valida os dados de um agendamento
     *
     * @param array $data Dados a serem validados
     * @return bool
     */
    public function validar(array $data): bool
    {
        $this->errors = [];

        // Validação de campos obrigatórios
        $this->validarCamposObrigatorios($data);

        // Validação de data
        if (isset($data['data_agendamento'])) {
            $this->validarData($data['data_agendamento']);
        }

        // Validação de hora
        if (isset($data['hora_inicio'])) {
            $this->validarHora($data['hora_inicio']);
        }

        // Validação de motivo
        if (isset($data['motivo'])) {
            $this->validarMotivo($data['motivo']);
        }

        // Validação de telefone
        if (isset($data['telefone_contato']) && !empty($data['telefone_contato'])) {
            $this->validarTelefone($data['telefone_contato']);
        }

        return empty($this->errors);
    }

    /**
     * Valida campos obrigatórios
     *
     * @param array $data Dados do agendamento
     */
    private function validarCamposObrigatorios(array $data): void
    {
        $camposObrigatorios = [
            'id_usuario' => 'ID do usuário',
            'id_agenda' => 'ID da agenda',
            'data_agendamento' => 'Data do agendamento',
            'hora_inicio' => 'Horário',
            'motivo' => 'Motivo',
        ];

        foreach ($camposObrigatorios as $campo => $nome) {
            if (!isset($data[$campo]) || empty($data[$campo])) {
                $this->errors[$campo] = "{$nome} é obrigatório.";
            }
        }
    }

    /**
     * Valida a data do agendamento
     *
     * @param string $data Data no formato Y-m-d
     */
    private function validarData(string $data): void
    {
        // Verifica formato
        $dataObj = DateTime::createFromFormat('Y-m-d', $data);
        if (!$dataObj || $dataObj->format('Y-m-d') !== $data) {
            $this->errors['data_agendamento'] = 'Data inválida.';
            return;
        }

        $hoje = new DateTime();
        $hoje->setTime(0, 0, 0);

        // Não permite agendamento para hoje ou datas passadas
        if ($dataObj <= $hoje) {
            $this->errors['data_agendamento'] = 'Não é permitido agendar para o dia de hoje ou datas passadas.';
            return;
        }

        // Verifica se é dia útil (não é fim de semana)
        $diaSemana = (int)$dataObj->format('N'); // 1 (segunda) a 7 (domingo)
        if ($diaSemana >= 6) {
            $this->errors['data_agendamento'] = 'Agendamentos só podem ser feitos em dias úteis (segunda a sexta).';
            return;
        }

        // Verifica limite de antecedência
        $dataLimite = clone $hoje;
        $dataLimite->add(new DateInterval('P' . $this->config['dias_antecedencia_maxima'] . 'D'));

        if ($dataObj > $dataLimite) {
            $dias = $this->config['dias_antecedencia_maxima'];
            $this->errors['data_agendamento'] = "Não é possível agendar com mais de {$dias} dias de antecedência.";
            return;
        }

        // Regra especial: não pode agendar para amanhã após 19h
        $amanha = clone $hoje;
        $amanha->add(new DateInterval('P1D'));

        if ($dataObj == $amanha) {
            $horaAtual = (new DateTime())->format('H:i:s');
            $horaLimite = $this->config['bloqueio_horario_limite'];

            if ($horaAtual >= $horaLimite) {
                $this->errors['data_agendamento'] = "Para agendar para amanhã, o agendamento deve ser feito até às {$horaLimite}.";
            }
        }
    }

    /**
     * Valida o horário do agendamento
     *
     * @param string $hora Hora no formato H:i:s
     */
    private function validarHora(string $hora): void
    {
        // Verifica formato
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $hora)) {
            $this->errors['hora_inicio'] = 'Horário inválido.';
            return;
        }

        // Normaliza para H:i:s
        if (strlen($hora) === 5) {
            $hora .= ':00';
        }

        $horarioInicio = $this->config['horario_inicio'];
        $horarioFim = $this->config['horario_fim'];

        // Verifica se está dentro do horário de atendimento
        if ($hora < $horarioInicio || $hora >= $horarioFim) {
            $this->errors['hora_inicio'] = "O horário deve estar entre {$horarioInicio} e {$horarioFim}.";
        }
    }

    /**
     * Valida o motivo do agendamento
     *
     * @param string $motivo Motivo do agendamento
     */
    private function validarMotivo(string $motivo): void
    {
        $motivosPermitidos = [
            'Atendimento',
            'Problemas com Senha',
            'Outros',
        ];

        if (!in_array($motivo, $motivosPermitidos)) {
            $this->errors['motivo'] = 'Motivo inválido.';
        }
    }

    /**
     * Valida o telefone
     *
     * @param string $telefone Telefone para contato
     */
    private function validarTelefone(string $telefone): void
    {
        // Remove caracteres não numéricos
        $telefoneNumeros = preg_replace('/[^0-9]/', '', $telefone);

        // Verifica se tem entre 10 e 11 dígitos (com DDD)
        if (strlen($telefoneNumeros) < 10 || strlen($telefoneNumeros) > 11) {
            $this->errors['telefone_contato'] = 'Telefone inválido. Deve conter DDD e número.';
        }
    }

    /**
     * Retorna os erros de validação
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Retorna a primeira mensagem de erro
     *
     * @return string|null
     */
    public function getFirstError(): ?string
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    /**
     * Verifica se há erros
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}
