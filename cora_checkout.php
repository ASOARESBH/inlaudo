<?php
/**
 * Checkout CORA Banking - Boleto Registrado
 * ERP INLAUDO - VERSÃO CORRIGIDA E FUNCIONAL
 * 
 * Fluxo:
 * 1. Valida sessão do cliente
 * 2. Busca conta a receber e dados do cliente
 * 3. Carrega configuração CORA
 * 4. Emite boleto via API CORA v2
 * 5. Salva dados no banco
 * 6. Redireciona para PDF
 * 
 * Melhorias:
 * - Tratamento de erros robusto
 * - Logs detalhados
 * - Validações de segurança
 * - Suporte a ambiente stage/production
 * - Webhook pronto para receber pagamentos
 */

session_start();

// ======================================================
// CARREGAMENTO DE DEPENDÊNCIAS
// ======================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib_boleto_cora_v2.php';
require_once __DIR__ . '/../lib_logs.php';

// ======================================================
// VALIDAÇÃO DE SESSÃO
// ======================================================
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] !== 'cliente') {
    http_response_code(403);
    die(json_encode(['erro' => 'Acesso não autorizado. Faça login como cliente.']));
}

$clienteId = $_SESSION['cliente_id'] ?? 0;

if (!$clienteId) {
    http_response_code(400);
    die(json_encode(['erro' => 'Cliente não identificado na sessão.']));
}

// ======================================================
// VALIDAÇÃO DE PARÂMETROS
// ======================================================
$contaId = isset($_GET['conta_id']) ? (int)$_GET['conta_id'] : 0;

if (!$contaId) {
    http_response_code(400);
    die(json_encode(['erro' => 'Parâmetro conta_id inválido ou não fornecido.']));
}

// ======================================================
// CONEXÃO COM BANCO
// ======================================================
try {
    $conn = getConnection();
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['erro' => 'Erro ao conectar ao banco de dados.']));
}

// ======================================================
// BUSCAR CONTA + CLIENTE
// ======================================================
try {
    $stmt = $conn->prepare("
        SELECT 
            cr.id,
            cr.cliente_id,
            cr.descricao,
            cr.valor,
            cr.data_vencimento,
            cr.status,
            cr.gateway,
            cr.gateway_id,
            c.id as cliente_id_check,
            c.nome,
            c.razao_social,
            c.email,
            c.cnpj_cpf,
            c.telefone,
            c.logradouro,
            c.numero,
            c.complemento,
            c.bairro,
            c.cidade,
            c.estado,
            c.cep
        FROM contas_receber cr
        INNER JOIN clientes c ON c.id = cr.cliente_id
        WHERE cr.id = ? AND cr.cliente_id = ?
        LIMIT 1
    ");
    
    $stmt->execute([$contaId, $clienteId]);
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conta) {
        http_response_code(404);
        die(json_encode(['erro' => 'Conta não encontrada ou não pertence ao cliente.']));
    }
    
    // Validar se já foi paga
    if ($conta['status'] === 'pago') {
        http_response_code(400);
        die(json_encode(['erro' => 'Esta conta já foi paga.']));
    }
    
    // Validar se já tem boleto gerado
    if ($conta['gateway'] === 'cora' && $conta['gateway_id']) {
        // Redirecionar para boleto existente
        header('Location: ../link_pagamento/gerar_link_pagamento.php?conta_id=' . $contaId);
        exit;
    }
    
} catch (PDOException $e) {
    LogIntegracao::registrar(
        'cora_checkout',
        'buscar_conta',
        'erro',
        'Erro ao buscar conta: ' . $e->getMessage(),
        json_encode(['conta_id' => $contaId, 'cliente_id' => $clienteId]),
        null,
        0,
        0
    );
    
    http_response_code(500);
    die(json_encode(['erro' => 'Erro ao buscar dados da conta.']));
}

