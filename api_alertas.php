<?php
/**
 * API de Alertas - Endpoints para gerenciar alertas via AJAX
 * ERP INLAUDO - Sistema de Alertas em Popup
 */

session_start();

// Validar sessão
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    die(json_encode(['sucesso' => false, 'mensagem' => 'Não autenticado']));
}

require_once 'config.php';
require_once 'lib_alertas.php';
require_once 'lib_logs.php';

header('Content-Type: application/json');

$acao = $_GET['acao'] ?? '';
$usuario_id = $_SESSION['usuario_id'];

try {
    switch ($acao) {
        
        // ============================================================
        // OBTER ALERTAS NÃO VISUALIZADOS
        // ============================================================
        case 'obter_alertas':
            obterAlertas($usuario_id);
            break;
        
        // ============================================================
        // MARCAR ALERTA COMO VISUALIZADO
        // ============================================================
        case 'marcar_visualizado':
            marcarVisualizado($usuario_id);
            break;
        
        // ============================================================
        // CANCELAR CONTA
        // ============================================================
        case 'cancelar_conta':
            cancelarConta($usuario_id);
            break;
        
        // ============================================================
        // OBTER DETALHES DO ALERTA
        // ============================================================
        case 'obter_detalhes':
            obterDetalhesAlerta($usuario_id);
            break;
        
        // ============================================================
        // GERAR ALERTAS (ADMIN ONLY)
        // ============================================================
        case 'gerar_alertas':
            gerarAlertas($usuario_id);
            break;
        
        // ============================================================
        // LIMPAR ALERTAS ANTIGOS (ADMIN ONLY)
        // ============================================================
        case 'limpar_antigos':
            limparAlertosAntigos($usuario_id);
            break;
        
        // ============================================================
        // OBTER CONFIGURAÇÕES (ADMIN ONLY)
        // ============================================================
        case 'obter_config':
            obterConfigAlertas($usuario_id);
            break;
        
        // ============================================================
        // ATUALIZAR CONFIGURAÇÕES (ADMIN ONLY)
        // ============================================================
        case 'atualizar_config':
            atualizarConfigAlertas($usuario_id);
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Ação inválida']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao processar requisição',
        'erro' => $e->getMessage()
    ]);
}

// ============================================================
// FUNÇÕES
// ============================================================

/**
 * Obter alertas não visualizados
 */
function obterAlertas($usuario_id) {
    $alertas = AlertasContasVencidas::obterAlertas($usuario_id);
    $resumo = AlertasContasVencidas::obterResumo($usuario_id);
    
    echo json_encode([
        'sucesso' => true,
        'alertas' => $alertas,
        'resumo' => $resumo,
        'total' => count($alertas)
    ]);
}

/**
 * Marcar alerta como visualizado
 */
function marcarVisualizado($usuario_id) {
    $dados = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($dados['alerta_id'])) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Alerta ID não fornecido']);
        return;
    }
    
    $alerta_id = (int)$dados['alerta_id'];
    $acao = $dados['acao'] ?? 'ver';
    
    // Validar que o alerta pertence ao usuário
    $conn = getConnection();
    $stmt = $conn->prepare("
        SELECT id FROM alertas_contas_vencidas
        WHERE id = ? AND usuario_id = ?
    ");
    $stmt->execute([$alerta_id, $usuario_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado']);
        return;
    }
    
    // Marcar como visualizado
    $resultado = AlertasContasVencidas::marcarVisualizado($alerta_id, $acao);
    
    echo json_encode([
        'sucesso' => $resultado,
        'mensagem' => $resultado ? 'Alerta marcado como visualizado' : 'Erro ao marcar alerta'
    ]);
}

/**
 * Cancelar conta
 */
function cancelarConta($usuario_id) {
    $dados = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($dados['conta_id']) || !isset($dados['alerta_id'])) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos']);
        return;
    }
    
    $conta_id = (int)$dados['conta_id'];
    $alerta_id = (int)$dados['alerta_id'];
    
    try {
        $conn = getConnection();
        
        // Validar que a conta existe
        $stmt = $conn->prepare("SELECT id FROM contas_receber WHERE id = ?");
        $stmt->execute([$conta_id]);
        
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Conta não encontrada']);
            return;
        }
        
        // Cancelar conta
        $stmt = $conn->prepare("
            UPDATE contas_receber
            SET status = 'cancelado', data_atualizacao = NOW()
            WHERE id = ?
        ");
        
        $resultado = $stmt->execute([$conta_id]);
        
        // Marcar alerta como cancelado
        if ($resultado) {
            AlertasContasVencidas::marcarVisualizado($alerta_id, 'cancelar');
            
            LogIntegracao::registrar(
                'alertas_sistema',
                'cancelar_conta',
                'sucesso',
                'Conta cancelada via alerta',
                json_encode(['conta_id' => $conta_id, 'usuario_id' => $usuario_id]),
                null,
                200,
                0
            );
        }
        
        echo json_encode([
            'sucesso' => $resultado,
            'mensagem' => $resultado ? 'Conta cancelada com sucesso' : 'Erro ao cancelar conta'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
    }
}

/**
 * Obter detalhes do alerta
 */
function obterDetalhesAlerta($usuario_id) {
    $alerta_id = $_GET['alerta_id'] ?? 0;
    
    if (!$alerta_id) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Alerta ID não fornecido']);
        return;
    }
    
    $detalhes = AlertasContasVencidas::obterDetalhes($alerta_id);
    
    if (!$detalhes) {
        http_response_code(404);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Alerta não encontrado']);
        return;
    }
    
    // Validar que pertence ao usuário
    if ($detalhes['usuario_id'] != $usuario_id) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado']);
        return;
    }
    
    echo json_encode([
        'sucesso' => true,
        'alerta' => $detalhes
    ]);
}

