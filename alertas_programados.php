<?php
require_once 'config.php';

$pageTitle = 'Alertas Programados';

$mensagem = '';
$erro = '';

// Processar cancelamento de alerta
if (isset($_GET['cancelar'])) {
    $alertaId = (int)$_GET['cancelar'];
    try {
        $conn = getConnection();
        $conn->prepare("UPDATE alertas_programados SET status = 'cancelado' WHERE id = ?")->execute([$alertaId]);
        $mensagem = "Alerta cancelado com sucesso!";
    } catch (PDOException $e) {
        $erro = "Erro ao cancelar alerta: " . $e->getMessage();
    }
}

// Processar reenvio de alerta
if (isset($_GET['reenviar'])) {
    $alertaId = (int)$_GET['reenviar'];
    try {
        $conn = getConnection();
        $conn->prepare("UPDATE alertas_programados SET status = 'pendente', tentativas = 0, mensagem_erro = NULL WHERE id = ?")->execute([$alertaId]);
        $mensagem = "Alerta marcado para reenvio!";
    } catch (PDOException $e) {
        $erro = "Erro ao marcar alerta para reenvio: " . $e->getMessage();
    }
}

// Processar filtros
$filtros = [];
if (!empty($_GET['status'])) $filtros['status'] = sanitize($_GET['status']);
if (!empty($_GET['tipo_alerta'])) $filtros['tipo_alerta'] = sanitize($_GET['tipo_alerta']);

// Buscar alertas
$conn = getConnection();
$sql = "SELECT ap.*, et.nome as template_nome, et.codigo as template_codigo
        FROM alertas_programados ap
        LEFT JOIN email_templates et ON ap.template_id = et.id
        WHERE 1=1";
$params = [];

if (!empty($filtros['status'])) {
    $sql .= " AND ap.status = ?";
    $params[] = $filtros['status'];
}

if (!empty($filtros['tipo_alerta'])) {
    $sql .= " AND ap.tipo_alerta = ?";
    $params[] = $filtros['tipo_alerta'];
}

$sql .= " ORDER BY ap.data_programada DESC, ap.hora_programada DESC LIMIT 100";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$alertas = $stmt->fetchAll();

// Estatísticas
$stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
    SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) as enviados,
    SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erros,
    SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as cancelados
    FROM alertas_programados
    WHERE data_programada >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch();

include 'header.php';
?>

