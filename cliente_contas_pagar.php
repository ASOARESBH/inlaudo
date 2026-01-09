<?php
/**
 * Portal do Cliente - Contas a Pagar
 * ERP INLAUDO - Versão 7.3
 */

require_once 'verifica_sessao_cliente.php';
require_once 'config.php';

$conn = getConnection();

// Filtros
$status_filtro = $_GET['status'] ?? 'pendente';

// Buscar contas a receber do cliente (são as contas a pagar do cliente)
$sql = "
    SELECT cr.*
    FROM contas_receber cr
    WHERE cr.cliente_id = ?
";

$params = [$cliente_id];

if ($status_filtro != 'todos') {
    $sql .= " AND cr.status = ?";
    $params[] = $status_filtro;
}

$sql .= " ORDER BY 
    CASE cr.status
        WHEN 'pendente' THEN 1
        WHEN 'pago' THEN 2
        WHEN 'cancelado' THEN 3
    END,
    cr.data_vencimento ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$contas = $stmt->fetchAll();

// Calcular totais
$totalPendente = 0;
$totalVencido = 0;
$totalPago = 0;

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
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contas a Pagar - Portal do Cliente</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        h1 {
            color: #1e293b;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            color: #64748b;
            margin-bottom: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        
        .stat-card.yellow { border-left-color: #f59e0b; }
        .stat-card.red { border-left-color: #ef4444; }
        .stat-card.green { border-left-color: #10b981; }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .stat-value.yellow { color: #f59e0b; }
        .stat-value.red { color: #ef4444; }
        .stat-value.green { color: #10b981; }
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filters form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .filter-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #475569;
        }
        
        .btn-pay {
            background: #3b82f6;
            color: white;
        }
        
        .btn-pay:hover {
            background: #2563eb;
        }
        
        .btn-nf {
            background: #8b5cf6;
            color: white;
        }
        
        .btn-nf:hover {
            background: #7c3aed;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        
        th {
            padding: 1rem;
            text-align: left;
            color: #64748b;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr.vencida {
            background: #fef2f2;
        }
        
        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge.pendente { background: #fef3c7; color: #92400e; }
        .badge.pago { background: #d1fae5; color: #065f46; }
        .badge.cancelado { background: #fee2e2; color: #991b1b; }
        
        .empty-state {
            background: white;
            padding: 4rem 2rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <a href="portal_cliente.php" class="btn-back">← Voltar</a>
                <div>
                    <h2>Contas a Pagar</h2>
                </div>
            </div>
            <div>
                <span><?php echo htmlspecialchars($cliente_nome); ?></span>
            </div>
        </div>
    </div>
    
    <div class="container">
        <h1>💳 Contas a Pagar</h1>
        <p class="subtitle">Visualize suas contas, vencimentos e realize pagamentos online</p>
        
        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card yellow">
                <div class="stat-label">Total Pendente</div>
                <div class="stat-value yellow"><?php echo formatMoeda($totalPendente); ?></div>
            </div>
            
            <?php if ($totalVencido > 0): ?>
            <div class="stat-card red">
                <div class="stat-label">Total Vencido</div>
                <div class="stat-value red"><?php echo formatMoeda($totalVencido); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="stat-card green">
                <div class="stat-label">Total Pago</div>
                <div class="stat-value green"><?php echo formatMoeda($totalPago); ?></div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filters">
            <form method="GET">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="todos" <?php echo $status_filtro == 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="pendente" <?php echo $status_filtro == 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="pago" <?php echo $status_filtro == 'pago' ? 'selected' : ''; ?>>Pago</option>
                        <option value="cancelado" <?php echo $status_filtro == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="cliente_contas_pagar.php" class="btn btn-secondary">Limpar</a>
            </form>
        </div>
        
        <!-- Tabela de Contas -->
        <?php if (empty($contas)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h2>Nenhuma conta encontrada</h2>
                <p>Você não possui contas com o filtro selecionado.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Valor</th>
                            <th>Vencimento</th>
                            <th>Forma Pagamento</th>
                            <th>Status</th>
                            <th>Parcela</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contas as $conta): 
                            $vencida = ($conta['status'] == 'pendente' && strtotime($conta['data_vencimento']) < time());
                        ?>
                            <tr class="<?php echo $vencida ? 'vencida' : ''; ?>">
                                <td>
                                    <div style="font-weight: 600; color: #1e293b; margin-bottom: 0.25rem;">
                                        <?php echo htmlspecialchars($conta['descricao']); ?>
                                    </div>
                                    <?php if ($vencida): ?>
                                        <div style="color: #ef4444; font-size: 0.85rem;">
                                            ⚠️ Vencida
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 700; font-size: 1.1rem; color: #1e293b;">
                                        <?php echo formatMoeda($conta['valor']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: <?php echo $vencida ? '#ef4444' : '#1e293b'; ?>;">
                                        <?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $formas = [
                                        'boleto' => '📄 Boleto',
                                        'cartao_credito' => '💳 Crédito',
                                        'pix' => '🔲 PIX',
                                        'transferencia' => '🏦 Transferência'
                                    ];
                                    echo $formas[$conta['forma_pagamento']] ?? ucfirst(str_replace('_', ' ', $conta['forma_pagamento']));
                                    ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $conta['status']; ?>">
                                        <?php
                                        $statusLabels = [
                                            'pendente' => '⏳ Pendente',
                                            'pago' => '✓ Pago',
                                            'cancelado' => '✗ Cancelado'
                                        ];
                                        echo $statusLabels[$conta['status']] ?? ucfirst($conta['status']);
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $conta['parcela_atual']; ?>/<?php echo $conta['recorrencia']; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <?php if ($conta['status'] == 'pendente'): ?>
                                            <a href="cliente_pagar.php?id=<?php echo $conta['id']; ?>" 
                                               class="btn btn-pay">
                                                💳 Pagar
                                            </a>
                                        <?php elseif ($conta['status'] == 'pago'): ?>
                                            <button class="btn btn-nf" disabled title="Funcionalidade em desenvolvimento">
                                                📄 NF
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
