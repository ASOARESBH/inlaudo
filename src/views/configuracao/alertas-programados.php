<?php
/**
 * Alertas Programados
 * 
 * Configurar alertas automáticos e agendados
 */

require_once dirname(dirname(dirname(__FILE__))) . '/core/Bootstrap.php';

use App\Core\Database;

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$db = Database::getInstance();
$mensagem = '';
$tipo_mensagem = '';

// Tipos de alertas disponíveis
$tipos_alertas = [
    'contas_vencidas' => 'Contas Vencidas',
    'contas_vencendo' => 'Contas Vencendo',
    'cliente_novo' => 'Novo Cliente',
    'pagamento_recebido' => 'Pagamento Recebido',
    'boleto_gerado' => 'Boleto Gerado'
];

// Frequências
$frequencias = [
    'diaria' => 'Diária',
    'semanal' => 'Semanal',
    'mensal' => 'Mensal',
    'imediata' => 'Imediata'
];

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    try {
        $tipo = $_POST['tipo'] ?? '';
        $frequencia = $_POST['frequencia'] ?? '';
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $destinatarios = $_POST['destinatarios'] ?? '';

        if (!$tipo || !$frequencia || !$destinatarios) {
            throw new Exception('Todos os campos são obrigatórios');
        }

        $sql = "INSERT INTO alertas_programados (tipo, frequencia, ativo, destinatarios, usuario_id, data_criacao)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    frequencia = VALUES(frequencia),
                    ativo = VALUES(ativo),
                    destinatarios = VALUES(destinatarios)";

        $db->execute($sql, [$tipo, $frequencia, $ativo, $destinatarios, $_SESSION['usuario_id']]);

        $mensagem = 'Alerta programado salvo com sucesso!';
        $tipo_mensagem = 'success';

    } catch (Exception $e) {
        $mensagem = 'Erro ao salvar alerta: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Obter alertas programados
$sql = "SELECT * FROM alertas_programados WHERE usuario_id = ? ORDER BY data_criacao DESC";
$alertas = $db->fetchAll($sql, [$_SESSION['usuario_id']]);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas Programados - ERP INLAUDO</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
    <style>
        .alertas-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
        }

        .alertas-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .alertas-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .btn-novo {
            padding: 10px 20px;
            background-color: #27ae60;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-novo:hover {
            background-color: #229954;
        }

        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #27ae60;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #e74c3c;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 15px;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #7f8c8d;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        /* Grid de Alertas */
        .alertas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .alerta-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            border-left: 4px solid #3498db;
        }

        .alerta-card.inativo {
            opacity: 0.6;
            border-left-color: #95a5a6;
        }

        .alerta-tipo {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .alerta-info {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 15px;
        }

        .alerta-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .alerta-status.ativo {
            background-color: #d4edda;
            color: #155724;
        }

        .alerta-status.inativo {
            background-color: #f8d7da;
            color: #721c24;
        }

        .alerta-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }

        .action-btn {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .action-btn-edit {
            background-color: #3498db;
            color: white;
        }

        .action-btn-edit:hover {
            background-color: #2980b9;
        }

        .action-btn-delete {
            background-color: #e74c3c;
            color: white;
        }

        .action-btn-delete:hover {
            background-color: #c0392b;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .alertas-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .alertas-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include dirname(__FILE__) . '/../layouts/navbar.php'; ?>

    <div class="alertas-container">
        <!-- Título -->
        <div class="alertas-header">
            <h1 class="alertas-title">🔔 Alertas Programados</h1>
            <button class="btn-novo" onclick="abrirNovoAlerta()">+ Novo Alerta</button>
        </div>

        <!-- Mensagem -->
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo_mensagem ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <!-- Grid de Alertas -->
        <?php if (empty($alertas)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔔</div>
                <h2>Nenhum alerta programado</h2>
                <p>Clique no botão acima para criar seu primeiro alerta</p>
            </div>
        <?php else: ?>
            <div class="alertas-grid">
                <?php foreach ($alertas as $alerta): ?>
                    <div class="alerta-card <?= $alerta['ativo'] ? '' : 'inativo' ?>">
                        <div class="alerta-tipo">
                            <?= $tipos_alertas[$alerta['tipo']] ?? $alerta['tipo'] ?>
                        </div>
                        <div class="alerta-info">
                            <strong>Frequência:</strong> <?= $frequencias[$alerta['frequencia']] ?? $alerta['frequencia'] ?><br>
                            <strong>Destinatários:</strong> <?= htmlspecialchars($alerta['destinatarios']) ?>
                        </div>
                        <div class="alerta-status <?= $alerta['ativo'] ? 'ativo' : 'inativo' ?>">
                            <?= $alerta['ativo'] ? '✓ Ativo' : '✗ Inativo' ?>
                        </div>
                        <div class="alerta-actions">
                            <button class="action-btn action-btn-edit" onclick="editarAlerta(<?= $alerta['id'] ?>)">
                                ✏️ Editar
                            </button>
                            <button class="action-btn action-btn-delete" onclick="deletarAlerta(<?= $alerta['id'] ?>)">
                                🗑️ Deletar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Novo/Edição Alerta -->
    <div id="alertaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Novo Alerta Programado</h2>
                <button class="modal-close" onclick="fecharModal()">×</button>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="acao" value="salvar">

                <!-- Tipo de Alerta -->
                <div class="form-group">
                    <label for="tipo">Tipo de Alerta</label>
                    <select id="tipo" name="tipo" required>
                        <option value="">Selecione um tipo...</option>
                        <?php foreach ($tipos_alertas as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Frequência -->
                <div class="form-group">
                    <label for="frequencia">Frequência</label>
                    <select id="frequencia" name="frequencia" required>
                        <option value="">Selecione uma frequência...</option>
                        <?php foreach ($frequencias as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Destinatários -->
                <div class="form-group">
                    <label for="destinatarios">Destinatários (separados por vírgula)</label>
                    <textarea id="destinatarios" name="destinatarios" placeholder="email1@exemplo.com, email2@exemplo.com" required></textarea>
                </div>

                <!-- Ativo -->
                <div class="form-group checkbox-group">
                    <input type="checkbox" id="ativo" name="ativo" checked>
                    <label for="ativo" style="margin-bottom: 0;">Ativar este alerta</label>
                </div>

                <!-- Botões -->
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">💾 Salvar Alerta</button>
                </div>
            </form>
        </div>
    </div>

    <?php include dirname(__FILE__) . '/../layouts/footer.php'; ?>

    <script>
        function abrirNovoAlerta() {
            document.getElementById('tipo').value = '';
            document.getElementById('frequencia').value = '';
            document.getElementById('destinatarios').value = '';
            document.getElementById('ativo').checked = true;
            document.getElementById('alertaModal').classList.add('show');
        }

        function fecharModal() {
            document.getElementById('alertaModal').classList.remove('show');
        }

        function editarAlerta(id) {
            alert('Editar alerta #' + id);
            // Implementar edição
        }

        function deletarAlerta(id) {
            if (confirm('Tem certeza que deseja deletar este alerta?')) {
                alert('Deletar alerta #' + id);
                // Implementar deleção
            }
        }

        // Fechar modal ao clicar fora
        document.getElementById('alertaModal').addEventListener('click', (e) => {
            if (e.target.id === 'alertaModal') {
                fecharModal();
            }
        });
    </script>
</body>
</html>
