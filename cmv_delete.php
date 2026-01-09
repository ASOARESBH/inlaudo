<?php
require_once 'config.php';

$custoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$contratoId = isset($_GET['contrato']) ? (int)$_GET['contrato'] : 0;

if ($custoId > 0 && $contratoId > 0) {
    $conn = getConnection();
    
    try {
        $stmt = $conn->prepare("DELETE FROM cmv WHERE id = ?");
        $stmt->execute([$custoId]);
        
        header("Location: contrato_cmv.php?id=$contratoId&msg=" . urlencode('Custo excluído com sucesso!'));
        exit;
        
    } catch (PDOException $e) {
        header("Location: contrato_cmv.php?id=$contratoId&erro=" . urlencode('Erro ao excluir custo: ' . $e->getMessage()));
        exit;
    }
} else {
    header('Location: contratos.php');
    exit;
}
?>
