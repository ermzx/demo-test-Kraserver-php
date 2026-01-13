<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 日志类
 * ============================================
 * 
 * @package KindleReading\Core
 * @version 1.0.0
 */

namespace KindleReading\Core;

/**
 * 日志类
 * 
 * 提供日志记录功能，支持不同日志级别
 */
class Logger
{
    /**
     * 日志级别常量
     */
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    /**
     * 日志级别优先级
     * 
     * @var array
     */
    private static array $levels = [
        self::DEBUG => 100,
        self::INFO => 200,
        self::WARNING => 300,
        self::ERROR => 400,
    ];

    /**
     * 日志配置
     * 
     * @var array
     */
    private static array $config = [];

    /**
     * 日志文件句柄
     * 
     * @var array
     */
    private static array $handles = [];

    /**
     * 初始化日志配置
     * 
     * @return void
     */
    private static function init(): void
    {
        if (empty(self::$config)) {
            self::$config = Config::getLogConfig();
        }
    }

    /**
     * 获取日志文件路径
     * 
     * @param string $level 日志级别
     * @return string 日志文件路径
     */
    private static function getLogFilePath(string $level): string
    {
        self::init();

        $logPath = self::$config['log_path'] ?? Config::getRootPath() . '/storage/logs';
        $date = date('Y-m-d');
        
        // 确保日志目录存在
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        return $logPath . '/' . $level . '-' . $date . '.log';
    }

    /**
     * 获取日志文件句柄
     * 
     * @param string $level 日志级别
     * @return resource 文件句柄
     */
    private static function getHandle(string $level)
    {
        $filePath = self::getLogFilePath($level);

        if (!isset(self::$handles[$level])) {
            self::$handles[$level] = fopen($filePath, 'a');
        }

        return self::$handles[$level];
    }

    /**
     * 格式化日志消息
     * 
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return string 格式化后的日志消息
     */
    private static function formatMessage(string $level, string $message, array $context = []): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $microtime = microtime(true);
        $milliseconds = sprintf('.%03d', ($microtime - (int)$microtime) * 1000);
        
        $formatted = "[{$timestamp}{$milliseconds}] [{$level}] {$message}";

        // 添加上下文信息
        if (!empty($context)) {
            $formatted .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $formatted . PHP_EOL;
    }

    /**
     * 写入日志
     * 
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        // 检查日志级别是否允许记录
        if (!self::shouldLog($level)) {
            return;
        }

        $formattedMessage = self::formatMessage($level, $message, $context);
        $handle = self::getHandle($level);

        if (is_resource($handle)) {
            fwrite($handle, $formattedMessage);
        }
    }

    /**
     * 检查是否应该记录该级别的日志
     * 
     * @param string $level 日志级别
     * @return bool 是否应该记录
     */
    private static function shouldLog(string $level): bool
    {
        self::init();

        $configLevel = self::$config['level'] ?? self::INFO;
        $configPriority = self::$levels[$configLevel] ?? 200;
        $levelPriority = self::$levels[$level] ?? 200;

        return $levelPriority >= $configPriority;
    }

