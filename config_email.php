<?php
// CONFIGURAÇÕES GERAIS DE E-MAIL - VERIFICAR
define('MAIL_HOST', 'smtp.gmail.com');       
define('MAIL_USERNAME', 'no-reply@oab-sc.org.br'); 
define('MAIL_PASSWORD', 'whkx qwhg rubh zqll');     
define('MAIL_PORT', 587);                         
define('MAIL_ENCRYPTION', 'tls');                 

// INFORMAÇÕES DO REMETENTE
define('MAIL_FROM_ADDRESS', 'no-reply@oab-sc.org.br'); 
define('MAIL_FROM_NAME', 'OAB/SC - Sistema de Agendamento');    

// E-MAIL DO ADMINISTRADOR
define('ADMIN_NOTIFICATION_LIST', [
    'grasibrasil.gb@gmail.com',
    'dtioab@oab-sc.org.br'

]);

// Adicionar configurações de debug
define('MAIL_DEBUG', 2); // 0 = off, 2 = cliente
?>