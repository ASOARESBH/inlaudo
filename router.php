<?php
/**
 * Router Centralizado - ERP INLAUDO
 * 
 * Gerencia todas as rotas de páginas e APIs
 * Implementa padrão MVC profissional
 */

// Definir constantes
define('BASE_PATH', dirname(__FILE__));
define('PAGES_PATH', BASE_PATH . '/pages');
define('API_PATH', BASE_PATH . '/api');
define('VIEWS_PATH', BASE_PATH . '/src/views');
define('MODELS_PATH', BASE_PATH . '/src/models');
define('SERVICES_PATH', BASE_PATH . '/src/services');
define('CONTROLLERS_PATH', BASE_PATH . '/src/controllers');

// Inicializar aplicação
require_once BASE_PATH . '/src/core/Bootstrap.php';
require_once BASE_PATH . '/src/core/Autoloader.php';

use App\Core\Bootstrap;
use App\Core\Autoloader;

// Registrar autoloader
$autoloader = new Autoloader();
$autoloader->register();

// Inicializar bootstrap
Bootstrap::init();

// Obter rota
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Remover base path da URI
$base_url = dirname($script_name);
if ($base_url !== '/') {
    $request_uri = str_replace($base_url, '', $request_uri);
}

// Remover query string
$route = explode('?', $request_uri)[0];
$route = trim($route, '/');

// Dividir rota em segmentos
$segments = array_filter(explode('/', $route));
$segments = array_values($segments); // Re-indexar array

// Definir mapa de rotas
$routes = [
    // Páginas principais
    'dashboard' => ['type' => 'page', 'path' => 'pages/dashboard/index.php'],
    'clientes' => ['type' => 'page', 'path' => 'pages/clientes/index.php'],
    'clientes/novo' => ['type' => 'page', 'path' => 'pages/clientes/form.php'],
    'clientes/editar' => ['type' => 'page', 'path' => 'pages/clientes/form.php'],
    'clientes/view' => ['type' => 'page', 'path' => 'pages/clientes/view.php'],
    
    'contas-receber' => ['type' => 'page', 'path' => 'pages/contas/receber.php'],
    'contas-receber/novo' => ['type' => 'page', 'path' => 'pages/contas/receber-form.php'],
    'contas-receber/editar' => ['type' => 'page', 'path' => 'pages/contas/receber-form.php'],
    
    'contas-pagar' => ['type' => 'page', 'path' => 'pages/contas/pagar.php'],
    'contas-pagar/novo' => ['type' => 'page', 'path' => 'pages/contas/pagar-form.php'],
    'contas-pagar/editar' => ['type' => 'page', 'path' => 'pages/contas/pagar-form.php'],
    
    'alertas' => ['type' => 'page', 'path' => 'pages/dashboard/alertas.php'],
    'boletos' => ['type' => 'page', 'path' => 'pages/integracao/boletos.php'],
    
    'integracao/cora' => ['type' => 'page', 'path' => 'pages/integracao/cora.php'],
    'integracao/mercadopago' => ['type' => 'page', 'path' => 'pages/integracao/mercadopago.php'],
    'integracao/stripe' => ['type' => 'page', 'path' => 'pages/integracao/stripe.php'],
    'integracao/logs' => ['type' => 'page', 'path' => 'pages/integracao/logs.php'],
    
    'configuracao/email' => ['type' => 'page', 'path' => 'pages/configuracao/email.php'],
    'configuracao/templates' => ['type' => 'page', 'path' => 'pages/configuracao/templates.php'],
    'configuracao/historico-email' => ['type' => 'page', 'path' => 'pages/configuracao/historico-email.php'],
    'configuracao/alertas-programados' => ['type' => 'page', 'path' => 'pages/configuracao/alertas-programados.php'],
    'configuracao/usuarios' => ['type' => 'page', 'path' => 'pages/configuracao/usuarios.php'],
    'configuracao/preferencias' => ['type' => 'page', 'path' => 'pages/configuracao/preferencias.php'],
    
    'portal' => ['type' => 'page', 'path' => 'pages/portal/index.php'],
    'portal/contratos' => ['type' => 'page', 'path' => 'pages/portal/contratos.php'],
    'portal/contas' => ['type' => 'page', 'path' => 'pages/portal/contas.php'],
    'portal/helpdesk' => ['type' => 'page', 'path' => 'pages/portal/helpdesk.php'],
    
    // APIs
    'api/clientes/listar' => ['type' => 'api', 'path' => 'api/clientes/listar.php'],
    'api/clientes/criar' => ['type' => 'api', 'path' => 'api/clientes/criar.php'],
    'api/clientes/atualizar' => ['type' => 'api', 'path' => 'api/clientes/atualizar.php'],
    'api/clientes/deletar' => ['type' => 'api', 'path' => 'api/clientes/deletar.php'],
    
    'api/contas/listar' => ['type' => 'api', 'path' => 'api/contas/listar.php'],
    'api/contas/criar' => ['type' => 'api', 'path' => 'api/contas/criar.php'],
    'api/contas/atualizar' => ['type' => 'api', 'path' => 'api/contas/atualizar.php'],
    'api/contas/deletar' => ['type' => 'api', 'path' => 'api/contas/deletar.php'],
    
    'api/email/testar' => ['type' => 'api', 'path' => 'api/email/testar.php'],
    'api/email/enviar' => ['type' => 'api', 'path' => 'api/email/enviar.php'],
    
    'api/alertas/listar' => ['type' => 'api', 'path' => 'api/alertas/listar.php'],
    'api/alertas/gerar' => ['type' => 'api', 'path' => 'api/alertas/gerar.php'],
    
    'api/pagamentos/processar' => ['type' => 'api', 'path' => 'api/pagamentos/processar.php'],
    'api/pagamentos/confirmar' => ['type' => 'api', 'path' => 'api/pagamentos/confirmar.php'],
    
    'api/integracao/cora/webhook' => ['type' => 'api', 'path' => 'api/integracao/cora-webhook.php'],
    'api/integracao/mercadopago/webhook' => ['type' => 'api', 'path' => 'api/integracao/mercadopago-webhook.php'],
];

