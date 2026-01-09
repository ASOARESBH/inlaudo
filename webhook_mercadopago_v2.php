<?php
/**
 * Webhook Mercado Pago - Versão 7.4 MELHORADA
 * ERP INLAUDO
 * 
 * Melhorias:
 * - Logs detalhados
 * - Busca por payment_id como fallback
 * - Tratamento robusto de erros
 * - Sempre retorna 200 OK
 * - Suporte a múltiplas tabelas de configuração
 */

// Sempre retornar 200 OK para o Mercado Pago
http_response_code(200);
header('Content-Type: application/json');

// ===============================
// CONFIGURAÇÃO DE LOGS
// ===============================
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/webhook_mercadopago.log';
$debugFile = $logDir . '/webhook_mp_debug.log';

function logMP($msg, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] [{$level}] {$msg}" . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

function logDebug($msg) {
    global $debugFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$msg}" . PHP_EOL;
    @file_put_contents($debugFile, $line, FILE_APPEND);
}

logMP('========== WEBHOOK INICIADO ==========');
logDebug('Webhook chamado - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// ===============================
// CARREGAR CONFIGURAÇÃO
// ===============================
try {
    require_once 'config.php';
} catch (Exception $e) {
    logMP('ERRO ao carregar config.php: ' . $e->getMessage(), 'ERROR');
    echo json_encode(['ok' => true, 'error' => 'config_error']);
    exit;
}

// ===============================
// LER PAYLOAD
// ===============================
$input = file_get_contents('php://input');
logDebug('Payload recebido: ' . $input);

$payload = json_decode($input, true);

if (!$payload) {
    logMP('Payload vazio ou inválido', 'WARNING');
    echo json_encode(['ok' => true, 'error' => 'invalid_payload']);
    exit;
}

// Verificar se tem payment ID
if (empty($payload['data']['id'])) {
    logMP('Payload sem data.id', 'WARNING');
    logDebug('Payload completo: ' . print_r($payload, true));
    echo json_encode(['ok' => true, 'error' => 'no_payment_id']);
    exit;
}

$paymentId = $payload['data']['id'];
$eventType = $payload['type'] ?? 'unknown';

logMP("Payment ID: {$paymentId} | Event: {$eventType}");

// ===============================
// CONECTAR AO BANCO
// ===============================
try {
    $conn = getConnection();
    logDebug('Conexão com banco estabelecida');
} catch (Exception $e) {
    logMP('ERRO ao conectar ao banco: ' . $e->getMessage(), 'ERROR');
    echo json_encode(['ok' => true, 'error' => 'db_connection_error']);
    exit;
}

// ===============================
// BUSCAR CREDENCIAIS DO MERCADO PAGO
// ===============================
$accessToken = null;

// Tentar 1: configuracoes_gateway
try {
    $stmt = $conn->prepare("
        SELECT access_token, webhook_url
        FROM configuracoes_gateway
        WHERE gateway = 'mercadopago'
        AND ativo = 1
        LIMIT 1
    ");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config && !empty($config['access_token'])) {
        $accessToken = $config['access_token'];
        logMP('Access token encontrado em configuracoes_gateway');
    }
} catch (Exception $e) {
    logDebug('Tabela configuracoes_gateway não encontrada ou erro: ' . $e->getMessage());
}

// Tentar 2: integracoes_pagamento (fallback)
if (!$accessToken) {
    try {
        $stmt = $conn->prepare("
            SELECT mp_access_token
            FROM integracoes_pagamento
            WHERE gateway = 'mercadopago'
            AND ativo = 1
            LIMIT 1
        ");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config && !empty($config['mp_access_token'])) {
            $accessToken = $config['mp_access_token'];
            logMP('Access token encontrado em integracoes_pagamento');
        }
    } catch (Exception $e) {
        logDebug('Tabela integracoes_pagamento não encontrada ou erro: ' . $e->getMessage());
    }
}

if (!$accessToken) {
    logMP('Access token não encontrado em nenhuma tabela', 'ERROR');
    echo json_encode(['ok' => true, 'error' => 'no_access_token']);
    exit;
}

// ===============================
// CONSULTAR PAGAMENTO NA API DO MERCADO PAGO
// ===============================
logMP('Consultando API do Mercado Pago...');

$ch = curl_init("https://api.mercadopago.com/v1/payments/{$paymentId}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    logMP("ERRO cURL: {$curlError}", 'ERROR');
    echo json_encode(['ok' => true, 'error' => 'curl_error']);
    exit;
}

if ($httpCode != 200) {
    logMP("API retornou HTTP {$httpCode}", 'ERROR');
    logDebug("Resposta da API: {$response}");
    echo json_encode(['ok' => true, 'error' => 'api_error', 'http_code' => $httpCode]);
    exit;
}

$payment = json_decode($response, true);

if (!$payment || empty($payment['status'])) {
    logMP('Resposta da API inválida', 'ERROR');
    logDebug("Resposta: {$response}");
    echo json_encode(['ok' => true, 'error' => 'invalid_api_response']);
    exit;
}

$status = $payment['status'];
$statusDetail = $payment['status_detail'] ?? '';
$externalReference = $payment['external_reference'] ?? '';

logMP("Status: {$status} | Detail: {$statusDetail} | External Ref: {$externalReference}");

// ===============================
// PROCESSAR APENAS PAGAMENTOS APROVADOS
// ===============================
if ($status !== 'approved') {
    logMP("Pagamento não aprovado (status: {$status}), ignorando");
    echo json_encode(['ok' => true, 'status' => $status]);
    exit;
}

// ===============================
// IDENTIFICAR CONTA A RECEBER
// ===============================
$contaId = null;

// Método 1: Via external_reference
if (!empty($externalReference)) {
    // Formato esperado: conta_123
    if (preg_match('/conta_(\d+)/', $externalReference, $matches)) {
        $contaId = (int)$matches[1];
        logMP("Conta identificada via external_reference: {$contaId}");
    }
}

// Método 2: Via payment_id (fallback)
if (!$contaId) {
    logMP('Tentando identificar conta via payment_id...');
    
    try {
        $stmt = $conn->prepare("
            SELECT id 
            FROM contas_receber 
            WHERE payment_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$paymentId]);
        $conta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conta) {
            $contaId = $conta['id'];
            logMP("Conta identificada via payment_id: {$contaId}");
        }
    } catch (Exception $e) {
        logMP("ERRO ao buscar conta por payment_id: " . $e->getMessage(), 'ERROR');
    }
}

