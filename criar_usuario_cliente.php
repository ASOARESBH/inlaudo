<?php
/**
 * Script para Criar Usuário Cliente Automaticamente
 * Cria usuário para cliente com CNPJ como login e senha padrão 123
 */

session_start();
require_once 'auth.php';
verificarAdmin(); // Apenas administradores podem acessar

require_once 'config.php';

$pageTitle = 'Criar Usuário Cliente';
$conn = getConnection();

$mensagem = '';
$erro = '';

// Buscar clientes sem usuário
$stmt = $conn->query("
    SELECT c.* 
    FROM clientes c
    LEFT JOIN usuarios u ON u.cliente_id = c.id AND u.tipo_usuario = 'cliente'
    WHERE u.id IS NULL
    ORDER BY c.nome
");
$clientesSemUsuario = $stmt->fetchAll();

// Processar criação de usuário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['criar_usuario'])) {
    $cliente_id = (int)$_POST['cliente_id'];
    
    // Buscar cliente
    $stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch();
    
    if ($cliente) {
        try {
            // Senha padrão: 123
            $senhaHash = password_hash('123', PASSWORD_BCRYPT);
            
            // CNPJ como login (email)
            $login = preg_replace('/[^0-9]/', '', $cliente['cnpj']); // Remove formatação
            
            // Inserir usuário
            $stmt = $conn->prepare("
                INSERT INTO usuarios (nome, email, senha, nivel, tipo_usuario, cliente_id, ativo)
                VALUES (?, ?, ?, 'usuario', 'cliente', ?, 1)
            ");
            $stmt->execute([
                $cliente['nome'],
                $login, // CNPJ como login
                $senhaHash,
                $cliente_id
            ]);
            
            $mensagem = "Usuário cliente criado com sucesso!<br><strong>Login:</strong> $login<br><strong>Senha:</strong> 123";
            
            // Atualizar lista
            $stmt = $conn->query("
                SELECT c.* 
                FROM clientes c
                LEFT JOIN usuarios u ON u.cliente_id = c.id AND u.tipo_usuario = 'cliente'
                WHERE u.id IS NULL
                ORDER BY c.nome
            ");
            $clientesSemUsuario = $stmt->fetchAll();
            
        } catch (Exception $e) {
            $erro = "Erro ao criar usuário: " . $e->getMessage();
        }
    } else {
        $erro = "Cliente não encontrado.";
    }
}

include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>🔐 Criar Usuário Cliente</h1>
        <a href="usuarios.php" class="btn btn-secondary">← Voltar</a>
    </div>
    
    <?php if ($mensagem): ?>
        <div class="alert alert-success">
            <?php echo $mensagem; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($erro): ?>
        <div class="alert alert-danger">
            <?php echo $erro; ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <h2>Informações</h2>
            <div class="alert alert-info">
                <p><strong>Como funciona:</strong></p>
                <ul>
                    <li>O <strong>CNPJ do cliente</strong> será usado como login (apenas números)</li>
                    <li>A senha padrão será <strong>123</strong></li>
                    <li>O cliente poderá alterar a senha no Portal do Cliente > Meus Dados</li>
                    <li>Apenas clientes sem usuário aparecem na lista abaixo</li>
                </ul>
            </div>
            
            <h2 style="margin-top: 30px;">Clientes Sem Usuário</h2>
            
            <?php if (empty($clientesSemUsuario)): ?>
                <div class="alert alert-warning">
                    Todos os clientes já possuem usuário criado!
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>CNPJ/CPF</th>
                                <th>E-mail</th>
                                <th>Tipo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientesSemUsuario as $cliente): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($cliente['nome']); ?></strong>
                                        <?php if (!empty($cliente['nome_fantasia'])): ?>
                                            <br><small><?php echo htmlspecialchars($cliente['nome_fantasia']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($cliente['cnpj']); ?></td>
                                    <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                                    <td>
                                        <?php if ($cliente['tipo'] == 'lead'): ?>
                                            <span class="badge badge-warning">Lead</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Cliente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Criar usuário para este cliente?\n\nLogin: <?php echo preg_replace('/[^0-9]/', '', $cliente['cnpj']); ?>\nSenha: 123');">
                                            <input type="hidden" name="criar_usuario" value="1">
                                            <input type="hidden" name="cliente_id" value="<?php echo $cliente['id']; ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                Criar Usuário
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
