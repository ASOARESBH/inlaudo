<?php
/**
 * Página - Listagem de Notas Fiscais
 * 
 * Exibe lista de notas fiscais importadas com filtros e ações
 */

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

use App\Controllers\NotaFiscalController;
use App\Models\NotaFiscalModel;

try {
    $controller = new NotaFiscalController();

    // Parâmetros
    $pagina = $_GET['pagina'] ?? 1;
    $filtros = [
        'fornecedor_id' => $_GET['fornecedor_id'] ?? '',
        'tipo_nota' => $_GET['tipo_nota'] ?? '',
        'status_nfe' => $_GET['status_nfe'] ?? '',
        'data_inicio' => $_GET['data_inicio'] ?? '',
        'data_fim' => $_GET['data_fim'] ?? '',
        'valor_minimo' => $_GET['valor_minimo'] ?? '',
        'valor_maximo' => $_GET['valor_maximo'] ?? '',
        'busca' => $_GET['busca'] ?? ''
    ];

    // Obter dados
    $resultado = $controller->listar($filtros, $pagina);
    $fornecedores = $controller->obterFornecedores();
    $stats = NotaFiscalModel::estatisticas();

} catch (Exception $e) {
    $erro = $e->getMessage();
    $resultado = ['dados' => [], 'total' => 0, 'pagina' => 1, 'por_pagina' => 20, 'total_paginas' => 0];
    $fornecedores = [];
    $stats = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas Fiscais - ERP INLAUDO</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
    <style>
        .nf-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 20px;
        }

        .nf-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .nf-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .btn-novo {
            padding: 12px 25px;
            background-color: #27ae60;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-novo:hover {
            background-color: #229954;
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

        .stat-label {
            font-size: 0.85rem;
            color: #7f8c8d;
            text-transform: uppercase;
            margin-bottom: 8px;
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
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
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

        .tipo-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .tipo-nfe {
            background-color: #d4edda;
            color: #155724;
        }

        .tipo-nfce {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-autorizada {
            background-color: #d4edda;
            color: #155724;
        }

        .status-cancelada {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-denegada {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
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

        .action-btn-download {
            background-color: #27ae60;
            color: white;
        }

        .action-btn-download:hover {
            background-color: #229954;
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

        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #e74c3c;
        }

        @media (max-width: 768px) {
            .nf-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

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
        }
    </style>
</head>
<body>
    <?php include VIEWS_PATH . '/layouts/navbar.php'; ?>

    <div class="nf-container">
        <!-- Título -->
        <div class="nf-header">
            <h1 class="nf-title">📄 Notas Fiscais</h1>
            <a href="<?= BASE_URL ?>/notas-fiscais/upload" class="btn-novo">+ Importar NF-e</a>
        </div>

        <!-- Erro -->
        <?php if (!empty($erro)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total de Notas</div>
                <div class="stat-value"><?= number_format($stats['total_notas'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">NF-e</div>
                <div class="stat-value"><?= number_format($stats['total_nfe'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">NFC-e</div>
                <div class="stat-value"><?= number_format($stats['total_nfce'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Valor Total</div>
                <div class="stat-value">R$ <?= number_format($stats['valor_total'] ?? 0, 2, ',', '.') ?></div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <div class="filters-title">🔍 Filtros</div>
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="fornecedor_id">Fornecedor</label>
                        <select id="fornecedor_id" name="fornecedor_id">
                            <option value="">Todos</option>
                            <?php foreach ($fornecedores as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= $filtros['fornecedor_id'] == $f['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['nome_fantasia'] ?? $f['razao_social']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="tipo_nota">Tipo</label>
                        <select id="tipo_nota" name="tipo_nota">
                            <option value="">Todos</option>
                            <option value="nfe" <?= $filtros['tipo_nota'] === 'nfe' ? 'selected' : '' ?>>NF-e</option>
                            <option value="nfce" <?= $filtros['tipo_nota'] === 'nfce' ? 'selected' : '' ?>>NFC-e</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="status_nfe">Status</label>
                        <select id="status_nfe" name="status_nfe">
                            <option value="">Todos</option>
                            <option value="autorizada" <?= $filtros['status_nfe'] === 'autorizada' ? 'selected' : '' ?>>Autorizada</option>
                            <option value="cancelada" <?= $filtros['status_nfe'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                            <option value="denegada" <?= $filtros['status_nfe'] === 'denegada' ? 'selected' : '' ?>>Denegada</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="data_inicio">Data Início</label>
                        <input type="date" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($filtros['data_inicio']) ?>">
                    </div>

                    <div class="filter-group">
                        <label for="data_fim">Data Fim</label>
                        <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($filtros['data_fim']) ?>">
                    </div>

                    <div class="filter-group">
                        <label for="busca">Busca</label>
                        <input type="text" id="busca" name="busca" placeholder="Chave ou fornecedor" value="<?= htmlspecialchars($filtros['busca']) ?>">
                    </div>
                </div>

                <div class="filter-buttons" style="margin-top: 15px;">
                    <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                    <a href="<?= BASE_URL ?>/notas-fiscais" class="btn btn-secondary">Limpar</a>
                </div>
            </form>
        </div>

        <!-- Tabela -->
        <div class="table-section">
            <table>
                <thead>
                    <tr>
                        <th>Chave</th>
                        <th>Fornecedor</th>
                        <th>Tipo</th>
                        <th>Data</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($resultado['dados'])): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #7f8c8d;">
                                Nenhuma nota fiscal encontrada
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($resultado['dados'] as $nf): ?>
                            <tr>
                                <td title="<?= htmlspecialchars($nf['chave_acesso']) ?>">
                                    <?= substr($nf['chave_acesso'], 0, 20) ?>...
                                </td>
                                <td><?= htmlspecialchars($nf['nome_fantasia'] ?? $nf['nome_fornecedor']) ?></td>
                                <td>
                                    <span class="tipo-badge tipo-<?= $nf['tipo_nota'] ?>">
                                        <?= strtoupper($nf['tipo_nota']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($nf['data_emissao'])) ?></td>
                                <td>R$ <?= number_format($nf['valor_total'], 2, ',', '.') ?></td>
                                <td>
                                    <span class="status-badge status-<?= $nf['status_nfe'] ?>">
                                        <?= ucfirst($nf['status_nfe']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="<?= BASE_URL ?>/notas-fiscais/view?id=<?= $nf['id'] ?>" class="action-btn action-btn-view">👁️</a>
                                        <a href="<?= BASE_URL ?>/api/notas-fiscais/download?id=<?= $nf['id'] ?>" class="action-btn action-btn-download">⬇️</a>
                                        <button class="action-btn action-btn-delete" onclick="deletarNF(<?= $nf['id'] ?>)">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($resultado['total_paginas'] > 1): ?>
            <div class="pagination">
                <?php if ($resultado['pagina'] > 1): ?>
                    <a href="?pagina=1<?= http_build_query(array_filter($filtros)) ? '&' . http_build_query(array_filter($filtros)) : '' ?>">« Primeira</a>
                    <a href="?pagina=<?= $resultado['pagina'] - 1 ?><?= http_build_query(array_filter($filtros)) ? '&' . http_build_query(array_filter($filtros)) : '' ?>">‹ Anterior</a>
                <?php endif; ?>

                <?php for ($i = max(1, $resultado['pagina'] - 2); $i <= min($resultado['total_paginas'], $resultado['pagina'] + 2); $i++): ?>
                    <?php if ($i === $resultado['pagina']): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?pagina=<?= $i ?><?= http_build_query(array_filter($filtros)) ? '&' . http_build_query(array_filter($filtros)) : '' ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($resultado['pagina'] < $resultado['total_paginas']): ?>
                    <a href="?pagina=<?= $resultado['pagina'] + 1 ?><?= http_build_query(array_filter($filtros)) ? '&' . http_build_query(array_filter($filtros)) : '' ?>">Próxima ›</a>
                    <a href="?pagina=<?= $resultado['total_paginas'] ?><?= http_build_query(array_filter($filtros)) ? '&' . http_build_query(array_filter($filtros)) : '' ?>">Última »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include VIEWS_PATH . '/layouts/footer.php'; ?>

    <script>
        function deletarNF(id) {
            if (confirm('Tem certeza que deseja deletar esta nota fiscal?')) {
                fetch('<?= BASE_URL ?>/api/notas-fiscais/deletar?id=' + id, {
                    method: 'DELETE'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.sucesso) {
                        location.reload();
                    } else {
                        alert('Erro: ' + data.erro);
                    }
                });
            }
        }
    </script>
</body>
</html>
