<?php
/**
 * Faturamento Completo - Geração e Gestão de Faturas
 * ERP INLAUDO - Versão 7.1
 */

$pageTitle = 'Faturamento';
require_once 'header.php';
require_once 'config.php';

$conn = getConnection();

// Filtros
$filtroGateway = isset($_GET['gateway']) ? $_GET['gateway'] : '';
$filtroStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filtroBusca = isset($_GET['busca']) ? $_GET['busca'] : '';

// Buscar contas a receber pendentes e contratos com gateway configurado
$sql = "
    SELECT 
        cr.id,
        cr.cliente_id,
        cr.descricao,
        cr.valor,
        cr.data_vencimento,
        cr.status,
        cr.contrato_id,
        c.nome as cliente_nome,
        c.razao_social as cliente_razao,
        c.email as cliente_email,
        c.cnpj_cpf,
        cont.gateway_pagamento,
        cont.link_pagamento,
        cont.payment_id,
        cont.status_pagamento,
        tp.payment_url,
        tp.boleto_url,
        tp.status as transacao_status,
        tp.data_criacao as transacao_data
    FROM contas_receber cr
    INNER JOIN clientes c ON cr.cliente_id = c.id
    LEFT JOIN contratos cont ON cr.contrato_id = cont.id
    LEFT JOIN transacoes_pagamento tp ON (tp.conta_receber_id = cr.id OR tp.contrato_id = cont.id) 
        AND tp.id = (
            SELECT MAX(id) FROM transacoes_pagamento 
            WHERE conta_receber_id = cr.id OR contrato_id = cont.id
        )
    WHERE cr.status IN ('pendente', 'vencida')
";

// Aplicar filtros
if ($filtroGateway) {
    $sql .= " AND cont.gateway_pagamento = " . $conn->quote($filtroGateway);
}

if ($filtroStatus) {
    $sql .= " AND cr.status = " . $conn->quote($filtroStatus);
}

if ($filtroBusca) {
    $sql .= " AND (c.nome LIKE " . $conn->quote("%$filtroBusca%") . 
            " OR c.razao_social LIKE " . $conn->quote("%$filtroBusca%") .
            " OR cr.descricao LIKE " . $conn->quote("%$filtroBusca%") . ")";
}

$sql .= " ORDER BY cr.data_vencimento ASC, cr.id DESC";

$stmt = $conn->query($sql);
$faturas = $stmt->fetchAll();

// Estatísticas
$stmtStats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
        SUM(CASE WHEN status = 'vencida' THEN 1 ELSE 0 END) as vencidas,
        SUM(valor) as valor_total
    FROM contas_receber 
    WHERE status IN ('pendente', 'vencida')
");
$stats = $stmtStats->fetch();
?>

<style>
.faturamento-container {
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid;
}