// ======================================================
// BUSCAR CONFIGURAÇÃO CORA
// ======================================================
try {
    $stmtCfg = $conn->prepare("
        SELECT *
        FROM integracoes
        WHERE tipo = 'cora' AND ativo = 1
        LIMIT 1
    ");
    
    $stmtCfg->execute();
    $config = $stmtCfg->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        LogIntegracao::registrar(
            'cora_checkout',
            'buscar_config',
            'erro',
            'Configuração CORA não encontrada ou desativada',
            null,
            null,
            0,
            0
        );
        
        http_response_code(500);
        die(json_encode(['erro' => 'Configuração CORA não encontrada.']));
    }
    
    // Decodificar configurações JSON
    $configJson = json_decode($config['configuracoes'], true);
    
    if (!$configJson) {
        LogIntegracao::registrar(
            'cora_checkout',
            'decodificar_config',
            'erro',
            'Erro ao decodificar JSON de configuração',
            $config['configuracoes'],
            null,
            0,
            0
        );
        
        http_response_code(500);
        die(json_encode(['erro' => 'Erro ao processar configuração CORA.']));
    }
    
    // Extrair credenciais
    $clientId       = $configJson['client_id'] ?? null;
    $ambiente       = $configJson['ambiente'] ?? 'production';
    $certificado    = $config['api_key'];
    $privateKey     = $config['api_secret'];
    
    // Validar credenciais
    if (!$clientId) {
        throw new Exception('Client ID não configurado');
    }
    
    if (!$certificado || !file_exists($certificado)) {
        throw new Exception('Certificado não encontrado: ' . $certificado);
    }
    
    if (!$privateKey || !file_exists($privateKey)) {
        throw new Exception('Chave privada não encontrada: ' . $privateKey);
    }
    
} catch (Exception $e) {
    LogIntegracao::registrar(
        'cora_checkout',
        'validar_config',
        'erro',
        'Erro ao validar configuração: ' . $e->getMessage(),
        null,
        null,
        0,
        0
    );
    
    http_response_code(500);
    die(json_encode(['erro' => 'Erro ao validar configuração CORA: ' . $e->getMessage()]));
}

// ======================================================
// INSTANCIAR API CORA
// ======================================================
try {
    $cora = new CoraAPIv2(
        $clientId,
        $certificado,
        $privateKey,
        $ambiente
    );
} catch (Exception $e) {
    LogIntegracao::registrar(
        'cora_checkout',
        'instanciar_api',
        'erro',
        'Erro ao instanciar API CORA: ' . $e->getMessage(),
        null,
        null,
        0,
        0
    );
    
    http_response_code(500);
    die(json_encode(['erro' => 'Erro ao inicializar API CORA.']));
}

// ======================================================
// MONTAR DADOS DO CLIENTE
// ======================================================
try {
    $documento = preg_replace('/\D/', '', $conta['cnpj_cpf']);
    
    // Validar documento
    if (strlen($documento) < 11) {
        throw new Exception('Documento do cliente inválido');
    }
    
    $dadosCliente = [
        'nome' => $conta['nome'] ?: $conta['razao_social'] ?: 'Cliente',
        'email' => $conta['email'] ?: 'nao-informado@email.com',
        'documento' => $documento,
        'endereco' => [
            'logradouro' => $conta['logradouro'] ?? 'Não informado',
            'numero' => $conta['numero'] ?? 'S/N',
            'complemento' => $conta['complemento'] ?? '',
            'bairro' => $conta['bairro'] ?? 'Centro',
            'cidade' => $conta['cidade'] ?? 'São Paulo',
            'uf' => $conta['estado'] ?? 'SP',
            'cep' => preg_replace('/\D/', '', $conta['cep'] ?? '00000000')
        ]
    ];
    
    // Validar dados obrigatórios
    if (empty($dadosCliente['nome'])) {
        throw new Exception('Nome do cliente não informado');
    }
    
} catch (Exception $e) {
    LogIntegracao::registrar(
        'cora_checkout',
        'montar_dados_cliente',
        'erro',
        'Erro ao montar dados do cliente: ' . $e->getMessage(),
        json_encode($conta),
        null,
        0,
        0
    );
    
    http_response_code(400);
    die(json_encode(['erro' => 'Erro ao processar dados do cliente: ' . $e->getMessage()]));
}

// ======================================================
// MONTAR DADOS DA COBRANÇA
// ======================================================
try {
    // Validar valor
    if ($conta['valor'] < 5.00) {
        throw new Exception('Valor mínimo de cobrança é R$ 5,00');
    }
    
    // Validar data de vencimento
    $dataVencimento = new DateTime($conta['data_vencimento']);
    $hoje = new DateTime('today');
    
    if ($dataVencimento < $hoje) {
        throw new Exception('Data de vencimento não pode ser anterior a hoje');
    }
    
    $dadosCobranca = [
        'codigo_unico'      => 'CR_' . $conta['id'],
        'descricao'         => $conta['descricao'] ?: 'Cobrança',
        'valor'             => (float)$conta['valor'],
        'data_vencimento'   => $conta['data_vencimento']
    ];
    
} catch (Exception $e) {
    LogIntegracao::registrar(
        'cora_checkout',
        'montar_dados_cobranca',
        'erro',
        'Erro ao montar dados da cobrança: ' . $e->getMessage(),
        json_encode($conta),
        null,
        0,
        0
    );
    
    http_response_code(400);
    die(json_encode(['erro' => 'Erro ao processar dados da cobrança: ' . $e->getMessage()]));
}

