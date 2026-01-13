# Kindle Reading PHP 系统测试策略

## 1. 概述

本文档定义了 Kindle Reading PHP 系统的完整测试策略，包括测试金字塔设计、测试优先级、测试数据管理、Mock/Stub 使用策略、测试环境配置和 CI/CD 集成策略。

### 1.1 测试目标

- 确保代码质量和稳定性
- 防止回归错误
- 提高代码可维护性
- 加速开发迭代
- 建立开发信心

### 1.2 测试原则

- **快速反馈**：测试应该快速执行，提供即时反馈
- **独立性**：每个测试应该独立运行，不依赖其他测试
- **可重复性**：测试结果应该可重复，不受外部因素影响
- **可读性**：测试代码应该清晰易懂，作为文档使用
- **单一职责**：每个测试只验证一个功能点

## 2. 测试金字塔设计

### 2.1 金字塔结构

```
        E2E Tests (5%)
       /             \
      /               \
     /                 \
    /   Integration     \
   /      Tests (25%)   \
  /                       \
 /                         \
/    Unit Tests (70%)       \
------------------------------
```

### 2.2 测试层级说明

#### 2.2.1 单元测试 (70%)

**目标**：验证单个类或方法的功能

**特点**：
- 执行速度快（毫秒级）
- 无外部依赖（数据库、网络、文件系统）
- 使用 Mock 和 Stub 隔离依赖
- 覆盖所有边界条件和异常情况

**测试范围**：
- 核心组件：Config, Logger, Response
- 工具类：SecurityHelper, FileHelper
- 模型方法：User, Device, ReadingLog, OAuthSession 的业务逻辑
- 服务层：UploadService, OAuth 的核心逻辑

**示例**：
```php
// SecurityHelper::generateUuid() 测试
public function testGenerateUuidReturnsValidFormat()
{
    $uuid = SecurityHelper::generateUuid();
    $this->assertMatchesRegularExpression(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        $uuid
    );
}
```

#### 2.2.2 集成测试 (25%)

**目标**：验证多个组件协同工作的正确性

**特点**：
- 使用真实的测试数据库
- 测试数据库交互和事务
- 测试组件间的接口
- 执行速度中等（秒级）

**测试范围**：
- 模型与数据库的交互
- 服务层与模型的集成
- OAuth 完整流程（使用 Mock GitHub API）
- 文件上传流程（使用内存文件系统）

**示例**：
```php
// User 模型与数据库集成测试
public function testCreateUserInDatabase()
{
    $userId = User::create([
        'github_uid' => '123456',
        'username' => 'testuser',
        'avatar_url' => 'https://example.com/avatar.png',
    ]);
    
    $user = User::findById($userId);
    $this->assertNotNull($user);
    $this->assertEquals('testuser', $user->getUsername());
}
```

#### 2.2.3 端到端测试 (5%)

**目标**：验证完整的用户场景

**特点**：
- 测试完整的 API 端点
- 使用真实的 HTTP 请求
- 测试认证和授权流程
- 执行速度较慢（秒级到分钟级）

**测试范围**：
- 完整的 OAuth 认证流程
- 文件上传 API 端到端测试
- 用户设备管理完整流程
- 错误处理和边界情况

**示例**：
```php
// 完整的文件上传流程测试
public function testCompleteFileUploadFlow()
{
    // 1. 发起 OAuth 认证
    $authResponse = $this->post('/api/auth.php', ['device_id' => 'test-device']);
    $this->assertEquals(200, $authResponse->getStatusCode());
    
    // 2. 模拟 GitHub 回调
    $callbackResponse = $this->post('/api/callback.php', [
        'code' => 'test-code',
        'state' => $authResponse->state,
    ]);
    
    // 3. 使用返回的 token 上传文件
    $uploadResponse = $this->post('/api/upload.php', [
        'files' => $this->createTestFile(),
        'device_id' => 'test-device',
    ], [
        'Authorization' => 'Bearer ' . $callbackResponse->user_token,
    ]);
    
    $this->assertEquals(200, $uploadResponse->getStatusCode());
}
```

## 3. 测试优先级和覆盖目标

### 3.1 优先级分类

#### P0 - 关键路径（必须测试）

- 用户认证和授权
- 文件上传和验证
- 数据库事务完整性
- 安全功能（加密、哈希、CSRF）

**覆盖率目标**：90%+

#### P1 - 核心业务逻辑（重要）

