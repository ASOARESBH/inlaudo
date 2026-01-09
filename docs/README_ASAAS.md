# 🏦 Integração Asaas v3 - ERP Sistema Inlaudo

## 📌 Visão Geral

Implementação completa e nativa da integração com a **API v3 do Asaas** para seu ERP, permitindo:

- ✅ **Criação automática de clientes** no Asaas
- ✅ **Geração de cobranças** via PIX (QR Code dinâmico) e Boleto
- ✅ **Webhooks** para receber notificações de pagamento
- ✅ **Atualização automática** de status de contas a receber
- ✅ **Auditoria completa** com logs detalhados
- ✅ **Interface web** para configuração
- ✅ **Tratamento robusto** de erros e exceções

---

## 📁 Estrutura de Arquivos

```
projeto/
├── src/
│   ├── services/
│   │   └── AsaasService.php          # Classe principal de integração
│   ├── models/
│   │   └── AsaasModel.php            # Modelo de dados
│   └── controllers/
│       └── AsaasController.php       # Controller com endpoints
├── api_asaas_routes.php              # Roteador de requisições
├── webhook_asaas.php                 # Webhook para eventos
├── integracao_asaas_config.php       # Interface de configuração
├── logs_asaas_viewer.php             # Dashboard de logs
├── logs/
│   └── webhook_asaas_*.log           # Logs de webhook
└── [Documentação]
    ├── README_ASAAS.md               # Este arquivo
    ├── ASAAS_IMPLEMENTATION_GUIDE.md # Guia técnico completo
    ├── ASAAS_USAGE_EXAMPLES.md       # Exemplos práticos
    ├── ASAAS_TESTING_GUIDE.md        # Guia de testes
    ├── MENU_INTEGRATION_INSTRUCTIONS.md # Como integrar no menu
    └── asaas_database_setup.md       # Script SQL
```

---

## 🚀 Início Rápido

### 1. Instalação

```bash
# 1. Copiar arquivos para seu projeto
cp -r src/services/AsaasService.php seu-projeto/src/services/
cp -r src/models/AsaasModel.php seu-projeto/src/models/
cp -r src/controllers/AsaasController.php seu-projeto/src/controllers/

# 2. Copiar arquivos principais
cp api_asaas_routes.php seu-projeto/
cp webhook_asaas.php seu-projeto/
cp integracao_asaas_config.php seu-projeto/
cp logs_asaas_viewer.php seu-projeto/
```

### 2. Banco de Dados

```bash
# Executar script SQL
mysql -u usuario -p banco < asaas_database_setup.md
```

### 3. Configuração

1. Acesse: `http://seu-dominio.com/integracao_asaas_config.php`
2. Preencha com suas credenciais do Asaas
3. Configure webhook no painel Asaas
4. Ative a integração

### 4. Teste

```bash
# Criar cliente
curl -X POST http://localhost/api/asaas/customers \
  -H "Content-Type: application/json" \
  -d '{"cliente_id": 1, "cpf_cnpj": "12345678901234", "nome": "Teste"}'

# Criar cobrança
curl -X POST http://localhost/api/asaas/payments \
  -H "Content-Type: application/json" \
  -d '{"conta_receber_id": 1, "tipo_cobranca": "PIX", "valor": 100, "data_vencimento": "2025-02-28"}'
```

---

## 📚 Documentação

| Documento | Descrição |
|-----------|-----------|
| **ASAAS_IMPLEMENTATION_GUIDE.md** | Guia técnico completo com todas as tabelas, endpoints e configurações |
| **ASAAS_USAGE_EXAMPLES.md** | Exemplos práticos em JavaScript, PHP e casos de uso reais |
| **ASAAS_TESTING_GUIDE.md** | Guia completo de testes com checklist e validações |
| **MENU_INTEGRATION_INSTRUCTIONS.md** | Como integrar Asaas no menu de integração existente |
| **asaas_database_setup.md** | Script SQL para criar tabelas necessárias |

---

## 🔌 Endpoints da API

### Criar/Buscar Cliente
```
POST /api/asaas/customers
```

### Criar Cobrança
```
POST /api/asaas/payments
```

### Obter Status de Cobrança
```
GET /api/asaas/payments/{paymentId}
```

---

## 🔔 Webhook

**URL**: `https://seu-dominio.com/webhook_asaas.php`

**Eventos Suportados**:
- `PAYMENT_RECEIVED` - Pagamento recebido
- `PAYMENT_CONFIRMED` - Pagamento confirmado
- `PAYMENT_PENDING` - Pagamento pendente
- `PAYMENT_OVERDUE` - Pagamento vencido
- `PAYMENT_DELETED` - Pagamento deletado

---

## 🗄️ Tabelas do Banco de Dados

| Tabela | Descrição |
|--------|-----------|
| `integracao_asaas` | Configuração da integração |
| `asaas_clientes` | Mapeamento de clientes |
| `asaas_pagamentos` | Mapeamento de cobranças |
| `asaas_logs` | Logs de operações |
| `asaas_webhooks` | Registro de eventos |

---

## ⚙️ Configuração

### Variáveis de Ambiente

