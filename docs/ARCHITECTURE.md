# Arquitetura - ERP INLAUDO v2.0.0

## 📐 Visão Geral

O ERP INLAUDO foi reorganizado em uma arquitetura **modular, escalável e profissional** seguindo padrões de desenvolvimento web modernos.

---

## 🏗️ Padrões Utilizados

### 1. **MVC (Model-View-Controller)**
- **Models**: Lógica de dados e acesso ao banco
- **Views**: Apresentação e interface
- **Controllers**: Processamento de requisições (a implementar)

### 2. **Service Layer**
- Encapsula lógica de negócio
- Reutilizável entre controllers
- Facilita testes

### 3. **Repository Pattern**
- Abstração de acesso a dados
- Models herdam de classe base

### 4. **Dependency Injection**
- Injeção de dependências
- Facilita testes e manutenção

### 5. **PSR-4 Autoloading**
- Carregamento automático de classes
- Baseado em namespaces

---

## 📦 Componentes Principais

### Core (`src/core/`)

#### Bootstrap.php
```php
// Inicializa toda a aplicação
Bootstrap::init();
```
Responsabilidades:
- Carregar configurações
- Configurar error handling
- Inicializar sessão
- Conectar ao banco de dados

#### Autoloader.php
```php
// Carrega classes automaticamente
Autoloader::register();
```
- Implementa PSR-4
- Mapeia namespaces para diretórios
- Evita `require` manual

#### Database.php
```php
// Gerencia conexão com banco
$db = Database::getInstance();
$resultado = $db->fetchOne($sql, $params);
```
- Singleton pattern
- Prepared statements
- Transações ACID

#### Model.php
```php
// Classe base para todos os modelos
class ClienteModel extends Model {
    protected $table = 'clientes';
}
```
- CRUD básico
- Queries comuns
- Extensível

---

### Models (`src/models/`)

Representam entidades do banco de dados:

```php
namespace App\Models;

class ClienteModel extends Model {
    protected $table = 'clientes';
    
    public function getAtivos() {
        // Lógica específica do cliente
    }
}
```

**Métodos Disponíveis:**
- `all()` - Obter todos
- `find($id)` - Obter por ID
- `where($col, $op, $val)` - Filtrar
- `create($data)` - Criar
- `update($id, $data)` - Atualizar
- `delete($id)` - Deletar
- `count()` - Contar

---

### Services (`src/services/`)

Contêm lógica de negócio complexa:

```php
namespace App\Services;

class AlertaService {
    public function gerarAlertas() {
        // Lógica de geração de alertas
    }
}
```

**Características:**
- Independentes de HTTP
- Reutilizáveis
- Testáveis
- Encapsulam regras de negócio

---

### Controllers (`src/controllers/`)

Processam requisições HTTP (a implementar):

```php
namespace App\Controllers;

class ClienteController {
    private $clienteModel;
    
    public function __construct() {
        $this->clienteModel = new ClienteModel();
    }
    
    public function index() {
        $clientes = $this->clienteModel->all();
        // Renderizar view
    }
}
```

---

### Views (`src/views/`)

Apresentam dados:

```php
// src/views/clientes.php
<?php
$clienteModel = new ClienteModel();
$clientes = $clienteModel->all();
?>

<table>
    <?php foreach ($clientes as $cliente): ?>
        <tr>
            <td><?= htmlspecialchars($cliente['nome']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
```

---

## 🔄 Fluxo de Requisição

```
1. Usuário acessa URL
   ↓
2. public/index.php (ponto de entrada)
   ↓
3. Bootstrap::init() (inicializar)
   ↓
4. Roteamento (determinar página)
   ↓
5. Verificar autenticação
   ↓
6. Carregar view apropriada
   ↓
7. View usa Models/Services
   ↓
8. Renderizar HTML
   ↓
9. Enviar resposta ao usuário
```

---

## 💾 Banco de Dados

### Estrutura

```
clientes
├── id (PK)
├── nome
├── cnpj_cpf
├── email
├── ativo
└── ...

contas_receber
├── id (PK)
├── cliente_id (FK)
├── descricao
├── valor
├── data_vencimento
├── status
└── ...

alertas_contas_vencidas
├── id (PK)
├── conta_receber_id (FK)
├── usuario_id (FK)
├── tipo_alerta
├── titulo
├── descricao
├── visualizado
└── ...

logs_integracao
├── id (PK)
├── tipo_integracao
├── acao
├── dados
├── status
├── data_criacao
└── ...
```

