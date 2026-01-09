# 🚀 ERP Inlaudo - Versão Completa com Asaas

## 📦 Conteúdo

Esta é a versão **completa** do seu ERP com a integração **Asaas v3** totalmente implementada.

---

## ✨ Novos Componentes Adicionados

### 🔌 Integração Asaas

#### Arquivos Principais
- ✅ `menu_integracoes_asaas.php` - Menu visual de integrações
- ✅ `integracao_asaas_config.php` - Configuração do Asaas
- ✅ `logs_asaas_viewer.php` - Dashboard de logs
- ✅ `webhook_asaas.php` - Webhook para eventos
- ✅ `api_asaas_routes.php` - Roteador de API

#### Estrutura de Código
```
src/
├── services/
│   └── AsaasService.php       (Classe de serviço)
├── models/
│   └── AsaasModel.php         (Modelo de dados)
└── controllers/
    └── AsaasController.php    (Controller)
```

#### Banco de Dados
```
sql/
├── asaas_integration_simples.sql      (Recomendado)
├── asaas_integration_mariadb.sql      (Avançado)
└── asaas_integration.sql              (Original)
```

#### Documentação
```
docs/
├── README_ASAAS.md
├── ASAAS_IMPLEMENTATION_GUIDE.md
├── ASAAS_USAGE_EXAMPLES.md
├── ASAAS_TESTING_GUIDE.md
├── MENU_INTEGRATION_INSTRUCTIONS.md
└── ...
```

---

## 🚀 Início Rápido

### 1️⃣ Executar Script SQL

```bash
# Via linha de comando
mysql -u seu_usuario -p inlaud99_erpinlaudo < sql/asaas_integration_simples.sql

# Ou via phpMyAdmin
# 1. Vá para "Importar"
# 2. Selecione sql/asaas_integration_simples.sql
# 3. Clique em "Executar"
```

### 2️⃣ Acessar Menu de Integrações

```
http://seu-dominio.com/menu_integracoes_asaas.php
```

### 3️⃣ Configurar Asaas

1. Acesse: `http://seu-dominio.com/integracao_asaas_config.php`
2. Preencha com suas credenciais do Asaas
3. Salve a configuração
4. Ative a integração

### 4️⃣ Testar Endpoints

```bash
curl -X POST http://localhost/api/asaas/customers \
  -H "Content-Type: application/json" \
  -d '{
    "cliente_id": 1,
    "cpf_cnpj": "12345678901234",
    "nome": "Teste"
  }'
```

---

## 📊 Estrutura do Projeto

```
asaas_app_final/
├── src/
│   ├── services/AsaasService.php
│   ├── models/AsaasModel.php
│   └── controllers/AsaasController.php
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
│   └── (logs de webhook)
├── menu_integracoes_asaas.php
├── integracao_asaas_config.php
├── logs_asaas_viewer.php
├── webhook_asaas.php
├── api_asaas_routes.php
├── INTEGRACAO_MENU_ASAAS.md
├── README_APP_COMPLETA.md
└── ... (arquivos originais do ERP)
```

---

## 🔌 Endpoints da API

### Criar/Buscar Cliente
```
POST /api/asaas/customers

Body:
{
  "cliente_id": 1,
  "cpf_cnpj": "12345678901234",
  "nome": "João Silva",
  "email": "joao@example.com",
  "telefone": "11999999999"
}

Response:
{
  "success": true,
  "customer_id": "cus_12345",
  "message": "Cliente processado com sucesso"
}
```

### Criar Cobrança
```
POST /api/asaas/payments

Body:
{
  "cliente_id": 1,
  "conta_receber_id": 100,
  "tipo_cobranca": "PIX",
  "valor": 150.00,
  "data_vencimento": "2025-02-15",
  "descricao": "Fatura #100"
}

Response:
{
  "success": true,
  "payment_id": "pay_12345",
  "status": "PENDING",
  "value": 150.00,
  "additional": {
    "encodedImage": "...",
    "payload": "00020126..."
  }
}
```

### Obter Status
```
GET /api/asaas/payments/{paymentId}

Response:
{
  "success": true,
  "payment": {
    "id": "pay_12345",
    "status": "PENDING",
    "value": 150.00,
    "dueDate": "2025-02-15"
  }
}
```

---

## 🔔 Webhook

**URL**: `https://seu-dominio.com/webhook_asaas.php`

**Eventos Suportados**:
- ✅ PAYMENT_RECEIVED
- ✅ PAYMENT_CONFIRMED
- ✅ PAYMENT_PENDING
- ✅ PAYMENT_OVERDUE
- ✅ PAYMENT_DELETED

**Ações Automáticas**:
1. Valida token de segurança
2. Processa evento
3. Atualiza status em `contas_receber`
4. Registra auditoria em `notas_contas_receber`
5. Gera log para rastreamento

---

## 🗄️ Banco de Dados

### Tabelas Criadas

