<?php
/**
 * Detalhes de Log de Integração
 * ERP INLAUDO - Sistema de Debug
 */

require_once 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    echo '<div class="alert alert-danger">ID do log não informado</div>';
    exit;
}

try {
    $conn = getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM logs_integracao WHERE id = ?");
    $stmt->execute([$id]);
    $log = $stmt->fetch();
    
    if (!$log) {
        echo '<div class="alert alert-danger">Log não encontrado</div>';
        exit;
    }
    
    $cores = [
        'DEBUG' => 'secondary',
        'INFO' => 'primary',
        'WARNING' => 'warning',
        'ERROR' => 'danger',
        'CRITICAL' => 'dark'
    ];
    
    ?>
    <div class="row">
        <div class="col-md-6 mb-3">
            <strong>ID:</strong> <?php echo $log['id']; ?>
        </div>
        <div class="col-md-6 mb-3">
            <strong>Data/Hora:</strong> <?php echo date('d/m/Y H:i:s', strtotime($log['data_criacao'])); ?>
        </div>
        <div class="col-md-6 mb-3">
            <strong>Gateway:</strong> 
            <span class="badge bg-secondary"><?php echo strtoupper($log['gateway']); ?></span>
        </div>
        <div class="col-md-6 mb-3">
            <strong>Operação:</strong> <?php echo htmlspecialchars($log['tipo_operacao']); ?>
        </div>
        <div class="col-md-6 mb-3">
            <strong>Nível:</strong> 
            <span class="badge bg-<?php echo $cores[$log['nivel']]; ?>"><?php echo $log['nivel']; ?></span>
        </div>
        <div class="col-md-6 mb-3">
            <strong>Tempo de Execução:</strong> 
            <?php echo $log['tempo_execucao'] ? number_format($log['tempo_execucao'], 3) . 's' : 'N/A'; ?>
        </div>
        <?php if ($log['conta_receber_id']): ?>
        <div class="col-md-6 mb-3">
            <strong>Conta a Receber:</strong> 
            <a href="conta_receber_detalhes.php?id=<?php echo $log['conta_receber_id']; ?>" target="_blank">
                #<?php echo $log['conta_receber_id']; ?>
            </a>
        </div>
        <?php endif; ?>
        <?php if ($log['cliente_id']): ?>
        <div class="col-md-6 mb-3">
            <strong>Cliente:</strong> 
            <a href="cliente_detalhes.php?id=<?php echo $log['cliente_id']; ?>" target="_blank">
                #<?php echo $log['cliente_id']; ?>
            </a>
        </div>
        <?php endif; ?>
        <?php if ($log['codigo_http']): ?>
        <div class="col-md-6 mb-3">
            <strong>Código HTTP:</strong> 
            <span class="badge bg-<?php echo $log['codigo_http'] >= 400 ? 'danger' : 'success'; ?>">
                <?php echo $log['codigo_http']; ?>
            </span>
        </div>
        <?php endif; ?>
        <?php if ($log['ip_origem']): ?>
        <div class="col-md-6 mb-3">
            <strong>IP Origem:</strong> <?php echo htmlspecialchars($log['ip_origem']); ?>
        </div>
        <?php endif; ?>
    </div>
    
    <hr>
    
    <div class="mb-3">
        <strong>Mensagem:</strong>
        <div class="alert alert-<?php echo $cores[$log['nivel']]; ?> mt-2">
            <?php echo nl2br(htmlspecialchars($log['mensagem'])); ?>
        </div>
    </div>
    
    <?php if ($log['dados_request']): ?>
    <div class="mb-3">
        <strong>Dados da Requisição:</strong>
        <pre class="bg-light p-3 rounded mt-2" style="max-height: 300px; overflow-y: auto;"><code><?php echo htmlspecialchars($log['dados_request']); ?></code></pre>
    </div>
    <?php endif; ?>
    
    <?php if ($log['dados_response']): ?>
    <div class="mb-3">
        <strong>Dados da Resposta:</strong>
        <pre class="bg-light p-3 rounded mt-2" style="max-height: 300px; overflow-y: auto;"><code><?php echo htmlspecialchars($log['dados_response']); ?></code></pre>
    </div>
    <?php endif; ?>
    
    <?php if ($log['stack_trace']): ?>
    <div class="mb-3">
        <strong>Stack Trace:</strong>
        <pre class="bg-danger text-white p-3 rounded mt-2" style="max-height: 300px; overflow-y: auto;"><code><?php echo htmlspecialchars($log['stack_trace']); ?></code></pre>
    </div>
    <?php endif; ?>
    
    <?php if ($log['user_agent']): ?>
    <div class="mb-3">
        <strong>User Agent:</strong>
        <small class="text-muted d-block mt-1"><?php echo htmlspecialchars($log['user_agent']); ?></small>
    </div>
    <?php endif; ?>
    
    <?php
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro ao carregar detalhes: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
