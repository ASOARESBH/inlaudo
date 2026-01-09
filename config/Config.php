<?php
/**
 * Configuração Principal - ERP INLAUDO
 * 
 * Define todas as constantes e configurações do sistema
 */

// ============================================================
// INFORMAÇÕES DO SISTEMA
// ============================================================
define('APP_NAME', 'ERP INLAUDO');
define('APP_VERSION', '2.0.0');
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_DEBUG', getenv('APP_DEBUG') ?: false);

// ============================================================
// CAMINHOS DO SISTEMA
// ============================================================
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('SRC_PATH', ROOT_PATH . '/src');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('DATABASE_PATH', ROOT_PATH . '/database');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('LOGS_PATH', STORAGE_PATH . '/logs');
define('CACHE_PATH', STORAGE_PATH . '/cache');
define('UPLOADS_PATH', STORAGE_PATH . '/uploads');

// ============================================================
// BANCO DE DADOS
// ============================================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: 3306);
define('DB_NAME', getenv('DB_NAME') ?: 'erpinlaudo');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// URLS E DOMÍNIOS
// ============================================================
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost');
define('APP_URL', BASE_URL);
define('ASSETS_URL', BASE_URL . '/public');
define('UPLOADS_URL', BASE_URL . '/storage/uploads');

// ============================================================
// INTEGRAÇÃO CORA
// ============================================================
define('CORA_API_KEY', getenv('CORA_API_KEY') ?: '');
define('CORA_ACCOUNT_ID', getenv('CORA_ACCOUNT_ID') ?: '');
define('CORA_API_URL', 'https://api.cora.com.br/v1');
define('CORA_WEBHOOK_SECRET', getenv('CORA_WEBHOOK_SECRET') ?: '');

// ============================================================
// INTEGRAÇÃO MERCADO PAGO
// ============================================================
define('MERCADOPAGO_ACCESS_TOKEN', getenv('MERCADOPAGO_ACCESS_TOKEN') ?: '');
define('MERCADOPAGO_PUBLIC_KEY', getenv('MERCADOPAGO_PUBLIC_KEY') ?: '');

// ============================================================
// INTEGRAÇÃO STRIPE
// ============================================================
define('STRIPE_SECRET_KEY', getenv('STRIPE_SECRET_KEY') ?: '');
define('STRIPE_PUBLIC_KEY', getenv('STRIPE_PUBLIC_KEY') ?: '');

// ============================================================
// EMAIL
// ============================================================
define('MAIL_DRIVER', getenv('MAIL_DRIVER') ?: 'smtp');
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.mailtrap.io');
define('MAIL_PORT', getenv('MAIL_PORT') ?: 465);
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@erpinlaudo.com.br');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'ERP INLAUDO');

// ============================================================
// SEGURANÇA
// ============================================================
define('APP_KEY', getenv('APP_KEY') ?: 'base64:' . base64_encode(random_bytes(32)));
define('SESSION_LIFETIME', 120); // minutos
define('PASSWORD_HASH_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_HASH_OPTIONS', ['cost' => 10]);

// ============================================================
// TIMEZONE
// ============================================================
date_default_timezone_set(getenv('TIMEZONE') ?: 'America/Sao_Paulo');

// ============================================================
// LOCALES
// ============================================================
define('DEFAULT_LOCALE', 'pt_BR');
define('DEFAULT_CURRENCY', 'BRL');

// ============================================================
// LOGGING
// ============================================================
define('LOG_CHANNEL', getenv('LOG_CHANNEL') ?: 'stack');
define('LOG_LEVEL', getenv('LOG_LEVEL') ?: 'debug');

// ============================================================
// ALERTAS
// ============================================================
define('ALERTAS_ATIVADOS', true);
define('ALERTAS_MOSTRAR_POPUP_LOGIN', true);
define('ALERTAS_DIAS_VENCIDO', 0);
define('ALERTAS_DIAS_VENCENDO', 7);

// ============================================================
// PAGINAÇÃO
// ============================================================
define('ITEMS_PER_PAGE', 20);
define('MAX_ITEMS_PER_PAGE', 100);

// ============================================================
// UPLOADS
// ============================================================
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif']);

// ============================================================
// MODO MANUTENÇÃO
// ============================================================
define('MAINTENANCE_MODE', getenv('MAINTENANCE_MODE') ?: false);
define('MAINTENANCE_MESSAGE', 'Sistema em manutenção. Tente novamente em breve.');

// ============================================================
// FUNÇÃO AUXILIAR PARA VARIÁVEIS DE AMBIENTE
// ============================================================
if (!function_exists('env')) {
    /**
     * Obter valor de variável de ambiente
     */
    function env($key, $default = null) {
        $value = getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Converter valores booleanos
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
