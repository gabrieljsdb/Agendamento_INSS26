/**
 * Serviço de Email
 * 
 * Gerencia envio de notificações por email
 */

import { addEmailToQueue, getSystemSettings } from "../db";

export interface EmailTemplate {
  subject: string;
  body: string;
}

export class EmailService {
  /**
   * Template de confirmação de agendamento
   */
  buildAppointmentConfirmationEmail(data: {
    userName: string;
    appointmentDate: string;
    startTime: string;
    endTime: string;
    reason: string;
    address?: string;
    phone?: string;
  }): EmailTemplate {
    return {
      subject: "Agendamento Confirmado - Sistema de Agendamento INSS",
      body: `
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamento Confirmado</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #667eea; color: white; padding: 20px; border-radius: 5px 5px 0 0; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #667eea; }
        .footer { background: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        .label { font-weight: bold; color: #667eea; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>✓ Agendamento Confirmado</h2>
        </div>
        <div class="content">
            <p>Olá <strong>${data.userName}</strong>,</p>
            <p>Seu agendamento foi confirmado com sucesso! Aqui estão os detalhes:</p>
            
            <div class="details">
                <p><span class="label">Data:</span> ${data.appointmentDate}</p>
                <p><span class="label">Horário:</span> ${data.startTime} às ${data.endTime}</p>
                <p><span class="label">Motivo:</span> ${data.reason}</p>
                ${data.address ? `<p><span class="label">Local:</span> ${data.address}</p>` : ""}
                ${data.phone ? `<p><span class="label">Telefone:</span> ${data.phone}</p>` : ""}
            </div>
            
            <p><strong>Importante:</strong></p>
            <ul>
                <li>Chegue com 10 minutos de antecedência</li>
                <li>Leve seus documentos de identificação</li>
                <li>Para cancelar, entre em contato com antecedência</li>
            </ul>
            
            <p>Se tiver dúvidas, entre em contato conosco.</p>
        </div>
        <div class="footer">
            <p>Este é um email automático. Não responda diretamente.</p>
            <p>&copy; 2026 Sistema de Agendamento INSS - OAB/SC</p>
        </div>
    </div>
</body>
</html>
      `,
    };
  }

  /**
   * Template de cancelamento de agendamento
   */
  buildAppointmentCancellationEmail(data: {
    userName: string;
    appointmentDate: string;
    startTime: string;
    reason?: string;
  }): EmailTemplate {
    return {
      subject: "Agendamento Cancelado - Sistema de Agendamento INSS",
      body: `
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamento Cancelado</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #dc3545; color: white; padding: 20px; border-radius: 5px 5px 0 0; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #dc3545; }
        .footer { background: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        .label { font-weight: bold; color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>✗ Agendamento Cancelado</h2>
        </div>
        <div class="content">
            <p>Olá <strong>${data.userName}</strong>,</p>
            <p>Seu agendamento foi cancelado. Aqui estão os detalhes:</p>
            
            <div class="details">
                <p><span class="label">Data:</span> ${data.appointmentDate}</p>
                <p><span class="label">Horário:</span> ${data.startTime}</p>
                ${data.reason ? `<p><span class="label">Motivo:</span> ${data.reason}</p>` : ""}
            </div>
            
            <p>Você pode agendar um novo horário a qualquer momento através do sistema.</p>
            <p>Se tiver dúvidas, entre em contato conosco.</p>
        </div>
        <div class="footer">
            <p>Este é um email automático. Não responda diretamente.</p>
            <p>&copy; 2026 Sistema de Agendamento INSS - OAB/SC</p>
        </div>
    </div>
</body>
</html>
      `,
    };
  }

  /**
   * Template de relatório diário para administradores
   */
  buildDailyReportEmail(data: {
    reportDate: string;
    appointments: Array<{
      userName: string;
      userEmail: string;
      appointmentDate: string;
      startTime: string;
      endTime: string;
      reason: string;
      phone?: string;
    }>;
  }): EmailTemplate {
    const appointmentRows = data.appointments
      .map(
        (apt) => `
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">${apt.userName}</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">${apt.userEmail}</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">${apt.startTime} - ${apt.endTime}</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">${apt.reason}</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">${apt.phone || "-"}</td>
        </tr>
      `
      )
      .join("");

    return {
      subject: `Relatório de Agendamentos - ${data.reportDate}`,
      body: `
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Agendamentos</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .header { background: #667eea; color: white; padding: 20px; border-radius: 5px 5px 0 0; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #667eea; color: white; padding: 12px; text-align: left; }
        .footer { background: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Relatório de Agendamentos</h2>
            <p>Data: ${data.reportDate}</p>
        </div>
        <div class="content">
            <p>Total de agendamentos: <strong>${data.appointments.length}</strong></p>
            
            <table>
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Email</th>
                        <th>Horário</th>
                        <th>Motivo</th>
                        <th>Telefone</th>
                    </tr>
                </thead>
                <tbody>
                    ${appointmentRows}
                </tbody>
            </table>
        </div>
        <div class="footer">
            <p>Este é um email automático. Não responda diretamente.</p>
            <p>&copy; 2026 Sistema de Agendamento INSS - OAB/SC</p>
        </div>
    </div>
</body>
</html>
      `,
    };
  }

