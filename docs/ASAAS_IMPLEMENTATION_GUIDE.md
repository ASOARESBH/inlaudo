# Guia de Implementação - Integração Asaas v3

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Arquivos Implementados](#arquivos-implementados)
3. [Configuração do Banco de Dados](#configuração-do-banco-de-dados)
4. [Configuração da Integração](#configuração-da-integração)
5. [Endpoints da API](#endpoints-da-api)
6. [Webhook](#webhook)
7. [Fluxo de Pagamento](#fluxo-de-pagamento)
8. [Tratamento de Erros](#tratamento-de-erros)
9. [Logs e Auditoria](#logs-e-auditoria)
10. [Testes](#testes)

---

## Visão Geral

A integração com a API v3 do Asaas permite que sua aplicação:

- **Crie clientes** automaticamente no Asaas
- **Gere cobranças** via PIX ou Boleto
- **Receba notificações** de pagamentos via webhook
- **Atualize status** de contas a receber automaticamente
- **Mantenha auditoria** completa de operações

---

## Arquivos Implementados

### Serviços

| Arquivo | Descrição |
|---------|-----------|
| `src/services/AsaasService.php` | Classe principal de integração com API Asaas |
| `src/models/AsaasModel.php` | Modelo para operações com banco de dados |
| `src/controllers/AsaasController.php` | Controller com endpoints da API |

### Rotas e Webhooks

| Arquivo | Descrição |
|---------|-----------|
| `api_asaas_routes.php` | Roteador de requisições para endpoints |
| `webhook_asaas.php` | Recebe e processa eventos do Asaas |

### Configuração

| Arquivo | Descrição |
|---------|-----------|
| `integracao_asaas_config.php` | Interface web para configuração |
| `asaas_database_setup.md` | Script SQL para criar tabelas |

---

## Configuração do Banco de Dados

### 1. Executar Script SQL

Execute o script SQL fornecido em seu banco de dados:

```bash
mysql -u seu_usuario -p seu_banco < asaas_database_setup.md
```

Ou copie e execute manualmente no phpMyAdmin/MySQL Workbench.

### 2. Tabelas Criadas

**integracao_asaas** - Configuração da integração
```sql
- id (PK)
- api_key (chave de API)
- webhook_token (token de segurança)
- webhook_url (URL do webhook)
- ambiente (sandbox/production)
- ativo (1/0)
```

**asaas_clientes** - Mapeamento de clientes
```sql
- id (PK)
- cliente_id (FK para clientes)
- asaas_customer_id (ID no Asaas)
- cpf_cnpj
```

**asaas_pagamentos** - Mapeamento de cobranças
```sql
- id (PK)
- conta_receber_id (FK para contas_receber)
- asaas_payment_id (ID no Asaas)
- tipo_cobranca (BOLETO/PIX)
- valor
- data_vencimento
- status_asaas
- url_boleto
- nosso_numero
- linha_digitavel
- qr_code_pix
- payload_pix
```

**asaas_logs** - Auditoria de operações
```sql
- id (PK)
- operacao
- status (sucesso/erro/pendente)
- dados_requisicao (JSON)
- dados_resposta (JSON)
- mensagem_erro
```

**asaas_webhooks** - Registro de eventos
```sql
- id (PK)
- event_id (ID único do evento)
- tipo_evento (PAYMENT_RECEIVED, etc)
- payment_id
- payload (JSON completo)
- processado (1/0)
```

---

## Configuração da Integração

### 1. Acessar Configuração

Acesse a página de configuração:
```
http://seu-dominio.com/integracao_asaas_config.php
```

### 2. Obter Credenciais Asaas

1. Acesse [asaas.com](https://asaas.com)
2. Crie uma conta (ou use existente)
3. Vá para **Configurações > Integrações > API**
4. Copie sua **API Key**:
   - Sandbox: `$aact_hmlg_...`
   - Produção: `$aact_prod_...`

### 3. Configurar Webhook

1. No painel Asaas, vá para **Webhooks**
2. Clique em **Novo Webhook**
3. Configure:
   - **URL**: `https://seu-dominio.com/webhook_asaas.php`
   - **Eventos**: Selecione `PAYMENT_RECEIVED` e `PAYMENT_CONFIRMED`
   - **Token**: Gere um UUID v4 forte

### 4. Preencher Formulário

Na página de configuração, preencha:

| Campo | Valor |
|-------|-------|
| **Ambiente** | Selecione `Sandbox` para testes ou `Produção` |
| **API Key** | Cole a chave obtida no Asaas |
| **URL do Webhook** | URL completa do webhook_asaas.php |
| **Token do Webhook** | Mesmo token configurado no Asaas |
| **Ativar** | Marque para ativar a integração |

Clique em **Salvar Configuração**.

---

## Endpoints da API

### 1. Buscar ou Criar Cliente

**Endpoint:**
```
POST /api/asaas/customers
```

**Request:**
```json
{
  "cliente_id": 123,
  "cpf_cnpj": "12345678901234",
  "nome": "Cliente Teste",
  "email": "cliente@example.com",
  "telefone": "11999999999"
}
```

**Response (Sucesso):**
```json
{
  "success": true,
  "customer_id": "cus_000005219613",
  "message": "Cliente processado com sucesso"
}
```

**Response (Erro):**
```json
{
  "error": "Descrição do erro"
}
```

---

### 2. Criar Cobrança

**Endpoint:**
```
POST /api/asaas/payments
```

**Request:**
```json
{
  "conta_receber_id": 456,
  "tipo_cobranca": "PIX",
  "valor": 100.00,
  "data_vencimento": "2025-02-28"
}
```

**Response (PIX):**
```json
{
  "success": true,
  "payment_id": "pay_080225913252",
  "status": "pending",
  "value": 100.00,
  "dueDate": "2025-02-28",
  "additional": {
    "encodedImage": "data:image/png;base64,...",
    "payload": "00020126580014...",
    "expirationDate": "2026-02-28"
  },
  "message": "Cobrança criada com sucesso"
}
```

**Response (Boleto):**
```json
{
  "success": true,
  "payment_id": "pay_080225913252",
  "status": "pending",
  "value": 100.00,
  "dueDate": "2025-02-28",
  "additional": {
    "bankSlipUrl": "https://asaas.com/boleto.pdf",
    "identificationField": "00190000090275928800021932978170187890000005000",
    "nossoNumero": "6543",
    "barCode": "00191878900000050000000002759288002193297817"
  },
  "message": "Cobrança criada com sucesso"
}
```

---

### 3. Obter Status de Cobrança

**Endpoint:**
```
GET /api/asaas/payments/{paymentId}
```

**Response:**
```json
{
  "success": true,
  "payment": {
    "id": "pay_080225913252",
    "status": "RECEIVED",
    "value": 100.00,
    "netValue": 97.50,
    "dueDate": "2025-02-28",
    "paymentDate": "2025-02-25",
    "billingType": "PIX"
  }
}
```

---

## Webhook

### Fluxo de Processamento

1. **Recebimento**: Asaas envia POST para `webhook_asaas.php`
2. **Validação**: Token de segurança é verificado
3. **Idempotência**: Verifica se evento já foi processado
4. **Processamento**: Atualiza banco de dados conforme evento
5. **Resposta**: Retorna HTTP 200 para confirmar

### Eventos Processados

| Evento | Ação |
|--------|------|
| `PAYMENT_RECEIVED` | Marca conta como paga, atualiza status |
| `PAYMENT_CONFIRMED` | Marca conta como paga, atualiza status |
| `PAYMENT_PENDING` | Registra em log |
| `PAYMENT_OVERDUE` | Registra em log |
| `PAYMENT_DELETED` | Registra em log |

### Estrutura do Payload

```json
{
  "id": "evt_05b708f961d739ea7eba7e4db318f621",
  "event": "PAYMENT_RECEIVED",
  "dateCreated": "2024-06-12 16:45:03",
  "payment": {
    "id": "pay_080225913252",
    "status": "RECEIVED",
    "value": 100.00,
    "netValue": 97.50,
    "dueDate": "2025-02-28",
    "paymentDate": "2025-02-25",
    "billingType": "PIX"
  }
}
```

---

## Fluxo de Pagamento

### Fluxo Completo

```
1. Sistema ERP
   ↓
2. Buscar/Criar Cliente (POST /api/asaas/customers)
   ↓
3. Asaas retorna customer_id
   ↓
4. Criar Cobrança (POST /api/asaas/payments)
   ↓
5. Asaas retorna payment_id + dados (QR Code/Boleto)
   ↓
6. Exibir para cliente (PIX/Boleto)
   ↓
7. Cliente paga
   ↓
8. Asaas envia webhook (PAYMENT_RECEIVED)
   ↓
9. webhook_asaas.php processa
   ↓
10. Atualiza contas_receber (status = 'pago')
   ↓
11. Registra auditoria em notas_contas_receber
```

---

## Tratamento de Erros

### Erros Comuns

| Erro | Causa | Solução |
|------|-------|--------|
| `Integração Asaas não configurada` | Config não salva | Acesse integracao_asaas_config.php |
| `API Key inválida` | Chave incorreta | Verifique chave no painel Asaas |
| `Cliente não mapeado` | Cliente não criado | Crie cliente primeiro |
| `Token inválido` | Token webhook incorreto | Verifique token na config |
| `Conta a receber não encontrada` | ID incorreto | Verifique ID da conta |

### Tratamento em Código

```php
try {
    $asaasService = new AsaasService();
    $customer = $asaasService->createCustomer($data);
} catch (\Exception $e) {
    error_log('Erro Asaas: ' . $e->getMessage());
    // Retornar erro ao usuário
    return ['error' => $e->getMessage()];
}
```

---

## Logs e Auditoria

### Arquivos de Log

**Webhook:**
```
logs/webhook_asaas_YYYY-MM-DD.log
```

**Banco de Dados:**
```sql
SELECT * FROM asaas_logs ORDER BY data_criacao DESC;
SELECT * FROM asaas_webhooks ORDER BY data_recebimento DESC;
```

### Exemplo de Log

```
[WEBHOOK ASAAS] Evento recebido: PAYMENT_RECEIVED - ID: evt_05b708f961d739ea7eba7e4db318f621
[WEBHOOK ASAAS] Processando pagamento: pay_080225913252
[WEBHOOK ASAAS] Pagamento processado com sucesso: pay_080225913252
```

### Auditoria em Contas a Receber

Cada pagamento registra uma nota:
```
Pagamento recebido via Asaas. ID: pay_080225913252. Status: RECEIVED
Valor pago: R$ 97,50
```

---

## Testes

### 1. Teste em Sandbox

1. Configure ambiente como **Sandbox**
2. Use API Key de teste (`$aact_hmlg_...`)
3. Crie clientes e cobranças de teste
4. Simule pagamentos no painel Asaas

### 2. Teste de Webhook

Use ferramentas como:
- **Postman**: Simule POST para webhook_asaas.php
- **ngrok**: Exponha localhost para testes
- **RequestBin**: Capture requisições

### 3. Exemplo de Teste com cURL

```bash
# Criar cliente
curl -X POST http://localhost/api/asaas/customers \
  -H "Content-Type: application/json" \
  -d '{
    "cliente_id": 123,
    "cpf_cnpj": "12345678901234",
    "nome": "Teste"
  }'

# Criar cobrança
curl -X POST http://localhost/api/asaas/payments \
  -H "Content-Type: application/json" \
  -d '{
    "conta_receber_id": 456,
    "tipo_cobranca": "PIX",
    "valor": 100.00,
    "data_vencimento": "2025-02-28"
  }'

# Simular webhook
curl -X POST http://localhost/webhook_asaas.php \
  -H "Content-Type: application/json" \
  -H "asaas-access-token: seu-token-webhook" \
  -d '{
    "id": "evt_test",
    "event": "PAYMENT_RECEIVED",
    "dateCreated": "2025-01-08 10:00:00",
    "payment": {
      "id": "pay_test",
      "status": "RECEIVED",
      "value": 100.00,
      "netValue": 97.50
    }
  }'
```

---

## Próximos Passos

1. **Integrar no Portal do Cliente**: Adicione botão "Pagar" que chama endpoints
2. **Adicionar Notificações**: Envie email quando pagamento for recebido
3. **Relatórios**: Crie dashboard com status de cobranças
4. **Automação**: Crie regras para gerar cobranças automaticamente
5. **Sincronização**: Implemente sincronização periódica de status

---

## Suporte

Para dúvidas sobre a integração Asaas:
- [Documentação Oficial](https://docs.asaas.com)
- [API Reference](https://docs.asaas.com/reference)
- [Sandbox](https://docs.asaas.com/docs/sandbox)

---

**Versão**: 1.0.0  
**Última Atualização**: Janeiro 2025  
**Desenvolvedor**: Backend Developer
