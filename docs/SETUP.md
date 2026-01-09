# Guia de Setup - ERP INLAUDO v2.0.0

## 📋 Pré-requisitos

- PHP 7.4+
- MySQL 5.7+ ou MariaDB 10.3+
- Composer (opcional, para gerenciar dependências)
- Apache ou Nginx com mod_rewrite ativado

---

## 🚀 Instalação Rápida

### 1. Clonar Repositório

```bash
git clone https://github.com/ASOARESBH/erpinlaudo.git
cd erpinlaudo_estruturado
```

### 2. Configurar Variáveis de Ambiente

```bash
cp .env.example .env
```

Editar `.env` com suas configurações:

```env
APP_ENV=production
APP_DEBUG=false

DB_HOST=localhost
DB_PORT=3306
DB_NAME=erpinlaudo
DB_USER=seu_usuario
DB_PASS=sua_senha

BASE_URL=http://seu-dominio.com

CORA_API_KEY=sua_chave_cora
CORA_ACCOUNT_ID=seu_account_id
```

### 3. Criar Banco de Dados

```bash
mysql -u seu_usuario -p -e "CREATE DATABASE erpinlaudo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 4. Executar Migrations

```bash
# Criar tabelas principais
mysql -u seu_usuario -p erpinlaudo < database/sql/schema.sql

# Criar tabelas de alertas
mysql -u seu_usuario -p erpinlaudo < database/sql/alertas.sql

# Criar tabelas de logs CORA
mysql -u seu_usuario -p erpinlaudo < database/sql/cora_logs.sql
```

### 5. Configurar Permissões

```bash
# Linux/Mac
chmod -R 755 storage/
chmod -R 755 public/uploads/

# Windows
# Dar permissão de escrita para storage/ e public/uploads/
```

### 6. Iniciar Servidor

```bash
# Desenvolvimento
php -S localhost:8000 -t public

# Produção (Apache/Nginx)
# Configurar DocumentRoot para public/
```

Acesse: http://localhost:8000

---

## 🔧 Configuração Avançada

### Apache (.htaccess)

Criar arquivo `.htaccess` em `public/`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
</IfModule>
```

### Nginx (nginx.conf)

```nginx
server {
    listen 80;
    server_name seu-dominio.com;
    
    root /var/www/erpinlaudo/public;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### SSL/HTTPS

```nginx
server {
    listen 443 ssl http2;
    server_name seu-dominio.com;
    
    ssl_certificate /etc/ssl/certs/seu-certificado.crt;
    ssl_certificate_key /etc/ssl/private/sua-chave.key;
    
    # ... resto da configuração
}
```

---

## 📊 Estrutura de Banco de Dados

### Tabelas Principais

#### clientes
```sql
CREATE TABLE clientes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    cnpj_cpf VARCHAR(20) UNIQUE,
    email VARCHAR(255),
    telefone VARCHAR(20),
    tipo_cliente ENUM('pf', 'pj'),
    ativo BOOLEAN DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### contas_receber
```sql
CREATE TABLE contas_receber (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id INT NOT NULL,
    descricao VARCHAR(255),
    valor DECIMAL(10, 2),
    data_vencimento DATE,
    status ENUM('pendente', 'pago', 'vencido', 'cancelado') DEFAULT 'pendente',
    forma_pagamento VARCHAR(50),
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);
```

#### alertas_contas_vencidas
```sql
CREATE TABLE alertas_contas_vencidas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conta_receber_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo_alerta ENUM('vencido', 'vencendo_hoje', 'vencendo_amanha', 'vencendo_semana'),
    titulo VARCHAR(255),
    descricao TEXT,
    valor DECIMAL(10, 2),
    dias_vencido INT,
    visualizado BOOLEAN DEFAULT 0,
    acao_tomada VARCHAR(50),
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_visualizacao TIMESTAMP NULL,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);
```

---

## 🔐 Segurança

### Configurações Recomendadas

#### php.ini
```ini
; Segurança
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source
display_errors = Off
error_reporting = E_ALL
log_errors = On
error_log = /var/log/php_errors.log

; Sessão
session.cookie_httponly = On
session.cookie_secure = On
session.cookie_samesite = Lax
session.use_strict_mode = On

; Upload
upload_max_filesize = 10M
post_max_size = 10M
```

### Headers de Segurança

Adicionar ao `.htaccess` ou `nginx.conf`:

