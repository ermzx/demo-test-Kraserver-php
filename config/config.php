<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 主配置文件
 * ============================================
 * 
 * 此文件包含应用程序的核心配置
 * 所有配置项都可以通过环境变量覆盖
 */

// 防止直接访问
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// 加载环境变量
$envFile = APP_ROOT . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_ENV) && !array_key_exists($name, $_SERVER)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// ============================================
// 应用配置
// ============================================
return [
    // 应用名称
    'app_name' => getenv('APP_NAME') ?: 'Kindle Reading GTK',
    
    // 应用环境：development, staging, production
    'app_env' => getenv('APP_ENV') ?: 'production',
    
    // 调试模式
    'app_debug' => getenv('APP_DEBUG') === 'true',
    
    // 应用 URL
    'app_url' => getenv('APP_URL') ?: 'http://localhost',
    
    // 时区设置
    'timezone' => 'Asia/Shanghai',
    
    // 字符编码
    'charset' => 'UTF-8',
    
    // ============================================
    // 文件上传配置
    // ============================================
    'upload' => [
        // 最大上传文件大小（字节）
        'max_file_size' => (int)(getenv('MAX_FILE_SIZE') ?: 104857600),
        
        // 允许上传的文件扩展名
        'allowed_extensions' => array_filter(
            explode(',', getenv('ALLOWED_EXTENSIONS') ?: 'log,txt'),
            function($ext) {
                return !empty(trim($ext));
            }
        ),
        
        // 上传文件存储路径
        'upload_path' => APP_ROOT . '/' . (getenv('UPLOAD_PATH') ?: 'public/uploads'),
    ],
    
    // ============================================
    // 会话配置
    // ============================================
    'session' => [
        // OAuth 会话超时时间（秒）
        'timeout' => (int)(getenv('SESSION_TIMEOUT') ?: 300),
        
        // 用户访问令牌有效期（秒）
        'user_token_lifetime' => (int)(getenv('USER_TOKEN_LIFETIME') ?: 7200),
        
        // 会话令牌前缀
        'session_token_prefix' => getenv('SESSION_TOKEN_PREFIX') ?: 'kr_',
        
        // 用户令牌前缀
        'user_token_prefix' => getenv('USER_TOKEN_PREFIX') ?: 'ur_',
    ],
    
    // ============================================
    // 日志配置
    // ============================================
    'log' => [
        // 日志级别：debug, info, warning, error
        'level' => getenv('LOG_LEVEL') ?: 'info',
        
        // 日志文件存储路径
        'log_path' => APP_ROOT . '/' . (getenv('LOG_PATH') ?: 'storage/logs'),
        
        // 日志文件名格式
        'filename_format' => 'Y-m-d',
        
        // 日志文件扩展名
        'file_extension' => '.log',
    ],
    
    // ============================================
    // 安全配置
    // ============================================
    'security' => [
        // 加密密钥（用于加密敏感数据）
        'encryption_key' => getenv('ENCRYPTION_KEY') ?: 'default-encryption-key-change-this',
        
        // 密码哈希算法
        'password_hash_algo' => PASSWORD_BCRYPT,
        
        // 密码哈希选项
        'password_hash_options' => [
            'cost' => 10,
        ],
        
        // CSRF 保护
        'csrf_protection' => true,
        
        // XSS 保护
        'xss_protection' => true,
    ],
    
    // ============================================
    // API 配置
    // ============================================
    'api' => [
        // API 版本
        'version' => '1.0',
        
        // API 响应格式
        'response_format' => 'json',
        
        // API 超时时间（秒）
        'timeout' => 30,
        
        // API 速率限制（每分钟请求数）
        'rate_limit' => 60,
    ],
    
    // ============================================
    // 存储配置
    // ============================================
    'storage' => [
        // 缓存路径
        'cache_path' => APP_ROOT . '/storage/cache',
        
        // 会话路径
        'session_path' => APP_ROOT . '/storage/sessions',
        
        // 日志路径
        'log_path' => APP_ROOT . '/storage/logs',
    ],
];