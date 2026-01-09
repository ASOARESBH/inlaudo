-- ============================================================
-- SCRIPT DE INTEGRAÇÃO ASAAS V3 - MariaDB
-- Sistema: ERP Inlaudo
-- Versão: 1.0.0 (Corrigido para MariaDB)
-- Data: Janeiro 2025
-- ============================================================

-- Tabela de configuração da integração Asaas
CREATE TABLE IF NOT EXISTS `integracao_asaas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Chave de API do Asaas',
  `webhook_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Token de segurança do webhook',
  `webhook_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL do webhook',
  `ambiente` enum('sandbox','production') COLLATE utf8mb4_unicode_ci DEFAULT 'sandbox' COMMENT 'Ambiente (sandbox ou production)',
  `ativo` tinyint(1) DEFAULT 0 COMMENT 'Se integração está ativa',
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuração da integração Asaas';

-- Tabela de mapeamento de clientes
CREATE TABLE IF NOT EXISTS `asaas_clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL COMMENT 'ID do cliente no sistema local',
  `asaas_customer_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID do cliente no Asaas',
  `cpf_cnpj` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'CPF ou CNPJ do cliente',
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cliente_id` (`cliente_id`),
  UNIQUE KEY `unique_asaas_customer_id` (`asaas_customer_id`),
  KEY `idx_cpf_cnpj` (`cpf_cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mapeamento de clientes entre sistema local e Asaas';

-- Tabela de mapeamento de pagamentos
CREATE TABLE IF NOT EXISTS `asaas_pagamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conta_receber_id` int(11) NOT NULL COMMENT 'ID da conta a receber',
  `asaas_payment_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID do pagamento no Asaas',
  `tipo_cobranca` enum('BOLETO','PIX','CREDIT_CARD') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de cobrança',
  `valor` decimal(10,2) NOT NULL COMMENT 'Valor da cobrança',
  `data_vencimento` date NOT NULL COMMENT 'Data de vencimento',
  `status_asaas` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'Status no Asaas',
  `url_boleto` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL do boleto',
  `nosso_numero` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nosso número do boleto',
  `linha_digitavel` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Linha digitável do boleto',
  `qr_code_pix` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'QR Code PIX em base64',
  `payload_pix` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Payload PIX (chave copia e cola)',
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_asaas_payment_id` (`asaas_payment_id`),
  KEY `idx_conta_receber_id` (`conta_receber_id`),
  KEY `idx_tipo_cobranca` (`tipo_cobranca`),
  KEY `idx_status_asaas` (`status_asaas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mapeamento de pagamentos entre sistema local e Asaas';

-- Tabela de logs de operações
CREATE TABLE IF NOT EXISTS `asaas_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `operacao` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de operação (create_customer, create_payment, etc)',
  `status` enum('sucesso','erro','pendente') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Status da operação',
  `dados_requisicao` longtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON com dados da requisição',
  `dados_resposta` longtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON com dados da resposta',
  `mensagem_erro` text COLLATE utf8mb4_unicode_ci COMMENT 'Mensagem de erro se houver',
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_operacao` (`operacao`),
  KEY `idx_status` (`status`),
  KEY `idx_data_criacao` (`data_criacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs de operações da integração Asaas';

-- Tabela de webhooks recebidos
CREATE TABLE IF NOT EXISTS `asaas_webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID único do evento',
  `tipo_evento` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo de evento (PAYMENT_RECEIVED, etc)',
  `payment_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID do pagamento',
  `payload` longtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON completo do webhook',
  `processado` tinyint(1) DEFAULT 0 COMMENT 'Se foi processado',
  `data_recebimento` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_processamento` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_event_id` (`event_id`),
  KEY `idx_tipo_evento` (`tipo_evento`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_processado` (`processado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de webhooks recebidos do Asaas';

-- ============================================================
-- ADICIONAR COLUNAS À TABELA contas_receber (SE EXISTIR)
-- ============================================================

-- Verificar se a tabela contas_receber existe antes de adicionar colunas
-- Se não existir, as colunas não serão adicionadas (sem erro)

SET @dbname = DATABASE();
SET @tablename = 'contas_receber';
SET @columnname1 = 'gateway_asaas_id';
SET @columnname2 = 'status_asaas';

-- Verificar e adicionar coluna gateway_asaas_id
SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = @columnname1) = 0,
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname1, '` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT "ID do pagamento no Asaas"'),
  'SELECT 1'
) INTO @sql1;

PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

-- Verificar e adicionar coluna status_asaas
SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND COLUMN_NAME = @columnname2) = 0,
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname2, '` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT "Status do pagamento no Asaas"'),
  'SELECT 1'
) INTO @sql2;

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- ============================================================
-- ADICIONAR ÍNDICES À TABELA contas_receber (SE EXISTIR)
-- ============================================================

-- Adicionar índice para gateway_asaas_id se não existir
SET @indexname1 = 'idx_gateway_asaas_id';
SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND INDEX_NAME = @indexname1) = 0,
  CONCAT('ALTER TABLE `', @tablename, '` ADD KEY `', @indexname1, '` (`gateway_asaas_id`)'),
  'SELECT 1'
) INTO @sql3;

PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- Adicionar índice para status_asaas se não existir
SET @indexname2 = 'idx_status_asaas';
SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
   WHERE TABLE_SCHEMA = @dbname 
   AND TABLE_NAME = @tablename 
   AND INDEX_NAME = @indexname2) = 0,
  CONCAT('ALTER TABLE `', @tablename, '` ADD KEY `', @indexname2, '` (`status_asaas`)'),
  'SELECT 1'
) INTO @sql4;

PREPARE stmt4 FROM @sql4;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;

-- ============================================================
-- INSERIR CONFIGURAÇÃO PADRÃO
-- ============================================================

INSERT INTO `integracao_asaas` (`api_key`, `webhook_token`, `webhook_url`, `ambiente`, `ativo`) 
VALUES ('', '', '', 'sandbox', 0)
ON DUPLICATE KEY UPDATE `data_atualizacao` = NOW();

-- ============================================================
-- FIM DO SCRIPT
-- ============================================================
