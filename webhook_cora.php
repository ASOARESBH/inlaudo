<?php
require_once 'config.php';

http_response_code(200);
header('Content-Type: application/json');

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload || empty($payload['boleto_id']) || empty($payload['status'])) {
    echo json_encode(['ok' => true]);
    exit;
}

try {
    $conn = getConnection();

    if ($payload['status'] !== 'PAID') {
        echo json_encode(['ok' => true]);
        exit;
    }

    $boletoId = $payload['boleto_id'];

    // ATUALIZA CONTAS A RECEBER
    $stmt = $conn->prepare("
        UPDATE contas_receber
        SET status = 'pago',
            data_pagamento = NOW(),
            gateway = 'cora'
        WHERE boleto_id = ?
        AND status <> 'pago'
    ");
    $stmt->execute([$boletoId]);

    // RECIBO
    $stmt = $conn->prepare("
        INSERT INTO recibos (conta_id, cliente_id, valor, gateway)
        SELECT id, cliente_id, valor, 'cora'
        FROM contas_receber
        WHERE boleto_id = ?
    ");
    $stmt->execute([$boletoId]);

} catch (Throwable $e) {
    // nunca retorna erro
}

echo json_encode(['ok' => true]);
exit;
