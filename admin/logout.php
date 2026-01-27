<?php
session_start();

// Limpa todas as variáveis de sessão do administrador
// Usar session_unset() ou limpar o array $_SESSION é uma boa prática
session_unset();

// Destrói a sessão completamente
session_destroy();

// Redireciona para a página de login DO ADMIN
header('Location: login.php');
exit;
?>