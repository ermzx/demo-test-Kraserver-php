<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 数据库连接类
 * ============================================
 * 
 * @package KindleReading\Core
 * @version 1.0.0
 */

namespace KindleReading\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * 数据库连接类
 * 
 * 使用 PDO 连接 MySQL 数据库，支持单例模式
 * 提供预处理语句查询方法和事务支持
 */
class Database
{
    /**
     * PDO 实例
     * 
     * @var PDO|null
     */
    private static ?PDO $instance = null;

    /**
     * 数据库配置
     * 
     * @var array
     */
    private static array $config = [];

    /**
     * 是否在事务中
     * 
     * @var bool
     */
    private static bool $inTransaction = false;

    /**
     * 私有构造函数，防止直接实例化
     */
    private function __construct()
    {
        // 单例模式，禁止直接实例化
    }

    /**
     * 获取数据库实例（单例模式）
     * 
     * @param array|null $config 数据库配置，如果为 null 则使用默认配置
     * @return PDO PDO 实例
     * @throws PDOException 如果连接失败
     */
    public static function getInstance(?array $config = null): PDO
    {
        if (self::$instance === null) {
            self::$config = $config ?? Config::getDatabaseConfig();
            self::connect();
        }

        return self::$instance;
    }

    /**
     * 连接数据库
     * 
     * @return void
     * @throws PDOException 如果连接失败
     */
    private static function connect(): void
    {
        $config = self::$config;

        // 构建 DSN
        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['driver'] ?? 'mysql',
            $config['host'] ?? 'localhost',
            $config['port'] ?? 3306,
            $config['database'] ?? '',
            $config['charset'] ?? 'utf8mb4'
        );

