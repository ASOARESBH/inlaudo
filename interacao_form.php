<?php
require_once 'config.php';

$pageTitle = 'Cadastro de Interação';
$conn = getConnection();

// Verificar se é edição
$interacaoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$interacao = null;

if ($interacaoId > 0) {
    $stmt = $conn->prepare("SELECT * FROM interacoes WHERE id = ?");
    $stmt->execute([$interacaoId]);
    $interacao = $stmt->fetch();
    
    if (!$interacao) {
        header('Location: interacoes.php');
        exit;
    }
}

// Buscar clientes
$stmtClientes = $conn->query("SELECT id, nome, razao_social, nome_fantasia, tipo_pessoa FROM clientes ORDER BY razao_social, nome");
$clientes = $stmtClientes->fetchAll();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $clienteId = (int)$_POST['cliente_id'];
    $dataHoraInteracao = $_POST['data_hora_interacao'];
    $formaContato = sanitize($_POST['forma_contato']);
    $historico = sanitize($_POST['historico']);
    $proximoContatoData = !empty($_POST['proximo_contato_data']) ? $_POST['proximo_contato_data'] : null;
    $proximoContatoForma = !empty($_POST['proximo_contato_forma']) ? sanitize($_POST['proximo_contato_forma']) : null;
    
    try {
        if ($interacaoId > 0) {
            // Atualizar
            $sql = "UPDATE interacoes SET 
                    cliente_id = ?, data_hora_interacao = ?, forma_contato = ?, 
                    historico = ?, proximo_contato_data = ?, proximo_contato_forma = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $clienteId, $dataHoraInteracao, $formaContato, $historico,
                $proximoContatoData, $proximoContatoForma, $interacaoId
            ]);
            $mensagem = "Interação atualizada com sucesso!";
        } else {
            // Inserir
            $sql = "INSERT INTO interacoes (
                    cliente_id, data_hora_interacao, forma_contato, historico,
                    proximo_contato_data, proximo_contato_forma
                    ) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $clienteId, $dataHoraInteracao, $formaContato, $historico,
                $proximoContatoData, $proximoContatoForma
            ]);
            $mensagem = "Interação cadastrada com sucesso!";
        }
        
        header('Location: interacoes.php?msg=' . urlencode($mensagem));
        exit;
        
    } catch (PDOException $e) {
        $erro = "Erro ao salvar interação: " . $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2><?php echo $interacaoId > 0 ? 'Editar Interação' : 'Nova Interação'; ?></h2>
        </div>
        
        <?php if (isset($erro)): ?>
            <div class="alert alert-error"><?php echo $erro; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Cliente *</label>
                <select name="cliente_id" required>
                    <option value="">Selecione o cliente...</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" 
                                <?php echo ($interacao && $interacao['cliente_id'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php 
                            echo $c['tipo_pessoa'] == 'CNPJ' 
                                ? ($c['razao_social'] ?: $c['nome_fantasia']) 
                                : $c['nome']; 
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Data e Hora da Interação *</label>
                    <input type="datetime-local" name="data_hora_interacao" required 
                           value="<?php echo $interacao ? date('Y-m-d\TH:i', strtotime($interacao['data_hora_interacao'])) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Forma de Contato *</label>
                    <select name="forma_contato" required>
                        <option value="">Selecione...</option>
                        <option value="telefone" <?php echo ($interacao && $interacao['forma_contato'] == 'telefone') ? 'selected' : ''; ?>>Telefone</option>
                        <option value="e-mail" <?php echo ($interacao && $interacao['forma_contato'] == 'e-mail') ? 'selected' : ''; ?>>E-mail</option>
                        <option value="presencial" <?php echo ($interacao && $interacao['forma_contato'] == 'presencial') ? 'selected' : ''; ?>>Presencial</option>
                        <option value="whatsapp" <?php echo ($interacao && $interacao['forma_contato'] == 'whatsapp') ? 'selected' : ''; ?>>WhatsApp</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Histórico da Interação *</label>
                <textarea name="historico" required rows="6"><?php echo $interacao ? htmlspecialchars($interacao['historico']) : ''; ?></textarea>
            </div>
            
            <div style="border-top: 2px solid #e5e7eb; padding-top: 1.5rem; margin-top: 1.5rem;">
                <h3 style="margin-bottom: 1rem; color: #1e40af;">Próximo Contato</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Data e Hora do Próximo Contato</label>
                        <input type="datetime-local" name="proximo_contato_data" 
                               value="<?php echo $interacao && $interacao['proximo_contato_data'] ? date('Y-m-d\TH:i', strtotime($interacao['proximo_contato_data'])) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Forma de Contato</label>
                        <select name="proximo_contato_forma">
                            <option value="">Selecione...</option>
                            <option value="telefone" <?php echo ($interacao && $interacao['proximo_contato_forma'] == 'telefone') ? 'selected' : ''; ?>>Telefone</option>
                            <option value="e-mail" <?php echo ($interacao && $interacao['proximo_contato_forma'] == 'e-mail') ? 'selected' : ''; ?>>E-mail</option>
                            <option value="presencial" <?php echo ($interacao && $interacao['proximo_contato_forma'] == 'presencial') ? 'selected' : ''; ?>>Presencial</option>
                            <option value="whatsapp" <?php echo ($interacao && $interacao['proximo_contato_forma'] == 'whatsapp') ? 'selected' : ''; ?>>WhatsApp</option>
                        </select>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <strong>Alerta:</strong> Se você definir uma data e hora para o próximo contato, o sistema criará um lembrete automático.
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-success">Salvar</button>
                <a href="interacoes.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
