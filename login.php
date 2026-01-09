<?php
/**
 * Página de Login - ERP INLAUDO
 * Versão Temporária - Acesso APENAS com E-mail (SEM validação de senha)
 */

session_start();

// Se já estiver logado, redirecionar para o dashboard correto
if (isset($_SESSION['usuario_id'])) {
    if (isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] == 'cliente') {
        header('Location: portal_cliente.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

require_once 'config.php';

$erro = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $erro = 'Por favor, informe o e-mail.';
    } else {
        try {
            $conn = getConnection();
            
            // Buscar usuário APENAS pelo e-mail (SEM validar senha)
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if ($usuario) {
                // Verificar se é cliente (clientes devem usar login_cliente.php)
                if (isset($usuario['tipo_usuario']) && $usuario['tipo_usuario'] == 'cliente') {
                    $erro = 'Clientes devem acessar pelo Portal do Cliente. <a href="login_cliente.php" style="color: #10b981; font-weight: 600;">Clique aqui</a>';
                } else {
                    // Login bem-sucedido para admin/usuario (SEM validar senha)
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_nome'] = $usuario['nome'];
                    $_SESSION['usuario_email'] = $usuario['email'];
                    $_SESSION['usuario_nivel'] = $usuario['nivel'];
                    $_SESSION['usuario_tipo'] = $usuario['tipo_usuario'] ?? 'usuario';
                    $_SESSION['login_time'] = time();
                    $_SESSION['ultimo_acesso'] = time();
                    $_SESSION['acesso_temporario'] = true; // Flag de acesso sem senha
                    
                    // Atualizar último acesso
                    $stmtUpdate = $conn->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
                    $stmtUpdate->execute([$usuario['id']]);
                    
                    // Registrar log de acesso
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $stmtLog = $conn->prepare("INSERT INTO logs_acesso (usuario_id, email, acao, ip, user_agent) VALUES (?, ?, 'login_sem_senha', ?, ?)");
                    $stmtLog->execute([$usuario['id'], $email, $ip, $userAgent]);
                    
                    // Redirecionar para dashboard administrativo
                    header('Location: index.php');
                    exit;
                }
            } else {
                // E-mail não encontrado
                $erro = 'E-mail não encontrado ou usuário inativo.';
                
                // Registrar tentativa falha
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $stmtLog = $conn->prepare("INSERT INTO logs_acesso (usuario_id, email, acao, ip, user_agent) VALUES (NULL, ?, 'tentativa_falha_email', ?, ?)");
                $stmtLog->execute([$email, $ip, $userAgent]);
            }
            
        } catch (Exception $e) {
            $erro = 'Erro ao processar login. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ERP INLAUDO</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            display: flex;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            padding: 60px 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .login-left img {
            max-width: 200px;
            margin-bottom: 30px;
            filter: brightness(0) invert(1);
        }

        .login-left h1 {
            font-size: 2rem;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .login-left p {
            font-size: 1rem;
            opacity: 0.9;
            line-height: 1.6;
        }

        .login-right {
            flex: 1;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-right h2 {
            color: #1e293b;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .login-right .subtitle {
            color: #64748b;
            margin-bottom: 30px;
            font-size: 1rem;
        }

        .warning-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .warning-box p {
            color: #92400e;
            font-size: 0.9rem;
            margin: 5px 0;
        }

        .warning-box strong {
            color: #78350f;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            color: #475569;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
            background: #f8fafc;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc2626;
            font-size: 0.95rem;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: #64748b;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }

            .login-left {
                padding: 40px 30px;
            }

            .login-left img {
                max-width: 150px;
            }

            .login-left h1 {
                font-size: 1.5rem;
            }

            .login-right {
                padding: 40px 30px;
            }

            .login-right h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <img src="LOGOBRANCA.png" alt="INLAUDO">
            <h1>ERP INLAUDO</h1>
            <p>Sistema de Gestão Empresarial</p>
            <p style="margin-top: 15px; font-size: 0.9rem;">Conectando Saúde e Tecnologia</p>
        </div>

        <div class="login-right">
            <h2>Bem-vindo!</h2>
            <p class="subtitle">Faça login para acessar o sistema</p>

            <div class="warning-box">
                <p><strong>⚠️ Modo Temporário</strong></p>
                <p>• Acesso apenas com e-mail (sem senha)</p>
                <p>• Sistema de senhas será implementado em breve</p>
            </div>

            <?php if ($erro): ?>
                <div class="error-message">
                    <?php echo $erro; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="seu@email.com"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        required
                        autofocus
                    >
                </div>

                <button type="submit" class="btn-login">
                    Entrar no Sistema
                </button>
            </form>

            <div class="footer-text">
                <p>&copy; <?php echo date('Y'); ?> INLAUDO. Todos os direitos reservados.</p>
                <p style="margin-top: 10px;">
                    <a href="login_cliente.php" style="color: #10b981; text-decoration: none; font-weight: 600;">👤 É cliente? Acesse o Portal do Cliente
                    </a>
                </p>
                <p style="margin-top: 5px; font-size: 0.85rem;">Versão 6.1 (Temporária)</p>
            </div>
        </div>
    </div>
</body>
</html>
