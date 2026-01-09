<?php
/**
 * Barra de Navegação - ERP INLAUDO
 * 
 * Menu principal com navegação entre módulos
 */

// Obter página atual
$current_page = basename($_SERVER['REQUEST_URI'], '.php');
$current_page = explode('?', $current_page)[0];
if (empty($current_page) || $current_page === '/') {
    $current_page = 'dashboard';
}

// Definir menus
$menus = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => '📊',
        'url' => BASE_URL . '/dashboard'
    ],
    'clientes' => [
        'label' => 'Clientes',
        'icon' => '👥',
        'url' => BASE_URL . '/clientes',
        'submenu' => [
            ['label' => 'Listar Clientes', 'url' => BASE_URL . '/clientes'],
            ['label' => 'Novo Cliente', 'url' => BASE_URL . '/clientes/novo'],
            ['label' => 'Relatório', 'url' => BASE_URL . '/clientes/relatorio']
        ]
    ],
    'contas' => [
        'label' => 'Contas',
        'icon' => '💰',
        'url' => BASE_URL . '/contas-receber',
        'submenu' => [
            ['label' => 'Contas a Receber', 'url' => BASE_URL . '/contas-receber'],
            ['label' => 'Contas a Pagar', 'url' => BASE_URL . '/contas-pagar'],
            ['label' => 'Alertas de Vencimento', 'url' => BASE_URL . '/alertas']
        ]
    ],
    'integracao' => [
        'label' => 'Integração',
        'icon' => '🔌',
        'url' => '#',
        'submenu' => [
            ['label' => 'CORA', 'url' => BASE_URL . '/integracao/cora'],
            ['label' => 'Mercado Pago', 'url' => BASE_URL . '/integracao/mercadopago'],
            ['label' => 'Stripe', 'url' => BASE_URL . '/integracao/stripe'],
            ['label' => 'Logs de Integração', 'url' => BASE_URL . '/integracao/logs']
        ]
    ],
    'configuracao' => [
        'label' => 'Configuração',
        'icon' => '⚙️',
        'url' => '#',
        'submenu' => [
            ['label' => 'E-mail Config', 'url' => BASE_URL . '/configuracao/email'],
            ['label' => 'Templates de E-mail', 'url' => BASE_URL . '/configuracao/templates'],
            ['label' => 'Histórico de E-mails', 'url' => BASE_URL . '/configuracao/historico-email'],
            ['label' => 'Alertas Programados', 'url' => BASE_URL . '/configuracao/alertas-programados'],
            ['label' => 'Usuários', 'url' => BASE_URL . '/configuracao/usuarios'],
            ['label' => 'Preferências', 'url' => BASE_URL . '/configuracao/preferencias']
        ]
    ],
    'relatorios' => [
        'label' => 'Relatórios',
        'icon' => '📈',
        'url' => BASE_URL . '/relatorios',
        'submenu' => [
            ['label' => 'Faturamento', 'url' => BASE_URL . '/relatorios/faturamento'],
            ['label' => 'Contas Vencidas', 'url' => BASE_URL . '/relatorios/vencidas'],
            ['label' => 'Clientes', 'url' => BASE_URL . '/relatorios/clientes']
        ]
    ]
];
?>

