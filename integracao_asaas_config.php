<?php
/**
 * Configuração da Integração Asaas
 * URL: https://erp.inlaudo.com.br/integracao_asaas_config.php
 * 
 * Página para configurar credenciais e ambiente Asaas
 */

session_start();

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

$pageTitle = 'Configurar Asaas';
$mensagem = '';
$tipo_mensagem = '';
$config = null;
$erro = '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar configuração existente
    $sql = "SELECT * FROM integracao_asaas WHERE id = 1 LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Processar formulário
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $api_key = trim($_POST['api_key'] ?? '');
        $ambiente = trim($_POST['ambiente'] ?? 'sandbox');
        $webhook_token = trim($_POST['webhook_token'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        // Validações
        if (empty($api_key)) {
            throw new Exception('API Key é obrigatória');
        }
        
        if (strlen($api_key) < 10) {
            throw new Exception('API Key parece inválida (muito curta)');
        }
        
        if (!in_array($ambiente, ['sandbox', 'production'])) {
            throw new Exception('Ambiente inválido');
        }
        
        if ($config) {
            // Atualizar
            $sql = "UPDATE integracao_asaas SET 
                    api_key = ?,
                    ambiente = ?,
                    webhook_token = ?,
                    ativo = ?,
                    data_atualizacao = NOW()
                    WHERE id = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$api_key, $ambiente, $webhook_token, $ativo]);
            $mensagem = 'Configuração atualizada com sucesso!';
        } else {
            // Inserir
            $sql = "INSERT INTO integracao_asaas (api_key, ambiente, webhook_token, ativo, data_criacao) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$api_key, $ambiente, $webhook_token, $ativo]);
            $mensagem = 'Configuração salva com sucesso!';
        }
        
        $tipo_mensagem = 'sucesso';
        
        // Recarregar config
        $sql = "SELECT * FROM integracao_asaas WHERE id = 1 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    $erro = $e->getMessage();
    $tipo_mensagem = 'erro';
}

include 'header.php';
?>

