-- Migration: Criar Tabelas Iniciais
-- Data: 2026-01-27
-- Descrição: Cria as tabelas principais do sistema de agendamento

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cpf_oab` VARCHAR(20) NOT NULL,
    `nome` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `oab` VARCHAR(20) DEFAULT NULL,
    `telefone` VARCHAR(20) DEFAULT NULL,
    `data_cadastro` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_cpf_oab` (`cpf_oab`),
    KEY `idx_email` (`email`),
    KEY `idx_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de agendas
CREATE TABLE IF NOT EXISTS `agendas` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome` VARCHAR(255) NOT NULL,
    `descricao` TEXT DEFAULT NULL,
    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `data_criacao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir agenda padrão
INSERT INTO `agendas` (`id`, `nome`, `descricao`, `ativo`) 
VALUES (1, 'Agenda INSS', 'Agenda de atendimento do INSS', 1)
ON DUPLICATE KEY UPDATE `nome` = VALUES(`nome`);

-- Tabela de agendamentos
CREATE TABLE IF NOT EXISTS `agendamentos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_agenda` INT UNSIGNED NOT NULL,
    `id_usuario` INT UNSIGNED NOT NULL,
    `motivo` VARCHAR(100) NOT NULL,
    `observacao` TEXT DEFAULT NULL,
    `data_agendamento` DATE NOT NULL,
    `hora_inicio` TIME NOT NULL,
    `hora_fim` TIME NOT NULL,
    `status` ENUM('Confirmado', 'Cancelado', 'Concluido') NOT NULL DEFAULT 'Confirmado',
    `telefone_contato` VARCHAR(20) DEFAULT NULL,
    `data_criacao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_cancelamento` TIMESTAMP NULL DEFAULT NULL,
    `data_atualizacao` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_agenda_data_hora` (`id_agenda`, `data_agendamento`, `hora_inicio`),
    KEY `idx_usuario_status` (`id_usuario`, `status`),
    KEY `idx_data_agendamento` (`data_agendamento`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_agendamentos_agenda` FOREIGN KEY (`id_agenda`) REFERENCES `agendas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_agendamentos_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de bloqueios
CREATE TABLE IF NOT EXISTS `bloqueios` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_agenda` INT UNSIGNED NOT NULL,
    `data_bloqueio` DATE NOT NULL,
    `data_fim_bloqueio` DATE DEFAULT NULL,
    `hora_inicio_bloqueio` TIME DEFAULT NULL,
    `hora_fim_bloqueio` TIME DEFAULT NULL,
    `motivo_bloqueio` VARCHAR(255) DEFAULT NULL,
    `data_criacao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_agenda_data` (`id_agenda`, `data_bloqueio`),
    CONSTRAINT `fk_bloqueios_agenda` FOREIGN KEY (`id_agenda`) REFERENCES `agendas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de administradores
CREATE TABLE IF NOT EXISTS `administradores` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario` VARCHAR(100) NOT NULL,
    `senha` VARCHAR(255) NOT NULL,
    `nome` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `data_criacao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ultimo_acesso` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_usuario` (`usuario`),
    KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de logs de sistema
CREATE TABLE IF NOT EXISTS `logs_sistema` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nivel` VARCHAR(20) NOT NULL,
    `mensagem` TEXT NOT NULL,
    `contexto` JSON DEFAULT NULL,
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `data_criacao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_nivel` (`nivel`),
    KEY `idx_usuario` (`usuario_id`),
    KEY `idx_data` (`data_criacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de fila de emails
CREATE TABLE IF NOT EXISTS `email_queue` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `destinatario_email` VARCHAR(255) NOT NULL,
    `destinatario_nome` VARCHAR(255) NOT NULL,
    `assunto` VARCHAR(255) NOT NULL,
    `corpo` TEXT NOT NULL,
    `anexos` JSON DEFAULT NULL,
    `status` ENUM('pendente', 'enviando', 'enviado', 'falhou') NOT NULL DEFAULT 'pendente',
    `tentativas` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `erro` TEXT DEFAULT NULL,
    `data_criacao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_envio` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_data_criacao` (`data_criacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
