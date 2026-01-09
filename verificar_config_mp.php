<?php
/**
 * Script de Verificação - Configuração Mercado Pago
 * ERP INLAUDO
 * 
 * Verifica se tudo está configurado corretamente
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Verificação - Mercado Pago</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #009ee3;
            border-bottom: 3px solid #009ee3;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 30px;
            background: #fff;
            padding: 15px;
            border-left: 5px solid #009ee3;
        }
        .success {
            color: #10b981;
            font-weight: bold;
        }
        .error {
            color: #dc2626;
            font-weight: bold;
        }
        .warning {
            color: #f59e0b;
            font-weight: bold;
        }
        .info {
            background: #fff;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #3b82f6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            margin: 10px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #009ee3;
            color: white;
        }
        code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        .box {
            background: #fff;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <h1>🔍 Verificação de Configuração - Mercado Pago</h1>
    <p>Esta página verifica se tudo está configurado corretamente para o webhook funcionar.</p>

    <?php
    $conn = getConnection();
    $erros = 0;
    $avisos = 0;
    
    // ========================================
    // 1. VERIFICAR CONFIGURACOES_GATEWAY
    // ========================================
    echo '<h2>1. Tabela configuracoes_gateway</h2>';
    echo '<div class="box">';
    
    try {
        $stmt = $conn->query("SELECT * FROM configuracoes_gateway WHERE gateway = 'mercadopago'");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            echo '<p class="success">✅ Registro encontrado</p>';
            
            echo '<table>';
            echo '<tr><th>Campo</th><th>Valor</th><th>Status</th></tr>';
            
            // Ativo
            $ativoStatus = $config['ativo'] ? '<span class="success">✅ SIM</span>' : '<span class="error">❌ NÃO</span>';
            echo "<tr><td>Ativo</td><td>{$config['ativo']}</td><td>{$ativoStatus}</td></tr>";
            if (!$config['ativo']) $erros++;
            
            // Access Token
            $tokenStatus = !empty($config['access_token']) ? '<span class="success">✅ Preenchido</span>' : '<span class="error">❌ VAZIO</span>';
            $tokenValue = !empty($config['access_token']) ? substr($config['access_token'], 0, 20) . '...' : 'VAZIO';
            echo "<tr><td>Access Token</td><td><code>{$tokenValue}</code></td><td>{$tokenStatus}</td></tr>";
            if (empty($config['access_token'])) $erros++;
            
            // Public Key
            $pkStatus = !empty($config['public_key']) ? '<span class="success">✅ Preenchido</span>' : '<span class="warning">⚠️ VAZIO</span>';
            $pkValue = !empty($config['public_key']) ? substr($config['public_key'], 0, 20) . '...' : 'VAZIO';
            echo "<tr><td>Public Key</td><td><code>{$pkValue}</code></td><td>{$pkStatus}</td></tr>";
            if (empty($config['public_key'])) $avisos++;
            
            // Webhook URL
            $webhookStatus = !empty($config['webhook_url']) ? '<span class="success">✅ Preenchido</span>' : '<span class="error">❌ VAZIO</span>';
            $webhookValue = !empty($config['webhook_url']) ? $config['webhook_url'] : 'VAZIO';
            echo "<tr><td>Webhook URL</td><td><code>{$webhookValue}</code></td><td>{$webhookStatus}</td></tr>";
            if (empty($config['webhook_url'])) $erros++;
            
            // Ambiente
            $ambienteValue = $config['ambiente'] ?? 'não definido';
            echo "<tr><td>Ambiente</td><td>{$ambienteValue}</td><td>ℹ️ Info</td></tr>";
            
            echo '</table>';
            
        } else {
            echo '<p class="error">❌ Nenhum registro encontrado para Mercado Pago</p>';
            echo '<div class="info">';
            echo '<strong>Solução:</strong> Execute o script SQL para criar o registro:<br><br>';
            echo '<code>INSERT INTO configuracoes_gateway (gateway, access_token, public_key, webhook_url, ativo) VALUES (\'mercadopago\', \'SEU_TOKEN\', \'SUA_KEY\', \'https://erp.inlaudo.com.br/webhook_mercadopago.php\', 1);</code>';
            echo '</div>';
            $erros++;
        }
        
    } catch (Exception $e) {
        echo '<p class="error">❌ Erro ao consultar tabela: ' . htmlspecialchars($e->getMessage()) . '</p>';
        $erros++;
    }
    
    echo '</div>';
    
    // ========================================
    // 2. VERIFICAR INTEGRACOES_PAGAMENTO (FALLBACK)
    // ========================================
    echo '<h2>2. Tabela integracoes_pagamento (Fallback)</h2>';
    echo '<div class="box">';
    
    try {
        $stmt = $conn->query("SELECT * FROM integracoes_pagamento WHERE gateway = 'mercadopago'");
        $config2 = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config2) {
            echo '<p class="success">✅ Registro encontrado (pode ser usado como fallback)</p>';
            
            $tokenStatus2 = !empty($config2['mp_access_token']) ? '<span class="success">✅ Preenchido</span>' : '<span class="error">❌ VAZIO</span>';
            echo '<p>Access Token: ' . $tokenStatus2 . '</p>';
            
        } else {
            echo '<p class="warning">⚠️ Nenhum registro encontrado (não é crítico se configuracoes_gateway estiver OK)</p>';
        }
        
    } catch (Exception $e) {
        echo '<p class="warning">⚠️ Tabela não existe ou erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    
    echo '</div>';
    
    // ========================================
    // 3. VERIFICAR PASTA DE LOGS
    // ========================================
    echo '<h2>3. Pasta de Logs</h2>';
    echo '<div class="box">';
    
    $logDir = __DIR__ . '/logs';
    
    if (is_dir($logDir)) {
        echo '<p class="success">✅ Pasta existe: <code>' . $logDir . '</code></p>';
        
        if (is_writable($logDir)) {
            echo '<p class="success">✅ Pasta tem permissão de escrita</p>';
        } else {
            echo '<p class="error">❌ Pasta SEM permissão de escrita</p>';
            echo '<div class="info"><strong>Solução:</strong> Execute: <code>chmod 755 ' . $logDir . '</code></div>';
            $erros++;
        }
        
        // Verificar se existem logs
        $logFile = $logDir . '/webhook_mercadopago.log';
        if (file_exists($logFile)) {
            $size = filesize($logFile);
            echo '<p class="success">✅ Arquivo de log existe (' . number_format($size) . ' bytes)</p>';
            
            // Mostrar últimas 10 linhas
            $lines = file($logFile);
            $lastLines = array_slice($lines, -10);
            
            echo '<p><strong>Últimas 10 linhas do log:</strong></p>';
            echo '<pre style="background:#f1f5f9;padding:10px;border-radius:5px;overflow-x:auto;">';
            echo htmlspecialchars(implode('', $lastLines));
            echo '</pre>';
        } else {
            echo '<p class="warning">⚠️ Arquivo de log ainda não foi criado (será criado no primeiro webhook)</p>';
        }
        
    } else {
        echo '<p class="error">❌ Pasta não existe: <code>' . $logDir . '</code></p>';
        echo '<div class="info"><strong>Solução:</strong> Execute: <code>mkdir -p ' . $logDir . ' && chmod 755 ' . $logDir . '</code></div>';
        $erros++;
    }
    
    echo '</div>';
    
    // ========================================
    // 4. VERIFICAR CONTAS COM PAYMENT_ID
    // ========================================
    echo '<h2>4. Contas a Receber</h2>';
    echo '<div class="box">';
    
    // Total com payment_id
    $stmt = $conn->query("SELECT COUNT(*) as total FROM contas_receber WHERE payment_id IS NOT NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo '<p>Total de contas com Payment ID: <strong>' . $result['total'] . '</strong></p>';
    
    // Pendentes com payment_id
    $stmt = $conn->query("SELECT COUNT(*) as total FROM contas_receber WHERE payment_id IS NOT NULL AND status = 'pendente'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pendentes = $result['total'];
    
    if ($pendentes > 0) {
        echo '<p class="warning">⚠️ <strong>' . $pendentes . '</strong> conta(s) pendente(s) com Payment ID</p>';
        echo '<p>Estas contas podem estar pagas no Mercado Pago mas não atualizadas no sistema.</p>';
        $avisos++;
        
        // Listar contas pendentes
        $stmt = $conn->query("
            SELECT id, descricao, valor, payment_id, data_vencimento 
            FROM contas_receber 
            WHERE payment_id IS NOT NULL AND status = 'pendente' 
            LIMIT 10
        ");
        
        echo '<table>';
        echo '<tr><th>ID</th><th>Descrição</th><th>Valor</th><th>Payment ID</th><th>Vencimento</th></tr>';
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . htmlspecialchars($row['descricao']) . '</td>';
            echo '<td>R$ ' . number_format($row['valor'], 2, ',', '.') . '</td>';
            echo '<td><code>' . htmlspecialchars($row['payment_id']) . '</code></td>';
            echo '<td>' . date('d/m/Y', strtotime($row['data_vencimento'])) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
    } else {
        echo '<p class="success">✅ Nenhuma conta pendente com Payment ID</p>';
    }
    
    echo '</div>';
    
    // ========================================
    // 5. TESTAR API DO MERCADO PAGO
    // ========================================
    echo '<h2>5. Teste de API do Mercado Pago</h2>';
    echo '<div class="box">';
    
    if (!empty($config['access_token'])) {
        $ch = curl_init('https://api.mercadopago.com/v1/payments/search?limit=1');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $config['access_token']
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            echo '<p class="error">❌ Erro cURL: ' . htmlspecialchars($curlError) . '</p>';
            $erros++;
        } elseif ($httpCode == 200) {
            echo '<p class="success">✅ API respondeu corretamente (HTTP 200)</p>';
            echo '<p>Access Token está válido e funcionando.</p>';
        } elseif ($httpCode == 401) {
            echo '<p class="error">❌ API retornou HTTP 401 (Não autorizado)</p>';
            echo '<p>Access Token está inválido ou expirado.</p>';
            $erros++;
        } else {
            echo '<p class="error">❌ API retornou HTTP ' . $httpCode . '</p>';
            echo '<pre>' . htmlspecialchars($response) . '</pre>';
            $erros++;
        }
        
    } else {
        echo '<p class="error">❌ Não é possível testar (Access Token não configurado)</p>';
        $erros++;
    }
    
    echo '</div>';
    
    // ========================================
    // 6. VERIFICAR WEBHOOK URL
    // ========================================
    echo '<h2>6. Webhook URL</h2>';
    echo '<div class="box">';
    
    $webhookUrl = 'https://erp.inlaudo.com.br/webhook_mercadopago.php';
    echo '<p>URL esperada: <code>' . $webhookUrl . '</code></p>';
    
    if (!empty($config['webhook_url'])) {
        if ($config['webhook_url'] == $webhookUrl) {
            echo '<p class="success">✅ URL está correta</p>';
        } else {
            echo '<p class="warning">⚠️ URL configurada é diferente: <code>' . htmlspecialchars($config['webhook_url']) . '</code></p>';
            $avisos++;
        }
    } else {
        echo '<p class="error">❌ URL não configurada</p>';
        $erros++;
    }
    
    echo '<div class="info">';
    echo '<strong>Lembre-se:</strong> Esta URL também precisa estar configurada no painel do Mercado Pago em:<br>';
    echo '<a href="https://www.mercadopago.com.br/developers/panel/app" target="_blank">https://www.mercadopago.com.br/developers/panel/app</a>';
    echo '</div>';
    
    echo '</div>';
    
    // ========================================
    // RESUMO FINAL
    // ========================================
    echo '<h2>📊 Resumo Final</h2>';
    echo '<div class="box">';
    
    if ($erros == 0 && $avisos == 0) {
        echo '<p class="success" style="font-size:18px;">✅ Tudo está configurado corretamente!</p>';
        echo '<p>O webhook deve funcionar normalmente.</p>';
    } elseif ($erros == 0) {
        echo '<p class="warning" style="font-size:18px;">⚠️ Configuração OK, mas com ' . $avisos . ' aviso(s)</p>';
        echo '<p>O webhook deve funcionar, mas verifique os avisos acima.</p>';
    } else {
        echo '<p class="error" style="font-size:18px;">❌ Encontrados ' . $erros . ' erro(s) crítico(s)</p>';
        echo '<p>Corrija os erros acima para o webhook funcionar corretamente.</p>';
    }
    
    echo '</div>';
    ?>
    
    <div style="margin-top:30px;padding:20px;background:#fff;border-radius:8px;">
        <h3>🔧 Próximos Passos</h3>
        <ol>
            <li>Corrija todos os erros críticos (❌) listados acima</li>
            <li>Verifique os avisos (⚠️) se houver</li>
            <li>Configure a URL do webhook no painel do Mercado Pago</li>
            <li>Faça um pagamento de teste</li>
            <li>Verifique os logs em <code>/logs/webhook_mercadopago.log</code></li>
        </ol>
    </div>
    
</body>
</html>
