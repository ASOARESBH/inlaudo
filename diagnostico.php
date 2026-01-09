<?php
/**
 * Página de Diagnóstico do Sistema
 * Verifica configurações, testa funcionalidades e exibe informações de debug
 */

require_once 'config.php';
require_once 'lib_debug.php';

$pageTitle = 'Diagnóstico do Sistema';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - ERP INLAUDO</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            color: #1e293b;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 3px solid #2563eb;
        }

        .section {
            background: white;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .section h2 {
            color: #2563eb;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }

        .info-item {
            padding: 15px;
            background: #f8fafc;
            border-left: 4px solid #2563eb;
            border-radius: 6px;
        }

        .info-item strong {
            display: block;
            color: #334155;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .info-item span {
            color: #64748b;
            font-family: monospace;
            font-size: 0.95rem;
        }

        .status-ok {
            color: #16a34a;
            font-weight: bold;
        }

        .status-error {
            color: #dc2626;
            font-weight: bold;
        }

        .status-warning {
            color: #f59e0b;
            font-weight: bold;
        }

        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin: 10px 0;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 5px;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #1e40af;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #16a34a;
        }

        .btn-success:hover {
            background: #15803d;
        }

        .btn-danger {
            background: #dc2626;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        table th {
            background: #2563eb;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }

        table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        table tr:hover {
            background: #f8fafc;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico do Sistema - ERP INLAUDO</h1>

        <!-- Informações do Sistema -->
        <div class="section">
            <h2>📊 Informações do Sistema</h2>
            <?php
            $sysInfo = get_system_info();
            ?>
            <div class="info-grid">
                <div class="info-item">
                    <strong>Versão PHP</strong>
                    <span class="<?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $sysInfo['php_version']; ?>
                    </span>
                </div>
                <div class="info-item">
                    <strong>password_hash() Disponível</strong>
                    <span class="<?php echo $sysInfo['password_hash_available'] ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $sysInfo['password_hash_available'] ? '✓ SIM' : '✗ NÃO'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <strong>password_verify() Disponível</strong>
                    <span class="<?php echo $sysInfo['password_verify_available'] ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $sysInfo['password_verify_available'] ? '✓ SIM' : '✗ NÃO'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <strong>PASSWORD_DEFAULT</strong>
                    <span><?php echo $sysInfo['password_default']; ?></span>
                </div>
                <div class="info-item">
                    <strong>PASSWORD_BCRYPT</strong>
                    <span><?php echo $sysInfo['password_bcrypt']; ?></span>
                </div>
                <div class="info-item">
                    <strong>Servidor</strong>
                    <span><?php echo $sysInfo['server_software']; ?></span>
                </div>
                <div class="info-item">
                    <strong>Sistema Operacional</strong>
                    <span><?php echo $sysInfo['os']; ?></span>
                </div>
                <div class="info-item">
                    <strong>Data/Hora</strong>
                    <span><?php echo $sysInfo['date']; ?></span>
                </div>
            </div>
        </div>

        <!-- Teste de Hash -->
        <div class="section">
            <h2>🔐 Teste de Geração de Hash</h2>
            <?php
            $senhaTest = 'Admin259087@';
            $testResult = test_password_hash($senhaTest);
            ?>
            <div class="alert alert-info">
                <strong>Senha de Teste:</strong> <?php echo $senhaTest; ?>
            </div>
            <div class="info-item">
                <strong>Hash Gerado</strong>
                <div class="code-block"><?php echo $testResult['hash']; ?></div>
            </div>
            <div class="info-item">
                <strong>Verificação (password_verify)</strong>
                <span class="<?php echo $testResult['verify'] ? 'status-ok' : 'status-error'; ?>">
                    <?php echo $testResult['verify'] ? '✓ SUCESSO' : '✗ FALHA'; ?>
                </span>
            </div>
            <div class="info-item">
                <strong>Informações do Hash</strong>
                <pre class="code-block"><?php print_r($testResult['info']); ?></pre>
            </div>
        </div>

        <!-- Usuários no Banco -->
        <div class="section">
            <h2>👥 Usuários no Banco de Dados</h2>
            <?php
            try {
                $conn = getConnection();
                $stmt = $conn->query("SELECT id, nome, email, nivel, ativo, ultimo_acesso, LENGTH(senha) as senha_length, senha FROM usuarios ORDER BY id");
                $usuarios = $stmt->fetchAll();
                
                if (count($usuarios) > 0):
            ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Nível</th>
                            <th>Ativo</th>
                            <th>Tamanho Hash</th>
                            <th>Hash</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?php echo $usuario['id']; ?></td>
                                <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td><?php echo $usuario['nivel']; ?></td>
                                <td>
                                    <span class="<?php echo $usuario['ativo'] ? 'status-ok' : 'status-error'; ?>">
                                        <?php echo $usuario['ativo'] ? 'Sim' : 'Não'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $usuario['senha_length'] >= 60 ? 'status-ok' : 'status-error'; ?>">
                                        <?php echo $usuario['senha_length']; ?> caracteres
                                    </span>
                                </td>
                                <td>
                                    <small style="font-family: monospace; font-size: 0.75rem;">
                                        <?php echo substr($usuario['senha'], 0, 30); ?>...
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php
                // Verificar hash do usuário master
                $master = null;
                foreach ($usuarios as $u) {
                    if ($u['email'] == 'financeiro@inlaudo.com.br') {
                        $master = $u;
                        break;
                    }
                }

                if ($master):
                ?>
                    <div class="alert <?php echo strlen($master['senha']) >= 60 ? 'alert-success' : 'alert-danger'; ?>">
                        <strong>Usuário Master (financeiro@inlaudo.com.br):</strong><br>
                        <?php if (strlen($master['senha']) >= 60): ?>
                            ✓ Hash parece válido (<?php echo strlen($master['senha']); ?> caracteres)
                        <?php else: ?>
                            ✗ Hash parece inválido (<?php echo strlen($master['senha']); ?> caracteres - esperado 60+)<br>
                            <strong>Execute o script de correção!</strong>
                        <?php endif; ?>
                    </div>

                    <div class="info-item">
                        <strong>Hash Completo do Master</strong>
                        <div class="code-block"><?php echo $master['senha']; ?></div>
                    </div>

                    <div class="info-item">
                        <strong>Teste de Senha do Master</strong>
                        <?php
                        $testMaster = password_verify('Admin259087@', $master['senha']);
                        ?>
                        <span class="<?php echo $testMaster ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $testMaster ? '✓ Senha "Admin259087@" FUNCIONA!' : '✗ Senha "Admin259087@" NÃO FUNCIONA - Execute correção!'; ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        ⚠️ Usuário master (financeiro@inlaudo.com.br) não encontrado!<br>
                        Execute o script de correção para criar.
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-warning">
                    ⚠️ Nenhum usuário encontrado no banco de dados!<br>
                    Execute o SQL de criação de usuários.
                </div>
            <?php endif; ?>

            <?php
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">';
                echo '<strong>Erro ao consultar banco:</strong><br>';
                echo htmlspecialchars($e->getMessage());
                echo '</div>';
            }
            ?>
        </div>

        <!-- Logs Recentes -->
        <div class="section">
            <h2>📋 Logs Recentes</h2>
            <?php
            $logDir = __DIR__ . '/logs';
            if (file_exists($logDir)) {
                $files = glob($logDir . '/*.log');
                if (!empty($files)) {
                    echo '<div class="info-grid">';
                    foreach ($files as $file) {
                        $filename = basename($file);
                        $size = filesize($file);
                        $modified = date('Y-m-d H:i:s', filemtime($file));
                        
                        echo '<div class="info-item">';
                        echo '<strong>' . htmlspecialchars($filename) . '</strong>';
                        echo '<span>Tamanho: ' . number_format($size) . ' bytes</span><br>';
                        echo '<span>Modificado: ' . $modified . '</span><br>';
                        echo '<a href="?view_log=' . urlencode($filename) . '" class="btn" style="margin-top: 10px; font-size: 0.85rem; padding: 6px 12px;">Ver Log</a>';
                        echo '</div>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="alert alert-info">Nenhum log encontrado ainda.</div>';
                }
            } else {
                echo '<div class="alert alert-warning">Diretório de logs não existe.</div>';
            }

            // Exibir log se solicitado
            if (isset($_GET['view_log'])) {
                $logFile = $logDir . '/' . basename($_GET['view_log']);
                if (file_exists($logFile)) {
                    echo '<h3 style="margin-top: 20px;">Conteúdo de ' . htmlspecialchars(basename($_GET['view_log'])) . '</h3>';
                    echo '<pre class="code-block" style="max-height: 400px; overflow-y: auto;">';
                    echo htmlspecialchars(file_get_contents($logFile));
                    echo '</pre>';
                }
            }
            ?>
        </div>

        <!-- Ações -->
        <div class="section">
            <h2>🔧 Ações</h2>
            <a href="corrigir_senha_master.php" class="btn btn-success">🔐 Corrigir Senha do Master</a>
            <a href="login.php" class="btn">← Voltar para Login</a>
            <a href="?refresh=1" class="btn">🔄 Atualizar Diagnóstico</a>
        </div>
    </div>
</body>
</html>
