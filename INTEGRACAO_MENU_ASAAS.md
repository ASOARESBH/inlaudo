# 📋 Integração do Menu Asaas no Sistema

## 🎯 Objetivo

Adicionar o menu de Integrações (Asaas) ao menu principal do seu ERP.

---

## 📁 Arquivos Adicionados

| Arquivo | Descrição |
|---------|-----------|
| `menu_integracoes_asaas.php` | Menu visual de integrações |
| `integracao_asaas_config.php` | Configuração do Asaas |
| `logs_asaas_viewer.php` | Dashboard de logs |
| `webhook_asaas.php` | Webhook para eventos |
| `api_asaas_routes.php` | Roteador de API |
| `src/services/AsaasService.php` | Serviço Asaas |
| `src/models/AsaasModel.php` | Modelo de dados |
| `src/controllers/AsaasController.php` | Controller |

---

## 🔧 Como Integrar no Menu Existente

### Opção 1: Adicionar Link no Menu Principal

Localize o arquivo que contém o menu principal (geralmente `menu.php`, `navbar.php` ou `header.php`).

Adicione este código no local apropriado:

```php
<!-- Menu Integrações -->
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="integracoesMenu" role="button" data-bs-toggle="dropdown">
        🔌 Integrações
    </a>
    <ul class="dropdown-menu" aria-labelledby="integracoesMenu">
        <li>
            <a class="dropdown-item" href="menu_integracoes_asaas.php">
                📊 Gerenciar Integrações
            </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item" href="integracao_asaas_config.php">
                🏦 Configurar Asaas
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="logs_asaas_viewer.php">
                📋 Logs Asaas
            </a>
        </li>
    </ul>
</li>
```

### Opção 2: Adicionar no Menu de Administração

Se você tem um menu de administração, adicione:

```html
<a href="menu_integracoes_asaas.php" class="admin-menu-item">
    <span class="icon">🔌</span>
    <span class="label">Integrações</span>
</a>
```

### Opção 3: Adicionar no Dashboard

Se quiser adicionar um card no dashboard:

```php
<div class="dashboard-card">
    <h3>🔌 Integrações</h3>
    <p>Gerencie suas integrações de pagamento</p>
    <a href="menu_integracoes_asaas.php" class="btn btn-primary">
        Acessar
    </a>
</div>
```

---

## 📍 Locais Comuns para Adicionar o Menu

### Bootstrap (navbar)
```html
<!-- Adicionar em navbar.php ou header.php -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <!-- ... outros itens ... -->
        
        <!-- Adicionar aqui -->
        <li class="nav-item">
            <a class="nav-link" href="menu_integracoes_asaas.php">Integrações</a>
        </li>
    </div>
</nav>
```

### Sidebar (menu lateral)
```html
<!-- Adicionar em sidebar.php -->
<div class="sidebar">
    <!-- ... outros itens ... -->
    
    <!-- Adicionar aqui -->
    <a href="menu_integracoes_asaas.php" class="sidebar-item">
        <i class="icon-plug"></i>
        <span>Integrações</span>
    </a>
</div>
```

### Menu Admin
```php
<!-- Adicionar em admin_menu.php -->
$menu_items = [
    // ... outros itens ...
    [
        'label' => 'Integrações',
        'url' => 'menu_integracoes_asaas.php',
        'icon' => '🔌',
        'permission' => 'admin'
    ]
];
```

---

## 🚀 Acessar o Menu

Após adicionar o link, você pode acessar:

```
http://seu-dominio.com/menu_integracoes_asaas.php
```

---

## 🎨 Personalização

### Mudar Cores

Edite o arquivo `menu_integracoes_asaas.php` e altere:

```css
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
```

Para suas cores preferidas.

### Mudar Ícones

Altere os emojis nos cards:

```php
<div class="integracao-icon">🏦</div>  <!-- Alterar este emoji -->
```

### Adicionar Mais Integrações

Copie o bloco de um card e modifique:

```php
<div class="integracao-card">
    <div class="integracao-icon">🆕</div>
    <h3>Minha Integração</h3>
    <!-- ... resto do conteúdo ... -->
</div>
```

---

## ✅ Checklist de Integração

- [ ] Arquivo `menu_integracoes_asaas.php` copiado
- [ ] Link adicionado ao menu principal
- [ ] Arquivo `integracao_asaas_config.php` acessível
- [ ] Arquivo `logs_asaas_viewer.php` acessível
- [ ] Arquivo `webhook_asaas.php` acessível
- [ ] Estrutura `src/` copiada
- [ ] Banco de dados criado (SQL executado)
- [ ] Permissões de arquivo verificadas
- [ ] Menu testado e funcionando
- [ ] Asaas configurado

---

## 🔗 Links Úteis

- **Menu Principal**: `menu_integracoes_asaas.php`
- **Configuração Asaas**: `integracao_asaas_config.php`
- **Logs**: `logs_asaas_viewer.php`
- **Documentação**: `docs/README_ASAAS.md`

---

## 📞 Suporte

Se tiver dúvidas:

1. Consulte a documentação em `docs/`
2. Verifique os logs em `logs_asaas_viewer.php`
3. Acesse o painel Asaas em [asaas.com](https://asaas.com)

---

**Status**: ✅ Pronto para Integração
