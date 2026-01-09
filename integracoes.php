<?php
/**
 * Gerenciamento Unificado de Integrações
 * ERP INLAUDO - Versão 8.0
 * Gerencia todos os gateways de pagamento em uma única interface
 */

$pageTitle = 'Integrações de Pagamento';
require_once 'header.php';
require_once 'config.php';

// Buscar todas as integrações
try {
    $conn = getConnection();
    $stmt = $conn->prepare("
        SELECT * FROM integracoes 
        ORDER BY 
            CASE gateway
                WHEN 'mercadopago' THEN 1
                WHEN 'cora' THEN 2
                WHEN 'stripe' THEN 3
                ELSE 4
            END
    ");
    $stmt->execute();
    $integracoes = $stmt->fetchAll();
} catch (Exception $e) {
    $integracoes = [];
}

// Função para obter ícone do gateway
function getGatewayIcon($gateway) {
    $icons = [
        'mercadopago' => '💳',
        'cora' => '🏦',
        'stripe' => '💰'
    ];
    return $icons[$gateway] ?? '🔌';
}

// Função para obter nome do gateway
function getGatewayName($gateway) {
    $names = [
        'mercadopago' => 'Mercado Pago',
        'cora' => 'CORA Banking',
        'stripe' => 'Stripe'
    ];
    return $names[$gateway] ?? ucfirst($gateway);
}

// Função para verificar se está configurado
function isConfigured($integracao) {
    $gateway = $integracao['gateway'];
    
    if ($gateway === 'mercadopago') {
        return !empty($integracao['mp_public_key']) && !empty($integracao['mp_access_token']);
    } elseif ($gateway === 'cora') {
        return !empty($integracao['cora_client_id']) && !empty($integracao['cora_certificado']);
    } elseif ($gateway === 'stripe') {
        return !empty($integracao['stripe_secret_key']) && !empty($integracao['stripe_publishable_key']);
    }
    
    return false;
}
?>

<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
                <h2>
                    <i class="fas fa-plug"></i> Integrações de Pagamento
                </h2>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>

            <!-- Cards de Integrações -->
            <div class="row">
                <?php foreach ($integracoes as $integracao): 
                    $configured = isConfigured($integracao);
                    $ativo = $integracao['ativo'];
                    $gateway = $integracao['gateway'];
                ?>
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-<?php echo $ativo ? 'success' : 'secondary'; ?> text-white">
                            <h5 class="mb-0">
                                <?php echo getGatewayIcon($gateway); ?> 
                                <?php echo getGatewayName($gateway); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Status -->
                            <div class="mb-3">
                                <span class="badge bg-<?php echo $ativo ? 'success' : 'secondary'; ?> me-2">
                                    <?php echo $ativo ? '✓ Ativo' : '○ Inativo'; ?>
                                </span>
                                <span class="badge bg-<?php echo $configured ? 'info' : 'warning'; ?>">
                                    <?php echo $configured ? '✓ Configurado' : '⚠ Não Configurado'; ?>
                                </span>
                            </div>

                            <!-- Ambiente -->
                            <div class="mb-3">
                                <small class="text-muted">Ambiente:</small>
                                <br>
                                <span class="badge bg-<?php echo $integracao['ambiente'] === 'producao' ? 'success' : 'warning'; ?>">
                                    <?php echo $integracao['ambiente'] === 'producao' ? 'Produção' : 'Teste'; ?>
                                </span>
                            </div>

                            <!-- Informações Específicas -->
                            <?php if ($gateway === 'mercadopago'): ?>
                            <div class="mb-3">
                                <small class="text-muted">Formas de pagamento:</small>
                                <br>
                                <span class="badge bg-light text-dark">PIX</span>
                                <span class="badge bg-light text-dark">Boleto</span>
                                <span class="badge bg-light text-dark">Cartão</span>
                            </div>
                            <?php elseif ($gateway === 'cora'): ?>
                            <div class="mb-3">
                                <small class="text-muted">Formas de pagamento:</small>
                                <br>
                                <span class="badge bg-light text-dark">Boleto</span>
                            </div>
                            <?php elseif ($gateway === 'stripe'): ?>
                            <div class="mb-3">
                                <small class="text-muted">Formas de pagamento:</small>
                                <br>
                                <span class="badge bg-light text-dark">Cartão</span>
                                <span class="badge bg-light text-dark">PIX</span>
                            </div>
                            <?php endif; ?>

                            <!-- Última Atualização -->
                            <?php if ($integracao['data_atualizacao']): ?>
                            <div class="mb-3">
                                <small class="text-muted">
                                    Atualizado em: <?php echo date('d/m/Y H:i', strtotime($integracao['data_atualizacao'])); ?>
                                </small>
                            </div>
                            <?php endif; ?>

                            <!-- Botão de Configuração -->
                            <a href="integracao_<?php echo $gateway; ?>.php" class="btn btn-primary w-100">
                                <i class="fas fa-cog"></i> Configurar
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Card de Informações -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle"></i> Sobre as Integrações
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6><strong>💳 Mercado Pago</strong></h6>
                            <p class="small">
                                Gateway completo com PIX, boleto e cartão. Ideal para e-commerce e cobranças recorrentes.
                            </p>
                            <ul class="small">
                                <li>PIX instantâneo</li>
                                <li>Boleto bancário</li>
                                <li>Cartão de crédito/débito</li>
                                <li>Parcelamento em até 12x</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6><strong>🏦 CORA Banking</strong></h6>
                            <p class="small">
                                Banco digital com foco em boletos registrados. Ideal para cobranças empresariais.
                            </p>
                            <ul class="small">
                                <li>Boleto registrado</li>
                                <li>Baixa automática</li>
                                <li>Notificações em tempo real</li>
                                <li>Sem taxas de emissão</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6><strong>💰 Stripe</strong></h6>
                            <p class="small">
                                Gateway internacional com suporte a múltiplas moedas. Ideal para vendas globais.
                            </p>
                            <ul class="small">
                                <li>Cartão internacional</li>
                                <li>PIX (via Stripe Brasil)</li>
                                <li>Assinaturas recorrentes</li>
                                <li>Checkout otimizado</li>
                            </ul>
                        </div>
                    </div>

                    <hr>

                    <h6><strong>Como configurar:</strong></h6>
                    <ol class="small">
                        <li>Clique em "Configurar" no gateway desejado</li>
                        <li>Preencha as credenciais obtidas no painel do gateway</li>
                        <li>Configure a URL do webhook (se necessário)</li>
                        <li>Escolha o ambiente (Teste ou Produção)</li>
                        <li>Ative a integração usando o switch</li>
                        <li>Salve as configurações</li>
                    </ol>

                    <div class="alert alert-warning mt-3" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Importante:</strong> Sempre teste em ambiente de teste antes de ativar em produção!
                    </div>
                </div>
            </div>

            <!-- Card de Estatísticas -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar"></i> Estatísticas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h3 class="text-primary"><?php echo count($integracoes); ?></h3>
                            <p class="text-muted">Total de Gateways</p>
                        </div>
                        <div class="col-md-3">
                            <h3 class="text-success">
                                <?php echo count(array_filter($integracoes, function($i) { return $i['ativo']; })); ?>
                            </h3>
                            <p class="text-muted">Ativos</p>
                        </div>
                        <div class="col-md-3">
                            <h3 class="text-info">
                                <?php echo count(array_filter($integracoes, function($i) { return isConfigured($i); })); ?>
                            </h3>
                            <p class="text-muted">Configurados</p>
                        </div>
                        <div class="col-md-3">
                            <h3 class="text-warning">
                                <?php echo count(array_filter($integracoes, function($i) { return $i['ambiente'] === 'teste'; })); ?>
                            </h3>
                            <p class="text-muted">Em Teste</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
