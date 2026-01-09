# Guia de Testes - Integração Asaas

## 📋 Checklist de Testes

### 1. Testes de Configuração

- [ ] Acessar página de configuração (`integracao_asaas_config.php`)
- [ ] Preencher formulário com dados de teste
- [ ] Salvar configuração
- [ ] Verificar dados salvos no banco de dados
- [ ] Testar com ambiente Sandbox
- [ ] Testar com ambiente Produção

**SQL para verificar:**
```sql
SELECT * FROM integracao_asaas WHERE id = 1;
```

---

### 2. Testes de Autenticação

**Objetivo**: Verificar se a autenticação com Asaas está funcionando

#### Teste 2.1: API Key Válida

```bash
curl -X GET https://api-sandbox.asaas.com/v3/customers \
  -H "access_token: $aact_hmlg_SEU_TOKEN_AQUI"
```

**Resultado esperado**: HTTP 200 com lista de clientes

#### Teste 2.2: API Key Inválida

```bash
curl -X GET https://api-sandbox.asaas.com/v3/customers \
  -H "access_token: $aact_hmlg_INVALIDO"
```

**Resultado esperado**: HTTP 401 com erro de autenticação

---

### 3. Testes de Endpoints

### 3.1: Criar Cliente

**Endpoint**: `POST /api/asaas/customers`

**Request:**
```bash
curl -X POST http://localhost/api/asaas/customers \
  -H "Content-Type: application/json" \
  -d '{
    "cliente_id": 1,
    "cpf_cnpj": "12345678901234",
    "nome": "Cliente Teste",
    "email": "teste@example.com",
    "telefone": "11999999999"
  }'
```

**Resultado esperado**:
```json
{
  "success": true,
  "customer_id": "cus_000005219613",
  "message": "Cliente processado com sucesso"
}
```

**Validações**:
- [ ] Cliente criado no Asaas
- [ ] Mapeamento salvo em `asaas_clientes`
- [ ] Log registrado em `asaas_logs`

---

### 3.2: Criar Cobrança PIX

**Endpoint**: `POST /api/asaas/payments`

**Request:**
```bash
curl -X POST http://localhost/api/asaas/payments \
  -H "Content-Type: application/json" \
  -d '{
    "conta_receber_id": 1,
    "tipo_cobranca": "PIX",
    "valor": 100.00,
    "data_vencimento": "2025-02-28"
  }'
```

**Resultado esperado**:
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

**Validações**:
- [ ] Cobrança criada no Asaas
- [ ] QR Code gerado
- [ ] Payload PIX retornado
- [ ] Mapeamento salvo em `asaas_pagamentos`
- [ ] Conta a receber atualizada com `gateway_asaas_id`

---

### 3.3: Criar Cobrança Boleto

**Endpoint**: `POST /api/asaas/payments`

**Request:**
```bash
curl -X POST http://localhost/api/asaas/payments \
  -H "Content-Type: application/json" \
  -d '{
    "conta_receber_id": 2,
    "tipo_cobranca": "BOLETO",
    "valor": 200.00,
    "data_vencimento": "2025-03-15"
  }'
```

**Resultado esperado**:
```json
{
  "success": true,
  "payment_id": "pay_080225913253",
  "status": "pending",
  "value": 200.00,
  "dueDate": "2025-03-15",
  "additional": {
    "bankSlipUrl": "https://asaas.com/boleto.pdf",
    "identificationField": "00190000090275928800021932978170187890000005000",
    "nossoNumero": "6543",
    "barCode": "00191878900000050000000002759288002193297817"
  },
  "message": "Cobrança criada com sucesso"
}
```

**Validações**:
- [ ] Cobrança criada no Asaas
- [ ] URL do boleto retornada
- [ ] Linha digitável gerada
- [ ] Nosso número retornado
- [ ] Mapeamento salvo em `asaas_pagamentos`

---

### 3.4: Obter Status de Cobrança

**Endpoint**: `GET /api/asaas/payments/{paymentId}`

**Request:**
```bash
curl -X GET http://localhost/api/asaas/payments/pay_080225913252
```

**Resultado esperado**:
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

### 4. Testes de Webhook

### 4.1: Validação de Token

**Objetivo**: Verificar se webhook valida token corretamente

#### Teste 4.1.1: Token Válido

