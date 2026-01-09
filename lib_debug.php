<?php
/**
 * Biblioteca de Debug e Auditoria
 * Sistema completo de logs para rastreamento de problemas
 */

// Definir se o modo debug está ativo
define('DEBUG_MODE', true);

/**
 * Escrever log de debug
 */
function debug_log($mensagem, $dados = null, $tipo = 'INFO') {
    if (!DEBUG_MODE) return;
    
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/debug_' . date('Y-m-d') . '.log';
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $url = $_SERVER['REQUEST_URI'] ?? 'CLI';
    
    $logEntry = "[$timestamp] [$tipo] [$ip] $mensagem\n";
    
    if ($dados !== null) {
        $logEntry .= "Dados: " . print_r($dados, true) . "\n";
    }
    
    $logEntry .= "URL: $url\n";
    $logEntry .= str_repeat('-', 80) . "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Log de autenticação
 */
function auth_log($acao, $email, $sucesso, $detalhes = '') {
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/auth_' . date('Y-m-d') . '.log';
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
    $status = $sucesso ? 'SUCESSO' : 'FALHA';
    
    $logEntry = "[$timestamp] [$status] $acao\n";
    $logEntry .= "E-mail: $email\n";
    $logEntry .= "IP: $ip\n";
    $logEntry .= "User-Agent: $userAgent\n";
    
    if ($detalhes) {
        $logEntry .= "Detalhes: $detalhes\n";
    }
    
    $logEntry .= str_repeat('=', 80) . "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Log de senha (apenas para debug - REMOVER EM PRODUÇÃO)
 */
function senha_debug_log($email, $senhaDigitada, $hashBanco, $resultado) {
    if (!DEBUG_MODE) return;
    
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/senha_debug_' . date('Y-m-d') . '.log';
    
    $timestamp = date('Y-m-d H:i:s');
    
    $logEntry = "[$timestamp] VERIFICAÇÃO DE SENHA\n";
    $logEntry .= "E-mail: $email\n";
    $logEntry .= "Senha digitada: $senhaDigitada\n";
    $logEntry .= "Hash no banco: $hashBanco\n";
    $logEntry .= "Resultado password_verify: " . ($resultado ? 'TRUE' : 'FALSE') . "\n";
    $logEntry .= "Versão PHP: " . PHP_VERSION . "\n";
    $logEntry .= "Algoritmo disponível: " . (defined('PASSWORD_DEFAULT') ? 'SIM' : 'NÃO') . "\n";
    $logEntry .= str_repeat('=', 80) . "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Log de erro
 */
function error_log_custom($mensagem, $exception = null) {
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    
    $logEntry = "[$timestamp] ERRO\n";
    $logEntry .= "IP: $ip\n";
    $logEntry .= "Mensagem: $mensagem\n";
    
    if ($exception) {
        $logEntry .= "Exception: " . $exception->getMessage() . "\n";
        $logEntry .= "File: " . $exception->getFile() . ":" . $exception->getLine() . "\n";
        $logEntry .= "Stack trace:\n" . $exception->getTraceAsString() . "\n";
    }
    
    $logEntry .= str_repeat('=', 80) . "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Log de SQL
 */
function sql_log($query, $params = [], $erro = null) {
    if (!DEBUG_MODE) return;
    
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/sql_' . date('Y-m-d') . '.log';
    
    $timestamp = date('Y-m-d H:i:s');
    
    $logEntry = "[$timestamp] SQL QUERY\n";
    $logEntry .= "Query: $query\n";
    
    if (!empty($params)) {
        $logEntry .= "Params: " . print_r($params, true) . "\n";
    }
    
    if ($erro) {
        $logEntry .= "ERRO: $erro\n";
    }
    
    $logEntry .= str_repeat('-', 80) . "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Exibir informações de debug na tela (apenas se DEBUG_MODE ativo)
 */
function debug_display($titulo, $dados) {
    if (!DEBUG_MODE) return;
    
    echo "<div style='background: #f8f9fa; border: 2px solid #dc2626; padding: 15px; margin: 10px 0; border-radius: 8px; font-family: monospace;'>";
    echo "<strong style='color: #dc2626;'>DEBUG: $titulo</strong><br>";
    echo "<pre style='margin: 10px 0; background: white; padding: 10px; border-radius: 4px; overflow-x: auto;'>";
    print_r($dados);
    echo "</pre>";
    echo "</div>";
}

/**
 * Informações do sistema
 */
function get_system_info() {
    return [
        'php_version' => PHP_VERSION,
        'password_hash_available' => function_exists('password_hash'),
        'password_verify_available' => function_exists('password_verify'),
        'password_default' => defined('PASSWORD_DEFAULT') ? PASSWORD_DEFAULT : 'N/A',
        'password_bcrypt' => defined('PASSWORD_BCRYPT') ? PASSWORD_BCRYPT : 'N/A',
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
        'os' => PHP_OS,
        'date' => date('Y-m-d H:i:s'),
    ];
}

/**
 * Testar hash de senha
 */
function test_password_hash($senha) {
    debug_log("Testando geração de hash", ['senha' => $senha]);
    
    $hash = password_hash($senha, PASSWORD_DEFAULT);
    
    debug_log("Hash gerado", [
        'hash' => $hash,
        'algoritmo' => PASSWORD_DEFAULT,
        'php_version' => PHP_VERSION
    ]);
    
    $verify = password_verify($senha, $hash);
    
    debug_log("Verificação de hash", [
        'resultado' => $verify ? 'SUCESSO' : 'FALHA'
    ]);
    
    return [
        'hash' => $hash,
        'verify' => $verify,
        'info' => password_get_info($hash)
    ];
}
?>
