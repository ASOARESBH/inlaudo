<?php
/**
 * Visualizador de Logs Asaas
 * 
 * Página para visualizar logs e auditoria da integração Asaas
 * 
 * @author Backend Developer
 * @version 1.0.0
 */

session_start();

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once 'vendor/autoload.php';

use App\Core\Database;

$db = Database::getInstance();

// Obter filtros
$filtro_operacao = $_GET['operacao'] ?? '';
$filtro_status = $_GET['status'] ?? '';
$filtro_data = $_GET['data'] ?? '';
$pagina = (int) ($_GET['pagina'] ?? 1);
$por_pagina = 20;

// Construir query
$sql = "SELECT * FROM asaas_logs WHERE 1=1";
$params = [];

if ($filtro_operacao) {
    $sql .= " AND operacao LIKE ?";
    $params[] = "%{$filtro_operacao}%";
}

if ($filtro_status) {
    $sql .= " AND status = ?";
    $params[] = $filtro_status;
}

if ($filtro_data) {
    $sql .= " AND DATE(data_criacao) = ?";
    $params[] = $filtro_data;
}

// Contar total
$countSql = str_replace('SELECT *', 'SELECT COUNT(*) as total', $sql);
$countResult = $db->fetchOne($countSql, $params);
$total = $countResult['total'] ?? 0;
$total_paginas = ceil($total / $por_pagina);

// Paginar
$offset = ($pagina - 1) * $por_pagina;
$sql .= " ORDER BY data_criacao DESC LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;

$logs = $db->fetchAll($sql, $params);

// Obter estatísticas
$statsql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
        SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erro,
        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendente
    FROM asaas_logs
