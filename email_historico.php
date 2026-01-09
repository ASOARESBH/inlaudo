<?php
require_once 'config.php';

$pageTitle = 'Histórico de E-mails';

// Processar filtros
$filtros = [];
if (!empty($_GET['status'])) $filtros['status'] = sanitize($_GET['status']);
if (!empty($_GET['destinatario'])) $filtros['destinatario'] = sanitize($_GET['destinatario']);
if (!empty($_GET['data_inicio'])) $filtros['data_inicio'] = $_GET['data_inicio'];
if (!empty($_GET['data_fim'])) $filtros['data_fim'] = $_GET['data_fim'];

// Paginação
$limite = 50;
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $limite;

// Buscar histórico
$conn = getConnection();
$sql = "SELECT eh.*, et.nome as template_nome, et.codigo as template_codigo
        FROM email_historico eh
        LEFT JOIN email_templates et ON eh.template_id = et.id
        WHERE 1=1";
$params = [];

if (!empty($filtros['status'])) {
    $sql .= " AND eh.status = ?";
    $params[] = $filtros['status'];
}

if (!empty($filtros['destinatario'])) {
    $sql .= " AND eh.destinatario LIKE ?";
    $params[] = '%' . $filtros['destinatario'] . '%';
}

if (!empty($filtros['data_inicio'])) {
    $sql .= " AND DATE(eh.data_envio) >= ?";
    $params[] = $filtros['data_inicio'];
}

if (!empty($filtros['data_fim'])) {
    $sql .= " AND DATE(eh.data_envio) <= ?";
    $params[] = $filtros['data_fim'];
}

$sql .= " ORDER BY eh.data_envio DESC LIMIT ? OFFSET ?";
$params[] = $limite;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$historico = $stmt->fetchAll();

// Contar total
$sqlCount = str_replace("SELECT eh.*, et.nome as template_nome, et.codigo as template_codigo", "SELECT COUNT(*) as total", $sql);
$sqlCount = preg_replace('/ORDER BY.*/', '', $sqlCount);
$sqlCount = preg_replace('/LIMIT.*/', '', $sqlCount);
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->execute(array_slice($params, 0, -2));
$totalRegistros = $stmtCount->fetch()['total'];
$totalPaginas = ceil($totalRegistros / $limite);

