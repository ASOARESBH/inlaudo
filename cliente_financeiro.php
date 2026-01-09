<?php
/**
 * Portal do Cliente - Meu Financeiro
 */

session_start();
require_once 'config.php';
require_once 'header_cliente.php';

$conn = getConnection();
$cliente_id = $_SESSION['cliente_id'];

// Filtros
$status_filtro = $_GET['status'] ?? 'todos';
$mes_filtro = $_GET['mes'] ?? date('Y-m');

// Buscar contas a receber do cliente
$sql = "
    SELECT 
        cr.*,
        pc.descricao as plano_conta_desc
    FROM contas_receber cr
    LEFT JOIN plano_contas pc ON cr.plano_contas_id = pc.id
    WHERE cr.cliente_id = ?
";

$params = [$cliente_id];

if ($status_filtro != 'todos') {
    $sql .= " AND cr.status = ?";
    $params[] = $status_filtro;
}

if (!empty($mes_filtro)) {
    $sql .= " AND DATE_FORMAT(cr.data_vencimento, '%Y-%m') = ?";
    $params[] = $mes_filtro;
}

$sql .= " ORDER BY cr.data_vencimento DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$contas = $stmt->fetchAll();

// Calcular totais
$totalPendente = 0;
$totalPago = 0;
$totalVencido = 0;

foreach ($contas as $conta) {
    if ($conta['status'] == 'pendente') {
        $totalPendente += $conta['valor'];
        if (strtotime($conta['data_vencimento']) < time()) {
            $totalVencido += $conta['valor'];
        }
    } elseif ($conta['status'] == 'pago') {
        $totalPago += $conta['valor'];
    }
}
?>

<h1 style="color: #1e293b; margin: 0 0 25px 0;">Meu Financeiro</h1>

<!-- Resumo Financeiro -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #f59e0b;">
        <p style="color: #64748b; font-size: 0.9rem; margin: 0 0 8px 0;">Total Pendente</p>
        <p style="color: #1e293b; font-size: 1.8rem; font-weight: 700; margin: 0;"><?php echo formatarMoeda($totalPendente); ?></p>
    </div>
    
    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #dc2626;">
        <p style="color: #64748b; font-size: 0.9rem; margin: 0 0 8px 0;">Vencidas</p>
        <p style="color: #dc2626; font-size: 1.8rem; font-weight: 700; margin: 0;"><?php echo formatarMoeda($totalVencido); ?></p>
    </div>
    
    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #10b981;">
        <p style="color: #64748b; font-size: 0.9rem; margin: 0 0 8px 0;">Total Pago</p>
        <p style="color: #10b981; font-size: 1.8rem; font-weight: 700; margin: 0;"><?php echo formatarMoeda($totalPago); ?></p>
    </div>
</div>

<!-- Filtros -->
<div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px;">
    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
        <div style="flex: 1; min-width: 200px;">
            <label style="display: block; color: #64748b; font-size: 0.9rem; margin-bottom: 5px;">Status</label>
            <select name="status" style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                <option value="todos" <?php echo $status_filtro == 'todos' ? 'selected' : ''; ?>>Todos</option>
                <option value="pendente" <?php echo $status_filtro == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                <option value="pago" <?php echo $status_filtro == 'pago' ? 'selected' : ''; ?>>Pago</option>
                <option value="cancelado" <?php echo $status_filtro == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
            </select>
        </div>
        
        <div style="flex: 1; min-width: 200px;">
            <label style="display: block; color: #64748b; font-size: 0.9rem; margin-bottom: 5px;">Mês</label>
            <input type="month" name="mes" value="<?php echo htmlspecialchars($mes_filtro); ?>" 
                   style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
        </div>
        
        <button type="submit" style="padding: 10px 24px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
            Filtrar
        </button>
        
        <a href="cliente_financeiro.php" style="padding: 10px 24px; background: #64748b; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
            Limpar
        </a>
    </form>
</div>

<!-- Lista de Contas -->
<?php if (empty($contas)): ?>
    <div style="background: #f8fafc; padding: 40px; border-radius: 12px; text-align: center; color: #64748b;">
        <p style="font-size: 1.2rem; margin: 0;">Nenhuma conta encontrada.</p>
    </div>
<?php else: ?>
    <div style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <th style="padding: 15px; text-align: left; color: #64748b; font-weight: 600; font-size: 0.9rem;">Descrição</th>
                        <th style="padding: 15px; text-align: left; color: #64748b; font-weight: 600; font-size: 0.9rem;">Vencimento</th>
                        <th style="padding: 15px; text-align: right; color: #64748b; font-weight: 600; font-size: 0.9rem;">Valor</th>
                        <th style="padding: 15px; text-align: center; color: #64748b; font-weight: 600; font-size: 0.9rem;">Forma Pgto</th>
                        <th style="padding: 15px; text-align: center; color: #64748b; font-weight: 600; font-size: 0.9rem;">Status</th>
                        <th style="padding: 15px; text-align: center; color: #64748b; font-weight: 600; font-size: 0.9rem;">Boleto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contas as $conta): 
                        $vencida = ($conta['status'] == 'pendente' && strtotime($conta['data_vencimento']) < time());
                    ?>
                        <tr style="border-bottom: 1px solid #e2e8f0; <?php echo $vencida ? 'background: #fef2f2;' : ''; ?>">
                            <td style="padding: 15px;">
                                <p style="color: #1e293b; font-weight: 600; margin: 0 0 4px 0;"><?php echo htmlspecialchars($conta['descricao']); ?></p>
                                <p style="color: #64748b; font-size: 0.85rem; margin: 0;"><?php echo htmlspecialchars($conta['plano_conta_desc'] ?? 'Sem categoria'); ?></p>
                            </td>
                            <td style="padding: 15px;">
                                <p style="color: <?php echo $vencida ? '#dc2626' : '#1e293b'; ?>; font-weight: 600; margin: 0;">
                                    <?php echo formatarData($conta['data_vencimento']); ?>
                                </p>
                                <?php if ($vencida): ?>
                                    <p style="color: #dc2626; font-size: 0.85rem; margin: 4px 0 0 0;">⚠️ Vencida</p>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px; text-align: right;">
                                <p style="color: #1e293b; font-weight: 700; font-size: 1.1rem; margin: 0;"><?php echo formatarMoeda($conta['valor']); ?></p>
                            </td>
                            <td style="padding: 15px; text-align: center;">
                                <?php 
                                    $formas = ['boleto' => '📄 Boleto', 'cartao_credito' => '💳 Crédito', 'cartao_debito' => '💳 Débito', 'pix' => '🔲 PIX'];
                                    echo $formas[$conta['forma_pagamento']] ?? $conta['forma_pagamento'];
                                ?>
                            </td>
                            <td style="padding: 15px; text-align: center;">
                                <?php if ($conta['status'] == 'pendente'): ?>
                                    <span style="background: #fef3c7; color: #92400e; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">⏳ Pendente</span>
                                <?php elseif ($conta['status'] == 'pago'): ?>
                                    <span style="background: #d1fae5; color: #065f46; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">✓ Pago</span>
                                <?php else: ?>
                                    <span style="background: #fee2e2; color: #991b1b; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">✗ Cancelado</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px; text-align: center;">
                                <?php if ($conta['forma_pagamento'] == 'boleto' && !empty($conta['boleto_url'])): ?>
                                    <a href="<?php echo htmlspecialchars($conta['boleto_url']); ?>" 
                                       target="_blank" 
                                       style="color: #10b981; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                                        Ver Boleto
                                    </a>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'footer_cliente.php'; ?>
