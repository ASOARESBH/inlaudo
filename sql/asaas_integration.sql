-- ============================================================================
-- SCRIPT DE INTEGRAÇÃO ASAAS v3
-- Banco de Dados: MariaDB
-- Data: 09 de Janeiro de 2026
-- ============================================================================

-- ============================================================================
-- 1. TABELA: integracao_asaas
-- Armazena configurações da integração Asaas
-- ============================================================================
CREATE TABLE IF NOT EXISTS `integracao_asaas` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `api_key` VARCHAR(255) NOT NULL COMMENT 'Chave de API do Asaas',
    `ambiente` VARCHAR(50) NOT NULL DEFAULT 'sandbox' COMMENT 'Ambiente: sandbox ou production',
    `webhook_token` VARCHAR(255) NULL COMMENT 'Token para validar webhooks',
    `ativo` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Status da integração',
    `data_criacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_ativo` (`ativo`),
    INDEX `idx_ambiente` (`ambiente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuração da integração Asaas';

-- ============================================================================
-- 2. TABELA: asaas_clientes
-- Mapeamento entre clientes locais e clientes Asaas
-- ============================================================================
CREATE TABLE IF NOT EXISTS `asaas_clientes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `cliente_id` INT(11) NULL COMMENT 'ID do cliente local',
    `asaas_customer_id` VARCHAR(100) NOT NULL UNIQUE COMMENT 'ID do cliente no Asaas',
    `cpf_cnpj` VARCHAR(20) NOT NULL UNIQUE COMMENT 'CPF ou CNPJ do cliente',
    `data_criacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_cliente_id` (`cliente_id`),
    INDEX `idx_asaas_customer_id` (`asaas_customer_id`),
    INDEX `idx_cpf_cnpj` (`cpf_cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mapeamento de clientes Asaas';

-- ============================================================================
-- 3. TABELA: asaas_pagamentos
-- Mapeamento entre cobranças locais e cobranças Asaas
-- ============================================================================
CREATE TABLE IF NOT EXISTS `asaas_pagamentos` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `conta_receber_id` INT(11) NULL COMMENT 'ID da conta a receber local',
    `asaas_payment_id` VARCHAR(100) NOT NULL UNIQUE COMMENT 'ID do pagamento no Asaas',
    `tipo_cobranca` VARCHAR(50) NOT NULL COMMENT 'PIX ou BOLETO',
    `valor` DECIMAL(15, 2) NOT NULL COMMENT 'Valor da cobrança',
    `data_vencimento` DATE NOT NULL COMMENT 'Data de vencimento',
    `status_asaas` VARCHAR(50) NOT NULL DEFAULT 'PENDING' COMMENT 'Status no Asaas',
    `url_boleto` VARCHAR(500) NULL COMMENT 'URL do boleto',
    `nosso_numero` VARCHAR(50) NULL COMMENT 'Nosso número do boleto',
    `linha_digitavel` VARCHAR(100) NULL COMMENT 'Linha digitável',
    `qr_code_pix` LONGTEXT NULL COMMENT 'QR Code PIX',
    `payload_pix` LONGTEXT NULL COMMENT 'Payload PIX',
    `data_criacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_conta_receber_id` (`conta_receber_id`),
    INDEX `idx_asaas_payment_id` (`asaas_payment_id`),
    INDEX `idx_tipo_cobranca` (`tipo_cobranca`),
    INDEX `idx_status_asaas` (`status_asaas`),
    INDEX `idx_data_vencimento` (`data_vencimento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mapeamento de pagamentos Asaas';

-- ============================================================================
-- 4. TABELA: asaas_logs
-- Registra todas as operações com a API Asaas
-- ============================================================================
CREATE TABLE IF NOT EXISTS `asaas_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `operacao` VARCHAR(100) NOT NULL COMMENT 'Nome da operação',
    `status` VARCHAR(50) NOT NULL COMMENT 'sucesso, erro, pendente',
    `dados_requisicao` LONGTEXT NULL COMMENT 'Dados enviados (JSON)',
    `dados_resposta` LONGTEXT NULL COMMENT 'Dados recebidos (JSON)',
    `mensagem_erro` TEXT NULL COMMENT 'Mensagem de erro',
    `data_criacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_operacao` (`operacao`),
    INDEX `idx_status` (`status`),
    INDEX `idx_data_criacao` (`data_criacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs de operações Asaas';

-- ============================================================================
-- 5. TABELA: asaas_webhooks
-- Registra todos os webhooks recebidos do Asaas
-- ============================================================================
CREATE TABLE IF NOT EXISTS `asaas_webhooks` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `event_id` VARCHAR(100) NOT NULL UNIQUE COMMENT 'ID único do evento',
    `tipo_evento` VARCHAR(100) NOT NULL COMMENT 'Tipo de evento (PAYMENT_RECEIVED, etc)',
    `payment_id` VARCHAR(100) NOT NULL COMMENT 'ID do pagamento',
    `payload` LONGTEXT NOT NULL COMMENT 'Payload do webhook (JSON)',
    `processado` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Se foi processado',
    `data_recebimento` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `data_processamento` DATETIME NULL,
    
    INDEX `idx_event_id` (`event_id`),
    INDEX `idx_tipo_evento` (`tipo_evento`),
    INDEX `idx_payment_id` (`payment_id`),
    INDEX `idx_processado` (`processado`),
    INDEX `idx_data_recebimento` (`data_recebimento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Webhooks recebidos do Asaas';

-- ============================================================================
-- 6. ALTERAÇÕES EM TABELAS EXISTENTES
-- Adicionar campos necessários para integração
-- ============================================================================

-- Adicionar coluna em contas_receber (se não existir)
ALTER TABLE `contas_receber` 
ADD COLUMN IF NOT EXISTS `gateway_payment_id` VARCHAR(100) NULL COMMENT 'ID do pagamento no gateway' AFTER `status`,
ADD COLUMN IF NOT EXISTS `forma_pagamento` VARCHAR(50) NULL COMMENT 'Forma de pagamento: asaas, mercadopago, cora, stripe' AFTER `gateway_payment_id`,
ADD COLUMN IF NOT EXISTS `ambiente_pagamento` VARCHAR(50) NULL COMMENT 'Ambiente: sandbox ou production' AFTER `forma_pagamento`,
ADD INDEX IF NOT EXISTS `idx_gateway_payment_id` (`gateway_payment_id`),
ADD INDEX IF NOT EXISTS `idx_forma_pagamento` (`forma_pagamento`);

-- Adicionar coluna em contratos (se não existir)
ALTER TABLE `contratos` 
ADD COLUMN IF NOT EXISTS `forma_pagamento` VARCHAR(50) NULL COMMENT 'Forma de pagamento' AFTER `status`,
ADD COLUMN IF NOT EXISTS `ambiente_pagamento` VARCHAR(50) NULL COMMENT 'Ambiente: sandbox ou production' AFTER `forma_pagamento`,
ADD INDEX IF NOT EXISTS `idx_forma_pagamento` (`forma_pagamento`);

-- Adicionar coluna em contas_pagar (se não existir)
ALTER TABLE `contas_pagar` 
ADD COLUMN IF NOT EXISTS `forma_pagamento` VARCHAR(50) NULL COMMENT 'Forma de pagamento' AFTER `status`,
ADD COLUMN IF NOT EXISTS `ambiente_pagamento` VARCHAR(50) NULL COMMENT 'Ambiente: sandbox ou production' AFTER `forma_pagamento`,
ADD INDEX IF NOT EXISTS `idx_forma_pagamento` (`forma_pagamento`);

-- Adicionar coluna em royalties (se não existir)
ALTER TABLE `royalties` 
ADD COLUMN IF NOT EXISTS `forma_pagamento` VARCHAR(50) NULL COMMENT 'Forma de pagamento' AFTER `status`,
ADD COLUMN IF NOT EXISTS `ambiente_pagamento` VARCHAR(50) NULL COMMENT 'Ambiente: sandbox ou production' AFTER `forma_pagamento`,
ADD INDEX IF NOT EXISTS `idx_forma_pagamento` (`forma_pagamento`);

-- ============================================================================
-- 7. ÍNDICES ADICIONAIS PARA PERFORMANCE
-- ============================================================================

-- Índices em asaas_clientes
ALTER TABLE `asaas_clientes` 
ADD INDEX IF NOT EXISTS `idx_data_criacao` (`data_criacao`);

-- Índices em asaas_pagamentos
ALTER TABLE `asaas_pagamentos` 
ADD INDEX IF NOT EXISTS `idx_data_criacao` (`data_criacao`),
ADD INDEX IF NOT EXISTS `idx_data_atualizacao` (`data_atualizacao`);

-- ============================================================================
-- FIM DO SCRIPT
-- ============================================================================
