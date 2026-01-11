<?php
/**
 * Configuração de E-mail
 * 
 * Gerenciar configurações SMTP e envio de emails
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

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dados = [
            'mail_driver' => $_POST['mail_driver'] ?? 'smtp',
            'mail_host' => $_POST['mail_host'] ?? '',
            'mail_port' => $_POST['mail_port'] ?? 587,
            'mail_username' => $_POST['mail_username'] ?? '',
            'mail_password' => $_POST['mail_password'] ?? '',
            'mail_from_address' => $_POST['mail_from_address'] ?? '',
            'mail_from_name' => $_POST['mail_from_name'] ?? '',
            'mail_encryption' => $_POST['mail_encryption'] ?? 'tls'
        ];

        // Salvar em arquivo .env ou banco de dados
        $sql = "INSERT INTO configuracoes (chave, valor, tipo_usuario_id) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)";

        foreach ($dados as $chave => $valor) {
            $db->execute($sql, [strtoupper($chave), $valor, $_SESSION['usuario_id']]);
        }

        $mensagem = 'Configurações de e-mail salvas com sucesso!';
        $tipo_mensagem = 'success';

    } catch (Exception $e) {
        $mensagem = 'Erro ao salvar configurações: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Obter configurações atuais
$sql = "SELECT chave, valor FROM configuracoes WHERE tipo_usuario_id = ? AND chave LIKE 'MAIL_%'";
$configs = $db->fetchAll($sql, [$_SESSION['usuario_id']]);

$config_array = [];
foreach ($configs as $config) {
    $config_array[strtolower($config['chave'])] = $config['valor'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração de E-mail - ERP INLAUDO</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
    <style>
        .config-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
        }

        .config-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 20px;
        }

        .config-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #34495e;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ecf0f1;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-help {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-top: 5px;
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

        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #7f8c8d;
        }

        .test-section {
            background-color: #ecf0f1;
            padding: 20px;
            border-radius: 4px;
            margin-top: 20px;
        }

        .test-section h3 {
            margin-top: 0;
            color: #2c3e50;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .config-container {
                margin: 10px;
                padding: 10px;
            }

            .config-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include dirname(__FILE__) . '/../layouts/navbar.php'; ?>

    <div class="config-container">
        <!-- Título -->
        <div class="config-card">
            <h1 class="config-title">📧 Configuração de E-mail</h1>

            <!-- Mensagem -->
            <?php if ($mensagem): ?>
                <div class="alert alert-<?= $tipo_mensagem ?>">
                    <?= htmlspecialchars($mensagem) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Seção: Servidor SMTP -->
                <div class="form-section">
                    <h2 class="form-section-title">Servidor SMTP</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="mail_driver">Driver de E-mail</label>
                            <select id="mail_driver" name="mail_driver" required>
                                <option value="smtp" <?= ($config_array['mail_driver'] ?? 'smtp') === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                                <option value="sendmail" <?= ($config_array['mail_driver'] ?? '') === 'sendmail' ? 'selected' : '' ?>>Sendmail</option>
                                <option value="mailgun" <?= ($config_array['mail_driver'] ?? '') === 'mailgun' ? 'selected' : '' ?>>Mailgun</option>
                            </select>
                            <span class="form-help">Selecione o driver de envio de e-mail</span>
                        </div>

                        <div class="form-group">
                            <label for="mail_encryption">Criptografia</label>
                            <select id="mail_encryption" name="mail_encryption" required>
                                <option value="tls" <?= ($config_array['mail_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= ($config_array['mail_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="none" <?= ($config_array['mail_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Nenhuma</option>
                            </select>
                            <span class="form-help">Tipo de criptografia da conexão</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="mail_host">Host SMTP</label>
                            <input type="text" id="mail_host" name="mail_host" 
                                   value="<?= htmlspecialchars($config_array['mail_host'] ?? '') ?>" required>
                            <span class="form-help">Ex: smtp.gmail.com, smtp.mailtrap.io</span>
                        </div>

                        <div class="form-group">
                            <label for="mail_port">Porta</label>
                            <input type="number" id="mail_port" name="mail_port" 
                                   value="<?= htmlspecialchars($config_array['mail_port'] ?? '587') ?>" required>
                            <span class="form-help">Porta SMTP (geralmente 587 ou 465)</span>
                        </div>
                    </div>
                </div>

                <!-- Seção: Credenciais -->
                <div class="form-section">
                    <h2 class="form-section-title">Credenciais</h2>

                    <div class="form-row full">
                        <div class="form-group">
                            <label for="mail_username">Usuário/Email</label>
                            <input type="email" id="mail_username" name="mail_username" 
                                   value="<?= htmlspecialchars($config_array['mail_username'] ?? '') ?>" required>
                            <span class="form-help">E-mail ou usuário para autenticação</span>
                        </div>
                    </div>

                    <div class="form-row full">
                        <div class="form-group">
                            <label for="mail_password">Senha</label>
                            <input type="password" id="mail_password" name="mail_password" 
                                   value="<?= htmlspecialchars($config_array['mail_password'] ?? '') ?>" required>
                            <span class="form-help">Senha ou token de autenticação</span>
                        </div>
                    </div>
                </div>

                <!-- Seção: Remetente -->
                <div class="form-section">
                    <h2 class="form-section-title">Remetente Padrão</h2>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="mail_from_address">E-mail do Remetente</label>
                            <input type="email" id="mail_from_address" name="mail_from_address" 
                                   value="<?= htmlspecialchars($config_array['mail_from_address'] ?? '') ?>" required>
                            <span class="form-help">E-mail que aparecerá como remetente</span>
                        </div>

                        <div class="form-group">
                            <label for="mail_from_name">Nome do Remetente</label>
                            <input type="text" id="mail_from_name" name="mail_from_name" 
                                   value="<?= htmlspecialchars($config_array['mail_from_name'] ?? 'ERP INLAUDO') ?>" required>
                            <span class="form-help">Nome que aparecerá como remetente</span>
                        </div>
                    </div>
                </div>

                <!-- Seção: Teste -->
                <div class="test-section">
                    <h3>🧪 Testar Configuração</h3>
                    <p>Clique no botão abaixo para enviar um e-mail de teste para validar as configurações.</p>
                    <button type="button" class="btn btn-secondary" onclick="testarEmail()">
                        Enviar E-mail de Teste
                    </button>
                </div>

                <!-- Botões -->
                <div class="button-group">
                    <a href="<?= BASE_URL ?>/dashboard" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">💾 Salvar Configurações</button>
                </div>
            </form>
        </div>
    </div>

    <?php include dirname(__FILE__) . '/../layouts/footer.php'; ?>

    <script>
        function testarEmail() {
            const email = document.getElementById('mail_from_address').value;
            
            if (!email) {
                alert('Por favor, preencha o e-mail do remetente');
                return;
            }

            fetch('<?= BASE_URL ?>/api/email/testar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?? '' ?>'
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ E-mail de teste enviado com sucesso!');
                } else {
                    alert('❌ Erro ao enviar e-mail: ' + (data.message || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('❌ Erro ao enviar e-mail de teste');
            });
        }
    </script>
</body>
</html>