### Migrations

Scripts em `database/migrations/`:
```bash
001_create_clientes_table.sql
002_create_contas_receber_table.sql
003_create_alertas_table.sql
```

---

## 🔐 Segurança

### Proteções Implementadas

1. **SQL Injection**
   - Prepared statements
   - Validação de entrada

2. **XSS (Cross-Site Scripting)**
   - `htmlspecialchars()` em outputs
   - Content Security Policy

3. **CSRF (Cross-Site Request Forgery)**
   - Tokens de sessão
   - SameSite cookies

4. **Authentication**
   - Sessões seguras
   - Password hashing (BCRYPT)

5. **Authorization**
   - Verificação de permissões
   - Controle de acesso

---

## 📊 Fluxo de Alertas

```
1. Usuário faz login
   ↓
2. Bootstrap::init() executa
   ↓
3. AlertaService::gerarAlertas()
   ↓
4. Busca contas vencidas/vencendo
   ↓
5. Cria registros em alertas_contas_vencidas
   ↓
6. View exibe modal com alertas
   ↓
7. Usuário interage (Ver/Cancelar/Ignorar)
   ↓
8. AJAX atualiza status
   ↓
9. Logs registram ações
```

---

## 🔌 Integração CORA

### Fluxo de Pagamento

```
1. Cliente clica "Pagar"
   ↓
2. cora_checkout.php processa
   ↓
3. Envia requisição para API CORA
   ↓
4. CORA retorna boleto/link
   ↓
5. Registra em logs_integracao
   ↓
6. Redireciona cliente
   ↓
7. Webhook CORA notifica pagamento
   ↓
8. webhook_cora.php processa
   ↓
9. Atualiza status de conta
   ↓
10. Registra em logs
```

---

## 📈 Performance

### Otimizações

1. **Índices no Banco**
   - Chaves primárias
   - Chaves estrangeiras
   - Índices em colunas frequentes

2. **Queries Otimizadas**
   - JOINs eficientes
   - Colunas específicas
   - LIMIT quando apropriado

3. **Cache**
   - Armazenado em `storage/cache/`
   - TTL configurável

4. **Lazy Loading**
   - Carregamento sob demanda
   - Paginação

---

## 🧪 Testes

### Estrutura

```
tests/
├── Unit/
│   ├── Models/
│   ├── Services/
│   └── Helpers/
└── Feature/
    ├── Controllers/
    └── Integration/
```

### Exemplo

```php
// tests/Unit/Models/ClienteModelTest.php
class ClienteModelTest extends TestCase {
    public function testFindById() {
        $cliente = new ClienteModel();
        $resultado = $cliente->find(1);
        
        $this->assertNotNull($resultado);
        $this->assertEquals(1, $resultado['id']);
    }
}
```

---

## 📚 Extensibilidade

### Adicionar Novo Model

```php
// src/models/NovoModel.php
namespace App\Models;

class NovoModel extends Model {
    protected $table = 'nova_tabela';
    
    public function metodoCustomizado() {
        // Lógica específica
    }
}
```

### Adicionar Novo Service

```php
// src/services/NovoService.php
namespace App\Services;

class NovoService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
}
```

### Adicionar Nova View

```php
// src/views/nova-pagina.php
<?php
// Lógica e apresentação
?>
```

---

## 🚀 Deploy

### Estrutura de Pastas no Servidor

```
/home/usuario/public_html/
├── index.php (link simbólico para public/index.php)
├── .env
├── src/
├── config/
├── database/
├── storage/
└── public/
    ├── css/
    ├── js/
    └── images/
```

### Configuração Apache

```apache
<Directory /home/usuario/public_html>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
</Directory>
```

---

## 📝 Convenções

### Nomes de Arquivos
- Controllers: `NomeController.php`
- Models: `NomeModel.php`
- Services: `NomeService.php`

### Nomes de Classes
- PascalCase: `ClienteController`
- Métodos: camelCase: `getAtivos()`
- Propriedades: camelCase: `$userId`

### Nomes de Banco
- Tabelas: snake_case: `contas_receber`
- Colunas: snake_case: `data_vencimento`

---

## 🔗 Referências

- [PSR-4 Autoloading](https://www.php-fig.org/psr/psr-4/)
- [MVC Pattern](https://en.wikipedia.org/wiki/Model%E2%80%93view%E2%80%93controller)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)

---

**Versão**: 2.0.0  
**Data**: 06/01/2026  
**Desenvolvedor**: Manus AI
