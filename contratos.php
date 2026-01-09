<?php
require_once 'config.php';

$pageTitle = 'Contratos - Produtos/Serviços';

// Processar filtros
$filtroStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$filtroTipo = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : '';
$filtroCliente = isset($_GET['cliente']) ? (int)$_GET['cliente'] : 0;

// Buscar contratos
$conn = getConnection();
$sql = "SELECT ct.*, c.nome, c.razao_social, c.nome_fantasia, c.tipo_pessoa
        FROM contratos ct
        INNER JOIN clientes c ON ct.cliente_id = c.id
        WHERE 1=1";
$params = [];

if (!empty($filtroStatus)) {
    $sql .= " AND ct.status = ?";
    $params[] = $filtroStatus;
}

if (!empty($filtroTipo)) {
    $sql .= " AND ct.tipo = ?";
    $params[] = $filtroTipo;
}

if ($filtroCliente > 0) {
    $sql .= " AND ct.cliente_id = ?";
    $params[] = $filtroCliente;
}

$sql .= " ORDER BY ct.data_cadastro DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$contratos = $stmt->fetchAll();

// Buscar clientes para o filtro
$stmtClientes = $conn->query("SELECT id, nome, razao_social, nome_fantasia, tipo_pessoa FROM clientes ORDER BY razao_social, nome");
$clientes = $stmtClientes->fetchAll();

// Calcular totais
$totalAtivos = 0;
$valorTotalAtivos = 0;

foreach ($contratos as $contrato) {
    if ($contrato['status'] == 'ativo') {
        $totalAtivos++;
        $valorTotalAtivos += $contrato['valor_total'];
    }
}

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Contratos - Produtos e Serviços</h2>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Contratos Ativos</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo $totalAtivos; ?></p>
            </div>
            <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Valor Total Ativo</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo formatMoeda($valorTotalAtivos); ?></p>
            </div>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div style="flex: 1; min-width: 250px;">
                <form method="GET" style="display: flex; gap: 0.5rem;">
                    <select name="status" style="width: auto;">
                        <option value="">Todos os Status</option>
                        <option value="ativo" <?php echo $filtroStatus == 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inativo" <?php echo $filtroStatus == 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                    <select name="tipo" style="width: auto;">
                        <option value="">Todos os Tipos</option>
                        <option value="produto" <?php echo $filtroTipo == 'produto' ? 'selected' : ''; ?>>Produto</option>
                        <option value="servico" <?php echo $filtroTipo == 'servico' ? 'selected' : ''; ?>>Serviço</option>
                    </select>
                    <select name="cliente" style="width: auto;">
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
            <a href="contrato_form.php" class="btn btn-success">+ Novo Contrato</a>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Valor Total</th>
                        <th>Forma Pagamento</th>
                        <th>Parcelas</th>
                        <th>Status</th>
                        <th>Período</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contratos)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem;">
                                Nenhum contrato encontrado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($contratos as $contrato): ?>
                            <tr>
                                <td>
                                    <?php 
                                    echo $contrato['tipo_pessoa'] == 'CNPJ' 
                                        ? ($contrato['razao_social'] ?: $contrato['nome_fantasia']) 
                                        : $contrato['nome']; 
                                    ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $contrato['tipo'] == 'produto' ? 'cliente' : 'lead'; ?>">
                                        <?php echo ucfirst($contrato['tipo']); ?>
                                    </span>
                                </td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars(substr($contrato['descricao'], 0, 100)); ?>
                                    <?php if (strlen($contrato['descricao']) > 100) echo '...'; ?>
                                </td>
                                <td style="font-weight: 600;"><?php echo formatMoeda($contrato['valor_total']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $contrato['forma_pagamento'])); ?></td>
                                <td><?php echo $contrato['recorrencia']; ?>x</td>
                                <td>
                                    <span class="badge badge-<?php echo $contrato['status'] == 'ativo' ? 'cliente' : 'cancelado'; ?>">
                                        <?php echo ucfirst($contrato['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($contrato['data_inicio']): ?>
                                        <?php echo formatData($contrato['data_inicio']); ?>
                                        <?php if ($contrato['data_fim']): ?>
                                            <br><small>até <?php echo formatData($contrato['data_fim']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="contrato_cmv.php?id=<?php echo $contrato['id']; ?>" class="btn btn-primary">CMV</a>
                                        <a href="contrato_form.php?id=<?php echo $contrato['id']; ?>" class="btn btn-primary">Editar</a>
                                        <a href="contrato_delete.php?id=<?php echo $contrato['id']; ?>" 
                                           class="btn btn-danger" 
                                           onclick="return confirmarExclusao('Tem certeza que deseja excluir este contrato?')">
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
