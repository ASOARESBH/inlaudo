<?php
require_once 'config.php';
require_once 'lib_email.php';

$pageTitle = 'Configuração de E-mail';

$mensagem = '';
$erro = '';

// Processar teste de e-mail
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['testar'])) {
    $configId = (int)$_POST['config_id'];
    $emailTeste = sanitize($_POST['email_teste']);
    
    if (empty($emailTeste) || !filter_var($emailTeste, FILTER_VALIDATE_EMAIL)) {
        $erro = "Por favor, informe um e-mail válido para teste.";
    } else {
        $resultado = EmailSender::testarConfiguracao($configId, $emailTeste);
        if ($resultado['sucesso']) {
            $mensagem = "E-mail de teste enviado com sucesso para $emailTeste! Verifique a caixa de entrada.";
        } else {
            $erro = "Erro ao enviar e-mail de teste: " . $resultado['mensagem'];
        }
    }
}

// Processar formulário de salvar/atualizar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar'])) {
    $configId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nomeConfig = sanitize($_POST['nome_config']);
    $smtpHost = sanitize($_POST['smtp_host']);
    $smtpPort = (int)$_POST['smtp_port'];
    $smtpSecure = sanitize($_POST['smtp_secure']);
    $smtpUser = sanitize($_POST['smtp_user']);
    $smtpPassword = $_POST['smtp_password']; // Não sanitizar senha
    $fromEmail = sanitize($_POST['from_email']);
    $fromName = sanitize($_POST['from_name']);
    $replyToEmail = sanitize($_POST['reply_to_email'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $testarEnvio = isset($_POST['testar_envio']) ? 1 : 0;
    $emailTeste = sanitize($_POST['email_teste_config'] ?? '');
    
    try {
        $conn = getConnection();
        
        if ($configId > 0) {
            // Atualizar
            $sql = "UPDATE email_config SET 
                    nome_config = ?, smtp_host = ?, smtp_port = ?, smtp_secure = ?,
                    smtp_user = ?, smtp_password = ?, from_email = ?, from_name = ?,
                    reply_to_email = ?, ativo = ?, testar_envio = ?, email_teste = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $nomeConfig, $smtpHost, $smtpPort, $smtpSecure,
                $smtpUser, $smtpPassword, $fromEmail, $fromName,
                $replyToEmail, $ativo, $testarEnvio, $emailTeste, $configId
            ]);
            $mensagem = "Configuração atualizada com sucesso!";
        } else {
            // Inserir
            $sql = "INSERT INTO email_config (
                        nome_config, smtp_host, smtp_port, smtp_secure, smtp_user,
                        smtp_password, from_email, from_name, reply_to_email,
                        ativo, testar_envio, email_teste
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $nomeConfig, $smtpHost, $smtpPort, $smtpSecure, $smtpUser,
                $smtpPassword, $fromEmail, $fromName, $replyToEmail,
                $ativo, $testarEnvio, $emailTeste
            ]);
            $mensagem = "Configuração criada com sucesso!";
        }
        
        // Se marcou como ativa, desativar outras
        if ($ativo) {
            $idAtualizado = $configId > 0 ? $configId : $conn->lastInsertId();
            $conn->prepare("UPDATE email_config SET ativo = FALSE WHERE id != ?")->execute([$idAtualizado]);
        }
        
    } catch (PDOException $e) {
        $erro = "Erro ao salvar configuração: " . $e->getMessage();
    }
}

// Processar exclusão
if (isset($_GET['deletar'])) {
    $configId = (int)$_GET['deletar'];
    try {
        $conn = getConnection();
        $conn->prepare("DELETE FROM email_config WHERE id = ?")->execute([$configId]);
        $mensagem = "Configuração excluída com sucesso!";
    } catch (PDOException $e) {
        $erro = "Erro ao excluir configuração: " . $e->getMessage();
    }
}

// Buscar configurações
$conn = getConnection();
$stmt = $conn->query("SELECT * FROM email_config ORDER BY ativo DESC, data_cadastro DESC");
$configuracoes = $stmt->fetchAll();

// Se está editando
$config = null;
if (isset($_GET['editar'])) {
    $configId = (int)$_GET['editar'];
    $stmt = $conn->prepare("SELECT * FROM email_config WHERE id = ?");
    $stmt->execute([$configId]);
    $config = $stmt->fetch();
}

