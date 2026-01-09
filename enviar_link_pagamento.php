<?php
/**
 * Envio de Link de Pagamento por E-mail
 * ERP INLAUDO - Versão 7.1
 */

require_once 'config.php';
require_once 'lib_email.php';
require_once 'lib_logs.php';

session_start();

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getConnection();
$contaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$enviado = false;
$erro = '';

if (!$contaId) {
    die('ID da conta não informado');
}

// Buscar dados da conta e link de pagamento
$stmt = $conn->prepare("
    SELECT 
        cr.*,
        c.nome as cliente_nome,
        c.razao_social as cliente_razao,
        c.email as cliente_email,
        cont.gateway_pagamento,
        cont.link_pagamento,
        tp.payment_url,
        tp.boleto_url,
        tp.linha_digitavel
    FROM contas_receber cr
    INNER JOIN clientes c ON cr.cliente_id = c.id
    LEFT JOIN contratos cont ON cr.contrato_id = cont.id
    LEFT JOIN transacoes_pagamento tp ON tp.conta_receber_id = cr.id
    WHERE cr.id = ?
    ORDER BY tp.id DESC
    LIMIT 1
");
$stmt->execute([$contaId]);
$conta = $stmt->fetch();

if (!$conta) {
    die('Conta não encontrada');
}

$linkPagamento = $conta['link_pagamento'] ?: $conta['payment_url'] ?: $conta['boleto_url'];

if (!$linkPagamento) {
    die('Link de pagamento não encontrado. Gere o link primeiro.');
}

// Processar envio
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $emailDestino = $_POST['email'] ?? $conta['cliente_email'];
    $assunto = $_POST['assunto'] ?? 'Link de Pagamento - INLAUDO';
    $mensagemPersonalizada = $_POST['mensagem'] ?? '';
    
    // Montar corpo do e-mail
    $corpo = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8fafc; padding: 30px; }
            .button { display: inline-block; padding: 15px 30px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
            .info-box { background: white; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0; }
            .linha-digitavel { background: #fef3c7; border: 2px dashed #f59e0b; padding: 15px; margin: 20px 0; font-family: monospace; word-break: break-all; }
            .footer { background: #1e293b; color: #94a3b8; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; font-size: 0.9rem; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>💳 Link de Pagamento</h1>
                <p>INLAUDO - Conectando Saúde e Tecnologia</p>
            </div>
            
            <div class='content'>
                <p>Olá, <strong>" . htmlspecialchars($conta['cliente_razao'] ?: $conta['cliente_nome']) . "</strong>!</p>
                
                " . ($mensagemPersonalizada ? "<p>" . nl2br(htmlspecialchars($mensagemPersonalizada)) . "</p>" : "") . "
                
                <div class='info-box'>
                    <strong>📋 Descrição:</strong> " . htmlspecialchars($conta['descricao']) . "<br>
                    <strong>💰 Valor:</strong> R$ " . number_format($conta['valor'], 2, ',', '.') . "<br>
                    <strong>📅 Vencimento:</strong> " . date('d/m/Y', strtotime($conta['data_vencimento'])) . "
                </div>
                
                <p style='text-align: center;'>
                    <a href='" . htmlspecialchars($linkPagamento) . "' class='button'>
                        🔗 Acessar Link de Pagamento
                    </a>
                </p>
                
                " . ($conta['linha_digitavel'] ? "
                <div class='linha-digitavel'>
                    <strong>Linha Digitável do Boleto:</strong><br>
                    " . $conta['linha_digitavel'] . "
                </div>
                " : "") . "
                
                <p style='color: #64748b; font-size: 0.9rem;'>
                    <strong>Observação:</strong> Este link é válido até a data de vencimento. 
                    Após o pagamento, você receberá a confirmação por e-mail.
                </p>
            </div>
            
            <div class='footer'>
                <p><strong>INLAUDO</strong><br>
                Conectando Saúde e Tecnologia</p>
                <p style='margin-top: 10px; font-size: 0.85rem;'>
                    Este é um e-mail automático, por favor não responda.<br>
                    Em caso de dúvidas, entre em contato conosco.
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    try {
        $emailLib = new EmailIntegracao();
        $resultado = $emailLib->enviarEmail(
            $emailDestino,
            $assunto,
            $corpo
        );
        
        if ($resultado['sucesso']) {
            $enviado = true;
            
            // Registrar log
            registrarLog('email', 'enviar_link_pagamento', 'sucesso', 
                "Link enviado para {$emailDestino} - Conta #{$contaId}", 
                ['email' => $emailDestino, 'conta_id' => $contaId]
            );
        } else {
            $erro = $resultado['erro'];
            
            registrarLog('email', 'enviar_link_pagamento', 'erro', 
                $resultado['erro'], 
                ['email' => $emailDestino, 'conta_id' => $contaId]
            );
        }
    } catch (Exception $e) {
        $erro = $e->getMessage();
        
        registrarLog('email', 'enviar_link_pagamento', 'erro', 
            $e->getMessage(), 
            ['exception' => $e->getTraceAsString()]
        );
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Link de Pagamento - ERP INLAUDO</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #1e293b;
            margin-bottom: 10px;
            font-size: 1.8rem;
            text-align: center;
        }
        
        .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #f8fafc;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 8px;
        }
        
        .info-card h3 {
            color: #1e293b;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .info-label {
            color: #64748b;
            font-weight: 600;
        }
        
        .info-value {
            color: #1e293b;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }
        
        input, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        input:focus, textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 10px;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #ef4444;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #64748b;
            text-decoration: none;
        }
        
        .back-link a:hover {
            color: #3b82f6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Enviar Link de Pagamento</h1>
        <p class="subtitle">Envie o link de pagamento por e-mail para o cliente</p>
        
        <?php if ($enviado): ?>
            <div class="alert alert-success">
                ✓ E-mail enviado com sucesso para <strong><?php echo htmlspecialchars($_POST['email']); ?></strong>!
            </div>
            
            <a href="faturamento_completo.php" class="btn btn-primary">
                ← Voltar para Faturamento
            </a>
            
        <?php elseif ($erro): ?>
            <div class="alert alert-error">
                ✕ Erro ao enviar e-mail: <?php echo htmlspecialchars($erro); ?>
            </div>
            
            <button onclick="location.reload()" class="btn btn-primary">
                🔄 Tentar Novamente
            </button>
            
        <?php else: ?>
            <div class="info-card">
                <h3>📋 Informações da Fatura</h3>
                <div class="info-row">
                    <span class="info-label">Cliente:</span>
                    <span class="info-value"><?php echo htmlspecialchars($conta['cliente_razao'] ?: $conta['cliente_nome']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Descrição:</span>
                    <span class="info-value"><?php echo htmlspecialchars($conta['descricao']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Valor:</span>
                    <span class="info-value">R$ <?php echo number_format($conta['valor'], 2, ',', '.'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Vencimento:</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?></span>
                </div>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>📧 E-mail do Destinatário:</label>
                    <input type="email" 
                           name="email" 
                           value="<?php echo htmlspecialchars($conta['cliente_email']); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label>📝 Assunto do E-mail:</label>
                    <input type="text" 
                           name="assunto" 
                           value="Link de Pagamento - INLAUDO" 
                           required>
                </div>
                
                <div class="form-group">
                    <label>💬 Mensagem Personalizada (opcional):</label>
                    <textarea name="mensagem" placeholder="Adicione uma mensagem personalizada para o cliente..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    📧 Enviar E-mail
                </button>
                
                <a href="faturamento_completo.php" class="btn btn-secondary">
                    ← Cancelar
                </a>
            </form>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="faturamento_completo.php">← Voltar para Faturamento</a>
        </div>
    </div>
</body>
</html>
