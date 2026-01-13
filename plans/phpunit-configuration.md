# PHPUnit 配置文件方案

## 1. 概述

本文档详细说明了 Kindle Reading PHP 系统的 PHPUnit 配置文件设计，包括主配置文件、测试套件配置、覆盖率配置和测试运行脚本。

## 2. 主配置文件 (phpunit.xml)

### 2.1 完整配置

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         verbose="true"
         failOnRisky="true"
         failOnWarning="true"
         stopOnFailure="false"
         convertErrorsToExceptions="true"
         convertNoticesToExceptions="true"
         convertWarningsToExceptions="true"
         beStrictAboutOutputDuringTests="true"
         beStrictAboutTodoAnnotatedTests="true"
         executionOrder="random"
         resolveDependencies="true">
    
    <!-- 测试套件 -->
    <testsuites>
        <!-- 完整测试套件 -->
        <testsuite name="Complete">
            <directory suffix="Test.php">tests/Unit</directory>
            <directory suffix="Test.php">tests/Integration</directory>
            <directory suffix="Test.php">tests/API</directory>
            <directory suffix="Test.php">tests/E2E</directory>
        </testsuite>
        
        <!-- 单元测试套件 -->
        <testsuite name="Unit">
            <directory suffix="Test.php">tests/Unit/Core</directory>
            <directory suffix="Test.php">tests/Unit/Models</directory>
            <directory suffix="Test.php">tests/Unit/Services</directory>
            <directory suffix="Test.php">tests/Unit/Utils</directory>
        </testsuite>
        
        <!-- 集成测试套件 -->
        <testsuite name="Integration">
            <directory suffix="Test.php">tests/Integration/Database</directory>
            <directory suffix="Test.php">tests/Integration/OAuth</directory>
            <directory suffix="Test.php">tests/Integration/Upload</directory>
        </testsuite>
        
        <!-- API 测试套件 -->
        <testsuite name="API">
            <directory suffix="Test.php">tests/API</directory>
        </testsuite>
        
        <!-- 端到端测试套件 -->
        <testsuite name="E2E">
            <directory suffix="Test.php">tests/E2E</directory>
        </testsuite>
        
        <!-- 快速测试套件（无数据库、无外部依赖） -->
        <testsuite name="Fast">
            <directory suffix="Test.php">tests/Unit/Core</directory>
            <directory suffix="Test.php">tests/Unit/Utils</directory>
            <exclude>tests/Unit/Core/DatabaseTest.php</exclude>
        </testsuite>
    </testsuites>
    
    <!-- 测试分组 -->
    <groups>
        <group>
            <name>critical</name>
            <include>
                <directory suffix="Test.php">tests/Unit/Utils/SecurityHelperTest.php</directory>
                <directory suffix="Test.php">tests/Unit/Services/OAuthTest.php</directory>
                <directory suffix="Test.php">tests/Unit/Services/UploadServiceTest.php</directory>
            </include>
        </group>
        
        <group>
            <name>database</name>
            <include>
                <directory suffix="Test.php">tests/Integration/Database</directory>
            </include>
        </group>
        
        <group>
            <name>api</name>
            <include>
                <directory suffix="Test.php">tests/API</directory>
            </include>
        </group>
        
        <group>
            <name>slow</name>
            <include>
                <directory suffix="Test.php">tests/E2E</directory>
            </include>
        </group>
    </groups>
    
    <!-- 代码覆盖率配置 -->
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">src</directory>
        </include>
        
        <exclude>
            <directory>src/Core/Database.php</directory>
            <directory>vendor</directory>
        </exclude>
        
        <report>
            <html outputDirectory="tests/coverage/html" lowUpperBound="50" highLowerBound="80"/>
            <clover outputFile="tests/coverage/clover.xml"/>
            <text outputFile="php://stdout" showUncoveredFiles="false" showOnlySummary="true"/>
        </report>
    </coverage>
    
    <!-- PHP 配置 -->
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_DEBUG" value="true"/>
        <ini name="display_errors" value="1"/>
        <ini name="error_reporting" value="-1"/>
        <ini name="memory_limit" value="512M"/>
        <ini name="max_execution_time" value="300"/>
    </php>
    
    <!-- 日志配置 -->
    <logging>
        <junit outputFile="tests/results/junit.xml"/>
        <testdoxHtml outputFile="tests/results/testdox.html"/>
        <testdoxText outputFile="tests/results/testdox.txt"/>
    </logging>
