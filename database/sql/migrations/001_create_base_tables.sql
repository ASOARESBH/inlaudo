-- ============================================================
-- Migrações - ERP INLAUDO v2.3.0
-- ============================================================
-- Data: 06/01/2026
-- Descrição: Criação de tabelas base do sistema

-- ============================================================
-- USUÁRIOS
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin', 'gerente', 'operador', 'cliente') DEFAULT 'operador',
    ativo BOOLEAN DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CLIENTES
-- ============================================================
CREATE TABLE IF NOT EXISTS clientes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    cnpj_cpf VARCHAR(20) UNIQUE,
    email VARCHAR(255),
    telefone VARCHAR(20),
    tipo_cliente ENUM('pf', 'pj') DEFAULT 'pj',
    endereco TEXT,
    cidade VARCHAR(100),
    estado VARCHAR(2),
    cep VARCHAR(10),
    ativo BOOLEAN DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cnpj_cpf (cnpj_cpf),
    INDEX idx_email (email),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONTAS A RECEBER
-- ============================================================
CREATE TABLE IF NOT EXISTS contas_receber (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id INT NOT NULL,
    descricao VARCHAR(255),
    valor DECIMAL(10, 2) NOT NULL,
    data_vencimento DATE NOT NULL,
    status ENUM('pendente', 'pago', 'vencido', 'cancelado') DEFAULT 'pendente',
    forma_pagamento VARCHAR(50),
    numero_boleto VARCHAR(50),
    referencia VARCHAR(100),
    observacoes TEXT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    INDEX idx_cliente_id (cliente_id),
    INDEX idx_status (status),
    INDEX idx_data_vencimento (data_vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONTAS A PAGAR
-- ============================================================
CREATE TABLE IF NOT EXISTS contas_pagar (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fornecedor_id INT,
    descricao VARCHAR(255),
    valor DECIMAL(10, 2) NOT NULL,
    data_vencimento DATE NOT NULL,
    status ENUM('pendente', 'pago', 'vencido', 'cancelado') DEFAULT 'pendente',
    forma_pagamento VARCHAR(50),
    referencia VARCHAR(100),
    observacoes TEXT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_data_vencimento (data_vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ALERTAS DE CONTAS VENCIDAS
-- ============================================================
CREATE TABLE IF NOT EXISTS alertas_contas_vencidas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conta_receber_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo_alerta ENUM('vencido', 'vencendo_hoje', 'vencendo_amanha', 'vencendo_semana') DEFAULT 'vencido',
    titulo VARCHAR(255),
    descricao TEXT,
    valor DECIMAL(10, 2),
    dias_vencido INT,
    visualizado BOOLEAN DEFAULT 0,
    acao_tomada VARCHAR(50),
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_visualizacao TIMESTAMP NULL,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_tipo_alerta (tipo_alerta),
    INDEX idx_visualizado (visualizado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONFIGURAÇÕES
-- ============================================================
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(100) NOT NULL,
    valor LONGTEXT,
    tipo_usuario_id INT,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_chave_usuario (chave, tipo_usuario_id),
    INDEX idx_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TEMPLATES DE E-MAIL
-- ============================================================
CREATE TABLE IF NOT EXISTS email_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_id VARCHAR(50) NOT NULL,
    assunto VARCHAR(255),
    conteudo LONGTEXT,
    usuario_id INT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_template_usuario (template_id, usuario_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_template_id (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LOG DE E-MAILS
-- ============================================================
CREATE TABLE IF NOT EXISTS email_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    destinatario VARCHAR(255),
    assunto VARCHAR(255),
    conteudo LONGTEXT,
    tipo VARCHAR(50),
    status ENUM('enviado', 'erro', 'pendente') DEFAULT 'pendente',
    mensagem_erro TEXT,
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_destinatario (destinatario),
    INDEX idx_data_envio (data_envio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ALERTAS PROGRAMADOS
-- ============================================================
CREATE TABLE IF NOT EXISTS alertas_programados (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo VARCHAR(50),
    frequencia VARCHAR(50),
    ativo BOOLEAN DEFAULT 1,
    destinatarios TEXT,
    usuario_id INT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LOG DE INTEGRAÇÃO
-- ============================================================
CREATE TABLE IF NOT EXISTS logs_integracao (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo_integracao VARCHAR(50),
    acao VARCHAR(100),
    dados JSON,
    status ENUM('sucesso', 'erro', 'pendente') DEFAULT 'pendente',
    mensagem_erro TEXT,
    usuario_id INT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_tipo_integracao (tipo_integracao),
    INDEX idx_status (status),
    INDEX idx_data_criacao (data_criacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WEBHOOKS DE PAGAMENTO
-- ============================================================
CREATE TABLE IF NOT EXISTS webhooks_pagamento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo_webhook VARCHAR(50),
    conta_receber_id INT,
    dados_webhook JSON,
    status ENUM('processado', 'erro', 'pendente') DEFAULT 'pendente',
    mensagem_erro TEXT,
    data_recebimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id) ON DELETE SET NULL,
    INDEX idx_tipo_webhook (tipo_webhook),
    INDEX idx_status (status),
    INDEX idx_data_recebimento (data_recebimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ÍNDICES ADICIONAIS
-- ============================================================
CREATE INDEX idx_contas_receber_data_vencimento ON contas_receber(data_vencimento);
CREATE INDEX idx_contas_pagar_data_vencimento ON contas_pagar(data_vencimento);
CREATE INDEX idx_usuarios_ativo ON usuarios(ativo);
CREATE INDEX idx_clientes_ativo ON clientes(ativo);
