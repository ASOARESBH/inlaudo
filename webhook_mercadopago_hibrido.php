<?php
/**
 * Webhook Mercado Pago - Versão Híbrida
 * 
 * Suporta AMBOS os formatos de notificação:
 * 1. Webhooks (POST) - Com validação x-signature
 * 2. IPN (GET) - Sem validação (legado)
 * 
 * Documentação:
 * - Webhooks: https://www.mercadopago.com.br/developers/pt/docs/your-integrations/notifications/webhooks
 * - IPN: https://www.mercadopago.com.br/developers/pt/docs/your-integrations/notifications/ipn
 * 
 * Versão: 8.1
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
// 1. DETECTAR TIPO DE NOTIFICAÇÃO
// ============================================

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $headers = getallheaders();
    
    registrarLog("=== NOTIFICAÇÃO RECEBIDA ===");
    registrarLog("Método: $method");
    registrarLog("IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    registrarLog("Headers: " . json_encode($headers));
    
    // ============================================
    // 2. CONECTAR AO BANCO DE DADOS
    // ============================================
    
    require_once __DIR__ . '/config.php';
    
    if (!isset($conn) || !$conn) {
        registrarLog("ERRO: Falha na conexão com o banco de dados");
        responder(500, 'database_error');
    }
    
    // ============================================
    // 3. BUSCAR ASSINATURA SECRETA (para Webhooks)
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
    
    $secretKey = $config['webhook_secret'] ?? null;
    
    // ============================================
    // 4. PROCESSAR CONFORME O MÉTODO
    // ============================================
    
    if ($method === 'POST') {
        // ============================================
        // FORMATO: WEBHOOK (NOVO)
        // ============================================
        
        registrarLog("Tipo: WEBHOOK (POST)");
        
        // Capturar body raw
        $bodyRaw = file_get_contents('php://input');
        
        registrarLog("Body: $bodyRaw");
        
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
        // VALIDAR ASSINATURA (x-signature)
        // ============================================
        
        if (!empty($secretKey)) {
            // Extrair headers necessários
            $xSignature = $headers['x-signature'] ?? $headers['X-Signature'] ?? '';
            $xRequestId = $headers['x-request-id'] ?? $headers['X-Request-Id'] ?? '';
            
            registrarLog("x-signature: $xSignature");
            registrarLog("x-request-id: $xRequestId");
            
            if (!empty($xSignature) && !empty($xRequestId) && !empty($dataId)) {
                // Separar ts e v1
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
                
                if (!empty($ts) && !empty($receivedHash)) {
                    // Construir template
                    $template = "id:$dataId;request-id:$xRequestId;ts:$ts;";
                    
                    // Calcular HMAC SHA256
                    $calculatedHash = hash_hmac('sha256', $template, $secretKey);
                    
                    registrarLog("Template: $template");
                    registrarLog("Hash calculado: $calculatedHash");
                    registrarLog("Hash recebido: $receivedHash");
                    
                    // Comparar hashes
                    if (!hash_equals($calculatedHash, $receivedHash)) {
                        registrarLog("ERRO: Assinatura inválida");
                        responder(401, 'invalid_signature');
                    }
                    
                    registrarLog("✅ Assinatura validada com sucesso");
                } else {
                    registrarLog("AVISO: x-signature incompleto - Validação ignorada");
                }
            } else {
                registrarLog("AVISO: Headers de validação ausentes - Validação ignorada");
            }
        } else {
            registrarLog("AVISO: Assinatura secreta não configurada - Validação ignorada");
        }
        
        // Gravar no BD
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
        
    } elseif ($method === 'GET') {
        // ============================================
        // FORMATO: IPN (LEGADO)
        // ============================================
        
        registrarLog("Tipo: IPN (GET)");
        
        // Capturar parâmetros GET
        $topic = $_GET['topic'] ?? $_GET['type'] ?? '';
        $dataId = $_GET['id'] ?? '';
        
        registrarLog("Topic: $topic");
        registrarLog("ID: $dataId");
        registrarLog("Query String: " . ($_SERVER['QUERY_STRING'] ?? ''));
        
        // Validar parâmetros
        if (empty($dataId)) {
            registrarLog("ERRO: ID ausente");
            responder(400, 'missing_id');
        }
        
        // IPN não tem validação de assinatura
        registrarLog("AVISO: IPN não possui validação de assinatura");
        
        // Gravar no BD
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
        
        $evento = "ipn.$topic";
        $payloadJson = json_encode([
            'type' => $topic,
            'id' => $dataId,
            'query_string' => $_SERVER['QUERY_STRING'] ?? ''
        ]);
        $headersJson = json_encode($headers);
        
        $stmt->execute([
            $evento,
            $dataId,
            $payloadJson,
            $headersJson
        ]);
        
        $webhookId = $conn->lastInsertId();
        registrarLog("✅ IPN gravado no BD - ID: $webhookId");
        
    } else {
        // Método não suportado
        registrarLog("ERRO: Método não suportado: $method");
        responder(405, 'method_not_allowed');
    }
    
    // ============================================
    // 5. RESPONDER 200 OK
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
