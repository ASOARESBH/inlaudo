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

-- Mercado Pago
INSERT IGNORE INTO gateway_status_mapping (gateway_id, status_gateway, status_erp, descricao) VALUES
((SELECT id FROM gateways_pagamento WHERE codigo = 'mercadopago'), 'pending', 'pendente', 'Aguardando pagamento'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'mercadopago'), 'in_process', 'processando', 'Pagamento em processamento'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'mercadopago'), 'approved', 'pago', 'Pagamento aprovado'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'mercadopago'), 'cancelled', 'cancelado', 'Pagamento cancelado'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'mercadopago'), 'refunded', 'estornado', 'Pagamento estornado'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'mercadopago'), 'rejected', 'erro', 'Pagamento rejeitado'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'mercadopago'), 'charged_back', 'estornado', 'Chargeback realizado');

-- Asaas
INSERT IGNORE INTO gateway_status_mapping (gateway_id, status_gateway, status_erp, descricao) VALUES
((SELECT id FROM gateways_pagamento WHERE codigo = 'asaas'), 'PENDING', 'pendente', 'Aguardando pagamento'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'asaas'), 'RECEIVED', 'pago', 'Pagamento recebido'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'asaas'), 'CONFIRMED', 'pago', 'Pagamento confirmado'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'asaas'), 'OVERDUE', 'vencido', 'Pagamento vencido'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'asaas'), 'REFUNDED', 'estornado', 'Pagamento estornado'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'asaas'), 'RECEIVED_IN_CASH', 'pago', 'Recebido em dinheiro'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'asaas'), 'REFUND_REQUESTED', 'processando', 'Estorno solicitado'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'asaas'), 'CHARGEBACK_REQUESTED', 'processando', 'Chargeback solicitado'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'asaas'), 'CHARGEBACK_DISPUTE', 'processando', 'Disputa de chargeback'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'asaas'), 'AWAITING_CHARGEBACK_REVERSAL', 'processando', 'Aguardando reversão'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'asaas'), 'DUNNING_REQUESTED', 'processando', 'Cobrança solicitada'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'asaas'), 'DUNNING_RECEIVED', 'pago', 'Cobrança recebida'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'asaas'), 'AWAITING_RISK_ANALYSIS', 'processando', 'Aguardando análise de risco');

-- Cora
INSERT IGNORE INTO gateway_status_mapping (gateway_id, status_gateway, status_erp, descricao) VALUES
((SELECT id FROM gateways_pagamento WHERE codigo = 'cora'), 'PENDING', 'pendente', 'Aguardando pagamento'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'cora'), 'PAID', 'pago', 'Pagamento confirmado'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'cora'), 'CANCELLED', 'cancelado', 'Pagamento cancelado'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'cora'), 'EXPIRED', 'vencido', 'Pagamento expirado'),
((SELECT id FROM gateways_pagamento WHERE codigo = 'cora'), 'REFUNDED', 'estornado', 'Pagamento estornado');

-- ============================================================
-- FIM DA MIGRATION 003
-- ============================================================