<div class="container">
    <?php if ($mensagem): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>
    
    <?php if ($erro): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2>Alertas Programados</h2>
        </div>
        
        <!-- Dashboard de Estatísticas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Total</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo number_format($stats['total'], 0, ',', '.'); ?></p>
            </div>
            
            <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Pendentes</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo number_format($stats['pendentes'], 0, ',', '.'); ?></p>
            </div>
            
            <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Enviados</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo number_format($stats['enviados'], 0, ',', '.'); ?></p>
            </div>
            
            <div style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Erros</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo number_format($stats['erros'], 0, ',', '.'); ?></p>
            </div>
            
            <div style="background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Cancelados</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo number_format($stats['cancelados'], 0, ',', '.'); ?></p>
            </div>
        </div>
        
        <!-- Filtros -->
        <form method="GET" style="background: #f9fafb; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <div class="form-row">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">Todos</option>
                        <option value="pendente" <?php echo ($filtros['status'] ?? '') == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="enviado" <?php echo ($filtros['status'] ?? '') == 'enviado' ? 'selected' : ''; ?>>Enviado</option>
                        <option value="erro" <?php echo ($filtros['status'] ?? '') == 'erro' ? 'selected' : ''; ?>>Erro</option>
                        <option value="cancelado" <?php echo ($filtros['status'] ?? '') == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Tipo de Alerta</label>
                    <select name="tipo_alerta">
                        <option value="">Todos</option>
                        <option value="conta_pagar_vencendo" <?php echo ($filtros['tipo_alerta'] ?? '') == 'conta_pagar_vencendo' ? 'selected' : ''; ?>>Conta a Pagar</option>
                        <option value="conta_receber_vencida" <?php echo ($filtros['tipo_alerta'] ?? '') == 'conta_receber_vencida' ? 'selected' : ''; ?>>Conta a Receber</option>
                        <option value="interacao_proxima" <?php echo ($filtros['tipo_alerta'] ?? '') == 'interacao_proxima' ? 'selected' : ''; ?>>Interação</option>
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="alertas_programados.php" class="btn btn-secondary">Limpar Filtros</a>
            </div>
        </form>
        
        <!-- Tabela de Alertas -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Data/Hora Programada</th>
                        <th>Tipo de Alerta</th>
                        <th>Template</th>
                        <th>Destinatário</th>
                        <th>Status</th>
                        <th>Tentativas</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($alertas)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem;">
                                Nenhum alerta programado encontrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($alertas as $alerta): ?>
                            <tr>
                                <td style="white-space: nowrap;">
                                    <?php echo formatData($alerta['data_programada']); ?>
                                    <?php if ($alerta['hora_programada']): ?>
                                        <br><small style="color: #6b7280;"><?php echo date('H:i', strtotime($alerta['hora_programada'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-cliente">
                                        <?php echo ucfirst(str_replace('_', ' ', $alerta['tipo_alerta'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($alerta['template_nome']): ?>
                                        <?php echo htmlspecialchars($alerta['template_nome']); ?>
                                        <br><small style="color: #6b7280;"><code><?php echo htmlspecialchars($alerta['template_codigo']); ?></code></small>
                                    <?php else: ?>
                                        <span style="color: #6b7280;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($alerta['destinatario_email']); ?></td>
                                <td>
                                    <?php
                                    $badgeClass = 'cancelado';
                                    if ($alerta['status'] == 'enviado') $badgeClass = 'pago';
                                    if ($alerta['status'] == 'erro') $badgeClass = 'vencido';
                                    if ($alerta['status'] == 'pendente') $badgeClass = 'pendente';
                                    ?>
                                    <span class="badge badge-<?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($alerta['status']); ?>
                                    </span>
                                    <?php if ($alerta['data_envio']): ?>
                                        <br><small style="color: #6b7280;"><?php echo formatDataHora($alerta['data_envio']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $alerta['tentativas']; ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($alerta['status'] == 'pendente'): ?>
                                            <a href="alertas_programados.php?cancelar=<?php echo $alerta['id']; ?>" 
                                               class="btn btn-danger"
                                               onclick="return confirm('Tem certeza que deseja cancelar este alerta?')">
                                                Cancelar
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($alerta['status'] == 'erro'): ?>
                                            <a href="alertas_programados.php?reenviar=<?php echo $alerta['id']; ?>" 
                                               class="btn btn-success">
                                                Reenviar
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($alerta['mensagem_erro']): ?>
                                            <button onclick="verErro(<?php echo $alerta['id']; ?>)" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                                Ver Erro
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            
                            <?php if ($alerta['mensagem_erro']): ?>
                                <tr id="erro_<?php echo $alerta['id']; ?>" style="display: none;">
                                    <td colspan="7" style="background: #fef2f2; padding: 1.5rem;">
                                        <strong style="color: #dc2626;">Mensagem de Erro:</strong><br>
                                        <code style="display: block; margin-top: 0.5rem; padding: 1rem; background: white; border-radius: 4px;">
                                            <?php echo htmlspecialchars($alerta['mensagem_erro']); ?>
                                        </code>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <h3>⚙️ Configuração de Alertas Automáticos</h3>
        </div>
        
        <div style="padding: 1.5rem;">
            <h4 style="color: #2563eb; margin-bottom: 0.5rem;">Como Funciona</h4>
            <p>Os alertas automáticos são processados diariamente pelo script <code>processar_alertas.php</code>. Este script verifica:</p>
            <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                <li>Contas a pagar próximas do vencimento</li>
                <li>Contas a receber vencidas</li>
                <li>Próximas interações agendadas com clientes</li>
                <li>Alertas programados pendentes</li>
            </ul>
            
            <h4 style="color: #2563eb; margin-bottom: 0.5rem; margin-top: 1rem;">Configurar CRON (Execução Automática)</h4>
            <p>Para que os alertas sejam enviados automaticamente, adicione a seguinte linha ao crontab do servidor:</p>
            <pre style="background: #f3f4f6; padding: 1rem; border-radius: 4px; overflow-x: auto; margin-top: 0.5rem;">0 9 * * * /usr/bin/php /caminho/completo/para/erp-inlaudo/processar_alertas.php</pre>
            <p style="margin-top: 0.5rem;"><small style="color: #6b7280;">Esta configuração executará o script todos os dias às 9h da manhã.</small></p>
            
            <h4 style="color: #2563eb; margin-bottom: 0.5rem; margin-top: 1rem;">Executar Manualmente</h4>
            <p>Você também pode executar o script manualmente via terminal:</p>
            <pre style="background: #f3f4f6; padding: 1rem; border-radius: 4px; overflow-x: auto; margin-top: 0.5rem;">php /caminho/completo/para/erp-inlaudo/processar_alertas.php</pre>
            
            <h4 style="color: #2563eb; margin-bottom: 0.5rem; margin-top: 1rem;">Configurar Templates</h4>
            <p>Para ativar o envio automático de um template:</p>
            <ol style="margin-left: 1.5rem; margin-top: 0.5rem;">
                <li>Acesse <a href="email_templates.php">Templates de E-mail</a></li>
                <li>Edite o template desejado</li>
                <li>Marque a opção "Enviar Automaticamente"</li>
                <li>Configure "Dias de Antecedência" (para alertas preventivos)</li>
                <li>Defina "Destinatários Padrão" (e-mails que sempre receberão o alerta)</li>
                <li>Salve as alterações</li>
            </ol>
        </div>
    </div>
</div>

<script>
    function verErro(alertaId) {
        const erro = document.getElementById('erro_' + alertaId);
        if (erro.style.display === 'none') {
            erro.style.display = 'table-row';
        } else {
            erro.style.display = 'none';
        }
    }
</script>

<?php include 'footer.php'; ?>