if (!$contaId || $contaId <= 0) {
    logMP('Não foi possível identificar a conta a receber', 'WARNING');
    echo json_encode(['ok' => true, 'error' => 'conta_not_found']);
    exit;
}

// ===============================
// ATUALIZAR CONTA A RECEBER
// ===============================
logMP("Atualizando conta {$contaId} para status PAGO...");

try {
    $stmt = $conn->prepare("
        UPDATE contas_receber
        SET status = 'pago',
            data_pagamento = NOW(),
            gateway = 'mercadopago',
            payment_id = ?
        WHERE id = ?
        AND status <> 'pago'
    ");
    
    $stmt->execute([$paymentId, $contaId]);
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected > 0) {
        logMP("✅ Conta {$contaId} atualizada com sucesso! ({$rowsAffected} linha(s) afetada(s))", 'SUCCESS');
    } else {
        logMP("⚠️ Nenhuma linha afetada (conta {$contaId} já estava paga ou não existe)", 'WARNING');
    }
    
} catch (Exception $e) {
    logMP("ERRO ao atualizar conta {$contaId}: " . $e->getMessage(), 'ERROR');
    echo json_encode(['ok' => true, 'error' => 'update_error']);
    exit;
}

// ===============================
// ATUALIZAR CONTAS A PAGAR (OPCIONAL)
// ===============================
try {
    $stmt = $conn->prepare("
        UPDATE contas_pagar
        SET status = 'pago',
            data_pagamento = NOW()
        WHERE conta_receber_id = ?
        AND status <> 'pago'
    ");
    
    $stmt->execute([$contaId]);
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected > 0) {
        logMP("✅ Contas a pagar vinculadas atualizadas ({$rowsAffected} linha(s))", 'SUCCESS');
    }
    
} catch (Exception $e) {
    logDebug("Erro ao atualizar contas_pagar (pode não existir): " . $e->getMessage());
}

// ===============================
// ATUALIZAR TRANSACOES_PAGAMENTO (SE EXISTIR)
// ===============================
try {
    $stmt = $conn->prepare("
        UPDATE transacoes_pagamento
        SET status = 'approved',
            data_atualizacao = NOW(),
            response_json = ?
        WHERE payment_id = ?
        OR transaction_id = ?
    ");
    
    $stmt->execute([
        json_encode($payment),
        $paymentId,
        $paymentId
    ]);
    
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected > 0) {
        logMP("✅ Transação de pagamento atualizada ({$rowsAffected} linha(s))", 'SUCCESS');
    }
    
} catch (Exception $e) {
    logDebug("Erro ao atualizar transacoes_pagamento (pode não existir): " . $e->getMessage());
}

// ===============================
// REGISTRAR WEBHOOK NO BANCO (SE TABELA EXISTIR)
// ===============================
try {
    $stmt = $conn->prepare("
        INSERT INTO webhooks_pagamento (
            gateway,
            evento,
            transaction_id,
            payload,
            processado,
            data_recebimento,
            data_processamento
        ) VALUES (?, ?, ?, ?, 1, NOW(), NOW())
    ");
    
    $stmt->execute([
        'mercadopago',
        $eventType,
        $paymentId,
        $input
    ]);
    
    logDebug("Webhook registrado no banco");
    
} catch (Exception $e) {
    logDebug("Erro ao registrar webhook (tabela pode não existir): " . $e->getMessage());
}

// ===============================
// RESPOSTA FINAL
// ===============================
logMP('========== WEBHOOK FINALIZADO COM SUCESSO ==========');

echo json_encode([
    'ok' => true,
    'payment_id' => $paymentId,
    'conta_id' => $contaId,
    'status' => 'updated'
]);

exit;
?>
