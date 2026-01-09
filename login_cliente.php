<?php
/**
 * Login Exclusivo para Clientes - Via CNPJ
 * Portal do Cliente - INLAUDO
 * Versão 7.3 - Login simplificado via CNPJ validado em contratos
 */

session_start();

// Se já estiver logado como cliente, redirecionar para o portal
if (isset($_SESSION['cliente_logado']) && $_SESSION['cliente_logado'] === true) {
    header('Location: portal_cliente.php');
    exit;
}

require_once 'config.php';

$erro = '';
$sucesso = '';

// Processar login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? ''); // Remove formatação
    
    if (empty($cnpj)) {
        $erro = 'Por favor, informe o CNPJ.';
    } else {
        try {
            $conn = getConnection();
            
            // Buscar cliente pelo CNPJ que tenha contrato ativo ou inativo
            $stmt = $conn->prepare("
                SELECT DISTINCT c.*, 
                       COUNT(ct.id) as total_contratos,
                       SUM(CASE WHEN ct.status = 'ativo' THEN 1 ELSE 0 END) as contratos_ativos
                FROM clientes c
                INNER JOIN contratos ct ON c.id = ct.cliente_id
                WHERE c.cnpj_cpf = ?
                GROUP BY c.id
                HAVING total_contratos > 0
            ");
            $stmt->execute([$cnpj]);
            $cliente = $stmt->fetch();
            
            if ($cliente) {
                // Login bem-sucedido - Cliente tem contrato
                $_SESSION['cliente_logado'] = true;
                $_SESSION['cliente_id'] = $cliente['id'];
                $_SESSION['cliente_nome'] = $cliente['tipo_pessoa'] == 'CNPJ' 
                    ? ($cliente['razao_social'] ?: $cliente['nome_fantasia']) 
                    : $cliente['nome'];
                $_SESSION['cliente_cnpj'] = $cliente['cnpj_cpf'];
                $_SESSION['cliente_email'] = $cliente['email'];
                $_SESSION['cliente_tipo_pessoa'] = $cliente['tipo_pessoa'];
                $_SESSION['total_contratos'] = $cliente['total_contratos'];
                $_SESSION['contratos_ativos'] = $cliente['contratos_ativos'];
                $_SESSION['login_time'] = time();
                $_SESSION['ultimo_acesso'] = time();
                
                // Redirecionar para o portal do cliente
                header('Location: portal_cliente.php');
                exit;
            } else {
                // Verificar se o CNPJ existe mas não tem contrato
                $stmtCheck = $conn->prepare("SELECT id FROM clientes WHERE cnpj_cpf = ?");
                $stmtCheck->execute([$cnpj]);
                $clienteExiste = $stmtCheck->fetch();
                
                if ($clienteExiste) {
                    $erro = 'CNPJ encontrado, mas não há contratos cadastrados. Entre em contato com a INLAUDO.';
                } else {
                    $erro = 'CNPJ não encontrado. Verifique o número digitado ou entre em contato com a INLAUDO.';
                }
            }
        } catch (Exception $e) {
            $erro = 'Erro ao processar login. Tente novamente.';
            error_log('Erro login cliente: ' . $e->getMessage());
        }
    }
}

// Verificar se há mensagem na URL
if (isset($_GET['erro'])) {
    if ($_GET['erro'] == 'sessao_expirada') {
        $erro = 'Sua sessão expirou. Faça login novamente.';
    } elseif ($_GET['erro'] == 'acesso_negado') {
        $erro = 'Acesso negado. Faça login para continuar.';
    }
}

if (isset($_GET['logout'])) {
    $sucesso = 'Logout realizado com sucesso!';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Cliente - INLAUDO</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        
        .login-header img {
            max-width: 180px;
            height: auto;
            margin-bottom: 20px;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .login-header p {
            font-size: 1rem;
            opacity: 0.95;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .alert-erro {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }
        
        .alert-sucesso {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: monospace;
            letter-spacing: 1px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .form-group small {
            display: block;
            margin-top: 8px;
            color: #6b7280;
            font-size: 0.85rem;
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .login-footer {
            text-align: center;
            padding: 25px 30px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
        
        .login-footer p {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .login-footer a {
            color: #10b981;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .info-box {
            background: #f0fdf4;
            border: 2px solid #bbf7d0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .info-box h3 {
            color: #065f46;
            font-size: 1rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-box ul {
            margin-left: 20px;
            color: #047857;
        }
        
        .info-box li {
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 480px) {
            .login-header h1 {
                font-size: 1.5rem;
            }
            
            .login-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="LOGOBRANCA.png" alt="INLAUDO">
            <h1>Portal do Cliente</h1>
            <p>Acesse com seu CNPJ</p>
        </div>
        
        <div class="login-body">
            <?php if ($erro): ?>
                <div class="alert alert-erro">
                    <strong>❌ Erro:</strong> <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($sucesso): ?>
                <div class="alert alert-sucesso">
                    <strong>✅ Sucesso:</strong> <?php echo htmlspecialchars($sucesso); ?>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h3>ℹ️ Como acessar</h3>
                <ul>
                    <li>Digite apenas o CNPJ da sua empresa</li>
                    <li>Não é necessário senha</li>
                    <li>Seu CNPJ deve ter contrato ativo</li>
                </ul>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="cnpj">CNPJ</label>
                    <input 
                        type="text" 
                        id="cnpj" 
                        name="cnpj" 
                        placeholder="00.000.000/0000-00"
                        maxlength="18"
                        required
                        autofocus
                        value="<?php echo isset($_POST['cnpj']) ? htmlspecialchars($_POST['cnpj']) : ''; ?>"
                    >
                    <small>Digite apenas os números ou com formatação</small>
                </div>
                
                <button type="submit" class="btn-login">
                    🔐 Acessar Portal
                </button>
            </form>
        </div>
        
        <div class="login-footer">
            <p>Não consegue acessar?</p>
            <a href="mailto:financeiro@inlaudo.com.br">Entre em contato com o suporte</a>
        </div>
    </div>
    
    <script>
        // Formatar CNPJ automaticamente
        document.getElementById('cnpj').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length <= 14) {
                value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            }
            
            e.target.value = value;
        });
    </script>
</body>
</html>
