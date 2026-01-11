<?php
/**
 * API: Gateways de Pagamento
 * Versão: 2.3.0
 * 
 * Retorna lista de gateways disponíveis
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/models/GatewayPagamentoModel.php';

try {
    $conn = getConnection();
    $gatewayModel = new GatewayPagamentoModel($conn);
    
    // Verificar se é para buscar gateways de uma conta específica
    if (isset($_GET['conta_id'])) {
        $conta_id = (int)$_GET['conta_id'];
        $gateways = $gatewayModel->buscarGatewaysDisponiveis($conta_id);
    } else {
        // Listar todos os gateways ativos
        $gateways = $gatewayModel->listarGatewaysAtivos();
    }
    
    // Formatar resposta
    $response = [
        'success' => true,
        'data' => $gateways,
        'count' => count($gateways)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar gateways: ' . $e->getMessage()
    ]);
}
