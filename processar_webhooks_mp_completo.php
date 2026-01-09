<?php
/**
 * Processador de Webhooks Mercado Pago - Versão Completa
 * 
 * Funcionalidades:
 * - Processa webhooks não processados
 * - Consulta API do Mercado Pago
 * - Atualiza status em contas_receber
 * - Atualiza status em contas_pagar (vinculadas)
 * - Marca webhook como processado
 * - Idempotente (seguro para múltiplas execuções)
 * 
 * Execução: Via CRON a cada 1-5 minutos
 * Exemplo CRON: */5 * * * * /usr/bin/php /caminho/processar_webhooks_mp_completo.php
 * 
 * Versão: 8.0
 * Data: 31/12/2025
 * Autor: Manus AI
 */

// Desabilitar saída HTML (execução via CRON)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações
define('BATCH_SIZE', 20); // Processar 20 webhooks por execução
define('LOG_FILE', __DIR__ . '/logs/processar_webhooks_mp.log');
define('MAX_RETRIES', 3); // Máximo de tentativas

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

// ============================================
// 1. CONECTAR AO BANCO DE DADOS
// ============================================

try {
    require_once __DIR__ . '/config.php';
    
    if (!isset($conn) || !$conn) {
        registrarLog("ERRO: Falha na conexão com o banco de dados");
        exit(1);
    }
    
    registrarLog("=== INICIANDO PROCESSAMENTO ===");
    
    // ============================================
    // 2. BUSCAR ACCESS TOKEN DO MERCADO PAGO
    // ============================================
    
    $stmt = $conn->prepare("
        SELECT access_token 
        FROM configuracoes_gateway 
        WHERE gateway = 'mercadopago' 
        AND ativo = 1 
        LIMIT 1
    ");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['access_token'])) {
        registrarLog("ERRO: Access token do Mercado Pago não configurado");
        exit(1);
    }
    
    $accessToken = $config['access_token'];
    registrarLog("Access token encontrado");
    
    // ============================================
    // 3. BUSCAR WEBHOOKS NÃO PROCESSADOS
    // ============================================
    
    $stmt = $conn->prepare("
        SELECT id, transaction_id, payload, evento
        FROM webhooks_pagamento
        WHERE gateway = 'mercadopago'
        AND processado = 0
        AND transaction_id IS NOT NULL
        AND transaction_id != ''
        ORDER BY data_recebimento ASC
        LIMIT " . BATCH_SIZE . "
    ");
    $stmt->execute();
    $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalWebhooks = count($webhooks);
    registrarLog("Webhooks não processados: $totalWebhooks");
    
    if ($totalWebhooks === 0) {
        registrarLog("Nenhum webhook para processar");
        registrarLog("=== FIM ===\n");
        exit(0);
    }
    
    // ============================================
    // 4. PROCESSAR CADA WEBHOOK
    // ============================================
    
    $processados = 0;
    $erros = 0;
    
    foreach ($webhooks as $webhook) {
        $webhookId = $webhook['id'];
        $transactionId = $webhook['transaction_id'];
        $evento = $webhook['evento'];
        
        registrarLog("--- Processando Webhook ID: $webhookId ---");
        registrarLog("Transaction ID: $transactionId");
        registrarLog("Evento: $evento");
        
        try {
            // ============================================
            // 4.1. CONSULTAR API DO MERCADO PAGO
            // ============================================
            
            $url = "https://api.mercadopago.com/v1/payments/$transactionId";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $accessToken",
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            registrarLog("HTTP Code: $httpCode");
            
            if ($httpCode !== 200) {
                throw new Exception("Erro na API do Mercado Pago - HTTP $httpCode: $curlError");
            }
            
            $payment = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Erro ao decodificar resposta da API: " . json_last_error_msg());
            }
            
            // Extrair dados do pagamento
            $status = $payment['status'] ?? '';
            $statusDetail = $payment['status_detail'] ?? '';
            $transactionAmount = $payment['transaction_amount'] ?? 0;
            $externalReference = $payment['external_reference'] ?? '';
            $paymentMethodId = $payment['payment_method_id'] ?? '';
            $dateApproved = $payment['date_approved'] ?? null;
            
            registrarLog("Status: $status");
            registrarLog("Status Detail: $statusDetail");
            registrarLog("Valor: R$ " . number_format($transactionAmount, 2, ',', '.'));
            registrarLog("External Reference: $externalReference");
            registrarLog("Método: $paymentMethodId");
            
            // ============================================
            // 4.2. ATUALIZAR STATUS EM CONTAS_RECEBER
            // ============================================
            
            if ($status === 'approved') {
                registrarLog("✅ Pagamento APROVADO - Atualizando contas_receber");
                
                // Extrair ID da conta do external_reference (formato: conta_123)
                $contaId = null;
                if (preg_match('/conta_(\d+)/', $externalReference, $matches)) {
                    $contaId = $matches[1];
                    registrarLog("Conta ID extraído: $contaId");
                }
                
                // Atualizar por conta_id OU payment_id
                $stmt = $conn->prepare("
                    UPDATE contas_receber 
                    SET status = 'pago',
                        data_pagamento = NOW(),
                        valor_pago = ?,
                        payment_id = ?,
                        gateway = 'mercadopago'
                    WHERE (id = ? OR payment_id = ?)
                    AND status != 'pago'
                ");
                
                $stmt->execute([
                    $transactionAmount,
                    $transactionId,
                    $contaId,
                    $transactionId
                ]);
                
                $linhasAfetadas = $stmt->rowCount();
                registrarLog("Contas_receber atualizadas: $linhasAfetadas");
                
                // ============================================
                // 4.3. ATUALIZAR STATUS EM CONTAS_PAGAR
                // ============================================
                
                if ($linhasAfetadas > 0 && $contaId) {
                    registrarLog("Atualizando contas_pagar vinculadas...");
                    
                    // Buscar contrato_id da conta a receber
                    $stmt = $conn->prepare("
                        SELECT contrato_id 
                        FROM contas_receber 
                        WHERE id = ? 
                        LIMIT 1
                    ");
                    $stmt->execute([$contaId]);
                    $conta = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($conta && !empty($conta['contrato_id'])) {
                        $contratoId = $conta['contrato_id'];
                        registrarLog("Contrato ID: $contratoId");
                        
                        // Atualizar contas_pagar do mesmo contrato
                        $stmt = $conn->prepare("
                            UPDATE contas_pagar 
                            SET status = 'pago',
                                data_pagamento = NOW(),
                                valor_pago = ?,
                                payment_id = ?,
                                gateway = 'mercadopago'
                            WHERE contrato_id = ?
                            AND status != 'pago'
                            AND valor = ?
                        ");
                        
                        $stmt->execute([
                            $transactionAmount,
                            $transactionId,
                            $contratoId,
                            $transactionAmount
                        ]);
                        
                        $linhasAfetadasPagar = $stmt->rowCount();
                        registrarLog("Contas_pagar atualizadas: $linhasAfetadasPagar");
                    } else {
                        registrarLog("Contrato não encontrado ou não vinculado");
                    }
                }
                
            } elseif ($status === 'pending' || $status === 'in_process') {
                registrarLog("⏳ Pagamento PENDENTE - Mantendo status");
                
            } elseif ($status === 'rejected') {
                registrarLog("❌ Pagamento REJEITADO");
                
                // Atualizar status para rejeitado
                $stmt = $conn->prepare("
                    UPDATE contas_receber 
                    SET status = 'rejeitado'
                    WHERE payment_id = ?
                ");
                $stmt->execute([$transactionId]);
                
            } elseif ($status === 'cancelled') {
                registrarLog("❌ Pagamento CANCELADO");
                
                // Atualizar status para cancelado
                $stmt = $conn->prepare("
                    UPDATE contas_receber 
                    SET status = 'cancelado'
                    WHERE payment_id = ?
                ");
                $stmt->execute([$transactionId]);
                
            } elseif ($status === 'refunded' || $status === 'charged_back') {
                registrarLog("🔄 Pagamento ESTORNADO/CHARGEBACK");
                
                // Atualizar status para estornado
                $stmt = $conn->prepare("
                    UPDATE contas_receber 
                    SET status = 'estornado',
                        data_pagamento = NULL,
                        valor_pago = NULL
                    WHERE payment_id = ?
                ");
                $stmt->execute([$transactionId]);
                
                // Reverter contas_pagar também
                $stmt = $conn->prepare("
                    UPDATE contas_pagar 
                    SET status = 'pendente',
                        data_pagamento = NULL,
                        valor_pago = NULL
                    WHERE payment_id = ?
                ");
                $stmt->execute([$transactionId]);
                
            } else {
                registrarLog("⚠️ Status desconhecido: $status");
            }
            
            // ============================================
            // 4.4. MARCAR WEBHOOK COMO PROCESSADO
            // ============================================
            
            $stmt = $conn->prepare("
                UPDATE webhooks_pagamento 
                SET processado = 1,
                    data_processamento = NOW(),
                    erro = NULL
                WHERE id = ?
            ");
            $stmt->execute([$webhookId]);
            
            registrarLog("✅ Webhook processado com sucesso");
            $processados++;
            
        } catch (Exception $e) {
            registrarLog("ERRO ao processar webhook: " . $e->getMessage());
            
            // Salvar erro no banco
            $stmt = $conn->prepare("
                UPDATE webhooks_pagamento 
                SET erro = ?,
                    processado = 0
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $webhookId]);
            
            $erros++;
        }
    }
    
    // ============================================
    // 5. RESUMO DO PROCESSAMENTO
    // ============================================
    
    registrarLog("=== RESUMO ===");
    registrarLog("Total de webhooks: $totalWebhooks");
    registrarLog("Processados com sucesso: $processados");
    registrarLog("Erros: $erros");
    registrarLog("=== FIM ===\n");
    
    exit(0);
    
} catch (PDOException $e) {
    registrarLog("ERRO PDO: " . $e->getMessage());
    exit(1);
    
} catch (Exception $e) {
    registrarLog("ERRO: " . $e->getMessage());
    exit(1);
}
?>
