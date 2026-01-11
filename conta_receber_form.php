<?php
require_once 'config.php';

$pageTitle = 'Cadastro de Conta a Receber';
$conn = getConnection();

// Verificar se é edição
$contaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$conta = null;
$anexosExistentes = [];

if ($contaId > 0) {
    $stmt = $conn->prepare("SELECT * FROM contas_receber WHERE id = ?");
    $stmt->execute([$contaId]);
    $conta = $stmt->fetch();
    
    if (!$conta) {
        header('Location: contas_receber.php');
        exit;
    }
    
    // Buscar anexos existentes
    $stmtAnexos = $conn->prepare("SELECT * FROM contas_receber_anexos WHERE conta_receber_id = ? ORDER BY data_upload DESC");
    $stmtAnexos->execute([$contaId]);
    $anexosExistentes = $stmtAnexos->fetchAll();
}

// Buscar clientes
$stmtClientes = $conn->query("SELECT id, nome, razao_social, nome_fantasia, tipo_pessoa FROM clientes WHERE tipo_cliente = 'CLIENTE' ORDER BY razao_social, nome");
$clientes = $stmtClientes->fetchAll();

// Buscar planos de contas de receita
$stmtPlanos = $conn->query("SELECT id, nome FROM plano_contas WHERE tipo = 'RECEITA' AND ativo = 1 ORDER BY nome");
$planosContas = $stmtPlanos->fetchAll();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $clienteId = (int)$_POST['cliente_id'];
    $planoContasId = (int)$_POST['plano_contas_id'];
    $descricao = sanitize($_POST['descricao']);
    $valor = (float)str_replace(',', '.', str_replace('.', '', $_POST['valor']));
    $dataVencimento = $_POST['data_vencimento'];
    $formaPagamento = sanitize($_POST['forma_pagamento']);
    $recorrencia = (int)$_POST['recorrencia'];
    $observacoes = sanitize($_POST['observacoes'] ?? '');
    
    // Novos campos de gateways
    $gatewayId = !empty($_POST['gateway_id']) ? (int)$_POST['gateway_id'] : null;
    $gatewaysDisponiveis = !empty($_POST['gateways_disponiveis']) ? $_POST['gateways_disponiveis'] : null;
    
    // Verificar se deve gerar boleto
    $gerarBoleto = isset($_POST['gerar_boleto']) && $_POST['gerar_boleto'] == '1' && $formaPagamento == 'boleto';
    $plataformaBoleto = isset($_POST['plataforma_boleto']) ? sanitize($_POST['plataforma_boleto']) : null;
    
    // Verificar se deve gerar fatura Stripe
    $gerarFatura = isset($_POST['gerar_fatura_stripe']) && $_POST['gerar_fatura_stripe'] == '1';
    $formaPagamentoFatura = isset($_POST['forma_pagamento_fatura']) ? sanitize($_POST['forma_pagamento_fatura']) : 'boleto';
    
    try {
        if ($contaId > 0) {
            // Atualizar
            $sql = "UPDATE contas_receber SET 
                    cliente_id = ?, plano_contas_id = ?, descricao = ?, valor = ?,
                    data_vencimento = ?, forma_pagamento = ?, recorrencia = ?, observacoes = ?,
                    gateway_id = ?, gateways_disponiveis = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $clienteId, $planoContasId, $descricao, $valor,
                $dataVencimento, $formaPagamento, $recorrencia, $observacoes,
                $gatewayId, $gatewaysDisponiveis, $contaId
            ]);
            
            // Processar uploads de anexos
            if (isset($_FILES['anexos']) && !empty($_FILES['anexos']['name'][0])) {
                require_once 'processar_upload_anexos.php';
                processarUploadAnexos($contaId, $_FILES['anexos'], $conn);
            }
            
            $mensagem = "Conta a receber atualizada com sucesso!";
            header('Location: contas_receber.php?msg=' . urlencode($mensagem));
            exit;
            
        } else {
            // Inserir com recorrência
            $contasGeradas = [];
            
            for ($i = 0; $i < $recorrencia; $i++) {
                $dataVencimentoParcela = date('Y-m-d', strtotime($dataVencimento . " +$i month"));
                
                $sql = "INSERT INTO contas_receber (
                        cliente_id, plano_contas_id, descricao, valor, data_vencimento,
                        forma_pagamento, recorrencia, parcela_atual, observacoes
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $clienteId, $planoContasId, $descricao, $valor, $dataVencimentoParcela,
                    $formaPagamento, $recorrencia, ($i + 1), $observacoes
                ]);
                
                $contaReceberIdGerada = $conn->lastInsertId();
                $contasGeradas[] = $contaReceberIdGerada;
                
                // Processar uploads de anexos (apenas na primeira parcela)
                if ($i == 0 && isset($_FILES['anexos']) && !empty($_FILES['anexos']['name'][0])) {
                    require_once 'processar_upload_anexos.php';
                    processarUploadAnexos($contaReceberIdGerada, $_FILES['anexos'], $conn);
                }
                
                // Gerar boleto se solicitado
                if ($gerarBoleto && $plataformaBoleto) {
                    // Buscar dados do cliente
                    $stmtCliente = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
                    $stmtCliente->execute([$clienteId]);
                    $clienteDados = $stmtCliente->fetch();
                    
                    try {
                        if ($plataformaBoleto == 'stripe') {
                            require_once 'lib_boleto_stripe.php';
                            $boletoLib = new BoletoStripe();
                        } else {
                            // Usar nova biblioteca CORA v2 com mTLS
                            require_once 'lib_boleto_cora_v2.php';
                            
                            // Buscar configurações CORA
                            $stmtConfigCora = $conn->query("SELECT * FROM integracoes WHERE tipo = 'cora' AND ativo = 1");
                            $configCora = $stmtConfigCora->fetch();
                            
                            if (!$configCora) {
                                throw new Exception('Integração CORA não está configurada ou ativa');
                            }
                        }
                    } catch (Exception $e) {
                        // Log do erro mas não interrompe o processo
                        error_log("Erro ao gerar boleto: " . $e->getMessage());
                    }
                }
                
                // Gerar fatura Stripe se solicitado
                if ($gerarFatura) {
                    // Buscar dados do cliente
                    $stmtCliente = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
                    $stmtCliente->execute([$clienteId]);
                    $clienteDados = $stmtCliente->fetch();
                    
                    try {
                        require_once 'lib_stripe_faturamento.php';
                        $stripeLib = new StripeFaturamento();
                        
                        // Criar ou obter customer
                        $customerId = $stripeLib->criarOuObterCustomer($clienteDados);
                        
                        // Criar fatura
                        $dadosFatura = [
                            'customer_id' => $customerId,
                            'cliente_id' => $clienteId,
                            'conta_receber_id' => $contaReceberIdGerada,
                            'descricao' => $descricao,
                            'valor' => $valor,
                            'data_vencimento' => $dataVencimentoParcela,
                            'forma_pagamento' => $formaPagamentoFatura
                        ];
                        
                        $resultadoFatura = $stripeLib->criarFatura($dadosFatura);
                        
                        if ($resultadoFatura['sucesso']) {
                            // Salvar fatura no banco
                            $sqlFatura = "INSERT INTO faturamento (conta_receber_id, cliente_id, stripe_invoice_id, stripe_customer_id, numero_fatura, descricao, valor_total, status, data_emissao, data_vencimento, url_fatura, hosted_invoice_url, payment_intent_id, boleto_url, forma_pagamento, resposta_api) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)";
                            $stmtFatura = $conn->prepare($sqlFatura);
                            $stmtFatura->execute([
                                $contaReceberIdGerada,
                                $clienteId,
                                $resultadoFatura['invoice_id'],
                                $customerId,
                                $resultadoFatura['numero_fatura'],
                                $descricao,
                                $valor,
                                $resultadoFatura['status'],
                                $dataVencimentoParcela,
                                $resultadoFatura['url_fatura'],
                                $resultadoFatura['hosted_invoice_url'],
                                $resultadoFatura['payment_intent_id'],
                                $resultadoFatura['boleto_url'],
                                $formaPagamentoFatura,
                                $resultadoFatura['resposta_completa']
                            ]);
                            
                            $faturaIdGerada = $conn->lastInsertId();
                            
                            // Atualizar conta a receber com ID da fatura
                            $conn->prepare("UPDATE contas_receber SET fatura_id = ? WHERE id = ?")->execute([$faturaIdGerada, $contaReceberIdGerada]);
                        }
                    } catch (Exception $e) {
                        // Log do erro mas não interrompe o processo
                        error_log("Erro ao gerar fatura Stripe: " . $e->getMessage());
                    }
                }
            }
            $mensagem = "Conta(s) a receber cadastrada(s) com sucesso!";
            header('Location: contas_receber.php?msg=' . urlencode($mensagem));
            exit;
        }
        
    } catch (PDOException $e) {
        $erro = "Erro ao salvar conta a receber: " . $e->getMessage();
    }
}