- 用户管理（创建、更新、删除）
- 设备管理
- 阅读日志管理
- OAuth 流程

**覆盖率目标**：85%+

#### P2 - 辅助功能（一般）

- 日志记录
- 配置管理
- 响应格式化
- 工具类方法

**覆盖率目标**：70%+

#### P3 - 边缘情况（可选）

- 错误处理
- 边界条件
- 性能测试
- 压力测试

**覆盖率目标**：50%+

### 3.2 整体覆盖率目标

| 测试类型 | 代码覆盖率 | 行覆盖率 | 分支覆盖率 |
|---------|-----------|---------|-----------|
| 单元测试 | 70-80% | 75-85% | 65-75% |
| 集成测试 | 50-60% | 55-65% | 45-55% |
| 端到端测试 | 30-40% | 35-45% | 25-35% |
| **总体目标** | **70-80%** | **75-85%** | **65-75%** |

### 3.3 关键模块覆盖率目标

| 模块 | 优先级 | 覆盖率目标 |
|------|--------|-----------|
| SecurityHelper | P0 | 95%+ |
| OAuth | P0 | 90%+ |
| UploadService | P0 | 90%+ |
| User Model | P1 | 85%+ |
| Device Model | P1 | 85%+ |
| ReadingLog Model | P1 | 85%+ |
| Database | P1 | 80%+ |
| Config | P2 | 75%+ |
| Logger | P2 | 70%+ |
| Response | P2 | 70%+ |
| FileHelper | P2 | 70%+ |

## 4. 测试数据管理策略

### 4.1 Fixtures（固定测试数据）

**用途**：用于单元测试和简单的集成测试

**特点**：
- 静态数据，不随测试改变
- 快速加载
- 易于维护

**存储位置**：`tests/Fixtures/`

**示例结构**：
```
tests/Fixtures/
├── users.php
├── devices.php
├── reading_logs.php
├── oauth_sessions.php
└── sample_files/
    ├── valid_log.txt
    ├── invalid_log.php
    └── large_log.txt
```

**示例代码**：
```php
// tests/Fixtures/users.php
return [
    'valid_user' => [
        'github_uid' => '123456',
        'username' => 'testuser',
        'avatar_url' => 'https://example.com/avatar.png',
    ],
    'admin_user' => [
        'github_uid' => '789012',
        'username' => 'admin',
        'avatar_url' => 'https://example.com/admin.png',
    ],
];
```

### 4.2 Factories（测试数据工厂）

**用途**：动态生成测试数据，支持参数化

**特点**：
- 灵活生成数据
- 支持关联数据
- 可配置状态

**存储位置**：`tests/Factories/`

**示例结构**：
```
tests/Factories/
├── UserFactory.php
├── DeviceFactory.php
├── ReadingLogFactory.php
└── OAuthSessionFactory.php
```

**示例代码**：
```php
// tests/Factories/UserFactory.php
class UserFactory
{
    public static function create(array $overrides = []): array
    {
        return array_merge([
            'github_uid' => (string)random_int(100000, 999999),
            'username' => 'user_' . random_int(1000, 9999),
            'avatar_url' => 'https://example.com/avatar.png',
        ], $overrides);
    }
    
    public static function createInDatabase(array $overrides = []): User
    {
        $data = self::create($overrides);
        $userId = User::create($data);
        return User::findById($userId);
    }
}
```

### 4.3 数据库迁移和种子

**用途**：为集成测试准备测试数据库

**策略**：
- 每次测试前清空测试数据库
- 运行迁移脚本创建表结构
- 可选：加载种子数据

**存储位置**：
- 迁移：`database/migrations/`
- 测试种子：`tests/Database/Seeds/`

**示例结构**：
```
database/migrations/
├── 2024_01_01_000001_create_users_table.php
├── 2024_01_01_000002_create_kindle_devices_table.php
├── 2024_01_01_000003_create_reading_logs_table.php
└── 2024_01_01_000004_create_oauth_sessions_table.php

tests/Database/
├── Migrations/
│   └── TestMigrationRunner.php
└── Seeds/
    ├── UserSeeder.php
    └── DeviceSeeder.php
```

### 4.4 测试数据清理策略

**策略**：
1. **事务回滚**：每个测试在事务中执行，测试后回滚
2. **数据库重置**：测试套件开始时重置数据库
3. **文件清理**：测试后清理上传的测试文件