```php
// Em config.php ou .env
ASAAS_API_KEY = $aact_hmlg_... (Sandbox) ou $aact_prod_... (Produção)
ASAAS_WEBHOOK_TOKEN = seu-token-seguro
ASAAS_WEBHOOK_URL = https://seu-dominio.com/webhook_asaas.php
ASAAS_AMBIENTE = sandbox ou production
```

### .htaccess (Apache)

```apache
RewriteRule ^api/asaas/(.*)$ api_asaas_routes.php [QSA,L]
RewriteRule ^webhook/asaas$ webhook_asaas.php [QSA,L]
```

### nginx

```nginx
location ~ ^/api/asaas/ {
    rewrite ^/api/asaas/(.*)$ /api_asaas_routes.php last;
}

location ~ ^/webhook/asaas$ {
    rewrite ^/webhook/asaas$ /webhook_asaas.php last;
}
```

---

## 🔐 Segurança

### Validação de Token
Todos os webhooks validam o token de segurança no header `asaas-access-token`

### Prevenção de SQL Injection
Uso de prepared statements em todas as queries

### Idempotência
Webhooks não processam eventos duplicados

### HTTPS Obrigatório
Em produção, sempre use HTTPS para webhook

---

## 📊 Monitoramento

### Dashboard de Logs
Acesse: `http://seu-dominio.com/logs_asaas_viewer.php`

### Verificar Logs
```sql
SELECT * FROM asaas_logs ORDER BY data_criacao DESC LIMIT 100;
SELECT * FROM asaas_webhooks ORDER BY data_recebimento DESC LIMIT 50;
```

### Arquivo de Log
```
logs/webhook_asaas_YYYY-MM-DD.log
```

---

## 🐛 Troubleshooting

### Problema: "Integração Asaas não configurada"
**Solução**: Acesse `integracao_asaas_config.php` e preencha os dados

### Problema: "Cliente não mapeado no Asaas"
**Solução**: Crie cliente primeiro usando endpoint `/api/asaas/customers`

### Problema: Webhook não recebe eventos
**Solução**: 
1. Verifique URL do webhook no painel Asaas
2. Verifique token de segurança
3. Verifique logs em `logs/webhook_asaas_*.log`
4. Teste webhook manualmente com cURL

### Problema: "Token inválido" no webhook
**Solução**: Verifique se token no header `asaas-access-token` é igual ao configurado

---

## 🧪 Testes

Consulte **ASAAS_TESTING_GUIDE.md** para:
- Testes de configuração
- Testes de endpoints
- Testes de webhook
- Testes de integração completa
- Testes de segurança

---

## 📈 Fluxo de Pagamento

```
1. Cliente criado no ERP
   ↓
2. Criar/buscar cliente no Asaas (POST /api/asaas/customers)
   ↓
3. Criar cobrança (POST /api/asaas/payments)
   ↓
4. Exibir QR Code PIX ou Boleto para cliente
   ↓
5. Cliente paga
   ↓
6. Asaas envia webhook (PAYMENT_RECEIVED)
   ↓
7. webhook_asaas.php processa evento
   ↓
8. Atualiza status em contas_receber
   ↓
9. Registra auditoria em notas_contas_receber
```

---

## 🔄 Integração no Portal do Cliente

Para integrar no portal do cliente:

1. Adicionar botão "Pagar com Asaas" em contas a pagar
2. Chamar endpoint `/api/asaas/payments`
3. Exibir QR Code ou Boleto
4. Webhook atualiza status automaticamente

Exemplo:
```javascript
// Gerar cobrança PIX
fetch('/api/asaas/payments', {
    method: 'POST',
    body: JSON.stringify({
        conta_receber_id: 123,
        tipo_cobranca: 'PIX',
        valor: 150.00,
        data_vencimento: '2025-02-28'
    })
}).then(r => r.json()).then(data => {
    // Exibir QR Code
    document.getElementById('qrcode').src = data.additional.encodedImage;
});
```

---

## 📞 Suporte

### Documentação Oficial Asaas
- [Docs Asaas](https://docs.asaas.com)
- [API Reference](https://docs.asaas.com/reference)
- [Sandbox](https://docs.asaas.com/docs/sandbox)

### Contato
- Email: suporte@asaas.com
- Chat: Disponível no painel Asaas

---

## 📝 Changelog

### v1.0.0 (Janeiro 2025)
- ✅ Implementação inicial
- ✅ Suporte a PIX e Boleto
- ✅ Webhook com validação
- ✅ Dashboard de logs
- ✅ Documentação completa

---

## 📄 Licença

Esta integração é parte do ERP Sistema Inlaudo.

---

## 👨‍💻 Desenvolvedor

**Backend Developer** | Janeiro 2025

---

## ✅ Checklist de Implementação

- [x] Classe de serviço Asaas
- [x] Endpoints de API
- [x] Webhook com validação
- [x] Dashboard de logs
- [x] Interface de configuração
- [x] Documentação técnica
- [x] Exemplos de uso
- [x] Guia de testes
- [x] Tratamento de erros
- [x] Sistema de auditoria

---

**Pronto para usar! 🚀**

Para começar, acesse: `http://seu-dominio.com/integracao_asaas_config.php`