/**
 * Gerar alertas (ADMIN ONLY)
 */
function gerarAlertas($usuario_id) {
    // Verificar se é admin
    if (!isAdmin($usuario_id)) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado']);
        return;
    }
    
    $resultado = AlertasContasVencidas::gerarAlertas();
    
    echo json_encode([
        'sucesso' => $resultado,
        'mensagem' => $resultado ? 'Alertas gerados com sucesso' : 'Erro ao gerar alertas'
    ]);
}

/**
 * Limpar alertas antigos (ADMIN ONLY)
 */
function limparAlertosAntigos($usuario_id) {
    // Verificar se é admin
    if (!isAdmin($usuario_id)) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado']);
        return;
    }
    
    $dados = json_decode(file_get_contents('php://input'), true);
    $dias = $dados['dias'] ?? 30;
    
    $resultado = AlertasContasVencidas::limparAntigos($dias);
    
    echo json_encode([
        'sucesso' => $resultado,
        'mensagem' => $resultado ? 'Alertas antigos removidos' : 'Erro ao limpar alertas'
    ]);
}

/**
 * Obter configurações (ADMIN ONLY)
 */
function obterConfigAlertas($usuario_id) {
    // Verificar se é admin
    if (!isAdmin($usuario_id)) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado']);
        return;
    }
    
    $conn = getConnection();
    $stmt = $conn->query("SELECT * FROM config_alertas");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $configFormatada = [];
    foreach ($configs as $config) {
        $configFormatada[$config['chave']] = [
            'valor' => $config['valor'],
            'tipo' => $config['tipo'],
            'descricao' => $config['descricao']
        ];
    }
    
    echo json_encode([
        'sucesso' => true,
        'configs' => $configFormatada
    ]);
}

/**
 * Atualizar configurações (ADMIN ONLY)
 */
function atualizarConfigAlertas($usuario_id) {
    // Verificar se é admin
    if (!isAdmin($usuario_id)) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Acesso negado']);
        return;
    }
    
    $dados = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($dados['chave']) || !isset($dados['valor'])) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Dados incompletos']);
        return;
    }
    
    $resultado = AlertasContasVencidas::atualizarConfig($dados['chave'], $dados['valor']);
    
    echo json_encode([
        'sucesso' => $resultado,
        'mensagem' => $resultado ? 'Configuração atualizada' : 'Erro ao atualizar configuração'
    ]);
}

/**
 * Verificar se usuário é admin
 */
function isAdmin($usuario_id) {
    $conn = getConnection();
    $stmt = $conn->prepare("
        SELECT nivel FROM usuarios
        WHERE id = ? AND tipo_usuario IN ('admin', 'usuario')
    ");
    $stmt->execute([$usuario_id]);
    
    return (bool)$stmt->fetch();
}
