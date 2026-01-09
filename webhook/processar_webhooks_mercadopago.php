<?php
/**
 * Processa Webhooks Mercado Pago
 * PASSO 3.3 — CONSULTAR PAGAMENTO
 * ERP INLAUDO
 */

require_once __DIR__ . '/../config.php';

// ===============================
// CONEXÃO
// ===============================
$conn = getConnection();

// ===============================
// BUSCAR CONFIG MP
// ===============================
$config = $conn->query("
    SELECT access_token
    FROM integracao_mercadopago
    WHERE gateway = 'mercadopago'
      AND ativo = 1
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$config || empty($config['access_token'])) {
    die('Access Token Mercado Pago não configurado');
}

$accessToken = trim($config['access_token']);

// ===============================
// BUSCAR WEBHOOKS NÃO PROCESSADOS
// ===============================
$webhooks = $conn->query("
    SELECT *
    FROM webhooks_pagamento
    WHERE gateway = 'mercadopago'
      AND processado = 0
      AND transaction_id IS NOT NULL
    ORDER BY id ASC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($webhooks as $wh) {

    $webhookId = $wh['id'];
    $paymentId = $wh['transaction_id'];

    // ===============================
    // CONSULTAR PAGAMENTO MP
    // ===============================
    $ch = curl_init("https://api.mercadopago.com/v1/payments/$paymentId");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $conn->prepare("
            UPDATE webhooks_pagamento
            SET erro = ?, data_processamento = NOW()
            WHERE id = ?
        ")->execute([
            "Erro API Mercado Pago ($httpCode)",
            $webhookId
        ]);
        continue;
    }

    $payment = json_decode($response, true);

    if (!$payment || empty($payment['id'])) {
        continue;
    }

    // ===============================
    // MAPEAR STATUS
    // ===============================
    $statusGateway = $payment['status']; // approved, pending, rejected
    $dataPagamento = null;
    $statusFinal   = 'pendente';

    if ($statusGateway === 'approved') {
        $statusFinal   = 'pago';
        $dataPagamento = date('Y-m-d');
    }

    // ===============================
    // ATUALIZAR CONTAS A RECEBER
    // ===============================
    $stmt = $conn->prepare("
        UPDATE contas_receber
        SET
            status = ?,
            status_gateway = ?,
            data_pagamento = ?,
            data_confirmacao = NOW(),
            gateway_payload = ?,
            data_atualizacao = NOW()
        WHERE payment_id = ?
          AND gateway = 'mercadopago'
        LIMIT 1
    ");

    $stmt->execute([
        $statusFinal,
        $statusGateway,
        $dataPagamento,
        json_encode($payment),
        $paymentId
    ]);

    // ===============================
    // MARCAR WEBHOOK COMO PROCESSADO
    // ===============================
    $conn->prepare("
        UPDATE webhooks_pagamento
        SET processado = 1,
            data_processamento = NOW()
        WHERE id = ?
    ")->execute([$webhookId]);
}

echo "Processamento finalizado";
