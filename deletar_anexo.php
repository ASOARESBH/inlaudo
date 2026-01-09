<?php
/**
 * Script para deletar anexo via AJAX
 */

session_start();
require_once 'config.php';
require_once 'processar_upload_anexos.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

// Receber dados
$input = json_decode(file_get_contents('php://input'), true);
$anexoId = isset($input['anexo_id']) ? (int)$input['anexo_id'] : 0;

if ($anexoId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de anexo inválido'
    ]);
    exit;
}

try {
    $conn = getConnection();
    $resultado = deletarAnexo($anexoId, $conn);
    echo json_encode($resultado);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar requisição: ' . $e->getMessage()
    ]);
}
?>