```bash
curl -X POST http://localhost/webhook_asaas.php \
  -H "Content-Type: application/json" \
  -H "asaas-access-token: seu-token-webhook-valido" \
  -d '{
    "id": "evt_test_001",
    "event": "PAYMENT_RECEIVED",
    "dateCreated": "2025-01-08 10:00:00",
    "payment": {
      "id": "pay_080225913252",
      "status": "RECEIVED",
      "value": 100.00,
      "netValue": 97.50
    }
  }'
```

**Resultado esperado**: HTTP 200 com `{"received": true}`

#### Teste 4.1.2: Token Inválido

```bash
curl -X POST http://localhost/webhook_asaas.php \
  -H "Content-Type: application/json" \
  -H "asaas-access-token: token-invalido" \
  -d '{...}'
```

**Resultado esperado**: HTTP 401 com `{"error": "Unauthorized"}`

---

### 4.2: Processamento de Evento

**Objetivo**: Verificar se webhook processa evento corretamente

**Request:**
```bash
curl -X POST http://localhost/webhook_asaas.php \
  -H "Content-Type: application/json" \
  -H "asaas-access-token: seu-token-webhook-valido" \
  -d '{
    "id": "evt_test_payment_received",
    "event": "PAYMENT_RECEIVED",
    "dateCreated": "2025-01-08 10:00:00",
    "payment": {
      "id": "pay_080225913252",
      "status": "RECEIVED",
      "value": 100.00,
      "netValue": 97.50,
      "dueDate": "2025-02-28",
      "paymentDate": "2025-02-25"
    }
  }'
```

**Validações após webhook**:
- [ ] HTTP 200 retornado
- [ ] Evento registrado em `asaas_webhooks`
- [ ] Conta a receber marcada como "pago"
- [ ] Nota de auditoria criada em `notas_contas_receber`
- [ ] Log criado em `asaas_logs`

**SQL para verificar:**
```sql
-- Verificar webhook recebido
SELECT * FROM asaas_webhooks WHERE event_id = 'evt_test_payment_received';

-- Verificar conta atualizada
SELECT status, data_pagamento FROM contas_receber WHERE id = 1;

-- Verificar nota de auditoria
SELECT * FROM notas_contas_receber WHERE conta_receber_id = 1 ORDER BY data_criacao DESC;
```

---

### 4.3: Idempotência

**Objetivo**: Verificar se webhook não processa duplicatas

**Teste**: Enviar mesmo evento 2 vezes

```bash
# Primeira vez
curl -X POST http://localhost/webhook_asaas.php \
  -H "Content-Type: application/json" \
  -H "asaas-access-token: seu-token" \
  -d '{"id": "evt_duplicado", "event": "PAYMENT_RECEIVED", ...}'

# Segunda vez (mesmo evento)
curl -X POST http://localhost/webhook_asaas.php \
  -H "Content-Type: application/json" \
  -H "asaas-access-token: seu-token" \
  -d '{"id": "evt_duplicado", "event": "PAYMENT_RECEIVED", ...}'
```

**Validações**:
- [ ] Primeira requisição: HTTP 200 com processamento
- [ ] Segunda requisição: HTTP 200 com `"duplicate": true`
- [ ] Apenas uma nota de auditoria criada
- [ ] Banco de dados atualizado apenas uma vez

---

### 5. Testes de Integração Completa

### 5.1: Fluxo Completo (Cliente → Cobrança → Webhook)

**Passo 1**: Criar cliente
```bash
curl -X POST http://localhost/api/asaas/customers \
  -H "Content-Type: application/json" \
  -d '{
    "cliente_id": 100,
    "cpf_cnpj": "12345678901234",
    "nome": "Teste Integração Completa"
  }'
```

**Passo 2**: Criar cobrança
```bash
curl -X POST http://localhost/api/asaas/payments \
  -H "Content-Type: application/json" \
  -d '{
    "conta_receber_id": 100,
    "tipo_cobranca": "PIX",
    "valor": 50.00,
    "data_vencimento": "2025-02-28"
  }'
```

**Passo 3**: Simular pagamento no painel Asaas
- Acessar painel Asaas Sandbox
- Marcar cobrança como paga
- Asaas enviará webhook

**Passo 4**: Verificar atualização no banco
```sql
SELECT status, data_pagamento FROM contas_receber WHERE id = 100;
```

**Resultado esperado**: Status = 'pago', data_pagamento preenchida

---

### 6. Testes de Tratamento de Erros

