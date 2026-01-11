-- ============================================================
-- Migrações - Módulo Notas Fiscais (NF-e/NFC-e)
-- ============================================================
-- Data: 06/01/2026
-- Descrição: Criação de tabelas para importação e armazenamento de NF-e/NFC-e

-- ============================================================
-- FORNECEDORES (Emitentes de NF-e)
-- ============================================================
CREATE TABLE IF NOT EXISTS fornecedores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cnpj VARCHAR(20) UNIQUE NOT NULL,
    nome_fantasia VARCHAR(255),
    razao_social VARCHAR(255),
    email VARCHAR(255),
    telefone VARCHAR(20),
    endereco TEXT,
    cidade VARCHAR(100),
    estado VARCHAR(2),
    cep VARCHAR(10),
    ativo BOOLEAN DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cnpj (cnpj),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NOTAS FISCAIS
-- ============================================================
CREATE TABLE IF NOT EXISTS notas_fiscais (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave_acesso VARCHAR(44) UNIQUE NOT NULL,
    tipo_nota ENUM('nfe', 'nfce') DEFAULT 'nfe',
    fornecedor_id INT NOT NULL,
    cnpj_fornecedor VARCHAR(20),
    nome_fornecedor VARCHAR(255),
    data_emissao DATE,
    data_saida_entrada DATE,
    valor_total DECIMAL(12, 2),
    valor_icms DECIMAL(12, 2) DEFAULT 0,
    valor_ipi DECIMAL(12, 2) DEFAULT 0,
    valor_pis DECIMAL(12, 2) DEFAULT 0,
    valor_cofins DECIMAL(12, 2) DEFAULT 0,
    numero_nf VARCHAR(20),
    serie_nf VARCHAR(10),
    natureza_operacao VARCHAR(255),
    tipo_documento ENUM('produto', 'servico', 'misto') DEFAULT 'produto',
    status_nfe ENUM('autorizada', 'cancelada', 'denegada', 'pendente') DEFAULT 'autorizada',
    protocolo_autorizacao VARCHAR(20),
    caminho_arquivo VARCHAR(500),
    caminho_arquivo_normalizado VARCHAR(500),
    hash_xml VARCHAR(64),
    tamanho_arquivo INT,
    usuario_id INT,
    data_importacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_chave_acesso (chave_acesso),
    INDEX idx_fornecedor_id (fornecedor_id),
    INDEX idx_data_emissao (data_emissao),
    INDEX idx_tipo_nota (tipo_nota),
    INDEX idx_status_nfe (status_nfe),
    INDEX idx_hash_xml (hash_xml),
    INDEX idx_data_importacao (data_importacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ITENS DA NOTA FISCAL
-- ============================================================
CREATE TABLE IF NOT EXISTS notas_fiscais_itens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nota_fiscal_id INT NOT NULL,
    numero_item INT,
    codigo_produto VARCHAR(60),
    descricao_produto TEXT,
    quantidade DECIMAL(15, 4),
    unidade_medida VARCHAR(6),
    valor_unitario DECIMAL(12, 4),
    valor_total DECIMAL(12, 2),
    valor_desconto DECIMAL(12, 2) DEFAULT 0,
    valor_icms DECIMAL(12, 2) DEFAULT 0,
    aliquota_icms DECIMAL(5, 2) DEFAULT 0,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nota_fiscal_id) REFERENCES notas_fiscais(id) ON DELETE CASCADE,
    INDEX idx_nota_fiscal_id (nota_fiscal_id),
    INDEX idx_codigo_produto (codigo_produto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LOG DE IMPORTAÇÃO
-- ============================================================
CREATE TABLE IF NOT EXISTS notas_fiscais_log_importacao (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    nome_arquivo VARCHAR(255),
    status ENUM('sucesso', 'erro', 'duplicado', 'invalido') DEFAULT 'sucesso',
    mensagem_erro TEXT,
    chave_acesso VARCHAR(44),
    dados_xml JSON,
    data_importacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_status (status),
    INDEX idx_chave_acesso (chave_acesso),
    INDEX idx_data_importacao (data_importacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PERMISSÕES DE ACESSO AO MÓDULO
-- ============================================================
CREATE TABLE IF NOT EXISTS permissoes_notas_fiscais (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    tipo_permissao ENUM('visualizar', 'importar', 'deletar', 'exportar', 'gerenciar') DEFAULT 'visualizar',
    ativo BOOLEAN DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuario_permissao (usuario_id, tipo_permissao),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_tipo_permissao (tipo_permissao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONFIGURAÇÕES DO MÓDULO
-- ============================================================
CREATE TABLE IF NOT EXISTS notas_fiscais_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor LONGTEXT,
    tipo ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    descricao TEXT,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ÍNDICES ADICIONAIS PARA PERFORMANCE
-- ============================================================
CREATE INDEX idx_nf_fornecedor_data ON notas_fiscais(fornecedor_id, data_emissao);
CREATE INDEX idx_nf_tipo_status ON notas_fiscais(tipo_nota, status_nfe);
CREATE INDEX idx_nf_valor_data ON notas_fiscais(valor_total, data_emissao);

-- ============================================================
-- DADOS INICIAIS
-- ============================================================

-- Inserir configurações padrão
INSERT INTO notas_fiscais_config (chave, valor, tipo, descricao) VALUES
('caminho_armazenamento', 'storage/notas_fiscais', 'string', 'Caminho base para armazenamento de NF-e'),
('tamanho_maximo_arquivo', '10485760', 'integer', 'Tamanho máximo de arquivo em bytes (10MB)'),
('permitir_duplicatas', 'false', 'boolean', 'Permitir importação de notas fiscais duplicadas'),
('validar_assinatura', 'true', 'boolean', 'Validar assinatura digital do XML'),
('arquivar_xml_original', 'true', 'boolean', 'Manter cópia do XML original'),
('organizar_por_fornecedor', 'true', 'boolean', 'Organizar arquivos por pasta de fornecedor');

-- Inserir permissões padrão para admin
-- (Será preenchido dinamicamente durante a instalação)
