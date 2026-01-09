<?php
/**
 * Gerador de Pagamentos
 * - Mercado Pago (PIX)
 * - Cora (BOLETO)
 * ERP INLAUDO
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib_boleto_cora_v2.php';

header('Content-Type: text/html; charset=utf-8');

/**
 * Erro controlado (NUNCA 500)
 */
function erro($msg, $debug = null) {
    http_response_code(200);
    echo "<h3>Erro ao gerar pagamento</h3>";
    echo "<p>$msg</p>";
    if ($debug) {
        echo "<pre>";
        print_r($debug);
        echo "</pre>";
    }
    exit;
}

// ======================
// PARÂMETROS
// ======================
$contaId = isset($_GET['conta_id']) ? (int)$_GET['conta_id'] : 0;
$gateway = $_GET['gateway'] ?? '';

if (!$contaId || !$gateway) {
    erro('Parâmetros inválidos.');
}

try {
    $conn = getConnection();

    // ======================
    // CONTA A RECEBER
    // ======================
    $stmt = $conn->prepare("
        SELECT *
        FROM contas_receber
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$contaId]);
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conta) {
        erro('Conta não encontrada.');
    }

    // ===== BLOQUEIOS IMPORTANTES =====
    if ($conta['status'] === 'pago') {
        erro('Esta conta já foi paga.');
    }

    if (!empty($conta['payment_id'])) {
        erro('Pagamento já gerado para esta conta.');
    }

    // ======================
    // ESCOLHA DO GATEWAY
    // ======================
    switch ($gateway) {

        // =====================================================
        // MERCADO PAGO (PIX)
        // =====================================================
        case 'mercadopago':

            // CONFIGURAÇÃO MP
            $stmt = $conn->prepare("
                SELECT *
                FROM configuracoes_gateway
                WHERE gateway = 'mercadopago'
                  AND ativo = 1
                LIMIT 1
            ");
            $stmt->execute();
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                erro('Mercado Pago não configurado.');
            }

            // CLIENTE
            $stmt = $conn->prepare("
                SELECT nome, email, tipo_pessoa, cnpj_cpf
                FROM clientes
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$conta['cliente_id']]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cliente || empty($cliente['email']) || empty($cliente['cnpj_cpf'])) {
                erro('Cliente sem dados obrigatórios.');
            }

            // DOCUMENTO
            $documento = preg_replace('/\D/', '', $cliente['cnpj_cpf']);
            $tipoDocumento = ($cliente['tipo_pessoa'] === 'CNPJ') ? 'CNPJ' : 'CPF';

            // PAYLOAD
            $payload = [
                'transaction_amount' => (float)$conta['valor'],
                'description'        => $conta['descricao'],
                'payment_method_id'  => 'pix',
                'notification_url'   => $config['webhook_url'],
                'external_reference' => 'conta_' . $contaId,
                'payer' => [
                    'email' => $cliente['email'],
                    'first_name' => $cliente['nome'] ?: 'Cliente',
                    'last_name'  => 'INLAUDO',
                    'identification' => [
                        'type'   => $tipoDocumento,
                        'number' => $documento
                    ]
                ]
            ];

            // IDEMPOTENCY
            $idempotencyKey = 'conta_' . $contaId . '_' . uniqid();

            // CHAMADA API
            $ch = curl_init('https://api.mercadopago.com/v1/payments');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $config['access_token'],
                    'Content-Type: application/json',
                    'X-Idempotency-Key: ' . $idempotencyKey
                ],
                CURLOPT_POSTFIELDS => json_encode($payload)
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);

            if (empty($result['id']) ||
                empty($result['point_of_interaction']['transaction_data']['ticket_url'])
            ) {
                erro('Erro ao gerar PIX.', $result);
            }

            // ===== SALVA PAYMENT ID =====
            $stmt = $conn->prepare("
                UPDATE contas_receber
                SET gateway = 'mercadopago',
                    payment_id = ?,
                    idempotency_key = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $result['id'],
                $idempotencyKey,
                $contaId
            ]);

            // REDIRECIONA
            header('Location: ' . $result['point_of_interaction']['transaction_data']['ticket_url']);
            exit;

        // =====================================================
        // CORA (BOLETO)
        // =====================================================
        case 'cora':

            // CLIENTE
            $stmt = $conn->prepare("
                SELECT *
                FROM clientes
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$conta['cliente_id']]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cliente) {
                erro('Cliente não encontrado.');
            }

            // DADOS BOLETO
            $dadosBoleto = [
                'valor'       => number_format($conta['valor'], 2, '.', ''),
                'vencimento'  => $conta['data_vencimento'],
                'descricao'   => $conta['descricao'],
                'documento'   => preg_replace('/\D/', '', $cliente['cnpj_cpf']),
                'tipo_pessoa' => $cliente['tipo_pessoa'],
                'nome'        => $cliente['nome'] ?: $cliente['razao_social'],
                'email'       => $cliente['email'],
                'conta_id'    => $contaId
            ];

            $resultado = coraGerarBoleto($dadosBoleto);

            if (empty($resultado['pdf_url'])) {
                erro('Erro ao gerar boleto Cora.', $resultado);
            }

            // ATUALIZA CONTA
            $stmt = $conn->prepare("
                UPDATE contas_receber
                SET forma_pagamento = 'boleto',
                    gateway = 'cora',
                    boleto_id = ?,
                    status = 'pendente'
                WHERE id = ?
            ");
            $stmt->execute([
                $resultado['boleto_id'] ?? null,
                $contaId
            ]);

            // ABRE PDF
            header('Location: ' . $resultado['pdf_url']);
            exit;

        default:
            erro('Gateway inválido.');
    }

} catch (Throwable $e) {
    erro('Erro interno.', $e->getMessage());
}