        // PDO 选项
        $options = $config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            self::$instance = new PDO(
                $dsn,
                $config['username'] ?? '',
                $config['password'] ?? '',
                $options
            );
        } catch (PDOException $e) {
            throw new PDOException(
                "数据库连接失败: " . $e->getMessage(),
                (int)$e->getCode()
            );
        }
    }

    /**
     * 执行查询并返回所有结果
     * 
     * @param string $sql SQL 语句
     * @param array $params 参数数组
     * @return array 查询结果数组
     * @throws PDOException 如果查询失败
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::execute($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * 执行查询并返回单行结果
     * 
     * @param string $sql SQL 语句
     * @param array $params 参数数组
     * @return array|null 查询结果数组，如果没有结果则返回 null
     * @throws PDOException 如果查询失败
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::execute($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * 执行查询并返回单个值
     * 
     * @param string $sql SQL 语句
     * @param array $params 参数数组
     * @return mixed 查询结果值，如果没有结果则返回 null
     * @throws PDOException 如果查询失败
     */
    public static function queryScalar(string $sql, array $params = [])
    {
        $stmt = self::execute($sql, $params);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : null;
    }

    /**
     * 执行 SQL 语句（INSERT, UPDATE, DELETE）
     * 
     * @param string $sql SQL 语句
     * @param array $params 参数数组
     * @return int 受影响的行数
     * @throws PDOException 如果执行失败
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::prepareAndExecute($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * 执行 SQL 语句并返回最后插入的 ID
     * 
     * @param string $sql SQL 语句
     * @param array $params 参数数组
     * @return string 最后插入的 ID
     * @throws PDOException 如果执行失败
     */
    public static function insert(string $sql, array $params = []): string
    {
        self::execute($sql, $params);
        return self::getInstance()->lastInsertId();
    }

    /**
     * 准备并执行预处理语句
     * 
     * @param string $sql SQL 语句
     * @param array $params 参数数组
     * @return PDOStatement PDOStatement 实例
     * @throws PDOException 如果执行失败
     */
    private static function prepareAndExecute(string $sql, array $params = []): PDOStatement
    {
        $pdo = self::getInstance();
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new PDOException(
                "SQL 执行失败: " . $e->getMessage() . " [SQL: {$sql}]",
                (int)$e->getCode()
            );
        }
    }

    /**
     * 执行预处理语句
     * 
     * @param string $sql SQL 语句
     * @param array $params 参数数组
     * @return PDOStatement PDOStatement 实例
     * @throws PDOException 如果执行失败
     */
    public static function executeStatement(string $sql, array $params = []): PDOStatement
    {
        return self::prepareAndExecute($sql, $params);
    }

    /**
     * 开始事务
     * 
     * @return bool 是否成功开始事务
     * @throws PDOException 如果开始事务失败
     */
    public static function beginTransaction(): bool
    {
        if (self::$inTransaction) {
            return true;
        }

        $pdo = self::getInstance();
        $result = $pdo->beginTransaction();
        
        if ($result) {
            self::$inTransaction = true;
        }

        return $result;
    }

    /**
     * 提交事务
     * 
     * @return bool 是否成功提交事务
     * @throws PDOException 如果提交事务失败
     */
    public static function commit(): bool
    {
        if (!self::$inTransaction) {
            return false;
        }

        $pdo = self::getInstance();
        $result = $pdo->commit();
        
        if ($result) {
            self::$inTransaction = false;
        }

        return $result;
    }

    /**
     * 回滚事务
     * 
     * @return bool 是否成功回滚事务
     * @throws PDOException 如果回滚事务失败
     */
    public static function rollBack(): bool
    {
        if (!self::$inTransaction) {
            return false;
        }

        $pdo = self::getInstance();
        $result = $pdo->rollBack();
        
        if ($result) {
            self::$inTransaction = false;
        }

        return $result;
    }

    /**
     * 检查是否在事务中
     * 
     * @return bool 是否在事务中
     */
    public static function inTransaction(): bool
    {
        return self::$inTransaction;
    }

    /**
     * 执行事务回调
     * 
     * @param callable $callback 事务回调函数
     * @return mixed 回调函数的返回值
     * @throws \Exception 如果事务执行失败
     */
    public static function transaction(callable $callback)
    {
        self::beginTransaction();

        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (\Exception $e) {
            self::rollBack();
            throw $e;
        }
    }

    /**
     * 获取表名
     * 
     * @param string $table 表名键
     * @return string 完整表名
     */
    public static function table(string $table): string
    {
        $tables = Config::getDatabaseTables();
        return $tables[$table] ?? $table;
    }

    /**
     * 检查表是否存在
     * 
     * @param string $tableName 表名
     * @return bool 表是否存在
     */
    public static function tableExists(string $tableName): bool
    {
        $sql = "SHOW TABLES LIKE :table_name";
        $result = self::queryOne($sql, ['table_name' => $tableName]);
        return $result !== null;
    }

    /**
     * 获取表的列信息
     * 
     * @param string $tableName 表名
     * @return array 列信息数组
     */
    public static function getTableColumns(string $tableName): array
    {
        $sql = "SHOW COLUMNS FROM `{$tableName}`";
        return self::query($sql);
    }

    /**
     * 获取最后插入的 ID
     * 
     * @param string|null $name 序列名称
     * @return string 最后插入的 ID
     */
    public static function lastInsertId(?string $name = null): string
    {
        return self::getInstance()->lastInsertId($name);
    }

    /**
     * 获取受影响的行数
     * 
     * @return int 受影响的行数
     */
    public static function rowCount(): int
    {
        // 注意：这个方法需要在执行语句后立即调用
        // 实际使用时应该使用 execute() 方法的返回值
        return 0;
    }

    /**
     * 引用字符串（用于 SQL 语句）
     * 
     * @param string $string 要引用的字符串
     * @return string 引用后的字符串
     */
    public static function quote(string $string): string
    {
        return self::getInstance()->quote($string);
    }

    /**
     * 获取数据库错误信息
     * 
     * @return array 错误信息数组
     */
    public static function errorInfo(): array
    {
        return self::getInstance()->errorInfo();
    }

    /**
     * 获取数据库错误代码
     * 
     * @return string 错误代码
     */
    public static function errorCode(): ?string
    {
        return self::getInstance()->errorCode();
    }

    /**
     * 关闭数据库连接
     * 
     * @return void
     */
    public static function close(): void
    {
        self::$instance = null;
        self::$inTransaction = false;
    }

    /**
     * 防止克隆
     */
    private function __clone()
    {
        // 单例模式，禁止克隆
    }

    /**
     * 防止反序列化
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}