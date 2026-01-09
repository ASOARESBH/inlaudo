<?php
require_once 'config.php';

$pageTitle = 'Faturas Mercado Pago';

// Processar filtros
$filtroStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$filtroCliente = isset($_GET['cliente']) ? (int)$_GET['cliente'] : 0;
$filtroPeriodo = isset($_GET['periodo']) ? sanitize($_GET['periodo']) : '';

// Buscar transações do Mercado Pago
$conn = getConnection();
$sql = "SELECT 
            t.*,
            c.nome, 
            c.razao_social, 
            c.nome_fantasia, 
            c.tipo_pessoa,
            c.email,
            c.cnpj_cpf,
            cr.descricao as conta_descricao,
            cr.data_vencimento,
            ct.titulo as contrato_titulo
        FROM transacoes_pagamento t
        INNER JOIN clientes c ON t.cliente_id = c.id
        LEFT JOIN contas_receber cr ON t.conta_receber_id = cr.id
        LEFT JOIN contratos ct ON t.contrato_id = ct.id
        WHERE t.gateway = 'mercadopago'";

$params = [];

if (!empty($filtroStatus)) {
    $sql .= " AND t.status = ?";
    $params[] = $filtroStatus;
}

if ($filtroCliente > 0) {
    $sql .= " AND t.cliente_id = ?";
    $params[] = $filtroCliente;
}

if (!empty($filtroPeriodo)) {
    switch ($filtroPeriodo) {
        case 'hoje':
            $sql .= " AND DATE(t.data_criacao) = CURDATE()";
            break;
        case 'semana':
            $sql .= " AND YEARWEEK(t.data_criacao) = YEARWEEK(NOW())";
            break;
        case 'mes':
            $sql .= " AND MONTH(t.data_criacao) = MONTH(NOW()) AND YEAR(t.data_criacao) = YEAR(NOW())";
            break;
        case 'ano':
            $sql .= " AND YEAR(t.data_criacao) = YEAR(NOW())";
            break;
    }
}

$sql .= " ORDER BY t.data_criacao DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$transacoes = $stmt->fetchAll();

