<?php
/**
 * Upload de Nota Fiscal - Contas a Receber
 * ERP INLAUDO - Versão 7.0
 */

$pageTitle = 'Upload de Nota Fiscal';
require_once 'header.php';
require_once 'config.php';

$contaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mensagem = '';
$tipo = '';

if (!$contaId) {
    header('Location: contas_receber.php');
    exit;
}

$conn = getConnection();

// Buscar conta
$stmt = $conn->prepare("SELECT * FROM contas_receber WHERE id = ?");
$stmt->execute([$contaId]);
$conta = $stmt->fetch();

if (!$conta) {
    header('Location: contas_receber.php');
    exit;
}

// Processar upload
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $nfNumero = trim($_POST['nf_numero'] ?? '');
        $nfDataEmissao = $_POST['nf_data_emissao'] ?? null;
        $nfValor = (float)str_replace(',', '.', str_replace('.', '', $_POST['nf_valor'] ?? '0'));
        
        // Upload de arquivo
        $nfArquivo = $conta['nf_arquivo'];
        
        if (isset($_FILES['nf_arquivo']) && $_FILES['nf_arquivo']['error'] == 0) {
            $arquivo = $_FILES['nf_arquivo'];
            $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
            $extensoesPermitidas = ['pdf', 'xml', 'jpg', 'jpeg', 'png'];
            
            if (in_array($extensao, $extensoesPermitidas)) {
                $nomeArquivo = 'nf_' . $contaId . '_' . time() . '.' . $extensao;
                $caminhoDestino = 'uploads/nf/' . $nomeArquivo;
                
                if (move_uploaded_file($arquivo['tmp_name'], $caminhoDestino)) {
                    // Remover arquivo antigo se existir
                    if ($conta['nf_arquivo'] && file_exists($conta['nf_arquivo'])) {
                        unlink($conta['nf_arquivo']);
                    }
                    $nfArquivo = $caminhoDestino;
                }
            } else {
                throw new Exception('Extensão de arquivo não permitida');
            }
        }
        
        // Atualizar banco
        $stmt = $conn->prepare("
            UPDATE contas_receber 
            SET nf_numero = ?, nf_arquivo = ?, nf_data_emissao = ?, nf_valor = ?
            WHERE id = ?
        ");
        $stmt->execute([$nfNumero, $nfArquivo, $nfDataEmissao, $nfValor, $contaId]);
        
        $mensagem = 'Nota Fiscal salva com sucesso!';
        $tipo = 'sucesso';
        
        // Recarregar dados
        $stmt = $conn->prepare("SELECT * FROM contas_receber WHERE id = ?");
        $stmt->execute([$contaId]);
        $conta = $stmt->fetch();
        
    } catch (Exception $e) {
        $mensagem = 'Erro ao salvar: ' . $e->getMessage();
        $tipo = 'erro';
    }
}
?>

<div class="container">
    <div class="page-header">
        <h1>📄 Upload de Nota Fiscal</h1>
        <p>Conta a Receber #<?php echo $contaId; ?> - <?php echo htmlspecialchars($conta['descricao']); ?></p>
    </div>

    <?php if ($mensagem): ?>
        <div class="alert alert-<?php echo $tipo; ?>">
            <?php echo htmlspecialchars($mensagem); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="nf_numero">Número da NF *</label>
                        <input type="text" 
                               class="form-control" 
                               id="nf_numero" 
                               name="nf_numero" 
                               value="<?php echo htmlspecialchars($conta['nf_numero'] ?? ''); ?>"
                               placeholder="Ex: 12345"
                               required>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="nf_data_emissao">Data de Emissão *</label>
                        <input type="date" 
                               class="form-control" 
                               id="nf_data_emissao" 
                               name="nf_data_emissao" 
                               value="<?php echo $conta['nf_data_emissao'] ?? ''; ?>"
                               required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="nf_valor">Valor da NF *</label>
                        <input type="text" 
                               class="form-control" 
                               id="nf_valor" 
                               name="nf_valor" 
                               value="<?php echo $conta['nf_valor'] ? number_format($conta['nf_valor'], 2, ',', '.') : ''; ?>"
                               placeholder="0,00"
                               required>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="nf_arquivo">Arquivo da NF (PDF, XML, JPG, PNG) *</label>
                        <input type="file" 
                               class="form-control" 
                               id="nf_arquivo" 
                               name="nf_arquivo" 
                               accept=".pdf,.xml,.jpg,.jpeg,.png"
                               <?php echo $conta['nf_arquivo'] ? '' : 'required'; ?>>
                        <?php if ($conta['nf_arquivo']): ?>
                            <small class="form-text">
                                Arquivo atual: <a href="<?php echo $conta['nf_arquivo']; ?>" target="_blank">Visualizar NF</a>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        💾 Salvar Nota Fiscal
                    </button>
                    <a href="contas_receber.php" class="btn btn-secondary">
                        ← Voltar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($conta['nf_arquivo']): ?>
    <div class="card mt-4">
        <div class="card-header">
            <h3>📄 Nota Fiscal Anexada</h3>
        </div>
        <div class="card-body">
            <div class="nf-info">
                <p><strong>Número:</strong> <?php echo htmlspecialchars($conta['nf_numero'] ?? 'N/A'); ?></p>
                <p><strong>Data de Emissão:</strong> <?php echo $conta['nf_data_emissao'] ? date('d/m/Y', strtotime($conta['nf_data_emissao'])) : 'N/A'; ?></p>
                <p><strong>Valor:</strong> R$ <?php echo number_format($conta['nf_valor'] ?? 0, 2, ',', '.'); ?></p>
                <p><strong>Arquivo:</strong> <a href="<?php echo $conta['nf_arquivo']; ?>" target="_blank" class="btn btn-sm btn-primary">📥 Baixar NF</a></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.card-header {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 20px;
    border-radius: 10px 10px 0 0;
}

.card-header h3 {
    margin: 0;
    font-size: 1.3rem;
}

.card-body {
    padding: 30px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    font-weight: 600;
    color: #475569;
    margin-bottom: 8px;
    display: block;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
}

.form-control:focus {
    outline: none;
    border-color: #10b981;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.form-actions {
    margin-top: 30px;
    display: flex;
    gap: 10px;
}

.btn {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-secondary {
    background: #64748b;
    color: white;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 0.9rem;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-sucesso {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-erro {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #dc2626;
}

.nf-info p {
    margin: 10px 0;
    color: #475569;
}

.nf-info strong {
    color: #1e293b;
}
</style>

<?php require_once 'footer.php'; ?>
