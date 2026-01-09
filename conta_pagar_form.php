<?php
require_once 'config.php';

$pageTitle = 'Cadastro de Conta a Pagar';
$conn = getConnection();

// Verificar se é edição
$contaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$conta = null;

if ($contaId > 0) {
    $stmt = $conn->prepare("SELECT * FROM contas_pagar WHERE id = ?");
    $stmt->execute([$contaId]);
    $conta = $stmt->fetch();
    
    if (!$conta) {
        header('Location: contas_pagar.php');
        exit;
    }
}

// Buscar planos de contas de despesa
$stmtPlanos = $conn->query("SELECT id, nome FROM plano_contas WHERE tipo = 'DESPESA' AND ativo = 1 ORDER BY nome");
$planosContas = $stmtPlanos->fetchAll();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fornecedor = sanitize($_POST['fornecedor']);
    $planoContasId = (int)$_POST['plano_contas_id'];
    $descricao = sanitize($_POST['descricao']);
    $valor = (float)str_replace(',', '.', str_replace('.', '', $_POST['valor']));
    $dataVencimento = $_POST['data_vencimento'];
    $formaPagamento = sanitize($_POST['forma_pagamento']);
    $recorrencia = (int)$_POST['recorrencia'];
    $observacoes = sanitize($_POST['observacoes'] ?? '');
    
    try {
        if ($contaId > 0) {
            // Atualizar
            $sql = "UPDATE contas_pagar SET 
                    fornecedor = ?, plano_contas_id = ?, descricao = ?, valor = ?,
                    data_vencimento = ?, forma_pagamento = ?, recorrencia = ?, observacoes = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $fornecedor, $planoContasId, $descricao, $valor,
                $dataVencimento, $formaPagamento, $recorrencia, $observacoes, $contaId
            ]);
            $mensagem = "Conta a pagar atualizada com sucesso!";
        } else {
            // Inserir com recorrência
            for ($i = 0; $i < $recorrencia; $i++) {
                $dataVencimentoParcela = date('Y-m-d', strtotime($dataVencimento . " +$i month"));
                
                $sql = "INSERT INTO contas_pagar (
                        fornecedor, plano_contas_id, descricao, valor, data_vencimento,
                        forma_pagamento, recorrencia, parcela_atual, observacoes
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $fornecedor, $planoContasId, $descricao, $valor, $dataVencimentoParcela,
                    $formaPagamento, $recorrencia, ($i + 1), $observacoes
                ]);
            }
            $mensagem = "Conta(s) a pagar cadastrada(s) com sucesso!";
        }
        
        header('Location: contas_pagar.php?msg=' . urlencode($mensagem));
        exit;
        
    } catch (PDOException $e) {
        $erro = "Erro ao salvar conta a pagar: " . $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2><?php echo $contaId > 0 ? 'Editar Conta a Pagar' : 'Nova Conta a Pagar'; ?></h2>
        </div>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error"><?php echo $erro; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Fornecedor *</label>
                    <input type="text" name="fornecedor" required 
                           value="<?php echo $conta ? htmlspecialchars($conta['fornecedor']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Plano de Contas *</label>
                    <select name="plano_contas_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($planosContas as $plano): ?>
                            <option value="<?php echo $plano['id']; ?>" 
                                    <?php echo ($conta && $conta['plano_contas_id'] == $plano['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($plano['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Descrição *</label>
                <input type="text" name="descricao" required 
                       value="<?php echo $conta ? htmlspecialchars($conta['descricao']) : ''; ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Valor (R$) *</label>
                    <input type="text" name="valor" id="valor" required 
                           value="<?php echo $conta ? number_format($conta['valor'], 2, ',', '.') : ''; ?>"
                           onkeyup="this.value = formatMoeda(this.value)">
                </div>
                
                <div class="form-group">
                    <label>Data de Vencimento *</label>
                    <input type="date" name="data_vencimento" required 
                           value="<?php echo $conta ? $conta['data_vencimento'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Forma de Pagamento *</label>
                    <select name="forma_pagamento" required>
                        <option value="">Selecione...</option>
                        <option value="boleto" <?php echo ($conta && $conta['forma_pagamento'] == 'boleto') ? 'selected' : ''; ?>>Boleto</option>
                        <option value="cartao_credito" <?php echo ($conta && $conta['forma_pagamento'] == 'cartao_credito') ? 'selected' : ''; ?>>Cartão de Crédito</option>
                        <option value="cartao_debito" <?php echo ($conta && $conta['forma_pagamento'] == 'cartao_debito') ? 'selected' : ''; ?>>Cartão de Débito</option>
                        <option value="pix" <?php echo ($conta && $conta['forma_pagamento'] == 'pix') ? 'selected' : ''; ?>>PIX</option>
                        <option value="dinheiro" <?php echo ($conta && $conta['forma_pagamento'] == 'dinheiro') ? 'selected' : ''; ?>>Dinheiro</option>
                        <option value="transferencia" <?php echo ($conta && $conta['forma_pagamento'] == 'transferencia') ? 'selected' : ''; ?>>Transferência</option>
                    </select>
                </div>
            </div>
            
            <?php if (!$conta): ?>
            <div class="form-group">
                <label>Recorrência (Número de Parcelas) *</label>
                <input type="number" name="recorrencia" min="1" max="120" value="1" required>
                <small style="color: #6b7280;">Informe quantas vezes esta conta se repetirá. Para pagamento único, deixe 1.</small>
            </div>
            <?php else: ?>
            <input type="hidden" name="recorrencia" value="<?php echo $conta['recorrencia']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Observações</label>
                <textarea name="observacoes" rows="4"><?php echo $conta ? htmlspecialchars($conta['observacoes']) : ''; ?></textarea>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-success">Salvar</button>
                <a href="contas_pagar.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
