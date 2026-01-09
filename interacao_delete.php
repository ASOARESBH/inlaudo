<?php
require_once 'config.php';

$interacaoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($interacaoId > 0) {
    $conn = getConnection();
    
    try {
        $stmt = $conn->prepare("DELETE FROM interacoes WHERE id = ?");
        $stmt->execute([$interacaoId]);
        
        header('Location: interacoes.php?msg=' . urlencode('Interação excluída com sucesso!'));
        exit;
        
    } catch (PDOException $e) {
        header('Location: interacoes.php?erro=' . urlencode('Erro ao excluir interação: ' . $e->getMessage()));
        exit;
    }
} else {
    header('Location: interacoes.php');
    exit;
}
?>