// Estatísticas
$statsData = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) as enviados,
    SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erros,
    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes
    FROM email_historico
    WHERE DATE(data_envio) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch();

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Histórico de E-mails Enviados</h2>
        </div>
        
        <!-- Dashboard de Estatísticas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Total Enviados</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo number_format($statsData['total'], 0, ',', '.'); ?></p>
                <small style="opacity: 0.9;">Últimos 30 dias</small>
            </div>
            
            <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Sucesso</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo number_format($statsData['enviados'], 0, ',', '.'); ?></p>
                <small style="opacity: 0.9;">
                    <?php echo $statsData['total'] > 0 ? number_format(($statsData['enviados'] / $statsData['total']) * 100, 1) : 0; ?>%
                </small>
            </div>
            
            <div style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Erros</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo number_format($statsData['erros'], 0, ',', '.'); ?></p>
                <small style="opacity: 0.9;">
                    <?php echo $statsData['total'] > 0 ? number_format(($statsData['erros'] / $statsData['total']) * 100, 1) : 0; ?>%
                </small>
            </div>
            
            <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Pendentes</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo number_format($statsData['pendentes'], 0, ',', '.'); ?></p>
            </div>
        </div>
        
        <!-- Filtros -->
        <form method="GET" style="background: #f9fafb; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <div class="form-row">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">Todos</option>
                        <option value="enviado" <?php echo ($filtros['status'] ?? '') == 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                        <option value="erro" <?php echo ($filtros['status'] ?? '') == 'erro' ? 'selected' : ''; ?>>Erro</option>
                        <option value="pendente" <?php echo ($filtros['status'] ?? '') == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Destinatário</label>
                    <input type="text" name="destinatario" value="<?php echo $filtros['destinatario'] ?? ''; ?>" placeholder="email@exemplo.com">
                </div>
                
                <div class="form-group">
                    <label>Data Início</label>
                    <input type="date" name="data_inicio" value="<?php echo $filtros['data_inicio'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Data Fim</label>
                    <input type="date" name="data_fim" value="<?php echo $filtros['data_fim'] ?? ''; ?>">
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="email_historico.php" class="btn btn-secondary">Limpar Filtros</a>
            </div>
        </form>
        
        <!-- Tabela de Histórico -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Destinatário</th>
                        <th>Template</th>
                        <th>Assunto</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historico)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem;">
                                Nenhum e-mail encontrado com os filtros selecionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historico as $email): ?>
                            <tr>
                                <td style="white-space: nowrap;">
                                    <?php echo formatDataHora($email['data_envio']); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($email['destinatario']); ?></strong>
                                    <?php if ($email['destinatario_nome']): ?>
                                        <br><small style="color: #6b7280;"><?php echo htmlspecialchars($email['destinatario_nome']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($email['template_nome']): ?>
                                        <?php echo htmlspecialchars($email['template_nome']); ?>
                                        <br><small style="color: #6b7280;"><code><?php echo htmlspecialchars($email['template_codigo']); ?></code></small>
                                    <?php else: ?>
                                        <span style="color: #6b7280;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars($email['assunto']); ?>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = 'cancelado';
                                    if ($email['status'] == 'enviado') $badgeClass = 'pago';
                                    if ($email['status'] == 'erro') $badgeClass = 'vencido';
                                    if ($email['status'] == 'pendente') $badgeClass = 'pendente';
                                    ?>
                                    <span class="badge badge-<?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($email['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button onclick="verDetalhes(<?php echo $email['id']; ?>)" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                        Ver Detalhes
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- Detalhes do e-mail (oculto por padrão) -->
                            <tr id="detalhes_<?php echo $email['id']; ?>" style="display: none;">
                                <td colspan="6" style="background: #f9fafb; padding: 1.5rem;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                        <div>
                                            <h4 style="margin-bottom: 0.5rem; color: #1e40af;">Informações</h4>
                                            <table style="width: 100%; font-size: 0.875rem;">
                                                <tr>
                                                    <td style="padding: 0.25rem 0; font-weight: bold;">Destinatário:</td>
                                                    <td><?php echo htmlspecialchars($email['destinatario']); ?></td>
                                                </tr>
                                                <?php if ($email['destinatario_nome']): ?>
                                                <tr>
                                                    <td style="padding: 0.25rem 0; font-weight: bold;">Nome:</td>
                                                    <td><?php echo htmlspecialchars($email['destinatario_nome']); ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <tr>
                                                    <td style="padding: 0.25rem 0; font-weight: bold;">Status:</td>
                                                    <td><?php echo ucfirst($email['status']); ?></td>
                                                </tr>
                                                <?php if ($email['referencia_tipo']): ?>
                                                <tr>
                                                    <td style="padding: 0.25rem 0; font-weight: bold;">Referência:</td>
                                                    <td><?php echo ucfirst(str_replace('_', ' ', $email['referencia_tipo'])); ?> #<?php echo $email['referencia_id']; ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                <?php if ($email['ip_origem']): ?>
                                                <tr>
                                                    <td style="padding: 0.25rem 0; font-weight: bold;">IP:</td>
                                                    <td><?php echo htmlspecialchars($email['ip_origem']); ?></td>
                                                </tr>
                                                <?php endif; ?>
                                            </table>
                                            
                                            <?php if ($email['mensagem_erro']): ?>
                                                <div style="margin-top: 1rem; padding: 1rem; background: #fef2f2; border-left: 4px solid #ef4444; border-radius: 4px;">
                                                    <strong style="color: #dc2626;">Erro:</strong><br>
                                                    <?php echo htmlspecialchars($email['mensagem_erro']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div>
                                            <h4 style="margin-bottom: 0.5rem; color: #1e40af;">Assunto</h4>
                                            <p style="background: white; padding: 0.75rem; border-radius: 4px; font-size: 0.875rem;">
                                                <?php echo htmlspecialchars($email['assunto']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div style="margin-top: 1.5rem;">
                                        <h4 style="margin-bottom: 0.5rem; color: #1e40af;">Conteúdo do E-mail</h4>
                                        <div style="background: white; padding: 1rem; border-radius: 4px; max-height: 400px; overflow-y: auto; border: 1px solid #e5e7eb;">
                                            <?php echo $email['corpo_html']; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginação -->
        <?php if ($totalPaginas > 1): ?>
            <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1.5rem;">
                <?php if ($pagina > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])); ?>" class="btn btn-secondary">
                        Anterior
                    </a>
                <?php endif; ?>
                
                <span style="padding: 0.75rem 1.5rem; background: #f3f4f6; border-radius: 4px;">
                    Página <?php echo $pagina; ?> de <?php echo $totalPaginas; ?>
                </span>
                
                <?php if ($pagina < $totalPaginas): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])); ?>" class="btn btn-secondary">
                        Próxima
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function verDetalhes(emailId) {
        const detalhes = document.getElementById('detalhes_' + emailId);
        if (detalhes.style.display === 'none') {
            detalhes.style.display = 'table-row';
        } else {
            detalhes.style.display = 'none';
        }
    }
</script>

<?php include 'footer.php'; ?>