include 'header.php';
?>

<style>
    .anexos-section {
        border: 2px solid #f59e0b;
        padding: 1.5rem;
        border-radius: 8px;
        background: #fffbeb;
        margin-top: 1rem;
    }
    
    .anexos-section h3 {
        color: #d97706;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .upload-area {
        border: 2px dashed #d97706;
        padding: 2rem;
        border-radius: 8px;
        text-align: center;
        background: white;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .upload-area:hover {
        border-color: #b45309;
        background: #fef3c7;
    }
    
    .upload-area input[type="file"] {
        display: none;
    }
    
    .upload-icon {
        font-size: 48px;
        color: #d97706;
        margin-bottom: 1rem;
    }
    
    .file-list {
        margin-top: 1rem;
        display: grid;
        gap: 0.5rem;
    }
    
    .file-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        background: white;
        border: 1px solid #d97706;
        border-radius: 6px;
    }
    
    .file-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
    }
    
    .file-icon {
        font-size: 24px;
    }
    
    .file-details {
        flex: 1;
    }
    
    .file-name {
        font-weight: 500;
        color: #1f2937;
    }
    
    .file-size {
        font-size: 0.875rem;
        color: #6b7280;
    }
    
    .file-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .btn-delete-file {
        background: #ef4444;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.875rem;
        transition: background 0.3s;
    }
    
    .btn-delete-file:hover {
        background: #dc2626;
    }
    
    .btn-view-file {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.875rem;
        text-decoration: none;
        display: inline-block;
        transition: background 0.3s;
    }
    
    .btn-view-file:hover {
        background: #2563eb;
    }
    
    .anexos-existentes {
        margin-top: 1.5rem;
    }
    
    .anexos-existentes h4 {
        color: #d97706;
        margin-bottom: 1rem;
    }
    
    .max-files-warning {
        color: #d97706;
        font-size: 0.875rem;
        margin-top: 0.5rem;
        display: block;
    }
