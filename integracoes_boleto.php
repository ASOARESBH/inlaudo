<?php
require_once 'config.php';

$pageTitle = 'Integrações - Boleto';
$conn = getConnection();

// Buscar configurações atuais
$stmtCora = $conn->query("SELECT * FROM integracoes WHERE tipo = 'cora'");
$configCora = $stmtCora->fetch();

$stmtStripe = $conn->query("SELECT * FROM integracoes WHERE tipo = 'stripe'");
$configStripe = $stmtStripe->fetch();

// Processar formulário CORA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'cora') {
    $clientId = sanitize($_POST['cora_client_id']);
    $ambiente = sanitize($_POST['cora_ambiente']);
    $ativo = isset($_POST['cora_ativo']) ? 1 : 0;
    
    // Processar upload de certificados
    $certificadoPath = $configCora ? $configCora['api_key'] : ''; // api_key guarda caminho do certificado
    $privateKeyPath = $configCora ? $configCora['api_secret'] : ''; // api_secret guarda caminho da chave privada
    
    // Upload do certificado
    if (isset($_FILES['cora_certificado']) && $_FILES['cora_certificado']['error'] == 0) {
        $uploadDir = __DIR__ . '/certs/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $certificadoNome = 'cora_certificate_' . time() . '.pem';
        $certificadoPath = $uploadDir . $certificadoNome;
        
        if (move_uploaded_file($_FILES['cora_certificado']['tmp_name'], $certificadoPath)) {
            chmod($certificadoPath, 0600);
        } else {
            $erro = "Erro ao fazer upload do certificado";
        }
    }
    
    // Upload da chave privada
    if (isset($_FILES['cora_private_key']) && $_FILES['cora_private_key']['error'] == 0) {
        $uploadDir = __DIR__ . '/certs/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $privateKeyNome = 'cora_private_key_' . time() . '.key';
        $privateKeyPath = $uploadDir . $privateKeyNome;
        
        if (move_uploaded_file($_FILES['cora_private_key']['tmp_name'], $privateKeyPath)) {
            chmod($privateKeyPath, 0600);
        } else {
            $erro = "Erro ao fazer upload da chave privada";
        }
    }
    
    if (!isset($erro)) {
        try {
            // Guardar client_id em configuracoes (JSON), certificado em api_key e private key em api_secret
            $configuracoes = json_encode([
                'client_id' => $clientId,
                'ambiente' => $ambiente
            ]);
            
            // Verificar se registro existe
            $stmtCheck = $conn->prepare("SELECT id FROM integracoes WHERE tipo = 'cora'");
            $stmtCheck->execute();
            $existe = $stmtCheck->fetch();
            
            if ($existe) {
                // UPDATE
                $sql = "UPDATE integracoes SET configuracoes = ?, api_key = ?, api_secret = ?, ativo = ? WHERE tipo = 'cora'";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$configuracoes, $certificadoPath, $privateKeyPath, $ativo]);
            } else {
                // INSERT
                $sql = "INSERT INTO integracoes (tipo, configuracoes, api_key, api_secret, ativo) VALUES ('cora', ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$configuracoes, $certificadoPath, $privateKeyPath, $ativo]);
            }
            
            $mensagem = "Configurações do CORA atualizadas com sucesso!";
            
            // Recarregar configurações
            $stmtCora = $conn->query("SELECT * FROM integracoes WHERE tipo = 'cora'");
            $configCora = $stmtCora->fetch();
            
        } catch (PDOException $e) {
            $erro = "Erro ao salvar configurações do CORA: " . $e->getMessage();
        }
    }
}

// Processar formulário Stripe
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tipo']) && $_POST['tipo'] == 'stripe') {
    $apiKey = sanitize($_POST['stripe_api_key']);
    $apiSecret = sanitize($_POST['stripe_api_secret']);
    $ativo = isset($_POST['stripe_ativo']) ? 1 : 0;
    
    try {
        // Verificar se registro existe
        $stmtCheck = $conn->prepare("SELECT id FROM integracoes WHERE tipo = 'stripe'");
        $stmtCheck->execute();
        $existe = $stmtCheck->fetch();
        
        if ($existe) {
            // UPDATE
            $sql = "UPDATE integracoes SET api_key = ?, api_secret = ?, ativo = ? WHERE tipo = 'stripe'";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$apiKey, $apiSecret, $ativo]);
        } else {
            // INSERT
            $sql = "INSERT INTO integracoes (tipo, api_key, api_secret, ativo) VALUES ('stripe', ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$apiKey, $apiSecret, $ativo]);
        }
        
        $mensagem = "Configurações do Stripe atualizadas com sucesso!";
        
        // Recarregar configurações
        $stmtStripe = $conn->query("SELECT * FROM integracoes WHERE tipo = 'stripe'");
        $configStripe = $stmtStripe->fetch();
        
    } catch (PDOException $e) {
        $erro = "Erro ao salvar configurações do Stripe: " . $e->getMessage();
    }
}

