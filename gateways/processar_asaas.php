<?php
/**
 * Processador de Pagamento - Asaas
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

// TODO: Integrar com API do Asaas
// Documentação: https://docs.asaas.com/

// Exemplo de integração (requer API Key do Asaas):
/*
$asaasApiKey = getenv('ASAAS_API_KEY');
$asaasUrl = 'https://www.asaas.com/api/v3';

// Criar ou buscar customer
$customerData = [
    'name' => $transacao['nome'],
    'email' => $transacao['email'],
    'cpfCnpj' => preg_replace('/[^0-9]/', '', $transacao['cpf_cnpj'])
];

$ch = curl_init($asaasUrl . '/customers');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($customerData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'access_token: ' . $asaasApiKey
]);

$response = curl_exec($ch);
$customer = json_decode($response, true);
curl_close($ch);

// Criar cobrança
$paymentData = [
    'customer' => $customer['id'],
    'billingType' => 'UNDEFINED', // Permite escolher na hora
    'value' => (float)$transacao['valor'],
    'dueDate' => $transacao['data_vencimento'],
    'description' => $transacao['descricao'],
    'externalReference' => $transacaoId
];

$ch = curl_init($asaasUrl . '/payments');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'access_token: ' . $asaasApiKey
]);

$response = curl_exec($ch);
$payment = json_decode($response, true);
curl_close($ch);

// Atualizar transação
$stmt = $conn->prepare("UPDATE gateway_transacoes SET transacao_gateway_id = ?, dados_adicionais = ? WHERE id = ?");
$stmt->execute([
    $payment['id'],
    json_encode($payment),
    $transacaoId
]);

// Redirecionar para página de pagamento do Asaas
header('Location: ' . $payment['invoiceUrl']);
exit;
*/

// Por enquanto, mostrar página de instruções
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento via Asaas</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
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
            background: #f5f3ff;
            border: 2px solid #8b5cf6;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }
        
        .info-box h3 {
            color: #6d28d9;
            margin-bottom: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #ede9fe;
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
            background: #8b5cf6;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 1.5rem;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #7c3aed;
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
        <div class="logo">🔷</div>
        <h1>Asaas</h1>
        <p>Você será redirecionado para o ambiente seguro do Asaas</p>
        
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
            A integração com o Asaas precisa ser configurada pelo administrador do sistema.
            Entre em contato com o suporte técnico.
        </div>
        
        <a href="../portal_cliente.php" class="btn">← Voltar ao Portal</a>
    </div>
</body>
</html>
