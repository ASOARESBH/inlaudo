-- ============================================================
-- MIGRATION 002: GATEWAYS DE PAGAMENTO E PADRONIZAÇÃO
-- ============================================================
-- Data: 2026-01-11
-- Versão: 2.3.0
-- Descrição: Implementa arquitetura de gateways de pagamento
--            seguindo boas práticas de ERP
-- ============================================================

-- ============================================================
-- 1. TABELA DE GATEWAYS DE PAGAMENTO (CADASTRO)
-- ============================================================
CREATE TABLE IF NOT EXISTS gateways_pagamento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL UNIQUE COMMENT 'Código único: mercadopago, asaas, cora, pix_manual, boleto_manual',
    nome VARCHAR(100) NOT NULL COMMENT 'Nome exibido: Mercado Pago, Asaas, Cora',
    tipo ENUM('online', 'manual') DEFAULT 'online' COMMENT 'online=API automática, manual=processo manual',
    ativo BOOLEAN DEFAULT 1 COMMENT 'Gateway ativo para uso',
    ordem_exibicao INT DEFAULT 0 COMMENT 'Ordem de exibição no portal do cliente',
    
    -- Configurações
    config_json JSON COMMENT 'Configurações específicas do gateway (API keys, etc)',
    taxa_percentual DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Taxa percentual do gateway',
    taxa_fixa DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Taxa fixa por transação',
    
    -- Metadados
    descricao TEXT COMMENT 'Descrição do gateway',
    icone VARCHAR(255) COMMENT 'URL ou classe do ícone',
    cor_hex VARCHAR(7) COMMENT 'Cor do gateway em hexadecimal',
    
    -- Auditoria
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_codigo (codigo),
    INDEX idx_ativo (ativo),
    INDEX idx_ordem (ordem_exibicao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cadastro de gateways de pagamento disponíveis';

-- ============================================================
-- 2. INSERIR GATEWAYS PADRÃO
-- ============================================================
INSERT INTO gateways_pagamento (codigo, nome, tipo, ativo, ordem_exibicao, descricao, icone, cor_hex) VALUES
('mercadopago', 'Mercado Pago', 'online', 1, 1, 'Pagamento via Mercado Pago com cartão, PIX e boleto', 'fab fa-cc-mastercard', '#00B1EA'),
('asaas', 'Asaas', 'online', 1, 2, 'Pagamento via Asaas com cartão, PIX e boleto', 'fas fa-credit-card', '#1E88E5'),
('cora', 'Cora', 'online', 1, 3, 'Pagamento via Cora com PIX e boleto', 'fas fa-university', '#FF6B35'),
('pix_manual', 'PIX Manual', 'manual', 1, 4, 'Pagamento via PIX com confirmação manual', 'fas fa-qrcode', '#32BCAD'),
('boleto_manual', 'Boleto Manual', 'manual', 1, 5, 'Boleto bancário com confirmação manual', 'fas fa-barcode', '#6C757D')
ON DUPLICATE KEY UPDATE 
    nome = VALUES(nome),
    descricao = VALUES(descricao);

-- ============================================================
-- 3. TABELA DE TRANSAÇÕES POR GATEWAY
-- ============================================================
CREATE TABLE IF NOT EXISTS gateway_transacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Vinculação
    conta_receber_id INT NOT NULL COMMENT 'FK para contas_receber (dona da verdade)',
    gateway_id INT NOT NULL COMMENT 'FK para gateways_pagamento',
    
    -- Identificação no Gateway
    gateway_transaction_id VARCHAR(255) COMMENT 'ID da transação no gateway externo',
    gateway_payment_id VARCHAR(255) COMMENT 'ID do pagamento no gateway externo',
    gateway_charge_id VARCHAR(255) COMMENT 'ID da cobrança no gateway externo',
    
    -- Status Padronizado (seguindo padrão ERP)
    status_erp ENUM(
        'pendente',      -- Aguardando pagamento
        'processando',   -- Pagamento em processamento
        'pago',          -- Pagamento confirmado
        'cancelado',     -- Cancelado manualmente
        'estornado',     -- Estorno realizado
        'vencido',       -- Vencido sem pagamento
        'erro'           -- Erro no processamento
    ) DEFAULT 'pendente' COMMENT 'Status padronizado do ERP',
    
    -- Status Original do Gateway
    status_gateway VARCHAR(50) COMMENT 'Status original retornado pelo gateway',
    
    -- Valores
    valor_original DECIMAL(10,2) NOT NULL COMMENT 'Valor original da transação',
    valor_taxa DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Taxa cobrada pelo gateway',
    valor_liquido DECIMAL(10,2) COMMENT 'Valor líquido recebido',
    
    -- Dados do Pagamento
    forma_pagamento VARCHAR(50) COMMENT 'Forma: credit_card, debit_card, pix, boleto',
    dados_pagamento JSON COMMENT 'Dados completos do pagamento',
    
    -- URLs
    payment_url TEXT COMMENT 'URL para pagamento',
    boleto_url TEXT COMMENT 'URL do boleto (se aplicável)',
    pix_qrcode TEXT COMMENT 'QR Code PIX (se aplicável)',
    pix_copia_cola TEXT COMMENT 'Código PIX copia e cola',
    
    -- Datas
    data_vencimento DATE COMMENT 'Data de vencimento',
    data_pagamento DATETIME COMMENT 'Data/hora do pagamento confirmado',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Metadados
    metadata_json JSON COMMENT 'Metadados adicionais',
    ip_origem VARCHAR(45) COMMENT 'IP de origem da transação',
    user_agent TEXT COMMENT 'User agent do cliente',
    
    -- Chaves estrangeiras
    FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id) ON DELETE CASCADE,
    FOREIGN KEY (gateway_id) REFERENCES gateways_pagamento(id) ON DELETE RESTRICT,
    
    -- Índices
    INDEX idx_conta_receber (conta_receber_id),
    INDEX idx_gateway (gateway_id),
    INDEX idx_status_erp (status_erp),
    INDEX idx_gateway_transaction_id (gateway_transaction_id),
    INDEX idx_data_vencimento (data_vencimento),
    INDEX idx_data_pagamento (data_pagamento),
    
    -- Índice composto para busca rápida
    INDEX idx_gateway_status (gateway_id, status_erp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Transações de pagamento por gateway - vinculadas a contas_receber';

-- ============================================================
-- FIM DA MIGRATION 002 - PARTE 1
-- ============================================================
