<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Psr\Log\LoggerInterface;

/**
 * Classe EmailService
 * 
 * Serviço responsável pelo envio de emails com suporte a templates,
 * filas e logs estruturados.
 */
class EmailService
{
    private array $config;
    private LoggerInterface $logger;
    private PHPMailer $mailer;

    /**
     * Construtor
     *
     * @param array $config Configurações de email
     * @param LoggerInterface $logger Logger para registro de eventos
     */
    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->mailer = $this->createMailer();
    }

    /**
     * Cria e configura uma instância do PHPMailer
     *
     * @return PHPMailer
     */
    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);

        try {
            // Configurações do servidor SMTP
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->SMTPSecure = $this->config['encryption'];
            $mail->Port = $this->config['port'];
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = $this->config['timeout'];

            // Configurações de segurança SSL
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ];

            // Debug apenas em ambiente de desenvolvimento
            $mail->SMTPDebug = $this->config['debug'];
            $mail->Debugoutput = function ($str, $level) {
                $this->logger->debug("PHPMailer: {$str}");
            };

            // Configurações do remetente
            $mail->setFrom(
                $this->config['from']['address'],
                $this->config['from']['name']
            );
            $mail->addReplyTo(
                $this->config['from']['address'],
                $this->config['from']['name']
            );

        } catch (Exception $e) {
            $this->logger->error("Erro ao configurar PHPMailer: " . $e->getMessage());
            throw $e;
        }

        return $mail;
    }

    /**
     * Envia um email
     *
     * @param string $to Email do destinatário
     * @param string $toName Nome do destinatário
     * @param string $subject Assunto do email
     * @param string $body Corpo do email (HTML)
     * @param array $attachments Anexos (opcional)
     * @return bool
     */
    public function send(
        string $to,
        string $toName,
        string $subject,
        string $body,
        array $attachments = []
    ): bool {
        try {
            // Limpa destinatários anteriores
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            // Adiciona destinatário
            $this->mailer->addAddress($to, $toName);

            // Configura conteúdo
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);

            // Adiciona anexos se houver
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $this->mailer->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? ''
                    );
                }
            }

            // Envia o email
            $result = $this->mailer->send();

            if ($result) {
                $this->logger->info("Email enviado com sucesso", [
                    'to' => $to,
                    'subject' => $subject,
                ]);
                return true;
            }

            return false;

        } catch (Exception $e) {
            $this->logger->error("Falha ao enviar email", [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Envia email para múltiplos destinatários
     *
     * @param array $recipients Lista de destinatários ['email' => 'nome']
     * @param string $subject Assunto
     * @param string $body Corpo do email
     * @return array Resultado do envio ['success' => int, 'failed' => int]
     */
    public function sendBulk(array $recipients, string $subject, string $body): array
    {
        $results = ['success' => 0, 'failed' => 0];

        foreach ($recipients as $email => $name) {
            if ($this->send($email, $name, $subject, $body)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        $this->logger->info("Envio em massa concluído", $results);

        return $results;
    }

    /**
     * Envia email usando template
     *
     * @param string $to Email do destinatário
     * @param string $toName Nome do destinatário
     * @param string $subject Assunto
     * @param string $template Nome do template
     * @param array $data Dados para o template
     * @return bool
     */
    public function sendWithTemplate(
        string $to,
        string $toName,
        string $subject,
        string $template,
        array $data = []
    ): bool {
        $templatePath = $this->config['template_path'] ?? __DIR__ . '/../../templates/emails/';
        $templateFile = $templatePath . $template . '.html';

        if (!file_exists($templateFile)) {
            $this->logger->error("Template de email não encontrado: {$template}");
            return false;
        }

        $body = file_get_contents($templateFile);

        // Substitui variáveis no template
        foreach ($data as $key => $value) {
            $body = str_replace("{{" . $key . "}}", $value, $body);
        }

        return $this->send($to, $toName, $subject, $body);
    }

    /**
     * Envia notificação para administradores
     *
     * @param string $subject Assunto
     * @param string $body Corpo do email
     * @return array Resultado do envio
     */
    public function notifyAdmins(string $subject, string $body): array
    {
        $adminEmails = $this->config['admin_emails'];

        if (empty($adminEmails)) {
            $this->logger->warning("Nenhum email de administrador configurado");
            return ['success' => 0, 'failed' => 0];
        }

        $recipients = [];
        foreach ($adminEmails as $email) {
            $recipients[$email] = 'Administrador';
        }

        return $this->sendBulk($recipients, $subject, $body);
    }
}
