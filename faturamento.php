<?php
require_once 'config.php';

$pageTitle = 'Faturamento - Stripe';

// Processar filtros
$filtroStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$filtroCliente = isset($_GET['cliente']) ? (int)$_GET['cliente'] : 0;

// Buscar faturas
$conn = getConnection();
$sql = "SELECT f.*, c.nome, c.razao_social, c.nome_fantasia, c.tipo_pessoa,
               cr.descricao as conta_descricao
        FROM faturamento f
        INNER JOIN clientes c ON f.cliente_id = c.id
        LEFT JOIN contas_receber cr ON f.conta_receber_id = cr.id
        WHERE 1=1";
$params = [];

if (!empty($filtroStatus)) {
    $sql .= " AND f.status = ?";
    $params[] = $filtroStatus;
}

if ($filtroCliente > 0) {
    $sql .= " AND f.cliente_id = ?";
    $params[] = $filtroCliente;
}

$sql .= " ORDER BY f.data_cadastro DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$faturas = $stmt->fetchAll();

// Buscar clientes para o filtro
$stmtClientes = $conn->query("SELECT id, nome, razao_social, nome_fantasia, tipo_pessoa FROM clientes ORDER BY razao_social, nome");
$clientes = $stmtClientes->fetchAll();

// Calcular totais
$totalEmitido = 0;
$totalPago = 0;
$totalAberto = 0;