</style>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2><?php echo $contaId > 0 ? 'Editar Conta a Receber' : 'Nova Conta a Receber'; ?></h2>
        </div>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error"><?php echo $erro; ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" id="formContaReceber">
            <div class="form-row">
                <div class="form-group">
                    <label>Cliente *</label>
                    <select name="cliente_id" required>
                        <option value="">Selecione o cliente...</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" 
                                    <?php echo ($conta && $conta['cliente_id'] == $c['id']) ? 'selected' : ''; ?>>
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
                    <input type="text" name="valor" required class="money-input"
                           value="<?php echo $conta ? number_format($conta['valor'], 2, ',', '.') : ''; ?>">
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
                        <option value="pix" <?php echo ($conta && $conta['forma_pagamento'] == 'pix') ? 'selected' : ''; ?>>PIX</option>
                        <option value="cartao" <?php echo ($conta && $conta['forma_pagamento'] == 'cartao') ? 'selected' : ''; ?>>Cartão</option>
                        <option value="transferencia" <?php echo ($conta && $conta['forma_pagamento'] == 'transferencia') ? 'selected' : ''; ?>>Transferência</option>
                        <option value="dinheiro" <?php echo ($conta && $conta['forma_pagamento'] == 'dinheiro') ? 'selected' : ''; ?>>Dinheiro</option>
                    </select>
                </div>
            </div>
            
            <?php if (!$contaId): ?>
            <div class="form-group">
                <label>Recorrência (Parcelas) *</label>
                <input type="number" name="recorrencia" min="1" max="120" value="1" required>
                <small style="color: #6b7280;">Informe quantas vezes esta conta se repetirá. Para pagamento único, deixe 1.</small>
            </div>
            
            <!-- Componente de Seleção de Gateways -->
            <div id="gateway-selector-container-conta"></div>
            
            <?php else: ?>
            <input type="hidden" name="recorrencia" value="<?php echo $conta['recorrencia']; ?>">
            
            <!-- Componente de Seleção de Gateways (Edição) -->
            <div id="gateway-selector-container-conta"></div>
            
            <?php endif; ?>
            
            <div class="form-group">
                <label>Observações</label>
                <textarea name="observacoes" rows="4"><?php echo $conta ? htmlspecialchars($conta['observacoes']) : ''; ?></textarea>
            </div>
            
            <!-- SEÇÃO DE ANEXOS -->
            <div class="anexos-section">
                <h3>
                    <span>📎</span>
                    Anexos (Máximo 4 arquivos)
                </h3>
                
                <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                    <div class="upload-icon">📁</div>
                    <p style="color: #d97706; font-weight: 500; margin-bottom: 0.5rem;">
                        Clique aqui para selecionar arquivos
                    </p>
                    <p style="color: #92400e; font-size: 0.875rem;">
                        PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (Máx: 10MB por arquivo)
                    </p>
                    <input type="file" id="fileInput" name="anexos[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" onchange="mostrarArquivosSelecionados(this)">
                </div>
                
                <div id="fileList" class="file-list" style="display: none;"></div>
                
                <small class="max-files-warning">
                    ⚠️ Você pode anexar até 4 arquivos. Se houver mais de 4, apenas os 4 primeiros serão processados.
                </small>
                
                <?php if ($contaId && count($anexosExistentes) > 0): ?>
                <div class="anexos-existentes">
                    <h4>📋 Anexos Existentes</h4>
                    <div class="file-list">
                        <?php foreach ($anexosExistentes as $anexo): ?>
                        <div class="file-item">
                            <div class="file-info">
                                <span class="file-icon">
                                    <?php
                                    $ext = strtolower(pathinfo($anexo['nome_arquivo'], PATHINFO_EXTENSION));
                                    if (in_array($ext, ['pdf'])) echo '📄';
                                    elseif (in_array($ext, ['doc', 'docx'])) echo '📝';
                                    elseif (in_array($ext, ['xls', 'xlsx'])) echo '📊';
                                    elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) echo '🖼️';
                                    else echo '📎';
                                    ?>
                                </span>
                                <div class="file-details">
                                    <div class="file-name"><?php echo htmlspecialchars($anexo['nome_original']); ?></div>
                                    <div class="file-size">
                                        <?php echo number_format($anexo['tamanho_arquivo'] / 1024, 2); ?> KB | 
                                        Enviado em <?php echo date('d/m/Y H:i', strtotime($anexo['data_upload'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="file-actions">
                                <a href="<?php echo $anexo['caminho_arquivo']; ?>" target="_blank" class="btn-view-file">
                                    👁️ Visualizar
                                </a>
                                <button type="button" class="btn-delete-file" onclick="deletarAnexo(<?php echo $anexo['id']; ?>)">
                                    🗑️ Excluir
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-success">Salvar</button>
                <a href="contas_receber.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
    // Mostrar arquivos selecionados
    function mostrarArquivosSelecionados(input) {
        const fileList = document.getElementById('fileList');
        const files = input.files;
        
        if (files.length === 0) {
            fileList.style.display = 'none';
            return;
        }
        
        // Limitar a 4 arquivos
        const maxFiles = 4;
        const filesToShow = Math.min(files.length, maxFiles);
        
        let html = '';
        for (let i = 0; i < filesToShow; i++) {
            const file = files[i];
            const ext = file.name.split('.').pop().toLowerCase();
            let icon = '📎';
            
            if (ext === 'pdf') icon = '📄';
            else if (['doc', 'docx'].includes(ext)) icon = '📝';
            else if (['xls', 'xlsx'].includes(ext)) icon = '📊';
            else if (['jpg', 'jpeg', 'png'].includes(ext)) icon = '🖼️';
            
            html += `
                <div class="file-item">
                    <div class="file-info">
                        <span class="file-icon">${icon}</span>
                        <div class="file-details">
                            <div class="file-name">${file.name}</div>
                            <div class="file-size">${(file.size / 1024).toFixed(2)} KB</div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        if (files.length > maxFiles) {
            html += `<p style="color: #ef4444; font-weight: 500; padding: 0.5rem;">⚠️ Apenas os primeiros ${maxFiles} arquivos serão enviados.</p>`;
        }
        
        fileList.innerHTML = html;
        fileList.style.display = 'grid';
    }
    
    // Deletar anexo
    function deletarAnexo(anexoId) {
        if (!confirm('Tem certeza que deseja excluir este anexo?')) {
            return;
        }
        
        fetch('deletar_anexo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ anexo_id: anexoId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Anexo excluído com sucesso!');
                location.reload();
            } else {
                alert('Erro ao excluir anexo: ' + data.message);
            }
        })
        .catch(error => {
            alert('Erro ao excluir anexo.');
            console.error(error);
        });
    }
    
    // Inicializar GatewaySelector
    document.addEventListener('DOMContentLoaded', function() {
        fetch('api/gateways.php')
            .then(res => res.json())
            .then(response => {
                if (response.success) {
                    <?php if ($conta): ?>
                    // Modo edição: carregar gateways salvos
                    const gatewaysSalvos = <?php echo json_encode($conta['gateways_disponiveis'] ? json_decode($conta['gateways_disponiveis'], true) : []); ?>;
                    const gatewayPreferencial = <?php echo $conta['gateway_id'] ?? 'null'; ?>;
                    <?php else: ?>
                    // Modo criação: todos selecionados por padrão
                    const gatewaysSalvos = response.data.map(g => g.id);
                    const gatewayPreferencial = response.data[0]?.id || null;
                    <?php endif; ?>
                    
                    new GatewaySelector('gateway-selector-container-conta', {
                        gateways: response.data,
                        selected: gatewaysSalvos,
                        preferencial: gatewayPreferencial,
                        onChange: (selection) => {
                            console.log('Gateways selecionados:', selection);
                        }
                    });
                } else {
                    console.error('Erro ao carregar gateways:', response.error);
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
            });
        
        const formaPagamento = document.querySelector('select[name="forma_pagamento"]');
        const opcoesBoleto = document.getElementById('opcoes_boleto');
        const gerarBoleto = document.getElementById('gerar_boleto');
        const plataformaBoletoGroup = document.getElementById('plataforma_boleto_group');
        
        if (formaPagamento) {
            formaPagamento.addEventListener('change', function() {
                if (this.value === 'boleto') {
                    opcoesBoleto.style.display = 'block';
                } else {
                    opcoesBoleto.style.display = 'none';
                    gerarBoleto.checked = false;
                    plataformaBoletoGroup.style.display = 'none';
                }
            });
        }
        
        if (gerarBoleto) {
            gerarBoleto.addEventListener('change', function() {
                if (this.checked) {
                    plataformaBoletoGroup.style.display = 'block';
                    document.getElementById('plataforma_boleto').required = true;
                } else {
                    plataformaBoletoGroup.style.display = 'none';
                    document.getElementById('plataforma_boleto').required = false;
                }
            });
        }
    });
</script>

<?php include 'footer.php'; ?>
