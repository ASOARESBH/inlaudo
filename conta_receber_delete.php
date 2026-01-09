<?php
require_once 'config.php';

$contaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($contaId > 0) {
    $conn = getConnection();
    
    try {
        $stmt = $conn->prepare("DELETE FROM contas_receber WHERE id = ?");
        $stmt->execute([$contaId]);
        
        header('Location: contas_receber.php?msg=' . urlencode('Conta a receber excluída com sucesso!'));
        exit;
        
    } catch (PDOException $e) {
        header('Location: contas_receber.php?erro=' . urlencode('Erro ao excluir conta a receber: ' . $e->getMessage()));
        exit;
    }
} else {
    header('Location: contas_receber.php');
    exit;
}
?>
