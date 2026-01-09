<?php
/**
 * API - Listar Clientes
 * 
 * GET /api/clientes/listar
 */

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

use App\Core\Database;

$db = Database::getInstance();

try {
    // Parâmetros
    $pagina = $_GET['pagina'] ?? 1;
    $por_pagina = $_GET['por_pagina'] ?? 20;
    $busca = $_GET['busca'] ?? '';
    $offset = ($pagina - 1) * $por_pagina;

    // Construir query
    $where = [];
    $params = [];

    if ($busca) {
        $where[] = "(nome LIKE ? OR email LIKE ? OR cnpj_cpf LIKE ?)";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
        $params[] = "%$busca%";
    }

    $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Obter total
    $sql_total = "SELECT COUNT(*) as total FROM clientes $where_clause";
    $resultado_total = $db->fetchOne($sql_total, $params);
    $total = $resultado_total['total'] ?? 0;

    // Obter clientes
    $sql = "SELECT id, nome, email, cnpj_cpf, tipo_cliente, ativo, data_criacao 
            FROM clientes 
            $where_clause 
            ORDER BY data_criacao DESC 
            LIMIT ? OFFSET ?";
    
    $params[] = $por_pagina;
    $params[] = $offset;
    
    $clientes = $db->fetchAll($sql, $params);

    // Retornar resposta
    echo json_encode([
        'success' => true,
        'data' => $clientes,
        'pagination' => [
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $por_pagina,
            'total_paginas' => ceil($total / $por_pagina)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