// Testar integração CORA
if (isset($_GET['testar_cora']) && $configCora && $configCora['ativo']) {
    require_once 'lib_boleto_cora_v2.php';
    
    $config = json_decode($configCora['config'], true);
    $clientId = $config['client_id'] ?? '';
    $ambiente = $config['ambiente'] ?? 'production';
    $certificado = $configCora['api_key'];
    $privateKey = $configCora['api_secret'];
    
    if ($clientId && file_exists($certificado) && file_exists($privateKey)) {
        $cora = new CoraAPIv2($clientId, $certificado, $privateKey, $ambiente);
        $resultado = $cora->testarConexao();
        
        if ($resultado['sucesso']) {
            $mensagem = "✅ " . $resultado['mensagem'];
        } else {
            $erro = "❌ " . $resultado['mensagem'];
        }
    } else {
        $erro = "Configuração incompleta. Verifique Client ID e certificados.";
    }
}

// Extrair dados da configuração CORA
$coraConfig = $configCora ? json_decode($configCora['config'], true) : [];
$coraClientId = $coraConfig['client_id'] ?? '';
$coraAmbiente = $coraConfig['ambiente'] ?? 'production';
$coraCertificado = $configCora ? $configCora['api_key'] : '';
$coraPrivateKey = $configCora ? $configCora['api_secret'] : '';

include 'header.php';
?>

