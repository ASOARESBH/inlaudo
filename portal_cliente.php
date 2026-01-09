<?php
/**
 * Portal do Cliente - Página Inicial
 * ERP INLAUDO - Versão 7.3
 */

require_once 'verifica_sessao_cliente.php';
require_once 'config.php';

$conn = getConnection();

// Buscar estatísticas do cliente
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM contratos 
    WHERE cliente_id = ? AND status = 'ativo'
");
$stmt->execute([$cliente_id]);
$totalContratosAtivos = $stmt->fetch()['total'];

$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM contratos 
    WHERE cliente_id = ?
");
$stmt->execute([$cliente_id]);
$totalContratos = $stmt->fetch()['total'];

$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM contas_receber 
    WHERE cliente_id = ? AND status = 'pendente'
");
$stmt->execute([$cliente_id]);
$totalContasPendentes = $stmt->fetch()['total'];

$stmt = $conn->prepare("
    SELECT SUM(valor) as total 
    FROM contas_receber 
    WHERE cliente_id = ? AND status = 'pendente'
");
$stmt->execute([$cliente_id]);
$valorPendente = $stmt->fetch()['total'] ?? 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM contas_receber 
    WHERE cliente_id = ? AND status = 'pendente' AND data_vencimento < CURDATE()
");
$stmt->execute([$cliente_id]);
$totalVencidas = $stmt->fetch()['total'];

// Buscar dados do cliente
$stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$cliente_id]);
$cliente = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Cliente - INLAUDO</title>
    <link rel="stylesheet" href="css/portal_cliente.css">
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
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header-left h1 {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }
        
        .header-left p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .header-right {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .welcome-banner {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .welcome-banner h2 {
            color: #1e293b;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .welcome-banner p {
            color: #64748b;
            font-size: 1.1rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        
        .stat-card.green { border-left-color: #10b981; }
        .stat-card.blue { border-left-color: #3b82f6; }
        .stat-card.yellow { border-left-color: #f59e0b; }
        .stat-card.red { border-left-color: #ef4444; }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            font-size: 2.5rem;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
        }
        
        .stat-value.green { color: #10b981; }
        .stat-value.yellow { color: #f59e0b; }
        .stat-value.red { color: #ef4444; }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        
        .menu-card {
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
            transition: all 0.3s;
            border-top: 4px solid;
        }
        
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .menu-card.green { border-top-color: #10b981; }
        .menu-card.blue { border-top-color: #3b82f6; }
        
        .menu-card-icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
        }
        
        .menu-card h3 {
            color: #1e293b;
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
        }
        
        .menu-card p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .menu-card-footer {
            color: #10b981;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .container {
                padding: 1rem;
            }
            
            .stats-grid,
            .menu-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <h1>Portal do Cliente</h1>
                <p>Bem-vindo, <?php echo htmlspecialchars($cliente_nome); ?>!</p>
            </div>
            <div class="header-right">
                <span>CNPJ: <?php echo formatCNPJ($cliente_cnpj); ?></span>
                <a href="logout_cliente.php" class="btn-logout">🚪 Sair</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome-banner">
            <h2>👋 Olá, <?php echo htmlspecialchars($cliente_nome); ?>!</h2>
            <p>Acesse suas informações, contratos e financeiro de forma rápida e segura.</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card green">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-label">Contratos Ativos</div>
                        <div class="stat-value green"><?php echo $totalContratosAtivos; ?></div>
                    </div>
                    <div class="stat-icon">📄</div>
                </div>
            </div>
            
            <div class="stat-card blue">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-label">Total de Contratos</div>
                        <div class="stat-value"><?php echo $totalContratos; ?></div>
                    </div>
                    <div class="stat-icon">📋</div>
                </div>
            </div>
            
            <div class="stat-card yellow">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-label">Contas Pendentes</div>
                        <div class="stat-value yellow"><?php echo $totalContasPendentes; ?></div>
                        <div class="stat-label"><?php echo formatMoeda($valorPendente); ?></div>
                    </div>
                    <div class="stat-icon">💰</div>
                </div>
            </div>
            
            <?php if ($totalVencidas > 0): ?>
            <div class="stat-card red">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-label">Contas Vencidas</div>
                        <div class="stat-value red"><?php echo $totalVencidas; ?></div>
                    </div>
                    <div class="stat-icon">⚠️</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="menu-grid">
            <a href="cliente_contratos.php" class="menu-card green">
                <div class="menu-card-icon">📄</div>
                <h3>Meus Contratos</h3>
                <p>Visualize todos os seus contratos ativos e inativos, com descrição, valor, forma de pagamento e período. Baixe os contratos em PDF.</p>
                <div class="menu-card-footer">
                    Acessar Contratos →
                </div>
            </a>
            
            <a href="cliente_contas_pagar.php" class="menu-card blue">
                <div class="menu-card-icon">💳</div>
                <h3>Contas a Pagar</h3>
                <p>Veja todas as suas contas pendentes, vencimentos e realize pagamentos online através dos nossos gateways de pagamento.</p>
                <div class="menu-card-footer">
                    Acessar Financeiro →
                </div>
            </a>
        </div>
    </div>
</body>
</html>