### 6.1: Cliente Inválido

**Request:**
```bash
curl -X POST http://localhost/api/asaas/customers \
  -H "Content-Type: application/json" \
  -d '{"cliente_id": 1}'  # Faltam campos obrigatórios
```

**Resultado esperado**: HTTP 400 com mensagem de erro

---

### 6.2: Cobrança sem Cliente

**Request:**
```bash
curl -X POST http://localhost/api/asaas/payments \
  -H "Content-Type: application/json" \
  -d '{
    "conta_receber_id": 999,  # Conta inexistente
    "tipo_cobranca": "PIX",
    "valor": 100.00,
    "data_vencimento": "2025-02-28"
  }'
```

**Resultado esperado**: HTTP 400 com "Cliente não mapeado no Asaas"

---

### 6.3: API Key Expirada

**Objetivo**: Testar comportamento com credenciais inválidas

- [ ] Alterar API Key para valor inválido
- [ ] Tentar criar cobrança
- [ ] Verificar se erro é capturado e registrado
- [ ] Verificar mensagem de erro apropriada

---

### 7. Testes de Performance

### 7.1: Criar 100 Cobranças

**Objetivo**: Verificar performance com múltiplas requisições

```bash
for i in {1..100}; do
  curl -X POST http://localhost/api/asaas/payments \
    -H "Content-Type: application/json" \
    -d "{
      \"conta_receber_id\": $i,
      \"tipo_cobranca\": \"PIX\",
      \"valor\": 100.00,
      \"data_vencimento\": \"2025-02-28\"
    }"
done
```

**Validações**:
- [ ] Todas as requisições completadas com sucesso
- [ ] Tempo total < 5 minutos
- [ ] Banco de dados não travou
- [ ] Logs registrados corretamente

---

### 8. Testes de Segurança

### 8.1: SQL Injection

**Teste**: Tentar injetar SQL no CPF/CNPJ

```bash
curl -X POST http://localhost/api/asaas/customers \
  -H "Content-Type: application/json" \
  -d '{
    "cliente_id": 1,
    "cpf_cnpj": "123456789\"; DROP TABLE clientes; --",
    "nome": "Teste"
  }'
```

**Resultado esperado**: Erro de validação, sem SQL injection

---

### 8.2: Token Webhook Brute Force

**Teste**: Tentar adivinhar token

```bash
for i in {1..10}; do
  curl -X POST http://localhost/webhook_asaas.php \
    -H "asaas-access-token: token_aleatorio_$i" \
    -d '{...}'
done
```

**Validações**:
- [ ] Todos retornam HTTP 401
- [ ] Tentativas registradas em logs
- [ ] Sem bloqueio de IP (não implementado, mas considerar)

---

### 9. Testes em Produção

**Antes de ir para produção:**

- [ ] Testar com API Key de produção
- [ ] Configurar webhook em produção
- [ ] Testar com cliente real
- [ ] Verificar se emails são enviados corretamente
- [ ] Monitorar logs por 24 horas
- [ ] Testar rollback se necessário

---

## 📊 Relatório de Testes

### Template

```
Data do Teste: __/__/____
Ambiente: [ ] Sandbox [ ] Produção
Versão: 1.0.0

Testes Executados:
- [ ] Configuração
- [ ] Autenticação
- [ ] Endpoints
- [ ] Webhook
- [ ] Integração Completa
- [ ] Tratamento de Erros
- [ ] Performance
- [ ] Segurança

Resultados:
✓ Passou: __
✗ Falhou: __
⚠ Avisos: __

Problemas Encontrados:
1. ...
2. ...

Observações:
...

Aprovado por: ________________
Data: __/__/____
```

---

## 🔧 Ferramentas Recomendadas

- **Postman**: Testar endpoints
- **ngrok**: Expor localhost para testes de webhook
- **MySQL Workbench**: Verificar banco de dados
- **cURL**: Testes via linha de comando
- **Insomnia**: Alternativa ao Postman

---

## 📞 Suporte

Em caso de problemas:
1. Verificar logs em `logs/webhook_asaas_*.log`
2. Consultar `asaas_logs` no banco de dados
3. Acessar painel do Asaas para verificar status
4. Revisar documentação em `ASAAS_IMPLEMENTATION_GUIDE.md`

---

**Versão**: 1.0.0  
**Última Atualização**: Janeiro 2025