// Buscar clientes para o filtro
$stmtClientes = $conn->query("
    SELECT DISTINCT c.id, c.nome, c.razao_social, c.nome_fantasia, c.tipo_pessoa 
    FROM clientes c
    INNER JOIN transacoes_pagamento t ON c.id = t.cliente_id
    WHERE t.gateway = 'mercadopago'
    ORDER BY c.razao_social, c.nome
");
$clientes = $stmtClientes->fetchAll();

// Calcular totais
$totalTransacoes = count($transacoes);
$totalValor = 0;
$totalAprovado = 0;
$totalPendente = 0;
$totalRejeitado = 0;

foreach ($transacoes as $transacao) {
    $totalValor += $transacao['valor'];
    
    if ($transacao['status'] == 'approved') {
        $totalAprovado += $transacao['valor'];
    } elseif (in_array($transacao['status'], ['pending', 'in_process', 'authorized'])) {
        $totalPendente += $transacao['valor'];
    } elseif (in_array($transacao['status'], ['rejected', 'cancelled', 'refunded'])) {
        $totalRejeitado += $transacao['valor'];
    }
}

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>💳 Faturas Mercado Pago</h2>
            <p style="margin: 0.5rem 0 0 0; color: #666;">Todas as transações geradas via Mercado Pago</p>
        </div>
        
        <!-- Cards de Estatísticas -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Total de Transações</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo $totalTransacoes; ?></p>
            </div>
            <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Total Aprovado</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo formatMoeda($totalAprovado); ?></p>
            </div>
            <div style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Total Pendente</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo formatMoeda($totalPendente); ?></p>
            </div>
            <div style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Total Rejeitado</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo formatMoeda($totalRejeitado); ?></p>
            </div>
        </div>
        
        <!-- Filtros -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div style="flex: 1; min-width: 250px;">
                <form method="GET" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <select name="status" style="width: auto; min-width: 150px;">
                        <option value="">Todos os Status</option>
                        <option value="pending" <?php echo $filtroStatus == 'pending' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="approved" <?php echo $filtroStatus == 'approved' ? 'selected' : ''; ?>>Aprovado</option>
                        <option value="authorized" <?php echo $filtroStatus == 'authorized' ? 'selected' : ''; ?>>Autorizado</option>
                        <option value="in_process" <?php echo $filtroStatus == 'in_process' ? 'selected' : ''; ?>>Em Processamento</option>
                        <option value="in_mediation" <?php echo $filtroStatus == 'in_mediation' ? 'selected' : ''; ?>>Em Mediação</option>
                        <option value="rejected" <?php echo $filtroStatus == 'rejected' ? 'selected' : ''; ?>>Rejeitado</option>
                        <option value="cancelled" <?php echo $filtroStatus == 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
                        <option value="refunded" <?php echo $filtroStatus == 'refunded' ? 'selected' : ''; ?>>Reembolsado</option>
                        <option value="charged_back" <?php echo $filtroStatus == 'charged_back' ? 'selected' : ''; ?>>Chargeback</option>
                    </select>
                    
                    <select name="cliente" style="width: auto; min-width: 200px;">
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
                    
                    <select name="periodo" style="width: auto; min-width: 150px;">
                        <option value="">Todos os Períodos</option>
                        <option value="hoje" <?php echo $filtroPeriodo == 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                        <option value="semana" <?php echo $filtroPeriodo == 'semana' ? 'selected' : ''; ?>>Esta Semana</option>
                        <option value="mes" <?php echo $filtroPeriodo == 'mes' ? 'selected' : ''; ?>>Este Mês</option>
                        <option value="ano" <?php echo $filtroPeriodo == 'ano' ? 'selected' : ''; ?>>Este Ano</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="faturas_mercadopago.php" class="btn btn-secondary">Limpar</a>
                </form>
            </div>
        </div>
        
        <!-- Tabela de Transações -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID Transação</th>
                        <th>Cliente</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                        <th>Método</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transacoes)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem;">
                                <p style="font-size: 1.125rem; color: #666; margin-bottom: 0.5rem;">
                                    📭 Nenhuma transação encontrada
                                </p>
                                <p style="font-size: 0.875rem; color: #999;">
                                    Ajuste os filtros ou gere novas faturas
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transacoes as $t): ?>
                            <tr>
                                <td style="font-weight: 600; font-family: monospace;">
                                    <?php echo htmlspecialchars($t['payment_id'] ?: $t['transaction_id'] ?: '-'); ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600;">
                                        <?php 
                                        echo $t['tipo_pessoa'] == 'CNPJ' 
                                            ? ($t['razao_social'] ?: $t['nome_fantasia']) 
                                            : $t['nome']; 
                                        ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #666;">
                                        <?php echo formatCNPJCPF($t['cnpj_cpf']); ?>
                                    </div>
                                </td>
                                <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php 
                                    $descricao = $t['conta_descricao'] ?: $t['contrato_titulo'] ?: 'Pagamento';
                                    echo htmlspecialchars($descricao); 
                                    ?>
                                </td>
                                <td style="font-weight: 600; font-size: 1rem;">
                                    <?php echo formatMoeda($t['valor']); ?>
                                </td>
                                <td>
                                    <?php
                                    $metodoMap = [
                                        'credit_card' => ['💳 Cartão', 'primary'],
                                        'debit_card' => ['💳 Débito', 'primary'],
                                        'bolbancario' => ['🎫 Boleto', 'warning'],
                                        'pix' => ['⚡ PIX', 'success'],
                                        'account_money' => ['💰 Saldo MP', 'info']
                                    ];
                                    $metodoInfo = $metodoMap[$t['metodo_pagamento']] ?? ['❓ ' . ucfirst($t['metodo_pagamento']), 'secondary'];
                                    ?>
                                    <span style="font-size: 0.875rem;">
                                        <?php echo $metodoInfo[0]; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusMap = [
                                        'pending' => ['Pendente', 'pendente', '⏳'],
                                        'approved' => ['Aprovado', 'pago', '✅'],
                                        'authorized' => ['Autorizado', 'pendente', '🔐'],
                                        'in_process' => ['Em Processamento', 'pendente', '⏳'],
                                        'in_mediation' => ['Em Mediação', 'pendente', '⚖️'],
                                        'rejected' => ['Rejeitado', 'cancelado', '❌'],
                                        'cancelled' => ['Cancelado', 'cancelado', '🚫'],
                                        'refunded' => ['Reembolsado', 'vencido', '↩️'],
                                        'charged_back' => ['Chargeback', 'cancelado', '⚠️']
                                    ];
                                    $statusInfo = $statusMap[$t['status']] ?? ['Desconhecido', 'cancelado', '❓'];
                                    ?>
                                    <span class="badge badge-<?php echo $statusInfo[1]; ?>">
                                        <?php echo $statusInfo[2] . ' ' . $statusInfo[0]; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 0.875rem;">
                                        <?php echo date('d/m/Y', strtotime($t['data_criacao'])); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #666;">
                                        <?php echo date('H:i', strtotime($t['data_criacao'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="actions">
                                        <?php if ($t['payment_url']): ?>
                                            <a href="<?php echo htmlspecialchars($t['payment_url']); ?>" 
                                               target="_blank" 
                                               class="btn btn-primary"
                                               title="Abrir link de pagamento">
                                                🔗 Link
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($t['boleto_url']): ?>
                                            <a href="<?php echo htmlspecialchars($t['boleto_url']); ?>" 
                                               target="_blank" 
                                               class="btn btn-success"
                                               title="Visualizar boleto">
                                                🎫 Boleto
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button onclick="verDetalhes(<?php echo $t['id']; ?>)" 
                                                class="btn btn-secondary"
                                                title="Ver detalhes completos">
                                            📋 Detalhes
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Detalhes da transação (oculto por padrão) -->
                            <tr id="detalhes_<?php echo $t['id']; ?>" style="display: none;">
                                <td colspan="8" style="background: #f9fafb; padding: 1.5rem;">
                                    <h4 style="margin-bottom: 1rem;">📊 Detalhes da Transação</h4>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                                        
                                        <?php if ($t['payment_id']): ?>
                                        <div>
                                            <strong>🆔 Payment ID:</strong><br>
                                            <code style="background: #fff; padding: 0.25rem 0.5rem; border-radius: 4px; display: inline-block; margin-top: 0.25rem;">
                                                <?php echo htmlspecialchars($t['payment_id']); ?>
                                            </code>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($t['transaction_id']): ?>
                                        <div>
                                            <strong>🔢 Transaction ID:</strong><br>
                                            <code style="background: #fff; padding: 0.25rem 0.5rem; border-radius: 4px; display: inline-block; margin-top: 0.25rem;">
                                                <?php echo htmlspecialchars($t['transaction_id']); ?>
                                            </code>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div>
                                            <strong>💰 Valor:</strong><br>
                                            <span style="font-size: 1.25rem; font-weight: 600; color: #10b981;">
                                                <?php echo formatMoeda($t['valor']); ?>
                                            </span>
                                        </div>
                                        
                                        <div>
                                            <strong>💳 Método de Pagamento:</strong><br>
                                            <?php 
                                            $metodoNome = [
                                                'credit_card' => 'Cartão de Crédito',
                                                'debit_card' => 'Cartão de Débito',
                                                'bolbancario' => 'Boleto Bancário',
                                                'pix' => 'PIX',
                                                'account_money' => 'Saldo Mercado Pago'
                                            ];
                                            echo $metodoNome[$t['metodo_pagamento']] ?? ucfirst(str_replace('_', ' ', $t['metodo_pagamento']));
                                            ?>
                                        </div>
                                        
                                        <?php if ($t['data_vencimento']): ?>
                                        <div>
                                            <strong>📅 Vencimento:</strong><br>
                                            <?php echo formatData($t['data_vencimento']); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($t['data_atualizacao']): ?>
                                        <div>
                                            <strong>🔄 Última Atualização:</strong><br>
                                            <?php echo date('d/m/Y H:i:s', strtotime($t['data_atualizacao'])); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($t['linha_digitavel']): ?>
                                        <div style="grid-column: 1 / -1;">
                                            <strong>🎫 Linha Digitável do Boleto:</strong><br>
                                            <code style="background: #fff; padding: 0.5rem; border-radius: 4px; display: block; margin-top: 0.25rem; font-size: 0.875rem;">
                                                <?php echo htmlspecialchars($t['linha_digitavel']); ?>
                                            </code>
                                            <button onclick="copiarLinhaDigitavel('<?php echo htmlspecialchars($t['linha_digitavel']); ?>')" 
                                                    class="btn btn-primary" 
                                                    style="margin-top: 0.5rem;">
                                                📋 Copiar Linha Digitável
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($t['payment_url']): ?>
                                        <div style="grid-column: 1 / -1;">
                                            <strong>🔗 Link de Pagamento:</strong><br>
                                            <a href="<?php echo htmlspecialchars($t['payment_url']); ?>" 
                                               target="_blank" 
                                               style="color: #3b82f6; text-decoration: underline; word-break: break-all;">
                                                <?php echo htmlspecialchars($t['payment_url']); ?>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($t['response_json']): ?>
                                        <div style="grid-column: 1 / -1;">
                                            <strong>📄 Resposta da API (JSON):</strong><br>
                                            <details style="margin-top: 0.5rem;">
                                                <summary style="cursor: pointer; color: #3b82f6;">Clique para expandir</summary>
                                                <pre style="background: #fff; padding: 1rem; border-radius: 4px; overflow-x: auto; margin-top: 0.5rem; font-size: 0.75rem; max-height: 300px; overflow-y: auto;"><?php echo htmlspecialchars(json_encode(json_decode($t['response_json']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                                            </details>
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
function verDetalhes(id) {
    const detalhes = document.getElementById('detalhes_' + id);
    if (detalhes.style.display === 'none') {
        detalhes.style.display = 'table-row';
    } else {
        detalhes.style.display = 'none';
    }
}

function copiarLinhaDigitavel(linha) {
    navigator.clipboard.writeText(linha).then(function() {
        alert('✅ Linha digitável copiada para a área de transferência!');
    }, function(err) {
        alert('❌ Erro ao copiar: ' + err);
    });
}
</script>

<?php include 'footer.php'; ?>
