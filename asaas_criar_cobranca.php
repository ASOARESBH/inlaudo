<?php
/**
 * Asaas - Criar Cobrança
 * 
 * Processa a criação de cobranças (PIX/Boleto)
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
    
    $cliente_id = $_POST['cliente_id'] ?? '';
    $tipo_cobranca = $_POST['tipo_cobranca'] ?? '';
    $valor = $_POST['valor'] ?? '';
    $data_vencimento = $_POST['data_vencimento'] ?? '';
    
    if (empty($cliente_id) || empty($tipo_cobranca) || empty($valor) || empty($data_vencimento)) {
        throw new Exception('Todos os campos são obrigatórios');
    }
    
    if (!in_array($tipo_cobranca, ['PIX', 'BOLETO'])) {
        throw new Exception('Tipo de cobrança inválido');
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
    
    // Buscar cliente local
    $sql = "SELECT id, nome, email FROM clientes WHERE id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cliente_id]);
    $cliente_local = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente_local) {
        throw new Exception('Cliente não encontrado');
    }
    
    // Buscar customer_id no Asaas
    $sql = "SELECT asaas_customer_id FROM asaas_clientes WHERE cliente_id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cliente_id]);
    $cliente_asaas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente_asaas) {
        throw new Exception('Cliente não está mapeado no Asaas. Crie o cliente primeiro.');
    }
    
    // Preparar dados da cobrança
    $dados_cobranca = [
        'customer' => $cliente_asaas['asaas_customer_id'],
        'value' => (float)$valor,
        'dueDate' => $data_vencimento,
        'description' => 'Cobrança - ' . $cliente_local['nome'],
        'billingType' => $tipo_cobranca
    ];
    
    // Criar cobrança no Asaas
    $resultado = $asaas->criarCobranca($dados_cobranca);
    
    if (!$resultado['success']) {
        throw new Exception($resultado['message']);
    }
    
    $payment_id = $resultado['data']['id'];
    
    // Salvar mapeamento
    $sql = "INSERT INTO asaas_pagamentos (cliente_id, asaas_payment_id, tipo_cobranca, valor, data_vencimento, status_asaas, data_criacao) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $cliente_id,
        $payment_id,
        $tipo_cobranca,
        $valor,
        $data_vencimento,
        $resultado['data']['status'] ?? 'PENDING'
    ]);
    
    // Preparar dados de resposta
    $response_data = [
        'payment_id' => $payment_id,
        'status' => $resultado['data']['status'] ?? 'PENDING',
        'value' => $valor,
        'dueDate' => $data_vencimento
    ];
    
    // Adicionar dados específicos por tipo
    if ($tipo_cobranca === 'PIX') {
        if (isset($resultado['data']['dict'])) {
            $response_data['qr_code'] = $resultado['data']['dict']['qrCode'] ?? null;
            $response_data['payload_pix'] = $resultado['data']['dict']['payload'] ?? null;
        }
        if (isset($resultado['data']['encodedImage'])) {
            $response_data['encoded_image'] = $resultado['data']['encodedImage'];
        }
    } else if ($tipo_cobranca === 'BOLETO') {
        $response_data['invoice_url'] = $resultado['data']['invoiceUrl'] ?? null;
        $response_data['nosso_numero'] = $resultado['data']['nossoNumero'] ?? null;
        $response_data['linha_digitavel'] = $resultado['data']['bankSlipUrl'] ?? null;
    }
    
    $response['success'] = true;
    $response['message'] = 'Cobrança criada com sucesso';
    $response['data'] = $response_data;
    
    // Registrar log
    $model->registrarLog(
        'CRIAR_COBRANCA',
        'sucesso',
        $dados_cobranca,
        $response_data
    );
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // Registrar erro
    if (isset($model)) {
        $model->registrarLog(
            'CRIAR_COBRANCA',
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
