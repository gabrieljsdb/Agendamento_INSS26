<?php
// rotina_diaria.php - VERSÃO ATUALIZADA COM LOGS NA HOME
// Este script busca os agendamentos do próximo dia útil e envia por e-mail.

// =============================================================================
// CONFIGURAÇÃO INICIAL - NOVOS CAMINHOS NA HOME
// =============================================================================

// Define o fuso horário para garantir que a data seja sempre correta
date_default_timezone_set('America/Sao_Paulo');

// NOVO: Diretório de logs na home
$log_dir = '/var/www/html/sistema_agendamento/logs/';
$log_file = $log_dir . 'rotina_diaria.log';

// Criar diretório de logs se não existir
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Função de log melhorada
function logRotina($mensagem) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $mensagem\n";
    
    // Log para arquivo na HOME (não sincroniza com Git)
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Também output para stdout (para cron)
    echo $log_entry;
}

// =============================================================================
// INÍCIO DA EXECUÇÃO
// =============================================================================

logRotina("=========================================");
logRotina("INICIANDO ROTINA DIÁRIA");
logRotina("=========================================");

try {
    // Inclui os arquivos necessários
    require 'conexao.php';
    require 'funcao_email.php';
    
    logRotina("✅ Arquivos incluídos com sucesso");

    // --- LÓGICA PARA DETERMINAR A DATA DO "DIA SEGUINTE" ---

    // Pega a data de hoje
    $hoje = new DateTime();
    $dia_da_semana = (int)$hoje->format('N'); // 1 (Segunda) a 7 (Domingo)

    logRotina("📅 Hoje: " . $hoje->format('d/m/Y') . " - Dia da semana: $dia_da_semana");

    // Se hoje for Sexta-feira (5), o próximo dia útil é Segunda-feira (+3 dias)
    if ($dia_da_semana === 5) {
        $data_alvo = (new DateTime())->modify('+3 days')->format('Y-m-d');
        $texto_dia = "próxima Segunda-feira";
        logRotina("🗓️ Sexta-feira detectada - próximo dia útil: Segunda-feira");
    } 
    // Se hoje for Sábado (6), o próximo dia útil também é Segunda-feira (+2 dias)
    elseif ($dia_da_semana === 6) {
        $data_alvo = (new DateTime())->modify('+2 days')->format('Y-m-d');
        $texto_dia = "próxima Segunda-feira";
        logRotina("🗓️ Sábado detectado - próximo dia útil: Segunda-feira");
    } 
    // Para os outros dias da semana, o próximo dia é amanhã (+1 dia)
    else {
        $data_alvo = (new DateTime())->modify('+1 day')->format('Y-m-d');
        $texto_dia = "amanhã";
        logRotina("🗓️ Dia útil normal - próximo dia útil: Amanhã");
    }

    // Formata a data alvo para exibição no e-mail
    $data_formatada_titulo = date('d/m/Y', strtotime($data_alvo));

    logRotina("🎯 Data alvo: $data_alvo ($texto_dia)");

    // --- BUSCA OS AGENDAMENTOS NO BANCO DE DADOS ---
    logRotina("🔍 Buscando agendamentos no banco de dados...");
    
    $sql = "SELECT a.hora_inicio, u.nome, u.cpf_oab, u.oab, a.motivo
            FROM agendamentos a
            JOIN usuarios u ON a.id_usuario = u.id
            WHERE a.data_agendamento = ? AND a.status = 'Confirmado'
            ORDER BY a.hora_inicio ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data_alvo]);
    $agendamentos_do_dia = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_agendamentos = count($agendamentos_do_dia);
    logRotina("📊 Total de agendamentos encontrados: " . $total_agendamentos);

    // --- MONTAGEM DO CORPO DO E-MAIL ---
    $assunto_email = "Agenda de Atendimentos para {$texto_dia}, {$data_formatada_titulo}";
    $corpo_html = "<h1>Lista de Atendimentos para {$texto_dia} ({$data_formatada_titulo})</h1>";

    if ($total_agendamentos > 0) {
        $corpo_html .= "<p>Total de <strong>{$total_agendamentos}</strong> agendamentos confirmados.</p>";
        $corpo_html .= "
            <table border='1' cellpadding='10' cellspacing='0' style='width: 100%; border-collapse: collapse;'>
                <thead style='background-color: #f2f2f2;'>
                    <tr>
                        <th>Horário</th>
                        <th>Nome</th>
                        <th>CPF/OAB</th>
                        <th>Nº OAB</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>";
        
        foreach ($agendamentos_do_dia as $ag) {
            $hora_formatada = date('H:i', strtotime($ag['hora_inicio']));
            $corpo_html .= "
                <tr>
                    <td>{$hora_formatada}</td>
                    <td>" . htmlspecialchars($ag['nome']) . "</td>
                    <td>" . htmlspecialchars($ag['cpf_oab']) . "</td>
                    <td>" . htmlspecialchars($ag['oab']) . "</td>
                    <td>" . htmlspecialchars($ag['motivo']) . "</td>
                </tr>";
            
            logRotina("   👤 {$hora_formatada} - " . $ag['nome'] . " (" . $ag['cpf_oab'] . ")");
        }

        $corpo_html .= "</tbody></table>";
    } else {
        $corpo_html .= "<p>Não há agendamentos confirmados para esta data.</p>";
        logRotina("ℹ️ Nenhum agendamento encontrado para a data");
    }

    // --- ENVIO DO E-MAIL PARA A LISTA DE ADMINISTRADORES ---
    if (defined('ADMIN_NOTIFICATION_LIST') && is_array(ADMIN_NOTIFICATION_LIST)) {
        if (!empty(ADMIN_NOTIFICATION_LIST)) {
            logRotina("📧 Enviando e-mail para os administradores...");
            
            $emails_enviados = 0;
            $emails_falhas = 0;
            
            foreach (ADMIN_NOTIFICATION_LIST as $admin_email) {
                if (enviar_email($admin_email, 'Administrador do Sistema', $assunto_email, $corpo_html)) {
                    logRotina("   ✅ E-mail enviado com sucesso para: " . $admin_email);
                    $emails_enviados++;
                } else {
                    logRotina("   ❌ FALHA ao enviar e-mail para: " . $admin_email);
                    $emails_falhas++;
                }
            }
            
            logRotina("📨 Resumo: {$emails_enviados} enviados, {$emails_falhas} falhas");
            
        } else {
            logRotina("⚠️ A lista de notificação de administradores está vazia. Nenhum e-mail foi enviado.");
        }
    } else {
        logRotina("⚠️ A constante ADMIN_NOTIFICATION_LIST não foi definida. Nenhum e-mail foi enviado.");
    }

    logRotina("=========================================");
    logRotina("ROTINA FINALIZADA COM SUCESSO");
    logRotina("=========================================");

} catch (Exception $e) {
    // Em caso de erro grave
    $mensagem_erro = "❌ ERRO FATAL no script de rotina diária: " . $e->getMessage();
    logRotina($mensagem_erro);
    
    // Tentar enviar e-mail de alerta em caso de erro crítico
    try {
        if (defined('ADMIN_NOTIFICATION_LIST') && is_array(ADMIN_NOTIFICATION_LIST)) {
            $assunto_erro = "ERRO - Rotina Diária do Sistema de Agendamento";
            $corpo_erro = "<h1>Erro na Rotina Diária</h1>
                          <p><strong>Data:</strong> " . date('d/m/Y H:i:s') . "</p>
                          <p><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                          <p>Por favor, verifique os logs do sistema.</p>";
            
            foreach (ADMIN_NOTIFICATION_LIST as $admin_email) {
                @enviar_email($admin_email, 'Administrador do Sistema', $assunto_erro, $corpo_erro);
            }
        }
    } catch (Exception $email_error) {
        logRotina("❌ Não foi possível enviar e-mail de erro: " . $email_error->getMessage());
    }
    
    exit(1); // Código de erro para o cron
}
?>