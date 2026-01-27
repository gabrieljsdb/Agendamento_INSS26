<?php

namespace App\Services;

use PDO;
use PDOException;

/**
 * Classe Database
 * 
 * Gerencia a conexão com o banco de dados usando o padrão Singleton
 * e configurações centralizadas.
 */
class Database
{
    private static ?PDO $instance = null;
    private array $config;

    /**
     * Construtor privado para implementar Singleton
     */
    private function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Obtém a instância única da conexão PDO
     *
     * @param array $config Configurações do banco de dados
     * @return PDO
     * @throws PDOException
     */
    public static function getInstance(array $config): PDO
    {
        if (self::$instance === null) {
            $db = new self($config);
            self::$instance = $db->connect();
        }

        return self::$instance;
    }

    /**
     * Cria a conexão com o banco de dados
     *
     * @return PDO
     * @throws PDOException
     */
    private function connect(): PDO
    {
        try {
            $dsn = sprintf(
                "%s:host=%s;port=%d;dbname=%s;charset=%s",
                $this->config['driver'],
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );

            $pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );

            return $pdo;

        } catch (PDOException $e) {
            // Log do erro (não expor detalhes ao usuário)
            error_log("Erro de conexão com banco de dados: " . $e->getMessage());
            
            throw new PDOException(
                "Não foi possível conectar ao banco de dados. Por favor, tente novamente mais tarde.",
                (int)$e->getCode()
            );
        }
    }

    /**
     * Previne clonagem da instância
     */
    private function __clone() {}

    /**
     * Previne deserialização da instância
     */
    public function __wakeup()
    {
        throw new \Exception("Não é possível deserializar um singleton.");
    }
}
