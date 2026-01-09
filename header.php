<?php
/**
 * Header Profissional - ERP Inlaudo
 * Novo layout com Bootstrap 5 e design responsivo
 */

session_start();

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Definir título padrão se não estiver definido
if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo htmlspecialchars($pageTitle); ?> - ERP INLAUDO</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    
    <!-- Dashboard CSS -->
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/tabelas_profissional.css">
    
    <style>
        :root {
            --primary-color: #1e40af;
            --primary-light: #3b82f6;
            --primary-dark: #1e3a8a;
            --success-color: #16a34a;
            --danger-color: #dc2626;
            --warning-color: #f59e0b;
            --info-color: #0891b2;
            --light-bg: #f8fafc;
            --border-color: #e2e8f0;
            --text-muted: #64748b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1f2937;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* ============================================================================
           NAVBAR
           ============================================================================ */
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 0.75rem 0;
            position: sticky;
            top: 0;
            z-index: 1030;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-right: 2rem;
        }
        
        .navbar-brand img {
            height: 35px;
            width: auto;
            object-fit: contain;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 0 0.25rem;
            padding: 0.5rem 0.75rem !important;
            font-size: 0.95rem;
            border-radius: 4px;
        }
        
        .nav-link:hover {
            color: white !important;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .nav-link.active {
            color: white !important;
            background-color: rgba(255, 255, 255, 0.15);
        }
        
        .dropdown-menu {
            background-color: #fff;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            margin-top: 0.5rem;
        }
        
        .dropdown-item {
            color: #1f2937;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 0.75rem 1.5rem;
            border-left: 3px solid transparent;
        }
        
        .dropdown-item:hover {
            background-color: var(--light-bg);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }
        
        .dropdown-divider {
            margin: 0.5rem 0;
        }
        
        /* ============================================================================
           MAIN CONTENT
           ============================================================================ */
        
        main {
            flex: 1;
            padding: 1.5rem;
        }
        
        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* ============================================================================
           CARDS E COMPONENTES
           ============================================================================ */
        
        .card {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: var(--light-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem;
            border-radius: 12px 12px 0 0;
        }
        
        .card-header h2,
        .card-header h3,
        .card-header h4,
        .card-header h5 {
            margin: 0;
            font-weight: 700;
            color: #1f2937;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        /* ============================================================================
           BADGES E STATUS
           ============================================================================ */
        
        .badge {
            padding: 0.35rem 0.7rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge.bg-success {
            background-color: rgba(22, 163, 74, 0.2) !important;
            color: var(--success-color);
        }
        
        .badge.bg-danger {
            background-color: rgba(220, 38, 38, 0.2) !important;
            color: var(--danger-color);
        }
        
        .badge.bg-warning {
            background-color: rgba(245, 158, 11, 0.2) !important;
            color: var(--warning-color);
        }
        
        .badge.bg-info {
            background-color: rgba(8, 145, 178, 0.2) !important;
            color: var(--info-color);
        }
        
        /* ============================================================================
           TABELAS
           ============================================================================ */
        
        .table {
            margin-bottom: 0;
            font-size: 0.9rem;
        }
        
        .table thead {
            background-color: var(--light-bg);
            border-bottom: 2px solid var(--border-color);
        }
        
        .table thead th {
            border: none;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.75rem;
        }
        
        .table tbody td {
            border: none;
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background-color: var(--light-bg);
        }
        
        /* ============================================================================
           BOTÕES
           ============================================================================ */
        
        .btn {
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #15803d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #b91c1c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }
        
        .btn-info {
            background-color: var(--info-color);
            color: white;
        }
        
        .btn-info:hover {
            background-color: #0e7490;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(8, 145, 178, 0.3);
        }
        
        .btn-sm {
            padding: 0.35rem 0.7rem;
            font-size: 0.8rem;
        }
        
        /* ============================================================================
           FORMULÁRIOS
           ============================================================================ */
        
        .form-control,
        .form-select {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        /* ============================================================================
           ALERTAS
           ============================================================================ */
        
        .alert {
            border: 1px solid;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: rgba(22, 163, 74, 0.1);
            border-color: var(--success-color);
            color: #15803d;
        }
        
        .alert-danger {
            background-color: rgba(220, 38, 38, 0.1);
            border-color: var(--danger-color);
            color: #b91c1c;
        }
        
        .alert-warning {
            background-color: rgba(245, 158, 11, 0.1);
            border-color: var(--warning-color);
            color: #d97706;
        }
        
        .alert-info {
            background-color: rgba(8, 145, 178, 0.1);
            border-color: var(--info-color);
            color: #0e7490;
        }
        
        /* ============================================================================
           RESPONSIVIDADE
           ============================================================================ */
        
        @media (max-width: 768px) {
            .navbar {
                padding: 0.5rem 0;
            }
            
            .navbar-brand {
                font-size: 1.1rem;
                margin-right: 1rem;
            }
            
            .navbar-brand img {
                height: 30px;
            }
            
            .nav-link {
                font-size: 0.85rem;
                padding: 0.4rem 0.5rem !important;
            }
            
            main {
                padding: 1rem;
            }
            
            .card {
                margin-bottom: 1rem;
            }
            
            .card-header {
                padding: 1rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .table {
                font-size: 0.8rem;
            }
            
            .table thead th,
            .table tbody td {
                padding: 0.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .navbar-brand {
                font-size: 0.95rem;
            }
            
            .navbar-brand img {
                height: 25px;
            }
            
            .nav-link {
                font-size: 0.75rem;
                padding: 0.3rem 0.4rem !important;
            }
            
            main {
                padding: 0.75rem;
            }
            
            .card {
                margin-bottom: 0.75rem;
            }
            
            .card-header {
                padding: 0.75rem;
            }
            
            .card-body {
                padding: 0.75rem;
            }
            
            .table {
                font-size: 0.75rem;
            }
            
            .table thead th,
            .table tbody td {
                padding: 0.4rem;
            }
            
            .btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    
    <!-- ============================================================================
         NAVBAR
         ============================================================================ -->
    
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid px-2 px-md-3">
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/logo.png" alt="Inlaudo" onerror="this.style.display='none'">
                <span>ERP INLAUDO</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>" href="index.php">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="crmDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-users"></i> CRM
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="crmDropdown">
                            <li><a class="dropdown-item" href="clientes.php">Clientes</a></li>
                            <li><a class="dropdown-item" href="interacoes.php">Interações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="cliente_form.php">Novo Cliente</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="financeiroDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-money-bill"></i> Financeiro
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="financeiroDropdown">
                            <li><a class="dropdown-item" href="contas_receber.php">Contas a Receber</a></li>
                            <li><a class="dropdown-item" href="contas_pagar.php">Contas a Pagar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="fluxo_caixa.php">Fluxo de Caixa</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="produtosDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-box"></i> Produtos
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="produtosDropdown">
                            <li><a class="dropdown-item" href="contratos.php">Contratos</a></li>
                            <li><a class="dropdown-item" href="produtos.php">Produtos</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="faturamentoDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-file-invoice"></i> Faturamento
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="faturamentoDropdown">
                            <li><a class="dropdown-item" href="faturamento.php">Faturas</a></li>
                            <li><a class="dropdown-item" href="faturas_mercadopago.php">Mercado Pago</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="relatoriosDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-chart-bar"></i> Relatórios
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="relatoriosDropdown">
                            <li><a class="dropdown-item" href="relatorios.php">Todos os Relatórios</a></li>
                            <li><a class="dropdown-item" href="relatorios.php?tipo=clientes">Clientes</a></li>
                            <li><a class="dropdown-item" href="relatorios.php?tipo=contratos">Contratos</a></li>
                            <li><a class="dropdown-item" href="relatorios.php?tipo=contas_pagar">Contas a Pagar</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="integracoesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-plug"></i> Integrações
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="integracoesDropdown">
                            <li><a class="dropdown-item" href="integracao_asaas.php">Asaas</a></li>
                            <li><a class="dropdown-item" href="integracao_mercadopago.php">Mercado Pago</a></li>
                            <li><a class="dropdown-item" href="integracoes_boleto.php">Boleto (CORA/Stripe)</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="email_config.php">E-mail Config</a></li>
                            <li><a class="dropdown-item" href="email_templates.php">Templates</a></li>
                            <li><a class="dropdown-item" href="logs_integracao.php">Logs</a></li>
                        </ul>
                    </li>
                    
                    <?php if (isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="usuariosDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-shield"></i> Usuários
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="usuariosDropdown">
                            <li><a class="dropdown-item" href="usuarios.php">Gerenciar Usuários</a></li>
                            <li><a class="dropdown-item" href="criar_usuario_cliente.php">Novo Usuário</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="usuarioDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo substr(htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário'), 0, 12); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="usuarioDropdown">
                            <li><a class="dropdown-item" href="perfil.php">Meu Perfil</a></li>
                            <li><a class="dropdown-item" href="configuracoes.php">Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- ============================================================================
         MAIN CONTENT
         ============================================================================ -->
    
    <main class="flex-grow-1">
