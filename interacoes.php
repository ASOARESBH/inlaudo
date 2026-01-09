<?php
require_once 'config.php';

$pageTitle = 'Interações';

// Processar busca
$busca = isset($_GET['busca']) ? sanitize($_GET['busca']) : '';
$filtroCliente = isset($_GET['cliente']) ? (int)$_GET['cliente'] : 0;

// Buscar interações
$conn = getConnection();
$sql = "SELECT i.*, c.nome, c.razao_social, c.nome_fantasia, c.tipo_pessoa 
        FROM interacoes i 
        INNER JOIN clientes c ON i.cliente_id = c.id 
        WHERE 1=1";
$params = [];

if (!empty($busca)) {
    $sql .= " AND (c.nome LIKE ? OR c.razao_social LIKE ? OR c.nome_fantasia LIKE ? OR i.historico LIKE ?)";
    $buscaParam = "%$busca%";
    $params = array_fill(0, 4, $buscaParam);
}

if ($filtroCliente > 0) {
    $sql .= " AND i.cliente_id = ?";
    $params[] = $filtroCliente;
}

$sql .= " ORDER BY i.data_hora_interacao DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$interacoes = $stmt->fetchAll();

// Buscar clientes para o filtro
$stmtClientes = $conn->query("SELECT id, nome, razao_social, nome_fantasia, tipo_pessoa FROM clientes ORDER BY razao_social, nome");
$clientes = $stmtClientes->fetchAll();

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Gerenciamento de Interações</h2>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div class="search-box" style="flex: 1; min-width: 250px;">
                <form method="GET" style="display: flex; gap: 0.5rem;">
                    <input type="text" name="busca" placeholder="Buscar por cliente ou histórico..." value="<?php echo htmlspecialchars($busca); ?>">
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
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </form>
            </div>
            <a href="interacao_form.php" class="btn btn-success">+ Nova Interação</a>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Data/Hora Interação</th>
                        <th>Forma de Contato</th>
                        <th>Histórico</th>
                        <th>Próximo Contato</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($interacoes)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem;">
                                Nenhuma interação encontrada.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($interacoes as $interacao): ?>
                            <tr>
                                <td>
                                    <?php 
                                    echo $interacao['tipo_pessoa'] == 'CNPJ' 
                                        ? ($interacao['razao_social'] ?: $interacao['nome_fantasia']) 
                                        : $interacao['nome']; 
                                    ?>
                                </td>
                                <td><?php echo formatDataHora($interacao['data_hora_interacao']); ?></td>
                                <td>
                                    <span class="badge badge-cliente">
                                        <?php echo ucfirst($interacao['forma_contato']); ?>
                                    </span>
                                </td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars(substr($interacao['historico'], 0, 100)); ?>
                                    <?php if (strlen($interacao['historico']) > 100) echo '...'; ?>
                                </td>
                                <td>
                                    <?php if ($interacao['proximo_contato_data']): ?>
                                        <?php echo formatDataHora($interacao['proximo_contato_data']); ?><br>
                                        <small><?php echo ucfirst($interacao['proximo_contato_forma']); ?></small>
                                    <?php else: ?>
                                        <span style="color: #999;">Não agendado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="interacao_form.php?id=<?php echo $interacao['id']; ?>" class="btn btn-primary">Editar</a>
                                        <a href="interacao_delete.php?id=<?php echo $interacao['id']; ?>" 
                                           class="btn btn-danger" 
                                           onclick="return confirmarExclusao('Tem certeza que deseja excluir esta interação?')">
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
