# Integração Asaas v3 - ERP Inlaudo

**Versão**: 5.0.0  
**Data**: 09 de Janeiro de 2026  
**Status**: ✅ Pronto para Produção

---

## 📋 Conteúdo do Pacote

```
erp_inlaudo_producao/
├── integracao_asaas.php              # Dashboard Asaas
├── integracao_asaas_config.php       # Configuração de credenciais
├── logout.php                        # Logout corrigido
├── webhook/
│   ├── asaas.php                     # Webhook para receber eventos
│   ├── .htaccess                     # Proteção de webhook
│   └── logs/                         # Pasta para logs (criar)
├── src/
│   └── services/
│       └── AsaasService.php          # Classe de serviço Asaas
├── sql/
│   └── asaas_integration.sql         # Script SQL para criar tabelas
└── README_ASAAS.md                   # Este arquivo
```

---

## 🚀 Instalação Rápida

### Passo 1: Fazer Backup

```bash
cp integracao_asaas_config.php integracao_asaas_config.php.backup
cp logout.php logout.php.backup
cp integracao_asaas.php integracao_asaas.php.backup
```

### Passo 2: Copiar Arquivos

```bash
# Copiar arquivos principais
cp integracao_asaas.php /seu/erp/
cp integracao_asaas_config.php /seu/erp/
cp logout.php /seu/erp/

# Copiar serviço
cp src/services/AsaasService.php /seu/erp/src/services/

# Copiar webhook
cp webhook/asaas.php /seu/erp/webhook/
cp webhook/.htaccess /seu/erp/webhook/
```

### Passo 3: Criar Diretório de Logs

```bash
mkdir -p /seu/erp/webhook/logs
chmod 755 /seu/erp/webhook/logs
```

### Passo 4: Executar Script SQL

```bash
# Via linha de comando
mysql -u usuario -p banco < sql/asaas_integration.sql

# Ou via phpMyAdmin
# 1. Selecione seu banco
# 2. Clique em "Importar"
# 3. Selecione o arquivo sql/asaas_integration.sql
# 4. Clique em "Executar"
```

### Passo 5: Configurar Asaas

1. Acesse: `https://erp.inlaudo.com.br/integracao_asaas_config.php`
2. Preencha:
   - **API Key**: Obtenha em https://app.asaas.com/settings/apikey
   - **Ambiente**: Selecione Sandbox ou Production
   - **Webhook Token**: (Opcional) Crie um token seguro
3. Clique em "Salvar Configuração"

### Passo 6: Configurar Webhook no Asaas

1. Acesse: https://app.asaas.com/webhooks
2. Clique em "Novo Webhook"
3. Cole a URL: `https://erp.inlaudo.com.br/webhook/asaas.php`
4. Selecione eventos:
   - PAYMENT_RECEIVED
   - PAYMENT_CONFIRMED
5. Clique em "Salvar"

---

## 📊 Funcionalidades

### Dashboard Asaas (`integracao_asaas.php`)

- ✅ Status da integração
- ✅ Ambiente ativo (Sandbox/Production)
- ✅ Total de clientes mapeados
- ✅ Total de cobranças criadas
- ✅ Ações rápidas
- ✅ Instruções de webhook
- ✅ Estatísticas

### Configuração (`integracao_asaas_config.php`)

- ✅ Salvar/atualizar API Key
- ✅ Selecionar ambiente
- ✅ Configurar webhook token
- ✅ Ativar/desativar integração
- ✅ Visualizar status

### Webhook (`webhook/asaas.php`)

- ✅ Receber eventos do Asaas
- ✅ Validar token de segurança
- ✅ Processar PAYMENT_RECEIVED
- ✅ Processar PAYMENT_CONFIRMED
- ✅ Atualizar status em contas_receber
- ✅ Registrar em notas_contas_receber
- ✅ Logs estruturados
- ✅ Idempotência

### Logout (`logout.php`)

- ✅ Registrar logout em logs_acesso
- ✅ Destruir sessão seguramente
- ✅ Limpar cookies
- ✅ Tratamento de erros

---

## 🔐 Segurança

### Proteção de Webhook

O arquivo `.htaccess` em `/webhook/` protege:
- ✅ Apenas POST é permitido
- ✅ Arquivos `.log` não são acessíveis
- ✅ Listagem de diretório desabilitada

### Validação

- ✅ Token de segurança do webhook
- ✅ Prepared statements (SQL Injection prevention)
- ✅ Validação de entrada
- ✅ Tratamento de erros

### Logs

- ✅ Arquivo diário: `/webhook/logs/asaas_YYYY-MM-DD.log`
- ✅ Protegido de acesso direto
- ✅ Auditoria completa

---

## 📝 Estrutura de Banco de Dados

### Tabelas Criadas

1. **integracao_asaas** - Configuração
2. **asaas_clientes** - Mapeamento de clientes
3. **asaas_pagamentos** - Mapeamento de cobranças
4. **asaas_logs** - Auditoria
5. **asaas_webhooks** - Eventos recebidos

### Colunas Adicionadas

- `contas_receber.gateway_payment_id`
- `contas_receber.forma_pagamento`
- `contas_receber.ambiente_pagamento`
- `contratos.forma_pagamento`
- `contratos.ambiente_pagamento`
- `contas_pagar.forma_pagamento`
- `contas_pagar.ambiente_pagamento`
- `royalties.forma_pagamento`
- `royalties.ambiente_pagamento`