include 'header.php';
?>

<div class="container">
    <?php if ($mensagem): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
    <?php endif; ?>
    
    <?php if ($erro): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2><?php echo $config ? 'Editar' : 'Nova'; ?> Configuração de E-mail</h2>
        </div>
        
        <form method="POST">
            <?php if ($config): ?>
                <input type="hidden" name="id" value="<?php echo $config['id']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Nome da Configuração *</label>
                <input type="text" name="nome_config" value="<?php echo $config['nome_config'] ?? 'Configuração Principal'; ?>" required>
                <small style="color: #6b7280;">Nome para identificar esta configuração</small>
            </div>
            
            <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #1e40af;">Configurações SMTP</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Servidor SMTP *</label>
                    <input type="text" name="smtp_host" value="<?php echo $config['smtp_host'] ?? 'smtp.gmail.com'; ?>" required placeholder="smtp.gmail.com">
                    <small style="color: #6b7280;">Gmail: smtp.gmail.com | Outlook: smtp-mail.outlook.com</small>
                </div>
                
                <div class="form-group">
                    <label>Porta SMTP *</label>
                    <input type="number" name="smtp_port" value="<?php echo $config['smtp_port'] ?? 587; ?>" required>
                    <small style="color: #6b7280;">TLS: 587 | SSL: 465</small>
                </div>
            </div>
            
            <div class="form-group">
                <label>Tipo de Segurança *</label>
                <select name="smtp_secure" required>
                    <option value="tls" <?php echo ($config['smtp_secure'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS (Recomendado)</option>
                    <option value="ssl" <?php echo ($config['smtp_secure'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="none" <?php echo ($config['smtp_secure'] ?? '') == 'none' ? 'selected' : ''; ?>>Nenhum</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Usuário SMTP (E-mail) *</label>
                    <input type="email" name="smtp_user" value="<?php echo $config['smtp_user'] ?? ''; ?>" required placeholder="seu-email@gmail.com">
                </div>
                
                <div class="form-group">
                    <label>Senha SMTP *</label>
                    <input type="password" name="smtp_password" value="<?php echo $config['smtp_password'] ?? ''; ?>" required placeholder="Senha ou senha de app">
                    <small style="color: #6b7280;">Gmail: use senha de app (não a senha normal)</small>
                </div>
            </div>
            
            <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #1e40af;">Informações do Remetente</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label>E-mail Remetente *</label>
                    <input type="email" name="from_email" value="<?php echo $config['from_email'] ?? ''; ?>" required placeholder="noreply@inlaudo.com.br">
                </div>
                
                <div class="form-group">
                    <label>Nome do Remetente *</label>
                    <input type="text" name="from_name" value="<?php echo $config['from_name'] ?? 'ERP INLAUDO'; ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>E-mail para Resposta (Reply-To)</label>
                <input type="email" name="reply_to_email" value="<?php echo $config['reply_to_email'] ?? ''; ?>" placeholder="contato@inlaudo.com.br">
                <small style="color: #6b7280;">Deixe em branco para usar o e-mail remetente</small>
            </div>
            
            <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #1e40af;">Opções</h3>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="ativo" value="1" <?php echo ($config['ativo'] ?? false) ? 'checked' : ''; ?>>
                    <span>Configuração Ativa</span>
                </label>
                <small style="color: #6b7280; display: block; margin-top: 0.5rem;">
                    Apenas uma configuração pode estar ativa por vez. Ao marcar esta, as outras serão desativadas.
                </small>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="testar_envio" value="1" <?php echo ($config['testar_envio'] ?? false) ? 'checked' : ''; ?> onchange="toggleEmailTeste(this)">
                    <span>Modo de Teste</span>
                </label>
                <small style="color: #6b7280; display: block; margin-top: 0.5rem;">
                    Quando ativado, todos os e-mails serão enviados apenas para o e-mail de teste abaixo.
                </small>
            </div>
            
            <div class="form-group" id="email_teste_group" style="display: <?php echo ($config['testar_envio'] ?? false) ? 'block' : 'none'; ?>;">
                <label>E-mail para Testes</label>
                <input type="email" name="email_teste_config" value="<?php echo $config['email_teste'] ?? ''; ?>" placeholder="teste@inlaudo.com.br">
                <small style="color: #6b7280;">Todos os e-mails serão redirecionados para este endereço no modo de teste</small>
            </div>
            
            <div class="btn-group">
                <button type="submit" name="salvar" class="btn btn-primary">Salvar Configuração</button>
                <a href="email_config.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
    
    <?php if ($config): ?>
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <h3>Testar Configuração</h3>
        </div>
        
        <form method="POST">
            <input type="hidden" name="config_id" value="<?php echo $config['id']; ?>">
            
            <div class="form-group">
                <label>E-mail para Teste *</label>
                <input type="email" name="email_teste" required placeholder="seu-email@gmail.com">
                <small style="color: #6b7280;">Um e-mail de teste será enviado para este endereço</small>
            </div>
            
            <button type="submit" name="testar" class="btn btn-success">Enviar E-mail de Teste</button>
        </form>
    </div>
    <?php endif; ?>
    
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <h2>Configurações Cadastradas</h2>
            <?php if (!$config): ?>
                <a href="email_config.php" class="btn btn-primary">Nova Configuração</a>
            <?php endif; ?>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Servidor SMTP</th>
                        <th>Porta</th>
                        <th>Usuário</th>
                        <th>Status</th>
                        <th>Modo Teste</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($configuracoes)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem;">
                                Nenhuma configuração cadastrada. Clique em "Nova Configuração" para começar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($configuracoes as $cfg): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($cfg['nome_config']); ?></strong></td>
                                <td><?php echo htmlspecialchars($cfg['smtp_host']); ?></td>
                                <td><?php echo $cfg['smtp_port']; ?></td>
                                <td><?php echo htmlspecialchars($cfg['smtp_user']); ?></td>
                                <td>
                                    <?php if ($cfg['ativo']): ?>
                                        <span class="badge badge-pago">Ativa</span>
                                    <?php else: ?>
                                        <span class="badge badge-cancelado">Inativa</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($cfg['testar_envio']): ?>
                                        <span class="badge badge-pendente">Teste</span>
                                    <?php else: ?>
                                        <span class="badge badge-cliente">Produção</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="email_config.php?editar=<?php echo $cfg['id']; ?>" class="btn btn-primary">Editar</a>
                                        <a href="email_config.php?deletar=<?php echo $cfg['id']; ?>" 
                                           class="btn btn-danger"
                                           onclick="return confirm('Tem certeza que deseja excluir esta configuração?')">
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
    
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <h3>📚 Guia de Configuração</h3>
        </div>
        
        <div style="padding: 1.5rem;">
            <h4 style="color: #2563eb; margin-bottom: 0.5rem;">Gmail</h4>
            <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                <li>Servidor: <code>smtp.gmail.com</code></li>
                <li>Porta: <code>587</code> (TLS)</li>
                <li>Você precisa criar uma <strong>Senha de App</strong>:
                    <ol style="margin-left: 1.5rem; margin-top: 0.5rem;">
                        <li>Acesse <a href="https://myaccount.google.com/security" target="_blank">Segurança da Conta Google</a></li>
                        <li>Ative "Verificação em duas etapas"</li>
                        <li>Vá em "Senhas de app"</li>
                        <li>Gere uma senha para "E-mail"</li>
                        <li>Use essa senha no campo "Senha SMTP"</li>
                    </ol>
                </li>
            </ul>
            
            <h4 style="color: #2563eb; margin-bottom: 0.5rem;">Outlook/Hotmail</h4>
            <ul style="margin-left: 1.5rem; margin-bottom: 1rem;">
                <li>Servidor: <code>smtp-mail.outlook.com</code></li>
                <li>Porta: <code>587</code> (TLS)</li>
                <li>Use sua senha normal do Outlook</li>
            </ul>
            
            <h4 style="color: #2563eb; margin-bottom: 0.5rem;">Outros Provedores</h4>
            <p>Consulte a documentação do seu provedor de e-mail para obter as configurações SMTP corretas.</p>
        </div>
    </div>
</div>

<script>
    function toggleEmailTeste(checkbox) {
        const emailTesteGroup = document.getElementById('email_teste_group');
        emailTesteGroup.style.display = checkbox.checked ? 'block' : 'none';
    }
</script>

<?php include 'footer.php'; ?>
