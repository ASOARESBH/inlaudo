<?php
/**
 * API Dashboard Data
 * Endpoint para fornecer dados dinâmicos do dashboard
 * 
 * @author ERP INLAUDO
 * @version 1.0.0
 * @date 2026-01-09
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Não autenticado'
    ]);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/models/DashboardModel.php';

try {
    $conn = getConnection();
    $dashboardModel = new DashboardModel($conn);
    
    // Obter tipo de dados solicitado
    $type = $_GET['type'] ?? 'all';
    
    $response = [
        'success' => true,
        'data' => []
    ];
    
    switch ($type) {
        case 'kpis':
            // Retorna todos os KPIs
            $response['data'] = [
                'clientes_ativos' => $dashboardModel->getTotalClientesAtivos(),
                'leads' => $dashboardModel->getTotalLeads(),
                'receita_mensal' => $dashboardModel->getReceitaMensal(),
                'contas_receber' => $dashboardModel->getContasReceber(),
                'contas_pagar' => $dashboardModel->getContasPagar(),
                'contas_vencidas' => $dashboardModel->getContasVencidas()
            ];
            break;
            
        case 'fluxo_caixa':
            // Retorna dados do fluxo de caixa
            $response['data'] = $dashboardModel->getFluxoCaixa();
            break;
            
        case 'contas_status':
            // Retorna distribuição de contas por status
            $response['data'] = $dashboardModel->getContasPorStatus();
            break;
            
        case 'ultimas_contas':
            // Retorna últimas contas a receber
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
            $response['data'] = $dashboardModel->getUltimasContasReceber($limit);
            break;
            
        case 'all':
        default:
            // Retorna todos os dados
            $response['data'] = [
                'kpis' => [
                    'clientes_ativos' => $dashboardModel->getTotalClientesAtivos(),
                    'leads' => $dashboardModel->getTotalLeads(),
                    'receita_mensal' => $dashboardModel->getReceitaMensal(),
                    'contas_receber' => $dashboardModel->getContasReceber(),
                    'contas_pagar' => $dashboardModel->getContasPagar(),
                    'contas_vencidas' => $dashboardModel->getContasVencidas()
                ],
                'fluxo_caixa' => $dashboardModel->getFluxoCaixa(),
                'contas_status' => $dashboardModel->getContasPorStatus(),
                'ultimas_contas' => $dashboardModel->getUltimasContasReceber(5)
            ];
            break;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar dados: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