// Encontrar rota correspondente
$route_found = false;
$route_file = null;
$route_type = null;

foreach ($routes as $pattern => $config) {
    if ($route === $pattern || $route === '') {
        $route_found = true;
        $route_file = $config['path'];
        $route_type = $config['type'];
        break;
    }
}

// Se rota não encontrada, tentar rota com parâmetros
if (!$route_found && count($segments) > 0) {
    // Verificar rotas dinâmicas
    $base_route = implode('/', array_slice($segments, 0, -1));
    
    if (isset($routes[$base_route])) {
        $route_found = true;
        $route_file = $routes[$base_route]['path'];
        $route_type = $routes[$base_route]['type'];
    }
}

// Processar requisição
if ($route_found && $route_file) {
    // Verificar autenticação para páginas protegidas
    if ($route_type === 'page' && !in_array($route, ['login', 'register', 'portal'])) {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
    }
    
    // Incluir arquivo
    $file_path = BASE_PATH . '/' . $route_file;
    
    if (file_exists($file_path)) {
        include $file_path;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Arquivo não encontrado: ' . $route_file]);
    }
} else {
    // Rota não encontrada
    http_response_code(404);
    
    if (strpos($route, 'api/') === 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API endpoint não encontrado']);
    } else {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>404 - Página não encontrada</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 50px; }
                h1 { color: #e74c3c; }
                p { color: #7f8c8d; }
            </style>
        </head>
        <body>
            <h1>404 - Página não encontrada</h1>
            <p>A rota "<strong>' . htmlspecialchars($route) . '</strong>" não existe.</p>
            <p><a href="' . BASE_URL . '/dashboard">Voltar ao Dashboard</a></p>
        </body>
        </html>';
    }
}
