<?php
/**
 * Script de Migração do Banco de Dados
 * Versão: 2.3.0
 * 
 * Executa migrations de forma segura e incremental
 * IMPORTANTE: Fazer backup do banco antes de executar!
 */

require_once __DIR__ . '/../config.php';

// Configurações
define('MIGRATIONS_DIR', __DIR__ . '/sql/migrations');
define('LOG_FILE', __DIR__ . '/migrations.log');

/**
 * Registra log de migração
 */
function logMigration($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$type}] {$message}\n";
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND);
    echo $logMessage;
}

/**
 * Cria tabela de controle de migrations
 */
function createMigrationsTable($conn) {
    $sql = "
    CREATE TABLE IF NOT EXISTS _migrations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        migration_file VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('success', 'failed') DEFAULT 'success',
        error_message TEXT,
        INDEX idx_migration_file (migration_file),
        INDEX idx_executed_at (executed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Controle de migrations executadas'
    ";
    
    try {
        $conn->exec($sql);
        logMigration("Tabela _migrations criada/verificada");
        return true;
    } catch (PDOException $e) {
        logMigration("Erro ao criar tabela _migrations: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Verifica se migration já foi executada
 */
function migrationExecuted($conn, $file) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM _migrations WHERE migration_file = ? AND status = 'success'");
    $stmt->execute([$file]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Registra execução de migration
 */
function registerMigration($conn, $file, $status = 'success', $error = null) {
    $stmt = $conn->prepare("
        INSERT INTO _migrations (migration_file, status, error_message) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            status = VALUES(status),
            error_message = VALUES(error_message),
            executed_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$file, $status, $error]);
}

/**
 * Executa uma migration SQL
 */
function executeMigration($conn, $file) {
    $filePath = MIGRATIONS_DIR . '/' . $file;
    
    if (!file_exists($filePath)) {
        logMigration("Arquivo não encontrado: {$file}", 'ERROR');
        return false;
    }
    
    logMigration("Executando migration: {$file}");
    
    $sql = file_get_contents($filePath);
    
    // Remover comentários
    $sql = preg_replace('/--.*$/m', '', $sql);
    
    // Dividir por statements (separados por ;)
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt);
        }
    );
    
    $conn->beginTransaction();
    
    try {
        foreach ($statements as $i => $statement) {
            if (empty(trim($statement))) continue;
            
            // Pular comandos DELIMITER
            if (stripos($statement, 'DELIMITER') !== false) continue;
            
            try {
                $conn->exec($statement);
                logMigration("  ✓ Statement " . ($i + 1) . " executado");
            } catch (PDOException $e) {
                // Ignorar erros de "já existe" (para compatibilidade)
                if (
                    stripos($e->getMessage(), 'already exists') !== false ||
                    stripos($e->getMessage(), 'Duplicate') !== false ||
                    stripos($e->getMessage(), '1050') !== false || // Table already exists
                    stripos($e->getMessage(), '1060') !== false || // Duplicate column
                    stripos($e->getMessage(), '1061') !== false || // Duplicate key
                    stripos($e->getMessage(), '1062') !== false    // Duplicate entry
                ) {
                    logMigration("  ⚠ Statement " . ($i + 1) . " já existe (ignorado)", 'WARN');
                    continue;
                }
                
                throw $e;
            }
        }
        
        $conn->commit();
        registerMigration($conn, $file, 'success');
        logMigration("✅ Migration {$file} executada com sucesso!");
        return true;
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = $e->getMessage();
        registerMigration($conn, $file, 'failed', $error);
        logMigration("❌ Erro ao executar {$file}: {$error}", 'ERROR');
        return false;
    }
}

/**
 * Lista migrations disponíveis
 */
function listMigrations() {
    if (!is_dir(MIGRATIONS_DIR)) {
        logMigration("Diretório de migrations não encontrado: " . MIGRATIONS_DIR, 'ERROR');
        return [];
    }
    
    $files = scandir(MIGRATIONS_DIR);
    $migrations = array_filter($files, function($file) {
        return preg_match('/^\d+.*\.sql$/', $file);
    });
    
    sort($migrations);
    return $migrations;
}

/**
 * Executa todas as migrations pendentes
 */
function runMigrations() {
    logMigration("=== INICIANDO MIGRATIONS ===");
    logMigration("Banco de dados: " . DB_NAME);
    logMigration("Data: " . date('Y-m-d H:i:s'));
    
    try {
        $conn = getConnection();
        
        // Criar tabela de controle
        if (!createMigrationsTable($conn)) {
            logMigration("Falha ao criar tabela de controle", 'ERROR');
            return false;
        }
        
        // Listar migrations
        $migrations = listMigrations();
        
        if (empty($migrations)) {
            logMigration("Nenhuma migration encontrada", 'WARN');
            return true;
        }
        
        logMigration("Migrations encontradas: " . count($migrations));
        
        $executed = 0;
        $skipped = 0;
        $failed = 0;
        
        foreach ($migrations as $migration) {
            if (migrationExecuted($conn, $migration)) {
                logMigration("⏭  Migration {$migration} já executada (pulando)");
                $skipped++;
                continue;
            }
            
            if (executeMigration($conn, $migration)) {
                $executed++;
            } else {
                $failed++;
                logMigration("Parando execução devido a erro", 'ERROR');
                break;
            }
        }
        
        logMigration("=== RESUMO ===");
        logMigration("Total de migrations: " . count($migrations));
        logMigration("Executadas: {$executed}");
        logMigration("Puladas: {$skipped}");
        logMigration("Falhas: {$failed}");
        
        if ($failed > 0) {
            logMigration("❌ Migrations concluídas com erros", 'ERROR');
            return false;
        }
        
        logMigration("✅ Todas as migrations executadas com sucesso!");
        return true;
        
    } catch (Exception $e) {
        logMigration("Erro fatal: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// ============================================================
// EXECUÇÃO
// ============================================================

// Verificar se está sendo executado via CLI
if (php_sapi_name() !== 'cli') {
    // Se for via web, verificar autenticação admin
    session_start();
    require_once __DIR__ . '/../auth.php';
    
    if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] !== 'admin') {
        die('❌ Acesso negado. Apenas administradores podem executar migrations.');
    }
    
    echo "<pre>";
}

// Executar migrations
$success = runMigrations();

if (php_sapi_name() !== 'cli') {
    echo "</pre>";
    
    if ($success) {
        echo "<p style='color: green; font-weight: bold;'>✅ Migrations executadas com sucesso!</p>";
        echo "<p><a href='../index.php'>Voltar ao Dashboard</a></p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ Erro ao executar migrations. Verifique o log.</p>";
        echo "<p><a href='../index.php'>Voltar ao Dashboard</a></p>";
    }
}

exit($success ? 0 : 1);
