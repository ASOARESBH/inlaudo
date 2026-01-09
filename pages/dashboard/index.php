<?php
/**
 * Dashboard - Página Principal
 * 
 * Exibe resumo do sistema e alertas
 */

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

use App\Core\Database;
use App\Services\AlertaService;

$db = Database::getInstance();
$alertaService = new AlertaService();

// Obter estatísticas
$stats = [
    'clientes_total' => $db->fetchOne("SELECT COUNT(*) as total FROM clientes")['total'] ?? 0,
    'contas_receber' => $db->fetchOne("SELECT COUNT(*) as total FROM contas_receber WHERE status = 'pendente'")['total'] ?? 0,
    'contas_pagar' => $db->fetchOne("SELECT COUNT(*) as total FROM contas_pagar WHERE status = 'pendente'")['total'] ?? 0,
    'valor_receber' => $db->fetchOne("SELECT SUM(valor) as total FROM contas_receber WHERE status = 'pendente'")['total'] ?? 0,
    'valor_pagar' => $db->fetchOne("SELECT SUM(valor) as total FROM contas_pagar WHERE status = 'pendente'")['total'] ?? 0,
];

// Obter alertas
$alertas = $alertaService->obterAlertas($_SESSION['usuario_id'], 5);

// Obter últimos clientes
$ultimos_clientes = $db->fetchAll("SELECT id, nome, email FROM clientes ORDER BY data_criacao DESC LIMIT 5");

// Obter contas vencidas
$contas_vencidas = $db->fetchAll("
    SELECT c.id, c.descricao, c.valor, c.data_vencimento, cl.nome as cliente_nome
    FROM contas_receber c
    JOIN clientes cl ON c.cliente_id = cl.id
    WHERE c.status = 'pendente' AND c.data_vencimento < NOW()
    ORDER BY c.data_vencimento ASC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ERP INLAUDO</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 20px;
        }

        .dashboard-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 30px;
        }

        /* Grid de Estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #3498db;
        }

        .stat-card.danger {
            border-left-color: #e74c3c;
        }

        .stat-card.success {
            border-left-color: #27ae60;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #7f8c8d;
            text-transform: uppercase;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .stat-change {
            font-size: 0.85rem;
            color: #27ae60;
            margin-top: 10px;
        }

        .stat-change.negative {
            color: #e74c3c;
        }

        /* Seções */
        .dashboard-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
        }

        /* Alertas */
        .alerts-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .alert-item {
            padding: 15px;
            border-left: 4px solid #f39c12;
            background-color: #fffbf0;
            margin-bottom: 10px;
            border-radius: 4px;
        }

        .alert-item.danger {
            border-left-color: #e74c3c;
            background-color: #fef5f5;
        }

        .alert-item.success {
            border-left-color: #27ae60;
            background-color: #f0fdf4;
        }

        .alert-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .alert-description {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        /* Tabelas */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: #f9f9f9;
        }

        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #ecf0f1;
        }

        tbody tr:hover {
            background-color: #f9f9f9;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-pago {
            background-color: #d4edda;
            color: #155724;
        }

        .status-vencido {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Grid de Seções */
        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .sections-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-container {
                padding: 10px;
            }

            .stat-value {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <?php include VIEWS_PATH . '/layouts/navbar.php'; ?>

    <div class="dashboard-container">
        <!-- Título -->
        <h1 class="dashboard-title">📊 Dashboard</h1>

        <!-- Grid de Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total de Clientes</div>
                <div class="stat-value"><?= number_format($stats['clientes_total']) ?></div>
            </div>

            <div class="stat-card danger">
                <div class="stat-label">Contas a Receber</div>
                <div class="stat-value"><?= number_format($stats['contas_receber']) ?></div>
                <div class="stat-change">R$ <?= number_format($stats['valor_receber'], 2, ',', '.') ?></div>
            </div>

            <div class="stat-card success">
                <div class="stat-label">Contas a Pagar</div>
                <div class="stat-value"><?= number_format($stats['contas_pagar']) ?></div>
                <div class="stat-change">R$ <?= number_format($stats['valor_pagar'], 2, ',', '.') ?></div>
            </div>
        </div>

        <!-- Grid de Seções -->
        <div class="sections-grid">
            <!-- Alertas -->
            <div class="dashboard-section">
                <h2 class="section-title">🔔 Alertas Recentes</h2>
                <?php if (empty($alertas)): ?>
                    <p style="color: #7f8c8d;">Nenhum alerta no momento</p>
                <?php else: ?>
                    <ul class="alerts-list">
                        <?php foreach ($alertas as $alerta): ?>
                            <li class="alert-item <?= $alerta['tipo_alerta'] === 'vencido' ? 'danger' : '' ?>">
                                <div class="alert-title"><?= htmlspecialchars($alerta['titulo']) ?></div>
                                <div class="alert-description">
                                    <?= htmlspecialchars($alerta['descricao']) ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Últimos Clientes -->
            <div class="dashboard-section">
                <h2 class="section-title">👥 Últimos Clientes</h2>
                <?php if (empty($ultimos_clientes)): ?>
                    <p style="color: #7f8c8d;">Nenhum cliente cadastrado</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimos_clientes as $cliente): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= BASE_URL ?>/clientes/view?id=<?= $cliente['id'] ?>">
                                                <?= htmlspecialchars($cliente['nome']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($cliente['email']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Contas Vencidas -->
        <?php if (!empty($contas_vencidas)): ?>
            <div class="dashboard-section">
                <h2 class="section-title">⚠️ Contas Vencidas</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Descrição</th>
                                <th>Valor</th>
                                <th>Vencimento</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contas_vencidas as $conta): ?>
                                <tr>
                                    <td><?= htmlspecialchars($conta['cliente_nome']) ?></td>
                                    <td><?= htmlspecialchars($conta['descricao']) ?></td>
                                    <td>R$ <?= number_format($conta['valor'], 2, ',', '.') ?></td>
                                    <td><?= date('d/m/Y', strtotime($conta['data_vencimento'])) ?></td>
                                    <td>
                                        <span class="status-badge status-vencido">Vencido</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include VIEWS_PATH . '/layouts/footer.php'; ?>
</body>
</html>
