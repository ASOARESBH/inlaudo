<?php
/**
 * Autoloader PSR-4
 * 
 * Carrega automaticamente classes baseado no namespace
 */

namespace App\Core;

class Autoloader {
    
    /**
     * Namespaces mapeados
     */
    private static $namespaces = [
        'App\\Controllers\\' => 'src/controllers/',
        'App\\Models\\' => 'src/models/',
        'App\\Services\\' => 'src/services/',
        'App\\Core\\' => 'src/core/',
    ];
    
    /**
     * Registrar autoloader
     */
    public static function register() {
        spl_autoload_register([self::class, 'load']);
    }
    
    /**
     * Carregar classe
     */
    public static function load($class) {
        foreach (self::$namespaces as $namespace => $path) {
            if (strpos($class, $namespace) === 0) {
                $file = ROOT_PATH . '/' . $path . str_replace('\\', '/', substr($class, strlen($namespace))) . '.php';
                
                if (file_exists($file)) {
                    require_once $file;
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Adicionar namespace
     */
    public static function addNamespace($namespace, $path) {
        self::$namespaces[$namespace] = $path;
    }
}
