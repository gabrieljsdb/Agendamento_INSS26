<?php
// admin/testeenvio.php
require '../funcao_email.php';

echo "<h1>Teste de Envio de E-mail</h1>";

// Teste básico da função
$teste = enviar_email(
    'dtioab@oab-sc.org.br', // Use um e-mail real para teste
    'Nome Teste', 
    'Teste de E-mail do Sistema', 
    '<h1>Teste do Sistema de Agendamento</h1><p>Este é um e-mail de teste do sistema.</p>'
);

if ($teste) {
    echo "<div style='color: green; font-weight: bold;'>✓ E-MAIL ENVIADO COM SUCESSO!</div>";
} else {
    echo "<div style='color: red; font-weight: bold;'>✗ FALHA NO ENVIO DO E-MAIL</div>";
    
    // Verificar se a função existe
    if (!function_exists('enviar_email')) {
        echo "<p>ERRO: Função enviar_email() não encontrada.</p>";
    }
    
    // Verificar arquivos incluídos
    echo "<h3>Arquivos Incluídos:</h3>";
    echo "<pre>";
    print_r(get_included_files());
    echo "</pre>";
}

// Testar configurações
echo "<h3>Configurações Carregadas:</h3>";
echo "<pre>";
if (defined('MAIL_HOST')) {
    echo "MAIL_HOST: " . MAIL_HOST . "\n";
    echo "MAIL_USERNAME: " . MAIL_USERNAME . "\n";
    echo "MAIL_PORT: " . MAIL_PORT . "\n";
    echo "MAIL_ENCRYPTION: " . MAIL_ENCRYPTION . "\n";
} else {
    echo "Configurações não carregadas\n";
}
echo "</pre>";
?>