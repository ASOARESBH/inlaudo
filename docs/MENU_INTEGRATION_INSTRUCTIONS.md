# Instruções de Integração no Menu de Integração

## Adicionar Asaas ao Menu de Integração Existente

### 1. Localizar Arquivo de Menu

Encontre o arquivo que gerencia o menu de integrações (geralmente `integracoes.php` ou similar).

### 2. Adicionar Item de Menu

No arquivo de menu, adicione um novo item para Asaas:

```php
// Exemplo em integracoes.php
<div class="integration-item">
    <div class="integration-header">
        <h3>🏦 Asaas</h3>
        <span class="badge <?php echo $asaasConfig['ativo'] ? 'active' : 'inactive'; ?>">
            <?php echo $asaasConfig['ativo'] ? 'Ativa' : 'Inativa'; ?>
        </span>
    </div>
    
    <p class="integration-description">
        Integração com API v3 do Asaas para cobranças via PIX e Boleto.
        Receba pagamentos de seus clientes de forma segura e rápida.
    </p>
    
    <div class="integration-features">
        <ul>
            <li>✓ Criação automática de clientes</li>
            <li>✓ Cobranças via PIX (QR Code dinâmico)</li>
            <li>✓ Cobranças via Boleto</li>
            <li>✓ Webhooks para notificações</li>
            <li>✓ Auditoria completa</li>
        </ul>
    </div>
    
    <a href="integracao_asaas_config.php" class="btn btn-primary">
        ⚙️ Configurar Asaas
    </a>
</div>
```

### 3. Carregar Configuração Asaas

No topo do arquivo de menu, adicione:

```php
<?php
// Carregar configuração Asaas
$db = Database::getInstance();
$sql = "SELECT * FROM integracao_asaas LIMIT 1";
$asaasConfig = $db->fetchOne($sql) ?? ['ativo' => 0];
?>
```

### 4. Adicionar Estilos CSS

Se necessário, adicione estilos para o item de menu:

```css
.integration-item {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.integration-item:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.integration-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.integration-header h3 {
    margin: 0;
    color: #333;
    font-size: 18px;
}

.badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge.active {
    background: #d4edda;
    color: #155724;
}

.badge.inactive {
    background: #f8d7da;
    color: #721c24;
}

.integration-description {
    color: #666;
    margin: 10px 0;
    font-size: 14px;
    line-height: 1.5;
}

.integration-features {
    margin: 15px 0;
}

.integration-features ul {
    list-style: none;
    padding: 0;
}

.integration-features li {
    padding: 5px 0;
    color: #555;
    font-size: 13px;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
}
```

### 5. Estrutura de Pasta Recomendada

```
projeto/
├── src/
│   ├── controllers/
│   │   └── AsaasController.php
│   ├── models/
│   │   └── AsaasModel.php
│   └── services/
│       └── AsaasService.php
├── api_asaas_routes.php
├── webhook_asaas.php
├── integracao_asaas_config.php
├── integracoes.php (menu principal)
└── logs/
    └── webhook_asaas_YYYY-MM-DD.log
```

### 6. Configurar .htaccess (Apache)

Se usar Apache, adicione ao `.htaccess`:

```apache
# Rotas da API Asaas
RewriteRule ^api/asaas/(.*)$ api_asaas_routes.php [QSA,L]

# Webhook Asaas
RewriteRule ^webhook/asaas$ webhook_asaas.php [QSA,L]
```

### 7. Configurar nginx

Se usar nginx, adicione ao `nginx.conf`:

```nginx
location ~ ^/api/asaas/ {
    rewrite ^/api/asaas/(.*)$ /api_asaas_routes.php last;
}

location ~ ^/webhook/asaas$ {
    rewrite ^/webhook/asaas$ /webhook_asaas.php last;
}
```

### 8. Adicionar Link no Menu Principal

Se houver um menu principal, adicione link para integração Asaas:

```php
<li>
    <a href="integracoes.php?tab=asaas">
        <i class="icon-asaas"></i>
        Integração Asaas
    </a>
</li>
```

### 9. Adicionar Notificação de Status

Adicione verificação de status no dashboard:

```php
<?php
// Verificar se Asaas está configurado
$sql = "SELECT ativo FROM integracao_asaas WHERE ativo = 1 LIMIT 1";
$asaasAtivo = $db->fetchOne($sql);

if (!$asaasAtivo) {
    echo '<div class="alert alert-warning">';
    echo '⚠️ Asaas não está configurado. ';
    echo '<a href="integracao_asaas_config.php">Configurar agora</a>';
    echo '</div>';
}
?>
```

### 10. Adicionar Logs de Integração

Crie página para visualizar logs:

```php
// logs_asaas.php
<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once 'vendor/autoload.php';

use App\Core\Database;

$db = Database::getInstance();

// Obter logs
$sql = "
    SELECT * FROM asaas_logs
    ORDER BY data_criacao DESC
    LIMIT 100
";
$logs = $db->fetchAll($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Logs Asaas</title>
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
        .sucesso { color: green; }
        .erro { color: red; }
        .pendente { color: orange; }
    </style>
</head>
<body>
    <h1>Logs de Integração Asaas</h1>
    
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Operação</th>
                <th>Status</th>
                <th>Mensagem</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?php echo $log['data_criacao']; ?></td>
                <td><?php echo htmlspecialchars($log['operacao']); ?></td>
                <td class="<?php echo $log['status']; ?>">
                    <?php echo ucfirst($log['status']); ?>
                </td>
                <td><?php echo htmlspecialchars($log['mensagem_erro'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
```

---

## Checklist de Implementação

- [ ] Copiar arquivos para projeto
- [ ] Executar script SQL
- [ ] Configurar credenciais Asaas
- [ ] Testar endpoints da API
- [ ] Configurar webhook no Asaas
- [ ] Testar webhook
- [ ] Adicionar item ao menu de integração
- [ ] Configurar .htaccess/nginx
- [ ] Criar página de logs
- [ ] Testar fluxo completo (cliente → cobrança → webhook)
- [ ] Documentar para equipe

---

## Suporte

Para dúvidas sobre integração:
1. Consulte `ASAAS_IMPLEMENTATION_GUIDE.md`
2. Verifique logs em `logs/webhook_asaas_*.log`
3. Acesse painel do Asaas para verificar status de cobranças