foreach ($faturas as $fatura) {
    $totalEmitido += $fatura['valor_total'];
    $totalPago += $fatura['valor_pago'];
    if ($fatura['status'] == 'open') {
        $totalAberto += $fatura['valor_total'];
    }
}

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Faturamento - Gestão de Faturas Stripe</h2>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Total Emitido</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo formatMoeda($totalEmitido); ?></p>
            </div>
            <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Total Pago</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo formatMoeda($totalPago); ?></p>
            </div>
            <div style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Total em Aberto</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo formatMoeda($totalAberto); ?></p>
            </div>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div style="flex: 1; min-width: 250px;">
                <form method="GET" style="display: flex; gap: 0.5rem;">
                    <select name="status" style="width: auto;">
                        <option value="">Todos os Status</option>
                        <option value="draft" <?php echo $filtroStatus == 'draft' ? 'selected' : ''; ?>>Rascunho</option>
                        <option value="open" <?php echo $filtroStatus == 'open' ? 'selected' : ''; ?>>Em Aberto</option>
                        <option value="paid" <?php echo $filtroStatus == 'paid' ? 'selected' : ''; ?>>Pago</option>
                        <option value="void" <?php echo $filtroStatus == 'void' ? 'selected' : ''; ?>>Cancelado</option>
                        <option value="uncollectible" <?php echo $filtroStatus == 'uncollectible' ? 'selected' : ''; ?>>Não Cobrável</option>
                    </select>
                    <select name="cliente" style="width: auto;">
                        <option value="">Todos os Clientes</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $filtroCliente == $c['id'] ? 'selected' : ''; ?>>
                                <?php 
                                echo $c['tipo_pessoa'] == 'CNPJ' 
                                    ? ($c['razao_social'] ?: $c['nome_fantasia']) 
                                    : $c['nome']; 
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </form>
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nº Fatura</th>
                        <th>Cliente</th>
                        <th>Descrição</th>
                        <th>Valor Total</th>
                        <th>Valor Pago</th>
                        <th>Status</th>
                        <th>Vencimento</th>
                        <th>Emissão</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($faturas)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem;">
                                Nenhuma fatura encontrada.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($faturas as $fatura): ?>
                            <tr>
                                <td style="font-weight: 600;">
                                    <?php echo htmlspecialchars($fatura['numero_fatura'] ?: '-'); ?>
                                </td>
                                <td>
                                    <?php 
                                    echo $fatura['tipo_pessoa'] == 'CNPJ' 
                                        ? ($fatura['razao_social'] ?: $fatura['nome_fantasia']) 
                                        : $fatura['nome']; 
                                    ?>
                                </td>
                                <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars($fatura['descricao'] ?: $fatura['conta_descricao']); ?>
                                </td>
                                <td style="font-weight: 600;"><?php echo formatMoeda($fatura['valor_total']); ?></td>
                                <td style="color: #10b981; font-weight: 600;"><?php echo formatMoeda($fatura['valor_pago']); ?></td>
                                <td>
                                    <?php
                                    $statusMap = [
                                        'draft' => ['Rascunho', 'cancelado'],
                                        'open' => ['Em Aberto', 'pendente'],
                                        'paid' => ['Pago', 'pago'],
                                        'void' => ['Cancelado', 'cancelado'],
                                        'uncollectible' => ['Não Cobrável', 'vencido']
                                    ];
                                    $statusInfo = $statusMap[$fatura['status']] ?? ['Desconhecido', 'cancelado'];
                                    ?>
                                    <span class="badge badge-<?php echo $statusInfo[1]; ?>">
                                        <?php echo $statusInfo[0]; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $fatura['data_vencimento'] ? formatData($fatura['data_vencimento']) : '-'; ?>
                                </td>
                                <td>
                                    <?php echo $fatura['data_emissao'] ? formatData($fatura['data_emissao']) : formatData($fatura['data_cadastro']); ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <?php if ($fatura['hosted_invoice_url']): ?>
                                            <a href="<?php echo htmlspecialchars($fatura['hosted_invoice_url']); ?>" 
                                               target="_blank" class="btn btn-primary">
                                                Ver Fatura
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($fatura['url_pdf']): ?>
                                            <a href="<?php echo htmlspecialchars($fatura['url_pdf']); ?>" 
                                               target="_blank" class="btn btn-primary">
                                                PDF
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($fatura['boleto_url']): ?>
                                            <a href="<?php echo htmlspecialchars($fatura['boleto_url']); ?>" 
                                               target="_blank" class="btn btn-success">
                                                Boleto
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button onclick="verDetalhes(<?php echo $fatura['id']; ?>)" 
                                                class="btn btn-secondary">
                                            Detalhes
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Detalhes da fatura (oculto por padrão) -->
                            <tr id="detalhes_<?php echo $fatura['id']; ?>" style="display: none;">
                                <td colspan="9" style="background: #f9fafb; padding: 1.5rem;">
                                    <h4 style="margin-bottom: 1rem;">Detalhes da Fatura</h4>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                        <?php if ($fatura['stripe_invoice_id']): ?>
                                        <div>
                                            <strong>ID Stripe:</strong><br>
                                            <code><?php echo htmlspecialchars($fatura['stripe_invoice_id']); ?></code>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($fatura['stripe_customer_id']): ?>
                                        <div>
                                            <strong>Customer ID:</strong><br>
                                            <code><?php echo htmlspecialchars($fatura['stripe_customer_id']); ?></code>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($fatura['payment_intent_id']): ?>
                                        <div>
                                            <strong>Payment Intent:</strong><br>
                                            <code><?php echo htmlspecialchars($fatura['payment_intent_id']); ?></code>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($fatura['forma_pagamento']): ?>
                                        <div>
                                            <strong>Forma de Pagamento:</strong><br>
                                            <?php echo ucfirst(str_replace('_', ' ', $fatura['forma_pagamento'])); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($fatura['data_pagamento']): ?>
                                        <div>
                                            <strong>Data de Pagamento:</strong><br>
                                            <?php echo formatData($fatura['data_pagamento']); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($fatura['observacoes']): ?>
                                        <div style="grid-column: 1 / -1;">
                                            <strong>Observações:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($fatura['observacoes'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function verDetalhes(faturaId) {
        const detalhes = document.getElementById('detalhes_' + faturaId);
        if (detalhes.style.display === 'none') {
            detalhes.style.display = 'table-row';
        } else {
            detalhes.style.display = 'none';
        }
    }
</script>

<?php include 'footer.php'; ?>
