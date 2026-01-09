<?php
require_once 'config.php';
require_once 'lib_email.php';

$pageTitle = 'Templates de E-mail';

$mensagem = '';
$erro = '';

// Processar envio de teste
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['testar_template'])) {
    $templateId = (int)$_POST['template_id'];
    $emailTeste = sanitize($_POST['email_teste']);
    
    if (empty($emailTeste) || !filter_var($emailTeste, FILTER_VALIDATE_EMAIL)) {
        $erro = "Por favor, informe um e-mail válido para teste.";
    } else {
        // Variáveis de exemplo para teste
        $variaveis = [
            'descricao' => 'Exemplo de Descrição',
            'fornecedor' => 'Fornecedor Teste Ltda',
            'cliente' => 'Cliente Teste',
            'valor' => 'R$ 1.500,00',
            'data_vencimento' => date('d/m/Y', strtotime('+5 days')),
            'dias_restantes' => '5',
            'dias_atraso' => '3',
            'plano_contas' => 'Despesas Operacionais',
            'contato_cliente' => 'cliente@teste.com',
            'data_hora' => date('d/m/Y H:i'),
            'forma_contato' => 'Telefone',
            'historico' => 'Último contato foi em ' . date('d/m/Y'),
            'link_sistema' => 'https://seusite.com/erp-inlaudo'
        ];
        
        $resultado = EmailSender::enviarComTemplate($templateId, $emailTeste, $variaveis, 'Teste', 'teste_template', $templateId);
        
        if ($resultado['sucesso']) {
            $mensagem = "E-mail de teste enviado com sucesso para $emailTeste!";
        } else {
            $erro = "Erro ao enviar e-mail de teste: " . $resultado['mensagem'];
        }
    }
}

// Processar formulário de salvar/atualizar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar'])) {
    $templateId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $codigo = sanitize($_POST['codigo']);
    $nome = sanitize($_POST['nome']);
    $descricao = sanitize($_POST['descricao']);
    $assunto = sanitize($_POST['assunto']);
    $corpoHtml = $_POST['corpo_html']; // Não sanitizar HTML
    $corpoTexto = $_POST['corpo_texto'];
    $variaveisDisponiveis = sanitize($_POST['variaveis_disponiveis'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $enviarAutomatico = isset($_POST['enviar_automatico']) ? 1 : 0;
    $diasAntecedencia = (int)$_POST['dias_antecedencia'];
    $destinatariosPadrao = sanitize($_POST['destinatarios_padrao'] ?? '');
    $categoria = sanitize($_POST['categoria']);
    
    try {
        $conn = getConnection();
        
        if ($templateId > 0) {
            // Atualizar
            $sql = "UPDATE email_templates SET 
                    codigo = ?, nome = ?, descricao = ?, assunto = ?, corpo_html = ?,
                    corpo_texto = ?, variaveis_disponiveis = ?, ativo = ?,
                    enviar_automatico = ?, dias_antecedencia = ?, destinatarios_padrao = ?, categoria = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $codigo, $nome, $descricao, $assunto, $corpoHtml,
                $corpoTexto, $variaveisDisponiveis, $ativo,
                $enviarAutomatico, $diasAntecedencia, $destinatariosPadrao, $categoria, $templateId
            ]);
            $mensagem = "Template atualizado com sucesso!";
        } else {
            // Inserir
            $sql = "INSERT INTO email_templates (
                        codigo, nome, descricao, assunto, corpo_html, corpo_texto,
                        variaveis_disponiveis, ativo, enviar_automatico, dias_antecedencia,
                        destinatarios_padrao, categoria
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $codigo, $nome, $descricao, $assunto, $corpoHtml, $corpoTexto,
                $variaveisDisponiveis, $ativo, $enviarAutomatico, $diasAntecedencia,
                $destinatariosPadrao, $categoria
            ]);
            $mensagem = "Template criado com sucesso!";
        }
        
        header('Location: email_templates.php?msg=' . urlencode($mensagem));
        exit;
        
    } catch (PDOException $e) {
        $erro = "Erro ao salvar template: " . $e->getMessage();
    }
}

// Processar exclusão
if (isset($_GET['deletar'])) {
    $templateId = (int)$_GET['deletar'];
    try {
        $conn = getConnection();
        $conn->prepare("DELETE FROM email_templates WHERE id = ?")->execute([$templateId]);
        $mensagem = "Template excluído com sucesso!";
    } catch (PDOException $e) {
        $erro = "Erro ao excluir template: " . $e->getMessage();
    }
}

// Buscar templates
$conn = getConnection();
$stmt = $conn->query("SELECT * FROM email_templates ORDER BY categoria, nome");
$templates = $stmt->fetchAll();

