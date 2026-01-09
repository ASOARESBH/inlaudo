<?php
/**
 * Portal do Cliente - Meus Dados (Alterar Senha)
 */

session_start();
require_once 'config.php';
require_once 'header_cliente.php';

$conn = getConnection();
$usuario_id = $_SESSION['usuario_id'];
$cliente_id = $_SESSION['cliente_id'];

$mensagem = '';
$erro = '';

// Processar alteração de senha
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['alterar_senha'])) {
    $senha_atual = $_POST['senha_atual'] ?? '';
    $senha_nova = $_POST['senha_nova'] ?? '';
    $senha_confirma = $_POST['senha_confirma'] ?? '';
    
    if (empty($senha_atual) || empty($senha_nova) || empty($senha_confirma)) {
        $erro = 'Por favor, preencha todos os campos.';
    } elseif ($senha_nova !== $senha_confirma) {
        $erro = 'A nova senha e a confirmação não coincidem.';
    } elseif (strlen($senha_nova) < 6) {
        $erro = 'A nova senha deve ter no mínimo 6 caracteres.';
    } else {
        // Buscar usuário
        $stmt = $conn->prepare("SELECT senha FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
        
        // Verificar senha atual
        if (!password_verify($senha_atual, $usuario['senha'])) {
            $erro = 'Senha atual incorreta.';
        } else {
            // Atualizar senha
            $nova_senha_hash = password_hash($senha_nova, PASSWORD_BCRYPT);
            $stmtUpdate = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $stmtUpdate->execute([$nova_senha_hash, $usuario_id]);
            
            $mensagem = 'Senha alterada com sucesso!';
            
            // Limpar campos
            $_POST = [];
        }
    }
}
?>

<h1 style="color: #1e293b; margin: 0 0 25px 0;">Meus Dados</h1>

<?php if ($mensagem): ?>
    <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #10b981;">
        ✓ <?php echo htmlspecialchars($mensagem); ?>
    </div>
<?php endif; ?>

<?php if ($erro): ?>
    <div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #dc2626;">
        ✗ <?php echo htmlspecialchars($erro); ?>
    </div>
<?php endif; ?>

<!-- Informações do Cliente -->
<div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 30px;">
    <h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 1.3rem;">Informações da Empresa</h2>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <div>
            <p style="color: #64748b; font-size: 0.9rem; margin: 0 0 5px 0;">Razão Social</p>
            <p style="color: #1e293b; font-weight: 600; font-size: 1.1rem; margin: 0;"><?php echo htmlspecialchars($cliente['nome']); ?></p>
        </div>
        
        <?php if (!empty($cliente['nome_fantasia'])): ?>
        <div>
            <p style="color: #64748b; font-size: 0.9rem; margin: 0 0 5px 0;">Nome Fantasia</p>
            <p style="color: #1e293b; font-weight: 600; font-size: 1.1rem; margin: 0;"><?php echo htmlspecialchars($cliente['nome_fantasia']); ?></p>
        </div>
        <?php endif; ?>
        
        <div>
            <p style="color: #64748b; font-size: 0.9rem; margin: 0 0 5px 0;">CNPJ/CPF</p>
            <p style="color: #1e293b; font-weight: 600; font-size: 1.1rem; margin: 0;">
                <?php 
                    if (strlen($cliente['cnpj']) == 14) {
                        echo formatarCNPJ($cliente['cnpj']);
                    } else {
                        echo formatarCPF($cliente['cnpj']);
                    }
                ?>
            </p>
        </div>
        
        <div>
            <p style="color: #64748b; font-size: 0.9rem; margin: 0 0 5px 0;">E-mail</p>
            <p style="color: #1e293b; font-weight: 600; font-size: 1.1rem; margin: 0;"><?php echo htmlspecialchars($cliente['email']); ?></p>
        </div>
        
        <div>
            <p style="color: #64748b; font-size: 0.9rem; margin: 0 0 5px 0;">Telefone</p>
            <p style="color: #1e293b; font-weight: 600; font-size: 1.1rem; margin: 0;"><?php echo htmlspecialchars($cliente['telefone']); ?></p>
        </div>
        
        <div>
            <p style="color: #64748b; font-size: 0.9rem; margin: 0 0 5px 0;">Tipo</p>
            <p style="color: #1e293b; font-weight: 600; font-size: 1.1rem; margin: 0;">
                <?php echo $cliente['tipo'] == 'lead' ? '🎯 Lead' : '✓ Cliente'; ?>
            </p>
        </div>
    </div>
    
    <?php if (!empty($cliente['endereco'])): ?>
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
            <p style="color: #64748b; font-size: 0.9rem; margin: 0 0 5px 0;">Endereço</p>
            <p style="color: #1e293b; font-weight: 600; margin: 0;"><?php echo htmlspecialchars($cliente['endereco']); ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Alterar Senha -->
<div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
    <h2 style="color: #1e293b; margin: 0 0 10px 0; font-size: 1.3rem;">Alterar Senha</h2>
    <p style="color: #64748b; margin: 0 0 25px 0;">Por segurança, recomendamos alterar a senha padrão.</p>
    
    <form method="POST" style="max-width: 500px;">
        <input type="hidden" name="alterar_senha" value="1">
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; color: #64748b; font-weight: 600; margin-bottom: 8px;">Senha Atual *</label>
            <input type="password" name="senha_atual" required
                   style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; color: #64748b; font-weight: 600; margin-bottom: 8px;">Nova Senha * (mínimo 6 caracteres)</label>
            <input type="password" name="senha_nova" required minlength="6"
                   style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
        </div>
        
        <div style="margin-bottom: 25px;">
            <label style="display: block; color: #64748b; font-weight: 600; margin-bottom: 8px;">Confirmar Nova Senha *</label>
            <input type="password" name="senha_confirma" required minlength="6"
                   style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
        </div>
        
        <button type="submit" style="padding: 14px 32px; background: #10b981; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer;">
            Alterar Senha
        </button>
    </form>
</div>

<!-- Informações de Segurança -->
<div style="background: #dbeafe; padding: 20px; border-radius: 12px; margin-top: 30px; border-left: 4px solid #3b82f6;">
    <h3 style="color: #1e40af; margin: 0 0 10px 0; font-size: 1.1rem;">🔐 Dicas de Segurança</h3>
    <ul style="color: #1e40af; margin: 0; padding-left: 20px; line-height: 1.8;">
        <li>Use uma senha forte com letras, números e caracteres especiais</li>
        <li>Não compartilhe sua senha com outras pessoas</li>
        <li>Altere sua senha periodicamente</li>
        <li>Não use a mesma senha em diferentes sistemas</li>
        <li>Faça logout ao terminar de usar o sistema</li>
    </ul>
</div>

<?php require_once 'footer_cliente.php'; ?>
