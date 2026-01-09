<?php
/**
 * Asaas - Consultar Status de Cobrança
 * 
 * Verifica o status de uma cobrança no Asaas
 */

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once 'src/services/AsaasService.php';
require_once 'src/models/AsaasModel.php';

$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    $payment_id = $_POST['payment_id'] ?? '';
    
    if (empty($payment_id)) {
        throw new Exception('ID do pagamento é obrigatório');
    }
    
    // Conectar ao banco
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obter configuração Asaas
    $sql = "SELECT * FROM integracao_asaas WHERE id = 1 AND ativo = 1 LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        throw new Exception('Integração Asaas não está configurada');
    }
    
    // Inicializar serviço
    $asaas = new AsaasService($config['api_key'], $config['ambiente']);
    $model = new AsaasModel($pdo);
    
    // Consultar status no Asaas
    $resultado = $asaas->obterCobranca($payment_id);
    
    if (!$resultado['success']) {
        throw new Exception($resultado['message']);
    }
    
    $payment_data = $resultado['data'];
    
    // Atualizar status no banco
    $sql = "UPDATE asaas_pagamentos SET status_asaas = ? WHERE asaas_payment_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$payment_data['status'] ?? 'PENDING', $payment_id]);
    
    // Preparar resposta
    $response_data = [
        'id' => $payment_data['id'] ?? $payment_id,
        'status' => $payment_data['status'] ?? 'PENDING',
        'value' => $payment_data['value'] ?? 0,
        'dueDate' => $payment_data['dueDate'] ?? null,
        'billingType' => $payment_data['billingType'] ?? null,
        'description' => $payment_data['description'] ?? null,
        'confirmedDate' => $payment_data['confirmedDate'] ?? null,
        'paymentDate' => $payment_data['paymentDate'] ?? null
    ];
    
    // Adicionar dados específicos por tipo
    if ($payment_data['billingType'] === 'PIX') {
        if (isset($payment_data['dict'])) {
            $response_data['qr_code'] = $payment_data['dict']['qrCode'] ?? null;
            $response_data['payload_pix'] = $payment_data['dict']['payload'] ?? null;
        }
    } else if ($payment_data['billingType'] === 'BOLETO') {
        $response_data['invoice_url'] = $payment_data['invoiceUrl'] ?? null;
        $response_data['nosso_numero'] = $payment_data['nossoNumero'] ?? null;
        $response_data['linha_digitavel'] = $payment_data['bankSlipUrl'] ?? null;
    }
    
    $response['success'] = true;
    $response['message'] = 'Status consultado com sucesso';
    $response['data'] = $response_data;
    
    // Registrar log
    $model->registrarLog(
        'CONSULTAR_COBRANCA',
        'sucesso',
        ['payment_id' => $payment_id],
        $response_data
    );
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // Registrar erro
    if (isset($model)) {
        $model->registrarLog(
            'CONSULTAR_COBRANCA',
            'erro',
            $_POST ?? [],
            ['erro' => $e->getMessage()]
        );
    }
}

// Retornar resposta
header('Content-Type: application/json');
echo json_encode($response);
?>
