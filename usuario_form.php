<?php
require_once 'auth.php';
verificarAdmin(); // Apenas administradores podem acessar

require_once 'config.php';

$pageTitle = 'Cadastro de Usuário';
$conn = getConnection();

// Verificar se é edição
$usuarioId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$usuario = null;

if ($usuarioId > 0) {
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$usuarioId]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        header('Location: usuarios.php');
        exit;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = sanitize($_POST['nome']);
    $email = sanitize($_POST['email']);
    $senha = $_POST['senha'] ?? '';
    $nivel = sanitize($_POST['nivel']);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    try {
        if ($usuarioId > 0) {
            // Atualizar
            if (!empty($senha)) {
                // Atualizar com nova senha
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET nome = ?, email = ?, senha = ?, nivel = ?, ativo = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$nome, $email, $senhaHash, $nivel, $ativo, $usuarioId]);
            } else {
                // Atualizar sem alterar senha
                $sql = "UPDATE usuarios SET nome = ?, email = ?, nivel = ?, ativo = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$nome, $email, $nivel, $ativo, $usuarioId]);
            }
            
            $_SESSION['mensagem'] = "Usuário atualizado com sucesso!";
        } else {
            // Inserir
            if (empty($senha)) {
                throw new Exception("Senha é obrigatória para novo usuário");
            }
            
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios (nome, email, senha, nivel, ativo) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nome, $email, $senhaHash, $nivel, $ativo]);
            
            $_SESSION['mensagem'] = "Usuário cadastrado com sucesso!";
        }
        
        header('Location: usuarios.php');
        exit;
    } catch (Exception $e) {
        $erro = "Erro ao salvar usuário: " . $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>
            <i class="fas fa-user"></i> 
            <?php echo $usuarioId > 0 ? 'Editar Usuário' : 'Novo Usuário'; ?>
        </h1>
        <a href="usuarios.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>

    <?php if (isset($erro)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($erro); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nome">Nome Completo *</label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="nome" 
                                name="nome" 
                                value="<?php echo $usuario ? htmlspecialchars($usuario['nome']) : ''; ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">E-mail *</label>
                            <input 
                                type="email" 
                                class="form-control" 
                                id="email" 
                                name="email" 
                                value="<?php echo $usuario ? htmlspecialchars($usuario['email']) : ''; ?>"
                                required
                            >
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="senha">
                                Senha <?php echo $usuarioId > 0 ? '(deixe em branco para manter a atual)' : '*'; ?>
                            </label>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="senha" 
                                name="senha"
                                <?php echo $usuarioId == 0 ? 'required' : ''; ?>
                                minlength="6"
                                placeholder="Mínimo 6 caracteres"
                            >
                            <small class="form-text text-muted">
                                A senha deve ter no mínimo 6 caracteres
                            </small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nivel">Nível de Acesso *</label>
                            <select class="form-control" id="nivel" name="nivel" required>
                                <option value="usuario" <?php echo ($usuario && $usuario['nivel'] == 'usuario') ? 'selected' : ''; ?>>
                                    Usuário
                                </option>
                                <option value="admin" <?php echo ($usuario && $usuario['nivel'] == 'admin') ? 'selected' : ''; ?>>
                                    Administrador
                                </option>
                            </select>
                            <small class="form-text text-muted">
                                Administrador tem acesso total ao sistema
                            </small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-check">
                        <input 
                            type="checkbox" 
                            class="form-check-input" 
                            id="ativo" 
                            name="ativo"
                            <?php echo (!$usuario || $usuario['ativo']) ? 'checked' : ''; ?>
                        >
                        <label class="form-check-label" for="ativo">
                            Usuário ativo (pode fazer login)
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                    <a href="usuarios.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    font-weight: 600;
    color: #334155;
    margin-bottom: 0.5rem;
}

.form-control {
    padding: 0.75rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
}

.form-control:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    outline: none;
}

.form-check {
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
}

.form-check-input {
    width: 1.25rem;
    height: 1.25rem;
    margin-top: 0.125rem;
}

.form-check-label {
    margin-left: 0.5rem;
    font-weight: 500;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 2px solid #e2e8f0;
}
</style>

<?php include 'footer.php'; ?>
