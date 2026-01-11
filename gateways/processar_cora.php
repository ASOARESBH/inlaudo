<?php
/**
 * Processador de Pagamento - Cora
 * Versão: 2.3.0
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/models/GatewayPagamentoModel.php';

// Verificar autenticação
if (!isset($_SESSION['cliente_portal'])) {
    header('Location: ../portal_cliente.php');
    exit;
}

$conn = getConnection();
$gatewayModel = new GatewayPagamentoModel($conn);

// Buscar ID da transação
$transacaoId = isset($_GET['transacao_id']) ? (int)$_GET['transacao_id'] : 0;

if ($transacaoId <= 0) {
    header('Location: ../portal_cliente.php?erro=transacao_invalida');
    exit;
}

// Buscar dados da transação
$stmt = $conn->prepare("
    SELECT gt.*, cr.descricao, cr.valor, cr.data_vencimento, c.nome, c.email, c.cpf_cnpj
    FROM gateway_transacoes gt
    INNER JOIN contas_receber cr ON gt.conta_receber_id = cr.id
    INNER JOIN clientes c ON cr.cliente_id = c.id
    WHERE gt.id = ? AND cr.cliente_id = ?
");
$stmt->execute([$transacaoId, $_SESSION['cliente_portal']['id']]);
$transacao = $stmt->fetch();

if (!$transacao) {
    header('Location: ../portal_cliente.php?erro=transacao_nao_encontrada');
    exit;
}

// TODO: Integrar com API do Cora
// Documentação: https://developers.cora.com.br/

// Exemplo de integração (requer API Key do Cora):
/*
$coraApiKey = getenv('CORA_API_KEY');
$coraUrl = 'https://api.cora.com.br/v1';

// Criar boleto
$boletoData = [
    'amount' => (int)($transacao['valor'] * 100), // Valor em centavos
    'due_date' => $transacao['data_vencimento'],
    'description' => $transacao['descricao'],
    'payer' => [
        'name' => $transacao['nome'],
        'email' => $transacao['email'],
        'document' => preg_replace('/[^0-9]/', '', $transacao['cpf_cnpj'])
    ],
    'external_reference' => $transacaoId
];

$ch = curl_init($coraUrl . '/charges');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($boletoData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $coraApiKey
]);

$response = curl_exec($ch);
$boleto = json_decode($response, true);
curl_close($ch);

// Atualizar transação
$stmt = $conn->prepare("UPDATE gateway_transacoes SET transacao_gateway_id = ?, dados_adicionais = ? WHERE id = ?");
$stmt->execute([
    $boleto['id'],
    json_encode($boleto),
    $transacaoId
]);

// Redirecionar para visualização do boleto
header('Location: ' . $boleto['url']);
exit;
*/

// Por enquanto, mostrar página de instruções
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento via Cora</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 3rem;
            text-align: center;
        }
        
        .logo {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        h1 {
            color: #1e293b;
            margin-bottom: 1rem;
        }
        
        .info-box {
            background: #fff7ed;
            border: 2px solid #f97316;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }
        
        .info-box h3 {
            color: #c2410c;
            margin-bottom: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #fed7aa;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #64748b;
        }
        
        .info-value {
            font-weight: 600;
            color: #1e293b;
        }
        
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: #f97316;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 1.5rem;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #ea580c;
            transform: translateY(-2px);
        }
        
        .warning {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            color: #92400e;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🏦</div>
        <h1>Cora</h1>
        <p>Você será redirecionado para o ambiente seguro do Cora</p>
        
        <div class="info-box">
            <h3>Detalhes do Pagamento</h3>
            <div class="info-item">
                <span class="info-label">Descrição:</span>
                <span class="info-value"><?php echo htmlspecialchars($transacao['descricao']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Valor:</span>
                <span class="info-value">R$ <?php echo number_format($transacao['valor'], 2, ',', '.'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Vencimento:</span>
                <span class="info-value"><?php echo date('d/m/Y', strtotime($transacao['data_vencimento'])); ?></span>
            </div>
        </div>
        
        <div class="warning">
            <strong>⚠️ Integração Pendente</strong><br>
            A integração com o Cora precisa ser configurada pelo administrador do sistema.
            Entre em contato com o suporte técnico.
        </div>
        
        <a href="../portal_cliente.php" class="btn">← Voltar ao Portal</a>
    </div>
</body>
</html>
