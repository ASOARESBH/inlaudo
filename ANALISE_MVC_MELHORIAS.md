# Análise MVC e Sugestões de Melhorias - ERP INLAUDO

## Desenvolvedor: Análise Sênior de Arquitetura
**Data:** 09 de Janeiro de 2026  
**Sistema:** ERP INLAUDO (https://erp.inlaudo.com.br/)  
**Ambiente:** Hostgator + MySQL (MariaDB)

---

## 1. RESUMO EXECUTIVO

O sistema **ERP INLAUDO** apresenta uma estrutura **híbrida** entre o padrão MVC tradicional e uma arquitetura procedural legada. A análise identificou **174 arquivos PHP** organizados parcialmente em camadas, com uma estrutura `src/` contendo Controllers, Models e Services, mas com a maioria das páginas ainda no padrão procedural na raiz do projeto.

### Pontos Positivos Identificados
- Estrutura `src/` já implementada com namespaces PSR-4
- Separação clara entre Controllers, Models e Services nas integrações
- Uso de PDO com prepared statements (segurança)
- Configuração centralizada em `config/Config.php`
- Documentação técnica presente (README, INSTALACAO.md)

### Principais Desafios
- **174 arquivos PHP** na raiz do projeto (padrão procedural)
- Ausência de autoloader (Composer não configurado)
- Falta de um Front Controller (index.php não funciona como router)
- Mistura de lógica de negócio com apresentação
- Credenciais de banco de dados expostas em `config.php`

---

## 2. ESTRUTURA ATUAL DO PROJETO

```
newCRM/
├── src/
│   ├── controllers/          # ✅ Controllers (2 arquivos)
│   │   ├── AsaasController.php
│   │   └── NotaFiscalController.php
│   ├── models/               # ✅ Models (5 arquivos)
│   │   ├── AsaasModel.php
│   │   ├── ClienteModel.php
│   │   ├── ContaReceberModel.php
│   │   ├── FornecedorModel.php
│   │   └── NotaFiscalModel.php
│   ├── services/             # ✅ Services (3 arquivos)
│   │   ├── AlertaService.php
│   │   ├── AsaasService.php
│   │   └── NotaFiscalXmlService.php
│   ├── core/                 # Estrutura básica
│   └── views/                # Views organizadas
│       ├── configuracao/
│       └── layouts/
├── config/
│   └── Config.php            # ✅ Configuração centralizada
├── public/                   # ✅ Assets públicos
│   ├── css/
│   └── js/
├── api/                      # APIs REST
│   └── clientes/
├── database/
│   ├── backups/
│   └── sql/migrations/
├── logs/                     # Logs do sistema
├── docs/                     # Documentação
├── index.php                 # ⚠️ Dashboard (não é Front Controller)
├── config.php                # ⚠️ Config legado (credenciais expostas)
├── clientes.php              # ⚠️ Padrão procedural
├── cliente_form.php          # ⚠️ Padrão procedural
├── boletos.php               # ⚠️ Padrão procedural
└── [+170 arquivos PHP]       # ⚠️ Todos procedurais na raiz
```

### Análise da Estrutura

O projeto apresenta uma **transição incompleta** do padrão procedural para MVC. A estrutura `src/` foi criada para novas funcionalidades (integração Asaas, Notas Fiscais), mas o core do sistema permanece procedural.

---

## 3. PROBLEMAS IDENTIFICADOS NA ARQUITETURA ATUAL

### 3.1 Violações do Padrão MVC

#### ❌ **Problema 1: Lógica de Negócio nas Views**

**Exemplo:** `clientes.php` (linhas 23-47)
```php
// Buscar clientes
try {
    $conn = getConnection();
    $sql = "SELECT * FROM clientes WHERE 1=1";
    $params = [];
    
    if (!empty($busca)) {
        $sql .= " AND (nome LIKE ? OR razao_social LIKE ? ...)";
        $buscaParam = "%$busca%";
        $params = array_fill(0, 5, $buscaParam);
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

**Impacto:**
- Código duplicado em múltiplas páginas
- Dificulta testes unitários
- Viola o princípio de responsabilidade única

#### ❌ **Problema 2: Ausência de Front Controller**

O arquivo `index.php` é um dashboard específico, não um router central:

```php
// index.php (linha 10-16)
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
// ... código do dashboard
```

**Impacto:**
- Cada página precisa implementar autenticação
- URLs não amigáveis (clientes.php, boletos.php)
- Impossível implementar middlewares globais

#### ❌ **Problema 3: Configuração Duplicada**

Existem **dois arquivos de configuração**:

1. **`config.php`** (raiz) - Usado pelas páginas procedurais
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'inlaud99_erpinlaudo');
define('DB_USER', 'inlaud99_admin');
define('DB_PASS', 'Admin259087@');  // ⚠️ Credencial exposta
```

2. **`config/Config.php`** - Usado pelos Controllers
```php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'erpinlaudo');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
```

**Impacto:**
- Manutenção duplicada
- Risco de inconsistência
- Credenciais versionadas no Git

#### ❌ **Problema 4: Falta de Autoloader**

O arquivo `api_asaas_routes.php` tenta usar autoloader inexistente:

```php
require_once __DIR__ . '/vendor/autoload.php';  // ❌ Arquivo não existe

use App\Controllers\AsaasController;
```

**Impacto:**
- Classes não podem ser carregadas automaticamente
- Necessidade de múltiplos `require_once`
- Dificulta organização de namespaces

#### ❌ **Problema 5: Mistura de Responsabilidades nos Controllers**

**Exemplo:** `AsaasController.php` (linhas 107-119)
```php
http_response_code(200);
echo json_encode([
    'success' => true,
    'customer_id' => $customer['id'],
    'message' => 'Cliente processado com sucesso'
]);
```

**Impacto:**
- Controller responsável por renderização de resposta
- Dificulta reutilização de lógica
- Viola separação de concerns

### 3.2 Problemas de Segurança

1. **Credenciais hardcoded** em `config.php`
2. **Session management** implementado em cada página
3. **CORS aberto** em `api_asaas_routes.php` (`Access-Control-Allow-Origin: *`)
4. **Error display** habilitado em produção (debug mode)

### 3.3 Problemas de Manutenibilidade

1. **Código duplicado** em 174 arquivos procedurais
2. **Falta de testes automatizados**
3. **Documentação inline inconsistente**
4. **Logs não estruturados**

---

## 4. SUGESTÕES DE MELHORIAS (SEM IMPACTAR PRODUÇÃO)

### Estratégia: **Migração Incremental Paralela**

A abordagem recomendada é implementar melhorias **sem modificar** o código em produção, criando uma estrutura paralela que pode ser ativada gradualmente.

---

### 4.1 FASE 1: Fundação (Prioridade ALTA)

#### ✅ **Melhoria 1: Implementar Composer e Autoloader**

**Arquivo:** `composer.json`

```json
{
    "name": "inlaudo/erp",
    "description": "ERP INLAUDO - Sistema de Gestão",
    "type": "project",
    "require": {
        "php": ">=7.4",
        "ext-pdo": "*",
        "ext-json": "*"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        },
        "files": [
            "src/helpers.php"
        ]
    },
    "require-dev": {
        "phpunit/phpunit": "^9.5"
    }
}
```

**Comandos:**
```bash
cd /home/inlaud99/public_html/
composer install
```

**Impacto:** ZERO (não afeta código existente)

---

#### ✅ **Melhoria 2: Criar Arquivo .env para Credenciais**

**Arquivo:** `.env` (adicionar ao `.gitignore`)

```env
# Banco de Dados
DB_HOST=localhost
DB_NAME=inlaud99_erpinlaudo
DB_USER=inlaud99_admin
DB_PASS=Admin259087@
DB_CHARSET=utf8mb4

# Sistema
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.inlaudo.com.br

# Segurança
APP_KEY=base64:GERAR_CHAVE_ALEATORIA_32_BYTES

# Email
MAIL_HOST=smtp.hostgator.com
MAIL_PORT=465
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM=noreply@inlaudo.com.br
```

**Arquivo:** `config/Database.php` (novo)

```php
<?php
namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                getenv('DB_HOST') ?: 'localhost',
                getenv('DB_NAME') ?: 'erpinlaudo',
                getenv('DB_CHARSET') ?: 'utf8mb4'
            );

            $this->connection = new PDO(
                $dsn,
                getenv('DB_USER'),
                getenv('DB_PASS'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false
                ]
            );
        } catch (PDOException $e) {
            error_log('[DATABASE] Connection failed: ' . $e->getMessage());
            throw new \Exception('Erro ao conectar ao banco de dados');
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    // Prevenir clonagem
    private function __clone() {}

    // Prevenir unserialize
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
```

**Impacto:** ZERO (código legado continua usando `config.php`)

---

#### ✅ **Melhoria 3: Criar Bootstrap Unificado**

**Arquivo:** `bootstrap.php` (novo)

```php
<?php
/**
 * Bootstrap do Sistema
 * 
 * Carrega dependências e configurações iniciais
 */

// Carregar autoloader do Composer
require_once __DIR__ . '/vendor/autoload.php';

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Configurar timezone
date_default_timezone_set(getenv('TIMEZONE') ?: 'America/Sao_Paulo');

// Configurar error reporting
if (getenv('APP_ENV') === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Iniciar sessão se não iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Registrar helper de compatibilidade
require_once __DIR__ . '/src/helpers.php';
```

**Impacto:** ZERO (arquivo opcional)

---

### 4.2 FASE 2: Camada de Abstração (Prioridade ALTA)

#### ✅ **Melhoria 4: Criar Base Controller**

**Arquivo:** `src/Core/Controller.php` (novo)

```php
<?php
namespace App\Core;

use App\Config\Database;
use PDO;

abstract class Controller
{
    protected $db;
    protected $pdo;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->pdo = $this->db->getConnection();
    }

    /**
     * Renderizar view
     */
    protected function view(string $view, array $data = []): void
    {
        extract($data);
        
        $viewFile = __DIR__ . "/../views/{$view}.php";
        
        if (!file_exists($viewFile)) {
            throw new \Exception("View não encontrada: {$view}");
        }
        
        require_once $viewFile;
    }

    /**
     * Retornar JSON
     */
    protected function json($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Redirecionar
     */
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Verificar autenticação
     */
    protected function requireAuth(): void
    {
        if (!isset($_SESSION['usuario_id'])) {
            $this->redirect('/login.php');
        }
    }

    /**
     * Obter usuário logado
     */
    protected function getUser(): ?array
    {
        if (!isset($_SESSION['usuario_id'])) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['usuario_id']]);
        return $stmt->fetch();
    }
}
```

**Impacto:** ZERO (classe base para novos controllers)

---

#### ✅ **Melhoria 5: Criar Base Model**

**Arquivo:** `src/Core/Model.php` (novo)

```php
<?php
namespace App\Core;

use App\Config\Database;
use PDO;

abstract class Model
{
    protected $pdo;
    protected $table;
    protected $primaryKey = 'id';

    public function __construct()
    {
        $db = Database::getInstance();
        $this->pdo = $db->getConnection();
    }

    /**
     * Buscar todos os registros
     */
    public function all(array $conditions = [], string $orderBy = null): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $field => $value) {
                $where[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Buscar por ID
     */
    public function find($id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Buscar um registro por condições
     */
    public function findOne(array $conditions): ?array
    {
        $where = [];
        $params = [];

        foreach ($conditions as $field => $value) {
            $where[] = "{$field} = ?";
            $params[] = $value;
        }

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . " LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Inserir registro
     */
    public function insert(array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atualizar registro
     */
    public function update($id, array $data): bool
    {
        $set = [];
        $params = [];

        foreach ($data as $field => $value) {
            $set[] = "{$field} = ?";
            $params[] = $value;
        }
        $params[] = $id;

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = ?",
            $this->table,
            implode(', ', $set),
            $this->primaryKey
        );

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Deletar registro
     */
    public function delete($id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Contar registros
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $field => $value) {
                $where[] = "{$field} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int) ($result['total'] ?? 0);
    }
}
```

**Impacto:** ZERO (classe base para novos models)

---

#### ✅ **Melhoria 6: Refatorar ClienteModel para Usar Base Model**

**Arquivo:** `src/Models/ClienteModel.php` (refatorado)

```php
<?php
namespace App\Models;

use App\Core\Model;

class ClienteModel extends Model
{
    protected $table = 'clientes';
    protected $primaryKey = 'id';

    /**
     * Buscar clientes com filtros
     */
    public function search(string $busca = '', string $tipo = ''): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if (!empty($busca)) {
            $sql .= " AND (nome LIKE ? OR razao_social LIKE ? OR nome_fantasia LIKE ? OR cnpj_cpf LIKE ? OR email LIKE ?)";
            $buscaParam = "%{$busca}%";
            $params = array_fill(0, 5, $buscaParam);
        }

        if (!empty($tipo)) {
            $sql .= " AND tipo_cliente = ?";
            $params[] = $tipo;
        }

        $sql .= " ORDER BY data_cadastro DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Buscar por CNPJ/CPF
     */
    public function findByCnpjCpf(string $cnpjCpf): ?array
    {
        $cnpjCpf = preg_replace('/\D/', '', $cnpjCpf);
        return $this->findOne(['cnpj_cpf' => $cnpjCpf]);
    }

    /**
     * Buscar clientes ativos
     */
    public function getAtivos(): array
    {
        return $this->all(['status' => 'ativo'], 'nome ASC');
    }

    /**
     * Buscar apenas clientes (não leads)
     */
    public function getClientes(): array
    {
        return $this->all(['tipo_cliente' => 'CLIENTE'], 'nome ASC');
    }

    /**
     * Buscar apenas leads
     */
    public function getLeads(): array
    {
        return $this->all(['tipo_cliente' => 'LEAD'], 'nome ASC');
    }

    /**
     * Contar clientes por tipo
     */
    public function countByTipo(string $tipo): int
    {
        return $this->count(['tipo_cliente' => $tipo]);
    }
}
```

**Impacto:** ZERO (arquivo novo, não substitui o existente)

---

### 4.3 FASE 3: Criar Novos Controllers (Prioridade MÉDIA)

#### ✅ **Melhoria 7: Criar ClienteController**

**Arquivo:** `src/Controllers/ClienteController.php` (novo)

```php
<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\ClienteModel;
use Exception;

class ClienteController extends Controller
{
    private $clienteModel;

    public function __construct()
    {
        parent::__construct();
        $this->clienteModel = new ClienteModel();
    }

    /**
     * Listar clientes
     */
    public function index(): void
    {
        $this->requireAuth();

        $busca = $_GET['busca'] ?? '';
        $tipo = $_GET['tipo'] ?? '';

        try {
            $clientes = $this->clienteModel->search($busca, $tipo);

            $this->view('clientes/index', [
                'pageTitle' => 'Clientes',
                'clientes' => $clientes,
                'busca' => $busca,
                'tipo' => $tipo
            ]);
        } catch (Exception $e) {
            error_log('[CLIENTES] Erro ao listar: ' . $e->getMessage());
            $this->view('clientes/index', [
                'pageTitle' => 'Clientes',
                'clientes' => [],
                'error' => 'Erro ao carregar clientes'
            ]);
        }
    }

    /**
     * Exibir formulário de criação
     */
    public function create(): void
    {
        $this->requireAuth();

        $this->view('clientes/form', [
            'pageTitle' => 'Novo Cliente',
            'cliente' => null
        ]);
    }

    /**
     * Salvar novo cliente
     */
    public function store(): void
    {
        $this->requireAuth();

        try {
            $data = [
                'nome' => $_POST['nome'] ?? '',
                'razao_social' => $_POST['razao_social'] ?? '',
                'nome_fantasia' => $_POST['nome_fantasia'] ?? '',
                'cnpj_cpf' => preg_replace('/\D/', '', $_POST['cnpj_cpf'] ?? ''),
                'email' => $_POST['email'] ?? '',
                'telefone' => $_POST['telefone'] ?? '',
                'tipo_cliente' => $_POST['tipo_cliente'] ?? 'CLIENTE',
                'status' => 'ativo',
                'data_cadastro' => date('Y-m-d H:i:s')
            ];

            // Validações
            if (empty($data['nome'])) {
                throw new Exception('Nome é obrigatório');
            }

            if (empty($data['cnpj_cpf'])) {
                throw new Exception('CNPJ/CPF é obrigatório');
            }

            // Verificar duplicidade
            $existe = $this->clienteModel->findByCnpjCpf($data['cnpj_cpf']);
            if ($existe) {
                throw new Exception('CNPJ/CPF já cadastrado');
            }

            $id = $this->clienteModel->insert($data);

            $_SESSION['success'] = 'Cliente cadastrado com sucesso!';
            $this->redirect("/cliente_dados.php?id={$id}");

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/cliente_form.php');
        }
    }

    /**
     * Exibir detalhes do cliente
     */
    public function show(int $id): void
    {
        $this->requireAuth();

        try {
            $cliente = $this->clienteModel->find($id);

            if (!$cliente) {
                throw new Exception('Cliente não encontrado');
            }

            $this->view('clientes/show', [
                'pageTitle' => $cliente['nome'],
                'cliente' => $cliente
            ]);

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/clientes.php');
        }
    }

    /**
     * Exibir formulário de edição
     */
    public function edit(int $id): void
    {
        $this->requireAuth();

        try {
            $cliente = $this->clienteModel->find($id);

            if (!$cliente) {
                throw new Exception('Cliente não encontrado');
            }

            $this->view('clientes/form', [
                'pageTitle' => 'Editar Cliente',
                'cliente' => $cliente
            ]);

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/clientes.php');
        }
    }

    /**
     * Atualizar cliente
     */
    public function update(int $id): void
    {
        $this->requireAuth();

        try {
            $cliente = $this->clienteModel->find($id);

            if (!$cliente) {
                throw new Exception('Cliente não encontrado');
            }

            $data = [
                'nome' => $_POST['nome'] ?? '',
                'razao_social' => $_POST['razao_social'] ?? '',
                'nome_fantasia' => $_POST['nome_fantasia'] ?? '',
                'email' => $_POST['email'] ?? '',
                'telefone' => $_POST['telefone'] ?? '',
                'tipo_cliente' => $_POST['tipo_cliente'] ?? 'CLIENTE'
            ];

            // Validações
            if (empty($data['nome'])) {
                throw new Exception('Nome é obrigatório');
            }

            $this->clienteModel->update($id, $data);

            $_SESSION['success'] = 'Cliente atualizado com sucesso!';
            $this->redirect("/cliente_dados.php?id={$id}");

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect("/cliente_form.php?id={$id}");
        }
    }

    /**
     * Deletar cliente
     */
    public function destroy(int $id): void
    {
        $this->requireAuth();

        try {
            $cliente = $this->clienteModel->find($id);

            if (!$cliente) {
                throw new Exception('Cliente não encontrado');
            }

            $this->clienteModel->delete($id);

            $_SESSION['success'] = 'Cliente excluído com sucesso!';
            $this->redirect('/clientes.php');

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/clientes.php');
        }
    }

    /**
     * API: Listar clientes (JSON)
     */
    public function apiList(): void
    {
        $this->requireAuth();

        try {
            $busca = $_GET['busca'] ?? '';
            $tipo = $_GET['tipo'] ?? '';

            $clientes = $this->clienteModel->search($busca, $tipo);

            $this->json([
                'success' => true,
                'data' => $clientes,
                'total' => count($clientes)
            ]);

        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
```

**Impacto:** ZERO (controller novo para uso futuro)

---

### 4.4 FASE 4: Router e Front Controller (Prioridade MÉDIA)

#### ✅ **Melhoria 8: Criar Sistema de Rotas**

**Arquivo:** `src/Core/Router.php` (novo)

```php
<?php
namespace App\Core;

class Router
{
    private $routes = [];
    private $notFoundCallback;

    /**
     * Adicionar rota GET
     */
    public function get(string $path, $callback): void
    {
        $this->addRoute('GET', $path, $callback);
    }

    /**
     * Adicionar rota POST
     */
    public function post(string $path, $callback): void
    {
        $this->addRoute('POST', $path, $callback);
    }

    /**
     * Adicionar rota PUT
     */
    public function put(string $path, $callback): void
    {
        $this->addRoute('PUT', $path, $callback);
    }

    /**
     * Adicionar rota DELETE
     */
    public function delete(string $path, $callback): void
    {
        $this->addRoute('DELETE', $path, $callback);
    }

    /**
     * Adicionar rota genérica
     */
    private function addRoute(string $method, string $path, $callback): void
    {
        $pattern = $this->convertPathToRegex($path);
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'callback' => $callback
        ];
    }

    /**
     * Converter path para regex
     */
    private function convertPathToRegex(string $path): string
    {
        // Converter {id} para (\d+), {slug} para ([a-z0-9-]+)
        $pattern = preg_replace('/\{id\}/', '(\d+)', $path);
        $pattern = preg_replace('/\{([a-z]+)\}/', '([a-zA-Z0-9-_]+)', $pattern);
        return '#^' . $pattern . '$#';
    }

    /**
     * Definir callback para 404
     */
    public function notFound($callback): void
    {
        $this->notFoundCallback = $callback;
    }

    /**
     * Executar roteamento
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Remover trailing slash
        $uri = rtrim($uri, '/');
        if (empty($uri)) {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches); // Remover match completo
                
                $callback = $route['callback'];

                // Se callback é string "Controller@method"
                if (is_string($callback) && strpos($callback, '@') !== false) {
                    [$controllerName, $methodName] = explode('@', $callback);
                    $controllerClass = "App\\Controllers\\{$controllerName}";
                    
                    if (!class_exists($controllerClass)) {
                        throw new \Exception("Controller não encontrado: {$controllerClass}");
                    }

                    $controller = new $controllerClass();
                    
                    if (!method_exists($controller, $methodName)) {
                        throw new \Exception("Método não encontrado: {$methodName}");
                    }

                    call_user_func_array([$controller, $methodName], $matches);
                    return;
                }

                // Se callback é closure
                if (is_callable($callback)) {
                    call_user_func_array($callback, $matches);
                    return;
                }
            }
        }

        // Nenhuma rota encontrada
        if ($this->notFoundCallback) {
            call_user_func($this->notFoundCallback);
        } else {
            http_response_code(404);
            echo "404 - Página não encontrada";
        }
    }
}
```

**Arquivo:** `routes/web.php` (novo)

```php
<?php
/**
 * Rotas Web
 * 
 * Define todas as rotas do sistema
 */

use App\Core\Router;

$router = new Router();

// ============================================================
// ROTAS PÚBLICAS
// ============================================================
$router->get('/', function() {
    require_once __DIR__ . '/../index.php';
});

$router->get('/login', function() {
    require_once __DIR__ . '/../login.php';
});

// ============================================================
// ROTAS DE CLIENTES
// ============================================================
$router->get('/clientes', 'ClienteController@index');
$router->get('/clientes/novo', 'ClienteController@create');
$router->post('/clientes', 'ClienteController@store');
$router->get('/clientes/{id}', 'ClienteController@show');
$router->get('/clientes/{id}/editar', 'ClienteController@edit');
$router->post('/clientes/{id}', 'ClienteController@update');
$router->post('/clientes/{id}/deletar', 'ClienteController@destroy');

// ============================================================
// API CLIENTES
// ============================================================
$router->get('/api/clientes', 'ClienteController@apiList');

// ============================================================
// ROTAS DE ASAAS
// ============================================================
$router->post('/api/asaas/customers', 'AsaasController@findOrCreateCustomer');
$router->post('/api/asaas/payments', 'AsaasController@createPayment');
$router->get('/api/asaas/payments/{id}', 'AsaasController@getPaymentStatus');

// ============================================================
// 404
// ============================================================
$router->notFound(function() {
    http_response_code(404);
    require_once __DIR__ . '/../404.php';
});

return $router;
```

**Arquivo:** `public/index.php` (novo Front Controller)

```php
<?php
/**
 * Front Controller
 * 
 * Ponto de entrada único do sistema
 */

require_once __DIR__ . '/../bootstrap.php';

try {
    $router = require_once __DIR__ . '/../routes/web.php';
    $router->dispatch();
} catch (Exception $e) {
    error_log('[ROUTER] Erro: ' . $e->getMessage());
    
    if (getenv('APP_ENV') === 'production') {
        http_response_code(500);
        echo "Erro interno do servidor";
    } else {
        echo "<pre>";
        echo "Erro: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString();
        echo "</pre>";
    }
}
```

**Impacto:** ZERO (estrutura paralela, não afeta código existente)

---

### 4.5 FASE 5: Middleware e Segurança (Prioridade MÉDIA)

#### ✅ **Melhoria 9: Sistema de Middleware**

**Arquivo:** `src/Core/Middleware.php` (novo)

```php
<?php
namespace App\Core;

interface Middleware
{
    public function handle(callable $next);
}
```

**Arquivo:** `src/Middleware/AuthMiddleware.php` (novo)

```php
<?php
namespace App\Middleware;

use App\Core\Middleware;

class AuthMiddleware implements Middleware
{
    public function handle(callable $next)
    {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: /login.php');
            exit;
        }

        return $next();
    }
}
```

**Arquivo:** `src/Middleware/CorsMiddleware.php` (novo)

```php
<?php
namespace App\Middleware;

use App\Core\Middleware;

class CorsMiddleware implements Middleware
{
    public function handle(callable $next)
    {
        // Configurar CORS de forma segura
        $allowedOrigins = [
            'https://erp.inlaudo.com.br',
            'https://www.inlaudo.com.br'
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: {$origin}");
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        return $next();
    }
}
```

**Impacto:** ZERO (middlewares para uso futuro)

---

### 4.6 FASE 6: Helpers e Utilitários (Prioridade BAIXA)

#### ✅ **Melhoria 10: Arquivo de Helpers**

**Arquivo:** `src/helpers.php` (novo)

```php
<?php
/**
 * Funções Helper Globais
 */

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
            default:
                return $value;
        }
    }
}

if (!function_exists('sanitize')) {
    function sanitize($data): string
    {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatMoeda')) {
    function formatMoeda($valor): string
    {
        return 'R$ ' . number_format((float)$valor, 2, ',', '.');
    }
}

if (!function_exists('formatData')) {
    function formatData($data): string
    {
        if (!$data) return '';
        try {
            return (new DateTime($data))->format('d/m/Y');
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('formatDataHora')) {
    function formatDataHora($data): string
    {
        if (!$data) return '';
        try {
            return (new DateTime($data))->format('d/m/Y H:i');
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('dataBRtoMySQL')) {
    function dataBRtoMySQL($data): ?string
    {
        if (!$data) return null;
        $parts = explode('/', $data);
        if (count($parts) !== 3) return null;
        return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
    }
}

if (!function_exists('formatCNPJ')) {
    function formatCNPJ($cnpj): string
    {
        if (empty($cnpj)) return '';
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        if (strlen($cnpj) != 14) return $cnpj;
        return substr($cnpj, 0, 2) . '.' . 
               substr($cnpj, 2, 3) . '.' . 
               substr($cnpj, 5, 3) . '/' . 
               substr($cnpj, 8, 4) . '-' . 
               substr($cnpj, 12, 2);
    }
}

if (!function_exists('formatCPF')) {
    function formatCPF($cpf): string
    {
        if (empty($cpf)) return '';
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) != 11) return $cpf;
        return substr($cpf, 0, 3) . '.' . 
               substr($cpf, 3, 3) . '.' . 
               substr($cpf, 6, 3) . '-' . 
               substr($cpf, 9, 2);
    }
}

if (!function_exists('dd')) {
    function dd(...$vars): void
    {
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
        }
        exit;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return rtrim(env('APP_URL', ''), '/') . '/public/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return rtrim(env('APP_URL', ''), '/') . '/' . ltrim($path, '/');
    }
}
```

**Impacto:** ZERO (helpers para uso futuro)

---

## 5. PLANO DE IMPLEMENTAÇÃO GRADUAL

### Estratégia de Migração Sem Downtime

A implementação deve seguir uma abordagem **paralela e incremental**:

#### **Etapa 1: Preparação (Semana 1)**
1. Instalar Composer
2. Criar arquivo `.env` (não versionar)
3. Adicionar `.env` ao `.gitignore`
4. Criar estrutura `src/Core/`
5. Criar `bootstrap.php`

**Risco:** ZERO - Nenhum código em produção é alterado

#### **Etapa 2: Base Classes (Semana 2)**
1. Criar `src/Core/Controller.php`
2. Criar `src/Core/Model.php`
3. Criar `src/Config/Database.php`
4. Refatorar `ClienteModel` para usar `Model` base

**Risco:** ZERO - Classes novas, não substituem existentes

#### **Etapa 3: Primeiro Controller (Semana 3)**
1. Criar `ClienteController`
2. Criar views em `src/views/clientes/`
3. Testar em ambiente de desenvolvimento

**Risco:** ZERO - Controller paralelo ao código existente

#### **Etapa 4: Router (Semana 4)**
1. Criar `src/Core/Router.php`
2. Criar `routes/web.php`
3. Criar `public/index.php` (Front Controller)
4. Configurar `.htaccess` para redirecionar para `public/`

**Risco:** BAIXO - Requer configuração de servidor

#### **Etapa 5: Migração Gradual (Semanas 5-12)**
1. Migrar uma página por semana
2. Testar em staging antes de produção
3. Manter código legado funcionando em paralelo
4. Redirecionar URLs antigas para novas rotas

**Risco:** MÉDIO - Requer testes extensivos

#### **Etapa 6: Deprecação (Semanas 13-16)**
1. Adicionar avisos de deprecação no código legado
2. Monitorar uso de páginas antigas
3. Remover código legado não utilizado
4. Documentar mudanças

**Risco:** BAIXO - Código já testado em produção

---

## 6. CONFIGURAÇÃO DO .HTACCESS PARA COEXISTÊNCIA

Para permitir que o novo sistema MVC coexista com o código legado:

**Arquivo:** `.htaccess` (atualizado)

```apache
# Configurações do Apache para ERP INLAUDO
RewriteEngine On

# Redirecionar para HTTPS (se disponível)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# ============================================================
# ROTAS NOVAS (MVC)
# ============================================================

# Se a rota começa com /app/, /api/, ou /clientes/ (novas rotas)
RewriteCond %{REQUEST_URI} ^/(app|api/v2|mvc)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ public/index.php [QSA,L]

# ============================================================
# ROTAS LEGADAS (Procedural)
# ============================================================

# Se o arquivo existe, servir diretamente
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# Se o diretório existe, servir diretamente
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# ============================================================
# SEGURANÇA
# ============================================================

# Proteção contra listagem de diretórios
Options -Indexes

# Proteção de arquivos sensíveis
<FilesMatch "\.(htaccess|htpasswd|ini|log|sh|sql|env)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# ============================================================
# PERFORMANCE
# ============================================================

# Habilitar compressão GZIP
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>

# Cache de arquivos estáticos
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>

# Charset padrão
AddDefaultCharset UTF-8
```

**Impacto:** BAIXO - Permite coexistência de ambos os sistemas

---

## 7. ESTRUTURA FINAL PROPOSTA

Após implementação completa:

```
erp-inlaudo/
├── public/                      # ✅ Novo - Ponto de entrada público
│   ├── index.php               # Front Controller
│   ├── css/
│   ├── js/
│   └── assets/
├── src/                         # ✅ Melhorado - Código MVC
│   ├── Core/
│   │   ├── Controller.php
│   │   ├── Model.php
│   │   ├── Router.php
│   │   └── Middleware.php
│   ├── Config/
│   │   └── Database.php
│   ├── Controllers/
│   │   ├── ClienteController.php
│   │   ├── AsaasController.php
│   │   └── NotaFiscalController.php
│   ├── Models/
│   │   ├── ClienteModel.php
│   │   ├── AsaasModel.php
│   │   └── NotaFiscalModel.php
│   ├── Services/
│   │   ├── AlertaService.php
│   │   └── AsaasService.php
│   ├── Middleware/
│   │   ├── AuthMiddleware.php
│   │   └── CorsMiddleware.php
│   ├── Views/
│   │   ├── layouts/
│   │   ├── clientes/
│   │   └── dashboard/
│   └── helpers.php
├── routes/                      # ✅ Novo - Definição de rotas
│   ├── web.php
│   └── api.php
├── config/                      # ✅ Mantido
│   └── Config.php
├── database/                    # ✅ Mantido
│   ├── migrations/
│   └── seeds/
├── storage/                     # ✅ Novo - Arquivos gerados
│   ├── logs/
│   ├── cache/
│   └── uploads/
├── tests/                       # ✅ Novo - Testes automatizados
│   ├── Unit/
│   └── Feature/
├── vendor/                      # ✅ Novo - Dependências Composer
├── legacy/                      # ⚠️ Código legado (migrar gradualmente)
│   ├── clientes.php
│   ├── boletos.php
│   └── [outros arquivos]
├── .env                         # ✅ Novo - Variáveis de ambiente (não versionar)
├── .env.example                 # ✅ Novo - Template de .env
├── .gitignore                   # ✅ Atualizado
├── composer.json                # ✅ Novo
├── bootstrap.php                # ✅ Novo
├── README.md                    # ✅ Mantido
└── .htaccess                    # ✅ Atualizado
```

---

## 8. CHECKLIST DE IMPLEMENTAÇÃO

### Fase 1: Fundação
- [ ] Criar `composer.json`
- [ ] Executar `composer install`
- [ ] Criar arquivo `.env`
- [ ] Adicionar `.env` ao `.gitignore`
- [ ] Criar `bootstrap.php`
- [ ] Testar autoloader

### Fase 2: Base Classes
- [ ] Criar `src/Core/Controller.php`
- [ ] Criar `src/Core/Model.php`
- [ ] Criar `src/Config/Database.php`
- [ ] Criar `src/helpers.php`
- [ ] Testar conexão com banco via `Database`

### Fase 3: Primeiro Controller
- [ ] Refatorar `ClienteModel`
- [ ] Criar `ClienteController`
- [ ] Criar views em `src/views/clientes/`
- [ ] Testar CRUD completo

### Fase 4: Router
- [ ] Criar `src/Core/Router.php`
- [ ] Criar `routes/web.php`
- [ ] Criar `public/index.php`
- [ ] Atualizar `.htaccess`
- [ ] Testar roteamento

### Fase 5: Middleware
- [ ] Criar `src/Core/Middleware.php`
- [ ] Criar `AuthMiddleware`
- [ ] Criar `CorsMiddleware`
- [ ] Integrar middlewares ao Router

### Fase 6: Migração Gradual
- [ ] Migrar página de clientes
- [ ] Migrar página de boletos
- [ ] Migrar página de contas a receber
- [ ] Migrar página de contas a pagar
- [ ] Migrar dashboard
- [ ] Migrar demais páginas

### Fase 7: Testes
- [ ] Configurar PHPUnit
- [ ] Criar testes unitários para Models
- [ ] Criar testes de integração para Controllers
- [ ] Criar testes de API

### Fase 8: Documentação
- [ ] Atualizar README.md
- [ ] Documentar arquitetura
- [ ] Documentar APIs
- [ ] Criar guia de contribuição

---

## 9. BENEFÍCIOS DA REFATORAÇÃO

### Técnicos
1. **Manutenibilidade:** Código organizado e reutilizável
2. **Testabilidade:** Separação de concerns facilita testes
3. **Escalabilidade:** Estrutura preparada para crescimento
4. **Segurança:** Credenciais protegidas, validações centralizadas
5. **Performance:** Autoloader otimizado, cache de rotas

### Negócio
1. **Redução de bugs:** Código mais limpo e testado
2. **Velocidade de desenvolvimento:** Reutilização de componentes
3. **Facilidade de onboarding:** Estrutura padronizada
4. **Menor custo de manutenção:** Menos código duplicado
5. **Melhor experiência do usuário:** URLs amigáveis, performance

---

## 10. RISCOS E MITIGAÇÕES

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| Quebra de funcionalidades existentes | Baixa | Alto | Implementação paralela, testes extensivos |
| Downtime durante migração | Baixa | Alto | Migração gradual, rollback preparado |
| Incompatibilidade com Hostgator | Média | Médio | Testar em staging, verificar versão PHP |
| Resistência da equipe | Média | Baixo | Treinamento, documentação clara |
| Aumento de complexidade inicial | Alta | Baixo | Documentação, exemplos práticos |

---

## 11. RECOMENDAÇÕES FINAIS

### Prioridade CRÍTICA
1. **Proteger credenciais:** Mover para `.env` imediatamente
2. **Implementar autoloader:** Facilita toda refatoração futura
3. **Criar Base Classes:** Fundação para todo sistema MVC

### Prioridade ALTA
4. **Refatorar ClienteModel:** Exemplo para demais models
5. **Criar ClienteController:** Exemplo para demais controllers
6. **Implementar Router:** Permite URLs amigáveis

### Prioridade MÉDIA
7. **Sistema de Middleware:** Melhora segurança e organização
8. **Migração gradual:** Uma página por semana
9. **Testes automatizados:** Garante qualidade

### Prioridade BAIXA
10. **Otimizações de performance:** Após migração completa
11. **Refatoração de views:** Após controllers estáveis
12. **Documentação avançada:** Após sistema estabilizado

---

## 12. PRÓXIMOS PASSOS IMEDIATOS

1. **Revisar este documento** com a equipe técnica
2. **Criar branch `refactor/mvc`** no repositório
3. **Implementar Fase 1** (Fundação) em ambiente de desenvolvimento
4. **Testar em staging** antes de qualquer deploy
5. **Documentar decisões** e aprendizados durante o processo

---

## CONCLUSÃO

O sistema **ERP INLAUDO** possui uma base sólida, mas apresenta oportunidades significativas de melhoria na arquitetura MVC. As sugestões apresentadas neste documento foram cuidadosamente planejadas para **não impactar o funcionamento atual** do sistema em produção.

A estratégia de **migração incremental paralela** permite que o sistema continue operando normalmente enquanto a nova arquitetura é implementada e testada. Cada fase pode ser executada de forma independente, minimizando riscos e permitindo rollback em caso de problemas.

A implementação completa levará aproximadamente **12-16 semanas**, mas os benefícios em termos de manutenibilidade, segurança e escalabilidade justificam o investimento.

---

**Documento elaborado por:** Desenvolvedor Sênior  
**Data:** 09 de Janeiro de 2026  
**Versão:** 1.0