**示例代码**：
```php
abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Database::beginTransaction();
    }
    
    protected function tearDown(): void
    {
        Database::rollBack();
        parent::tearDown();
    }
}
```

## 5. Mock 和 Stub 使用策略

### 5.1 使用原则

**何时使用 Mock**：
- 需要验证方法调用
- 需要控制返回值
- 需要模拟异常情况
- 外部依赖（API、文件系统）

**何时使用 Stub**：
- 只需要提供返回值
- 不需要验证调用
- 简化测试设置

### 5.2 Mock 策略

#### 5.2.1 数据库 Mock（单元测试）

```php
public function testUserCreationWithMockedDatabase()
{
    // Mock Database 类
    $databaseMock = $this->createMock(Database::class);
    $databaseMock->expects($this->once())
        ->method('insert')
        ->willReturn('123');
    
    // 使用 Mock 替换真实 Database
    Database::setInstance($databaseMock);
    
    $userId = User::create([
        'github_uid' => '123456',
        'username' => 'testuser',
    ]);
    
    $this->assertEquals('123', $userId);
}
```

#### 5.2.2 外部 API Mock（集成测试）

```php
public function testOAuthWithMockedGitHubApi()
{
    // Mock OAuth 的 HTTP 请求
    $oauthMock = $this->getMockBuilder(OAuth::class)
        ->setConstructorArgs([null])
        ->onlyMethods(['makeRequest'])
        ->getMock();
    
    $oauthMock->method('makeRequest')
        ->willReturnOnConsecutiveCalls(
            json_encode(['access_token' => 'test_token']),
            json_encode(['id' => '123456', 'login' => 'testuser'])
        );
    
    $result = $oauthMock->handleCallback('test_code', 'test_state');
    
    $this->assertNotNull($result);
    $this->assertEquals('testuser', $result['user']->getUsername());
}
```

#### 5.2.3 文件系统 Mock

```php
public function testFileUploadWithMockedFileSystem()
{
    // 使用 vfsStream 创建虚拟文件系统
    vfsStream::setup('uploads');
    
    $uploadService = new UploadService();
    $uploadService->setUploadPath(vfsStream::url('uploads'));
    
    $file = [
        'name' => 'test.log',
        'tmp_name' => vfsStream::url('uploads/test.log'),
        'size' => 1024,
        'error' => UPLOAD_ERR_OK,
    ];
    
    $result = $uploadService->uploadFile($file, 1, 1);
    
    $this->assertTrue($result['success']);
}
```

### 5.3 Stub 策略

```php
public function testConfigWithStub()
{
    // Stub Config 类
    $configStub = $this->createStub(Config::class);
    $configStub->method('get')
        ->willReturn('test_value');
    
    // 使用 Stub
    $value = $configStub->get('some.key');
    $this->assertEquals('test_value', $value);
}
```

### 5.4 Mock/Stub 最佳实践

1. **不要过度 Mock**：只在必要时使用 Mock
2. **保持简单**：Mock 应该简单易懂
3. **验证行为**：使用 Mock 验证方法调用
4. **隔离依赖**：每个测试只 Mock 相关的依赖
5. **使用测试替身库**：考虑使用 Mockery 或 Prophecy

## 6. 测试环境配置

### 6.1 测试数据库配置

**数据库类型**：MySQL（与生产环境一致）

**配置文件**：`.env.testing`

```env
# 测试环境配置
APP_ENV=testing
APP_DEBUG=true

# 测试数据库
DB_HOST=localhost
DB_PORT=3306
DB_NAME=kindle_reading_test
DB_USER=test_user
DB_PASS=test_password
DB_CHARSET=utf8mb4

# 测试文件存储
UPLOAD_PATH=tests/storage/uploads
LOG_PATH=tests/storage/logs
CACHE_PATH=tests/storage/cache
SESSION_PATH=tests/storage/sessions

# 测试 OAuth 配置
GITHUB_CLIENT_ID=test_client_id
GITHUB_CLIENT_SECRET=test_client_secret
GITHUB_REDIRECT_URI=http://localhost:8000/api/callback.php

# 测试安全配置
ENCRYPTION_KEY=test-encryption-key-for-testing-only
```

### 6.2 测试文件存储

**目录结构**：
```
tests/storage/
├── uploads/          # 测试上传文件
├── logs/             # 测试日志
├── cache/            # 测试缓存
└── sessions/         # 测试会话
```

