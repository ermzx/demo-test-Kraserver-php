<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 配置管理类
 * ============================================
 * 
 * @package KindleReading\Core
 * @version 1.0.0
 */

namespace KindleReading\Core;

/**
 * 配置管理类
 * 
 * 提供统一的配置访问接口，支持从 .env 文件加载配置
 */
class Config
{
    /**
     * 配置数据存储
     * 
     * @var array
     */
    private static array $config = [];

    /**
     * 是否已加载配置
     * 
     * @var bool
     */
    private static bool $loaded = false;

    /**
     * 应用根目录
     * 
     * @var string|null
     */
    private static ?string $rootPath = null;

    /**
     * 设置应用根目录
     * 
     * @param string $path 应用根目录路径
     * @return void
     */
    public static function setRootPath(string $path): void
    {
        self::$rootPath = rtrim($path, '/');
    }

    /**
     * 获取应用根目录
     * 
     * @return string 应用根目录路径
     */
    public static function getRootPath(): string
    {
        if (self::$rootPath === null) {
            self::$rootPath = dirname(__DIR__, 2);
        }
        return self::$rootPath;
    }

    /**
     * 加载配置文件
     * 
     * @return void
     * @throws \RuntimeException 如果配置文件不存在
     */
    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        // 加载 .env 文件
        self::loadEnvFile();

        // 加载主配置文件
        $configFile = self::getRootPath() . '/config/config.php';
        if (!file_exists($configFile)) {
            throw new \RuntimeException("配置文件不存在: {$configFile}");
        }

        self::$config = require $configFile;
        self::$loaded = true;
    }

    /**
     * 加载 .env 文件
     * 
     * @return void
     */
    private static function loadEnvFile(): void
    {
        $envFile = self::getRootPath() . '/.env';
        if (!file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // 跳过注释行
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // 解析键值对
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // 移除引号
                if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }

                // 设置环境变量
                if (!array_key_exists($name, $_ENV) && !array_key_exists($name, $_SERVER)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }

    /**
     * 获取配置值
     * 
     * @param string $key 配置键，支持点号分隔的嵌套键（如 'app.name'）
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public static function get(string $key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 设置配置值（运行时）
     * 
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @return void
     */
    public static function set(string $key, $value): void
    {
        if (!self::$loaded) {
            self::load();
        }

        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    /**
     * 检查配置是否存在
     * 
     * @param string $key 配置键
     * @return bool
     */
    public static function has(string $key): bool
    {
        if (!self::$loaded) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /**
     * 获取所有配置
     * 
     * @return array 所有配置
     */
    public static function all(): array
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$config;
    }

    /**
     * 获取应用名称
     * 
     * @return string 应用名称
     */
    public static function getAppName(): string
    {
        return self::get('app_name', 'Kindle Reading GTK');
    }

    /**
     * 获取应用环境
     * 
     * @return string 应用环境（development, staging, production）
     */
    public static function getAppEnv(): string
    {
        return self::get('app_env', 'production');
    }

    /**
     * 是否为调试模式
     * 
     * @return bool
     */
    public static function isDebug(): bool
    {
        return self::get('app_debug', false);
    }

    /**
     * 获取应用 URL
     * 
     * @return string 应用 URL
     */
    public static function getAppUrl(): string
    {
        return self::get('app_url', 'http://localhost');
    }

    /**
     * 获取时区
     * 
     * @return string 时区
     */
    public static function getTimezone(): string
    {
        return self::get('timezone', 'Asia/Shanghai');
    }

    /**
     * 获取数据库配置
     * 
     * @param string|null $connection 连接名称，默认为 'mysql'
     * @return array 数据库配置
     */
    public static function getDatabaseConfig(?string $connection = null): array
    {
        $databaseConfig = require self::getRootPath() . '/config/database.php';
        
        if ($connection === null) {
            $connection = $databaseConfig['default'] ?? 'mysql';
        }

        return $databaseConfig['connections'][$connection] ?? [];
    }

    /**
     * 获取数据库表名配置
     * 
     * @return array 数据库表名配置
     */
    public static function getDatabaseTables(): array
    {
        $databaseConfig = require self::getRootPath() . '/config/database.php';
        return $databaseConfig['tables'] ?? [];
    }

    /**
     * 获取上传配置
     * 
     * @return array 上传配置
     */
    public static function getUploadConfig(): array
    {
        return self::get('upload', []);
    }

    /**
     * 获取会话配置
     * 
     * @return array 会话配置
     */
    public static function getSessionConfig(): array
    {
        return self::get('session', []);
    }

    /**
     * 获取日志配置
     * 
     * @return array 日志配置
     */
    public static function getLogConfig(): array
    {
        return self::get('log', []);
    }

    /**
     * 获取安全配置
     * 
     * @return array 安全配置
     */
    public static function getSecurityConfig(): array
    {
        return self::get('security', []);
    }

    /**
     * 获取 API 配置
     * 
     * @return array API 配置
     */
    public static function getApiConfig(): array
    {
        return self::get('api', []);
    }

    /**
     * 获取存储配置
     * 
     * @return array 存储配置
     */
    public static function getStorageConfig(): array
    {
        return self::get('storage', []);
    }

    /**
     * 获取环境变量值
     * 
     * @param string $key 环境变量键
     * @param mixed $default 默认值
     * @return mixed 环境变量值
     */
    public static function env(string $key, $default = null)
    {
        $value = getenv($key);
        
        if ($value === false) {
            return $default;
        }

        // 转换布尔值
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }

        // 转换 null
        if (strtolower($value) === 'null') {
            return null;
        }

        return $value;
    }
}