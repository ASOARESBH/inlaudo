<?php
require_once 'config.php';

$pageTitle = 'Cadastro de Contrato';
$conn = getConnection();

// Verificar se é edição
$contratoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$contrato = null;

if ($contratoId > 0) {
    $stmt = $conn->prepare("SELECT * FROM contratos WHERE id = ?");
    $stmt->execute([$contratoId]);
    $contrato = $stmt->fetch();
    
    if (!$contrato) {
        header('Location: contratos.php');
        exit;
    }
}

// Buscar clientes
$stmtClientes = $conn->query("SELECT id, nome, razao_social, nome_fantasia, tipo_pessoa FROM clientes WHERE tipo_cliente = 'CLIENTE' ORDER BY razao_social, nome");
$clientes = $stmtClientes->fetchAll();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $clienteId = (int)$_POST['cliente_id'];
    $tipo = sanitize($_POST['tipo']);
    $descricao = sanitize($_POST['descricao']);
    $valorTotal = (float)str_replace(',', '.', str_replace('.', '', $_POST['valor_total']));
    $formaPagamento = sanitize($_POST['forma_pagamento']);
    $gatewayPagamento = sanitize($_POST['gateway_pagamento'] ?? 'cora');
    $recorrencia = (int)$_POST['recorrencia'];
    $status = sanitize($_POST['status']);
    $dataInicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
    $dataFim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
    $observacoes = sanitize($_POST['observacoes'] ?? '');
    $gerarContasReceber = isset($_POST['gerar_contas_receber']) ? 1 : 0;
    
    // Upload de arquivo
    $arquivoContrato = $contrato ? $contrato['arquivo_contrato'] : null;
    
    if (isset($_FILES['arquivo_contrato']) && $_FILES['arquivo_contrato']['error'] == 0) {
        $arquivo = $_FILES['arquivo_contrato'];
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        $extensoesPermitidas = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        
        if (in_array($extensao, $extensoesPermitidas)) {
            $nomeArquivo = 'contrato_' . time() . '_' . uniqid() . '.' . $extensao;
            $caminhoDestino = 'uploads/contratos/' . $nomeArquivo;
            
            if (move_uploaded_file($arquivo['tmp_name'], $caminhoDestino)) {
                // Remover arquivo antigo se existir
                if ($contrato && $contrato['arquivo_contrato'] && file_exists($contrato['arquivo_contrato'])) {
                    unlink($contrato['arquivo_contrato']);
                }
                $arquivoContrato = $caminhoDestino;
            }
        }
    }
    
    try {
        if ($contratoId > 0) {
            // Atualizar
            $sql = "UPDATE contratos SET 
                    cliente_id = ?, tipo = ?, descricao = ?, valor_total = ?,
                    forma_pagamento = ?, gateway_pagamento = ?, recorrencia = ?, status = ?, data_inicio = ?,
                    data_fim = ?, observacoes = ?, arquivo_contrato = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $clienteId, $tipo, $descricao, $valorTotal, $formaPagamento, $gatewayPagamento,
                $recorrencia, $status, $dataInicio, $dataFim, $observacoes,
                $arquivoContrato, $contratoId
            ]);
            $mensagem = "Contrato atualizado com sucesso!";
        } else {
            // Inserir
            $sql = "INSERT INTO contratos (
                    cliente_id, tipo, descricao, valor_total, forma_pagamento, gateway_pagamento,
                    recorrencia, status, data_inicio, data_fim, observacoes, arquivo_contrato
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $clienteId, $tipo, $descricao, $valorTotal, $formaPagamento, $gatewayPagamento,
                $recorrencia, $status, $dataInicio, $dataFim, $observacoes, $arquivoContrato
            ]);
            
            $contratoId = $conn->lastInsertId();
            $mensagem = "Contrato cadastrado com sucesso!";
            
            // Gerar contas a receber se solicitado
            if ($gerarContasReceber && $recorrencia > 0) {
                $valorParcela = $valorTotal / $recorrencia;
                $dataVencimento = $dataInicio ?: date('Y-m-d');
                
                // Buscar plano de contas padrão
                $stmtPlano = $conn->query("SELECT id FROM plano_contas WHERE tipo = 'RECEITA' LIMIT 1");
                $planoContas = $stmtPlano->fetch();
                
                if ($planoContas) {
                    for ($i = 0; $i < $recorrencia; $i++) {
                        $dataVencimentoParcela = date('Y-m-d', strtotime($dataVencimento . " +$i month"));
                        
                        $sqlConta = "INSERT INTO contas_receber (
                                cliente_id, plano_contas_id, descricao, valor, data_vencimento,
                                forma_pagamento, recorrencia, parcela_atual, observacoes
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmtConta = $conn->prepare($sqlConta);
                        $stmtConta->execute([
                            $clienteId, $planoContas['id'], 
                            "Contrato #$contratoId - " . substr($descricao, 0, 50),
                            $valorParcela, $dataVencimentoParcela, $formaPagamento,
                            $recorrencia, ($i + 1), "Gerado automaticamente do contrato #$contratoId"
                        ]);
                    }
                }
            }
        }
        
        header('Location: contratos.php?msg=' . urlencode($mensagem));
        exit;
        
    } catch (PDOException $e) {
        $erro = "Erro ao salvar contrato: " . $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2><?php echo $contratoId > 0 ? 'Editar Contrato' : 'Novo Contrato'; ?></h2>
        </div>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error"><?php echo $erro; ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label>Cliente *</label>
                    <select name="cliente_id" required>
                        <option value="">Selecione o cliente...</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" 
                                    <?php echo ($contrato && $contrato['cliente_id'] == $c['id']) ? 'selected' : ''; ?>>
                                <?php 
                                echo $c['tipo_pessoa'] == 'CNPJ' 
                                    ? ($c['razao_social'] ?: $c['nome_fantasia']) 
                                    : $c['nome']; 
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="tipo" required>
                        <option value="">Selecione...</option>
                        <option value="produto" <?php echo ($contrato && $contrato['tipo'] == 'produto') ? 'selected' : ''; ?>>Produto</option>
                        <option value="servico" <?php echo ($contrato && $contrato['tipo'] == 'servico') ? 'selected' : ''; ?>>Serviço</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" required>
                        <option value="ativo" <?php echo (!$contrato || $contrato['status'] == 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inativo" <?php echo ($contrato && $contrato['status'] == 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Descrição do Produto/Serviço *</label>
                <textarea name="descricao" required rows="4"><?php echo $contrato ? htmlspecialchars($contrato['descricao']) : ''; ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Valor Total (R$) *</label>
                    <input type="text" name="valor_total" id="valor_total" required 
                           value="<?php echo $contrato ? number_format($contrato['valor_total'], 2, ',', '.') : ''; ?>"
                           onkeyup="this.value = formatMoeda(this.value)">
                </div>
                
                <div class="form-group">
                    <label>Forma de Pagamento *</label>
                    <select name="forma_pagamento" id="forma_pagamento" required onchange="atualizarGateway()">
                        <option value="">Selecione...</option>
                        <option value="boleto" <?php echo ($contrato && $contrato['forma_pagamento'] == 'boleto') ? 'selected' : ''; ?>>Boleto</option>
                        <option value="cartao_credito" <?php echo ($contrato && $contrato['forma_pagamento'] == 'cartao_credito') ? 'selected' : ''; ?>>Cartão de Crédito</option>
                        <option value="cartao_debito" <?php echo ($contrato && $contrato['forma_pagamento'] == 'cartao_debito') ? 'selected' : ''; ?>>Cartão de Débito</option>
                        <option value="pix" <?php echo ($contrato && $contrato['forma_pagamento'] == 'pix') ? 'selected' : ''; ?>>PIX</option>
                        <option value="dinheiro" <?php echo ($contrato && $contrato['forma_pagamento'] == 'dinheiro') ? 'selected' : ''; ?>>Dinheiro</option>
                        <option value="transferencia" <?php echo ($contrato && $contrato['forma_pagamento'] == 'transferencia') ? 'selected' : ''; ?>>Transferência</option>
                    </select>
                </div>
                
                <div class="form-group" id="gateway_group" style="display: none;">
                    <label>Gateway de Pagamento *</label>
                    <select name="gateway_pagamento" id="gateway_pagamento">
                        <option value="cora" <?php echo ($contrato && $contrato['gateway_pagamento'] == 'cora') ? 'selected' : ''; ?>>CORA (Boleto)</option>
                        <option value="mercadopago" <?php echo ($contrato && $contrato['gateway_pagamento'] == 'mercadopago') ? 'selected' : ''; ?>>Mercado Pago</option>
                        <option value="stripe" <?php echo ($contrato && $contrato['gateway_pagamento'] == 'stripe') ? 'selected' : ''; ?>>Stripe</option>
                    </select>
                    <small style="display: block; margin-top: 0.5rem; color: #64748b;">
                        <span id="gateway_info">Selecione o gateway de pagamento</span>
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Recorrência (Parcelas) *</label>
                    <input type="number" name="recorrencia" min="1" max="120" 
                           value="<?php echo $contrato ? $contrato['recorrencia'] : '1'; ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Data de Início</label>
                    <input type="date" name="data_inicio" 
                           value="<?php echo $contrato ? $contrato['data_inicio'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Data de Término</label>
                    <input type="date" name="data_fim" 
                           value="<?php echo $contrato ? $contrato['data_fim'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Anexar Contrato (PDF, DOC, DOCX, JPG, PNG)</label>
                <input type="file" name="arquivo_contrato" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                <?php if ($contrato && $contrato['arquivo_contrato']): ?>
                    <small style="display: block; margin-top: 0.5rem; color: #10b981;">
                        Arquivo atual: <a href="<?php echo $contrato['arquivo_contrato']; ?>" target="_blank">Visualizar</a>
                    </small>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label>Observações</label>
                <textarea name="observacoes" rows="3"><?php echo $contrato ? htmlspecialchars($contrato['observacoes']) : ''; ?></textarea>
            </div>
            
            <?php if (!$contrato): ?>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="gerar_contas_receber" value="1" checked>
                    <span>Gerar automaticamente contas a receber com base nas parcelas</span>
                </label>
            </div>
            <?php endif; ?>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-success">Salvar</button>
                <a href="contratos.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
function atualizarGateway() {
    const formaPagamento = document.getElementById('forma_pagamento').value;
    const gatewayGroup = document.getElementById('gateway_group');
    const gatewaySelect = document.getElementById('gateway_pagamento');
    const gatewayInfo = document.getElementById('gateway_info');
    
    // Mostrar gateway apenas para boleto, cartão ou pix
    const formasComGateway = ['boleto', 'cartao_credito', 'cartao_debito', 'pix'];
    
    if (formasComGateway.includes(formaPagamento)) {
        gatewayGroup.style.display = 'block';
        
        // Configurar opções baseado na forma de pagamento
        if (formaPagamento === 'boleto') {
            gatewaySelect.value = 'cora';
            gatewayInfo.textContent = 'CORA: Gera boleto registrado automaticamente';
        } else {
            gatewaySelect.value = 'mercadopago';
            gatewayInfo.textContent = 'Mercado Pago: Aceita cartão, pix e boleto';
        }
    } else {
        gatewayGroup.style.display = 'none';
    }
}

// Executar ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    atualizarGateway();
});
</script>

<?php include 'footer.php'; ?>
