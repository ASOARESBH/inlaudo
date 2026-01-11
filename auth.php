<?php
/**
 * Funções de Autenticação - ERP INLAUDO
 * Versão: 2.2.0
 * 
 * Funções para login, logout e verificação de autenticação
 */

/**
 * Registra log de auditoria de autenticação
 * 
 * @param string $acao Ação realizada (login, logout, acesso_negado, etc)
 * @param string $email E-mail do usuário
 * @param string $detalhes Detalhes adicionais
 * @param bool $sucesso Se a ação foi bem-sucedida
 */
function registrarLogAuth($acao, $email = '', $detalhes = '', $sucesso = true) {
    $logDir = __DIR__ . '/logs';
    
    // Criar diretório de logs se não existir
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/auth_' . date('Y-m-d') . '.log';
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    $status = $sucesso ? 'SUCESSO' : 'FALHA';
    
    $logEntry = sprintf(
        "[%s] %s | Ação: %s | E-mail: %s | IP: %s | Status: %s | Detalhes: %s | User-Agent: %s\n",
        $timestamp,
        session_id(),
        $acao,
        $email,
        $ip,
        $status,
        $detalhes,
        $userAgent
    );
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Função de logout
 * Destrói a sessão e redireciona para login.php
 * 
 * @param string $mensagem Mensagem opcional para exibir no login
 */
function logout($mensagem = '') {
    // Registrar log de logout
    $email = $_SESSION['usuario_email'] ?? 'DESCONHECIDO';
    $nome = $_SESSION['usuario_nome'] ?? 'DESCONHECIDO';
    $sessionDuration = isset($_SESSION['login_time']) ? (time() - $_SESSION['login_time']) : 0;
    
    registrarLogAuth(
        'LOGOUT',
        $email,
        sprintf('Usuário: %s | Duração da sessão: %d segundos', $nome, $sessionDuration),
        true
    );
    
    // Destruir todas as variáveis de sessão
    $_SESSION = array();
    
    // Destruir o cookie de sessão se existir
    if (isset($_COOKIE[session_name()])) {
        setcookie(
            session_name(),
            '',
            time() - 42000,
            '/',
            '',
            isset($_SERVER['HTTPS']),
            true
        );
    }
    
    // Destruir a sessão
    session_destroy();
    
    // Redirecionar para login.php
    if (!empty($mensagem)) {
        header('Location: login.php?msg=' . urlencode($mensagem));
    } else {
        header('Location: login.php');
    }
    exit;
}

/**
 * Verifica se o usuário está autenticado
 * Se não estiver, redireciona para login.php
 * 
 * @return bool
 */
function verificarAutenticacao() {
    if (!isset($_SESSION['usuario_id'])) {
        registrarLogAuth(
            'ACESSO_NEGADO',
            '',
            'Tentativa de acesso sem autenticação | URL: ' . ($_SERVER['REQUEST_URI'] ?? ''),
            false
        );
        
        header('Location: login.php?msg=' . urlencode('Sessão expirada. Faça login novamente.'));
        exit;
    }
    
    // Atualizar último acesso
    $_SESSION['ultimo_acesso'] = time();
    
    return true;
}

/**
 * Verifica se o usuário tem permissão de admin
 * 
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === 'admin';
}

/**
 * Verifica se o usuário é cliente
 * 
 * @return bool
 */
function isCliente() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'cliente';
}

/**
 * Obtém o tempo de sessão em formato legível
 * 
 * @return string
 */
function getTempoSessao() {
    if (!isset($_SESSION['login_time'])) {
        return '0 minutos';
    }
    
    $segundos = time() - $_SESSION['login_time'];
    
    if ($segundos < 60) {
        return $segundos . ' segundo' . ($segundos != 1 ? 's' : '');
    }
    
    $minutos = floor($segundos / 60);
    if ($minutos < 60) {
        return $minutos . ' minuto' . ($minutos != 1 ? 's' : '');
    }
    
    $horas = floor($minutos / 60);
    $minutosRestantes = $minutos % 60;
    
    return $horas . 'h ' . $minutosRestantes . 'min';
}

/**
 * Registra login bem-sucedido
 * 
 * @param array $usuario Dados do usuário
 */
function registrarLogin($usuario) {
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['usuario_nivel'] = $usuario['nivel'] ?? 'usuario';
    $_SESSION['usuario_tipo'] = $usuario['tipo_usuario'] ?? 'usuario';
    $_SESSION['login_time'] = time();
    $_SESSION['ultimo_acesso'] = time();
    
    registrarLogAuth(
        'LOGIN',
        $usuario['email'],
        sprintf('Usuário: %s | Nível: %s | Tipo: %s', 
            $usuario['nome'], 
            $usuario['nivel'] ?? 'usuario',
            $usuario['tipo_usuario'] ?? 'usuario'
        ),
        true
    );
}

/**
 * Verifica timeout de sessão (30 minutos de inatividade)
 * 
 * @param int $timeout Tempo de timeout em segundos (padrão: 1800 = 30 minutos)
 */
function verificarTimeout($timeout = 1800) {
    if (isset($_SESSION['ultimo_acesso'])) {
        $inativo = time() - $_SESSION['ultimo_acesso'];
        
        if ($inativo > $timeout) {
            registrarLogAuth(
                'TIMEOUT',
                $_SESSION['usuario_email'] ?? '',
                sprintf('Sessão expirada por inatividade | Tempo inativo: %d segundos', $inativo),
                false
            );
            
            logout('Sua sessão expirou por inatividade. Faça login novamente.');
        }
    }
}
