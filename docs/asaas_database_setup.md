# Script SQL - Integração Asaas

Execute este script no seu banco de dados para criar as tabelas necessárias para a integração com o Asaas.

```sql
-- Tabela de configuração da integração Asaas
CREATE TABLE IF NOT EXISTS `integracao_asaas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Chave de API do Asaas',
  `webhook_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Token de segurança do webhook',
  `webhook_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL do webhook',
  `ambiente` enum('sandbox','production') COLLATE utf8mb4_unicode_ci DEFAULT 'sandbox',
  `ativo` tinyint(1) DEFAULT '0',
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de clientes Asaas (mapeamento)
CREATE TABLE IF NOT EXISTS `asaas_clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `asaas_customer_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cpf_cnpj` varchar(18) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_sincronizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cliente_id` (`cliente_id`),
  UNIQUE KEY `unique_asaas_customer_id` (`asaas_customer_id`),
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de cobranças Asaas (mapeamento)
CREATE TABLE IF NOT EXISTS `asaas_pagamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conta_receber_id` int(11) NOT NULL,
  `asaas_payment_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_cobranca` enum('BOLETO','PIX') COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `status_asaas` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_boleto` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nosso_numero` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linha_digitavel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_code_pix` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_pix` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_asaas_payment_id` (`asaas_payment_id`),
  FOREIGN KEY (`conta_receber_id`) REFERENCES `contas_receber`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de logs de integração Asaas
CREATE TABLE IF NOT EXISTS `asaas_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `operacao` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('sucesso','erro','pendente') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `dados_requisicao` longtext COLLATE utf8mb4_unicode_ci,
  `dados_resposta` longtext COLLATE utf8mb4_unicode_ci,
  `mensagem_erro` text COLLATE utf8mb4_unicode_ci,
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_operacao` (`operacao`),
  KEY `idx_status` (`status`),
  KEY `idx_data_criacao` (`data_criacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de webhooks recebidos
CREATE TABLE IF NOT EXISTS `asaas_webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_evento` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci,
  `processado` tinyint(1) DEFAULT '0',
  `data_recebimento` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_processamento` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_event_id` (`event_id`),
  KEY `idx_tipo_evento` (`tipo_evento`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_processado` (`processado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar colunas à tabela contas_receber
ALTER TABLE `contas_receber` 
ADD COLUMN IF NOT EXISTS `gateway_asaas_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `status_asaas` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
ADD KEY IF NOT EXISTS `idx_gateway_asaas_id` (`gateway_asaas_id`);

-- Inserir configuração padrão
INSERT INTO `integracao_asaas` (`api_key`, `webhook_token`, `ambiente`, `ativo`) 
VALUES ('', '', 'sandbox', 0)
ON DUPLICATE KEY UPDATE `data_atualizacao` = NOW();
```
