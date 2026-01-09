<?php
/**
 * Visualizador de Logs de Integração
 * ERP INLAUDO - Sistema de Debug e Diagnóstico
 * 
 * Interface para visualizar e filtrar logs de integrações
 */

$pageTitle = 'Logs de Integração';
require_once 'header.php';
require_once 'config.php';

// Parâmetros de filtro
$gateway = $_GET['gateway'] ?? '';
$nivel = $_GET['nivel'] ?? '';
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');
$limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 100;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

$offset = ($pagina - 1) * $limite;

try {
    $conn = getConnection();
    
    // Construir query com filtros
    $where = ["DATE(data_criacao) BETWEEN ? AND ?"];
    $params = [$dataInicio, $dataFim];
    
    if ($gateway) {
        $where[] = "gateway = ?";
        $params[] = $gateway;
    }
    
    if ($nivel) {
        $where[] = "nivel = ?";
        $params[] = $nivel;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total de registros
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM logs_integracao WHERE $whereClause");
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    $totalPaginas = ceil($totalRegistros / $limite);
    
    // Buscar logs
    $stmt = $conn->prepare("
        SELECT * 
        FROM logs_integracao 
        WHERE $whereClause
        ORDER BY data_criacao DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $limite;
    $params[] = $offset;
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Estatísticas
    $stmtStats = $conn->prepare("
        SELECT 
            gateway,
            nivel,
            COUNT(*) as total
        FROM logs_integracao
        WHERE DATE(data_criacao) BETWEEN ? AND ?
        GROUP BY gateway, nivel
        ORDER BY gateway, nivel
    ");
    $stmtStats->execute([$dataInicio, $dataFim]);
    $estatisticas = $stmtStats->fetchAll();
    
} catch (Exception $e) {
    $erro = $e->getMessage();
}
?>

<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <!-- Cabeçalho -->
            <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
                <h2><i class="fas fa-list-alt"></i> Logs de Integração</h2>
                <div>
                    <button class="btn btn-danger" onclick="if(confirm('Limpar logs antigos (mais de 30 dias)?')) window.location.href='?limpar=1'">
                        <i class="fas fa-trash"></i> Limpar Logs Antigos
                    </button>
                </div>
            </div>

            <?php if (isset($erro)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Erro: <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>

            <!-- Estatísticas -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Estatísticas do Período</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php
                                $stats = [];
                                foreach ($estatisticas as $stat) {
                                    $stats[$stat['gateway']][$stat['nivel']] = $stat['total'];
                                }
                                
                                $cores = [
                                    'DEBUG' => 'secondary',
                                    'INFO' => 'primary',
                                    'WARNING' => 'warning',
                                    'ERROR' => 'danger',
                                    'CRITICAL' => 'dark'
                                ];
                                
                                foreach ($stats as $gw => $niveis):
                                    $total = array_sum($niveis);
                                ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="text-uppercase text-muted mb-2"><?php echo strtoupper($gw); ?></h6>
                                            <h3 class="mb-3"><?php echo number_format($total); ?> logs</h3>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($niveis as $nv => $qt): ?>
                                                    <span class="badge bg-<?php echo $cores[$nv]; ?>">
                                                        <?php echo $nv; ?>: <?php echo $qt; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Gateway</label>
                            <select name="gateway" class="form-select">
                                <option value="">Todos</option>
                                <option value="mercadopago" <?php echo $gateway == 'mercadopago' ? 'selected' : ''; ?>>Mercado Pago</option>
                                <option value="cora" <?php echo $gateway == 'cora' ? 'selected' : ''; ?>>CORA</option>
                                <option value="stripe" <?php echo $gateway == 'stripe' ? 'selected' : ''; ?>>Stripe</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Nível</label>
                            <select name="nivel" class="form-select">
                                <option value="">Todos</option>
                                <option value="DEBUG" <?php echo $nivel == 'DEBUG' ? 'selected' : ''; ?>>DEBUG</option>
                                <option value="INFO" <?php echo $nivel == 'INFO' ? 'selected' : ''; ?>>INFO</option>
                                <option value="WARNING" <?php echo $nivel == 'WARNING' ? 'selected' : ''; ?>>WARNING</option>
                                <option value="ERROR" <?php echo $nivel == 'ERROR' ? 'selected' : ''; ?>>ERROR</option>
                                <option value="CRITICAL" <?php echo $nivel == 'CRITICAL' ? 'selected' : ''; ?>>CRITICAL</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" value="<?php echo $dataInicio; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control" value="<?php echo $dataFim; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Registros</label>
                            <select name="limite" class="form-select">
                                <option value="50" <?php echo $limite == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $limite == 100 ? 'selected' : ''; ?>>100</option>
                                <option value="200" <?php echo $limite == 200 ? 'selected' : ''; ?>>200</option>
                                <option value="500" <?php echo $limite == 500 ? 'selected' : ''; ?>>500</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Logs -->
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Logs (<?php echo number_format($totalRegistros); ?> registros)</h5>
                    <span class="badge bg-light text-dark">Página <?php echo $pagina; ?> de <?php echo $totalPaginas; ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="50">ID</th>
                                    <th width="120">Data/Hora</th>
                                    <th width="100">Gateway</th>
                                    <th width="150">Operação</th>
                                    <th width="80">Nível</th>
                                    <th>Mensagem</th>
                                    <th width="80">Tempo</th>
                                    <th width="80">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                            Nenhum log encontrado para os filtros selecionados
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo $log['id']; ?></td>
                                            <td>
                                                <small><?php echo date('d/m/Y H:i:s', strtotime($log['data_criacao'])); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo strtoupper($log['gateway']); ?></span>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($log['tipo_operacao']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $cores[$log['nivel']] ?? 'secondary'; ?>">
                                                    <?php echo $log['nivel']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars(substr($log['mensagem'], 0, 100)); ?><?php echo strlen($log['mensagem']) > 100 ? '...' : ''; ?></small>
                                            </td>
                                            <td>
                                                <?php if ($log['tempo_execucao']): ?>
                                                    <small><?php echo number_format($log['tempo_execucao'], 3); ?>s</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="verDetalhes(<?php echo $log['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Paginação -->
                <?php if ($totalPaginas > 1): ?>
                <div class="card-footer">
                    <nav>
                        <ul class="pagination pagination-sm mb-0 justify-content-center">
                            <?php if ($pagina > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>">Anterior</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $pagina - 2); $i <= min($totalPaginas, $pagina + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $pagina ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($pagina < $totalPaginas): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>">Próximo</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Detalhes -->
<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalDetalhesBody">
                <div class="text-center py-5">
                    <div class="spinner-border" role="status"></div>
                    <p class="mt-2">Carregando...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function verDetalhes(id) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetalhes'));
    modal.show();
    
    fetch('logs_integracao_detalhes.php?id=' + id)
        .then(response => response.text())
        .then(html => {
            document.getElementById('modalDetalhesBody').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('modalDetalhesBody').innerHTML = 
                '<div class="alert alert-danger">Erro ao carregar detalhes: ' + error + '</div>';
        });
}
</script>

<?php require_once 'footer.php'; ?>