<div class="container" style="max-width: 900px; margin: 40px auto;">
    <div class="card">
        <div class="card-header">
            <h2>Configurar Asaas</h2>
            <p style="color: #666; margin: 10px 0 0 0;">Gerencie as credenciais e configurações da integração Asaas</p>
        </div>
        
        <div class="card-body" style="padding: 30px;">
            
            <?php if ($mensagem): ?>
            <div style="background: <?php echo $tipo_mensagem === 'sucesso' ? '#d4edda' : '#f8d7da'; ?>; 
                        border: 1px solid <?php echo $tipo_mensagem === 'sucesso' ? '#c3e6cb' : '#f5c6cb'; ?>; 
                        color: <?php echo $tipo_mensagem === 'sucesso' ? '#155724' : '#721c24'; ?>; 
                        padding: 15px; 
                        border-radius: 4px; 
                        margin-bottom: 20px;">
                <strong><?php echo $tipo_mensagem === 'sucesso' ? '✓' : '✕'; ?></strong> 
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($erro): ?>
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <strong>Erro:</strong> <?php echo htmlspecialchars($erro); ?>
            </div>
            <?php endif; ?>
            
            <!-- Informações -->
            <div style="background: #e7f3ff; border-left: 4px solid #667eea; padding: 15px; margin-bottom: 30px; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0; color: #667eea;">Informações Importantes</h4>
                <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                    <li>Obtenha sua API Key em: <a href="https://app.asaas.com/settings/apikey" target="_blank" style="color: #667eea;">app.asaas.com/settings/apikey</a></li>
                    <li>Use <strong>Sandbox</strong> para testes e <strong>Production</strong> para produção</li>
                    <li>O webhook token é opcional, mas recomendado para segurança</li>
                    <li>Mantenha suas credenciais seguras e nunca as compartilhe</li>
                </ul>
            </div>
            
            <!-- Formulário -->
            <form method="POST" style="display: grid; gap: 20px;">
                
                <!-- API Key -->
                <div>
                    <label for="api_key" style="display: block; font-weight: bold; margin-bottom: 8px; color: #333;">
                        API Key <span style="color: red;">*</span>
                    </label>
                    <input type="password" 
                           id="api_key" 
                           name="api_key" 
                           value="<?php echo htmlspecialchars($config['api_key'] ?? ''); ?>" 
                           required
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 0.9em;">
                    <small style="color: #666; display: block; margin-top: 5px;">Sua chave de API do Asaas (mantida segura)</small>
                </div>
                
                <!-- Ambiente -->
                <div>
                    <label for="ambiente" style="display: block; font-weight: bold; margin-bottom: 8px; color: #333;">
                        Ambiente <span style="color: red;">*</span>
                    </label>
                    <select id="ambiente" 
                            name="ambiente" 
                            required
                            style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="sandbox" <?php echo ($config['ambiente'] ?? 'sandbox') === 'sandbox' ? 'selected' : ''; ?>>
                            Sandbox (Testes)
                        </option>
                        <option value="production" <?php echo ($config['ambiente'] ?? '') === 'production' ? 'selected' : ''; ?>>
                            Production (Produção)
                        </option>
                    </select>
                    <small style="color: #666; display: block; margin-top: 5px;">Selecione o ambiente para usar</small>
                </div>
                
                <!-- Webhook Token -->
                <div>
                    <label for="webhook_token" style="display: block; font-weight: bold; margin-bottom: 8px; color: #333;">
                        Webhook Token (Opcional)
                    </label>
                    <input type="password" 
                           id="webhook_token" 
                           name="webhook_token" 
                           value="<?php echo htmlspecialchars($config['webhook_token'] ?? ''); ?>"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 0.9em;">
                    <small style="color: #666; display: block; margin-top: 5px;">Token para validar webhooks (segurança adicional)</small>
                </div>
                
                <!-- Status -->
                <div>
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" 
                               name="ativo" 
                               <?php echo ($config['ativo'] ?? 0) ? 'checked' : ''; ?>
                               style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="font-weight: bold; color: #333;">Ativar Integração</span>
                    </label>
                    <small style="color: #666; display: block; margin-top: 5px; margin-left: 28px;">Marque para ativar a integração Asaas</small>
                </div>
                
                <!-- Botões -->
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" 
                            style="flex: 1; padding: 12px; background: #667eea; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 1em;">
                        Salvar Configuração
                    </button>
                    <a href="integracao_asaas.php" 
                       style="flex: 1; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 1em; text-align: center; text-decoration: none;">
                        Cancelar
                    </a>
                </div>
            </form>
            
            <!-- Status da Integração -->
            <?php if ($config): ?>
            <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid #ddd;">
                <h3 style="margin-top: 0;">Status da Integração</h3>
                
                <table style="width: 100%; border-collapse: collapse;">
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 10px; font-weight: bold; width: 30%;">Status:</td>
                        <td style="padding: 10px;">
                            <span style="background: <?php echo $config['ativo'] ? '#28a745' : '#dc3545'; ?>; 
                                         color: white; 
                                         padding: 5px 10px; 
                                         border-radius: 4px; 
                                         font-weight: bold;">
                                <?php echo $config['ativo'] ? 'Ativa' : 'Inativa'; ?>
                            </span>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 10px; font-weight: bold;">Ambiente:</td>
                        <td style="padding: 10px;">
                            <span style="background: <?php echo $config['ambiente'] === 'production' ? '#dc3545' : '#ffc107'; ?>; 
                                         color: <?php echo $config['ambiente'] === 'production' ? 'white' : '#000'; ?>; 
                                         padding: 5px 10px; 
                                         border-radius: 4px; 
                                         font-weight: bold;">
                                <?php echo ucfirst($config['ambiente']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 10px; font-weight: bold;">Última Atualização:</td>
                        <td style="padding: 10px;">
                            <?php 
                            if ($config['data_atualizacao']) {
                                echo date('d/m/Y H:i:s', strtotime($config['data_atualizacao']));
                            } else {
                                echo 'Nunca atualizado';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold;">Criado em:</td>
                        <td style="padding: 10px;">
                            <?php echo date('d/m/Y H:i:s', strtotime($config['data_criacao'])); ?>
                        </td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