```apache
# .htaccess
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
Header set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

```nginx
# nginx.conf
add_header X-Content-Type-Options "nosniff";
add_header X-Frame-Options "SAMEORIGIN";
add_header X-XSS-Protection "1; mode=block";
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";
```

---

## 📧 Configuração de Email

### SMTP

Editar `.env`:

```env
MAIL_DRIVER=smtp
MAIL_HOST=smtp.seu-provedor.com
MAIL_PORT=587
MAIL_USERNAME=seu-email@seu-dominio.com
MAIL_PASSWORD=sua-senha
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@seu-dominio.com
MAIL_FROM_NAME="ERP INLAUDO"
```

### Mailtrap (Testes)

```env
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=seu-usuario-mailtrap
MAIL_PASSWORD=sua-senha-mailtrap
MAIL_ENCRYPTION=tls
```

---

## 🔌 Integração CORA

### Obter Credenciais

1. Acessar https://app.cora.com.br/
2. Fazer login
3. Ir para Configurações → Integrações
4. Copiar API Key e Account ID

### Configurar no .env

```env
CORA_API_KEY=sua_chave_api
CORA_ACCOUNT_ID=seu_account_id
CORA_WEBHOOK_SECRET=seu_webhook_secret
```

### Registrar Webhook

1. Em Configurações → Webhooks
2. Adicionar URL: `https://seu-dominio.com/webhook/cora`
3. Selecionar eventos: `boleto.pago`, `boleto.vencido`

---

## 🧪 Testes

### Teste de Conexão

```bash
# Verificar conexão com banco
php -r "
require_once 'src/core/Bootstrap.php';
use App\Core\Database;
\App\Core\Bootstrap::init();
\$db = Database::getInstance();
echo 'Conexão OK!';
"
```

### Teste de Alertas

```bash
# Gerar alertas
php -r "
require_once 'src/core/Bootstrap.php';
use App\Services\AlertaService;
\$alertas = new AlertaService();
\$alertas->gerarAlertas();
echo 'Alertas gerados!';
"
```

---

## 📈 Performance

### Otimizações

1. **Cache**
   - Ativar cache de opcode (OPcache)
   - Configurar cache de aplicação

2. **Banco de Dados**
   - Criar índices
   - Usar prepared statements
   - Implementar paginação

3. **Assets**
   - Minificar CSS/JS
   - Comprimir imagens
   - Usar CDN

### Monitoramento

```bash
# Ver logs de erro
tail -f storage/logs/error.log

# Ver logs de aplicação
tail -f storage/logs/application.log

# Ver logs de banco de dados
tail -f storage/logs/database.log
```

---

## 🐛 Troubleshooting

### Erro: "Arquivo de configuração não encontrado"

```bash
# Verificar se config/Config.php existe
ls -la config/Config.php

# Se não existir, copiar do exemplo
cp config/Config.php.example config/Config.php
```

### Erro: "Erro ao conectar ao banco de dados"

```bash
# Verificar credenciais em .env
cat .env | grep DB_

# Testar conexão
mysql -h localhost -u seu_usuario -p seu_banco -e "SELECT 1;"
```

### Erro: "Permissão negada em storage/"

```bash
# Dar permissão de escrita
chmod -R 777 storage/
chmod -R 777 public/uploads/
```

### Erro: "Rewrite não funciona"

```bash
# Verificar se mod_rewrite está ativado
a2enmod rewrite

# Reiniciar Apache
systemctl restart apache2
```

---

## 🚀 Deploy em Produção

### Checklist

- [ ] Configurar `.env` para produção
- [ ] Definir `APP_DEBUG=false`
- [ ] Definir `APP_ENV=production`
- [ ] Executar migrations
- [ ] Configurar SSL/HTTPS
- [ ] Configurar backups automáticos
- [ ] Configurar monitoramento
- [ ] Testar funcionalidades principais
- [ ] Configurar alertas de erro
- [ ] Documentar credenciais (seguro)

### Backup Automático

```bash
#!/bin/bash
# backup.sh

BACKUP_DIR="/var/backups/erpinlaudo"
DATE=$(date +%Y%m%d_%H%M%S)

# Backup do banco
mysqldump -u usuario -p senha erpinlaudo > $BACKUP_DIR/db_$DATE.sql

# Backup de arquivos
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/erpinlaudo

# Remover backups antigos (mais de 30 dias)
find $BACKUP_DIR -type f -mtime +30 -delete
```

---

## 📞 Suporte

Para dúvidas ou problemas:

1. Consulte a documentação em `docs/`
2. Verifique os logs em `storage/logs/`
3. Revise o arquivo `ARCHITECTURE.md`

---

**Versão**: 2.0.0  
**Data**: 06/01/2026  
**Status**: ✅ Pronto para Produção
