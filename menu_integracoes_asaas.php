<?php
/**
 * Menu de Integrações - Asaas
 * 
 * Página de menu para gerenciar integrações
 * Incluindo Asaas, MercadoPago, Cora, etc
 */

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

// Obter configurações de integrações
$integracao_asaas = null;
$integracao_mercadopago = null;
$integracao_cora = null;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar Asaas
    $sql = "SELECT * FROM integracao_asaas WHERE id = 1 LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $integracao_asaas = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log('Erro ao carregar integrações: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integrações - ERP Inlaudo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
        }
        
        .header p {
            color: #666;
            font-size: 16px;
        }
        
        .breadcrumb {
            margin-top: 15px;
            font-size: 14px;
            color: #999;
        }
        
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
            margin: 0 5px;
        }
        
        .integracoes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .integracao-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .integracao-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .integracao-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        
        .integracao-icon {
            font-size: 48px;
            margin-bottom: 15px;
            display: inline-block;
        }
        
        .integracao-card h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 22px;
        }
        
        .integracao-card p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .status-badge.ativo {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.inativo {
            background: #f8d7da;
            color: #721c24;
        }
        
        .features {
            list-style: none;
            margin-bottom: 20px;
        }
        
        .features li {
            padding: 5px 0;
            color: #555;
            font-size: 13px;
        }
        
        .features li:before {
            content: '✓ ';
            color: #667eea;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .btn-small {
            padding: 8px 12px;
            font-size: 12px;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #0c5aa0;
            line-height: 1.6;
        }
        
        .info-box strong {
            display: block;
            margin-bottom: 8px;
        }
        
        .section-title {
            color: white;
            font-size: 24px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: white;
            color: #667eea;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background: #f0f0f0;
        }
        
        @media (max-width: 768px) {
            .integracoes-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Voltar ao Dashboard</a>
        
        <div class="header">
            <h1>🔌 Integrações</h1>
            <p>Gerencie as integrações de pagamento e serviços do seu ERP</p>
            <div class="breadcrumb">
                <a href="dashboard.php">Dashboard</a> > Integrações
            </div>
        </div>
        
        <h2 class="section-title">Gateways de Pagamento</h2>
        
        <div class="integracoes-grid">
            <!-- ASAAS -->
            <div class="integracao-card">
                <div class="integracao-icon">🏦</div>
                <h3>Asaas</h3>
                
                <?php if ($integracao_asaas && $integracao_asaas['ativo']): ?>
                    <span class="status-badge ativo">✓ Ativa</span>
                <?php else: ?>
                    <span class="status-badge inativo">✗ Inativa</span>
                <?php endif; ?>
                
                <p>Plataforma de pagamentos com suporte a PIX, Boleto e Cartão de Crédito. Ideal para cobranças recorrentes e pagamentos únicos.</p>
                
                <ul class="features">
                    <li>Cobranças via PIX (QR Code)</li>
                    <li>Boletos bancários</li>
                    <li>Webhooks para notificações</li>
                    <li>Relatórios em tempo real</li>
                    <li>Suporte a múltiplas contas</li>
                </ul>
                
                <div class="info-box">
                    <strong>Ambiente:</strong>
                    <?php echo ($integracao_asaas && $integracao_asaas['ambiente']) ? ucfirst($integracao_asaas['ambiente']) : 'Não configurado'; ?>
                </div>
                
                <div class="btn-group">
                    <a href="integracao_asaas_config.php" class="btn btn-primary">⚙️ Configurar</a>
                    <a href="logs_asaas_viewer.php" class="btn btn-secondary btn-small">📊 Logs</a>
                </div>
            </div>
            
            <!-- MERCADO PAGO -->
            <div class="integracao-card">
                <div class="integracao-icon">💳</div>
                <h3>Mercado Pago</h3>
                <span class="status-badge inativo">✗ Inativa</span>
                
                <p>Solução de pagamentos do Mercado Livre. Aceite cartões de crédito, débito e transferências bancárias.</p>
                
                <ul class="features">
                    <li>Cartão de crédito/débito</li>
                    <li>Transferência bancária</li>
                    <li>Dinheiro na conta</li>
                    <li>Parcelamento</li>
                    <li>Antifraude integrado</li>
                </ul>
                
                <div class="info-box">
                    <strong>Status:</strong> Disponível para configuração
                </div>
                
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="alert('Em desenvolvimento')">⚙️ Configurar</button>
                </div>
            </div>
            
            <!-- CORA -->
            <div class="integracao-card">
                <div class="integracao-icon">🏧</div>
                <h3>Cora</h3>
                <span class="status-badge inativo">✗ Inativa</span>
                
                <p>Conta digital e plataforma de pagamentos para empresas. Ideal para gestão financeira integrada.</p>
                
                <ul class="features">
                    <li>Conta digital empresarial</li>
                    <li>Transferências e pagamentos</li>
                    <li>Cartão corporativo</li>
                    <li>Integração contábil</li>
                    <li>Relatórios financeiros</li>
                </ul>
                
                <div class="info-box">
                    <strong>Status:</strong> Disponível para configuração
                </div>
                
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="alert('Em desenvolvimento')">⚙️ Configurar</button>
                </div>
            </div>
        </div>
        
        <h2 class="section-title" style="margin-top: 50px;">Serviços Adicionais</h2>
        
        <div class="integracoes-grid">
            <!-- NOTA FISCAL -->
            <div class="integracao-card">
                <div class="integracao-icon">📄</div>
                <h3>Nota Fiscal Eletrônica</h3>
                <span class="status-badge inativo">✗ Inativa</span>
                
                <p>Emissão de Notas Fiscais Eletrônicas (NF-e) e Notas Fiscais de Serviço Eletrônicas (NFS-e).</p>
                
                <ul class="features">
                    <li>Emissão de NF-e</li>
                    <li>Emissão de NFS-e</li>
                    <li>Cancelamento de notas</li>
                    <li>Integração com SEFAZ</li>
                    <li>Histórico de emissões</li>
                </ul>
                
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="alert('Em desenvolvimento')">⚙️ Configurar</button>
                </div>
            </div>
            
            <!-- SMSMARKETING -->
            <div class="integracao-card">
                <div class="integracao-icon">📱</div>
                <h3>SMS Marketing</h3>
                <span class="status-badge inativo">✗ Inativa</span>
                
                <p>Envio de SMS para notificações, lembretes e campanhas de marketing.</p>
                
                <ul class="features">
                    <li>Envio em massa</li>
                    <li>Agendamento</li>
                    <li>Templates personalizados</li>
                    <li>Relatórios de entrega</li>
                    <li>Integração com CRM</li>
                </ul>
                
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="alert('Em desenvolvimento')">⚙️ Configurar</button>
                </div>
            </div>
            
            <!-- EMAIL MARKETING -->
            <div class="integracao-card">
                <div class="integracao-icon">✉️</div>
                <h3>Email Marketing</h3>
                <span class="status-badge inativo">✗ Inativa</span>
                
                <p>Campanhas de email marketing com automação e segmentação de contatos.</p>
                
                <ul class="features">
                    <li>Criação de campanhas</li>
                    <li>Automação de envios</li>
                    <li>Segmentação de listas</li>
                    <li>Analytics detalhado</li>
                    <li>Templates responsivos</li>
                </ul>
                
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="alert('Em desenvolvimento')">⚙️ Configurar</button>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 50px; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);">
            <h3 style="color: #333; margin-bottom: 15px;">📚 Documentação</h3>
            <p style="color: #666; margin-bottom: 15px;">Para mais informações sobre cada integração, consulte a documentação:</p>
            <ul style="list-style: none; color: #666;">
                <li style="margin-bottom: 10px;">
                    <a href="docs/README_ASAAS.md" style="color: #667eea; text-decoration: none;">📖 Documentação Asaas</a>
                </li>
                <li style="margin-bottom: 10px;">
                    <a href="docs/ASAAS_IMPLEMENTATION_GUIDE.md" style="color: #667eea; text-decoration: none;">📖 Guia de Implementação</a>
                </li>
                <li>
                    <a href="docs/ASAAS_USAGE_EXAMPLES.md" style="color: #667eea; text-decoration: none;">📖 Exemplos de Uso</a>
                </li>
            </ul>
        </div>
    </div>
</body>
</html>
