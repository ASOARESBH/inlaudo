<?php
/**
 * Rotas da API Asaas
 * 
 * Endpoints para integração com Asaas
 * Coloque este arquivo na raiz do projeto e configure o .htaccess ou nginx
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Controllers\AsaasController;

// Headers CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Tratar preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Extrair rota
$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$request = explode('?', $request)[0];

$basePath = '/api/asaas';
if (strpos($request, $basePath) === 0) {
    $route = substr($request, strlen($basePath));
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Rota não encontrada']);
    exit;
}

$controller = new AsaasController();

try {
    // POST /api/asaas/customers - Buscar ou criar cliente
    if ($route === '/customers' && $method === 'POST') {
        $controller->findOrCreateCustomer();
    }
    // POST /api/asaas/payments - Criar cobrança
    elseif ($route === '/payments' && $method === 'POST') {
        $controller->createPayment();
    }
    // GET /api/asaas/payments/{paymentId} - Obter status
    elseif (preg_match('#^/payments/([a-zA-Z0-9_-]+)$#', $route, $matches) && $method === 'GET') {
        $controller->getPaymentStatus($matches[1]);
    }
    // Rota não encontrada
    else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint não encontrado']);
    }
} catch (\Exception $e) {
    error_log('[ASAAS ROUTER] Erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}