**清理策略**：
- 每次测试后清理上传文件
- 定期清理日志文件
- 测试套件开始时清空所有目录

### 6.3 测试环境隔离

**策略**：
1. 使用独立的测试数据库
2. 使用独立的文件存储目录
3. 使用独立的配置文件
4. 使用独立的日志文件

**好处**：
- 测试不影响开发环境
- 测试可以并行运行
- 易于清理和重置

### 6.4 测试环境初始化

**Bootstrap 文件**：`tests/bootstrap.php`

```php
<?php
// 设置测试环境
define('APP_ENV', 'testing');
define('APP_ROOT', dirname(__DIR__));

// 加载自动加载器
require_once APP_ROOT . '/vendor/autoload.php';

// 加载测试环境配置
$envFile = APP_ROOT . '/.env.testing';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        putenv(sprintf('%s=%s', trim($name), trim($value)));
    }
}

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 初始化测试数据库连接
use KindleReading\Core\Database;
Database::getInstance();

// 确保测试存储目录存在
$storageDirs = [
    APP_ROOT . '/tests/storage/uploads',
    APP_ROOT . '/tests/storage/logs',
    APP_ROOT . '/tests/storage/cache',
    APP_ROOT . '/tests/storage/sessions',
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
```

## 7. CI/CD 集成策略

### 7.1 CI 流程

```mermaid
graph LR
    A[代码提交] --> B[触发 CI]
    B --> C[安装依赖]
    C --> D[代码规范检查]
    D --> E[快速测试套件]
    E --> F[完整测试套件]
    F --> G[生成覆盖率报告]
    G --> H[部署到测试环境]
    H --> I[端到端测试]
    I --> J[部署到生产环境]
```

### 7.2 GitHub Actions 配置

**文件**：`.github/workflows/tests.yml`

```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: ['8.0', '8.1', '8.2']
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: kindle_reading_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php-version }}
        extensions: mbstring, pdo, pdo_mysql, curl, openssl
        coverage: xdebug
    
    - name: Copy .env.testing
      run: cp .env.example .env.testing
    
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
    
    - name: Run code style check
      run: composer cs-check
    
    - name: Run fast tests
      run: composer test-fast
    
    - name: Run full tests
      run: composer test
    
    - name: Generate coverage report
      run: composer test-coverage
    
    - name: Upload coverage to Codecov
      uses: codecov/codecov-action@v3
      with:
        files: ./coverage.xml
```

### 7.3 测试阶段划分

#### 阶段 1：快速测试（每次提交）

- 运行时间：< 30 秒
- 测试范围：单元测试（无数据库）
- 触发条件：每次 push 和 PR

```bash
composer test-fast
```

#### 阶段 2：完整测试（合并前）

- 运行时间：< 5 分钟
- 测试范围：所有单元测试和集成测试
- 触发条件：PR 合并前

```bash
composer test
```

#### 阶段 3：端到端测试（部署前）

- 运行时间：< 10 分钟
- 测试范围：完整的 API 测试
- 触发条件：部署到生产环境前

```bash
composer test-e2e
```

### 7.4 测试报告

#### 7.4.1 覆盖率报告

**工具**：PHPUnit + Xdebug

**输出格式**：
- HTML：`tests/coverage/html/`
- Clover XML：`tests/coverage/clover.xml`
- Text：控制台输出

**查看方式**：
```bash
# 生成覆盖率报告
composer test-coverage

# 在浏览器中查看
open tests/coverage/html/index.html
```

#### 7.4.2 测试结果报告

**工具**：PHPUnit

**输出格式**：
- JUnit XML：`tests/results/junit.xml`
- 控制台输出

**集成**：
- GitHub Actions 自动显示测试结果
- 失败的测试会阻止合并

### 7.5 质量门禁

**通过标准**：
1. 所有测试必须通过
2. 代码覆盖率 >= 70%
3. 关键模块覆盖率 >= 85%
4. 代码规范检查通过
5. 无安全漏洞

**失败处理**：
- 阻止代码合并
- 发送通知给开发者
- 生成详细报告

## 8. 测试命名和组织规范

### 8.1 测试文件命名

**规则**：`{ClassName}Test.php`

**示例**：
- `UserTest.php` - 测试 User 模型
- `UploadServiceTest.php` - 测试 UploadService
- `SecurityHelperTest.php` - 测试 SecurityHelper

### 8.2 测试方法命名

**规则**：`test{MethodName}{Scenario}`

