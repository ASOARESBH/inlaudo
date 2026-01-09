<?php
/**
 * Arquivo de Autenticação - Portal do Cliente
 * Verifica se o usuário está logado e é do tipo cliente
 */

if (!isset($_SESSION)) {
    session_start();
}

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar se é cliente
if (!isset($_SESSION['usuario_tipo']) || $_SESSION['usuario_tipo'] != 'cliente') {
    header('Location: index.php'); // Redirecionar para área administrativa
    exit;
}

// Verificar se tem cliente_id
if (!isset($_SESSION['cliente_id']) || empty($_SESSION['cliente_id'])) {
    session_destroy();
    header('Location: login.php?erro=cliente_invalido');
    exit;
}

// Atualizar último acesso
$_SESSION['ultimo_acesso'] = time();

// Timeout de sessão (30 minutos)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
    session_destroy();
    header('Location: login.php?erro=sessao_expirada');
    exit;
}

// Funções auxiliares para o portal do cliente
function getClienteInfo() {
    require_once 'config.php';
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$_SESSION['cliente_id']]);
    return $stmt->fetch();
}

function formatarCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    if (strlen($cnpj) == 14) {
        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . 
               substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }
    return $cnpj;
}

function formatarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) == 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . 
               substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatarData($data) {
    if (empty($data)) return '-';
    return date('d/m/Y', strtotime($data));
}

function formatarDataHora($data) {
    if (empty($data)) return '-';
    return date('d/m/Y H:i', strtotime($data));
}
?>