";
$stats = $db->fetchOne($statsql);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs Asaas - Auditoria</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
        }
        
        .stat-card.success {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            color: #333;
        }
        
        .stat-card.error {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: #333;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .filters h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #666;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .logs-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f5f5f5;
            border-bottom: 2px solid #ddd;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 13px;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-badge.sucesso {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.erro {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-badge.pendente {
            background: #fff3cd;
            color: #856404;
        }
        
        .operation-name {
            font-weight: 600;
            color: #667eea;
        }
        
        .timestamp {
            color: #999;
            font-size: 12px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #667eea;
            font-size: 13px;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        
        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .pagination .disabled {
            color: #ccc;
            cursor: not-allowed;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .json-viewer {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📊 Logs de Auditoria - Asaas</h1>
            
            <!-- Estatísticas -->
            <div class="stats">
                <div class="stat-card">
                    <h3>Total de Operações</h3>
                    <div class="value"><?php echo $stats['total'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card success">
                    <h3>Sucesso</h3>
                    <div class="value"><?php echo $stats['sucesso'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card error">
                    <h3>Erros</h3>
                    <div class="value"><?php echo $stats['erro'] ?? 0; ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Pendentes</h3>
                    <div class="value"><?php echo $stats['pendente'] ?? 0; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filters">
            <h3>🔍 Filtros</h3>
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="operacao">Operação</label>
                        <input type="text" id="operacao" name="operacao" value="<?php echo htmlspecialchars($filtro_operacao); ?>" placeholder="Ex: create_payment">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">Todos</option>
                            <option value="sucesso" <?php echo $filtro_status === 'sucesso' ? 'selected' : ''; ?>>Sucesso</option>
                            <option value="erro" <?php echo $filtro_status === 'erro' ? 'selected' : ''; ?>>Erro</option>
                            <option value="pendente" <?php echo $filtro_status === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="data">Data</label>
                        <input type="date" id="data" name="data" value="<?php echo htmlspecialchars($filtro_data); ?>">
                    </div>
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">🔎 Filtrar</button>
                    <a href="logs_asaas_viewer.php" class="btn btn-secondary">↻ Limpar</a>
                </div>
            </form>
        </div>
        
        <!-- Tabela de Logs -->
        <div class="logs-table">
            <?php if (!empty($logs)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Operação</th>
                        <th>Status</th>
                        <th>Mensagem</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <div><?php echo date('d/m/Y', strtotime($log['data_criacao'])); ?></div>
                            <div class="timestamp"><?php echo date('H:i:s', strtotime($log['data_criacao'])); ?></div>
                        </td>
                        <td>
                            <span class="operation-name"><?php echo htmlspecialchars($log['operacao']); ?></span>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $log['status']; ?>">
                                <?php echo ucfirst($log['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            $msg = $log['mensagem_erro'] ?? '';
                            echo htmlspecialchars(substr($msg, 0, 50));
                            if (strlen($msg) > 50) echo '...';
                            ?>
                        </td>
                        <td>
                            <button class="btn btn-secondary" onclick="abrirDetalhes(<?php echo $log['id']; ?>)">
                                Detalhes
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Paginação -->
            <?php if ($total_paginas > 1): ?>
            <div class="pagination">
                <?php if ($pagina > 1): ?>
                    <a href="?pagina=1<?php echo $filtro_operacao ? "&operacao={$filtro_operacao}" : ''; ?><?php echo $filtro_status ? "&status={$filtro_status}" : ''; ?><?php echo $filtro_data ? "&data={$filtro_data}" : ''; ?>">« Primeira</a>
                    <a href="?pagina=<?php echo $pagina - 1; ?><?php echo $filtro_operacao ? "&operacao={$filtro_operacao}" : ''; ?><?php echo $filtro_status ? "&status={$filtro_status}" : ''; ?><?php echo $filtro_data ? "&data={$filtro_data}" : ''; ?>">‹ Anterior</a>
                <?php else: ?>
                    <span class="disabled">« Primeira</span>
                    <span class="disabled">‹ Anterior</span>
                <?php endif; ?>
                
                <span><?php echo $pagina; ?> de <?php echo $total_paginas; ?></span>
                
                <?php if ($pagina < $total_paginas): ?>
                    <a href="?pagina=<?php echo $pagina + 1; ?><?php echo $filtro_operacao ? "&operacao={$filtro_operacao}" : ''; ?><?php echo $filtro_status ? "&status={$filtro_status}" : ''; ?><?php echo $filtro_data ? "&data={$filtro_data}" : ''; ?>">Próxima ›</a>
                    <a href="?pagina=<?php echo $total_paginas; ?><?php echo $filtro_operacao ? "&operacao={$filtro_operacao}" : ''; ?><?php echo $filtro_status ? "&status={$filtro_status}" : ''; ?><?php echo $filtro_data ? "&data={$filtro_data}" : ''; ?>">Última »</a>
                <?php else: ?>
                    <span class="disabled">Próxima ›</span>
                    <span class="disabled">Última »</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="no-data">
                <p>Nenhum log encontrado com os filtros selecionados.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal de Detalhes -->
    <div id="detalhesModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="fecharDetalhes()">×</button>
            <div class="modal-header">Detalhes do Log</div>
            
            <div style="margin-bottom: 20px;">
                <h4 style="margin-bottom: 10px; color: #666;">Requisição</h4>
                <div id="requisicao" class="json-viewer"></div>
            </div>
            
            <div>
                <h4 style="margin-bottom: 10px; color: #666;">Resposta</h4>
                <div id="resposta" class="json-viewer"></div>
            </div>
        </div>
    </div>
    
    <script>
        function abrirDetalhes(logId) {
            // Aqui você faria uma requisição AJAX para obter os detalhes
            // Por enquanto, apenas abrimos o modal
            document.getElementById('detalhesModal').classList.add('active');
        }
        
        function fecharDetalhes() {
            document.getElementById('detalhesModal').classList.remove('active');
        }
        
        // Fechar modal ao clicar fora
        document.getElementById('detalhesModal').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharDetalhes();
            }
        });
    </script>
</body>
</html>
