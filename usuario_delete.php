<?php
require_once 'auth.php';
verificarAdmin(); // Apenas administradores podem acessar

require_once 'config.php';

$usuarioId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($usuarioId > 0) {
    // Não permitir excluir o próprio usuário
    if ($usuarioId == $_SESSION['usuario_id']) {
        $_SESSION['erro'] = "Você não pode excluir seu próprio usuário!";
        header('Location: usuarios.php');
        exit;
    }
    
    try {
        $conn = getConnection();
        
        // Excluir usuário
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$usuarioId]);
        
        $_SESSION['mensagem'] = "Usuário excluído com sucesso!";
    } catch (Exception $e) {
        $_SESSION['erro'] = "Erro ao excluir usuário: " . $e->getMessage();
    }
}

header('Location: usuarios.php');
exit;
?>
