<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 数据库配置文件
 * ============================================
 * 
 * 此文件包含数据库连接配置
 * 所有配置项都可以通过环境变量覆盖
 */

// 防止直接访问
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// ============================================
// 数据库配置
// ============================================
return [
    // 默认数据库连接
    'default' => 'mysql',
    
    // 数据库连接配置
    'connections' => [
        'mysql' => [
            // 数据库驱动
            'driver' => 'mysql',
            
            // 数据库主机
            'host' => getenv('DB_HOST') ?: 'localhost',
            
            // 数据库端口
            'port' => (int)(getenv('DB_PORT') ?: 3306),
            
            // 数据库名称
            'database' => getenv('DB_NAME') ?: 'kindle_reading',
            
            // 数据库用户名
            'username' => getenv('DB_USER') ?: 'root',
            
            // 数据库密码
            'password' => getenv('DB_PASS') ?: '',
            
            // 字符集
            'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
            
            // 排序规则
            'collation' => 'utf8mb4_unicode_ci',
            
            // 表前缀（可选）
            'prefix' => '',
            
            // 严格模式
            'strict' => true,
            
            // 引擎
            'engine' => 'InnoDB',
            
            // PDO 选项
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ],
        ],
    ],
    
    // ============================================
    // 数据库表名配置
    // ============================================
    'tables' => [
        // 用户表
        'users' => 'users',
        
        // Kindle 设备表
        'kindle_devices' => 'kindle_devices',
        
        // 阅读日志文件表
        'reading_logs' => 'reading_logs',
        
        // OAuth 会话表
        'oauth_sessions' => 'oauth_sessions',
        
        // 系统配置表
        'system_config' => 'system_config',
    ],
    
    // ============================================
    // 数据库迁移配置
    // ============================================
    'migrations' => [
        // 迁移文件存储路径
        'path' => APP_ROOT . '/database/migrations',
        
        // 迁移表名
        'table' => 'migrations',
    ],
    
    // ============================================
    // 数据库备份配置
    // ============================================
    'backup' => [
        // 备份存储路径
        'path' => APP_ROOT . '/storage/backups',
        
        // 备份保留天数
        'retention_days' => 30,
        
        // 是否启用自动备份
        'auto_backup' => false,
        
        // 自动备份时间（cron 格式）
        'schedule' => '0 2 * * *',
    ],
];