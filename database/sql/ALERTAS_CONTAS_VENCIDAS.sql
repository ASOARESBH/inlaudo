-- ============================================================
-- SISTEMA DE ALERTAS DE CONTAS VENCIDAS
-- ERP INLAUDO - Alertas em Popup ao Login do Administrador
-- ============================================================

-- ============================================================
-- 1. TABELA DE ALERTAS DE CONTAS VENCIDAS
-- ============================================================
CREATE TABLE IF NOT EXISTS `alertas_contas_vencidas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conta_receber_id` int(11) NOT NULL COMMENT 'ID da conta a receber vencida',
  `usuario_id` int(11) NOT NULL COMMENT 'ID do usuário que recebe o alerta',
  `tipo_alerta` enum('vencido','vencendo_hoje','vencendo_amanha','vencendo_semana') COLLATE utf8mb4_unicode_ci DEFAULT 'vencido',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `valor` decimal(10,2) NOT NULL,
  `dias_vencido` int(11) DEFAULT 0 COMMENT 'Quantos dias vencido',
  `visualizado` tinyint(1) DEFAULT 0 COMMENT 'Se foi visualizado pelo usuário',
  `data_visualizacao` timestamp NULL DEFAULT NULL,
  `acao_tomada` enum('nenhuma','ver','cancelar','ignorar') COLLATE utf8mb4_unicode_ci DEFAULT 'nenhuma',
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario_id` (`usuario_id`),
  KEY `idx_conta_receber_id` (`conta_receber_id`),
  KEY `idx_visualizado` (`visualizado`),
  KEY `idx_tipo_alerta` (`tipo_alerta`),
  KEY `idx_data_criacao` (`data_criacao`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`conta_receber_id`) REFERENCES `contas_receber`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Alertas de contas vencidas para administradores';

-- ============================================================
-- 2. TABELA DE CONFIGURAÇÃO DE ALERTAS
-- ============================================================
CREATE TABLE IF NOT EXISTS `config_alertas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
  `valor` text COLLATE utf8mb4_unicode_ci,
  `tipo` enum('boolean','integer','string','json') COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `data_atualizacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurações do sistema de alertas';

-- ============================================================
-- 3. INSERIR CONFIGURAÇÕES PADRÃO
-- ============================================================
INSERT IGNORE INTO `config_alertas` (`chave`, `valor`, `tipo`, `descricao`) VALUES
('alertas_ativados', '1', 'boolean', 'Se o sistema de alertas está ativado'),
('alertas_mostrar_popup_login', '1', 'boolean', 'Mostrar popup de alertas ao fazer login'),
('alertas_dias_vencido', '0', 'integer', 'Mostrar alertas de contas vencidas há X dias (0 = todas)'),
('alertas_dias_vencendo', '7', 'integer', 'Mostrar alertas de contas vencendo nos próximos X dias'),
('alertas_valor_minimo', '0', 'string', 'Valor mínimo da conta para gerar alerta'),
('alertas_som_ativado', '1', 'boolean', 'Reproduzir som ao mostrar alerta'),
('alertas_auto_fechar', '0', 'boolean', 'Fechar popup automaticamente após X segundos'),
('alertas_tempo_auto_fechar', '0', 'integer', 'Tempo em segundos para auto-fechar (0 = desativado)');

-- ============================================================
-- 4. PROCEDURE PARA GERAR ALERTAS DE CONTAS VENCIDAS
-- ============================================================
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS `sp_gerar_alertas_contas_vencidas`()
BEGIN
    DECLARE v_dias_vencido INT;
    DECLARE v_dias_vencendo INT;
    DECLARE v_valor_minimo DECIMAL(10,2);
    
    -- Obter configurações
    SELECT CAST(valor AS SIGNED) INTO v_dias_vencido 
    FROM config_alertas WHERE chave = 'alertas_dias_vencido' LIMIT 1;
    
    SELECT CAST(valor AS SIGNED) INTO v_dias_vencendo 
    FROM config_alertas WHERE chave = 'alertas_dias_vencendo' LIMIT 1;
    
    SELECT CAST(valor AS DECIMAL(10,2)) INTO v_valor_minimo 
    FROM config_alertas WHERE chave = 'alertas_valor_minimo' LIMIT 1;
    
    -- Definir valores padrão se não existirem
    SET v_dias_vencido = COALESCE(v_dias_vencido, 0);
    SET v_dias_vencendo = COALESCE(v_dias_vencendo, 7);
    SET v_valor_minimo = COALESCE(v_valor_minimo, 0);
    
    -- Gerar alertas para contas vencidas
    INSERT INTO alertas_contas_vencidas (
        conta_receber_id,
        usuario_id,
        tipo_alerta,
        titulo,
        descricao,
        valor,
        dias_vencido
    )
    SELECT 
        cr.id,
        u.id,
        CASE 
            WHEN DATEDIFF(CURDATE(), cr.data_vencimento) = 0 THEN 'vencido'
            WHEN DATEDIFF(CURDATE(), cr.data_vencimento) = 1 THEN 'vencido'
            ELSE 'vencido'
        END as tipo_alerta,
        CONCAT('Conta Vencida - ', cr.descricao) as titulo,
        CONCAT(
            'Cliente: ', c.nome, '\n',
            'Valor: R$ ', FORMAT(cr.valor, 2, 'pt_BR'), '\n',
            'Vencimento: ', DATE_FORMAT(cr.data_vencimento, '%d/%m/%Y'), '\n',
            'Dias Vencido: ', DATEDIFF(CURDATE(), cr.data_vencimento)
        ) as descricao,
        cr.valor,
        DATEDIFF(CURDATE(), cr.data_vencimento) as dias_vencido
    FROM contas_receber cr
    INNER JOIN clientes c ON c.id = cr.cliente_id
    CROSS JOIN usuarios u
    WHERE cr.status IN ('pendente', 'vencido')
    AND cr.data_vencimento < CURDATE()
    AND cr.valor >= v_valor_minimo
    AND u.tipo_usuario IN ('admin', 'usuario')
    AND u.ativo = 1
    AND NOT EXISTS (
        SELECT 1 FROM alertas_contas_vencidas acv
        WHERE acv.conta_receber_id = cr.id
        AND acv.usuario_id = u.id
        AND DATE(acv.data_criacao) = CURDATE()
    )
    ON DUPLICATE KEY UPDATE
        data_atualizacao = NOW();
    
    -- Gerar alertas para contas vencendo
    INSERT INTO alertas_contas_vencidas (
        conta_receber_id,
        usuario_id,
        tipo_alerta,
        titulo,
        descricao,
        valor,
        dias_vencido
    )
    SELECT 
        cr.id,
        u.id,
        CASE 
            WHEN DATEDIFF(cr.data_vencimento, CURDATE()) = 0 THEN 'vencendo_hoje'
            WHEN DATEDIFF(cr.data_vencimento, CURDATE()) = 1 THEN 'vencendo_amanha'
            ELSE 'vencendo_semana'
        END as tipo_alerta,
        CONCAT('Conta Vencendo - ', cr.descricao) as titulo,
        CONCAT(
            'Cliente: ', c.nome, '\n',
            'Valor: R$ ', FORMAT(cr.valor, 2, 'pt_BR'), '\n',
            'Vencimento: ', DATE_FORMAT(cr.data_vencimento, '%d/%m/%Y'), '\n',
            'Dias para Vencer: ', DATEDIFF(cr.data_vencimento, CURDATE())
        ) as descricao,
        cr.valor,
        DATEDIFF(cr.data_vencimento, CURDATE()) as dias_vencido
    FROM contas_receber cr
    INNER JOIN clientes c ON c.id = cr.cliente_id
    CROSS JOIN usuarios u
    WHERE cr.status = 'pendente'
    AND cr.data_vencimento > CURDATE()
    AND cr.data_vencimento <= DATE_ADD(CURDATE(), INTERVAL v_dias_vencendo DAY)
    AND cr.valor >= v_valor_minimo
    AND u.tipo_usuario IN ('admin', 'usuario')
    AND u.ativo = 1
    AND NOT EXISTS (
        SELECT 1 FROM alertas_contas_vencidas acv
        WHERE acv.conta_receber_id = cr.id
        AND acv.usuario_id = u.id
        AND DATE(acv.data_criacao) = CURDATE()
    )
    ON DUPLICATE KEY UPDATE
        data_atualizacao = NOW();
        
END$$

DELIMITER ;

-- ============================================================
-- 5. PROCEDURE PARA OBTER ALERTAS NÃO VISUALIZADOS
-- ============================================================
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS `sp_obter_alertas_nao_visualizados`(IN p_usuario_id INT)
BEGIN
    SELECT 
        acv.id,
        acv.conta_receber_id,
        acv.tipo_alerta,
        acv.titulo,
        acv.descricao,
        acv.valor,
        acv.dias_vencido,
        acv.data_criacao,
        cr.descricao as conta_descricao,
        cr.data_vencimento,
        c.nome as cliente_nome,
        c.cnpj_cpf
    FROM alertas_contas_vencidas acv
    INNER JOIN contas_receber cr ON cr.id = acv.conta_receber_id
    INNER JOIN clientes c ON c.id = cr.cliente_id
    WHERE acv.usuario_id = p_usuario_id
    AND acv.visualizado = 0
    ORDER BY 
        CASE acv.tipo_alerta
            WHEN 'vencido' THEN 1
            WHEN 'vencendo_hoje' THEN 2
            WHEN 'vencendo_amanha' THEN 3
            WHEN 'vencendo_semana' THEN 4
        END,
        acv.valor DESC,
        acv.data_criacao DESC;
END$$

DELIMITER ;

-- ============================================================
-- 6. PROCEDURE PARA MARCAR ALERTA COMO VISUALIZADO
-- ============================================================
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS `sp_marcar_alerta_visualizado`(
    IN p_alerta_id INT,
    IN p_acao VARCHAR(50)
)
BEGIN
    UPDATE alertas_contas_vencidas
    SET 
        visualizado = 1,
        data_visualizacao = NOW(),
        acao_tomada = p_acao
    WHERE id = p_alerta_id;
END$$

DELIMITER ;

-- ============================================================
-- 7. PROCEDURE PARA LIMPAR ALERTAS ANTIGOS
-- ============================================================
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS `sp_limpar_alertas_antigos`(IN p_dias INT)
BEGIN
    DELETE FROM alertas_contas_vencidas
    WHERE data_criacao < DATE_SUB(NOW(), INTERVAL p_dias DAY)
    AND visualizado = 1;
END$$

DELIMITER ;

-- ============================================================
-- 8. VIEW PARA RESUMO DE ALERTAS
-- ============================================================
CREATE OR REPLACE VIEW `v_alertas_resumo` AS
SELECT 
    u.id as usuario_id,
    u.nome as usuario_nome,
    COUNT(*) as total_alertas,
    SUM(CASE WHEN acv.tipo_alerta = 'vencido' THEN 1 ELSE 0 END) as alertas_vencidos,
    SUM(CASE WHEN acv.tipo_alerta = 'vencendo_hoje' THEN 1 ELSE 0 END) as alertas_vencendo_hoje,
    SUM(CASE WHEN acv.tipo_alerta = 'vencendo_amanha' THEN 1 ELSE 0 END) as alertas_vencendo_amanha,
    SUM(CASE WHEN acv.tipo_alerta = 'vencendo_semana' THEN 1 ELSE 0 END) as alertas_vencendo_semana,
    SUM(CASE WHEN acv.visualizado = 0 THEN 1 ELSE 0 END) as alertas_nao_visualizados,
    SUM(acv.valor) as valor_total_alertas
FROM usuarios u
LEFT JOIN alertas_contas_vencidas acv ON acv.usuario_id = u.id
WHERE u.ativo = 1
AND u.tipo_usuario IN ('admin', 'usuario')
GROUP BY u.id, u.nome;

-- ============================================================
-- 9. VIEW PARA ALERTAS CRÍTICOS
-- ============================================================
CREATE OR REPLACE VIEW `v_alertas_criticos` AS
SELECT 
    acv.id,
    acv.conta_receber_id,
    acv.usuario_id,
    acv.tipo_alerta,
    acv.titulo,
    acv.valor,
    acv.dias_vencido,
    c.nome as cliente_nome,
    cr.data_vencimento,
    u.nome as usuario_nome,
    u.email
FROM alertas_contas_vencidas acv
INNER JOIN contas_receber cr ON cr.id = acv.conta_receber_id
INNER JOIN clientes c ON c.id = cr.cliente_id
INNER JOIN usuarios u ON u.id = acv.usuario_id
WHERE acv.visualizado = 0
AND (
    acv.tipo_alerta = 'vencido'
    OR (acv.tipo_alerta = 'vencendo_hoje' AND acv.dias_vencido <= 0)
)
ORDER BY acv.valor DESC, acv.dias_vencido DESC;

-- ============================================================
-- 10. ÍNDICES PARA PERFORMANCE
-- ============================================================
CREATE INDEX IF NOT EXISTS `idx_alertas_usuario_visualizado` 
ON `alertas_contas_vencidas`(`usuario_id`, `visualizado`);

CREATE INDEX IF NOT EXISTS `idx_alertas_tipo_data` 
ON `alertas_contas_vencidas`(`tipo_alerta`, `data_criacao`);

CREATE INDEX IF NOT EXISTS `idx_alertas_valor` 
ON `alertas_contas_vencidas`(`valor`);

-- ============================================================
-- 11. TRIGGER PARA ATUALIZAR CONTA AO MARCAR ALERTA
-- ============================================================
DELIMITER $$

CREATE TRIGGER IF NOT EXISTS `tr_alerta_cancelar_conta`
AFTER UPDATE ON `alertas_contas_vencidas`
FOR EACH ROW
BEGIN
    IF NEW.acao_tomada = 'cancelar' AND OLD.acao_tomada != 'cancelar' THEN
        UPDATE contas_receber 
        SET status = 'cancelado'
        WHERE id = NEW.conta_receber_id;
    END IF;
END$$

DELIMITER ;

-- ============================================================
-- 12. SCHEDULE PARA GERAR ALERTAS AUTOMATICAMENTE
-- ============================================================
-- Executar a cada hora para gerar novos alertas
-- UNCOMMENT para ativar:
-- CREATE EVENT IF NOT EXISTS `evt_gerar_alertas_horario`
-- ON SCHEDULE EVERY 1 HOUR
-- DO CALL sp_gerar_alertas_contas_vencidas();

-- ============================================================
-- COMMIT
-- ============================================================
COMMIT;

-- ============================================================
-- MENSAGENS DE SUCESSO
-- ============================================================
-- Tabelas e procedures criadas com sucesso!
--
-- Próximos passos:
-- 1. Executar: CALL sp_gerar_alertas_contas_vencidas();
-- 2. Verificar alertas: SELECT * FROM alertas_contas_vencidas;
-- 3. Integrar ao login.php
-- 4. Criar modal de alertas no JavaScript
