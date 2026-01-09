# 🚀 Guia de Instalação Rápida - Asaas v3

## ⚡ Instalação em 5 Minutos

### Passo 1: Copiar Arquivos

```bash
# Copiar estrutura src/ para seu projeto
cp -r src/* seu-projeto/src/

# Copiar arquivos principais
cp api_asaas_routes.php seu-projeto/
cp webhook_asaas.php seu-projeto/
cp integracao_asaas_config.php seu-projeto/
cp logs_asaas_viewer.php seu-projeto/
```

### Passo 2: Executar Script SQL

```bash
# Opção 1: Via linha de comando
mysql -u seu_usuario -p seu_banco < sql/asaas_integration.sql

# Opção 2: Via phpMyAdmin
# 1. Acesse phpMyAdmin
# 2. Selecione seu banco de dados
# 3. Vá para "Importar"
# 4. Selecione arquivo sql/asaas_integration.sql
# 5. Clique em "Executar"
```

### Passo 3: Configurar Autoloader

Se usar Composer, adicione ao `composer.json`:

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

Depois execute:
```bash
composer dump-autoload
```

### Passo 4: Configurar Asaas

1. Acesse: `http://seu-dominio.com/integracao_asaas_config.php`
2. Preencha com suas credenciais do Asaas
3. Configure webhook no painel Asaas
4. Ative a integração

### Passo 5: Testar

```bash
# Teste rápido
curl -X POST http://localhost/api/asaas/customers \
  -H "Content-Type: application/json" \
  -d '{
    "cliente_id": 1,
    "cpf_cnpj": "12345678901234",
    "nome": "Teste"
  }'
```

---

## 📁 Estrutura de Pastas

```
seu-projeto/
├── src/
│   ├── services/
│   │   └── AsaasService.php
│   ├── models/
│   │   └── AsaasModel.php
│   └── controllers/
│       └── AsaasController.php
├── api_asaas_routes.php
├── webhook_asaas.php
├── integracao_asaas_config.php
├── logs_asaas_viewer.php
└── logs/
    └── (arquivos de log serão criados aqui)
```

---

## 🔧 Configuração do Servidor

### Apache (.htaccess)

Adicione ao seu `.htaccess`:

```apache
# Rotas da API Asaas
RewriteRule ^api/asaas/(.*)$ api_asaas_routes.php [QSA,L]

# Webhook Asaas
RewriteRule ^webhook/asaas$ webhook_asaas.php [QSA,L]
```

### Nginx

Adicione ao seu `nginx.conf`:

```nginx
location ~ ^/api/asaas/ {
    rewrite ^/api/asaas/(.*)$ /api_asaas_routes.php last;
}

location ~ ^/webhook/asaas$ {
    rewrite ^/webhook/asaas$ /webhook_asaas.php last;
}
```

---

## 🔐 Obter Credenciais Asaas

1. Acesse [asaas.com](https://asaas.com)
2. Crie uma conta (ou use existente)
3. Vá para **Configurações > Integrações > API**
4. Copie sua **API Key**:
   - Sandbox: `$aact_hmlg_...`
   - Produção: `$aact_prod_...`

---

## ✅ Checklist de Instalação

- [ ] Arquivos copiados
- [ ] Script SQL executado
- [ ] Autoloader configurado
- [ ] .htaccess/nginx configurado
- [ ] Credenciais Asaas obtidas
- [ ] Página de configuração acessível
- [ ] Webhook configurado no Asaas
- [ ] Teste de cliente criado com sucesso
- [ ] Teste de cobrança criada com sucesso
- [ ] Logs visíveis no dashboard

---

## 🆘 Troubleshooting

### Erro: "Classe não encontrada"
- Verifique se autoloader está configurado
- Verifique se namespace está correto
- Execute `composer dump-autoload`

### Erro: "Integração Asaas não configurada"
- Acesse `integracao_asaas_config.php`
- Preencha os dados e salve
- Verifique se tabela `integracao_asaas` foi criada

### Erro: "API Key inválida"
- Verifique se API Key está correta
- Verifique se ambiente está correto (Sandbox/Produção)
- Teste API Key no painel Asaas

### Webhook não recebe eventos
- Verifique URL do webhook no painel Asaas
- Verifique se URL é acessível (use HTTPS em produção)
- Verifique logs em `logs/webhook_asaas_*.log`

---

## 📚 Próximos Passos

1. Leia `docs/README_ASAAS.md`
2. Consulte `docs/ASAAS_IMPLEMENTATION_GUIDE.md`
3. Veja exemplos em `docs/ASAAS_USAGE_EXAMPLES.md`
4. Execute testes em `docs/ASAAS_TESTING_GUIDE.md`

---

## 📞 Suporte

- Documentação: `/docs/`
- Dashboard de logs: `http://seu-dominio.com/logs_asaas_viewer.php`
- Painel Asaas: [asaas.com](https://asaas.com)

---

**Pronto! 🎉 Sua integração Asaas está instalada e configurada.**
