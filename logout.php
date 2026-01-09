<?php
/**
 * Página de Logout
 */

session_start();
require_once 'auth.php';

// Fazer logout
logout();
?>
