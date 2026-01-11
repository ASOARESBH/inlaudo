-- ============================================================
-- TABELAS PARA INTEGRAÇÃO CORA COM LOGS DETALHADOS
-- ERP INLAUDO - Versão Corrigida
-- ============================================================

-- ============================================================
-- 1. TABELA DE LOGS DE INTEGRAÇÃO (MELHORADA)
-- ============================================================
CREATE TABLE IF NOT EXISTS `logs_integracao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sistema` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Sistema (cora_api_v2, webhook_cora, etc)',
  `operacao` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Operação realizada',
  `tipo` enum('sucesso','erro','aviso','info') COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `dados_entrada` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Dados enviados (JSON)',
  `dados_saida` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Dados recebidos (JSON)',
  `codigo_http` int(11) DEFAULT NULL,
  `tempo_resposta_ms` int(11) DEFAULT 0 COMMENT 'Tempo de resposta em milissegundos',
  `ip_cliente` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sistema` (`sistema`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_data_criacao` (`data_criacao`),
  KEY `idx_usuario_id` (`usuario_id`),
  FULLTEXT KEY `ft_mensagem` (`mensagem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log detalhado de integrações com APIs';

-- ============================================================
-- 2. TABELA DE WEBHOOKS RECEBIDOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `webhooks_pagamento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Gateway (cora, stripe, mercadopago)',
  `gateway_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID do recurso no gateway',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Status do pagamento',
  `payload` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Payload completo do webhook (JSON)',
  `processado` tinyint(1) DEFAULT 0 COMMENT 'Se foi processado com sucesso',
  `erro_processamento` text COLLATE utf8mb4_unicode_ci COMMENT 'Erro ao processar, se houver',
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_processamento` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_webhook_unique` (`gateway`, `gateway_id`, `data_criacao`),
  KEY `idx_gateway` (`gateway`),
  KEY `idx_status` (`status`),
  KEY `idx_processado` (`processado`),
  KEY `idx_data_criacao` (`data_criacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de webhooks recebidos';

-- ============================================================
-- 3. TABELA DE CONFIGURAÇÃO DE WEBHOOKS
-- ============================================================
CREATE TABLE IF NOT EXISTS `webhook_endpoints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID do endpoint no gateway',
  `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL do webhook',
  `resource` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Recurso (invoice, payment, etc)',
  `trigger` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Gatilho (paid, canceled, etc)',
  `ativo` tinyint(1) DEFAULT 1,
  `webhook_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Secret para validar assinatura',
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_endpoint` (`gateway`, `endpoint_id`),
  KEY `idx_gateway` (`gateway`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuração de webhooks por gateway';

-- ============================================================
-- 4. TABELA DE BOLETOS (MELHORADA)
-- ============================================================
CREATE TABLE IF NOT EXISTS `boletos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conta_receber_id` int(11) NOT NULL,
  `plataforma` enum('cora','stripe','mercadopago') COLLATE utf8mb4_unicode_ci NOT NULL,
  `boleto_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID do boleto na plataforma',
  `codigo_barras` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linha_digitavel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_boleto` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_pdf` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_code_pix` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'QR Code Pix se disponível',
  `pix_copia_cola` text COLLATE utf8mb4_unicode_ci COMMENT 'Chave Pix cópia e cola',
  `status` enum('pendente','pago','cancelado','vencido','parcialmente_pago') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `data_vencimento` date NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `valor_pago` decimal(10,2) DEFAULT 0.00,
  `data_pagamento` timestamp NULL DEFAULT NULL,
  `resposta_api` longtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON da resposta da API',
  `data_geracao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conta_receber_id` (`conta_receber_id`),
  KEY `idx_boleto_id` (`boleto_id`),
  KEY `idx_status` (`status`),
  KEY `idx_data_vencimento` (`data_vencimento`),
  KEY `idx_plataforma` (`plataforma`),
  FOREIGN KEY (`conta_receber_id`) REFERENCES `contas_receber`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de boletos emitidos';

-- ============================================================
-- 5. TABELA DE TRANSAÇÕES DE PAGAMENTO (MELHORADA)
-- ============================================================
CREATE TABLE IF NOT EXISTS `transacoes_pagamento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conta_receber_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `gateway` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gateway_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID da transação no gateway',
  `tipo` enum('boleto','pix','cartao','transferencia') COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `status` enum('pendente','processando','sucesso','falha','cancelado') COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `resposta_gateway` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Resposta completa do gateway (JSON)',
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conta_receber_id` (`conta_receber_id`),
  KEY `idx_cliente_id` (`cliente_id`),
  KEY `idx_gateway_id` (`gateway_id`),
  KEY `idx_status` (`status`),
  KEY `idx_gateway` (`gateway`),
  FOREIGN KEY (`conta_receber_id`) REFERENCES `contas_receber`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de transações de pagamento';

-- ============================================================
-- 6. TABELA DE CONFIGURAÇÃO DE INTEGRAÇÕES (MELHORADA)
-- ============================================================
CREATE TABLE IF NOT EXISTS `integracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo (cora, stripe, mercadopago)',
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ativo` tinyint(1) DEFAULT 0,
  `ambiente` enum('stage','production') COLLATE utf8mb4_unicode_ci DEFAULT 'production',
  `configuracoes` longtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON com configurações',
  `api_key` text COLLATE utf8mb4_unicode_ci COMMENT 'Chave/Certificado da API',
  `api_secret` text COLLATE utf8mb4_unicode_ci COMMENT 'Secret/Chave privada da API',
  `webhook_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Secret para validar webhooks',
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tipo_ambiente` (`tipo`, `ambiente`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuração de integrações com gateways';

-- ============================================================
-- 7. ÍNDICES ADICIONAIS PARA PERFORMANCE
-- ============================================================
ALTER TABLE `contas_receber` ADD COLUMN IF NOT EXISTS `gateway` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `contas_receber` ADD COLUMN IF NOT EXISTS `gateway_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `contas_receber` ADD COLUMN IF NOT EXISTS `status_gateway` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `contas_receber` ADD COLUMN IF NOT EXISTS `url_boleto` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `contas_receber` ADD COLUMN IF NOT EXISTS `url_pdf` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `contas_receber` ADD COLUMN IF NOT EXISTS `linha_digitavel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `contas_receber` ADD COLUMN IF NOT EXISTS `codigo_barras` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL;

CREATE INDEX IF NOT EXISTS `idx_gateway` ON `contas_receber`(`gateway`);
CREATE INDEX IF NOT EXISTS `idx_gateway_id` ON `contas_receber`(`gateway_id`);
CREATE INDEX IF NOT EXISTS `idx_status_gateway` ON `contas_receber`(`status_gateway`);

-- ============================================================
-- 8. DADOS INICIAIS - EXEMPLO DE CONFIGURAÇÃO CORA
-- ============================================================
INSERT IGNORE INTO `integracoes` (
  `tipo`,
  `nome`,
  `ativo`,
  `ambiente`,
  `configuracoes`,
  `api_key`,
  `api_secret`,
  `webhook_secret`
) VALUES (
  'cora',
  'Cora Banking - Integração Direta',
  0,
  'production',
  '{"client_id":"seu_client_id_aqui","ambiente":"production"}',
  '/path/to/certificate.pem',
  '/path/to/private_key.pem',
  'seu_webhook_secret_aqui'
);

-- ============================================================
-- 9. VIEW PARA MONITORAMENTO DE INTEGRAÇÕES
-- ============================================================
CREATE OR REPLACE VIEW `v_logs_integracao_recentes` AS
SELECT 
  l.id,
  l.sistema,
  l.operacao,
  l.tipo,
  l.mensagem,
  l.codigo_http,
  l.tempo_resposta_ms,
  l.data_criacao,
  COUNT(*) OVER (PARTITION BY l.sistema, l.tipo) as count_tipo
FROM logs_integracao l
WHERE l.data_criacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY l.data_criacao DESC;

-- ============================================================
-- 10. VIEW PARA ANÁLISE DE WEBHOOKS
-- ============================================================
CREATE OR REPLACE VIEW `v_webhooks_analise` AS
SELECT 
  w.gateway,
  w.status,
  COUNT(*) as total,
  SUM(CASE WHEN w.processado = 1 THEN 1 ELSE 0 END) as processados,
  SUM(CASE WHEN w.processado = 0 THEN 1 ELSE 0 END) as nao_processados,
  MAX(w.data_criacao) as ultimo_webhook,
  DATE(w.data_criacao) as data
FROM webhooks_pagamento w
GROUP BY w.gateway, w.status, DATE(w.data_criacao)
ORDER BY w.data_criacao DESC;

-- ============================================================
-- 11. PROCEDURE PARA LIMPEZA DE LOGS ANTIGOS
-- ============================================================
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS `sp_limpar_logs_antigos`(IN dias_retencao INT)
BEGIN
  DELETE FROM logs_integracao 
  WHERE data_criacao < DATE_SUB(NOW(), INTERVAL dias_retencao DAY);
  
  DELETE FROM webhooks_pagamento 
  WHERE data_criacao < DATE_SUB(NOW(), INTERVAL dias_retencao DAY)
  AND processado = 1;
END$$

DELIMITER ;

-- ============================================================
-- 12. PROCEDURE PARA REPROCESSAR WEBHOOKS FALHADOS
-- ============================================================
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS `sp_reprocessar_webhooks_falhados`()
BEGIN
  SELECT 
    id,
    gateway,
    gateway_id,
    payload,
    erro_processamento
  FROM webhooks_pagamento
  WHERE processado = 0
  AND data_criacao >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
  ORDER BY data_criacao ASC;
END$$

DELIMITER ;

-- ============================================================
-- 13. TRIGGER PARA ATUALIZAR CONTAS RECEBER
-- ============================================================
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS `tr_boleto_atualiza_conta` 
AFTER UPDATE ON `boletos`
FOR EACH ROW
BEGIN
  IF NEW.status = 'pago' AND OLD.status != 'pago' THEN
    UPDATE contas_receber 
    SET 
      status = 'pago',
      data_pagamento = NOW(),
      data_atualizacao = NOW()
    WHERE id = NEW.conta_receber_id;
  END IF;
END$$

DELIMITER ;

-- ============================================================
-- COMMIT
-- ============================================================
COMMIT;

-- ============================================================
-- MENSAGENS DE SUCESSO
-- ============================================================
-- Tabelas criadas com sucesso!
-- 
-- Próximos passos:
-- 1. Configurar credenciais da CORA em integracoes
-- 2. Registrar webhook endpoint na CORA
-- 3. Testar emissão de boleto
-- 4. Validar recebimento de webhooks
-- 5. Monitorar logs em logs_integracao
