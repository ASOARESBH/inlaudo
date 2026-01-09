<?php
/**
 * Templates de E-mail
 * 
 * Gerenciar templates de e-mail para notificações
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

// Templates padrão
$templates_padrao = [
    'novo_cliente' => [
        'nome' => 'Novo Cliente',
        'descricao' => 'E-mail enviado quando um novo cliente é criado',
        'variáveis' => ['{{nome_cliente}}', '{{email_cliente}}', '{{data_criacao}}']
    ],
    'conta_vencida' => [
        'nome' => 'Conta Vencida',
        'descricao' => 'E-mail de alerta para contas vencidas',
        'variáveis' => ['{{nome_cliente}}', '{{valor}}', '{{data_vencimento}}', '{{dias_vencido}}']
    ],
    'pagamento_recebido' => [
        'nome' => 'Pagamento Recebido',
        'descricao' => 'E-mail de confirmação de pagamento',
        'variáveis' => ['{{nome_cliente}}', '{{valor}}', '{{data_pagamento}}', '{{referencia}}']
    ],
    'boleto_gerado' => [
        'nome' => 'Boleto Gerado',
        'descricao' => 'E-mail com boleto para pagamento',
        'variáveis' => ['{{nome_cliente}}', '{{valor}}', '{{data_vencimento}}', '{{url_boleto}}']
    ]
];

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    try {
        $template_id = $_POST['template_id'] ?? '';
        $assunto = $_POST['assunto'] ?? '';
        $conteudo = $_POST['conteudo'] ?? '';

        if (!$template_id || !$assunto || !$conteudo) {
            throw new Exception('Todos os campos são obrigatórios');
        }

        $sql = "INSERT INTO email_templates (template_id, assunto, conteudo, usuario_id, data_atualizacao)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    assunto = VALUES(assunto),
                    conteudo = VALUES(conteudo),
                    data_atualizacao = NOW()";

        $db->execute($sql, [$template_id, $assunto, $conteudo, $_SESSION['usuario_id']]);

        $mensagem = 'Template salvo com sucesso!';
        $tipo_mensagem = 'success';

    } catch (Exception $e) {
        $mensagem = 'Erro ao salvar template: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Obter templates salvos
$sql = "SELECT * FROM email_templates WHERE usuario_id = ? ORDER BY data_atualizacao DESC";
$templates_salvos = $db->fetchAll($sql, [$_SESSION['usuario_id']]);

$templates_array = [];
foreach ($templates_salvos as $template) {
    $templates_array[$template['template_id']] = $template;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Templates de E-mail - ERP INLAUDO</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
    <style>
        .templates-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
        }

        .templates-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .templates-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2c3e50;
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

        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .template-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid #3498db;
        }

        .template-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .template-card h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .template-card p {
            margin: 0 0 15px 0;
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .template-card .btn {
            padding: 8px 15px;
            font-size: 0.85rem;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .template-card .btn:hover {
            background-color: #2980b9;
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
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
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

        .modal-close:hover {
            color: #2c3e50;
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
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
            font-family: inherit;
        }

        .form-group textarea {
            min-height: 300px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .variables-list {
            background-color: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .variables-list h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }

        .variables-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .variables-list li {
            background: white;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 0.85rem;
            color: #2c3e50;
            font-family: monospace;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .variables-list li:hover {
            background-color: #3498db;
            color: white;
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

        @media (max-width: 768px) {
            .templates-grid {
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

    <div class="templates-container">
        <!-- Título -->
        <div class="templates-header">
            <h1 class="templates-title">📧 Templates de E-mail</h1>
        </div>

        <!-- Mensagem -->
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo_mensagem ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <!-- Grid de Templates -->
        <div class="templates-grid">
            <?php foreach ($templates_padrao as $id => $template): ?>
                <div class="template-card">
                    <h3><?= $template['nome'] ?></h3>
                    <p><?= $template['descricao'] ?></p>
                    <button class="btn" onclick="abrirTemplate('<?= $id ?>')">
                        ✏️ Editar
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div id="templateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Editar Template</h2>
                <button class="modal-close" onclick="fecharModal()">×</button>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="template_id" id="templateId" value="">

                <!-- Variáveis Disponíveis -->
                <div class="variables-list">
                    <h4>📌 Variáveis Disponíveis:</h4>
                    <ul id="variavesList"></ul>
                </div>

                <!-- Assunto -->
                <div class="form-group">
                    <label for="assunto">Assunto do E-mail</label>
                    <input type="text" id="assunto" name="assunto" required>
                </div>

                <!-- Conteúdo -->
                <div class="form-group">
                    <label for="conteudo">Conteúdo do E-mail</label>
                    <textarea id="conteudo" name="conteudo" required></textarea>
                </div>

                <!-- Botões -->
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">💾 Salvar Template</button>
                </div>
            </form>
        </div>
    </div>

    <?php include dirname(__FILE__) . '/../layouts/footer.php'; ?>

    <script>
        function abrirTemplate(templateId) {
            const template = <?= json_encode($templates_padrao) ?>;
            const salvos = <?= json_encode($templates_array) ?>;

            const dados = template[templateId];
            const salvo = salvos[templateId];

            document.getElementById('templateId').value = templateId;
            document.getElementById('modalTitle').textContent = 'Editar: ' + dados.nome;
            document.getElementById('assunto').value = salvo?.assunto || '';
            document.getElementById('conteudo').value = salvo?.conteudo || '';

            // Mostrar variáveis
            const variavesList = document.getElementById('variavesList');
            variavesList.innerHTML = '';
            dados.variáveis.forEach(v => {
                const li = document.createElement('li');
                li.textContent = v;
                li.onclick = () => inserirVariavel(v);
                variavesList.appendChild(li);
            });

            document.getElementById('templateModal').classList.add('show');
        }

        function fecharModal() {
            document.getElementById('templateModal').classList.remove('show');
        }

        function inserirVariavel(variavel) {
            const textarea = document.getElementById('conteudo');
            textarea.value += variavel;
            textarea.focus();
        }

        // Fechar modal ao clicar fora
        document.getElementById('templateModal').addEventListener('click', (e) => {
            if (e.target.id === 'templateModal') {
                fecharModal();
            }
        });
    </script>
</body>
</html>
