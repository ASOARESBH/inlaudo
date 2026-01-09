<?php
/**
 * Verificação de Sessão do Cliente
 * Incluir no início de todas as páginas do portal do cliente
 */

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o cliente está logado
if (!isset($_SESSION['cliente_logado']) || $_SESSION['cliente_logado'] !== true) {
    // Redirecionar para login
    header('Location: login_cliente.php?erro=acesso_negado');
    exit;
}

// Verificar timeout de sessão (30 minutos de inatividade)
$timeout = 30 * 60; // 30 minutos em segundos

if (isset($_SESSION['ultimo_acesso'])) {
    $tempoInativo = time() - $_SESSION['ultimo_acesso'];
    
    if ($tempoInativo > $timeout) {
        // Sessão expirada
        session_unset();
        session_destroy();
        header('Location: login_cliente.php?erro=sessao_expirada');
        exit;
    }
}

// Atualizar último acesso
$_SESSION['ultimo_acesso'] = time();

// Dados do cliente disponíveis em todas as páginas
$cliente_id = $_SESSION['cliente_id'];
$cliente_nome = $_SESSION['cliente_nome'];
$cliente_cnpj = $_SESSION['cliente_cnpj'];
$cliente_email = $_SESSION['cliente_email'] ?? '';
$cliente_tipo_pessoa = $_SESSION['cliente_tipo_pessoa'] ?? 'CNPJ';
?>