</phpunit>
```

### 2.2 配置说明

#### 2.2.1 基本属性

| 属性 | 值 | 说明 |
|------|-----|------|
| `bootstrap` | `tests/bootstrap.php` | 测试引导文件 |
| `colors` | `true` | 启用彩色输出 |
| `verbose` | `true` | 详细输出 |
| `failOnRisky` | `true` | 有风险的测试失败 |
| `failOnWarning` | `true` | 警告视为失败 |
| `stopOnFailure` | `false` | 失败后继续运行 |
| `executionOrder` | `random` | 随机执行顺序 |
| `resolveDependencies` | `true` | 解析测试依赖 |

#### 2.2.2 测试套件

**Complete**：运行所有测试
- 包含：单元测试、集成测试、API 测试、E2E 测试
- 用途：完整测试套件

**Unit**：单元测试
- 包含：Core、Models、Services、Utils
- 用途：测试单个类和方法

**Integration**：集成测试
- 包含：Database、OAuth、Upload
- 用途：测试组件集成

**API**：API 测试
- 包含：所有 API 端点测试
- 用途：测试 API 接口

**E2E**：端到端测试
- 包含：完整用户流程测试
- 用途：测试完整场景

**Fast**：快速测试
- 包含：无数据库、无外部依赖的测试
- 用途：快速反馈

#### 2.2.3 测试分组

**critical**：关键路径测试
- SecurityHelper、OAuth、UploadService
- 覆盖率目标：90%+

**database**：数据库测试
- 所有数据库集成测试
- 需要测试数据库

**api**：API 测试
- 所有 API 端点测试
- 需要 HTTP 服务器

**slow**：慢速测试
- E2E 测试
- 执行时间较长

#### 2.2.4 代码覆盖率配置

**包含目录**：
- `src/`：所有源代码

**排除目录**：
- `src/Core/Database.php`：数据库类（难以测试）
- `vendor/`：第三方库

**报告格式**：
- HTML：`tests/coverage/html/`
- Clover XML：`tests/coverage/clover.xml`
- Text：控制台输出

**覆盖率阈值**：
- 低：50%
- 高：80%

#### 2.2.5 PHP 配置

**环境变量**：
- `APP_ENV=testing`：测试环境
- `APP_DEBUG=true`：调试模式

**PHP 配置**：
- `display_errors=1`：显示错误
- `error_reporting=-1`：报告所有错误
- `memory_limit=512M`：内存限制
- `max_execution_time=300`：执行时间限制

#### 2.2.6 日志配置

**JUnit XML**：`tests/results/junit.xml`
- 用于 CI/CD 集成

**TestDox HTML**：`tests/results/testdox.html`
- 人类可读的测试报告

**TestDox Text**：`tests/results/testdox.txt`
- 文本格式的测试报告

## 3. 测试引导文件 (tests/bootstrap.php)

### 3.1 完整代码

```php
<?php
/**
 * PHPUnit Bootstrap File
 * 
 * 初始化测试环境
 */

// 定义应用根目录
define('APP_ROOT', dirname(__DIR__));

// 定义测试环境
define('APP_ENV', 'testing');
define('APP_DEBUG', true);

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 加载 Composer 自动加载器
if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
} else {
    die('Composer dependencies not installed. Run: composer install');
}

