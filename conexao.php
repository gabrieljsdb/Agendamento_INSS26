<?php
$host = 'localhost';
$dbname = 'sistema_agendamento';
$user = 'gabrieljsdb'; // ou seu usuário do banco
$pass = '@Aa016512'; // ou sua senha do banco

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    // Configura o PDO para lançar exceções em caso de erro
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Em um ambiente real, não exiba o erro diretamente ao usuário
    die("Erro ao conectar com o banco de dados: " . $e->getMessage());
}
?>