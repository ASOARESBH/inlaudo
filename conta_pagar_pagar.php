<?php
require_once 'config.php';

$contaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($contaId > 0) {
    $conn = getConnection();
    
    try {
        $stmt = $conn->prepare("UPDATE contas_pagar SET status = 'pago', data_pagamento = CURDATE() WHERE id = ?");
        $stmt->execute([$contaId]);
        
        header('Location: contas_pagar.php?msg=' . urlencode('Conta marcada como paga com sucesso!'));
        exit;
        
    } catch (PDOException $e) {
        header('Location: contas_pagar.php?erro=' . urlencode('Erro ao marcar conta como paga: ' . $e->getMessage()));
        exit;
    }
} else {
    header('Location: contas_pagar.php');
    exit;
}
?>