// 加载测试环境配置
$envFile = APP_ROOT . '/.env.testing';
if (file_exists($envFile)) {
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
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// 加载应用配置
require_once APP_ROOT . '/config/config.php';

// 初始化测试数据库连接
use KindleReading\Core\Database;
use KindleReading\Core\Config;

try {
    // 获取测试数据库配置
    $dbConfig = Config::getDatabaseConfig();
    
    // 初始化数据库连接
    Database::getInstance($dbConfig);
    
    // 运行测试数据库迁移
    if (getenv('RUN_MIGRATIONS') === 'true') {
        require_once APP_ROOT . '/tests/Database/Migrations/TestMigrationRunner.php';
        TestMigrationRunner::run();
    }
} catch (\Exception $e) {
    die('Failed to initialize test database: ' . $e->getMessage());
}

// 确保测试存储目录存在
$storageDirs = [
    APP_ROOT . '/tests/storage/uploads',
    APP_ROOT . '/tests/storage/logs',
    APP_ROOT . '/tests/storage/cache',
    APP_ROOT . '/tests/storage/sessions',
    APP_ROOT . '/tests/coverage',
    APP_ROOT . '/tests/results',
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// 清理测试上传文件
$uploadDir = APP_ROOT . '/tests/storage/uploads';
if (is_dir($uploadDir)) {
    $files = glob($uploadDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

// 注册错误处理器
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// 注册异常处理器
set_exception_handler(function ($exception) {
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "Uncaught Exception: " . $exception->getMessage() . "\n";
    echo "File: " . $exception->getFile() . ":" . $exception->getLine() . "\n";
    echo str_repeat('=', 80) . "\n";
    echo $exception->getTraceAsString() . "\n";
    exit(1);
});

// 输出测试环境信息
if (getenv('PHPUNIT_VERBOSE') === 'true') {
    echo "\n";
    echo str_repeat('=', 80) . "\n";
    echo "Test Environment Initialized\n";
    echo str_repeat('=', 80) . "\n";
    echo "APP_ENV: " . APP_ENV . "\n";
    echo "APP_ROOT: " . APP_ROOT . "\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Timezone: " . date_default_timezone_get() . "\n";
    echo str_repeat('=', 80) . "\n\n";
}
```

### 3.2 功能说明

1. **定义常量**：设置应用根目录和环境
2. **错误报告**：配置错误显示和报告级别
3. **加载依赖**：加载 Composer 自动加载器
4. **加载配置**：加载测试环境配置
5. **初始化数据库**：连接测试数据库并运行迁移
6. **创建目录**：确保测试存储目录存在
7. **清理文件**：清理测试上传文件
8. **错误处理**：注册错误和异常处理器
9. **输出信息**：显示测试环境信息

## 4. 测试环境配置文件 (.env.testing)

### 4.1 完整配置

```env
# ============================================
# 测试环境配置
# ============================================

# 应用配置
APP_NAME=Kindle Reading GTK (Test)
APP_ENV=testing
APP_DEBUG=true
APP_URL=http://localhost:8000

# 时区配置
TIMEZONE=Asia/Shanghai

# ============================================
# 数据库配置
# ============================================
DB_HOST=localhost
DB_PORT=3306
DB_NAME=kindle_reading_test
DB_USER=test_user
DB_PASS=test_password
DB_CHARSET=utf8mb4

# ============================================
# 文件上传配置
# ============================================
MAX_FILE_SIZE=104857600
ALLOWED_EXTENSIONS=log,txt
UPLOAD_PATH=tests/storage/uploads

# ============================================
# 会话配置
# ============================================
SESSION_TIMEOUT=300
USER_TOKEN_LIFETIME=7200
SESSION_TOKEN_PREFIX=kr_test_
USER_TOKEN_PREFIX=ur_test_

# ============================================
# 日志配置
# ============================================
LOG_LEVEL=debug
LOG_PATH=tests/storage/logs

# ============================================
# 安全配置
# ============================================
ENCRYPTION_KEY=test-encryption-key-for-testing-only-do-not-use-in-production

# ============================================
# OAuth 配置
# ============================================
GITHUB_CLIENT_ID=test_client_id
GITHUB_CLIENT_SECRET=test_client_secret
GITHUB_REDIRECT_URI=http://localhost:8000/api/callback.php
GITHUB_SCOPE=read:user,user:email
GITHUB_STATE_LENGTH=32

# ============================================
# 测试配置
# ============================================
RUN_MIGRATIONS=true
PHPUNIT_VERBOSE=false
```

### 4.2 配置说明

**应用配置**：
- 测试环境名称和 URL
- 调试模式开启

**数据库配置**：
- 独立的测试数据库
- 测试用户和密码

**文件上传配置**：
- 测试上传目录
- 允许的文件类型

**会话配置**：
- 测试会话前缀（避免冲突）
- 较短的有效期

**日志配置**：
- Debug 级别日志
- 测试日志目录

**安全配置**：
- 测试加密密钥（仅用于测试）

**OAuth 配置**：
- 测试 GitHub 凭据
- 测试回调 URL

**测试配置**：
- 是否运行迁移
- 是否显示详细信息

## 5. 测试运行脚本

### 5.1 Composer 脚本配置

在 `composer.json` 中添加以下脚本：

```json
{
    "scripts": {
        "test": "phpunit",
        "test-fast": "phpunit --testsuite=Fast",
        "test-unit": "phpunit --testsuite=Unit",
        "test-integration": "phpunit --testsuite=Integration",
        "test-api": "phpunit --testsuite=API",
        "test-e2e": "phpunit --testsuite=E2E",
        "test-critical": "phpunit --group=critical",
        "test-coverage": "phpunit --coverage-html tests/coverage/html --coverage-clover tests/coverage/clover.xml",
        "test-coverage-text": "phpunit --coverage-text",
        "test-verbose": "phpunit --verbose",
        "test-debug": "phpunit --debug",
        "test-filter": "phpunit --filter",
        "test-watch": "phpunit-watcher watch"
    }
}
```

### 5.2 使用示例

```bash
# 运行所有测试
composer test

# 运行快速测试
composer test-fast

# 运行单元测试
composer test-unit

# 运行集成测试
composer test-integration

# 运行 API 测试
composer test-api

# 运行端到端测试
composer test-e2e

# 运行关键路径测试
composer test-critical

# 生成覆盖率报告
composer test-coverage

# 显示覆盖率文本
composer test-coverage-text

# 详细输出
composer test-verbose

# 调试模式
composer test-debug

# 过滤测试
composer test-filter "testCreateUser"

# 监视文件变化自动运行测试
composer test-watch
```

## 6. 测试数据库迁移

### 6.1 测试迁移运行器

文件：`tests/Database/Migrations/TestMigrationRunner.php`

```php
<?php
/**
 * Test Migration Runner
 * 
 * 运行测试数据库迁移
 */

namespace KindleReading\Tests\Database\Migrations;

use KindleReading\Core\Database;
use KindleReading\Core\Logger;

class TestMigrationRunner
{
    /**
     * 运行所有迁移
     */
    public static function run(): void
    {
        $pdo = Database::getInstance();
        
        // 删除所有表（如果存在）
        self::dropAllTables($pdo);
        
        // 创建所有表
        self::createTables($pdo);
        
        Logger::info('Test database migrations completed');
    }
    
    /**
     * 删除所有表
     */
    private static function dropAllTables(\PDO $pdo): void
    {
        $tables = [
            'user_tokens',
            'reading_logs',
            'kindle_devices',
            'oauth_sessions',
            'users',
        ];
        
        foreach ($tables as $table) {
            $sql = "DROP TABLE IF EXISTS `{$table}`";
            $pdo->exec($sql);
        }
    }
    
    /**
     * 创建所有表
     */
    private static function createTables(\PDO $pdo): void
    {
        // 创建用户表
        $pdo->exec("
            CREATE TABLE `users` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `github_uid` VARCHAR(255) NOT NULL,
                `username` VARCHAR(255) NOT NULL,
                `avatar_url` VARCHAR(500) NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_login_at` TIMESTAMP NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_github_uid` (`github_uid`),
                KEY `idx_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // 创建 OAuth 会话表
        $pdo->exec("
            CREATE TABLE `oauth_sessions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `session_token` VARCHAR(255) NOT NULL,
                `device_id` VARCHAR(255) NOT NULL,
                `state` VARCHAR(255) NOT NULL,
                `status` ENUM('pending', 'authorized', 'expired', 'failed') NOT NULL DEFAULT 'pending',
                `user_id` INT UNSIGNED NULL,
                `expires_at` TIMESTAMP NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_session_token` (`session_token`),
                KEY `idx_device_id` (`device_id`),
                KEY `idx_state` (`state`),
                KEY `idx_status` (`status`),
                KEY `idx_expires_at` (`expires_at`),
                KEY `idx_user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // 创建 Kindle 设备表
        $pdo->exec("
            CREATE TABLE `kindle_devices` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `device_id` VARCHAR(255) NOT NULL,
                `device_name` VARCHAR(255) NULL,
                `last_sync_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_device_id` (`device_id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_last_sync_at` (`last_sync_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // 创建阅读日志表
        $pdo->exec("
            CREATE TABLE `reading_logs` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `device_id` INT UNSIGNED NOT NULL,
                `file_path` VARCHAR(500) NOT NULL,
                `file_name` VARCHAR(255) NOT NULL,
                `file_size` BIGINT UNSIGNED NOT NULL,
                `file_hash` VARCHAR(64) NOT NULL,
                `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_file_hash` (`file_hash`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_device_id` (`device_id`),
                KEY `idx_uploaded_at` (`uploaded_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // 创建用户令牌表
        $pdo->exec("
            CREATE TABLE `user_tokens` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `token` VARCHAR(255) NOT NULL,
                `expires_at` TIMESTAMP NOT NULL,
                `revoked_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_token` (`token`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_expires_at` (`expires_at`),
                KEY `idx_revoked_at` (`revoked_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
```

## 7. 测试基类

### 7.1 数据库测试基类

文件：`tests/Helpers/DatabaseTestCase.php`

```php
<?php
/**
 * Database Test Case
 * 
 * 数据库测试基类
 */

namespace KindleReading\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use KindleReading\Core\Database;

abstract class DatabaseTestCase extends TestCase
{
    /**
     * 设置测试
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // 开始事务
        Database::beginTransaction();
    }
    
    /**
     * 清理测试
     */
    protected function tearDown(): void
    {
        // 回滚事务
        Database::rollBack();
        
        parent::tearDown();
    }
    
    /**
     * 断言表存在
     */
    protected function assertTableExists(string $tableName): void
    {
        $this->assertTrue(
            Database::tableExists($tableName),
            "Table '{$tableName}' does not exist"
        );
    }
    
    /**
     * 断言表不存在
     */
    protected function assertTableNotExists(string $tableName): void
    {
        $this->assertFalse(
            Database::tableExists($tableName),
            "Table '{$tableName}' exists"
        );
    }
    
    /**
     * 断言记录存在
     */
    protected function assertRecordExists(string $table, array $conditions): void
    {
        $where = [];
        foreach ($conditions as $key => $value) {
            $where[] = "`{$key}` = :{$key}";
        }
        
        $sql = "SELECT COUNT(*) as count FROM `{$table}` WHERE " . implode(' AND ', $where);
        $result = Database::queryOne($sql, $conditions);
        
        $this->assertGreaterThan(
            0,
            $result['count'],
            "Record not found in table '{$table}' with conditions: " . json_encode($conditions)
        );
    }
    
    /**
     * 断言记录不存在
     */
    protected function assertRecordNotExists(string $table, array $conditions): void
    {
        $where = [];
        foreach ($conditions as $key => $value) {
            $where[] = "`{$key}` = :{$key}";
        }
        
        $sql = "SELECT COUNT(*) as count FROM `{$table}` WHERE " . implode(' AND ', $where);
        $result = Database::queryOne($sql, $conditions);
        
        $this->assertEquals(
            0,
            $result['count'],
            "Record found in table '{$table}' with conditions: " . json_encode($conditions)
        );
    }
}
```

### 7.2 API 测试基类

文件：`tests/Helpers/ApiTestCase.php`

```php
<?php
/**
 * API Test Case
 * 
 * API 测试基类
 */

namespace KindleReading\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

abstract class ApiTestCase extends TestCase
{
    /**
     * HTTP 客户端
     */
    protected Client $client;
    
    /**
     * 基础 URL
     */
    protected string $baseUrl;
    
    /**
     * 访问令牌
     */
    protected ?string $accessToken = null;
    
    /**
     * 设置测试
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->baseUrl = getenv('APP_URL') ?: 'http://localhost:8000';
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'http_errors' => false,
        ]);
    }
    
    /**
     * 发送 GET 请求
     */
    protected function get(string $uri, array $headers = []): Response
    {
        return $this->client->get($uri, [
            'headers' => $this->getHeaders($headers),
        ]);
    }
    
    /**
     * 发送 POST 请求
     */
    protected function post(string $uri, array $data = [], array $headers = []): Response
    {
        return $this->client->post($uri, [
            'headers' => $this->getHeaders($headers),
            'json' => $data,
        ]);
    }
    
    /**
     * 发送 PUT 请求
     */
    protected function put(string $uri, array $data = [], array $headers = []): Response
    {
        return $this->client->put($uri, [
            'headers' => $this->getHeaders($headers),
            'json' => $data,
        ]);
    }
    
    /**
     * 发送 DELETE 请求
     */
    protected function delete(string $uri, array $headers = []): Response
    {
        return $this->client->delete($uri, [
            'headers' => $this->getHeaders($headers),
        ]);
    }
    
    /**
     * 获取请求头
     */
    protected function getHeaders(array $headers = []): array
    {
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        
        if ($this->accessToken !== null) {
            $defaultHeaders['Authorization'] = 'Bearer ' . $this->accessToken;
        }
        
        return array_merge($defaultHeaders, $headers);
    }
    
    /**
     * 设置访问令牌
     */
    protected function setAccessToken(string $token): void
    {
        $this->accessToken = $token;
    }
    
    /**
     * 断言成功响应
     */
    protected function assertSuccessResponse(Response $response): void
    {
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success'] ?? false);
    }
    
    /**
     * 断言错误响应
     */
    protected function assertErrorResponse(Response $response, int $statusCode = 400): void
    {
        $this->assertEquals($statusCode, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success'] ?? true);
    }
}
```

## 8. 总结

本 PHPUnit 配置方案为 Kindle Reading PHP 系统提供了完整的测试框架配置，包括：

1. **主配置文件**：定义测试套件、分组、覆盖率和日志
2. **测试引导文件**：初始化测试环境
3. **环境配置文件**：测试环境变量
4. **测试运行脚本**：便捷的测试命令
5. **数据库迁移**：测试数据库初始化
6. **测试基类**：提供公共测试功能

通过这些配置，开发团队可以：
- 快速运行不同类型的测试
- 生成详细的测试报告
- 集成到 CI/CD 流程
- 提高测试效率和质量