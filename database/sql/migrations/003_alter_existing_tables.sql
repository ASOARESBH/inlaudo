-- ============================================================
-- MIGRATION 003: ALTERAÇÕES EM TABELAS EXISTENTES
-- ============================================================
-- Data: 2026-01-11
-- Versão: 2.3.0
-- Descrição: Adiciona colunas para suporte a gateways de pagamento
-- ============================================================

-- ============================================================
-- 1. CRIAR TABELA CONTRATOS (SE NÃO EXISTIR)
-- ============================================================
CREATE TABLE IF NOT EXISTS contratos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Cliente
    cliente_id INT NOT NULL,
    
    -- Dados do Contrato
    numero_contrato VARCHAR(50) UNIQUE COMMENT 'Número único do contrato',
    descricao TEXT NOT NULL COMMENT 'Descrição do serviço/produto',
    tipo VARCHAR(50) COMMENT 'Tipo de contrato',
    
    -- Valores
    valor_total DECIMAL(10,2) NOT NULL COMMENT 'Valor total do contrato',
    valor_parcela DECIMAL(10,2) COMMENT 'Valor de cada parcela',
    quantidade_parcelas INT DEFAULT 1 COMMENT 'Número de parcelas',
    
    -- Forma de Pagamento
    forma_pagamento VARCHAR(50) COMMENT 'Forma de pagamento padrão',
    gateway_id INT COMMENT 'Gateway preferencial',
    gateways_disponiveis JSON COMMENT 'Array de IDs de gateways disponíveis',
    
    -- Período
    data_inicio DATE NOT NULL COMMENT 'Data de início do contrato',
    data_termino DATE COMMENT 'Data de término do contrato',
    
    -- Recorrência
    recorrente BOOLEAN DEFAULT 0 COMMENT 'Contrato recorrente',
    periodicidade ENUM('mensal', 'bimestral', 'trimestral', 'semestral', 'anual') COMMENT 'Periodicidade se recorrente',
    dia_vencimento INT COMMENT 'Dia do mês para vencimento',
    
    -- Status
    status ENUM('ativo', 'inativo', 'suspenso', 'cancelado', 'finalizado') DEFAULT 'ativo',
    
    -- Arquivos
    arquivo_contrato VARCHAR(255) COMMENT 'Caminho do arquivo do contrato',
    
    -- Configurações
    gerar_contas_automaticamente BOOLEAN DEFAULT 1 COMMENT 'Gerar contas a receber automaticamente',
    enviar_email_cobranca BOOLEAN DEFAULT 1 COMMENT 'Enviar e-mail de cobrança',
    
    -- Observações
    observacoes TEXT,
    
    -- Auditoria
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_por INT COMMENT 'ID do usuário que criou',
    
    -- Chaves estrangeiras
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    
    -- Índices
    INDEX idx_cliente_id (cliente_id),
    INDEX idx_status (status),
    INDEX idx_data_inicio (data_inicio),
    INDEX idx_data_termino (data_termino),
    INDEX idx_numero_contrato (numero_contrato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Contratos de serviços/produtos com clientes';

-- ============================================================
-- 2. TABELA DE MAPEAMENTO DE STATUS
-- ============================================================
CREATE TABLE IF NOT EXISTS gateway_status_mapping (
    id INT PRIMARY KEY AUTO_INCREMENT,
    gateway_id INT NOT NULL,
    status_gateway VARCHAR(50) NOT NULL COMMENT 'Status original do gateway',
    status_erp ENUM('pendente', 'processando', 'pago', 'cancelado', 'estornado', 'vencido', 'erro') NOT NULL COMMENT 'Status padronizado do ERP',
    descricao TEXT COMMENT 'Descrição do mapeamento',
    
    UNIQUE KEY unique_gateway_status (gateway_id, status_gateway),
    INDEX idx_gateway_status (gateway_id, status_gateway)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Mapeamento de status dos gateways para status padronizado do ERP';

-- ============================================================
-- 3. INSERIR MAPEAMENTOS DE STATUS PADRÃO
-- ============================================================

-- Mercado Pago (ID = 1)
INSERT IGNORE INTO gateway_status_mapping (gateway_id, status_gateway, status_erp, descricao) VALUES
(1, 'pending', 'pendente', 'Aguardando pagamento'),
(1, 'in_process', 'processando', 'Pagamento em processamento'),
(1, 'approved', 'pago', 'Pagamento aprovado'),
(1, 'cancelled', 'cancelado', 'Pagamento cancelado'),
(1, 'refunded', 'estornado', 'Pagamento estornado'),
(1, 'rejected', 'erro', 'Pagamento rejeitado'),
(1, 'charged_back', 'estornado', 'Chargeback realizado');

-- Asaas (ID = 2)
INSERT IGNORE INTO gateway_status_mapping (gateway_id, status_gateway, status_erp, descricao) VALUES
(2, 'PENDING', 'pendente', 'Aguardando pagamento'),
(2, 'RECEIVED', 'pago', 'Pagamento recebido'),
(2, 'CONFIRMED', 'pago', 'Pagamento confirmado'),
(2, 'OVERDUE', 'vencido', 'Pagamento vencido'),
(2, 'REFUNDED', 'estornado', 'Pagamento estornado'),
(2, 'RECEIVED_IN_CASH', 'pago', 'Recebido em dinheiro'),
(2, 'REFUND_REQUESTED', 'processando', 'Estorno solicitado'),
(2, 'CHARGEBACK_REQUESTED', 'processando', 'Chargeback solicitado'),
(2, 'CHARGEBACK_DISPUTE', 'processando', 'Disputa de chargeback'),
(2, 'AWAITING_CHARGEBACK_REVERSAL', 'processando', 'Aguardando reversão'),
(2, 'DUNNING_REQUESTED', 'processando', 'Cobrança solicitada'),
(2, 'DUNNING_RECEIVED', 'pago', 'Cobrança recebida'),
(2, 'AWAITING_RISK_ANALYSIS', 'processando', 'Aguardando análise de risco');

-- Cora (ID = 3)
INSERT IGNORE INTO gateway_status_mapping (gateway_id, status_gateway, status_erp, descricao) VALUES
(3, 'PENDING', 'pendente', 'Aguardando pagamento'),
(3, 'PAID', 'pago', 'Pagamento confirmado'),
(3, 'CANCELLED', 'cancelado', 'Pagamento cancelado'),
(3, 'EXPIRED', 'vencido', 'Pagamento expirado'),
(3, 'REFUNDED', 'estornado', 'Pagamento estornado');

-- ============================================================
-- FIM DA MIGRATION 003
-- ============================================================
