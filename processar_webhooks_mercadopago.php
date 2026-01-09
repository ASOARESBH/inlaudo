<?php
/**
 * PROCESSADOR DE WEBHOOKS - MERCADO PAGO
 * ERP INLAUDO
 */

require_once __DIR__ . '/config.php';

$logFile = __DIR__ . '/logs/processar_webhooks.log';

function logMsg($msg) {
    global $logFile;
    file_put_contents($logFile, '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL, FILE_APPEND);
}

logMsg('Iniciando processador Mercado Pago');

$conn = getConnection();

/**
 * Busca webhooks pendentes
 */
$stmt = $conn->prepare("
    SELECT *
    FROM webhooks_pagamento
    WHERE gateway = 'mercadopago'
      AND processado = 0
      AND evento = 'payment'
    ORDER BY id ASC
    LIMIT 20
");
$stmt->execute();
$webhooks = $stmt->fetchAll();

logMsg('Webhooks encontrados: '.count($webhooks));

foreach ($webhooks as $wh) {

    try {

        $idWebhook = $wh['id'];
        $payload = json_decode($wh['payload'], true);

        // ID do pagamento pode vir em formatos diferentes
        $paymentId =
            $payload['data']['id'] ??
            $payload['id'] ??
            null;

        if (!$paymentId) {
            throw new Exception('payment_id não encontrado no payload');
        }

        logMsg("Webhook {$idWebhook} | payment_id={$paymentId}");

        /**
         * Consulta API Mercado Pago
         */
        $cfg = $conn->query("
            SELECT access_token
            FROM configuracoes_gateway
            WHERE gateway = 'mercadopago'
              AND ativo = 1
            LIMIT 1
        ")->fetch();

        if (!$cfg) {
            throw new Exception('Configuração Mercado Pago não encontrada');
        }

        $ch = curl_init("https://api.mercadopago.com/v1/payments/{$paymentId}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$cfg['access_token']
            ]
        ]);

        $resp = curl_exec($ch);
        curl_close($ch);

        $mp = json_decode($resp, true);

        if (empty($mp['status'])) {
            throw new Exception('Resposta inválida da API MP');
        }

        /**
         * Só processa se estiver aprovado
         */
        if ($mp['status'] !== 'approved') {
            logMsg("Pagamento {$paymentId} ainda não aprovado ({$mp['status']})");
            continue;
        }

        /**
         * Recupera conta pelo external_reference
         */
        $external = $mp['external_reference'] ?? null;

        if (!$external || !str_starts_with($external, 'conta_')) {
            throw new Exception('external_reference inválido');
        }

        $contaId = (int) str_replace('conta_', '', $external);

        /**
         * Atualiza contas_receber
         */
        $stmt = $conn->prepare("
            UPDATE contas_receber
            SET status = 'pago',
                status_gateway = 'approved',
                data_pagamento = NOW(),
                payment_id = ?
            WHERE id = ?
              AND status <> 'pago'
        ");
        $stmt->execute([$paymentId, $contaId]);

        /**
         * Marca webhook como processado
         */
        $stmt = $conn->prepare("
            UPDATE webhooks_pagamento
            SET processado = 1,
                data_processamento = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$idWebhook]);

        logMsg("Conta {$contaId} baixada com sucesso");

    } catch (Throwable $e) {

        $stmt = $conn->prepare("
            UPDATE webhooks_pagamento
            SET erro = ?
            WHERE id = ?
        ");
        $stmt->execute([$e->getMessage(), $idWebhook]);

        logMsg("Erro webhook {$idWebhook}: ".$e->getMessage());
    }
}

logMsg('Processamento finalizado');