<div class="container">
    <?php if (isset($mensagem)): ?>
        <div class="alert alert-success"><?php echo $mensagem; ?></div>
    <?php endif; ?>
    
    <?php if (isset($erro)): ?>
        <div class="alert alert-error"><?php echo $erro; ?></div>
    <?php endif; ?>
    
    <!-- Integração CORA -->
    <div class="card">
        <div class="card-header">
            <h2>🏦 Integração CORA - Boletos Registrados (API v2)</h2>
        </div>
        
        <div class="alert alert-info">
            <strong>📌 Sobre a integração CORA:</strong><br>
            A CORA utiliza autenticação mTLS (Mutual TLS) com certificado digital para máxima segurança.<br>
            <strong>Como obter as credenciais:</strong>
            <ol style="margin: 0.5rem 0 0 1.5rem;">
                <li>Acesse sua conta CORA em <a href="https://app.cora.com.br" target="_blank">app.cora.com.br</a></li>
                <li>Vá em <strong>Conta > Integrações via APIs</strong></li>
                <li>Copie o <strong>Client-ID</strong></li>
                <li>Faça download do arquivo ZIP com <strong>Certificado e Private Key</strong></li>
                <li>Extraia os arquivos <code>certificate.pem</code> e <code>private-key.key</code></li>
                <li>Faça upload dos arquivos abaixo</li>
            </ol>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="tipo" value="cora">
            
            <div class="form-group">
                <label>Client-ID * <small>(Exemplo: int-6f2u3vpjglGsZ8nev37Wm7)</small></label>
                <input type="text" name="cora_client_id" 
                       value="<?php echo htmlspecialchars($coraClientId); ?>" 
                       placeholder="Digite o Client-ID fornecido pela CORA"
                       required>
            </div>
            
            <div class="form-group">
                <label>Ambiente *</label>
                <select name="cora_ambiente" required>
                    <option value="production" <?php echo $coraAmbiente == 'production' ? 'selected' : ''; ?>>Produção</option>
                    <option value="stage" <?php echo $coraAmbiente == 'stage' ? 'selected' : ''; ?>>Teste (Stage)</option>
                </select>
                <small>Use "Teste" para desenvolvimento e "Produção" para operação real</small>
            </div>
            
            <div class="form-group">
                <label>Certificado (certificate.pem) *</label>
                <?php if ($coraCertificado && file_exists($coraCertificado)): ?>
                    <div style="padding: 0.5rem; background: #e8f5e9; border-radius: 4px; margin-bottom: 0.5rem;">
                        ✅ Certificado atual: <code><?php echo basename($coraCertificado); ?></code>
                        <small style="display: block; margin-top: 0.25rem;">
                            Faça upload de um novo arquivo apenas se quiser substituir
                        </small>
                    </div>
                <?php endif; ?>
                <input type="file" name="cora_certificado" accept=".pem">
                <small>Arquivo certificate.pem fornecido pela CORA</small>
            </div>
            
            <div class="form-group">
                <label>Chave Privada (private-key.key) *</label>
                <?php if ($coraPrivateKey && file_exists($coraPrivateKey)): ?>
                    <div style="padding: 0.5rem; background: #e8f5e9; border-radius: 4px; margin-bottom: 0.5rem;">
                        ✅ Chave privada atual: <code><?php echo basename($coraPrivateKey); ?></code>
                        <small style="display: block; margin-top: 0.25rem;">
                            Faça upload de um novo arquivo apenas se quiser substituir
                        </small>
                    </div>
                <?php endif; ?>
                <input type="file" name="cora_private_key" accept=".key,.pem">
                <small>Arquivo private-key.key fornecido pela CORA</small>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="cora_ativo" value="1" 
                           <?php echo ($configCora && $configCora['ativo']) ? 'checked' : ''; ?>>
                    <span>Integração Ativa</span>
                </label>
                <small>Marque para ativar a integração com CORA</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Salvar Configurações</button>
                <?php if ($configCora && $configCora['ativo']): ?>
                    <a href="?testar_cora=1" class="btn btn-secondary">🧪 Testar Conexão</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Integração Stripe -->
    <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
            <h2>💳 Integração Stripe - Boletos</h2>
        </div>
        
        <div class="alert alert-info">
            <strong>📌 Sobre o Stripe:</strong> O Stripe é uma plataforma global de pagamentos que permite a geração de boletos bancários no Brasil. 
            Para integrar, você precisa criar uma conta no <a href="https://stripe.com" target="_blank">site do Stripe</a> 
            e obter suas credenciais de API.
        </div>
        
        <form method="POST">
            <input type="hidden" name="tipo" value="stripe">
            
            <div class="form-group">
                <label>API Key (Publishable Key) *</label>
                <input type="text" name="stripe_api_key" 
                       value="<?php echo $configStripe ? htmlspecialchars($configStripe['api_key']) : ''; ?>" 
                       placeholder="pk_live_...">
                <small>Chave pública do Stripe (começa com pk_)</small>
            </div>
            
            <div class="form-group">
                <label>API Secret (Secret Key) *</label>
                <input type="password" name="stripe_api_secret" 
                       value="<?php echo $configStripe ? htmlspecialchars($configStripe['api_secret']) : ''; ?>" 
                       placeholder="sk_live_...">
                <small>Chave secreta do Stripe (começa com sk_)</small>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="stripe_ativo" value="1" 
                           <?php echo ($configStripe && $configStripe['ativo']) ? 'checked' : ''; ?>>
                    <span>Integração Ativa</span>
                </label>
                <small>Marque para ativar a integração com Stripe</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Salvar Configurações</button>
            </div>
        </form>
    </div>
    
    <!-- Documentação -->
    <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
            <h2>📚 Documentação</h2>
        </div>
        
        <div style="padding: 1.5rem;">
            <h3>Como usar a integração CORA</h3>
            <ol>
                <li>Configure as credenciais acima (Client-ID e certificados)</li>
                <li>Ative a integração marcando "Integração Ativa"</li>
                <li>Teste a conexão clicando em "Testar Conexão"</li>
                <li>Ao criar uma conta a receber, selecione "Boleto" como forma de pagamento</li>
                <li>Marque "Gerar boleto automaticamente" e selecione "CORA"</li>
                <li>O boleto será gerado automaticamente ao salvar</li>
            </ol>
            
            <h3 style="margin-top: 1.5rem;">Diferenças entre Ambientes</h3>
            <ul>
                <li><strong>Teste (Stage):</strong> Use para desenvolvimento. Boletos não são reais.</li>
                <li><strong>Produção:</strong> Use para operação real. Boletos são válidos e podem ser pagos.</li>
            </ul>
            
            <h3 style="margin-top: 1.5rem;">Segurança</h3>
            <p>
                Os certificados são armazenados com permissões restritas (600) no servidor e nunca são expostos publicamente.
                A autenticação mTLS garante que apenas o seu sistema pode acessar a API CORA.
            </p>
            
            <h3 style="margin-top: 1.5rem;">Links Úteis</h3>
            <ul>
                <li><a href="https://developers.cora.com.br" target="_blank">Documentação da API CORA</a></li>
                <li><a href="https://app.cora.com.br" target="_blank">Painel CORA</a></li>
                <li><a href="logs_integracao.php">Ver Logs de Integração</a></li>
            </ul>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
