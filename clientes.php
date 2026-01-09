<?php
/**
 * clientes.php - VERSÃO CORRIGIDA V2
 * 
 * Correções aplicadas:
 * 1. PDO::FETCH_ASSOC no fetchAll()
 * 2. Verificação de dados antes de renderizar
 * 3. Debug mode opcional
 * 4. Tratamento de erros robusto
 */

// Debug mode (remover em produção)
$debug = isset($_GET['debug']) ? true : false;
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

require_once 'config.php';

$pageTitle = 'Clientes';

// Processar busca
$busca = isset($_GET['busca']) ? sanitize($_GET['busca']) : '';
$filtroTipo = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : '';

// Buscar clientes
try {
    $conn = getConnection();
    $sql = "SELECT * FROM clientes WHERE 1=1";
    $params = [];

    if (!empty($busca)) {
        $sql .= " AND (nome LIKE ? OR razao_social LIKE ? OR nome_fantasia LIKE ? OR cnpj_cpf LIKE ? OR email LIKE ?)";
        $buscaParam = "%$busca%";
        $params = array_fill(0, 5, $buscaParam);
    }

    if (!empty($filtroTipo)) {
        $sql .= " AND tipo_cliente = ?";
        $params[] = $filtroTipo;
    }

    $sql .= " ORDER BY data_cadastro DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    // ✅ CORREÇÃO PRINCIPAL: Adicionar PDO::FETCH_ASSOC
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($debug) {
        echo "<!-- DEBUG: Total de clientes: " . count($clientes) . " -->";
        if (count($clientes) > 0) {
            echo "<!-- DEBUG: Primeiro cliente: " . print_r($clientes[0], true) . " -->";
        }
    }
    
} catch (Exception $e) {
    $clientes = [];
    $erro = "Erro ao buscar clientes: " . $e->getMessage();
    if ($debug) {
        echo "<!-- DEBUG ERROR: {$erro} -->";
    }
}

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Gerenciamento de Clientes</h2>
        </div>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>
        
        <div class="search-filter-bar">
            <div class="search-box">
                <form method="GET" style="display: flex; gap: 0.5rem;">
                    <input type="text" 
                           name="busca" 
                           placeholder="Buscar por nome, CNPJ/CPF, e-mail..." 
                           value="<?php echo htmlspecialchars($busca); ?>" 
                           style="flex: 1;">
                    <select name="tipo" style="width: auto;">
                        <option value="">Todos</option>
                        <option value="LEAD" <?php echo $filtroTipo == 'LEAD' ? 'selected' : ''; ?>>Leads</option>
                        <option value="CLIENTE" <?php echo $filtroTipo == 'CLIENTE' ? 'selected' : ''; ?>>Clientes</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </form>
            </div>
            <a href="cliente_form.php" class="btn btn-success">+ Novo Cliente</a>
        </div>
        
        <?php if ($debug): ?>
            <div class="alert alert-info">
                <strong>DEBUG MODE:</strong> Total de registros: <?php echo count($clientes); ?>
            </div>
        <?php endif; ?>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>CNPJ/CPF</th>
                        <th>Nome/Razão Social</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th>Cidade/UF</th>
                        <th>Data Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem;">
                                <?php if (!empty($busca) || !empty($filtroTipo)): ?>
                                    Nenhum cliente encontrado com os filtros aplicados.
                                    <br><br>
                                    <a href="clientes.php" class="btn btn-secondary">Limpar Filtros</a>
                                <?php else: ?>
                                    Nenhum cliente cadastrado ainda.
                                    <br><br>
                                    <a href="cliente_form.php" class="btn btn-success">Cadastrar Primeiro Cliente</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($cliente['tipo_cliente']); ?>">
                                        <?php echo htmlspecialchars($cliente['tipo_cliente']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if (isset($cliente['tipo_pessoa']) && isset($cliente['cnpj_cpf'])) {
                                        echo $cliente['tipo_pessoa'] == 'CNPJ' 
                                            ? formatCNPJ($cliente['cnpj_cpf']) 
                                            : formatCPF($cliente['cnpj_cpf']); 
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if (isset($cliente['tipo_pessoa'])) {
                                        if ($cliente['tipo_pessoa'] == 'CNPJ') {
                                            echo htmlspecialchars($cliente['razao_social'] ?: $cliente['nome_fantasia'] ?: '-');
                                        } else {
                                            echo htmlspecialchars($cliente['nome'] ?: '-');
                                        }
                                    } else {
                                        echo htmlspecialchars($cliente['nome'] ?: $cliente['razao_social'] ?: '-');
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($cliente['email'] ?? '-'); ?></td>
                                <td>
                                    <?php 
                                    $telefone = $cliente['celular'] ?? $cliente['telefone'] ?? '';
                                    echo $telefone ? formatTelefone($telefone) : '-';
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $cidade = $cliente['cidade'] ?? '';
                                    $estado = $cliente['estado'] ?? '';
                                    if ($cidade && $estado) {
                                        echo htmlspecialchars($cidade) . '/' . htmlspecialchars($estado);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    echo isset($cliente['data_cadastro']) 
                                        ? formatData($cliente['data_cadastro']) 
                                        : '-';
                                    ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="cliente_form.php?id=<?php echo $cliente['id']; ?>" 
                                           class="btn btn-primary">
                                            Editar
                                        </a>
                                        <a href="cliente_delete.php?id=<?php echo $cliente['id']; ?>" 
                                           class="btn btn-danger" 
                                           onclick="return confirm('Tem certeza que deseja excluir este cliente?')">
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
        
        <?php if (!empty($clientes)): ?>
            <div style="margin-top: 1rem; color: #6b7280;">
                Total de registros: <strong><?php echo count($clientes); ?></strong>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
