<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'config_email.php';

function enviar_email($para_email, $para_nome, $assunto, $corpo_html) {
    $mail = new PHPMailer(true);

    try {
        // Configurações do servidor
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Configurações específicas para Gmail
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->Timeout = 30;
        
        // Para debug - DESATIVE EM PRODUÇÃO
        $mail->SMTPDebug = 2; // Mude para 0 em produção
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer DEBUG: $str");
        };

        // Remetente
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);

        // Destinatário
        $mail->addAddress($para_email, $para_nome);

        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $corpo_html;
        $mail->AltBody = strip_tags($corpo_html);

        error_log("Tentando enviar e-mail para: $para_email");
        
        if ($mail->send()) {
            error_log("E-mail enviado com sucesso para: $para_email");
            return true;
        } else {
            error_log("Falha no envio: " . $mail->ErrorInfo);
            return false;
        }

    } catch (Exception $e) {
        error_log("EXCEÇÃO no PHPMailer: " . $e->getMessage());
        return false;
    }
}
?>