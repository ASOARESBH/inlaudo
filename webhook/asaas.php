<?php
/**
 * Webhook Asaas - Receber Notificações de Pagamento
 * URL: https://erp.inlaudo.com.br/webhook/asaas.php
 * 
 * Recebe notificações de eventos do Asaas e processa:
 * - PAYMENT_RECEIVED: Pagamento recebido
 * - PAYMENT_CONFIRMED: Pagamento confirmado
 * - PAYMENT_PENDING: Pagamento pendente
 * - PAYMENT_OVERDUE: Pagamento vencido
 * - PAYMENT_DELETED: Pagamento deletado
 */

// Desabilitar output buffering para logging
ob_start();

// Arquivo de log
$log_dir = __DIR__ . '/logs';
$log_file = $log_dir . '/asaas_' . date('Y-m-d') . '.log';

// Criar diretório de logs se não existir
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Função para registrar logs
function registrarLog($mensagem, $dados = null) {
    global $log_file;
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] " . $mensagem;
    
    if ($dados) {
        $log_entry .= "\nDados: " . json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    $log_entry .= "\n" . str_repeat('-', 80) . "\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Registrar requisição
registrarLog('Webhook Asaas recebido', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'desconhecido',
    'headers' => getallheaders()
]);

try {
    // Apenas POST é aceito
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        registrarLog('Erro: Método não permitido', ['method' => $_SERVER['REQUEST_METHOD']]);
        echo json_encode(['erro' => 'Método não permitido']);
        exit;
    }
    
    // Obter payload
    $payload = file_get_contents('php://input');
    $dados = json_decode($payload, true);
    
    if (!$dados) {
        http_response_code(400);
        registrarLog('Erro: JSON inválido', ['payload' => $payload]);
        echo json_encode(['erro' => 'JSON inválido']);
        exit;
    }
    
    registrarLog('Webhook processando', $dados);
    
    // Conectar ao banco de dados
    require_once __DIR__ . '/../config.php';
    
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar configuração Asaas
    $sql = "SELECT * FROM integracao_asaas WHERE id = 1 LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || !$config['ativo']) {
        http_response_code(400);
        registrarLog('Erro: Asaas não configurado ou desativado');
        echo json_encode(['erro' => 'Asaas não configurado']);
        exit;
    }
    
    // Validar token de segurança (se configurado)
    if ($config['webhook_token']) {
        $token = $_GET['token'] ?? $_POST['token'] ?? null;
        
        if (!$token || $token !== $config['webhook_token']) {
            http_response_code(401);
            registrarLog('Erro: Token de webhook inválido', ['token_recebido' => $token]);
            echo json_encode(['erro' => 'Token inválido']);
            exit;
        }
    }
    
    // Extrair informações do webhook
    $event = $dados['event'] ?? null;
    $payment_id = $dados['payment']['id'] ?? null;
    $payment_status = $dados['payment']['status'] ?? null;
    
    if (!$event || !$payment_id) {
        http_response_code(400);
        registrarLog('Erro: Evento ou payment_id ausente');
        echo json_encode(['erro' => 'Dados incompletos']);
        exit;
    }
    
    registrarLog("Processando evento: $event para pagamento: $payment_id");
    
    // Verificar se webhook já foi processado (idempotência)
    $sql = "SELECT id FROM asaas_webhooks WHERE event_id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dados['id'] ?? $payment_id . '_' . $event]);
    
    if ($stmt->rowCount() > 0) {
        registrarLog("Webhook já foi processado (idempotência)");
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'mensagem' => 'Webhook já processado']);
        exit;
    }
    
    // Processar eventos
    $processado = false;
    
    switch ($event) {
        case 'PAYMENT_RECEIVED':
        case 'PAYMENT_CONFIRMED':
            $processado = processarPagamento($pdo, $dados, $log_file);
            break;
            
        case 'PAYMENT_PENDING':
            registrarLog("Pagamento pendente: $payment_id");
            $processado = true;
            break;
            
        case 'PAYMENT_OVERDUE':
            registrarLog("Pagamento vencido: $payment_id");
            $processado = true;
            break;
            
        case 'PAYMENT_DELETED':
            registrarLog("Pagamento deletado: $payment_id");
            $processado = true;
            break;
            
        default:
            registrarLog("Evento desconhecido: $event");
            $processado = true;
    }
    
    // Registrar webhook no banco
    $sql = "INSERT INTO asaas_webhooks (event_id, tipo_evento, payment_id, payload, processado, data_recebimento, data_processamento) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $dados['id'] ?? $payment_id . '_' . $event,
        $event,
        $payment_id,
        json_encode($dados),
        $processado ? 1 : 0
    ]);
    
    registrarLog("Webhook registrado no banco de dados");
    
    // Responder ao Asaas
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'processado' => $processado]);
    
} catch (Exception $e) {
    registrarLog('Erro crítico: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao processar webhook']);
}

