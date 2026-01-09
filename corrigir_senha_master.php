<?php
/**
 * Script para Corrigir Senha do Usuário Master
 * Execute este arquivo para gerar e atualizar o hash correto da senha
 */

require_once 'config.php';
require_once 'lib_debug.php';

echo "<html><head><title>Corrigir Senha Master</title></head><body>";
echo "<h1>Correção de Senha do Usuário Master</h1>";

// Informações do sistema
echo "<h2>Informações do Sistema</h2>";
$sysInfo = get_system_info();
echo "<pre>";
print_r($sysInfo);
echo "</pre>";

// Dados do usuário master
$email = 'financeiro@inlaudo.com.br';
$senha = 'Admin259087@';

echo "<h2>Dados do Usuário Master</h2>";
echo "<p><strong>E-mail:</strong> $email</p>";
echo "<p><strong>Senha:</strong> $senha</p>";

// Gerar hash
echo "<h2>Gerando Hash da Senha</h2>";
$hash = password_hash($senha, PASSWORD_DEFAULT);

echo "<p><strong>Hash gerado:</strong></p>";
echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc; overflow-x: auto;'>$hash</pre>";

// Testar hash
echo "<h2>Testando Hash</h2>";
$verify = password_verify($senha, $hash);
echo "<p><strong>Resultado do password_verify:</strong> " . ($verify ? '<span style="color: green;">✓ SUCESSO</span>' : '<span style="color: red;">✗ FALHA</span>') . "</p>";

// Informações do hash
$hashInfo = password_get_info($hash);
echo "<p><strong>Informações do Hash:</strong></p>";
echo "<pre>";
print_r($hashInfo);
echo "</pre>";

// Atualizar banco de dados
try {
    $conn = getConnection();
    
    echo "<h2>Atualizando Banco de Dados</h2>";
    
    // Verificar se usuário existe
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        echo "<p>✓ Usuário encontrado no banco de dados</p>";
        echo "<p><strong>ID:</strong> {$usuario['id']}</p>";
        echo "<p><strong>Nome:</strong> {$usuario['nome']}</p>";
        echo "<p><strong>E-mail:</strong> {$usuario['email']}</p>";
        echo "<p><strong>Nível:</strong> {$usuario['nivel']}</p>";
        echo "<p><strong>Ativo:</strong> " . ($usuario['ativo'] ? 'Sim' : 'Não') . "</p>";
        echo "<p><strong>Hash atual no banco:</strong></p>";
        echo "<pre style='background: #fff3cd; padding: 10px; border: 1px solid #ffc107; overflow-x: auto;'>{$usuario['senha']}</pre>";
        
        // Atualizar senha
        $stmtUpdate = $conn->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
        $resultado = $stmtUpdate->execute([$hash, $email]);
        
        if ($resultado) {
            echo "<p style='color: green; font-weight: bold;'>✓ SENHA ATUALIZADA COM SUCESSO!</p>";
            
            // Verificar atualização
            $stmtVerify = $conn->prepare("SELECT senha FROM usuarios WHERE email = ?");
            $stmtVerify->execute([$email]);
            $novoUsuario = $stmtVerify->fetch();
            
            echo "<p><strong>Novo hash no banco:</strong></p>";
            echo "<pre style='background: #d1fae5; padding: 10px; border: 1px solid #10b981; overflow-x: auto;'>{$novoUsuario['senha']}</pre>";
            
            // Testar login
            echo "<h2>Teste de Login</h2>";
            $testVerify = password_verify($senha, $novoUsuario['senha']);
            echo "<p><strong>Teste de verificação:</strong> " . ($testVerify ? '<span style="color: green;">✓ SUCESSO - Login funcionará!</span>' : '<span style="color: red;">✗ FALHA - Ainda há problema</span>') . "</p>";
            
            // Log
            debug_log("Senha do usuário master corrigida", [
                'email' => $email,
                'hash_antigo' => $usuario['senha'],
                'hash_novo' => $hash,
                'teste_verificacao' => $testVerify
            ]);
            
            auth_log('CORRECAO_SENHA', $email, true, 'Senha do usuário master corrigida via script');
            
        } else {
            echo "<p style='color: red; font-weight: bold;'>✗ ERRO AO ATUALIZAR SENHA</p>";
            error_log_custom("Erro ao atualizar senha do usuário master");
        }
        
    } else {
        echo "<p style='color: red;'>✗ Usuário não encontrado no banco de dados</p>";
        echo "<p>Execute o SQL de criação de usuários primeiro!</p>";
        
        // Criar usuário
        echo "<h3>Criando Usuário Master</h3>";
        $stmtCreate = $conn->prepare("INSERT INTO usuarios (nome, email, senha, nivel, ativo) VALUES (?, ?, ?, ?, ?)");
        $resultadoCreate = $stmtCreate->execute([
            'Administrador Master',
            $email,
            $hash,
            'admin',
            1
        ]);
        
        if ($resultadoCreate) {
            echo "<p style='color: green; font-weight: bold;'>✓ USUÁRIO MASTER CRIADO COM SUCESSO!</p>";
            debug_log("Usuário master criado", ['email' => $email]);
        } else {
            echo "<p style='color: red; font-weight: bold;'>✗ ERRO AO CRIAR USUÁRIO</p>";
            error_log_custom("Erro ao criar usuário master");
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>✗ ERRO: " . htmlspecialchars($e->getMessage()) . "</p>";
    error_log_custom("Erro ao corrigir senha master", $e);
}

echo "<hr>";
echo "<h2>Próximos Passos</h2>";
echo "<ol>";
echo "<li>Verifique se a mensagem acima diz 'SENHA ATUALIZADA COM SUCESSO'</li>";
echo "<li>Verifique se o teste de verificação mostra 'SUCESSO'</li>";
echo "<li>Tente fazer login com:<br>";
echo "   <strong>E-mail:</strong> financeiro@inlaudo.com.br<br>";
echo "   <strong>Senha:</strong> Admin259087@</li>";
echo "<li>Se ainda não funcionar, verifique os logs em /logs/</li>";
echo "</ol>";

echo "<hr>";
echo "<p><a href='login.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Ir para Login</a></p>";
echo "<p><a href='diagnostico.php' style='background: #16a34a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Ver Diagnóstico Completo</a></p>";

echo "</body></html>";
?>
