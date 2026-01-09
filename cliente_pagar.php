<?php
/**
 * Portal do Cliente - Realizar Pagamento
 * ERP INLAUDO - Versão 8.0
 * Fluxo direto: Clique → Redirect para checkout
 */

require_once 'verifica_sessao_cliente.php';
require_once 'config.php';

$conn = getConnection();

$conta_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$conta_id) {
    header('Location: cliente_contas_pagar.php');
    exit;
}

// Buscar conta e verificar se pertence ao cliente
$stmt = $conn->prepare("
    SELECT cr.*
    FROM contas_receber cr
    WHERE cr.id = ? AND cr.cliente_id = ?
");
$stmt->execute([$conta_id, $cliente_id]);
$conta = $stmt->fetch();

if (!$conta) {
    header('Location: cliente_contas_pagar.php');
    exit;
}

// Verificar se conta já foi paga
if ($conta['status'] == 'pago') {
    header('Location: cliente_contas_pagar.php?erro=conta_paga');
    exit;
}

// Buscar anexos da conta
$stmtAnexos = $conn->prepare("
    SELECT * 
    FROM contas_receber_anexos 
    WHERE conta_receber_id = ? 
    ORDER BY data_upload DESC
");
$stmtAnexos->execute([$conta_id]);
$anexos = $stmtAnexos->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Realizar Pagamento - Portal do Cliente</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .payment-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .payment-header {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            padding: 2rem;
            border-radius: 12px 12px 0 0;
            border-bottom: 3px solid #10b981;
        }
        
        .payment-header h1 {
            color: #065f46;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .payment-header p {
            color: #047857;
            font-size: 1rem;
        }
        
        .payment-body {
            padding: 2rem;
        }
        
        .info-section {
            background: #f0fdf4;
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #10b981;
            margin-bottom: 2rem;
        }
        
        .info-section h3 {
            color: #065f46;
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .info-label {
            color: #047857;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .info-value {
            color: #065f46;
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .info-value.large {
            font-size: 1.8rem;
            color: #10b981;
        }
        
        /* SEÇÃO DE ANEXOS */
        .anexos-section {
            background: #fffbeb;
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #f59e0b;
            margin-bottom: 2rem;
        }
        
        .anexos-section h3 {
            color: #92400e;
            font-size: 1.2rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .anexos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .anexo-card {
            background: white;
            border: 2px solid #fbbf24;
            border-radius: 10px;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }
        
        .anexo-card:hover {
            border-color: #f59e0b;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
            transform: translateY(-2px);
        }
        
        .anexo-icon {
            font-size: 2.5rem;
            flex-shrink: 0;
        }
        
        .anexo-info {
            flex: 1;
            min-width: 0;
        }
        
        .anexo-nome {
            font-weight: 600;
            color: #92400e;
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .anexo-meta {
            font-size: 0.8rem;
            color: #b45309;
        }
        
        .anexo-action {
            background: #f59e0b;
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .gateway-section {
            margin-bottom: 2rem;
        }
        
        .gateway-section h3 {
            color: #1e293b;
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .gateway-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .gateway-option {
            position: relative;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .gateway-option:hover {
            border-color: #10b981;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            transform: translateY(-2px);
        }
        
        .gateway-content {
            padding-left: 1rem;
            border-left: 4px solid transparent;
            transition: all 0.3s;
        }
        
        .gateway-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .gateway-name {
            color: #1e293b;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .gateway-description {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 0.75rem;
        }
        
        .gateway-methods {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .method-badge {
            background: #f0fdf4;
            color: #065f46;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .gateway-grid {
                grid-template-columns: 1fr;
            }
            
            .anexos-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <a href="cliente_contas_pagar.php" class="btn-back">← Voltar</a>
                <div>
                    <h2>💳 Realizar Pagamento</h2>
                </div>
            </div>
            <div>
                <span><?php echo htmlspecialchars($cliente_nome); ?></span>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="payment-card">
            <div class="payment-header">
                <h1>💳 Realizar Pagamento</h1>
                <p>Selecione a forma de pagamento e prossiga com segurança</p>
            </div>
            
            <div class="payment-body">
                <!-- Informações da Conta -->
                <div class="info-section">
                    <h3>📋 Informações da Conta</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Descrição</span>
                            <span class="info-value"><?php echo htmlspecialchars($conta['descricao']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Valor a Pagar</span>
                            <span class="info-value large"><?php echo formatMoeda($conta['valor']); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Vencimento</span>
                            <span class="info-value">
                                <?php 
                                echo date('d/m/Y', strtotime($conta['data_vencimento']));
                                if (strtotime($conta['data_vencimento']) < time()) {
                                    echo ' <span style="color: #ef4444;">⚠️ Vencida</span>';
                                }
                                ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Parcela</span>
                            <span class="info-value"><?php echo $conta['parcela_atual']; ?>/<?php echo $conta['recorrencia']; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- SEÇÃO DE ANEXOS -->
                <?php if (count($anexos) > 0): ?>
                <div class="anexos-section">
                    <h3>📎 Documentos Anexados (<?php echo count($anexos); ?>)</h3>
                    <div class="anexos-grid">
                        <?php foreach ($anexos as $anexo): 
                            $ext = strtolower(pathinfo($anexo['nome_arquivo'], PATHINFO_EXTENSION));
                            $icon = '📎';
                            $tipo = 'Arquivo';
                            
                            if ($ext == 'pdf') {
                                $icon = '📄';
                                $tipo = 'PDF';
                            } elseif (in_array($ext, ['doc', 'docx'])) {
                                $icon = '📝';
                                $tipo = 'Word';
                            } elseif (in_array($ext, ['xls', 'xlsx'])) {
                                $icon = '📊';
                                $tipo = 'Excel';
                            } elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                                $icon = '🖼️';
                                $tipo = 'Imagem';
                            }
                        ?>
                        <a href="<?php echo $anexo['caminho_arquivo']; ?>" target="_blank" class="anexo-card" title="Clique para abrir em nova aba">
                            <span class="anexo-icon"><?php echo $icon; ?></span>
                            <div class="anexo-info">
                                <div class="anexo-nome" title="<?php echo htmlspecialchars($anexo['nome_original']); ?>">
                                    <?php echo htmlspecialchars($anexo['nome_original']); ?>
                                </div>
                                <div class="anexo-meta">
                                    <?php echo $tipo; ?> • <?php echo number_format($anexo['tamanho_arquivo'] / 1024, 2); ?> KB
                                </div>
                            </div>
                            <span class="anexo-action">👁️ Abrir</span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Seleção de Gateway -->
                <div class="gateway-section">
                    <h3>💰 Selecione a Forma de Pagamento</h3>
                    
                    <div class="gateway-grid">
                        <!-- Mercado Pago -->
                        <a href="link_pagamento/mercadopago_checkout.php?conta_id=<?php echo $conta['id']; ?>" class="gateway-option">
                            <div class="gateway-content">
                                <div class="gateway-icon">💳</div>
                                <div class="gateway-name">Mercado Pago</div>
                                <div class="gateway-description">
                                    Pague com segurança através do Mercado Pago. Aceita múltiplas formas de pagamento.
                                </div>
                                <div class="gateway-methods">
                                    <span class="method-badge">PIX</span>
                                    <span class="method-badge">Boleto</span>
                                    <span class="method-badge">Cartão</span>
                                </div>
                            </div>
                        </a>
                        
                        <!-- CORA -->
                        <a href="link_pagamento/cora_checkout.php?conta_id=<?php echo $conta['id']; ?>" class="gateway-option">
                            <div class="gateway-content">
                                <div class="gateway-icon">🏦</div>
                                <div class="gateway-name">CORA Banking</div>
                                <div class="gateway-description">
                                    Pagamento via boleto bancário registrado pelo CORA Banking.
                                </div>
                                <div class="gateway-methods">
                                    <span class="method-badge">Boleto</span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Informações de Segurança -->
        <div style="background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <h3 style="color: #1e293b; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                🔒 Pagamento Seguro
            </h3>
            <p style="color: #64748b; line-height: 1.6; margin-bottom: 0.75rem;">
                Seus dados estão protegidos. Todas as transações são processadas através de gateways de pagamento certificados e seguros.
            </p>
            <p style="color: #64748b; line-height: 1.6;">
                Após a confirmação do pagamento, o status da sua conta será atualizado automaticamente.
            </p>
        </div>
    </div>
</body>
</html>
