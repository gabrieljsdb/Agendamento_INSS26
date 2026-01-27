<?php

namespace App\Models;

use PDO;

/**
 * Classe Usuario
 * 
 * Modelo responsável pelas operações relacionadas a usuários
 */
class Usuario
{
    private PDO $pdo;

    /**
     * Construtor
     *
     * @param PDO $pdo Instância PDO
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca um usuário por CPF/OAB
     *
     * @param string $cpfOab CPF ou OAB do usuário
     * @return array|false
     */
    public function buscarPorCpfOab(string $cpfOab)
    {
        $sql = "SELECT * FROM usuarios WHERE cpf_oab = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cpfOab]);

        return $stmt->fetch();
    }

    /**
     * Busca um usuário por ID
     *
     * @param int $id ID do usuário
     * @return array|false
     */
    public function buscarPorId(int $id)
    {
        $sql = "SELECT * FROM usuarios WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch();
    }

    /**
     * Cria um novo usuário
     *
     * @param array $data Dados do usuário
     * @return int|false ID do usuário criado ou false em caso de erro
     */
    public function criar(array $data)
    {
        try {
            $sql = "INSERT INTO usuarios (
                        cpf_oab, nome, email, oab, telefone, data_cadastro
                    ) VALUES (?, ?, ?, ?, ?, NOW())";

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $data['cpf_oab'],
                $data['nome'],
                $data['email'],
                $data['oab'] ?? '',
                $data['telefone'] ?? '',
            ]);

            if (!$result) {
                return false;
            }

            return (int)$this->pdo->lastInsertId();

        } catch (\Exception $e) {
            error_log("Erro ao criar usuário: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza os dados de um usuário
     *
     * @param string $cpfOab CPF/OAB do usuário
     * @param array $data Dados a serem atualizados
     * @return bool
     */
    public function atualizar(string $cpfOab, array $data): bool
    {
        try {
            $sql = "UPDATE usuarios 
                    SET nome = ?, 
                        email = ?, 
                        oab = ?, 
                        telefone = ?,
                        data_atualizacao = NOW()
                    WHERE cpf_oab = ?";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                $data['nome'],
                $data['email'],
                $data['oab'] ?? '',
                $data['telefone'] ?? '',
                $cpfOab,
            ]);

        } catch (\Exception $e) {
            error_log("Erro ao atualizar usuário: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza o telefone do usuário
     *
     * @param int $id ID do usuário
     * @param string $telefone Telefone
     * @return bool
     */
    public function atualizarTelefone(int $id, string $telefone): bool
    {
        $sql = "UPDATE usuarios SET telefone = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$telefone, $id]);
    }

    /**
     * Cria ou atualiza um usuário (upsert)
     *
     * @param array $data Dados do usuário
     * @return int ID do usuário
     */
    public function criarOuAtualizar(array $data): int
    {
        $usuario = $this->buscarPorCpfOab($data['cpf_oab']);

        if ($usuario) {
            // Atualiza usuário existente
            $this->atualizar($data['cpf_oab'], $data);
            return (int)$usuario['id'];
        } else {
            // Cria novo usuário
            return $this->criar($data);
        }
    }

    /**
     * Lista todos os usuários
     *
     * @param int $limite Limite de resultados
     * @param int $offset Offset para paginação
     * @return array
     */
    public function listar(int $limite = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM usuarios 
                ORDER BY data_cadastro DESC 
                LIMIT ? OFFSET ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limite, $offset]);

        return $stmt->fetchAll();
    }

    /**
     * Conta o total de usuários
     *
     * @return int
     */
    public function contar(): int
    {
        $sql = "SELECT COUNT(*) FROM usuarios";
        $stmt = $this->pdo->query($sql);

        return (int)$stmt->fetchColumn();
    }

    /**
     * Busca usuários por nome
     *
     * @param string $nome Nome ou parte do nome
     * @return array
     */
    public function buscarPorNome(string $nome): array
    {
        $sql = "SELECT * FROM usuarios 
                WHERE nome LIKE ? 
                ORDER BY nome ASC 
                LIMIT 50";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['%' . $nome . '%']);

        return $stmt->fetchAll();
    }

    /**
     * Verifica se um usuário existe
     *
     * @param string $cpfOab CPF/OAB do usuário
     * @return bool
     */
    public function existe(string $cpfOab): bool
    {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE cpf_oab = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$cpfOab]);

        return (int)$stmt->fetchColumn() > 0;
    }
}