.stat-card.total { border-left-color: #3b82f6; }
.stat-card.pendente { border-left-color: #f59e0b; }
.stat-card.vencida { border-left-color: #ef4444; }
.stat-card.valor { border-left-color: #10b981; }

.stat-card h3 {
    font-size: 0.9rem;
    color: #64748b;
    margin: 0 0 10px 0;
    text-transform: uppercase;
    font-weight: 600;
}

.stat-card .value {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
}

.filters {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: end;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    display: block;
    font-weight: 600;
    color: #475569;
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.filter-group select,
.filter-group input {
    width: 100%;
    padding: 10px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
}

.btn-filter {
    padding: 10px 20px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.btn-clear {
    padding: 10px 20px;
    background: #64748b;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.faturas-table {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.table-header {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h2 {
    margin: 0;
    font-size: 1.3rem;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: #f8fafc;
}

th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
}

td {
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
    color: #1e293b;
}

tr:hover {
    background: #f8fafc;
}

.badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.badge.pendente { background: #fef3c7; color: #92400e; }
.badge.vencida { background: #fee2e2; color: #991b1b; }
.badge.pago { background: #d1fae5; color: #065f46; }
.badge.mercadopago { background: #e0f2fe; color: #075985; }
.badge.cora { background: #ddd6fe; color: #5b21b6; }
.badge.stripe { background: #fce7f3; color: #9f1239; }

.actions {
    display: flex;
    gap: 8px;
}

.btn-action {
    padding: 8px 15px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
}

.btn-gerar {
    background: #10b981;
    color: white;
}

.btn-enviar {
    background: #3b82f6;
    color: white;
}

.btn-ver {
    background: #64748b;
    color: white;
}

.btn-action:hover {
    opacity: 0.8;
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
    color: #64748b;
}

.empty-state svg {
    width: 80px;
    height: 80px;
    margin-bottom: 20px;
    opacity: 0.3;
}
</style>

<div class="faturamento-container">
    <h1 style="color: #1e293b; margin: 0 0 25px 0;">💳 Faturamento</h1>

    <!-- Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card total">
            <h3>Total de Faturas</h3>
            <div class="value"><?php echo number_format($stats['total'], 0, ',', '.'); ?></div>
        </div>
        <div class="stat-card pendente">
            <h3>Pendentes</h3>
            <div class="value"><?php echo number_format($stats['pendentes'], 0, ',', '.'); ?></div>
        </div>
        <div class="stat-card vencida">
            <h3>Vencidas</h3>
            <div class="value"><?php echo number_format($stats['vencidas'], 0, ',', '.'); ?></div>
        </div>
        <div class="stat-card valor">
            <h3>Valor Total</h3>
            <div class="value">R$ <?php echo number_format($stats['valor_total'], 2, ',', '.'); ?></div>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" class="filters">
        <div class="filter-group">
            <label>Gateway de Pagamento</label>
            <select name="gateway">
                <option value="">Todos</option>
                <option value="mercadopago" <?php echo $filtroGateway == 'mercadopago' ? 'selected' : ''; ?>>Mercado Pago</option>
                <option value="cora" <?php echo $filtroGateway == 'cora' ? 'selected' : ''; ?>>CORA</option>
                <option value="stripe" <?php echo $filtroGateway == 'stripe' ? 'selected' : ''; ?>>Stripe</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label>Status</label>
            <select name="status">
                <option value="">Todos</option>
                <option value="pendente" <?php echo $filtroStatus == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                <option value="vencida" <?php echo $filtroStatus == 'vencida' ? 'selected' : ''; ?>>Vencida</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label>Buscar Cliente/Descrição</label>
            <input type="text" name="busca" value="<?php echo htmlspecialchars($filtroBusca); ?>" placeholder="Digite para buscar...">
        </div>
        
        <button type="submit" class="btn-filter">🔍 Filtrar</button>
        <a href="faturamento_completo.php" class="btn-clear">🔄 Limpar</a>
    </form>

    <!-- Tabela de Faturas -->
    <div class="faturas-table">
        <div class="table-header">
            <h2>📋 Faturas a Faturar</h2>
            <span><?php echo count($faturas); ?> faturas encontradas</span>
        </div>

        <?php if (empty($faturas)): ?>
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="width" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3>Nenhuma fatura encontrada</h3>
                <p>Não há faturas pendentes com os filtros selecionados.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Gateway</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($faturas as $fatura): ?>
                        <tr>
                            <td>#<?php echo $fatura['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($fatura['cliente_razao'] ?: $fatura['cliente_nome']); ?></strong><br>
                                <small style="color: #64748b;"><?php echo htmlspecialchars($fatura['cliente_email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($fatura['descricao']); ?></td>
                            <td><strong>R$ <?php echo number_format($fatura['valor'], 2, ',', '.'); ?></strong></td>
                            <td><?php echo date('d/m/Y', strtotime($fatura['data_vencimento'])); ?></td>
                            <td>
                                <?php if ($fatura['gateway_pagamento']): ?>
                                    <span class="badge <?php echo $fatura['gateway_pagamento']; ?>">
                                        <?php echo strtoupper($fatura['gateway_pagamento']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: #f1f5f9; color: #64748b;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $fatura['status']; ?>">
                                    <?php echo ucfirst($fatura['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <?php if ($fatura['link_pagamento'] || $fatura['payment_url'] || $fatura['boleto_url']): ?>
                                        <a href="<?php echo $fatura['link_pagamento'] ?: $fatura['payment_url'] ?: $fatura['boleto_url']; ?>" 
                                           target="_blank" 
                                           class="btn-action btn-ver"
                                           title="Ver Link">
                                            👁️ Ver
                                        </a>
                                        <a href="enviar_link_pagamento.php?id=<?php echo $fatura['id']; ?>" 
                                           class="btn-action btn-enviar"
                                           title="Enviar por E-mail">
                                            📧 Enviar
                                        </a>
                                    <?php else: ?>
                                        <a href="link_pagamento/gerar_link_pagamento.php?id=<?php echo $fatura['id']; ?>" 
                                           class="btn-action btn-gerar"
                                           title="Gerar Link de Pagamento">
                                            🔗 Gerar Link
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>