// Se está editando
$template = null;
if (isset($_GET['editar'])) {
    $templateId = (int)$_GET['editar'];
    $stmt = $conn->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();
}

// Mensagem da URL
if (isset($_GET['msg'])) {
    $mensagem = $_GET['msg'];
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
    
    <?php if (isset($_GET['editar']) || isset($_GET['novo'])): ?>
    <div class="card">
        <div class="card-header">
            <h2><?php echo $template ? 'Editar' : 'Novo'; ?> Template de E-mail</h2>
        </div>
        
        <form method="POST">
            <?php if ($template): ?>
                <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Código do Template *</label>
                    <input type="text" name="codigo" value="<?php echo $template['codigo'] ?? ''; ?>" required pattern="[a-z0-9_]+" placeholder="conta_pagar_vencendo">
                    <small style="color: #6b7280;">Apenas letras minúsculas, números e underscore</small>
                </div>
                
                <div class="form-group">
                    <label>Nome do Template *</label>
                    <input type="text" name="nome" value="<?php echo $template['nome'] ?? ''; ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Descrição</label>
                <textarea name="descricao" rows="2"><?php echo $template['descricao'] ?? ''; ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Categoria *</label>
                    <select name="categoria" required>
                        <option value="alerta" <?php echo ($template['categoria'] ?? 'alerta') == 'alerta' ? 'selected' : ''; ?>>Alerta</option>
                        <option value="notificacao" <?php echo ($template['categoria'] ?? '') == 'notificacao' ? 'selected' : ''; ?>>Notificação</option>
                        <option value="relatorio" <?php echo ($template['categoria'] ?? '') == 'relatorio' ? 'selected' : ''; ?>>Relatório</option>
                        <option value="cobranca" <?php echo ($template['categoria'] ?? '') == 'cobranca' ? 'selected' : ''; ?>>Cobrança</option>
                        <option value="sistema" <?php echo ($template['categoria'] ?? '') == 'sistema' ? 'selected' : ''; ?>>Sistema</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Dias de Antecedência</label>
                    <input type="number" name="dias_antecedencia" value="<?php echo $template['dias_antecedencia'] ?? 0; ?>" min="0">
                    <small style="color: #6b7280;">Para alertas automáticos, quantos dias antes enviar</small>
                </div>
            </div>
            
            <div class="form-group">
                <label>Assunto do E-mail *</label>
                <input type="text" name="assunto" value="<?php echo $template['assunto'] ?? ''; ?>" required placeholder="Use {{variavel}} para inserir variáveis">
            </div>
            
            <div class="form-group">
                <label>Corpo do E-mail (HTML) *</label>
                <textarea name="corpo_html" rows="15" required style="font-family: monospace; font-size: 12px;"><?php echo htmlspecialchars($template['corpo_html'] ?? ''); ?></textarea>
                <small style="color: #6b7280;">Use {{variavel}} para inserir variáveis dinâmicas</small>
            </div>
            
            <div class="form-group">
                <label>Corpo do E-mail (Texto Puro)</label>
                <textarea name="corpo_texto" rows="8"><?php echo $template['corpo_texto'] ?? ''; ?></textarea>
                <small style="color: #6b7280;">Versão em texto puro para clientes que não suportam HTML</small>
            </div>
            
            <div class="form-group">
                <label>Variáveis Disponíveis (JSON)</label>
                <textarea name="variaveis_disponiveis" rows="4" style="font-family: monospace; font-size: 12px;"><?php echo $template['variaveis_disponiveis'] ?? ''; ?></textarea>
                <small style="color: #6b7280;">Formato: {"variavel": "Descrição", "outra": "Outra descrição"}</small>
            </div>
            
            <div class="form-group">
                <label>Destinatários Padrão</label>
                <input type="text" name="destinatarios_padrao" value="<?php echo $template['destinatarios_padrao'] ?? ''; ?>" placeholder="email1@exemplo.com, email2@exemplo.com">
                <small style="color: #6b7280;">E-mails separados por vírgula que sempre receberão este alerta</small>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="ativo" value="1" <?php echo ($template['ativo'] ?? true) ? 'checked' : ''; ?>>
                    <span>Template Ativo</span>
                </label>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="enviar_automatico" value="1" <?php echo ($template['enviar_automatico'] ?? false) ? 'checked' : ''; ?>>
                    <span>Enviar Automaticamente</span>
                </label>
                <small style="color: #6b7280; display: block; margin-top: 0.5rem;">
                    Quando marcado, o sistema enviará este e-mail automaticamente quando o evento ocorrer
                </small>
            </div>
            
            <div class="btn-group">
                <button type="submit" name="salvar" class="btn btn-primary">Salvar Template</button>
                <a href="email_templates.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <h2>Templates Cadastrados</h2>
            <a href="email_templates.php?novo=1" class="btn btn-primary">Novo Template</a>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nome</th>
                        <th>Categoria</th>
                        <th>Status</th>
                        <th>Envio Auto</th>
                        <th>Dias Antec.</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($templates)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem;">
                                Nenhum template cadastrado. Execute o script database_update_v4.sql para criar os templates padrão.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($templates as $tpl): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($tpl['codigo']); ?></code></td>
                                <td><strong><?php echo htmlspecialchars($tpl['nome']); ?></strong></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $tpl['categoria'] == 'alerta' ? 'vencido' : 
                                            ($tpl['categoria'] == 'cobranca' ? 'pendente' : 'cliente'); 
                                    ?>">
                                        <?php echo ucfirst($tpl['categoria']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($tpl['ativo']): ?>
                                        <span class="badge badge-pago">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge badge-cancelado">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tpl['enviar_automatico']): ?>
                                        <span class="badge badge-cliente">Sim</span>
                                    <?php else: ?>
                                        <span class="badge badge-cancelado">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $tpl['dias_antecedencia']; ?> dias</td>
                                <td>
                                    <div class="actions">
                                        <a href="email_templates.php?editar=<?php echo $tpl['id']; ?>" class="btn btn-primary">Editar</a>
                                        <button onclick="testarTemplate(<?php echo $tpl['id']; ?>)" class="btn btn-success">Testar</button>
                                        <a href="email_templates.php?deletar=<?php echo $tpl['id']; ?>" 
                                           class="btn btn-danger"
                                           onclick="return confirm('Tem certeza que deseja excluir este template?')">
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
            <h3>📚 Guia de Uso de Variáveis</h3>
        </div>
        
        <div style="padding: 1.5rem;">
            <p>Use variáveis no formato <code>{{nome_variavel}}</code> para inserir conteúdo dinâmico nos templates.</p>
            
            <h4 style="color: #2563eb; margin-top: 1rem; margin-bottom: 0.5rem;">Variáveis Comuns</h4>
            <ul style="margin-left: 1.5rem;">
                <li><code>{{descricao}}</code> - Descrição da conta ou item</li>
                <li><code>{{cliente}}</code> - Nome do cliente</li>
                <li><code>{{fornecedor}}</code> - Nome do fornecedor</li>
                <li><code>{{valor}}</code> - Valor formatado (ex: R$ 1.500,00)</li>
                <li><code>{{data_vencimento}}</code> - Data de vencimento</li>
                <li><code>{{dias_restantes}}</code> - Dias até vencer</li>
                <li><code>{{dias_atraso}}</code> - Dias em atraso</li>
                <li><code>{{link_sistema}}</code> - Link para o sistema</li>
            </ul>
            
            <h4 style="color: #2563eb; margin-top: 1rem; margin-bottom: 0.5rem;">Exemplo de Uso</h4>
            <pre style="background: #f3f4f6; padding: 1rem; border-radius: 4px; overflow-x: auto;">
Assunto: Alerta: Conta Vencendo - {{descricao}}

Corpo HTML:
&lt;p&gt;Olá!&lt;/p&gt;
&lt;p&gt;A conta &lt;strong&gt;{{descricao}}&lt;/strong&gt; no valor de &lt;strong&gt;{{valor}}&lt;/strong&gt; vence em {{dias_restantes}} dias.&lt;/p&gt;
&lt;p&gt;Data de vencimento: {{data_vencimento}}&lt;/p&gt;
            </pre>
        </div>
    </div>
</div>

<!-- Modal para teste de template -->
<div id="modalTesteTemplate" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 2rem; border-radius: 8px; max-width: 500px; width: 90%;">
        <h3 style="margin-bottom: 1rem;">Testar Template</h3>
        <form method="POST">
            <input type="hidden" name="template_id" id="template_id_teste">
            <div class="form-group">
                <label>E-mail para Teste *</label>
                <input type="email" name="email_teste" required placeholder="seu-email@exemplo.com">
                <small style="color: #6b7280;">Um e-mail de teste será enviado com variáveis de exemplo</small>
            </div>
            <div class="btn-group">
                <button type="submit" name="testar_template" class="btn btn-success">Enviar Teste</button>
                <button type="button" onclick="fecharModal()" class="btn btn-secondary">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function testarTemplate(templateId) {
        document.getElementById('template_id_teste').value = templateId;
        const modal = document.getElementById('modalTesteTemplate');
        modal.style.display = 'flex';
    }
    
    function fecharModal() {
        const modal = document.getElementById('modalTesteTemplate');
        modal.style.display = 'none';
    }
    
    // Fechar modal ao clicar fora
    document.getElementById('modalTesteTemplate').addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModal();
        }
    });
</script>

<?php include 'footer.php'; ?>
