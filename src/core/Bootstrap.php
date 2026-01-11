<?php
/**
 * Bootstrap - Inicialização do Sistema
 * 
 * Responsável por carregar todas as dependências e configurações
 */

namespace App\Core;

class Bootstrap {
    
    /**
     * Inicializar aplicação
     */
    public static function init() {
        // Carregar configurações
        self::loadConfig();
        
        // Configurar error handling
        self::setupErrorHandling();
        
        // Carregar autoloader
        self::loadAutoloader();
        
        // Inicializar sessão
        self::initSession();
        
        // Conectar ao banco de dados
        self::connectDatabase();
    }
    
    /**
     * Carregar arquivo de configuração
     */
    private static function loadConfig() {
        $configFile = dirname(dirname(dirname(__FILE__))) . '/config/Config.php';
        
        if (file_exists($configFile)) {
            require_once $configFile;
        } else {
            die('Arquivo de configuração não encontrado: ' . $configFile);
        }
    }
    
    /**
     * Configurar tratamento de erros
     */
    private static function setupErrorHandling() {
        if (APP_DEBUG) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
            ini_set('error_log', LOGS_PATH . '/error.log');
        }
        
        // Registrar handler de erros
        set_error_handler([self::class, 'errorHandler']);
        set_exception_handler([self::class, 'exceptionHandler']);
    }
    
    /**
     * Carregar autoloader
     */
    private static function loadAutoloader() {
        $autoloadFile = dirname(dirname(dirname(__FILE__))) . '/src/core/Autoloader.php';
        
        if (file_exists($autoloadFile)) {
            require_once $autoloadFile;
            Autoloader::register();
        }
    }
    
    /**
     * Inicializar sessão
     */
    private static function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_lifetime' => SESSION_LIFETIME * 60,
                'cookie_httponly' => true,
                'cookie_secure' => !APP_DEBUG,
                'cookie_samesite' => 'Lax'
            ]);
        }
    }
    
    /**
     * Conectar ao banco de dados
     */
    private static function connectDatabase() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $GLOBALS['db'] = new \PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (\PDOException $e) {
            die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
        }
    }
    
    /**
     * Handler de erros
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline) {
        $errorTypes = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED',
        ];
        
        $type = $errorTypes[$errno] ?? 'UNKNOWN';
        
        $message = sprintf(
            "[%s] %s in %s on line %d",
            $type,
            $errstr,
            $errfile,
            $errline
        );
        
        error_log($message);
        
        if (APP_DEBUG) {
            echo '<pre>' . htmlspecialchars($message) . '</pre>';
        }
        
        return true;
    }
    
    /**
     * Handler de exceções
     */
    public static function exceptionHandler(\Throwable $exception) {
        $message = sprintf(
            "[%s] %s in %s on line %d",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
        
        error_log($message);
        
        if (APP_DEBUG) {
            echo '<pre>' . htmlspecialchars($message) . '</pre>';
            echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
        } else {
            http_response_code(500);
            echo 'Erro interno do servidor. Por favor, tente novamente mais tarde.';
        }
    }
}
