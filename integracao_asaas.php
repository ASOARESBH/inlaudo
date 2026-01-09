<?php
/**
 * Dashboard Asaas - Integração de Pagamentos
 * URL: https://erp.inlaudo.com.br/integracao_asaas.php
 * 
 * Interface profissional para gerenciar integração Asaas
 */

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

$pageTitle = 'Integração Asaas';
$config = null;
$stats = [];

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar configuração
    $sql = "SELECT * FROM integracao_asaas WHERE id = 1 LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar estatísticas
    if ($config) {
        // Total de clientes mapeados
        $sql = "SELECT COUNT(*) as total FROM asaas_clientes";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['clientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de cobranças
        $sql = "SELECT COUNT(*) as total FROM asaas_pagamentos";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['cobracas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Webhooks processados
        $sql = "SELECT COUNT(*) as total FROM asaas_webhooks WHERE processado = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['webhooks'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Logs registrados
        $sql = "SELECT COUNT(*) as total FROM asaas_logs";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stats['logs'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
} catch (Exception $e) {
    error_log('Erro ao carregar Asaas: ' . $e->getMessage());
}

include 'header.php';
?>

<div class="container" style="max-width: 1200px; margin: 40px auto;">
    
    <!-- Cabeçalho -->
    <div style="margin-bottom: 40px;">
        <h1 style="margin: 0 0 10px 0; color: #333;">Integração Asaas</h1>
        <p style="color: #666; margin: 0;">Gerencie sua integração de pagamentos com o Asaas</p>
    </div>
    
    <!-- Status da Integração -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px;">
        
        <!-- Card: Status -->
        <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
            <h3 style="margin: 0 0 15px 0; color: #333; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px;">Status</h3>
            <div style="font-size: 2em; font-weight: bold; margin-bottom: 10px;">
                <?php if ($config && $config['ativo']): ?>
                    <span style="color: #28a745;">Ativo</span>
                <?php else: ?>
                    <span style="color: #dc3545;">Inativo</span>
                <?php endif; ?>
            </div>
            <p style="color: #666; margin: 0; font-size: 0.9em;">
                <?php echo $config ? 'Integração configurada e ' . ($config['ativo'] ? 'ativa' : 'desativada') : 'Não configurada'; ?>
            </p>
        </div>
        
        <!-- Card: Ambiente -->
        <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
            <h3 style="margin: 0 0 15px 0; color: #333; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px;">Ambiente</h3>
            <div style="font-size: 2em; font-weight: bold; margin-bottom: 10px;">
                <?php if ($config): ?>
                    <span style="color: <?php echo $config['ambiente'] === 'production' ? '#dc3545' : '#ffc107'; ?>;">
                        <?php echo ucfirst($config['ambiente']); ?>
                    </span>
                <?php else: ?>
                    <span style="color: #999;">—</span>
                <?php endif; ?>
            </div>
            <p style="color: #666; margin: 0; font-size: 0.9em;">
                <?php echo $config ? ($config['ambiente'] === 'production' ? 'Ambiente de produção' : 'Ambiente de testes') : 'Não configurado'; ?>
            </p>
        </div>
        
        <!-- Card: Clientes -->
        <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
            <h3 style="margin: 0 0 15px 0; color: #333; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px;">Clientes Mapeados</h3>
            <div style="font-size: 2em; font-weight: bold; margin-bottom: 10px; color: #667eea;">
                <?php echo $stats['clientes'] ?? 0; ?>
            </div>
            <p style="color: #666; margin: 0; font-size: 0.9em;">Clientes sincronizados com Asaas</p>
        </div>
        
        <!-- Card: Cobranças -->
        <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
            <h3 style="margin: 0 0 15px 0; color: #333; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px;">Cobranças Criadas</h3>
            <div style="font-size: 2em; font-weight: bold; margin-bottom: 10px; color: #28a745;">
                <?php echo $stats['cobracas'] ?? 0; ?>
            </div>
            <p style="color: #666; margin: 0; font-size: 0.9em;">Cobranças geradas no Asaas</p>
        </div>
        
    </div>
    
    <!-- Ações Rápidas -->
    <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 30px; margin-bottom: 40px;">
        <h2 style="margin: 0 0 20px 0; color: #333;">Ações Rápidas</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            
            <!-- Botão: Configurar -->
            <a href="integracao_asaas_config.php" 
               style="display: block; padding: 15px; background: #667eea; color: white; text-align: center; border-radius: 4px; text-decoration: none; font-weight: bold; transition: background 0.3s;">
                Configurar Credenciais
            </a>
            
            <!-- Botão: Logs -->
            <a href="logs_asaas_viewer.php" 
               style="display: block; padding: 15px; background: #6c757d; color: white; text-align: center; border-radius: 4px; text-decoration: none; font-weight: bold; transition: background 0.3s;">
                Visualizar Logs
            </a>
            
            <!-- Botão: Documentação -->
            <a href="https://docs.asaas.com" 
               target="_blank"
               style="display: block; padding: 15px; background: #17a2b8; color: white; text-align: center; border-radius: 4px; text-decoration: none; font-weight: bold; transition: background 0.3s;">
                Documentação Asaas
            </a>
            
            <!-- Botão: Webhook -->
            <a href="#webhook-info" 
               style="display: block; padding: 15px; background: #28a745; color: white; text-align: center; border-radius: 4px; text-decoration: none; font-weight: bold; transition: background 0.3s;">
                Configurar Webhook
            </a>
            
        </div>
    </div>
    
    <!-- Informações do Webhook -->
    <div id="webhook-info" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 30px; margin-bottom: 40px;">
        <h2 style="margin: 0 0 20px 0; color: #333;">Configuração de Webhook</h2>
        
        <p style="color: #666; margin-bottom: 20px;">
            Configure o webhook do Asaas para receber notificações de pagamento em tempo real. Use a URL abaixo:
        </p>
        
        <div style="background: white; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-bottom: 20px; font-family: monospace; word-break: break-all;">
            https://erp.inlaudo.com.br/webhook/asaas.php
        </div>
        
        <div style="background: #e7f3ff; border-left: 4px solid #667eea; padding: 15px; border-radius: 4px;">
            <h4 style="margin: 0 0 10px 0; color: #667eea;">Instruções</h4>
            <ol style="margin: 0; padding-left: 20px; line-height: 1.6; color: #666;">
                <li>Acesse <a href="https://app.asaas.com/webhooks" target="_blank" style="color: #667eea;">app.asaas.com/webhooks</a></li>
                <li>Clique em "Novo Webhook"</li>
                <li>Cole a URL acima</li>
                <li>Selecione os eventos: PAYMENT_RECEIVED, PAYMENT_CONFIRMED</li>
                <li>Salve e teste</li>
            </ol>
        </div>
    </div>
    
    <!-- Últimas Atividades -->
    <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 30px;">
        <h2 style="margin: 0 0 20px 0; color: #333;">Estatísticas</h2>
        
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px; font-weight: bold; color: #333;">Webhooks Processados</td>
                <td style="padding: 12px; text-align: right; color: #667eea; font-weight: bold;">
                    <?php echo $stats['webhooks'] ?? 0; ?>
                </td>
            </tr>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 12px; font-weight: bold; color: #333;">Logs Registrados</td>
                <td style="padding: 12px; text-align: right; color: #667eea; font-weight: bold;">
                    <?php echo $stats['logs'] ?? 0; ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px; font-weight: bold; color: #333;">Última Atualização</td>
                <td style="padding: 12px; text-align: right; color: #666;">
                    <?php 
                    if ($config && $config['data_atualizacao']) {
                        echo date('d/m/Y H:i:s', strtotime($config['data_atualizacao']));
                    } else {
                        echo 'Nunca atualizado';
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>
    
</div>

<?php include 'footer.php'; ?>
