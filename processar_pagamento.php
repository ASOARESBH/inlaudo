<?php
/**
 * Processador de Pagamento - Portal do Cliente
 * Versão: 2.3.0
 * 
 * Exibe gateways disponíveis e redireciona para pagamento
 */

session_start();
require_once 'config.php';
require_once 'src/models/GatewayPagamentoModel.php';

// Verificar autenticação do cliente
if (!isset($_SESSION['cliente_portal'])) {
    header('Location: portal_cliente.php');
    exit;
}

$conn = getConnection();
$gatewayModel = new GatewayPagamentoModel($conn);

// Buscar ID da conta
$contaId = isset($_GET['conta_id']) ? (int)$_GET['conta_id'] : 0;

if ($contaId <= 0) {
    header('Location: portal_cliente.php?erro=conta_invalida');
    exit;
}

// Buscar dados da conta
$stmt = $conn->prepare("
    SELECT cr.*, c.nome, c.razao_social, c.email, c.cpf_cnpj
    FROM contas_receber cr
    INNER JOIN clientes c ON cr.cliente_id = c.id
    WHERE cr.id = ? AND cr.cliente_id = ?
");
$stmt->execute([$contaId, $_SESSION['cliente_portal']['id']]);
$conta = $stmt->fetch();

if (!$conta) {
    header('Location: portal_cliente.php?erro=conta_nao_encontrada');
    exit;
}

// Verificar se conta já está paga
if ($conta['status'] == 'pago') {
    header('Location: portal_cliente.php?msg=conta_ja_paga');
    exit;
}

// Buscar gateways disponíveis para esta conta
$gateways = $gatewayModel->buscarGatewaysDisponiveis($contaId);

// Se não houver gateways configurados, mostrar todos ativos
if (empty($gateways)) {
    $gateways = $gatewayModel->listarGatewaysAtivos();
}

// Processar seleção de gateway
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['gateway_id'])) {
    $gatewayId = (int)$_POST['gateway_id'];
    
    // Buscar gateway selecionado
    $gateway = null;
    foreach ($gateways as $g) {
        if ($g['id'] == $gatewayId) {
            $gateway = $g;
            break;
        }
    }
    
    if (!$gateway) {
        $erro = "Gateway inválido";
    } else {
        // Criar transação
        try {
            $transacaoId = $gatewayModel->criarTransacao(
                $contaId,
                $gatewayId,
                $conta['valor'],
                [
                    'cliente_nome' => $conta['razao_social'] ?: $conta['nome'],
                    'cliente_email' => $conta['email'],
                    'cliente_cpf_cnpj' => $conta['cpf_cnpj'],
                    'descricao' => $conta['descricao'],
                    'vencimento' => $conta['data_vencimento']
                ]
            );
            
            // Redirecionar para processador específico do gateway
            $slug = $gateway['slug'];
            header("Location: gateways/processar_{$slug}.php?transacao_id={$transacaoId}");
            exit;
            
        } catch (Exception $e) {
            $erro = "Erro ao processar pagamento: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Selecionar Forma de Pagamento';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - ERP INLAUDO</title>
    <link rel="stylesheet" href="assets/css/forms_profissional.css">
    <link rel="stylesheet" href="assets/css/gateway-selector.css">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .payment-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            padding: 3rem;
        }
        
        .payment-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .payment-header h1 {
            color: #1e293b;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .payment-header p {
            color: #64748b;
            font-size: 1rem;
        }
        
        .conta-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .conta-info h2 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        .conta-valor {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .conta-detalhes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        
        .conta-detalhe {
            display: flex;
            flex-direction: column;
        }
        
        .conta-detalhe-label {
            font-size: 0.875rem;
            opacity: 0.8;
            margin-bottom: 0.25rem;
        }
        
        .conta-detalhe-valor {
            font-size: 1.125rem;
            font-weight: 600;
        }
        
        .gateways-section {
            margin: 2rem 0;
        }
        
        .gateways-section h3 {
            color: #1e293b;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .gateway-option {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .gateway-option:hover {
            border-color: #667eea;
            background: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .gateway-option input[type="radio"] {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        
        .gateway-icon {
            font-size: 3rem;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            color: white;
        }
        
        .gateway-info {
            flex: 1;
        }
        
        .gateway-nome {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .gateway-descricao {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .gateway-taxa {
            color: #059669;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .btn-pagar {
            width: 100%;
            padding: 1.25rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 2rem;
        }
        
        .btn-pagar:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-pagar:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-voltar {
            display: block;
            text-align: center;
            color: #64748b;
            text-decoration: none;
            margin-top: 1rem;
            font-size: 0.875rem;
        }
        
        .btn-voltar:hover {
            color: #1e293b;
        }
        
        .erro-msg {
            background: #fee2e2;
            border: 2px solid #ef4444;
            color: #991b1b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h1>💳 Pagamento</h1>
            <p>Selecione a forma de pagamento de sua preferência</p>
        </div>
        
        <?php if (isset($erro)): ?>
        <div class="erro-msg">
            <strong>⚠️ Erro:</strong> <?php echo htmlspecialchars($erro); ?>
        </div>
        <?php endif; ?>
        
        <div class="conta-info">
            <h2>Detalhes da Cobrança</h2>
            <div class="conta-valor">
                R$ <?php echo number_format($conta['valor'], 2, ',', '.'); ?>
            </div>
            <div class="conta-detalhes">
                <div class="conta-detalhe">
                    <span class="conta-detalhe-label">Descrição</span>
                    <span class="conta-detalhe-valor"><?php echo htmlspecialchars($conta['descricao']); ?></span>
                </div>
                <div class="conta-detalhe">
                    <span class="conta-detalhe-label">Vencimento</span>
                    <span class="conta-detalhe-valor"><?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?></span>
                </div>
                <?php if ($conta['parcela_atual'] && $conta['recorrencia'] > 1): ?>
                <div class="conta-detalhe">
                    <span class="conta-detalhe-label">Parcela</span>
                    <span class="conta-detalhe-valor"><?php echo $conta['parcela_atual']; ?>/<?php echo $conta['recorrencia']; ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="gateways-section">
            <h3>Escolha a forma de pagamento</h3>
            
            <form method="POST" id="formPagamento">
                <?php foreach ($gateways as $gateway): ?>
                <label class="gateway-option">
                    <input type="radio" name="gateway_id" value="<?php echo $gateway['id']; ?>" required>
                    
                    <div class="gateway-icon">
                        <?php echo htmlspecialchars($gateway['icone'] ?: '💳'); ?>
                    </div>
                    
                    <div class="gateway-info">
                        <div class="gateway-nome"><?php echo htmlspecialchars($gateway['nome']); ?></div>
                        <div class="gateway-descricao"><?php echo htmlspecialchars($gateway['descricao'] ?: 'Processamento seguro'); ?></div>
                        <?php if ($gateway['taxa_percentual'] > 0 || $gateway['taxa_fixa'] > 0): ?>
                        <div class="gateway-taxa">
                            Taxa: 
                            <?php if ($gateway['taxa_percentual'] > 0): ?>
                                <?php echo number_format($gateway['taxa_percentual'], 2, ',', '.'); ?>%
                            <?php endif; ?>
                            <?php if ($gateway['taxa_fixa'] > 0): ?>
                                + R$ <?php echo number_format($gateway['taxa_fixa'], 2, ',', '.'); ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </label>
                <?php endforeach; ?>
                
                <?php if (empty($gateways)): ?>
                <div class="erro-msg">
                    Nenhuma forma de pagamento disponível. Entre em contato com o suporte.
                </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-pagar" <?php echo empty($gateways) ? 'disabled' : ''; ?>>
                    🔒 Pagar com Segurança
                </button>
            </form>
            
            <a href="portal_cliente.php" class="btn-voltar">← Voltar para o portal</a>
        </div>
    </div>
    
    <script>
        // Adicionar efeito visual ao selecionar gateway
        document.querySelectorAll('.gateway-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.gateway-option').forEach(opt => {
                    opt.style.borderColor = '#e5e7eb';
                    opt.style.background = 'white';
                });
                this.style.borderColor = '#667eea';
                this.style.background = '#f8fafc';
            });
        });
    </script>
</body>
</html>
