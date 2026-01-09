# 🚀 Guia de Instalação Completa - ERP Inlaudo com Asaas

## 📋 Pré-requisitos

- PHP 7.4+
- MariaDB 10.x+
- Apache/Nginx
- cURL habilitado

---

## 🔧 Instalação Passo a Passo

### Passo 1: Descompactar Arquivos

```bash
unzip erp_inlaudo_asaas_completo.zip
cd asaas_app_final
```

### Passo 2: Copiar para Servidor Web

```bash
# Copiar para raiz do servidor (ex: /var/www/html)
cp -r . /var/www/html/seu-projeto/

# Ou para um subdiretório
cp -r . /var/www/html/erp/
```

### Passo 3: Configurar Permissões

```bash
# Dar permissão de escrita para logs
chmod -R 777 logs/
chmod -R 777 uploads/
chmod -R 777 storage/

# Dar permissão de leitura para arquivos
chmod -R 755 src/
chmod -R 755 docs/
```

### Passo 4: Executar Script SQL

#### Opção A: Via phpMyAdmin
1. Acesse phpMyAdmin
2. Selecione banco `inlaud99_erpinlaudo`
3. Clique em **"Importar"**
4. Selecione **`sql/asaas_integration_simples.sql`**
5. Clique em **"Executar"**

#### Opção B: Via Linha de Comando
```bash
mysql -u seu_usuario -p inlaud99_erpinlaudo < sql/asaas_integration_simples.sql
```

#### Opção C: Via Heidi SQL
1. Abra Heidi SQL
2. Conecte ao servidor
3. Selecione banco `inlaud99_erpinlaudo`
4. Clique em **"Arquivo" > "Executar arquivo SQL"**
5. Selecione `sql/asaas_integration_simples.sql`

### Passo 5: Configurar .htaccess (Apache)

Verifique se o arquivo `.htaccess` está presente e contém:

```apache
RewriteEngine On
RewriteBase /seu-projeto/

# Rotas da API Asaas
RewriteRule ^api/asaas/(.*)$ api_asaas_routes.php [QSA,L]

# Webhook Asaas
RewriteRule ^webhook/asaas$ webhook_asaas.php [QSA,L]
```

### Passo 6: Configurar Nginx (se usar)

Adicione ao seu `nginx.conf`:

```nginx
location ~ ^/api/asaas/ {
    rewrite ^/api/asaas/(.*)$ /api_asaas_routes.php last;
}

location ~ ^/webhook/asaas$ {
    rewrite ^/webhook/asaas$ /webhook_asaas.php last;
}
```

### Passo 7: Configurar Asaas

