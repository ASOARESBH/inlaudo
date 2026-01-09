<?php
/**
 * Webhook Mercado Pago - Confirmação de Pagamentos
 * ERP INLAUDO
 * Atualiza contas_receber e contas_pagar
 * NUNCA retorna erro 500
 */

file_put_contents(
    __DIR__ . '/logs/webhook_mp_debug.log',
    date('Y-m-d H:i:s') . ' - webhook chamado' . PHP_EOL,
    FILE_APPEND
);

require_once 'config.php';

http_response_code(200);
header('Content-Type: application/json');

// ===============================
// LOG
// ===============================
$logFile = __DIR__ . '/logs/webhook_mercadopago.log';

function logMP($msg) {
    global $logFile;
    file_put_contents(
        $logFile,
        date('Y-m-d H:i:s') . ' - ' . $msg . PHP_EOL,
        FILE_APPEND
    );
}

logMP('Webhook recebido');

// ===============================
// PAYLOAD
// ===============================
$payload = json_decode(file_get_contents('php://input'), true);

if (!$payload || empty($payload['data']['id'])) {
    logMP('Payload inválido');
    echo json_encode(['ok' => true]);
    exit;
}

$paymentId = $payload['data']['id'];
logMP("Payment ID recebido: {$paymentId}");

try {
    $conn = getConnection();

    // ===============================
    // TOKEN MERCADO PAGO
    // ===============================
    $stmt = $conn->prepare("
        SELECT access_token
        FROM configuracoes_gateway
        WHERE gateway = 'mercadopago'
        AND ativo = 1
        LIMIT 1
    ");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        logMP('Access token não encontrado');
        echo json_encode(['ok' => true]);
        exit;
    }

    // ===============================
    // CONSULTA PAGAMENTO NA API
    // ===============================
    $ch = curl_init("https://api.mercadopago.com/v1/payments/{$paymentId}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['access_token']
        ],
        CURLOPT_TIMEOUT => 20
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $payment = json_decode($response, true);

    if (!$payment || empty($payment['status'])) {
        logMP('Resposta inválida da API Mercado Pago');
        echo json_encode(['ok' => true]);
        exit;
    }

    logMP("Status do pagamento: {$payment['status']}");

    // ===============================
    // PROCESSAR SOMENTE PAGAMENTOS APROVADOS
    // ===============================
    if ($payment['status'] !== 'approved') {
        echo json_encode(['ok' => true]);
        exit;
    }

    // ===============================
    // REFERÊNCIA EXTERNA
    // ===============================
    if (empty($payment['external_reference'])) {
        logMP('Sem external_reference');
        echo json_encode(['ok' => true]);
        exit;
    }

    // external_reference = conta_33
    $contaId = (int) str_replace('conta_', '', $payment['external_reference']);

    if ($contaId <= 0) {
        logMP('Conta inválida');
        echo json_encode(['ok' => true]);
        exit;
    }

    // ===============================
    // ATUALIZA CONTAS A RECEBER
    // ===============================
    $stmt = $conn->prepare("
        UPDATE contas_receber
        SET status = 'pago',
            data_pagamento = NOW()
        WHERE id = ?
        AND status <> 'pago'
    ");
    $stmt->execute([$contaId]);

    logMP("Conta a receber {$contaId} marcada como PAGA");

    // ===============================
    // (OPCIONAL) CONTAS A PAGAR
    // ===============================
    $stmt = $conn->prepare("
        UPDATE contas_pagar
        SET status = 'pago',
            data_pagamento = NOW()
        WHERE conta_receber_id = ?
        AND status <> 'pago'
    ");
    $stmt->execute([$contaId]);

    logMP("Contas a pagar vinculadas à conta {$contaId} atualizadas");

} catch (Throwable $e) {
    logMP('ERRO: ' . $e->getMessage());
}

// ===============================
// RESPOSTA FINAL (SEMPRE 200)
// ===============================
echo json_encode(['ok' => true]);
exit;
