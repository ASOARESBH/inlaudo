<?php
/**
 * Histórico de E-mails
 * 
 * Visualizar histórico de e-mails enviados
 */

require_once dirname(dirname(dirname(__FILE__))) . '/core/Bootstrap.php';

use App\Core\Database;

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$db = Database::getInstance();

// Paginação
$pagina = $_GET['pagina'] ?? 1;
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_data = $_GET['data'] ?? '';

// Construir query
$where = [];
$params = [];

if ($filtro_status) {
    $where[] = "status = ?";
    $params[] = $filtro_status;
}

if ($filtro_tipo) {
    $where[] = "tipo = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_data) {
    $where[] = "DATE(data_envio) = ?";
    $params[] = $filtro_data;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Obter total
$sql_total = "SELECT COUNT(*) as total FROM email_log $where_clause";
$resultado_total = $db->fetchOne($sql_total, $params);
$total = $resultado_total['total'] ?? 0;
$total_paginas = ceil($total / $por_pagina);

// Obter e-mails
$sql = "SELECT * FROM email_log $where_clause ORDER BY data_envio DESC LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;
$emails = $db->fetchAll($sql, $params);

// Estatísticas
$sql_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) as enviados,
    SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erros,
    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes
FROM email_log";
$stats = $db->fetchOne($sql_stats);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de E-mails - ERP INLAUDO</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
    <style>
        .historico-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }

        .historico-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 30px;
        }

        /* Estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #3498db;
        }

        .stat-card.success {
            border-left-color: #27ae60;
        }

        .stat-card.danger {
            border-left-color: #e74c3c;
        }

        .stat-card.warning {
            border-left-color: #f39c12;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #7f8c8d;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
        }

        /* Filtros */
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .filters-title {
            font-weight: 600;
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .filter-group input,
        .filter-group select {
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        /* Tabela */
        .table-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: #2c3e50;
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        td {
            padding: 12px 15px;
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
            text-transform: uppercase;
        }

        .status-enviado {
            background-color: #d4edda;
            color: #155724;
        }

        .status-erro {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }

        .email-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            padding: 4px 8px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .action-btn-view {
            background-color: #3498db;
            color: white;
        }

        .action-btn-view:hover {
            background-color: #2980b9;
        }

        .action-btn-delete {
            background-color: #e74c3c;
            color: white;
        }

        .action-btn-delete:hover {
            background-color: #c0392b;
        }

        /* Paginação */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
            padding: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }

        .pagination .active {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.85rem;
            }

            th, td {
                padding: 8px 10px;
            }

            .email-cell {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
    <?php include dirname(__FILE__) . '/../layouts/navbar.php'; ?>

    <div class="historico-container">
        <!-- Título -->
        <h1 class="historico-title">📧 Histórico de E-mails</h1>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total de E-mails</div>
                <div class="stat-value"><?= number_format($stats['total'] ?? 0) ?></div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">Enviados</div>
                <div class="stat-value"><?= number_format($stats['enviados'] ?? 0) ?></div>
            </div>
            <div class="stat-card danger">
                <div class="stat-label">Erros</div>
                <div class="stat-value"><?= number_format($stats['erros'] ?? 0) ?></div>
            </div>
            <div class="stat-card warning">
                <div class="stat-label">Pendentes</div>
                <div class="stat-value"><?= number_format($stats['pendentes'] ?? 0) ?></div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <div class="filters-title">🔍 Filtros</div>
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">Todos</option>
                            <option value="enviado" <?= $filtro_status === 'enviado' ? 'selected' : '' ?>>Enviado</option>
                            <option value="erro" <?= $filtro_status === 'erro' ? 'selected' : '' ?>>Erro</option>
                            <option value="pendente" <?= $filtro_status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo">
                            <option value="">Todos</option>
                            <option value="notificacao" <?= $filtro_tipo === 'notificacao' ? 'selected' : '' ?>>Notificação</option>
                            <option value="alerta" <?= $filtro_tipo === 'alerta' ? 'selected' : '' ?>>Alerta</option>
                            <option value="confirmacao" <?= $filtro_tipo === 'confirmacao' ? 'selected' : '' ?>>Confirmação</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="data">Data</label>
                        <input type="date" id="data" name="data" value="<?= htmlspecialchars($filtro_data) ?>">
                    </div>

                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                        <a href="<?= BASE_URL ?>/configuracao/historico-email" class="btn btn-secondary">Limpar</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabela -->
        <div class="table-section">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Para</th>
                        <th>Assunto</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($emails)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #7f8c8d;">
                                Nenhum e-mail encontrado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($emails as $email): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($email['data_envio'])) ?></td>
                                <td class="email-cell" title="<?= htmlspecialchars($email['destinatario']) ?>">
                                    <?= htmlspecialchars($email['destinatario']) ?>
                                </td>
                                <td title="<?= htmlspecialchars($email['assunto']) ?>">
                                    <?= htmlspecialchars(substr($email['assunto'], 0, 50)) ?>...
                                </td>
                                <td><?= htmlspecialchars($email['tipo']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $email['status'] ?>">
                                        <?= ucfirst($email['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn action-btn-view" onclick="verEmail(<?= $email['id'] ?>)">
                                            👁️
                                        </button>
                                        <button class="action-btn action-btn-delete" onclick="deletarEmail(<?= $email['id'] ?>)">
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($total_paginas > 1): ?>
            <div class="pagination">
                <?php if ($pagina > 1): ?>
                    <a href="?pagina=1<?= $filtro_status ? '&status=' . urlencode($filtro_status) : '' ?><?= $filtro_tipo ? '&tipo=' . urlencode($filtro_tipo) : '' ?><?= $filtro_data ? '&data=' . urlencode($filtro_data) : '' ?>">« Primeira</a>
                    <a href="?pagina=<?= $pagina - 1 ?><?= $filtro_status ? '&status=' . urlencode($filtro_status) : '' ?><?= $filtro_tipo ? '&tipo=' . urlencode($filtro_tipo) : '' ?><?= $filtro_data ? '&data=' . urlencode($filtro_data) : '' ?>">‹ Anterior</a>
                <?php endif; ?>

                <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                    <?php if ($i === $pagina): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?pagina=<?= $i ?><?= $filtro_status ? '&status=' . urlencode($filtro_status) : '' ?><?= $filtro_tipo ? '&tipo=' . urlencode($filtro_tipo) : '' ?><?= $filtro_data ? '&data=' . urlencode($filtro_data) : '' ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($pagina < $total_paginas): ?>
                    <a href="?pagina=<?= $pagina + 1 ?><?= $filtro_status ? '&status=' . urlencode($filtro_status) : '' ?><?= $filtro_tipo ? '&tipo=' . urlencode($filtro_tipo) : '' ?><?= $filtro_data ? '&data=' . urlencode($filtro_data) : '' ?>">Próxima ›</a>
                    <a href="?pagina=<?= $total_paginas ?><?= $filtro_status ? '&status=' . urlencode($filtro_status) : '' ?><?= $filtro_tipo ? '&tipo=' . urlencode($filtro_tipo) : '' ?><?= $filtro_data ? '&data=' . urlencode($filtro_data) : '' ?>">Última »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include dirname(__FILE__) . '/../layouts/footer.php'; ?>

    <script>
        function verEmail(id) {
            // Implementar visualização do e-mail
            alert('Visualizar e-mail #' + id);
        }

        function deletarEmail(id) {
            if (confirm('Tem certeza que deseja deletar este e-mail?')) {
                // Implementar deleção
                alert('Deletar e-mail #' + id);
            }
        }
    </script>
</body>
</html>
