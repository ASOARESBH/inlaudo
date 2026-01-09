<?php
/**
 * Header - Portal do Cliente
 */

if (!isset($_SESSION)) {
    session_start();
}

require_once 'auth_cliente.php';

$cliente = getClienteInfo();
$paginaAtual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Cliente - ERP INLAUDO</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos específicos do Portal do Cliente */
        .portal-cliente-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .portal-cliente-header .logo {
            font-size: 1.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .portal-cliente-header .logo img {
            height: 40px;
            filter: brightness(0) invert(1);
        }
        
        .portal-cliente-header .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .portal-cliente-header .user-name {
            font-weight: 600;
        }
        
        .portal-cliente-header .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .portal-cliente-header .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .portal-cliente-nav {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .portal-cliente-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 0;
        }
        
        .portal-cliente-nav li {
            margin: 0;
        }
        
        .portal-cliente-nav a {
            display: block;
            padding: 15px 25px;
            color: #475569;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .portal-cliente-nav a:hover {
            background: #e2e8f0;
            color: #10b981;
        }
        
        .portal-cliente-nav a.active {
            color: #10b981;
            border-bottom-color: #10b981;
            background: white;
        }
        
        .portal-cliente-content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .portal-welcome {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }
        
        .portal-welcome h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
        }
        
        .portal-welcome p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .portal-cliente-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .portal-cliente-nav ul {
                flex-direction: column;
            }
            
            .portal-cliente-nav a {
                border-bottom: 1px solid #e2e8f0;
                border-left: 3px solid transparent;
            }
            
            .portal-cliente-nav a.active {
                border-bottom-color: #e2e8f0;
                border-left-color: #10b981;
            }
            
            .portal-cliente-content {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="portal-cliente-header">
        <div class="logo">
            <img src="LOGOBRANCA.png" alt="INLAUDO">
            <span>Portal do Cliente</span>
        </div>
        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($cliente['nome']); ?></span>
            <a href="logout.php" class="logout-btn">Sair</a>
        </div>
    </div>
    
    <nav class="portal-cliente-nav">
        <ul>
            <li><a href="portal_cliente.php" class="<?php echo $paginaAtual == 'portal_cliente.php' ? 'active' : ''; ?>">Início</a></li>
            <li><a href="cliente_contratos.php" class="<?php echo $paginaAtual == 'cliente_contratos.php' ? 'active' : ''; ?>">Meus Contratos</a></li>
            <li><a href="cliente_financeiro.php" class="<?php echo $paginaAtual == 'cliente_financeiro.php' ? 'active' : ''; ?>">Meu Financeiro</a></li>
            <li><a href="cliente_helpdesk.php" class="<?php echo $paginaAtual == 'cliente_helpdesk.php' ? 'active' : ''; ?>">Helpdesk</a></li>
            <li><a href="cliente_dados.php" class="<?php echo $paginaAtual == 'cliente_dados.php' ? 'active' : ''; ?>">Meus Dados</a></li>
        </ul>
    </nav>
    
    <div class="portal-cliente-content">
