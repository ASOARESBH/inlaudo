<?php
/**
 * Webhook Unificado - Todos os Gateways
 * Versão: 2.3.0
 * 
 * Recebe webhooks de todos os gateways de pagamento
 * e processa de forma padronizada
 */

require_once 'config.php';
require_once 'src/models/GatewayPagamentoModel.php';

// Configurar headers
header('Content-Type: application/json');

// Capturar dados do webhook
$input = file_get_contents('php://input');
$headers = getallheaders();

// Log do webhook recebido
$logDir = __DIR__ . '/logs/webhooks';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/webhook_' . date('Y-m-d') . '.log';
$logEntry = sprintf(
    "[%s] IP: %s | Method: %s | Headers: %s | Body: %s\n",
    date('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
    $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    json_encode($headers),
    $input
);
@file_put_contents($logFile, $logEntry, FILE_APPEND);

/**
 * Identifica o gateway pelo path ou header
 */
function identificarGateway() {
    $path = $_SERVER['REQUEST_URI'] ?? '';
    
    // Identificar por path
    if (stripos($path, 'mercadopago') !== false) {
        return 'mercadopago';
    }
    if (stripos($path, 'asaas') !== false) {
        return 'asaas';
    }
    if (stripos($path, 'cora') !== false) {
        return 'cora';
    }
    
    // Identificar por query string
    if (isset($_GET['gateway'])) {
        return strtolower($_GET['gateway']);
    }
    
    // Identificar por header
    $headers = getallheaders();
    if (isset($headers['X-Gateway'])) {
        return strtolower($headers['X-Gateway']);
    }
    
    return null;
}

/**
 * Processa webhook do Mercado Pago
 */
function processarMercadoPago($data, $gatewayModel) {
    // Mercado Pago envia notificações de diferentes tipos
    $tipo = $data['type'] ?? $data['action'] ?? null;
    
    if ($tipo === 'payment') {
        $payment_id = $data['data']['id'] ?? null;
        
        if (!$payment_id) {
            return ['success' => false, 'error' => 'Payment ID não encontrado'];
        }
        
        // Buscar transação no banco
        $gateway = $gatewayModel->buscarPorCodigo('mercadopago');
        $transacao = $gatewayModel->buscarTransacaoPorGatewayId($gateway['id'], $payment_id);
        
        if (!$transacao) {
            return ['success' => false, 'error' => 'Transação não encontrada'];
        }
        
        // Aqui você deve fazer uma chamada à API do Mercado Pago para buscar o status atual
        // Por enquanto, vamos simular o mapeamento
        $status_gateway = $data['status'] ?? 'pending';
        $status_erp = $gatewayModel->mapearStatus($gateway['id'], $status_gateway);
        
        // Atualizar transação
        $gatewayModel->atualizarStatusTransacao(
            $transacao['id'],
            $status_erp,
            $status_gateway
        );
        
        return ['success' => true, 'status_erp' => $status_erp];
    }
    
    return ['success' => true, 'message' => 'Tipo de webhook não processado'];
}

/**
 * Processa webhook do Asaas
 */
function processarAsaas($data, $gatewayModel) {
    $event = $data['event'] ?? null;
    
    if ($event === 'PAYMENT_RECEIVED' || $event === 'PAYMENT_CONFIRMED') {
        $payment_id = $data['payment']['id'] ?? null;
        
        if (!$payment_id) {
            return ['success' => false, 'error' => 'Payment ID não encontrado'];
        }
        
        // Buscar transação no banco
        $gateway = $gatewayModel->buscarPorCodigo('asaas');
        $transacao = $gatewayModel->buscarTransacaoPorGatewayId($gateway['id'], $payment_id);
        
        if (!$transacao) {
            return ['success' => false, 'error' => 'Transação não encontrada'];
        }
        
        $status_gateway = $data['payment']['status'] ?? 'PENDING';
        $status_erp = $gatewayModel->mapearStatus($gateway['id'], $status_gateway);
        
        // Atualizar transação
        $gatewayModel->atualizarStatusTransacao(
            $transacao['id'],
            $status_erp,
            $status_gateway
        );
        
        return ['success' => true, 'status_erp' => $status_erp];
    }
    
    return ['success' => true, 'message' => 'Evento não processado'];
}

/**
 * Processa webhook do Cora
 */
function processarCora($data, $gatewayModel) {
    $event_type = $data['event_type'] ?? null;
    
    if ($event_type === 'payment.paid' || $event_type === 'payment.status_changed') {
        $payment_id = $data['data']['id'] ?? null;
        
        if (!$payment_id) {
            return ['success' => false, 'error' => 'Payment ID não encontrado'];
        }
        
        // Buscar transação no banco
        $gateway = $gatewayModel->buscarPorCodigo('cora');
        $transacao = $gatewayModel->buscarTransacaoPorGatewayId($gateway['id'], $payment_id);
        
        if (!$transacao) {
            return ['success' => false, 'error' => 'Transação não encontrada'];
        }
        
        $status_gateway = $data['data']['status'] ?? 'PENDING';
        $status_erp = $gatewayModel->mapearStatus($gateway['id'], $status_gateway);
        
        // Atualizar transação
        $gatewayModel->atualizarStatusTransacao(
            $transacao['id'],
            $status_erp,
            $status_gateway
        );
        
        return ['success' => true, 'status_erp' => $status_erp];
    }
    
    return ['success' => true, 'message' => 'Evento não processado'];
}

/**
 * Registra webhook no banco
 */
function registrarWebhook($conn, $gateway_id, $tipo, $dados, $status, $erro = null) {
    $sql = "
        INSERT INTO webhooks_pagamento (
            gateway_id,
            tipo_webhook,
            evento,
            dados_webhook,
            status,
            mensagem_erro
        ) VALUES (?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $gateway_id,
        $tipo,
        $dados['event'] ?? $dados['type'] ?? $dados['event_type'] ?? 'unknown',
        json_encode($dados),
        $status,
        $erro
    ]);
    
    return $conn->lastInsertId();
}

