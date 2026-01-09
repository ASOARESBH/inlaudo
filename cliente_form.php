<?php
require_once 'config.php';

$pageTitle = 'Cadastro de Cliente';
$conn = getConnection();

// Verificar se é edição
$clienteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$cliente = null;

if ($clienteId > 0) {
    $stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$clienteId]);
    $cliente = $stmt->fetch();
    
    if (!$cliente) {
        header('Location: clientes.php');
        exit;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipoPessoa = sanitize($_POST['tipo_pessoa']);
    $tipoCliente = sanitize($_POST['tipo_cliente']);
    $cnpjCpf = preg_replace('/[^0-9]/', '', $_POST['cnpj_cpf']);
    $nome = sanitize($_POST['nome'] ?? '');
    $razaoSocial = sanitize($_POST['razao_social'] ?? '');
    $nomeFantasia = sanitize($_POST['nome_fantasia'] ?? '');
    $email = sanitize($_POST['email']);
    $telefone = preg_replace('/[^0-9]/', '', $_POST['telefone'] ?? '');
    $celular = preg_replace('/[^0-9]/', '', $_POST['celular'] ?? '');
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    $logradouro = sanitize($_POST['logradouro'] ?? '');
    $numero = sanitize($_POST['numero'] ?? '');
    $complemento = sanitize($_POST['complemento'] ?? '');
    $bairro = sanitize($_POST['bairro'] ?? '');
    $cidade = sanitize($_POST['cidade'] ?? '');
    $estado = sanitize($_POST['estado'] ?? '');
    
    try {
        if ($clienteId > 0) {
            // Atualizar
            $sql = "UPDATE clientes SET 
                    tipo_pessoa = ?, tipo_cliente = ?, cnpj_cpf = ?, nome = ?, 
                    razao_social = ?, nome_fantasia = ?, email = ?, telefone = ?, 
                    celular = ?, cep = ?, logradouro = ?, numero = ?, complemento = ?, 
                    bairro = ?, cidade = ?, estado = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $tipoPessoa, $tipoCliente, $cnpjCpf, $nome, $razaoSocial, $nomeFantasia,
                $email, $telefone, $celular, $cep, $logradouro, $numero, $complemento,
                $bairro, $cidade, $estado, $clienteId
            ]);
            $mensagem = "Cliente atualizado com sucesso!";
        } else {
            // Inserir
            $sql = "INSERT INTO clientes (
                    tipo_pessoa, tipo_cliente, cnpj_cpf, nome, razao_social, nome_fantasia,
                    email, telefone, celular, cep, logradouro, numero, complemento,
                    bairro, cidade, estado
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $tipoPessoa, $tipoCliente, $cnpjCpf, $nome, $razaoSocial, $nomeFantasia,
                $email, $telefone, $celular, $cep, $logradouro, $numero, $complemento,
                $bairro, $cidade, $estado
            ]);
            $mensagem = "Cliente cadastrado com sucesso!";
        }
        
        header('Location: clientes.php?msg=' . urlencode($mensagem));
        exit;
        
    } catch (PDOException $e) {
        $erro = "Erro ao salvar cliente: " . $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2><?php echo $clienteId > 0 ? 'Editar Cliente' : 'Novo Cliente'; ?></h2>
        </div>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error"><?php echo $erro; ?></div>
        <?php endif; ?>
        
        <form method="POST" id="formCliente">
            <div class="form-row">
                <div class="form-group">
                    <label>Tipo de Pessoa *</label>
                    <select name="tipo_pessoa" id="tipoPessoa" required onchange="alterarTipoPessoa()">
                        <option value="">Selecione...</option>
                        <option value="CNPJ" <?php echo ($cliente && $cliente['tipo_pessoa'] == 'CNPJ') ? 'selected' : ''; ?>>CNPJ</option>
                        <option value="CPF" <?php echo ($cliente && $cliente['tipo_pessoa'] == 'CPF') ? 'selected' : ''; ?>>CPF</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Tipo de Cliente *</label>
                    <select name="tipo_cliente" required>
                        <option value="">Selecione...</option>
                        <option value="LEAD" <?php echo ($cliente && $cliente['tipo_cliente'] == 'LEAD') ? 'selected' : ''; ?>>Lead</option>
                        <option value="CLIENTE" <?php echo ($cliente && $cliente['tipo_cliente'] == 'CLIENTE') ? 'selected' : ''; ?>>Cliente</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label id="labelCnpjCpf">CNPJ/CPF *</label>
                    <input type="text" name="cnpj_cpf" id="cnpjCpf" required 
                           value="<?php echo $cliente ? htmlspecialchars($cliente['cnpj_cpf']) : ''; ?>"
                           onblur="buscarCNPJ()">
                </div>
            </div>
            
            <div id="camposCNPJ" style="display: none;">
                <div class="form-row">
                    <div class="form-group">
                        <label>Razão Social</label>
                        <input type="text" name="razao_social" id="razaoSocial" 
                               value="<?php echo $cliente ? htmlspecialchars($cliente['razao_social']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Nome Fantasia</label>
                        <input type="text" name="nome_fantasia" id="nomeFantasia" 
                               value="<?php echo $cliente ? htmlspecialchars($cliente['nome_fantasia']) : ''; ?>">
                    </div>
                </div>
            </div>
            
            <div id="camposCPF" style="display: none;">
                <div class="form-group">
                    <label>Nome Completo *</label>
                    <input type="text" name="nome" id="nome" 
                           value="<?php echo $cliente ? htmlspecialchars($cliente['nome']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>E-mail *</label>
                    <input type="email" name="email" id="email" required 
                           value="<?php echo $cliente ? htmlspecialchars($cliente['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone" id="telefone" 
                           value="<?php echo $cliente ? htmlspecialchars($cliente['telefone']) : ''; ?>"
                           onkeyup="this.value = formatTelefone(this.value)">
                </div>
                
                <div class="form-group">
                    <label>Celular</label>
                    <input type="text" name="celular" id="celular" 
                           value="<?php echo $cliente ? htmlspecialchars($cliente['celular']) : ''; ?>"
                           onkeyup="this.value = formatTelefone(this.value)">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>CEP</label>
                    <input type="text" name="cep" id="cep" 
                           value="<?php echo $cliente ? htmlspecialchars($cliente['cep']) : ''; ?>"
                           onkeyup="this.value = formatCEP(this.value)">
                </div>
                
                <div class="form-group">
                    <label>Logradouro</label>
                    <input type="text" name="logradouro" id="logradouro" 
                           value="<?php echo $cliente ? htmlspecialchars($cliente['logradouro']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Número</label>
                    <input type="text" name="numero" id="numero" 
                           value="<?php echo $cliente ? htmlspecialchars($cliente['numero']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Complemento</label>
                    <input type="text" name="complemento" id="complemento" 
                           value="<?php echo $cliente ? htmlspecialchars($cliente['complemento']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Bairro</label>
                    <input type="text" name="bairro" id="bairro" 
                           value="<?php echo $cliente ? htmlspecialchars($cliente['bairro']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Cidade</label>
                    <input type="text" name="cidade" id="cidade" 
                           value="<?php echo $cliente ? htmlspecialchars($cliente['cidade']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Estado</label>
                    <input type="text" name="estado" id="estado" maxlength="2" 
                           value="<?php echo $cliente ? htmlspecialchars($cliente['estado']) : ''; ?>"
                           style="text-transform: uppercase;">
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-success">Salvar</button>
                <a href="clientes.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
    function alterarTipoPessoa() {
        const tipo = document.getElementById('tipoPessoa').value;
        const camposCNPJ = document.getElementById('camposCNPJ');
        const camposCPF = document.getElementById('camposCPF');
        const labelCnpjCpf = document.getElementById('labelCnpjCpf');
        const inputCnpjCpf = document.getElementById('cnpjCpf');
        
        if (tipo === 'CNPJ') {
            camposCNPJ.style.display = 'block';
            camposCPF.style.display = 'none';
            labelCnpjCpf.textContent = 'CNPJ *';
            inputCnpjCpf.placeholder = '00.000.000/0000-00';
            inputCnpjCpf.onkeyup = function() { this.value = formatCNPJ(this.value); };
        } else if (tipo === 'CPF') {
            camposCNPJ.style.display = 'none';
            camposCPF.style.display = 'block';
            labelCnpjCpf.textContent = 'CPF *';
            inputCnpjCpf.placeholder = '000.000.000-00';
            inputCnpjCpf.onkeyup = function() { this.value = formatCPF(this.value); };
        } else {
            camposCNPJ.style.display = 'none';
            camposCPF.style.display = 'none';
        }
    }
    
    function buscarCNPJ() {
        const tipo = document.getElementById('tipoPessoa').value;
        if (tipo !== 'CNPJ') return;
        
        const cnpj = document.getElementById('cnpjCpf').value.replace(/\D/g, '');
        if (cnpj.length !== 14) return;
        
        fetch(`api_cnpj.php?cnpj=${cnpj}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'OK') {
                    document.getElementById('razaoSocial').value = data.nome || '';
                    document.getElementById('nomeFantasia').value = data.fantasia || '';
                    document.getElementById('email').value = data.email || '';
                    document.getElementById('telefone').value = data.telefone || '';
                    document.getElementById('cep').value = data.cep ? formatCEP(data.cep) : '';
                    document.getElementById('logradouro').value = data.logradouro || '';
                    document.getElementById('numero').value = data.numero || '';
                    document.getElementById('complemento').value = data.complemento || '';
                    document.getElementById('bairro').value = data.bairro || '';
                    document.getElementById('cidade').value = data.municipio || '';
                    document.getElementById('estado').value = data.uf || '';
                } else {
                    alert('CNPJ não encontrado ou inválido.');
                }
            })
            .catch(error => {
                console.error('Erro ao buscar CNPJ:', error);
            });
    }
    
    // Inicializar tipo de pessoa se estiver editando
    <?php if ($cliente): ?>
        alterarTipoPessoa();
    <?php endif; ?>
</script>

<?php include 'footer.php'; ?>
