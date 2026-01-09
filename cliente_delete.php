<?php
require_once 'config.php';

$clienteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($clienteId > 0) {
    $conn = getConnection();
    
    try {
        $stmt = $conn->prepare("DELETE FROM clientes WHERE id = ?");
        $stmt->execute([$clienteId]);
        
        header('Location: clientes.php?msg=' . urlencode('Cliente excluído com sucesso!'));
        exit;
        
    } catch (PDOException $e) {
        header('Location: clientes.php?erro=' . urlencode('Erro ao excluir cliente: ' . $e->getMessage()));
        exit;
    }
} else {
    header('Location: clientes.php');
    exit;
}
?>
