<?php
/**
 * Página de Logout - ERP INLAUDO
 * Versão: 2.2.0
 * 
 * Destrói a sessão do usuário e redireciona para login.php
 */

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir funções de autenticação
require_once 'auth.php';

// Executar logout
logout('Logout realizado com sucesso.');
