<?php
/**
 * Processador de Pagamento - Mercado Pago
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

// TODO: Integrar com SDK do Mercado Pago
// Documentação: https://www.mercadopago.com.br/developers/pt/docs

// Exemplo de integração (requer SDK do Mercado Pago):
/*
require_once 'vendor/autoload.php';
MercadoPago\SDK::setAccessToken(getenv('MERCADOPAGO_ACCESS_TOKEN'));

$preference = new MercadoPago\Preference();

$item = new MercadoPago\Item();
$item->title = $transacao['descricao'];
$item->quantity = 1;
$item->unit_price = (float)$transacao['valor'];

$preference->items = array($item);
$preference->external_reference = $transacaoId;
$preference->back_urls = array(
    "success" => "https://erp.inlaudo.com.br/gateways/retorno_mercadopago.php?status=success",
    "failure" => "https://erp.inlaudo.com.br/gateways/retorno_mercadopago.php?status=failure",
    "pending" => "https://erp.inlaudo.com.br/gateways/retorno_mercadopago.php?status=pending"
);
$preference->auto_return = "approved";

$preference->save();

// Atualizar transação com ID do Mercado Pago
$stmt = $conn->prepare("UPDATE gateway_transacoes SET transacao_gateway_id = ?, dados_adicionais = ? WHERE id = ?");
$stmt->execute([
    $preference->id,
    json_encode(['preference_id' => $preference->id, 'init_point' => $preference->init_point]),
    $transacaoId
]);

// Redirecionar para checkout do Mercado Pago
header('Location: ' . $preference->init_point);
exit;
*/

// Por enquanto, mostrar página de instruções
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento via Mercado Pago</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #009ee3 0%, #0070ba 100%);
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
            background: #f0f9ff;
            border: 2px solid #0ea5e9;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }
        
        .info-box h3 {
            color: #0369a1;
            margin-bottom: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e0f2fe;
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
            background: #009ee3;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 1.5rem;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #0070ba;
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
        <div class="logo">💳</div>
        <h1>Mercado Pago</h1>
        <p>Você será redirecionado para o ambiente seguro do Mercado Pago</p>
        
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
            A integração com o Mercado Pago precisa ser configurada pelo administrador do sistema.
            Entre em contato com o suporte técnico.
        </div>
        
        <a href="../portal_cliente.php" class="btn">← Voltar ao Portal</a>
    </div>
</body>
</html>