| Tabela | Função |
|--------|--------|
| `integracao_asaas` | Configuração da integração |
| `asaas_clientes` | Mapeamento de clientes |
| `asaas_pagamentos` | Mapeamento de cobranças |
| `asaas_logs` | Auditoria de operações |
| `asaas_webhooks` | Registro de eventos |

### Colunas Adicionadas (se tabela existir)

À tabela `contas_receber`:
- `gateway_asaas_id` - ID do pagamento no Asaas
- `status_asaas` - Status do pagamento

---

## 📚 Documentação

### Para Começar
- 📖 `README_ASAAS.md` - Visão geral
- 📖 `INTEGRACAO_MENU_ASAAS.md` - Como integrar no menu

### Guias Técnicos
- 📖 `ASAAS_IMPLEMENTATION_GUIDE.md` - Guia completo
- 📖 `ASAAS_USAGE_EXAMPLES.md` - 20+ exemplos
- 📖 `ASAAS_TESTING_GUIDE.md` - Guia de testes

---

## 🎯 Funcionalidades

### ✅ Gerenciamento de Clientes
- Buscar cliente por CPF/CNPJ
- Criar cliente automaticamente
- Mapeamento entre sistema local e Asaas
- Tratamento de duplicatas

### ✅ Geração de Cobranças
- PIX com QR Code dinâmico
- Boleto com linha digitável
- Retorno de invoiceUrl
- Retorno de nossoNumero
- Suporte a Sandbox e Produção

### ✅ Webhooks
- Validação de token de segurança
- Processamento de eventos
- Idempotência (sem duplicatas)
- Atualização automática de status
- Registro de auditoria

### ✅ Logs e Auditoria
- Logs em arquivo
- Logs em banco de dados
- Dashboard visual
- Filtros por operação, status e data
- Paginação

### ✅ Segurança
- Prepared statements
- Validação de entrada
- Transações ACID
- Tratamento robusto de erros

---

## 🔐 Configuração de Segurança

### Obter Credenciais Asaas

1. Acesse [asaas.com](https://asaas.com)
2. Crie uma conta
3. Vá para **Configurações > Integrações > API**
4. Copie sua **API Key**:
   - Sandbox: `$aact_hmlg_...`
   - Produção: `$aact_prod_...`

### Configurar Webhook

1. No painel Asaas, vá para **Webhooks**
2. Adicione URL: `https://seu-dominio.com/webhook_asaas.php`
3. Copie o **Token de Segurança**
4. Cole em `integracao_asaas_config.php`

---

## 🧪 Testes

### Teste Rápido

```bash
# 1. Criar cliente
curl -X POST http://localhost/api/asaas/customers \
  -H "Content-Type: application/json" \
  -d '{"cliente_id": 1, "cpf_cnpj": "12345678901234", "nome": "Teste"}'

# 2. Criar cobrança
curl -X POST http://localhost/api/asaas/payments \
  -H "Content-Type: application/json" \
  -d '{
    "cliente_id": 1,
    "conta_receber_id": 100,
    "tipo_cobranca": "PIX",
    "valor": 100.00,
    "data_vencimento": "2025-02-15"
  }'

# 3. Obter status
curl -X GET http://localhost/api/asaas/payments/pay_12345
```

### Dashboard de Testes

Acesse: `http://seu-dominio.com/logs_asaas_viewer.php`

---

## ✅ Checklist de Instalação

- [ ] Arquivos copiados
- [ ] Script SQL executado
- [ ] Tabelas criadas com sucesso
- [ ] Menu acessível
- [ ] Configuração Asaas preenchida
- [ ] Webhook configurado no Asaas
- [ ] Teste de cliente criado
- [ ] Teste de cobrança criada
- [ ] Logs visíveis no dashboard
- [ ] Integração no menu principal

---

## 🆘 Troubleshooting

### Erro: "Integração Asaas não configurada"
- Acesse `integracao_asaas_config.php`
- Preencha os dados
- Verifique se tabela `integracao_asaas` foi criada

### Erro: "API Key inválida"
- Verifique se API Key está correta
- Verifique se ambiente está correto (Sandbox/Produção)
- Teste API Key no painel Asaas

### Webhook não recebe eventos
- Verifique URL do webhook no painel Asaas
- Verifique se URL é acessível (HTTPS em produção)
- Consulte logs em `logs_asaas_viewer.php`

---

## 📞 Suporte

- **Documentação**: `/docs/`
- **Dashboard de Logs**: `logs_asaas_viewer.php`
- **Painel Asaas**: [asaas.com](https://asaas.com)
- **Documentação Asaas**: [docs.asaas.com](https://docs.asaas.com)

---

## 🎉 Conclusão

Sua APP está **100% pronta** com:

✅ Integração Asaas completa  
✅ Menu de integrações  
✅ Dashboard de logs  
✅ Documentação completa  
✅ Exemplos práticos  
✅ Segurança robusta  
✅ Pronto para produção  

---

**Versão**: 1.0.0  
**Status**: ✅ Pronto para Usar  
**Data**: Janeiro 2025  
**Banco de Dados**: MariaDB 10.x+

🚀 **Bom uso!**