// ============================================================
// PROCESSAMENTO PRINCIPAL
// ============================================================

try {
    $conn = getConnection();
    $gatewayModel = new GatewayPagamentoModel($conn);
    
    // Identificar gateway
    $gateway_codigo = identificarGateway();
    
    if (!$gateway_codigo) {
        http_response_code(400);
        echo json_encode(['error' => 'Gateway não identificado']);
        exit;
    }
    
    // Buscar gateway no banco
    $gateway = $gatewayModel->buscarPorCodigo($gateway_codigo);
    
    if (!$gateway) {
        http_response_code(404);
        echo json_encode(['error' => 'Gateway não encontrado']);
        exit;
    }
    
    // Decodificar dados do webhook
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON inválido']);
        exit;
    }
    
    // Processar de acordo com o gateway
    $resultado = null;
    
    switch ($gateway_codigo) {
        case 'mercadopago':
            $resultado = processarMercadoPago($data, $gatewayModel);
            break;
            
        case 'asaas':
            $resultado = processarAsaas($data, $gatewayModel);
            break;
            
        case 'cora':
            $resultado = processarCora($data, $gatewayModel);
            break;
            
        default:
            $resultado = ['success' => false, 'error' => 'Gateway não suportado'];
    }
    
    // Registrar webhook no banco
    $status = $resultado['success'] ? 'processado' : 'erro';
    $erro = $resultado['error'] ?? null;
    
    registrarWebhook(
        $conn,
        $gateway['id'],
        $gateway_codigo,
        $data,
        $status,
        $erro
    );
    
    // Responder
    if ($resultado['success']) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Webhook processado com sucesso']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $resultado['error']]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
    
    // Log de erro
    error_log('[WEBHOOK ERROR] ' . $e->getMessage());
}
