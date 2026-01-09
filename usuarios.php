<?php
require_once 'auth.php';
verificarAdmin(); // Apenas administradores podem acessar

require_once 'config.php';

$pageTitle = 'Gerenciamento de Usuários';
$conn = getConnection();

// Buscar todos os usuários
$stmt = $conn->query("SELECT * FROM usuarios ORDER BY nome");
$usuarios = $stmt->fetchAll();

include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Gerenciamento de Usuários</h1>
        <a href="usuario_form.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Novo Usuário
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Lista de Usuários</h3>
        </div>
        <div class="card-body">
            <?php if (count($usuarios) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Nível</th>
                                <th>Status</th>
                                <th>Último Acesso</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?php echo $usuario['id']; ?></td>
                                    <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    <td>
                                        <?php if ($usuario['nivel'] == 'admin'): ?>
                                            <span class="badge badge-danger">Administrador</span>
                                        <?php else: ?>
                                            <span class="badge badge-info">Usuário</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($usuario['ativo']): ?>
                                            <span class="badge badge-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($usuario['ultimo_acesso']) {
                                            echo formatarDataHora($usuario['ultimo_acesso']);
                                        } else {
                                            echo '<span class="text-muted">Nunca acessou</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="usuario_form.php?id=<?php echo $usuario['id']; ?>" 
                                               class="btn btn-sm btn-warning" 
                                               title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                                <a href="usuario_delete.php?id=<?php echo $usuario['id']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Tem certeza que deseja excluir este usuário?')"
                                                   title="Excluir">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    Nenhum usuário cadastrado.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h3>Logs de Acesso Recentes</h3>
        </div>
        <div class="card-body">
            <?php
            $stmtLogs = $conn->query("SELECT l.*, u.nome FROM logs_acesso l LEFT JOIN usuarios u ON l.usuario_id = u.id ORDER BY l.data_hora DESC LIMIT 20");
            $logs = $stmtLogs->fetchAll();
            ?>
            
            <?php if (count($logs) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Usuário</th>
                                <th>E-mail</th>
                                <th>Ação</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo formatarDataHora($log['data_hora']); ?></td>
                                    <td><?php echo htmlspecialchars($log['nome'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($log['email']); ?></td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'login' => '<span class="badge badge-success">Login</span>',
                                            'logout' => '<span class="badge badge-secondary">Logout</span>',
                                            'tentativa_falha' => '<span class="badge badge-danger">Falha</span>'
                                        ];
                                        echo $badges[$log['acao']] ?? $log['acao'];
                                        ?>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($log['ip']); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    Nenhum log de acesso registrado.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.badge {
    padding: 5px 10px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.badge-danger {
    background: #dc2626;
    color: white;
}

.badge-info {
    background: #0ea5e9;
    color: white;
}

.badge-success {
    background: #16a34a;
    color: white;
}

.badge-secondary {
    background: #64748b;
    color: white;
}

.btn-group {
    display: flex;
    gap: 5px;
}

.table-sm {
    font-size: 0.9rem;
}

.table-sm td, .table-sm th {
    padding: 8px;
}
</style>

<?php include 'footer.php'; ?>