<nav class="navbar">
    <div class="navbar-container">
        <!-- Logo -->
        <div class="navbar-brand">
            <a href="<?= BASE_URL ?>/dashboard" class="navbar-logo">
                <span class="logo-icon">📊</span>
                <span class="logo-text">ERP INLAUDO</span>
            </a>
        </div>

        <!-- Menu Principal -->
        <ul class="navbar-menu">
            <?php foreach ($menus as $key => $menu): ?>
                <li class="navbar-item <?= isset($menu['submenu']) ? 'has-submenu' : '' ?>">
                    <a href="<?= $menu['url'] ?>" class="navbar-link <?= strpos($current_page, $key) !== false ? 'active' : '' ?>">
                        <span class="menu-icon"><?= $menu['icon'] ?></span>
                        <span class="menu-label"><?= $menu['label'] ?></span>
                        <?php if (isset($menu['submenu'])): ?>
                            <span class="submenu-arrow">▼</span>
                        <?php endif; ?>
                    </a>

                    <!-- Submenu -->
                    <?php if (isset($menu['submenu'])): ?>
                        <ul class="submenu">
                            <?php foreach ($menu['submenu'] as $subitem): ?>
                                <li class="submenu-item">
                                    <a href="<?= $subitem['url'] ?>" class="submenu-link">
                                        <?= $subitem['label'] ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Usuário e Logout -->
        <div class="navbar-user">
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário') ?></span>
                <span class="user-role"><?= htmlspecialchars($_SESSION['usuario_tipo'] ?? 'Admin') ?></span>
            </div>
            <a href="<?= BASE_URL ?>/logout" class="logout-btn" title="Sair">
                🚪
            </a>
        </div>
    </div>
</nav>

<!-- Overlay para mobile -->
<div class="navbar-overlay"></div>

<style>
/* Navbar Styles */
.navbar {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 0;
    z-index: 100;
}

.navbar-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 70px;
}

/* Logo */
.navbar-brand {
    flex-shrink: 0;
}

.navbar-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: white;
    font-weight: 700;
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.navbar-logo:hover {
    opacity: 0.8;
}

.logo-icon {
    font-size: 1.5rem;
}

/* Menu Principal */
.navbar-menu {
    display: flex;
    list-style: none;
    gap: 5px;
    flex: 1;
    margin: 0 30px;
    padding: 0;
}

.navbar-item {
    position: relative;
}

.navbar-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    border-radius: 4px;
    transition: all 0.3s ease;
    white-space: nowrap;
    font-size: 0.95rem;
}

.navbar-link:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
}

.navbar-link.active {
    background-color: #3498db;
    color: white;
}

.menu-icon {
    font-size: 1.1rem;
}

.submenu-arrow {
    font-size: 0.7rem;
    transition: transform 0.3s ease;
}

.navbar-item.has-submenu:hover .submenu-arrow {
    transform: rotate(180deg);
}

/* Submenu */
.submenu {
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    min-width: 220px;
    list-style: none;
    padding: 10px 0;
    margin: 5px 0 0 0;
    border-radius: 4px;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    z-index: 200;
}

.navbar-item:hover .submenu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.submenu-item {
    padding: 0;
}

.submenu-link {
    display: block;
    padding: 12px 20px;
    color: #2c3e50;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.submenu-link:hover {
    background-color: #ecf0f1;
    color: #3498db;
    padding-left: 25px;
}

/* Usuário */
.navbar-user {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-shrink: 0;
}

.user-info {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    color: white;
}

.user-name {
    font-weight: 600;
    font-size: 0.9rem;
}

.user-role {
    font-size: 0.75rem;
    opacity: 0.8;
}

.logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 1.2rem;
}

.logout-btn:hover {
    background-color: #e74c3c;
    transform: scale(1.1);
}

/* Responsivo */
@media (max-width: 768px) {
    .navbar-container {
        flex-wrap: wrap;
        height: auto;
        padding: 10px 15px;
    }

    .navbar-menu {
        order: 3;
        width: 100%;
        margin: 10px 0 0 0;
        flex-direction: column;
        gap: 0;
    }

    .navbar-item {
        width: 100%;
    }

    .navbar-link {
        width: 100%;
        padding: 12px 15px;
    }

    .submenu {
        position: static;
        opacity: 0;
        visibility: hidden;
        max-height: 0;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .navbar-item:hover .submenu,
    .navbar-item.active .submenu {
        opacity: 1;
        visibility: visible;
        max-height: 500px;
    }

    .user-info {
        display: none;
    }
}
</style>