    /**
     * 记录 DEBUG 级别日志
     * 
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * 记录 INFO 级别日志
     * 
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::INFO, $message, $context);
    }

    /**
     * 记录 WARNING 级别日志
     * 
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(self::WARNING, $message, $context);
    }

    /**
     * 记录 ERROR 级别日志
     * 
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::ERROR, $message, $context);
    }

    /**
     * 记录异常
     * 
     * @param \Throwable $exception 异常对象
     * @param array $context 上下文信息
     * @return void
     */
    public static function exception(\Throwable $exception, array $context = []): void
    {
        $message = sprintf(
            '%s: %s in %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        $context['trace'] = $exception->getTraceAsString();

        self::error($message, $context);
    }

    /**
     * 记录 SQL 查询
     * 
     * @param string $sql SQL 语句
     * @param array $params 参数
     * @param float $executionTime 执行时间（秒）
     * @return void
     */
    public static function sql(string $sql, array $params = [], float $executionTime = 0): void
    {
        $context = [
            'sql' => $sql,
            'params' => $params,
            'execution_time' => round($executionTime * 1000, 2) . 'ms',
        ];

        self::debug('SQL Query', $context);
    }

    /**
     * 记录 API 请求
     * 
     * @param string $method 请求方法
     * @param string $uri 请求 URI
     * @param array $params 请求参数
     * @param int $statusCode 响应状态码
     * @param float $executionTime 执行时间（秒）
     * @return void
     */
    public static function apiRequest(string $method, string $uri, array $params = [], int $statusCode = 200, float $executionTime = 0): void
    {
        $context = [
            'method' => $method,
            'uri' => $uri,
            'params' => $params,
            'status_code' => $statusCode,
            'execution_time' => round($executionTime * 1000, 2) . 'ms',
        ];

        $level = $statusCode >= 400 ? self::ERROR : self::INFO;
        self::log($level, 'API Request', $context);
    }

    /**
     * 记录用户操作
     * 
     * @param int|null $userId 用户 ID
     * @param string $action 操作名称
     * @param array $context 上下文信息
     * @return void
     */
    public static function userAction(?int $userId, string $action, array $context = []): void
    {
        $context['user_id'] = $userId;
        $context['action'] = $action;

        self::info('User Action', $context);
    }

    /**
     * 记录文件上传
     * 
     * @param int|null $userId 用户 ID
     * @param string $fileName 文件名
     * @param int $fileSize 文件大小
     * @param bool $success 是否成功
     * @return void
     */
    public static function fileUpload(?int $userId, string $fileName, int $fileSize, bool $success): void
    {
        $context = [
            'user_id' => $userId,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'success' => $success,
        ];

        $level = $success ? self::INFO : self::ERROR;
        self::log($level, 'File Upload', $context);
    }

    /**
     * 记录数据库操作
     * 
     * @param string $operation 操作类型（insert, update, delete）
     * @param string $table 表名
     * @param int|null $id 记录 ID
     * @param array $data 数据
     * @return void
     */
    public static function databaseOperation(string $operation, string $table, ?int $id, array $data = []): void
    {
        $context = [
            'operation' => $operation,
            'table' => $table,
            'id' => $id,
            'data' => $data,
        ];

        self::debug('Database Operation', $context);
    }

    /**
     * 记录认证事件
     * 
     * @param string $event 事件类型（login, logout, register, etc.）
     * @param int|null $userId 用户 ID
     * @param string $ip IP 地址
     * @param array $context 上下文信息
     * @return void
     */
    public static function authEvent(string $event, ?int $userId, string $ip, array $context = []): void
    {
        $context['event'] = $event;
        $context['user_id'] = $userId;
        $context['ip'] = $ip;

        self::info('Auth Event', $context);
    }

    /**
     * 清理过期日志文件
     * 
     * @param int $days 保留天数
     * @return int 删除的文件数量
     */
    public static function cleanOldLogs(int $days = 30): int
    {
        self::init();

        $logPath = self::$config['log_path'] ?? Config::getRootPath() . '/storage/logs';
        $deletedCount = 0;
        $cutoffTime = time() - ($days * 86400);

        if (!is_dir($logPath)) {
            return 0;
        }

        $files = glob($logPath . '/*.log');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
        }

        return $deletedCount;
    }

    /**
     * 关闭所有日志文件句柄
     * 
     * @return void
     */
    public static function close(): void
    {
        foreach (self::$handles as $level => $handle) {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        self::$handles = [];
    }

    /**
     * 获取日志内容
     * 
     * @param string $level 日志级别
     * @param int $lines 读取行数
     * @return array 日志行数组
     */
    public static function getLogs(string $level = self::INFO, int $lines = 100): array
    {
        $filePath = self::getLogFilePath($level);

        if (!file_exists($filePath)) {
            return [];
        }

        $file = new \SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $logs = [];
        $startLine = max(0, $totalLines - $lines);

        for ($i = $startLine; $i <= $totalLines; $i++) {
            $file->seek($i);
            $logs[] = trim($file->current());
        }

        return array_filter($logs);
    }
}