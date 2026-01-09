<?php
require_once 'config.php';

$contratoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($contratoId > 0) {
    $conn = getConnection();
    
    try {
        // Buscar arquivo do contrato para excluir
        $stmt = $conn->prepare("SELECT arquivo_contrato FROM contratos WHERE id = ?");
        $stmt->execute([$contratoId]);
        $contrato = $stmt->fetch();
        
        // Excluir arquivo físico se existir
        if ($contrato && $contrato['arquivo_contrato'] && file_exists($contrato['arquivo_contrato'])) {
            unlink($contrato['arquivo_contrato']);
        }
        
        // Excluir contrato do banco
        $stmt = $conn->prepare("DELETE FROM contratos WHERE id = ?");
        $stmt->execute([$contratoId]);
        
        header('Location: contratos.php?msg=' . urlencode('Contrato excluído com sucesso!'));
        exit;
        
    } catch (PDOException $e) {
        header('Location: contratos.php?erro=' . urlencode('Erro ao excluir contrato: ' . $e->getMessage()));
        exit;
    }
} else {
    header('Location: contratos.php');
    exit;
}
?>