/**
 * Processar pagamento confirmado/recebido
 */
function processarPagamento($pdo, $dados, $log_file) {
    global $log_file;
    
    try {
        $payment_id = $dados['payment']['id'];
        $payment_status = $dados['payment']['status'];
        
        registrarLog("Processando pagamento: $payment_id com status: $payment_status");
        
        // Buscar conta a receber pelo gateway_payment_id
        $sql = "SELECT cr.*, c.id as cliente_id FROM contas_receber cr 
                JOIN clientes c ON cr.cliente_id = c.id
                WHERE cr.gateway_payment_id = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$payment_id]);
        $conta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conta) {
            registrarLog("Aviso: Conta a receber não encontrada para payment_id: $payment_id");
            return false;
        }
        
        registrarLog("Conta encontrada: ID {$conta['id']}, Cliente: {$conta['cliente_id']}");
        
        // Mapear status Asaas para status local
        $status_mapeado = mapearStatusAsaas($payment_status);
        
        // Atualizar conta a receber
        $sql = "UPDATE contas_receber SET 
                status = ?,
                data_pagamento = NOW(),
                data_atualizacao = NOW()
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status_mapeado, $conta['id']]);
        
        registrarLog("Conta atualizada com status: $status_mapeado");
        
        // Registrar nota de auditoria
        $sql = "INSERT INTO notas_contas_receber (conta_receber_id, usuario_id, nota, data_criacao) 
                VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $conta['id'],
            null,
            "Pagamento confirmado via Asaas. Payment ID: $payment_id. Status: $payment_status"
        ]);
        
        registrarLog("Nota de auditoria registrada");
        
        // Atualizar financeiro (se tabela existir)
        try {
            $sql = "UPDATE financeiro SET 
                    status = ?,
                    data_pagamento = NOW(),
                    data_atualizacao = NOW()
                    WHERE conta_receber_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status_mapeado, $conta['id']]);
            registrarLog("Financeiro atualizado");
        } catch (Exception $e) {
            registrarLog("Aviso: Erro ao atualizar financeiro: " . $e->getMessage());
        }
        
        // Atualizar royalties (se aplicável)
        try {
            $sql = "UPDATE royalties SET 
                    status = ?,
                    data_atualizacao = NOW()
                    WHERE conta_receber_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status_mapeado, $conta['id']]);
            registrarLog("Royalties atualizado");
        } catch (Exception $e) {
            registrarLog("Aviso: Erro ao atualizar royalties: " . $e->getMessage());
        }
        
        registrarLog("Pagamento processado com sucesso!");
        return true;
        
    } catch (Exception $e) {
        registrarLog("Erro ao processar pagamento: " . $e->getMessage());
        return false;
    }
}

/**
 * Mapear status Asaas para status local
 */
function mapearStatusAsaas($status_asaas) {
    $mapeamento = [
        'PENDING' => 'pendente',
        'CONFIRMED' => 'confirmado',
        'RECEIVED' => 'pago',
        'OVERDUE' => 'vencido',
        'DELETED' => 'cancelado',
        'REFUNDED' => 'reembolsado'
    ];
    
    return $mapeamento[$status_asaas] ?? 'pendente';
}

// Enviar logs para output
ob_end_flush();
?>
