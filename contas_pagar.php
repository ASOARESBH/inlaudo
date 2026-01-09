<?php
require_once 'config.php';

$pageTitle = 'Contas a Pagar';

// Processar filtros
$filtroStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Buscar contas a pagar
$conn = getConnection();
$sql = "SELECT cp.*, pc.nome as plano_conta_nome
        FROM contas_pagar cp
        INNER JOIN plano_contas pc ON cp.plano_contas_id = pc.id
        WHERE 1=1";
$params = [];

if (!empty($filtroStatus)) {
    $sql .= " AND cp.status = ?";
    $params[] = $filtroStatus;
}

$sql .= " ORDER BY cp.data_vencimento ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$contas = $stmt->fetchAll();

// Atualizar status de contas vencidas
$conn->exec("UPDATE contas_pagar SET status = 'vencido' WHERE status = 'pendente' AND data_vencimento < CURDATE()");

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

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Contas a Pagar</h2>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div style="background: #dbeafe; padding: 1rem; border-radius: 8px; border-left: 4px solid #2563eb;">
                <p style="color: #1e40af; font-size: 0.875rem; margin-bottom: 0.5rem;">Total Pendente</p>
                <p style="font-size: 1.5rem; font-weight: 600; color: #1e40af;"><?php echo formatMoeda($totalPendente); ?></p>
            </div>
            <div style="background: #d1fae5; padding: 1rem; border-radius: 8px; border-left: 4px solid #10b981;">
                <p style="color: #065f46; font-size: 0.875rem; margin-bottom: 0.5rem;">Total Pago</p>
                <p style="font-size: 1.5rem; font-weight: 600; color: #065f46;"><?php echo formatMoeda($totalPago); ?></p>
            </div>
            <div style="background: #fee2e2; padding: 1rem; border-radius: 8px; border-left: 4px solid #ef4444;">
                <p style="color: #991b1b; font-size: 0.875rem; margin-bottom: 0.5rem;">Total Vencido</p>
                <p style="font-size: 1.5rem; font-weight: 600; color: #991b1b;"><?php echo formatMoeda($totalVencido); ?></p>
            </div>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div style="flex: 1; min-width: 250px;">
                <form method="GET" style="display: flex; gap: 0.5rem;">
                    <select name="status" style="width: auto;">
                        <option value="">Todos os Status</option>
                        <option value="pendente" <?php echo $filtroStatus == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="pago" <?php echo $filtroStatus == 'pago' ? 'selected' : ''; ?>>Pago</option>
                        <option value="vencido" <?php echo $filtroStatus == 'vencido' ? 'selected' : ''; ?>>Vencido</option>
                        <option value="cancelado" <?php echo $filtroStatus == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </form>
            </div>
            <a href="conta_pagar_form.php" class="btn btn-danger">+ Nova Conta a Pagar</a>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Fornecedor</th>
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
                            <td colspan="9" style="text-align: center; padding: 2rem;">
                                Nenhuma conta a pagar encontrada.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($contas as $conta): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($conta['fornecedor']); ?></td>
                                <td><?php echo htmlspecialchars($conta['descricao']); ?></td>
                                <td><?php echo htmlspecialchars($conta['plano_conta_nome']); ?></td>
                                <td style="font-weight: 600;"><?php echo formatMoeda($conta['valor']); ?></td>
                                <td><?php echo formatData($conta['data_vencimento']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $conta['forma_pagamento'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $conta['status']; ?>">
                                        <?php echo ucfirst($conta['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $conta['parcela_atual'] . '/' . $conta['recorrencia']; ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($conta['status'] == 'pendente' || $conta['status'] == 'vencido'): ?>
                                            <a href="conta_pagar_pagar.php?id=<?php echo $conta['id']; ?>" 
                                               class="btn btn-success"
                                               onclick="return confirm('Confirmar pagamento desta conta?')">
                                                Pagar
                                            </a>
                                        <?php endif; ?>
                                        <a href="conta_pagar_form.php?id=<?php echo $conta['id']; ?>" class="btn btn-primary">Editar</a>
                                        <a href="conta_pagar_delete.php?id=<?php echo $conta['id']; ?>" 
                                           class="btn btn-danger" 
                                           onclick="return confirmarExclusao('Tem certeza que deseja excluir esta conta?')">
                                            Excluir
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
