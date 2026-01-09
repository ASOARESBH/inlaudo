# Pesquisa - API Asaas v3

## Autenticação
- **Header obrigatório**: `access_token` com a chave de API
- **Formato da chave**:
  - Produção: `$aact_prod_...`
  - Sandbox: `$aact_hmlg_...`
- **Headers necessários**:
  ```
  Content-Type: application/json
  User-Agent: your_app_name
  access_token: your_api_key
  ```

## URLs Base
- **Produção**: `https://api.asaas.com/v3`
- **Sandbox**: `https://api-sandbox.asaas.com/v3`

## Criação de Clientes
- **Endpoint**: `POST /v3/customers`
- **Parâmetros obrigatórios**:
  - `name`: Nome do cliente
  - `cpfCnpj`: CPF ou CNPJ (sem formatação)
  - `mobilePhone`: Telefone (opcional mas recomendado)
- **Resposta**: Retorna objeto com `id` (identificador único do cliente)
- **Importante**: Duplicatas são permitidas, deve-se verificar antes de criar

## Cobranças via Boleto
- **Endpoint**: `POST /v3/lean/payments`
- **Parâmetros obrigatórios**:
  - `customer`: ID do cliente (cus_...)
  - `billingType`: "BOLETO"
  - `value`: Valor da cobrança
  - `dueDate`: Data de vencimento (YYYY-MM-DD)
- **Resposta**: Retorna `bankSlipUrl` (PDF do boleto) e outros dados
- **Linha digitável**: `GET /v3/lean/payments/{id}/identificationField`
  - Retorna: `identificationField`, `nossoNumero`, `barCode`

## Cobranças via PIX
- **Endpoint**: `POST /v3/lean/payments`
- **Parâmetros obrigatórios**:
  - `customer`: ID do cliente
  - `billingType`: "PIX"
  - `value`: Valor
  - `dueDate`: Data de vencimento
- **QR Code**: `GET /v3/payments/{id}/pixQrCode`
  - Retorna: `encodedImage` (Base64), `payload` (copia e cola), `expirationDate`
- **Características**:
  - QR Code dinâmico com vencimento
  - Expira 12 meses após data de vencimento
  - Pode ser impresso em boletos

## Webhooks
- **Configuração**: Via interface web ou API
- **Máximo**: 10 webhooks por conta
- **Segurança**: Token de acesso no header `asaas-access-token`
- **Garantia**: "At least once" (pode receber duplicatas)
- **Retenção**: 14 dias de eventos na fila
- **Resposta esperada**: HTTP 200-299 para sucesso

## Eventos de Webhook
- **PAYMENT_RECEIVED**: Pagamento recebido
- **PAYMENT_CONFIRMED**: Pagamento confirmado
- **Estrutura do evento**:
  ```json
  {
    "id": "evt_...",
    "event": "PAYMENT_RECEIVED",
    "dateCreated": "2024-06-12 16:45:03",
    "payment": {
      "object": "payment",
      "id": "pay_...",
      ...
    }
  }
  ```

## Boas Práticas
1. Validar token de segurança no header `asaas-access-token`
2. Implementar idempotência (usar ID do evento)
3. Retornar resposta 200 rapidamente
4. Processar eventos de forma assíncrona
5. Registrar eventos já processados para evitar duplicatas
6. Usar IPs oficiais do Asaas para firewall
7. Armazenar IDs de clientes criados para evitar duplicatas
