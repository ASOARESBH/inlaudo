<?php
/**
 * Checkout Mercado Pago
 * ERP INLAUDO - VERSÃO FINAL CORRETA
 */

require_once '../config.php';
require_once '../lib_mercadopago.php';
require_once '../verifica_sessao_cliente.php';

$conn = getConnection();

/**
 * 1️⃣ VALIDAR CONTA
 */
$conta_id = isset($_GET['conta_id']) ? (int)$_GET['conta_id'] : 0;

if ($conta_id <= 0) {
    die('Conta inválida');
}

/**
 * 2️⃣ BUSCAR CONTA ESPECÍFICA (SEM ORDER BY DESC!)
 */
$stmt = $conn->prepare("
    SELECT *
    FROM contas_receber
    WHERE id = ?
      AND cliente_id = ?
      AND status = 'pendente'
    LIMIT 1
");
$stmt->execute([$conta_id, $cliente_id]);
$conta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conta) {
    die('Conta não encontrada ou já paga');
}

/**
 * 3️⃣ CRIAR REFERÊNCIA ÚNICA (CRÍTICO)
 */
$externalReference = 'CONTA_' . $conta['id'];

/**
 * 4️⃣ CRIAR PREFERÊNCIA NO MERCADO PAGO
 */
$mp = new MercadoPago();

$preferencia = $mp->criarPreferencia([
    'descricao'           => $conta['descricao'],
    'valor'               => (float)$conta['valor'],
    'external_reference'  => $externalReference
]);

if (!$preferencia['sucesso']) {
    die('Erro ao gerar pagamento: ' . ($preferencia['erro'] ?? 'Erro desconhecido'));
}

/**
 * 5️⃣ SALVAR REFERÊNCIA NO ERP (ANTES DO PAGAMENTO)
 */
$stmt = $conn->prepare("
    UPDATE contas_receber
    SET
        gateway = 'mercadopago',
        external_reference = ?,
        status_gateway = 'checkout_criado',
        gateway_payload = ?
    WHERE id = ?
");
$stmt->execute([
    $externalReference,
    json_encode($preferencia),
    $conta['id']
]);

/**
 * 6️⃣ REDIRECIONAR PARA O CHECKOUT
 */
header('Location: ' . $preferencia['init_point']);
exit;