1. Acesse: `http://seu-dominio.com/integracao_asaas_config.php`
2. Preencha com suas credenciais:
   - **API Key**: Obtenha em [asaas.com](https://asaas.com)
   - **Webhook Token**: Copie do painel Asaas
   - **Webhook URL**: `https://seu-dominio.com/webhook_asaas.php`
   - **Ambiente**: Selecione `sandbox` ou `production`
3. Clique em **"Salvar"**
4. Ative a integração

### Passo 8: Configurar Webhook no Asaas

1. Acesse [asaas.com](https://asaas.com)
2. Vá para **Configurações > Webhooks**
3. Adicione novo webhook:
   - **URL**: `https://seu-dominio.com/webhook_asaas.php`
   - **Eventos**: Selecione todos
4. Copie o **Token de Segurança**
5. Cole em `integracao_asaas_config.php`

### Passo 9: Testar Integração

#### Teste 1: Acessar Menu
```
http://seu-dominio.com/menu_integracoes_asaas.php
```

#### Teste 2: Criar Cliente
```bash
curl -X POST http://seu-dominio.com/api/asaas/customers \
  -H "Content-Type: application/json" \
  -d '{
    "cliente_id": 1,
    "cpf_cnpj": "12345678901234",
    "nome": "Teste"
  }'
```

#### Teste 3: Criar Cobrança
```bash
curl -X POST http://seu-dominio.com/api/asaas/payments \
  -H "Content-Type: application/json" \
  -d '{
    "cliente_id": 1,
    "conta_receber_id": 100,
    "tipo_cobranca": "PIX",
    "valor": 100.00,
    "data_vencimento": "2025-02-15"
  }'
```

#### Teste 4: Visualizar Logs
```
http://seu-dominio.com/logs_asaas_viewer.php
```

---

## 📁 Estrutura de Pastas

```
seu-projeto/
├── src/
│   ├── services/
│   │   ├── AsaasService.php
│   │   └── AlertaService.php
│   ├── models/
│   │   ├── AsaasModel.php
│   │   ├── ClienteModel.php
│   │   └── ...
│   ├── controllers/
│   │   ├── AsaasController.php
│   │   └── NotaFiscalController.php
│   ├── core/
│   ├── views/
│   └── ...
├── sql/
│   ├── asaas_integration_simples.sql
│   ├── asaas_integration_mariadb.sql
│   └── asaas_integration.sql
├── docs/
│   ├── README_ASAAS.md
│   ├── ASAAS_IMPLEMENTATION_GUIDE.md
│   ├── ASAAS_USAGE_EXAMPLES.md
│   ├── ASAAS_TESTING_GUIDE.md
│   └── ...
├── logs/
│   └── webhook_asaas_*.log
├── uploads/
├── menu_integracoes_asaas.php
├── integracao_asaas_config.php
├── logs_asaas_viewer.php
├── webhook_asaas.php
├── api_asaas_routes.php
├── INSTALACAO_COMPLETA.md
├── README_APP_COMPLETA.md
├── INTEGRACAO_MENU_ASAAS.md
└── ... (arquivos originais do ERP)
```

---

## ✅ Checklist de Instalação

- [ ] Arquivos descompactados
- [ ] Permissões configuradas
- [ ] Script SQL executado
- [ ] Tabelas criadas (verificar em phpMyAdmin)
- [ ] .htaccess/Nginx configurado
- [ ] Menu acessível
- [ ] Asaas configurado
- [ ] Webhook configurado
- [ ] Teste de cliente OK
- [ ] Teste de cobrança OK
- [ ] Logs visíveis

---

## 🆘 Troubleshooting

### Erro 404 ao acessar menu
- Verifique se arquivo `menu_integracoes_asaas.php` existe
- Verifique permissões do arquivo
- Verifique configuração do .htaccess/Nginx

### Erro: "Integração Asaas não configurada"
- Acesse `integracao_asaas_config.php`
- Preencha os dados
- Verifique se tabela `integracao_asaas` foi criada

### Erro: "API Key inválida"
- Verifique se API Key está correta
- Verifique se ambiente está correto
- Teste API Key no painel Asaas

### Erro ao executar SQL
- Verifique se banco de dados existe
- Verifique credenciais de acesso
- Verifique se MariaDB está rodando
- Tente script `asaas_integration_simples.sql`

### Webhook não recebe eventos
- Verifique URL do webhook (deve ser HTTPS)
- Verifique se URL é acessível de fora
- Consulte logs em `logs_asaas_viewer.php`
- Verifique token de segurança

---

## 📚 Documentação

- **README_APP_COMPLETA.md** - Visão geral da APP
- **INSTALACAO_COMPLETA.md** - Este arquivo
- **INTEGRACAO_MENU_ASAAS.md** - Como integrar no menu
- **docs/README_ASAAS.md** - Guia do Asaas
- **docs/ASAAS_IMPLEMENTATION_GUIDE.md** - Guia técnico
- **docs/ASAAS_USAGE_EXAMPLES.md** - Exemplos

---

## 🔐 Segurança

### Obter Credenciais Asaas

1. Acesse [asaas.com](https://asaas.com)
2. Crie uma conta
3. Vá para **Configurações > Integrações > API**
4. Copie sua **API Key**

### Usar HTTPS em Produção

```bash
# Redirecionar HTTP para HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### Proteger Arquivos Sensíveis

```apache
# Proteger config.php
<Files "config.php">
    Order allow,deny
    Deny from all
</Files>

# Proteger .htaccess
<Files ".htaccess">
    Order allow,deny
    Deny from all
</Files>
```

---

## 📞 Suporte

- **Documentação**: `/docs/`
- **Dashboard de Logs**: `logs_asaas_viewer.php`
- **Painel Asaas**: [asaas.com](https://asaas.com)
- **Documentação Asaas**: [docs.asaas.com](https://docs.asaas.com)

---

## 🎉 Pronto!

Sua APP está instalada e configurada! 🚀

Acesse:
- **Menu de Integrações**: `http://seu-dominio.com/menu_integracoes_asaas.php`
- **Configuração Asaas**: `http://seu-dominio.com/integracao_asaas_config.php`
- **Dashboard de Logs**: `http://seu-dominio.com/logs_asaas_viewer.php`

---

**Versão**: 1.0.0  
**Status**: ✅ Pronto para Usar  
**Data**: Janeiro 2025