---

## 🧪 Testes

### Teste 1: Configuração

```bash
curl https://erp.inlaudo.com.br/integracao_asaas_config.php
```

**Esperado**: Página carrega sem erro 500

### Teste 2: Dashboard

```bash
curl https://erp.inlaudo.com.br/integracao_asaas.php
```

**Esperado**: Dashboard carrega com estatísticas

### Teste 3: Logout

```bash
curl https://erp.inlaudo.com.br/logout.php
```

**Esperado**: Redireciona para login.php

### Teste 4: Webhook

```bash
curl -X POST https://erp.inlaudo.com.br/webhook/asaas.php \
  -H "Content-Type: application/json" \
  -d '{
    "event": "PAYMENT_RECEIVED",
    "payment": {
      "id": "pay_test_123",
      "status": "RECEIVED"
    }
  }'
```

**Esperado**: Resposta JSON com status 200

### Teste 5: Logs

```bash
tail -f /seu/erp/webhook/logs/asaas_$(date +%Y-%m-%d).log
```

**Esperado**: Logs aparecem em tempo real

---

## 📞 Troubleshooting

### Erro 500 em `integracao_asaas_config.php`

**Problema**: Página retorna erro 500

**Solução**:
1. Verifique se `config.php` existe
2. Verifique permissões do arquivo
3. Verifique logs do servidor

### Erro 500 em `logout.php`

**Problema**: Logout não funciona

**Solução**:
1. Verifique se `config.php` existe
2. Verifique se tabela `logs_acesso` existe
3. Verifique permissões de banco

### Webhook não recebe eventos

**Problema**: Eventos do Asaas não chegam

**Solução**:
1. Verifique URL no Asaas: `https://erp.inlaudo.com.br/webhook/asaas.php`
2. Verifique arquivo existe: `/seu/erp/webhook/asaas.php`
3. Verifique permissões: `chmod 644 /seu/erp/webhook/asaas.php`
4. Verifique logs: `/seu/erp/webhook/logs/asaas_YYYY-MM-DD.log`

### Logs não aparecem

**Problema**: Arquivo de log não é criado

**Solução**:
1. Verifique diretório: `/seu/erp/webhook/logs/` existe?
2. Verifique permissões: `chmod 755 /seu/erp/webhook/logs`
3. Verifique se webhook foi chamado
4. Verifique logs do servidor

---

## 🔗 Links Úteis

- **Documentação Asaas**: https://docs.asaas.com
- **Sandbox Asaas**: https://sandbox.asaas.com
- **Produção Asaas**: https://app.asaas.com
- **API Key**: https://app.asaas.com/settings/apikey
- **Webhooks**: https://app.asaas.com/webhooks

---

## 📊 Endpoints Disponíveis

### Páginas Web

| URL | Descrição |
|-----|-----------|
| `/integracao_asaas.php` | Dashboard |
| `/integracao_asaas_config.php` | Configuração |
| `/logout.php` | Logout |

### Webhook

| URL | Método | Descrição |
|-----|--------|-----------|
| `/webhook/asaas.php` | POST | Receber eventos |

### Serviço

| Classe | Arquivo |
|--------|---------|
| `AsaasService` | `src/services/AsaasService.php` |

---

## 📚 Exemplos de Uso

### Usar AsaasService

```php
require_once 'config.php';
require_once 'src/services/AsaasService.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);

// Buscar configuração
$sql = "SELECT * FROM integracao_asaas WHERE id = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

// Criar serviço
$asaas = new AsaasService($pdo, $config['api_key'], $config['ambiente']);

// Criar cliente
$cliente = $asaas->criarOuBuscarCliente('12345678901234', 'Nome Cliente', 'email@example.com');

// Criar cobrança PIX
$pix = $asaas->criarCobrancaPix($cliente['id'], 100.00, '2026-02-09', 'Descrição');

// Criar cobrança Boleto
$boleto = $asaas->criarCobrancaBoleto($cliente['id'], 100.00, '2026-02-09', 'Descrição');

// Obter status
$status = $asaas->obterStatusPagamento($pix['id']);
```

---

## ✅ Checklist Final

- [ ] Backup dos arquivos originais realizado
- [ ] Arquivos copiados para os locais corretos
- [ ] Diretório `/webhook/logs/` criado
- [ ] Script SQL executado
- [ ] Tabelas criadas no banco
- [ ] Configuração Asaas preenchida
- [ ] Webhook configurado no Asaas
- [ ] Testes executados com sucesso
- [ ] Logs aparecem em `/webhook/logs/`
- [ ] Nenhum erro 500
- [ ] Logout funciona
- [ ] Dashboard carrega

---

## 📞 Suporte

Para dúvidas ou problemas:

1. Consulte os logs em `/webhook/logs/`
2. Verifique a documentação do Asaas
3. Verifique permissões de arquivo/pasta
4. Verifique conexão com banco de dados

---

**Versão**: 5.0.0  
**Data**: 09 de Janeiro de 2026  
**Desenvolvedor**: Engenheiro de Software Sênior

🚀 **Pronto para Produção!**
