<?php
/**
 * Script Simples para Atualizar Senha com Bcrypt
 * Execute este arquivo UMA VEZ para corrigir a senha do usuário master
 */

require_once 'config.php';

// Dados do usuário master
$email = 'financeiro@inlaudo.com.br';
$senha = 'Admin259087@';

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>Atualizar Senha</title>";
echo "<style>body{font-family:Arial;padding:40px;background:#f5f5f5;}";
echo ".box{background:white;padding:30px;border-radius:10px;max-width:800px;margin:0 auto;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
echo "h1{color:#2563eb;border-bottom:3px solid #2563eb;padding-bottom:15px;}";
echo ".success{background:#d1fae5;color:#065f46;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #10b981;}";
echo ".error{background:#fee2e2;color:#991b1b;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #ef4444;}";
echo ".info{background:#dbeafe;color:#1e40af;padding:15px;border-radius:8px;margin:15px 0;border-left:4px solid #3b82f6;}";
echo ".code{background:#1e293b;color:#e2e8f0;padding:15px;border-radius:6px;overflow-x:auto;font-family:monospace;margin:10px 0;}";
echo ".btn{display:inline-block;background:#2563eb;color:white;padding:12px 24px;text-decoration:none;border-radius:8px;margin:10px 5px 0 0;}";
echo ".btn:hover{background:#1e40af;}</style></head><body>";
echo "<div class='box'>";
echo "<h1>🔐 Atualizar Senha do Usuário Master</h1>";

try {
    $conn = getConnection();
    
    // Informações do PHP
    echo "<div class='info'>";
    echo "<strong>Informações do Sistema:</strong><br>";
    echo "PHP Version: " . PHP_VERSION . "<br>";
    echo "password_hash disponível: " . (function_exists('password_hash') ? 'SIM ✓' : 'NÃO ✗') . "<br>";
    echo "password_verify disponível: " . (function_exists('password_verify') ? 'SIM ✓' : 'NÃO ✗') . "<br>";
    echo "</div>";
    
    // Dados do usuário
    echo "<h2>Dados do Usuário</h2>";
    echo "<p><strong>E-mail:</strong> $email</p>";
    echo "<p><strong>Senha:</strong> $senha</p>";
    
    // Gerar hash com bcrypt
    echo "<h2>Gerando Hash Bcrypt</h2>";
    $hash = password_hash($senha, PASSWORD_BCRYPT);
    
    echo "<p><strong>Hash gerado:</strong></p>";
    echo "<div class='code'>$hash</div>";
    echo "<p><strong>Tamanho:</strong> " . strlen($hash) . " caracteres</p>";
    
    // Testar hash
    echo "<h2>Testando Hash</h2>";
    $testVerify = password_verify($senha, $hash);
    
    if ($testVerify) {
        echo "<div class='success'>✓ Teste de verificação: SUCESSO! O hash está correto.</div>";
    } else {
        echo "<div class='error'>✗ Teste de verificação: FALHA! Há algo errado.</div>";
        exit;
    }
    
    // Verificar se usuário existe
    echo "<h2>Verificando Usuário no Banco</h2>";
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        echo "<div class='success'>✓ Usuário encontrado no banco de dados</div>";
        echo "<p><strong>ID:</strong> {$usuario['id']}</p>";
        echo "<p><strong>Nome:</strong> {$usuario['nome']}</p>";
        echo "<p><strong>Nível:</strong> {$usuario['nivel']}</p>";
        echo "<p><strong>Ativo:</strong> " . ($usuario['ativo'] ? 'Sim' : 'Não') . "</p>";
        
        echo "<p><strong>Hash atual no banco:</strong></p>";
        echo "<div class='code'>{$usuario['senha']}</div>";
        echo "<p><strong>Tamanho do hash atual:</strong> " . strlen($usuario['senha']) . " caracteres</p>";
        
        // Atualizar senha
        echo "<h2>Atualizando Senha no Banco</h2>";
        $stmtUpdate = $conn->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
        $resultado = $stmtUpdate->execute([$hash, $email]);
        
        if ($resultado) {
            echo "<div class='success'><strong>✓ SENHA ATUALIZADA COM SUCESSO!</strong></div>";
            
            // Verificar atualização
            $stmtCheck = $conn->prepare("SELECT senha FROM usuarios WHERE email = ?");
            $stmtCheck->execute([$email]);
            $usuarioAtualizado = $stmtCheck->fetch();
            
            echo "<p><strong>Novo hash no banco:</strong></p>";
            echo "<div class='code'>{$usuarioAtualizado['senha']}</div>";
            
            // Testar login
            echo "<h2>Teste Final de Login</h2>";
            $testeFinal = password_verify($senha, $usuarioAtualizado['senha']);
            
            if ($testeFinal) {
                echo "<div class='success'>";
                echo "<strong>✓✓✓ PERFEITO! Login funcionará!</strong><br><br>";
                echo "Você pode fazer login agora com:<br>";
                echo "<strong>E-mail:</strong> $email<br>";
                echo "<strong>Senha:</strong> $senha";
                echo "</div>";
            } else {
                echo "<div class='error'>✗ Teste final falhou. Ainda há problema.</div>";
            }
            
        } else {
            echo "<div class='error'>✗ Erro ao atualizar senha no banco de dados</div>";
        }
        
    } else {
        echo "<div class='error'>✗ Usuário não encontrado no banco de dados</div>";
        echo "<p>Criando usuário master...</p>";
        
        // Criar usuário
        $stmtCreate = $conn->prepare("INSERT INTO usuarios (nome, email, senha, nivel, ativo) VALUES (?, ?, ?, ?, ?)");
        $resultadoCreate = $stmtCreate->execute([
            'Administrador Master',
            $email,
            $hash,
            'admin',
            1
        ]);
        
        if ($resultadoCreate) {
            echo "<div class='success'><strong>✓ USUÁRIO MASTER CRIADO COM SUCESSO!</strong></div>";
            echo "<p>Você pode fazer login agora com:</p>";
            echo "<p><strong>E-mail:</strong> $email</p>";
            echo "<p><strong>Senha:</strong> $senha</p>";
        } else {
            echo "<div class='error'>✗ Erro ao criar usuário</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'><strong>✗ ERRO:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr>";
echo "<h2>Próximos Passos</h2>";
echo "<ol>";
echo "<li>Verifique se apareceu a mensagem 'SENHA ATUALIZADA COM SUCESSO' acima</li>";
echo "<li>Verifique se o teste final mostra 'PERFEITO! Login funcionará!'</li>";
echo "<li>Clique no botão abaixo para ir ao login</li>";
echo "<li>Faça login com: <strong>financeiro@inlaudo.com.br</strong> / <strong>Admin259087@</strong></li>";
echo "</ol>";

echo "<a href='login.php' class='btn'>→ Ir para Login</a>";
echo "<a href='?refresh=1' class='btn' style='background:#16a34a;'>🔄 Executar Novamente</a>";

echo "<hr>";
echo "<div class='info'>";
echo "<strong>⚠️ IMPORTANTE:</strong> Após o login funcionar, delete este arquivo (atualizar_senha.php) por segurança!";
echo "</div>";

echo "</div></body></html>";
?>
