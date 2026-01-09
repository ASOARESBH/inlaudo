<?php
/**
 * Webhook Asaas
 * 
 * Recebe notificações de eventos de pagamento do Asaas
 * Valida token de segurança e processa eventos
 * 
 * @author Backend Developer
 * @version 1.0.0
 */

// Incluir dependências
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\Services\AsaasService;
use App\Core\Database;

// Definir headers
header('Content-Type: application/json');

// Log de debug
$logFile = __DIR__ . '/logs/webhook_asaas_' . date('Y-m-d') . '.log';

try {
    // Obter token do header
    $tokenHeader = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? null;
    
    // Obter configuração do Asaas
    $db = Database::getInstance();
    $sql = "SELECT webhook_token FROM integracao_asaas WHERE ativo = 1 LIMIT 1";
    $config = $db->fetchOne($sql);
    
    if (!$config) {
        throw new \Exception('Integração Asaas não configurada');
    }
    
    // Validar token
    if (!AsaasService::validateWebhookToken($tokenHeader, $config['webhook_token'])) {
        error_log('[WEBHOOK ASAAS] Token inválido', 3, $logFile);
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    // Obter payload
    $payload = file_get_contents('php://input');
    $event = json_decode($payload, true);
    
    if (!$event) {
        throw new \Exception('Payload inválido');
    }
    
    // Log do evento recebido
    error_log('[WEBHOOK ASAAS] Evento recebido: ' . $event['event'] . ' - ID: ' . $event['id'], 3, $logFile);
    
    // Verificar se evento já foi processado (idempotência)
    $sql = "SELECT id FROM asaas_webhooks WHERE event_id = ?";
    $existingEvent = $db->fetchOne($sql, [$event['id']]);
    
    if ($existingEvent) {
        error_log('[WEBHOOK ASAAS] Evento duplicado, ignorando: ' . $event['id'], 3, $logFile);
        http_response_code(200);
        echo json_encode(['received' => true, 'duplicate' => true]);
        exit;
    }
    
    // Registrar webhook recebido
    $sql = "
        INSERT INTO asaas_webhooks (event_id, tipo_evento, payment_id, payload, processado)
        VALUES (?, ?, ?, ?, 0)
    ";
    
    $db->execute($sql, [
        $event['id'],
        $event['event'],
        $event['payment']['id'] ?? null,
        $payload
    ]);
    
    // Processar evento conforme tipo
    switch ($event['event']) {
        case 'PAYMENT_RECEIVED':
        case 'PAYMENT_CONFIRMED':
            processarPagamentoRecebido($event, $db, $logFile);
            break;
            
        case 'PAYMENT_PENDING':
            error_log('[WEBHOOK ASAAS] Pagamento pendente: ' . $event['payment']['id'], 3, $logFile);
            break;
            
        case 'PAYMENT_OVERDUE':
            error_log('[WEBHOOK ASAAS] Pagamento vencido: ' . $event['payment']['id'], 3, $logFile);
            break;
            
        case 'PAYMENT_DELETED':
            error_log('[WEBHOOK ASAAS] Pagamento deletado: ' . $event['payment']['id'], 3, $logFile);
            break;
            
        default:
            error_log('[WEBHOOK ASAAS] Evento não processado: ' . $event['event'], 3, $logFile);
    }
    
    // Marcar como processado
    $sql = "UPDATE asaas_webhooks SET processado = 1, data_processamento = NOW() WHERE event_id = ?";
    $db->execute($sql, [$event['id']]);
    
    // Retornar sucesso
    http_response_code(200);
    echo json_encode(['received' => true]);
    
} catch (\Exception $e) {
    error_log('[WEBHOOK ASAAS] ERRO: ' . $e->getMessage(), 3, $logFile);
    
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Processar pagamento recebido
 * Atualiza status no banco de dados local
 */
function processarPagamentoRecebido($event, $db, $logFile) {
    try {
        $payment = $event['payment'];
        $paymentId = $payment['id'];
        
        error_log('[WEBHOOK ASAAS] Processando pagamento: ' . $paymentId, 3, $logFile);
        
        // Buscar cobrança mapeada
        $sql = "SELECT conta_receber_id FROM asaas_pagamentos WHERE asaas_payment_id = ?";
        $mapping = $db->fetchOne($sql, [$paymentId]);
        
        if (!$mapping) {
            error_log('[WEBHOOK ASAAS] Cobrança não mapeada: ' . $paymentId, 3, $logFile);
            return;
        }
        
        $contaId = $mapping['conta_receber_id'];
        
        // Iniciar transação
        $db->beginTransaction();
        
        try {
            // Atualizar status da cobrança no Asaas
            $sql = "
                UPDATE asaas_pagamentos 
                SET status_asaas = ?
                WHERE asaas_payment_id = ?
            ";
            $db->execute($sql, [$payment['status'], $paymentId]);
            
            // Atualizar conta a receber
            $sql = "
                UPDATE contas_receber 
                SET status = 'pago', 
                    status_asaas = ?,
                    data_pagamento = NOW(),
                    data_atualizacao = NOW()
                WHERE id = ?
            ";
            $db->execute($sql, [$payment['status'], $contaId]);
            
            // Registrar nota de alteração (auditoria)
            $sql = "
                INSERT INTO notas_contas_receber (conta_receber_id, nota, usuario_id, data_criacao)
                VALUES (?, ?, 1, NOW())
            ";
            $nota = "Pagamento recebido via Asaas. ID: {$paymentId}. Status: {$payment['status']}";
            $db->execute($sql, [$contaId, $nota]);
            
            // Se houver valor pago diferente, registrar
            if ($payment['netValue'] && $payment['netValue'] != $payment['value']) {
                $sql = "
                    INSERT INTO notas_contas_receber (conta_receber_id, nota, usuario_id, data_criacao)
                    VALUES (?, ?, 1, NOW())
                ";
                $nota = "Valor pago: R$ " . number_format($payment['netValue'], 2, ',', '.');
                $db->execute($sql, [$contaId, $nota]);
            }
            
            // Commit transação
            $db->commit();
            
            error_log('[WEBHOOK ASAAS] Pagamento processado com sucesso: ' . $paymentId, 3, $logFile);
            
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
        
    } catch (\Exception $e) {
        error_log('[WEBHOOK ASAAS] Erro ao processar pagamento: ' . $e->getMessage(), 3, $logFile);
        throw $e;
    }
}
