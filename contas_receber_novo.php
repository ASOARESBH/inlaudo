<?php
require_once 'config.php';

$pageTitle = 'Contas a Receber';

// Processar filtros
$filtroStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$filtroCliente = isset($_GET['cliente']) ? (int)$_GET['cliente'] : 0;

// Buscar contas a receber
$conn = getConnection();
$sql = "SELECT cr.*, c.nome, c.razao_social, c.nome_fantasia, c.tipo_pessoa, pc.nome as plano_conta_nome
        FROM contas_receber cr
        INNER JOIN clientes c ON cr.cliente_id = c.id
        INNER JOIN plano_contas pc ON cr.plano_contas_id = pc.id
        WHERE 1=1";
$params = [];

if (!empty($filtroStatus)) {
    $sql .= " AND cr.status = ?";
    $params[] = $filtroStatus;
}

if ($filtroCliente > 0) {
    $sql .= " AND cr.cliente_id = ?";
    $params[] = $filtroCliente;
}

$sql .= " ORDER BY cr.data_vencimento ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$contas = $stmt->fetchAll();

// Atualizar status de contas vencidas
$conn->exec("UPDATE contas_receber SET status = 'vencido' WHERE status = 'pendente' AND data_vencimento < CURDATE()");

// Buscar clientes para o filtro
$stmtClientes = $conn->query("SELECT id, nome, razao_social, nome_fantasia, tipo_pessoa FROM clientes ORDER BY razao_social, nome");
$clientes = $stmtClientes->fetchAll();

// Calcular totais
$totalPendente = 0;
$totalPago = 0;
$totalVencido = 0;

foreach ($contas as $conta) {
    if ($conta['status'] == 'pendente') $totalPendente += $conta['valor'];
    if ($conta['status'] == 'pago') $totalPago += $conta['valor'];
    if ($conta['status'] == 'vencido') $totalVencido += $conta['valor'];
}

include 'header.php';
?>

<link rel="stylesheet" href="assets/css/tabelas_profissional.css">

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Contas a Receber</h2>
        </div>
        
        <!-- Cards de Totais -->
        <div class="totals-container">
            <div class="total-card total-card-pending">
                <div class="total-card-label">Total Pendente</div>
                <div class="total-card-value"><?php echo formatMoeda($totalPendente); ?></div>
            </div>
            <div class="total-card total-card-paid">
                <div class="total-card-label">Total Pago</div>
                <div class="total-card-value"><?php echo formatMoeda($totalPago); ?></div>
            </div>
            <div class="total-card total-card-overdue">
                <div class="total-card-label">Total Vencido</div>
                <div class="total-card-value"><?php echo formatMoeda($totalVencido); ?></div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div class="filter-container" style="flex: 1; min-width: 250px;">
                <form method="GET" style="display: flex; gap: 0.75rem; width: 100%; flex-wrap: wrap;">
                    <select name="status" style="flex: 1; min-width: 150px;">
                        <option value="">Todos os Status</option>
                        <option value="pendente" <?php echo $filtroStatus == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="pago" <?php echo $filtroStatus == 'pago' ? 'selected' : ''; ?>>Pago</option>
                        <option value="vencido" <?php echo $filtroStatus == 'vencido' ? 'selected' : ''; ?>>Vencido</option>
                        <option value="cancelado" <?php echo $filtroStatus == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                    <select name="cliente" style="flex: 1; min-width: 150px;">
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
            <a href="conta_receber_form.php" class="btn btn-success">+ Nova Conta a Receber</a>
        </div>
        
        <!-- Tabela Responsiva -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Descrição</th>
                        <th>Plano de Contas</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Forma Pagamento</th>
                        <th>Status</th>
                        <th>Parcela</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contas)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem; color: #64748b;">
                                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                                Nenhuma conta a receber encontrada.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($contas as $conta): ?>
                            <tr>
                                <td>
                                    <?php 
                                    echo htmlspecialchars($conta['tipo_pessoa'] == 'CNPJ' 
                                        ? ($conta['razao_social'] ?: $conta['nome_fantasia']) 
                                        : $conta['nome']); 
                                    ?>
                                </td>
                                <td class="truncate">
                                    <?php echo htmlspecialchars($conta['descricao']); ?>
                                </td>
                                <td class="truncate">
                                    <?php echo htmlspecialchars($conta['plano_conta_nome']); ?>
                                </td>
                                <td style="font-weight: 600; color: #1e40af;">
                                    <?php echo formatMoeda($conta['valor']); ?>
                                </td>
                                <td>
                                    <?php echo formatData($conta['data_vencimento']); ?>
                                </td>
                                <td>
                                    <?php echo ucfirst(str_replace('_', ' ', $conta['forma_pagamento'])); ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $conta['status']; ?>">
                                        <?php echo ucfirst($conta['status']); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <?php echo $conta['parcela_atual'] . '/' . $conta['recorrencia']; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <?php if ($conta['status'] == 'pendente' || $conta['status'] == 'vencido'): ?>
                                            <a href="conta_receber_pagar.php?id=<?php echo $conta['id']; ?>" 
                                               class="btn btn-success btn-sm"
                                               onclick="return confirm('Confirmar pagamento desta conta?')">
                                                <i class="fas fa-check"></i> Pagar
                                            </a>
                                        <?php endif; ?>
                                        <a href="conta_receber_form.php?id=<?php echo $conta['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <a href="conta_receber_delete.php?id=<?php echo $conta['id']; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Tem certeza que deseja excluir esta conta?')">
                                            <i class="fas fa-trash"></i> Excluir
                                        </a>
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

<?php include 'footer.php'; ?>
