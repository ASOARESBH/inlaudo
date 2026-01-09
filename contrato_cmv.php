<?php
require_once 'config.php';

$pageTitle = 'CMV - Custo de Mercadoria Vendida';
$conn = getConnection();

// Verificar se o contrato existe
$contratoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($contratoId == 0) {
    header('Location: contratos.php');
    exit;
}

$stmt = $conn->prepare("SELECT ct.*, c.nome, c.razao_social, c.nome_fantasia, c.tipo_pessoa 
                        FROM contratos ct
                        INNER JOIN clientes c ON ct.cliente_id = c.id
                        WHERE ct.id = ?");
$stmt->execute([$contratoId]);
$contrato = $stmt->fetch();

if (!$contrato) {
    header('Location: contratos.php');
    exit;
}

// Buscar custos do contrato
$stmtCustos = $conn->prepare("SELECT * FROM cmv WHERE contrato_id = ? ORDER BY data_cadastro DESC");
$stmtCustos->execute([$contratoId]);
$custos = $stmtCustos->fetchAll();

// Calcular totais
$totalCustos = 0;
$totalCustosRecorrentes = 0;

foreach ($custos as $custo) {
    $totalCustos += $custo['valor_total'];
    if ($custo['recorrente']) {
        $totalCustosRecorrentes += $custo['valor_total'];
    }
}

$valorBruto = $contrato['valor_total'];
$valorLiquido = $valorBruto - $totalCustos;
$margemLiquida = $valorBruto > 0 ? (($valorLiquido / $valorBruto) * 100) : 0;

// Processar adição de custo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'adicionar') {
    $descricao = sanitize($_POST['descricao']);
    $valorUnitario = (float)str_replace(',', '.', str_replace('.', '', $_POST['valor_unitario']));
    $quantidade = (float)str_replace(',', '.', $_POST['quantidade']);
    $valorTotal = $valorUnitario * $quantidade;
    $recorrente = isset($_POST['recorrente']) ? 1 : 0;
    $observacoes = sanitize($_POST['observacoes'] ?? '');
    
    try {
        $sql = "INSERT INTO cmv (contrato_id, descricao, valor_unitario, quantidade, valor_total, recorrente, observacoes)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$contratoId, $descricao, $valorUnitario, $quantidade, $valorTotal, $recorrente, $observacoes]);
        
        header("Location: contrato_cmv.php?id=$contratoId&msg=" . urlencode('Custo adicionado com sucesso!'));
        exit;
        
    } catch (PDOException $e) {
        $erro = "Erro ao adicionar custo: " . $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container">
    <!-- Informações do Contrato -->
    <div class="card">
        <div class="card-header">
            <h2>CMV - Custo de Mercadoria Vendida</h2>
        </div>
        
        <div style="background: #f9fafb; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <h3 style="margin-bottom: 1rem; color: #1e40af;">Informações do Contrato</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <strong>Cliente:</strong><br>
                    <?php 
                    echo $contrato['tipo_pessoa'] == 'CNPJ' 
                        ? ($contrato['razao_social'] ?: $contrato['nome_fantasia']) 
                        : $contrato['nome']; 
                    ?>
                </div>
                <div>
                    <strong>Tipo:</strong><br>
                    <?php echo ucfirst($contrato['tipo']); ?>
                </div>
                <div>
                    <strong>Descrição:</strong><br>
                    <?php echo htmlspecialchars(substr($contrato['descricao'], 0, 50)); ?>
                    <?php if (strlen($contrato['descricao']) > 50) echo '...'; ?>
                </div>
            </div>
        </div>
        
        <!-- Resumo Financeiro -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1.5rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Valor Bruto do Contrato</p>
                <p style="font-size: 1.8rem; font-weight: 600;"><?php echo formatMoeda($valorBruto); ?></p>
            </div>
            
            <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 1.5rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Total de Custos</p>
                <p style="font-size: 1.8rem; font-weight: 600;"><?php echo formatMoeda($totalCustos); ?></p>
                <small style="opacity: 0.9;">Recorrentes: <?php echo formatMoeda($totalCustosRecorrentes); ?></small>
            </div>
            
            <div style="background: linear-gradient(135deg, <?php echo $valorLiquido >= 0 ? '#10b981, #059669' : '#ef4444, #dc2626'; ?>); color: white; padding: 1.5rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Valor Líquido</p>
                <p style="font-size: 1.8rem; font-weight: 600;"><?php echo formatMoeda($valorLiquido); ?></p>
                <small style="opacity: 0.9;">Margem: <?php echo number_format($margemLiquida, 2, ',', '.'); ?>%</small>
            </div>
        </div>
        
        <div style="text-align: right; margin-bottom: 1rem;">
            <a href="contratos.php" class="btn btn-secondary">Voltar para Contratos</a>
        </div>
    </div>
    
    <!-- Formulário para Adicionar Custo -->
    <div class="card">
        <div class="card-header">
            <h2>Adicionar Novo Custo</h2>
        </div>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error"><?php echo $erro; ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="acao" value="adicionar">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Descrição do Custo *</label>
                    <input type="text" name="descricao" required placeholder="Ex: Mão de obra, Material, Transporte">
                </div>
                
                <div class="form-group">
                    <label>Valor Unitário (R$) *</label>
                    <input type="text" name="valor_unitario" id="valor_unitario" required 
                           onkeyup="calcularTotal()">
                </div>
                
                <div class="form-group">
                    <label>Quantidade *</label>
                    <input type="text" name="quantidade" id="quantidade" value="1" required 
                           onkeyup="calcularTotal()">
                </div>
                
                <div class="form-group">
                    <label>Valor Total (R$)</label>
                    <input type="text" id="valor_total_display" readonly style="background: #f3f4f6;">
                </div>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="recorrente" value="1">
                    <span>Este é um custo recorrente (se repete durante o contrato)</span>
                </label>
            </div>
            
            <div class="form-group">
                <label>Observações</label>
                <textarea name="observacoes" rows="3"></textarea>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-success">Adicionar Custo</button>
            </div>
        </form>
    </div>
    
    <!-- Lista de Custos -->
    <div class="card">
        <div class="card-header">
            <h2>Custos Cadastrados</h2>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Valor Unitário</th>
                        <th>Quantidade</th>
                        <th>Valor Total</th>
                        <th>Recorrente</th>
                        <th>Data Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($custos)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem;">
                                Nenhum custo cadastrado para este contrato.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($custos as $custo): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($custo['descricao']); ?>
                                    <?php if ($custo['observacoes']): ?>
                                        <br><small style="color: #6b7280;"><?php echo htmlspecialchars($custo['observacoes']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatMoeda($custo['valor_unitario']); ?></td>
                                <td><?php echo number_format($custo['quantidade'], 2, ',', '.'); ?></td>
                                <td style="font-weight: 600;"><?php echo formatMoeda($custo['valor_total']); ?></td>
                                <td>
                                    <?php if ($custo['recorrente']): ?>
                                        <span class="badge badge-cliente">Sim</span>
                                    <?php else: ?>
                                        <span class="badge badge-cancelado">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatData($custo['data_cadastro']); ?></td>
                                <td>
                                    <a href="cmv_delete.php?id=<?php echo $custo['id']; ?>&contrato=<?php echo $contratoId; ?>" 
                                       class="btn btn-danger" 
                                       onclick="return confirmarExclusao('Tem certeza que deseja excluir este custo?')">
                                        Excluir
                                    </a>
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
    function calcularTotal() {
        const valorUnitario = document.getElementById('valor_unitario').value.replace(/\D/g, '') / 100;
        const quantidade = parseFloat(document.getElementById('quantidade').value.replace(',', '.')) || 1;
        const total = valorUnitario * quantidade;
        
        document.getElementById('valor_total_display').value = formatMoeda((total * 100).toString());
        
        // Formatar valor unitário
        document.getElementById('valor_unitario').value = formatMoeda(document.getElementById('valor_unitario').value);
    }
</script>

<?php include 'footer.php'; ?>
