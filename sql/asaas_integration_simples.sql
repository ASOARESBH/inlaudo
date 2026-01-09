-- ============================================================
-- SCRIPT DE INTEGRAÇÃO ASAAS V3 - MariaDB (VERSÃO SIMPLES)
-- Sistema: ERP Inlaudo
-- Versão: 1.0.0
-- Data: Janeiro 2025
-- ============================================================

-- 1. Tabela de configuração da integração Asaas
CREATE TABLE IF NOT EXISTS `integracao_asaas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_key` varchar(255) NOT NULL,
  `webhook_token` varchar(255) NOT NULL,
  `webhook_url` varchar(500) NOT NULL,
  `ambiente` varchar(20) DEFAULT 'sandbox',
  `ativo` tinyint(1) DEFAULT 0,
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabela de mapeamento de clientes
CREATE TABLE IF NOT EXISTS `asaas_clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `asaas_customer_id` varchar(100) NOT NULL,
  `cpf_cnpj` varchar(20) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cliente_id` (`cliente_id`),
  UNIQUE KEY `unique_asaas_customer_id` (`asaas_customer_id`),
  KEY `idx_cpf_cnpj` (`cpf_cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabela de mapeamento de pagamentos
CREATE TABLE IF NOT EXISTS `asaas_pagamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conta_receber_id` int(11) NOT NULL,
  `asaas_payment_id` varchar(100) NOT NULL,
  `tipo_cobranca` varchar(20) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `status_asaas` varchar(50) DEFAULT 'pending',
  `url_boleto` varchar(500) DEFAULT NULL,
  `nosso_numero` varchar(50) DEFAULT NULL,
  `linha_digitavel` varchar(100) DEFAULT NULL,
  `qr_code_pix` longtext DEFAULT NULL,
  `payload_pix` varchar(500) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_asaas_payment_id` (`asaas_payment_id`),
  KEY `idx_conta_receber_id` (`conta_receber_id`),
  KEY `idx_tipo_cobranca` (`tipo_cobranca`),
  KEY `idx_status_asaas` (`status_asaas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabela de logs de operações
CREATE TABLE IF NOT EXISTS `asaas_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `operacao` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL,
  `dados_requisicao` longtext DEFAULT NULL,
  `dados_resposta` longtext DEFAULT NULL,
  `mensagem_erro` text DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_operacao` (`operacao`),
  KEY `idx_status` (`status`),
  KEY `idx_data_criacao` (`data_criacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabela de webhooks recebidos
CREATE TABLE IF NOT EXISTS `asaas_webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` varchar(100) NOT NULL,
  `tipo_evento` varchar(100) NOT NULL,
  `payment_id` varchar(100) DEFAULT NULL,
  `payload` longtext DEFAULT NULL,
  `processado` tinyint(1) DEFAULT 0,
  `data_recebimento` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_processamento` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_event_id` (`event_id`),
  KEY `idx_tipo_evento` (`tipo_evento`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_processado` (`processado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Inserir configuração padrão
INSERT INTO `integracao_asaas` (`api_key`, `webhook_token`, `webhook_url`, `ambiente`, `ativo`) 
VALUES ('', '', '', 'sandbox', 0);

-- ============================================================
-- FIM DO SCRIPT
-- ============================================================
