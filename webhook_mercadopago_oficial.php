<?php
/**
 * Webhook Mercado Pago - Implementação Oficial
 * 
 * Baseado na documentação oficial do Mercado Pago:
 * https://www.mercadopago.com.br/developers/pt/docs/your-integrations/notifications/webhooks
 * 
 * Funcionalidades:
 * - Recebe notificações em tempo real
 * - Valida assinatura secreta (x-signature) usando HMAC SHA256
 * - Grava todas as notificações no BD
 * - Responde 200 OK imediatamente
 * - Processamento assíncrono via CRON
 * 
 * Versão: 8.0
 * Data: 31/12/2025
 * Autor: Manus AI
 */

// Desabilitar exibição de erros (produção)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Arquivo de log
define('LOG_FILE', __DIR__ . '/logs/webhook_mercadopago.log');

/**
 * Registrar log
 */
function registrarLog($mensagem) {
    $timestamp = date('Y-m-d H:i:s');
    $logDir = dirname(LOG_FILE);
    
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    @file_put_contents(LOG_FILE, "[$timestamp] $mensagem\n", FILE_APPEND);
}

/**
 * Responder e encerrar
 */
function responder($codigo, $mensagem) {
    http_response_code($codigo);
    header('Content-Type: application/json');
    echo json_encode(['status' => $mensagem]);
    exit;
}

// ============================================
// 1. RECEBER DADOS DA NOTIFICAÇÃO
// ============================================

try {
    // Capturar body raw
    $bodyRaw = file_get_contents('php://input');
    
    // Capturar headers
    $headers = getallheaders();
    
    // Registrar recebimento
    registrarLog("=== WEBHOOK RECEBIDO ===");
    registrarLog("IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    registrarLog("Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
    registrarLog("Headers: " . json_encode($headers));
    registrarLog("Body: " . $bodyRaw);
    
    // Validar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        registrarLog("ERRO: Método não permitido");
        responder(405, 'method_not_allowed');
    }
    
    // Validar body
    if (empty($bodyRaw)) {
        registrarLog("ERRO: Body vazio");
        responder(400, 'empty_body');
    }
    
    // Decodificar JSON
    $payload = json_decode($bodyRaw, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        registrarLog("ERRO: JSON inválido - " . json_last_error_msg());
        responder(400, 'invalid_json');
    }
    
    // Extrair dados principais
    $type = $payload['type'] ?? '';
    $action = $payload['action'] ?? '';
    $dataId = $payload['data']['id'] ?? '';
    
    registrarLog("Type: $type");
    registrarLog("Action: $action");
    registrarLog("Data ID: $dataId");
    
    // ============================================
    // 2. CONECTAR AO BANCO DE DADOS
    // ============================================
    
    require_once __DIR__ . '/config.php';
    
    if (!isset($conn) || !$conn) {
        registrarLog("ERRO: Falha na conexão com o banco de dados");
        responder(500, 'database_error');
    }
    
    // ============================================
    // 3. BUSCAR ASSINATURA SECRETA
    // ============================================
    
    $stmt = $conn->prepare("
        SELECT webhook_secret 
        FROM configuracoes_gateway 
        WHERE gateway = 'mercadopago' 
        AND ativo = 1 
        LIMIT 1
    ");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['webhook_secret'])) {
        registrarLog("AVISO: Assinatura secreta não configurada - Validação ignorada");
        $validarAssinatura = false;
    } else {
        $secretKey = $config['webhook_secret'];
        $validarAssinatura = true;
        registrarLog("Assinatura secreta encontrada");
    }
    
    // ============================================
    // 4. VALIDAR ASSINATURA (x-signature)
    // ============================================
    
    if ($validarAssinatura) {
        // Extrair headers necessários
        $xSignature = $headers['x-signature'] ?? $headers['X-Signature'] ?? '';
        $xRequestId = $headers['x-request-id'] ?? $headers['X-Request-Id'] ?? '';
        
        registrarLog("x-signature: $xSignature");
        registrarLog("x-request-id: $xRequestId");
        
        if (empty($xSignature) || empty($xRequestId)) {
            registrarLog("ERRO: Headers x-signature ou x-request-id ausentes");
            responder(401, 'missing_signature_headers');
        }
        
        // Separar ts e v1 do x-signature
        // Formato: ts=1704908010,v1=618c85345248dd820d5fd456117c2ab2ef8eda45a0282ff693eac24131a5e839
        $parts = explode(',', $xSignature);
        $ts = null;
        $receivedHash = null;
        
        foreach ($parts as $part) {
            $keyValue = explode('=', trim($part), 2);
            if (count($keyValue) === 2) {
                list($key, $value) = $keyValue;
                if ($key === 'ts') {
                    $ts = $value;
                } elseif ($key === 'v1') {
                    $receivedHash = $value;
                }
            }
        }
        
        registrarLog("Timestamp (ts): $ts");
        registrarLog("Hash recebido (v1): $receivedHash");
        
        if (empty($ts) || empty($receivedHash)) {
            registrarLog("ERRO: Formato de x-signature inválido");
            responder(401, 'invalid_signature_format');
        }
        
        if (empty($dataId)) {
            registrarLog("ERRO: data.id ausente no payload");
            responder(400, 'missing_data_id');
        }
        
        // Construir template conforme documentação oficial
        // Formato: id:{data.id};request-id:{x-request-id};ts:{ts};
        $template = "id:$dataId;request-id:$xRequestId;ts:$ts;";
        
        registrarLog("Template: $template");
        
        // Calcular HMAC SHA256
        $calculatedHash = hash_hmac('sha256', $template, $secretKey);
        
        registrarLog("Hash calculado: $calculatedHash");
        
        // Comparar hashes de forma segura
        if (!hash_equals($calculatedHash, $receivedHash)) {
            registrarLog("ERRO: Assinatura inválida - Possível tentativa de fraude");
            responder(401, 'invalid_signature');
        }
        
        registrarLog("✅ Assinatura validada com sucesso");
    }
    
    // ============================================
    // 5. GRAVAR NOTIFICAÇÃO NO BANCO
    // ============================================
    
    $stmt = $conn->prepare("
        INSERT INTO webhooks_pagamento (
            gateway,
            evento,
            transaction_id,
            payload,
            headers,
            processado,
            data_recebimento
        ) VALUES (
            'mercadopago',
            ?,
            ?,
            ?,
            ?,
            0,
            NOW()
        )
    ");
    
    $evento = "$type.$action";
    $headersJson = json_encode($headers);
    
    $stmt->execute([
        $evento,
        $dataId,
        $bodyRaw,
        $headersJson
    ]);
    
    $webhookId = $conn->lastInsertId();
    
    registrarLog("✅ Webhook gravado no BD - ID: $webhookId");
    
    // ============================================
    // 6. RESPONDER 200 OK IMEDIATAMENTE
    // ============================================
    
    registrarLog("✅ Respondendo 200 OK");
    registrarLog("=== FIM ===\n");
    
    responder(200, 'received');
    
} catch (PDOException $e) {
    registrarLog("ERRO PDO: " . $e->getMessage());
    responder(500, 'database_error');
    
} catch (Exception $e) {
    registrarLog("ERRO: " . $e->getMessage());
    responder(500, 'internal_error');
}
?>