  /**
   * Adiciona email à fila de envio
   */
  async queueEmail(data: {
    toEmail: string;
    toName?: string;
    subject: string;
    body: string;
    emailType: string;
    appointmentId?: number;
    userId?: number;
  }): Promise<void> {
    try {
      await addEmailToQueue(data);
    } catch (error) {
      console.error("[EmailService] Erro ao adicionar email à fila:", error);
      throw error;
    }
  }

  /**
   * Envia email de confirmação de agendamento
   */
  async sendAppointmentConfirmation(data: {
    toEmail: string;
    userName: string;
    appointmentDate: string;
    startTime: string;
    endTime: string;
    reason: string;
    address?: string;
    phone?: string;
    appointmentId?: number;
    userId?: number;
  }): Promise<void> {
    const template = this.buildAppointmentConfirmationEmail({
      userName: data.userName,
      appointmentDate: data.appointmentDate,
      startTime: data.startTime,
      endTime: data.endTime,
      reason: data.reason,
      address: data.address,
      phone: data.phone,
    });

    await this.queueEmail({
      toEmail: data.toEmail,
      toName: data.userName,
      subject: template.subject,
      body: template.body,
      emailType: "appointment_confirmation",
      appointmentId: data.appointmentId,
      userId: data.userId,
    });
  }

  /**
   * Envia email de cancelamento de agendamento
   */
  async sendAppointmentCancellation(data: {
    toEmail: string;
    userName: string;
    appointmentDate: string;
    startTime: string;
    reason?: string;
    appointmentId?: number;
    userId?: number;
  }): Promise<void> {
    const template = this.buildAppointmentCancellationEmail({
      userName: data.userName,
      appointmentDate: data.appointmentDate,
      startTime: data.startTime,
      reason: data.reason,
    });

    await this.queueEmail({
      toEmail: data.toEmail,
      toName: data.userName,
      subject: template.subject,
      body: template.body,
      emailType: "appointment_cancellation",
      appointmentId: data.appointmentId,
      userId: data.userId,
    });
  }

  /**
   * Template de notificação personalizada enviada pelo administrador
   */
  buildCustomNotificationEmail(data: {
    userName: string;
    message: string;
    appointmentDate: string;
    startTime: string;
  }): EmailTemplate {
    return {
      subject: "Notificação sobre seu Agendamento - OAB/SC",
      body: `
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificação de Agendamento</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #667eea; color: white; padding: 20px; border-radius: 5px 5px 0 0; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .message-box { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #667eea; font-style: italic; }
        .details { font-size: 14px; color: #666; margin-top: 20px; border-top: 1px solid #eee; pt: 10px; }
        .footer { background: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Notificação de Agendamento</h2>
        </div>
        <div class="content">
            <p>Olá <strong>${data.userName}</strong>,</p>
            <p>A administração do sistema de agendamentos enviou uma mensagem importante para você:</p>
            
            <div class="message-box">
                ${data.message.replace(/\n/g, '<br>')}
            </div>
            
            <div class="details">
                <p>Referente ao agendamento de: <strong>${data.appointmentDate} às ${data.startTime}</strong></p>
            </div>
            
            <p>Se tiver dúvidas, entre em contato conosco.</p>
        </div>
        <div class="footer">
            <p>Este é um email automático enviado pela administração. Não responda diretamente.</p>
            <p>&copy; 2026 Sistema de Agendamento INSS - OAB/SC</p>
        </div>
    </div>
</body>
</html>
      `,
    };
  }

  /**
   * Envia notificação personalizada
   */
  async sendCustomNotification(data: {
    toEmail: string;
    userName: string;
    message: string;
    appointmentDate: string;
    startTime: string;
    appointmentId: number;
    userId: number;
  }): Promise<void> {
    const template = this.buildCustomNotificationEmail({
      userName: data.userName,
      message: data.message,
      appointmentDate: data.appointmentDate,
      startTime: data.startTime,
    });

    await this.queueEmail({
      toEmail: data.toEmail,
      toName: data.userName,
      subject: template.subject,
      body: template.body,
      emailType: "custom_notification",
      appointmentId: data.appointmentId,
      userId: data.userId,
    });
  }

  /**
   * Envia relatório diário para administradores
   */
  async sendDailyReport(data: {
    reportDate: string;
    appointments: Array<{
      userName: string;
      userEmail: string;
      appointmentDate: string;
      startTime: string;
      endTime: string;
      reason: string;
      phone?: string;
    }>;
  }): Promise<void> {
    const settings = await getSystemSettings();
    const adminEmails = settings?.adminEmails ? JSON.parse(settings.adminEmails) : [];

    if (!Array.isArray(adminEmails) || adminEmails.length === 0) {
      console.warn("[EmailService] Nenhum email de administrador configurado");
      return;
    }

    const template = this.buildDailyReportEmail(data);

    for (const adminEmail of adminEmails) {
      await this.queueEmail({
        toEmail: adminEmail,
        subject: template.subject,
        body: template.body,
        emailType: "daily_report",
      });
    }
  }
}

// Exporta instância singleton
export const emailService = new EmailService();
