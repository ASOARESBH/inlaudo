# ERP INLAUDO

Sistema de Gestão Empresarial (ERP) desenvolvido para a INLAUDO.

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-Proprietary-red)](LICENSE)

## 📋 Sobre o Projeto

O **ERP INLAUDO** é um sistema completo de gestão empresarial que integra:

- 📊 **CRM** - Gestão de clientes e leads
- 💰 **Financeiro** - Contas a pagar e receber
- 📄 **Notas Fiscais** - Emissão e gerenciamento
- 🔔 **Alertas** - Sistema de notificações programadas
- 🔗 **Integrações** - Asaas, Mercado Pago, Stripe, Cora
- 👥 **Portal do Cliente** - Acesso para clientes

## 🚀 Tecnologias

- **Backend:** PHP 7.4+
- **Banco de Dados:** MySQL 5.7+ / MariaDB
- **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5
- **Bibliotecas:** PDO, FPDF, PHPMailer
- **Servidor:** Apache (Hostgator)

## 📁 Estrutura do Projeto

```
erp-inlaudo/
├── src/                    # Código MVC (em desenvolvimento)
│   ├── controllers/        # Controllers
│   ├── models/            # Models
│   ├── services/          # Services
│   ├── views/             # Views
│   └── core/              # Classes base
├── config/                # Configurações
├── public/                # Assets públicos (CSS, JS)
├── api/                   # APIs REST
├── database/              # Migrações e backups
├── logs/                  # Logs do sistema
├── uploads/               # Arquivos enviados
├── webhook/               # Webhooks de integrações
├── docs/                  # Documentação
└── [arquivos legados]     # Sistema procedural (em migração)
```

## 🔧 Instalação

### Requisitos

- PHP >= 7.4
- MySQL >= 5.7 ou MariaDB >= 10.3
- Apache com mod_rewrite
- Composer (recomendado)
- Extensões PHP: PDO, JSON, cURL, mbstring, GD

### Passo a Passo

1. **Clone o repositório:**
```bash
git clone https://github.com/ASOARESBH/inlaudo.git
cd inlaudo
```

2. **Configure o banco de dados:**
```bash
# Importar estrutura do banco
mysql -u seu_usuario -p seu_banco < database/sql/schema.sql
```

3. **Configure as variáveis de ambiente:**
```bash
# Copiar arquivo de exemplo
cp .env.example .env

# Editar com suas credenciais
nano .env
```

4. **Configure permissões:**
```bash
chmod 755 uploads/
chmod 755 logs/
chmod 755 storage/cache/
```

5. **Acesse o sistema:**
```
http://seu-dominio.com.br
```

## ⚙️ Configuração

### Arquivo .env

Crie um arquivo `.env` na raiz do projeto com as seguintes variáveis:

```env
# Banco de Dados
DB_HOST=localhost
DB_NAME=seu_banco
DB_USER=seu_usuario
DB_PASS=sua_senha
DB_CHARSET=utf8mb4

# Sistema
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.inlaudo.com.br

# Email
MAIL_HOST=smtp.hostgator.com
MAIL_PORT=465
MAIL_USERNAME=seu_email@dominio.com
MAIL_PASSWORD=sua_senha
MAIL_FROM=noreply@inlaudo.com.br

# Integrações (opcional)
ASAAS_API_KEY=
MERCADOPAGO_ACCESS_TOKEN=
STRIPE_SECRET_KEY=
CORA_API_KEY=
```

### Apache (.htaccess)

O arquivo `.htaccess` já está configurado. Certifique-se de que o `mod_rewrite` está habilitado:

```bash
sudo a2enmod rewrite
sudo service apache2 restart
```

## 📚 Documentação

- [Instalação Completa](INSTALACAO_COMPLETA.md)
- [Integração Asaas](README_ASAAS.md)
- [Análise MVC e Melhorias](ANALISE_MVC_MELHORIAS.md)
- [API Documentation](docs/API.md)

## 🔐 Segurança

### Boas Práticas Implementadas

- ✅ Prepared Statements (PDO)
- ✅ Sanitização de inputs
- ✅ Proteção contra SQL Injection
- ✅ Proteção contra XSS
- ✅ Validação de sessões
- ✅ HTTPS recomendado
- ✅ Credenciais em variáveis de ambiente

### Arquivos Protegidos

O `.htaccess` protege automaticamente:
- Arquivos `.env`
- Logs (`.log`)
- Configurações (`.ini`)
- Scripts shell (`.sh`)
- Dumps SQL (`.sql`)

## 🔄 Integrações

### Asaas (Gateway de Pagamento)

Sistema de cobrança via PIX e Boleto integrado.

**Endpoints:**
- `POST /api/asaas/customers` - Criar/buscar cliente
- `POST /api/asaas/payments` - Criar cobrança
- `GET /api/asaas/payments/{id}` - Consultar status

**Documentação:** [README_ASAAS.md](README_ASAAS.md)

### Mercado Pago

Integração com checkout e webhooks.

### Stripe

Processamento de pagamentos internacionais.

### Cora

Banking as a Service.

## 🧪 Testes

```bash
# Executar testes (quando implementados)
composer test
```

## 📊 Status do Projeto

### ✅ Implementado

- Sistema de autenticação
- CRUD de clientes
- Gestão financeira (contas a pagar/receber)
- Sistema de alertas
- Integração Asaas
- Portal do cliente
- Emissão de boletos
- Webhooks

### 🚧 Em Desenvolvimento

- Migração completa para MVC
- Testes automatizados
- API REST completa
- Dashboard analytics
- Relatórios avançados

### 📋 Planejado

- App mobile
- Integração com contabilidade
- Sistema de estoque
- Módulo de RH

## 🤝 Contribuindo

Este é um projeto privado. Para contribuir:

1. Crie uma branch para sua feature (`git checkout -b feature/MinhaFeature`)
2. Commit suas mudanças (`git commit -m 'Adiciona MinhaFeature'`)
3. Push para a branch (`git push origin feature/MinhaFeature`)
4. Abra um Pull Request

### Padrões de Código

- PSR-12 para PHP
- Comentários em português
- Documentação inline obrigatória
- Testes para novas features

## 🐛 Reportar Bugs

Encontrou um bug? Abra uma issue com:

- Descrição detalhada
- Passos para reproduzir
- Comportamento esperado vs atual
- Screenshots (se aplicável)
- Ambiente (PHP version, OS, etc)

## 📝 Changelog

### [2.0.0] - 2026-01-09

#### Adicionado
- Estrutura MVC em `src/`
- Sistema de rotas
- Base Controllers e Models
- Integração Asaas completa
- Sistema de alertas programados
- Portal do cliente

#### Modificado
- Refatoração de ClienteModel
- Melhorias de segurança
- Otimização de queries

#### Corrigido
- Bugs de autenticação
- Problemas com webhooks
- Validações de formulários

## 📄 Licença

Este projeto é proprietário e confidencial. Todos os direitos reservados à INLAUDO.

## 👥 Equipe

- **Desenvolvimento:** INLAUDO Dev Team
- **Contato:** dev@inlaudo.com.br
- **Website:** https://erp.inlaudo.com.br

## 🔗 Links Úteis

- [Site Oficial](https://www.inlaudo.com.br)
- [Sistema ERP](https://erp.inlaudo.com.br)
- [Suporte](mailto:suporte@inlaudo.com.br)

---

**Desenvolvido com ❤️ pela equipe INLAUDO**
