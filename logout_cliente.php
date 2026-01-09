<?php
/**
 * Logout do Cliente
 * Portal do Cliente - INLAUDO
 */

session_start();

// Limpar todas as variáveis de sessão
$_SESSION = array();

// Destruir o cookie de sessão
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Destruir a sessão
session_destroy();

// Redirecionar para login com mensagem de sucesso
header('Location: login_cliente.php?logout=1');
exit;
?>