**示例**：
```php
public function testCreateUserWithValidData()
public function testCreateUserWithInvalidData()
public function testCreateUserWithDuplicateGithubUid()
public function testGenerateAccessTokenWithDefaultExpiration()
public function testGenerateAccessTokenWithCustomExpiration()
```

### 8.3 测试组织结构

```
tests/
├── Unit/                    # 单元测试
│   ├── Core/
│   │   ├── ConfigTest.php
│   │   ├── DatabaseTest.php
│   │   ├── LoggerTest.php
│   │   └── ResponseTest.php
│   ├── Models/
│   │   ├── UserTest.php
│   │   ├── DeviceTest.php
│   │   ├── ReadingLogTest.php
│   │   └── OAuthSessionTest.php
│   ├── Services/
│   │   ├── UploadServiceTest.php
│   │   └── OAuthTest.php
│   └── Utils/
│       ├── SecurityHelperTest.php
│       └── FileHelperTest.php
├── Integration/             # 集成测试
│   ├── Database/
│   │   ├── UserIntegrationTest.php
│   │   ├── DeviceIntegrationTest.php
│   │   └── ReadingLogIntegrationTest.php
│   ├── OAuth/
│   │   └── OAuthFlowTest.php
│   └── Upload/
│       └── FileUploadTest.php
├── API/                     # API 测试
│   ├── AuthApiTest.php
│   ├── CallbackApiTest.php
│   ├── UploadApiTest.php
│   ├── UserApiTest.php
│   └── LogsApiTest.php
├── E2E/                     # 端到端测试
│   ├── CompleteOAuthFlowTest.php
│   ├── CompleteUploadFlowTest.php
│   └── CompleteUserFlowTest.php
├── Fixtures/                # 测试数据
│   ├── users.php
│   ├── devices.php
│   └── sample_files/
├── Factories/               # 测试数据工厂
│   ├── UserFactory.php
│   ├── DeviceFactory.php
│   └── ReadingLogFactory.php
├── Helpers/                 # 测试辅助类
│   ├── DatabaseTestCase.php
│   ├── ApiTestCase.php
│   └── MockHelper.php
├── storage/                 # 测试存储
│   ├── uploads/
│   ├── logs/
│   ├── cache/
│   └── sessions/
├── bootstrap.php            # 测试引导文件
└── phpunit.xml              # PHPUnit 配置
```

## 9. 测试最佳实践

### 9.1 编写可维护的测试

1. **使用测试基类**：提取公共设置和清理逻辑
2. **使用辅助方法**：减少重复代码
3. **使用数据提供器**：参数化测试
4. **使用断言消息**：提供清晰的错误信息

### 9.2 编写快速的测试

1. **避免睡眠**：使用 Mock 代替等待
2. **使用内存数据库**：加速数据库测试
3. **并行运行测试**：使用 PHPUnit 的并行测试功能
4. **隔离测试**：避免测试间的依赖

### 9.3 编写可靠的测试

1. **清理测试数据**：确保测试后环境干净
2. **使用固定数据**：避免随机性
3. **处理异常**：测试异常情况
4. **验证边界**：测试边界条件

### 9.4 编写有意义的测试

1. **测试行为而非实现**：关注功能而非细节
2. **使用描述性名称**：测试名称应该说明测试内容
3. **一个测试一个断言**：保持测试简单
4. **测试用户场景**：从用户角度编写测试

## 10. 测试工具和库

### 10.1 核心工具

- **PHPUnit 9.5**：测试框架
- **Xdebug**：代码覆盖率
- **PHP_CodeSniffer**：代码规范检查

### 10.2 推荐库

- **Mockery**：强大的 Mock 库
- **Faker**：生成测试数据
- **vfsStream**：虚拟文件系统
- **Guzzle**：HTTP 客户端（用于 API 测试）

### 10.3 可选工具

- **Infection**：变异测试
- **PHPStan**：静态分析
- **Psalm**：静态分析
- **PHPUnit Slow Test Detector**：检测慢测试

## 11. 总结

本测试策略为 Kindle Reading PHP 系统提供了完整的测试框架和指导原则。通过遵循本策略，团队可以：

1. 建立高质量的测试套件
2. 提高代码质量和稳定性
3. 加速开发迭代
4. 建立开发信心
5. 降低维护成本

测试是一个持续改进的过程，团队应该定期回顾和优化测试策略，确保其与项目需求保持一致。