// ======================================================
// EMITIR BOLETO
// ======================================================
try {
    $tempoInicio = microtime(true);
    
    $resultado = $cora->emitirBoleto($dadosCliente, $dadosCobranca);
    
    $tempoFim = microtime(true);
    $tempoResposta = round(($tempoFim - $tempoInicio) * 1000);
    
    if (!$resultado['sucesso']) {
        LogIntegracao::registrar(
            'cora_checkout',
            'emitir_boleto',
            'erro',
            'Erro ao emitir boleto: ' . $resultado['mensagem'],
            json_encode($dadosCobranca),
            json_encode($resultado['dados']),
            0,
            $tempoResposta
        );
        
        http_response_code(500);
        die(json_encode([
            'erro' => 'Erro ao emitir boleto: ' . $resultado['mensagem'],
            'detalhes' => $resultado['dados']
        ]));
    }
    
    $boleto = $resultado['dados'];
    
    LogIntegracao::registrar(
        'cora_checkout',
        'emitir_boleto',
        'sucesso',
        'Boleto emitido com sucesso',
        json_encode($dadosCobranca),
        json_encode($boleto),
        200,
        $tempoResposta
    );
    
} catch (Exception $e) {
    LogIntegracao::registrar(
        'cora_checkout',
        'emitir_boleto',
        'erro',
        'Exceção ao emitir boleto: ' . $e->getMessage(),
        json_encode($dadosCobranca),
        null,
        0,
        0
    );
    
    http_response_code(500);
    die(json_encode(['erro' => 'Erro ao emitir boleto.']));
}

// ======================================================
// SALVAR NO BANCO DE DADOS
// ======================================================
try {
    $stmtUpd = $conn->prepare("
        UPDATE contas_receber SET
            gateway = 'cora',
            gateway_id = ?,
            linha_digitavel = ?,
            codigo_barras = ?,
            url_boleto = ?,
            url_pdf = ?,
            status_gateway = ?,
            data_atualizacao = NOW()
        WHERE id = ?
    ");
    
    $stmtUpd->execute([
        $boleto['id_cora'],
        $boleto['linha_digitavel'] ?? null,
        $boleto['codigo_barras'] ?? null,
        $boleto['url_boleto'] ?? null,
        $boleto['url_pdf'] ?? null,
        $boleto['status'] ?? 'pending',
        $conta['id']
    ]);
    
    // Registrar também na tabela de boletos se existir
    $stmtBoleto = $conn->prepare("
        INSERT INTO boletos (
            conta_receber_id,
            plataforma,
            boleto_id,
            codigo_barras,
            linha_digitavel,
            url_boleto,
            url_pdf,
            status,
            data_vencimento,
            valor,
            resposta_api,
            data_geracao
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            data_atualizacao = NOW()
    ");
    
    $stmtBoleto->execute([
        $conta['id'],
        'cora',
        $boleto['id_cora'],
        $boleto['codigo_barras'] ?? null,
        $boleto['linha_digitavel'] ?? null,
        $boleto['url_boleto'] ?? null,
        $boleto['url_pdf'] ?? null,
        $boleto['status'] ?? 'pending',
        $conta['data_vencimento'],
        $conta['valor'],
        json_encode($boleto['resposta_completa'] ?? $boleto)
    ]);
    
    LogIntegracao::registrar(
        'cora_checkout',
        'salvar_banco',
        'sucesso',
        'Dados do boleto salvos no banco',
        json_encode(['conta_id' => $conta['id'], 'boleto_id' => $boleto['id_cora']]),
        null,
        200,
        0
    );
    
} catch (PDOException $e) {
    LogIntegracao::registrar(
        'cora_checkout',
        'salvar_banco',
        'erro',
        'Erro ao salvar boleto no banco: ' . $e->getMessage(),
        json_encode(['conta_id' => $conta['id']]),
        null,
        0,
        0
    );
    
    http_response_code(500);
    die(json_encode(['erro' => 'Erro ao salvar dados do boleto.']));
}

// ======================================================
// REDIRECIONAR PARA O PDF
// ======================================================
if ($boleto['url_pdf']) {
    header('Location: ' . $boleto['url_pdf']);
    exit;
} else {
    http_response_code(500);
    die(json_encode(['erro' => 'URL do PDF não foi retornada pela API CORA.']));
}
