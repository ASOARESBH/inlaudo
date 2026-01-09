<?php
/**
 * Arquivo de Entrada Principal - ERP INLAUDO
 * 
 * Ponto de entrada único para toda a aplicação
 */

// ============================================================
// INICIALIZAR APLICAÇÃO
// ============================================================
require_once dirname(dirname(__FILE__)) . '/src/core/Bootstrap.php';

use App\Core\Bootstrap;

// Inicializar sistema
Bootstrap::init();

// ============================================================
// ROTEAMENTO BÁSICO
// ============================================================
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = str_replace('public/index.php', '', $_SERVER['SCRIPT_NAME']);
$route = str_replace($base_path, '', $request_uri);
$route = trim($route, '/');

// Redirecionar para dashboard se não houver rota
if (empty($route)) {
    $route = 'dashboard';
}

// ============================================================
// VERIFICAR AUTENTICAÇÃO
// ============================================================
$public_routes = ['login', 'login-cliente', 'webhook', 'api'];
$is_public = false;

foreach ($public_routes as $public_route) {
    if (strpos($route, $public_route) === 0) {
        $is_public = true;
        break;
    }
}

if (!$is_public && !isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

// ============================================================
// CARREGAR PÁGINA APROPRIADA
// ============================================================
$page_file = dirname(__FILE__) . '/../src/views/' . $route . '.php';

if (file_exists($page_file)) {
    include $page_file;
} else {
    http_response_code(404);
    echo 'Página não encontrada: ' . htmlspecialchars($route);
}
