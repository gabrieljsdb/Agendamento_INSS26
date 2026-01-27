<?php

namespace App\Controllers;

use App\Models\Agendamento;
use App\Models\Usuario;
use App\Validators\AgendamentoValidator;
use App\Services\EmailService;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

class AgendamentoController
{
    private Agendamento $agendamentoModel;
    private Usuario $usuarioModel;
    private EmailService $emailService;

    public function __construct($pdo)
    {
        $this->agendamentoModel = new Agendamento($pdo, config('agendamento'));
        $this->usuarioModel = new Usuario($pdo);
        $this->emailService = new EmailService(config('mail'), logger());
    }

    public function index()
    {
        AuthMiddleware::require('login.php');
        
        $idUsuario = AuthMiddleware::getUserId();
        $idAgenda = config('agendamento.agenda_id_padrao');
        
        $proximos = $this->agendamentoModel->buscarProximos($idUsuario, $idAgenda);
        $usuario = $this->usuarioModel->buscarPorId($idUsuario);

        require config('paths.templates') . '/agendamento/painel.php';
    }

    public function agendar()
    {
        AuthMiddleware::require('login.php');
        CsrfMiddleware::require('index.php');

        $idUsuario = AuthMiddleware::getUserId();
        $data = $_POST;
        $data['id_usuario'] = $idUsuario;
        $data['id_agenda'] = config('agendamento.agenda_id_padrao');

        // Validações de negócio
        if (!$this->agendamentoModel->podeFazerNovoAgendamento($idUsuario)) {
            redirect('index.php?status=bloqueio_cancelamento');
        }

        $ano = date('Y', strtotime($data['data_agendamento']));
        $mes = date('m', strtotime($data['data_agendamento']));
        if ($this->agendamentoModel->contarPorMes($idUsuario, $data['id_agenda'], $ano, $mes) >= config('agendamento.limite_mensal')) {
            redirect('index.php?status=limite_excedido');
        }

        $validator = new AgendamentoValidator(config('agendamento'));
        if (!$validator->validar($data)) {
            flash('error', $validator->getFirstError());
            redirect('index.php');
        }

        $id = $this->agendamentoModel->criar($data);

        if ($id) {
            // Atualiza telefone se fornecido
            if (!empty($data['telefone_contato'])) {
                $this->usuarioModel->atualizarTelefone($idUsuario, $data['telefone_contato']);
            }

            // Envia email de confirmação
            $this->enviarEmailConfirmacao($id);

            redirect('index.php?status=sucesso');
        } else {
            redirect('index.php?status=horario_ocupado');
        }
    }

    public function buscarEventos()
    {
        AuthMiddleware::require('login.php');
        header('Content-Type: application/json');
        
        $idAgenda = config('agendamento.agenda_id_padrao');
        $agendamentos = $this->agendamentoModel->buscarConfirmados($idAgenda);
        
        $eventos = [];
        foreach ($agendamentos as $ag) {
            $eventos[] = [
                'title' => 'Reservado',
                'start' => $ag['data_agendamento'] . 'T' . $ag['hora_inicio'],
                'end'   => $ag['data_agendamento'] . 'T' . $ag['hora_fim'],
                'backgroundColor' => '#dc3545',
                'borderColor' => '#dc3545'
            ];
        }
        
        echo json_encode($eventos);
        exit;
    }

    private function enviarEmailConfirmacao($idAgendamento)
    {
        $ag = $this->agendamentoModel->buscarPorId($idAgendamento);
        if (!$ag) return;

        $dataFmt = formatDate($ag['data_agendamento']);
        $horaFmt = formatTime($ag['hora_inicio']);
        $endereco = config('agendamento.endereco_atendimento');

        $assunto = "OAB/SC - Agendamento Confirmado!";
        $corpo = "<h1>Olá, " . sanitize($ag['nome']) . "!</h1>
                  <p>Seu agendamento com o servidor do <strong>INSS</strong> foi <strong>confirmado</strong>.</p>
                  <ul>
                    <li><strong>Data:</strong> {$dataFmt}</li>
                    <li><strong>Horário:</strong> {$horaFmt}</li>
                    <li><strong>Motivo:</strong> " . sanitize($ag['motivo']) . "</li>
                  </ul>
                  <p><strong>Local:</strong> {$endereco['nome']}<br>{$endereco['rua']}, {$endereco['bairro']} – {$endereco['cidade']}/{$endereco['estado']}</p>";

        $this->emailService->send($ag['email'], $ag['nome'], $assunto, $corpo);
    }
}
