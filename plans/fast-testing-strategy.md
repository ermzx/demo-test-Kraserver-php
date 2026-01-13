# 快速测试方案

## 1. 概述

本文档详细说明了 Kindle Reading PHP 系统的快速测试方案，包括快速测试的定义、测试范围、组织方式、运行策略和最佳实践。

## 2. 快速测试定义

### 2.1 什么是快速测试

快速测试是指执行时间短、无外部依赖、可以频繁运行的测试套件。

**特点**：
- 执行时间：< 30 秒
- 无数据库依赖
- 无网络依赖
- 无文件系统依赖（或使用虚拟文件系统）
- 使用 Mock 和 Stub 隔离所有外部依赖

**目的**：
- 提供即时反馈
- 支持频繁运行（每次保存后）
- 集成到开发工作流
- 提高开发效率

### 2.2 快速测试 vs 完整测试

| 特性 | 快速测试 | 完整测试 |
|------|---------|---------|
| 执行时间 | < 30 秒 | 2-5 分钟 |
| 数据库 | 无 | 有 |
| 网络 | 无 | 有 |
| 文件系统 | 虚拟或无 | 真实 |
| 测试范围 | 核心逻辑 | 全部功能 |
| 运行频率 | 每次保存 | 每次提交 |
| 反馈速度 | 即时 | 延迟 |

## 3. 快速测试范围

### 3.1 包含的测试

#### 3.1.1 核心组件测试

**Config 类测试**：
- 配置加载
- 配置获取和设置
- 环境变量解析
- 嵌套配置访问
- 默认值处理

**Logger 类测试**（使用 Mock 文件系统）：
- 日志级别设置
- 日志格式化
- 日志消息构建
- 日志上下文处理

**Response 类测试**：
- JSON 响应生成
- HTTP 状态码设置
- 响应头设置
- 错误响应生成
- 成功响应生成

#### 3.1.2 工具类测试

**SecurityHelper 类测试**：
- UUID 生成和验证
- 随机字符串生成
- 令牌生成
- 哈希计算
- 加密和解密
- 输入清理
- 文件名清理
- 验证方法（IP、邮箱、URL）

**FileHelper 类测试**（使用虚拟文件系统）：
- 文件扩展名获取
- 文件大小格式化
- 路径处理
- 目录创建（虚拟）

#### 3.1.3 模型测试（使用 Mock 数据库）

**User 模型测试**：
- 用户数据填充
- 用户数据转换
- 令牌生成逻辑（不实际生成）
- 数据验证逻辑

**Device 模型测试**：
- 设备数据填充
- 设备数据转换
- 数据验证逻辑

**ReadingLog 模型测试**：
- 日志数据填充
- 日志数据转换
- 数据验证逻辑

**OAuthSession 模型测试**：
- 会话数据填充
- 会话数据转换
- 过期检查逻辑
- 状态验证逻辑

#### 3.1.4 服务层测试（使用 Mock）

**UploadService 测试**（使用虚拟文件系统）：
- 文件验证逻辑
- 文件类型检测
- 文件大小验证
- 文件内容验证
- 错误处理逻辑

**OAuth 测试**（使用 Mock HTTP）：
- 授权 URL 生成
- 用户数据处理
- 状态验证逻辑
- 错误处理逻辑

### 3.2 排除的测试

以下测试**不包含**在快速测试中：

- 数据库集成测试
- API 端点测试
- 端到端测试
- 文件上传集成测试
- OAuth 完整流程测试
- 任何需要真实数据库的测试
- 任何需要网络请求的测试
- 任何需要真实文件系统的测试

## 4. 快速测试组织

### 4.1 测试套件配置

在 `phpunit.xml` 中定义快速测试套件：

```xml
<testsuites>
    <!-- 快速测试套件 -->
    <testsuite name="Fast">
        <directory suffix="Test.php">tests/Unit/Core</directory>
        <directory suffix="Test.php">tests/Unit/Utils</directory>
        <exclude>tests/Unit/Core/DatabaseTest.php</exclude>
    </testsuite>
</testsuites>
```

### 4.2 测试分组

使用 `@group` 注解标记快速测试：

```php
/**
 * @group fast
 * @group unit
 */
class ConfigTest extends TestCase
{
    /**
     * @group fast
     */
    public function testGetConfigValue()
    {
        // 测试代码
    }
}
```

### 4.3 测试文件组织

```
tests/Unit/
├── Core/
│   ├── ConfigTest.php          # ✅ 包含
│   ├── DatabaseTest.php        # ❌ 排除（需要数据库）
│   ├── LoggerTest.php          # ✅ 包含
│   └── ResponseTest.php        # ✅ 包含
├── Models/
│   ├── UserTest.php            # ✅ 包含（使用 Mock）
│   ├── DeviceTest.php          # ✅ 包含（使用 Mock）
│   ├── ReadingLogTest.php      # ✅ 包含（使用 Mock）
│   └── OAuthSessionTest.php    # ✅ 包含（使用 Mock）
├── Services/
│   ├── UploadServiceTest.php   # ✅ 包含（使用虚拟文件系统）
│   └── OAuthTest.php           # ✅ 包含（使用 Mock HTTP）
└── Utils/
    ├── SecurityHelperTest.php  # ✅ 包含
    └── FileHelperTest.php      # ✅ 包含（使用虚拟文件系统）
```

## 5. 快速测试运行策略

### 5.1 Composer 脚本

在 `composer.json` 中添加快速测试脚本：

```json
{
    "scripts": {
        "test-fast": "phpunit --testsuite=Fast",
        "test-fast-verbose": "phpunit --testsuite=Fast --verbose",
        "test-fast-debug": "phpunit --testsuite=Fast --debug",
        "test-fast-filter": "phpunit --testsuite=Fast --filter"
    }
}
```

### 5.2 运行命令

```bash
# 运行快速测试
composer test-fast

# 详细输出
composer test-fast-verbose

# 调试模式
composer test-fast-debug

# 过滤测试
composer test-fast-filter "testGenerateUuid"
```

### 5.3 运行时机

#### 5.3.1 开发期间

- 每次保存文件后自动运行
- 使用文件监视器自动触发

#### 5.3.2 提交前

- 作为 pre-commit hook 运行
- 确保核心逻辑没有破坏

#### 5.3.3 CI/CD 中

- 作为第一阶段运行
- 快速失败，节省时间

## 6. 文件监视器配置

### 6.1 使用 PHPUnit Watcher

安装 PHPUnit Watcher：

```bash
composer require --dev spatie/phpunit-watcher
```

配置 `phpunit-watcher.yml`：

```yaml
watch:
  directories:
    - src
  fileMask: '*.php'
  tests:
    directory: tests
    fileMask: '*Test.php'
  annotations:
    - group: fast
```

运行文件监视器：

```bash
composer test-watch
```

### 6.2 使用 nodemon

安装 nodemon：

```bash
npm install -g nodemon
```

配置 `nodemon.json`：

```json
{
  "watch": ["src", "tests/Unit"],
  "ext": "php",
  "exec": "composer test-fast"
}

运行：

```bash
nodemon
```

### 6.3 使用 entr（Linux/macOS）

安装 entr：

```bash
# macOS
brew install entr

# Linux
sudo apt-get install entr
```

运行：

```bash
find src tests/Unit -name "*.php" | entr -r composer test-fast
```

## 7. 快速测试最佳实践

### 7.1 编写快速测试

#### 7.1.1 使用 Mock 和 Stub

```php
public function testConfigGetValue()
{
    // Mock Config 类
    $configMock = $this->createMock(Config::class);
    $configMock->method('get')
        ->willReturn('test_value');
    
    $value = $configMock->get('some.key');
    $this->assertEquals('test_value', $value);
}
```

#### 7.1.2 使用虚拟文件系统

```php
use org\bovigo\vfs\vfsStream;

public function testFileHelperGetExtension()
{
    // 不需要真实文件系统
    $extension = FileHelper::getExtension('test.log');
    $this->assertEquals('log', $extension);
}

public function testFileUploadWithVirtualFileSystem()
{
    // 使用虚拟文件系统
    vfsStream::setup('uploads');
    
    $uploadService = new UploadService();
    $uploadService->setUploadPath(vfsStream::url('uploads'));
    
    // 测试文件上传
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

#### 7.1.3 避免数据库操作

```php
// ❌ 不好的做法（需要数据库）
public function testCreateUser()
{
    $userId = User::create([
        'github_uid' => '123456',
        'username' => 'testuser',
    ]);
    $this->assertGreaterThan(0, $userId);
}

// ✅ 好的做法（使用 Mock）
public function testCreateUserWithMockedDatabase()
{
    $databaseMock = $this->createMock(Database::class);
    $databaseMock->expects($this->once())
        ->method('insert')
        ->willReturn('123');
    
    Database::setInstance($databaseMock);
    
    $userId = User::create([
        'github_uid' => '123456',
        'username' => 'testuser',
    ]);
    
    $this->assertEquals('123', $userId);
}
```

### 7.2 保持测试快速

#### 7.2.1 避免睡眠

```php
// ❌ 不好的做法
public function testTokenExpiration()
{
    $token = $user->generateAccessToken();
    sleep(7200); // 等待 2 小时
    $this->assertNull(User::findByToken($token));
}

// ✅ 好的做法（使用 Mock）
public function testTokenExpirationWithMock()
{
    $user = UserFactory::createInDatabase();
    
    // Mock 时间
    $currentTime = time();
    $expiresAt = $currentTime + 7200;
    
    // 验证过期时间
    $token = $user->generateAccessToken();
    $this->assertGreaterThan($currentTime, $expiresAt);
}
```

#### 7.2.2 避免网络请求

```php
// ❌ 不好的做法（真实网络请求）
public function testOAuthGetUserInfo()
{
    $oauth = new OAuth();
    $userInfo = $oauth->getUserInfo('real_token');
    $this->assertNotNull($userInfo);
}

// ✅ 好的做法（使用 Mock）
public function testOAuthGetUserInfoWithMock()
{
    $oauthMock = $this->getMockBuilder(OAuth::class)
        ->onlyMethods(['makeRequest'])
        ->getMock();
    
    $oauthMock->method('makeRequest')
        ->willReturn(json_encode([
            'id' => '123456',
            'login' => 'testuser',
        ]));
    
    $userInfo = $oauthMock->getUserInfo('test_token');
    $this->assertEquals('testuser', $userInfo['login']);
}
```

#### 7.2.3 避免文件 I/O

```php
// ❌ 不好的做法（真实文件 I/O）
public function testFileUpload()
{
    file_put_contents('/tmp/test.log', 'test content');
    $file = [
        'name' => 'test.log',
        'tmp_name' => '/tmp/test.log',
        'size' => 12,
        'error' => UPLOAD_ERR_OK,
    ];
    $result = $uploadService->uploadFile($file, 1, 1);
    $this->assertTrue($result['success']);
}

// ✅ 好的做法（使用虚拟文件系统）
public function testFileUploadWithVirtualFileSystem()
{
    vfsStream::setup('uploads');
    vfsStream::newFile('test.log')
        ->withContent('test content')
        ->at(vfsStream::url('uploads'));
    
    $file = [
        'name' => 'test.log',
        'tmp_name' => vfsStream::url('uploads/test.log'),
        'size' => 12,
        'error' => UPLOAD_ERR_OK,
    ];
    
    $result = $uploadService->uploadFile($file, 1, 1);
    $this->assertTrue($result['success']);
}
```

### 7.3 测试隔离

#### 7.3.1 每个测试独立

```php
// ✅ 好的做法
public function testCreateUser()
{
    $data = UserFactory::create();
    $userId = User::create($data);
    $this->assertGreaterThan(0, $userId);
}

public function testFindUser()
{
    $user = UserFactory::createInDatabase();
    $foundUser = User::findById($user->getId());
    $this->assertNotNull($foundUser);
}

// ❌ 不好的做法（测试依赖）
public function testCreateUser()
{
    $this->userId = User::create([...]);
}

public function testFindUser()
{
    $user = User::findById($this->userId); // 依赖上一个测试
    $this->assertNotNull($user);
}
```

#### 7.3.2 清理测试数据

```php
protected function setUp(): void
{
    parent::setUp();
    // 设置测试环境
}

protected function tearDown(): void
{
    // 清理测试数据
    MockHelper::clearAllMocks();
    parent::tearDown();
}
```

## 8. 快速测试示例

### 8.1 Config 测试示例

```php
<?php
namespace KindleReading\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use KindleReading\Core\Config;

/**
 * @group fast
 * @group unit
 */
class ConfigTest extends TestCase
{
    private Config $config;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new Config();
    }
    
    /**
     * @group fast
     */
    public function testGetConfigValue()
    {
        $value = $this->config->get('app_name');
        $this->assertEquals('Kindle Reading GTK', $value);
    }
    
    /**
     * @group fast
     */
    public function testGetConfigValueWithDefault()
    {
        $value = $this->config->get('nonexistent.key', 'default');
        $this->assertEquals('default', $value);
    }
    
    /**
     * @group fast
     */
    public function testSetConfigValue()
    {
        $this->config->set('test.key', 'test_value');
        $value = $this->config->get('test.key');
        $this->assertEquals('test_value', $value);
    }
    
    /**
     * @group fast
     */
    public function testGetNestedConfigValue()
    {
        $value = $this->config->get('upload.max_file_size');
        $this->assertIsInt($value);
    }
}
```

### 8.2 SecurityHelper 测试示例

```php
<?php
namespace KindleReading\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use KindleReading\Utils\SecurityHelper;

/**
 * @group fast
 * @group unit
 */
class SecurityHelperTest extends TestCase
{
    /**
     * @group fast
     */
    public function testGenerateUuidReturnsValidFormat()
    {
        $uuid = SecurityHelper::generateUuid();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
    }
    
    /**
     * @group fast
     */
    public function testGenerateUuidReturnsUniqueValues()
    {
        $uuid1 = SecurityHelper::generateUuid();
        $uuid2 = SecurityHelper::generateUuid();
        $this->assertNotEquals($uuid1, $uuid2);
    }
    
    /**
     * @group fast
     */
    public function testGenerateRandomStringReturnsCorrectLength()
    {
        $length = 32;
        $string = SecurityHelper::generateRandomString($length);
        $this->assertEquals($length, strlen($string));
    }
    
    /**
     * @group fast
     */
    public function testGenerateTokenReturnsHexadecimal()
    {
        $token = SecurityHelper::generateToken(64);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }
    
    /**
     * @group fast
     */
    public function testHashReturnsConsistentValue()
    {
        $data = 'test data';
        $hash1 = SecurityHelper::hash($data);
        $hash2 = SecurityHelper::hash($data);
        $this->assertEquals($hash1, $hash2);
    }
    
    /**
     * @group fast
     */
    public function testSanitizeInputEscapesHtml()
    {
        $input = '<script>alert("xss")</script>';
        $sanitized = SecurityHelper::sanitizeInput($input);
        $this->assertStringNotContainsString('<script>', $sanitized);
    }
}
```

### 8.3 Response 测试示例

```php
<?php
namespace KindleReading\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use KindleReading\Core\Response;

/**
 * @group fast
 * @group unit
 */
class ResponseTest extends TestCase
{
    /**
     * @group fast
     */
    public function testSuccessReturnsCorrectStructure()
    {
        $response = Response::success(['data' => 'test'], 'Success message');
        
        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertEquals('Success message', $response['message']);
        $this->assertEquals(['data' => 'test'], $response['data']);
    }
    
    /**
     * @group fast
     */
    public function testErrorReturnsCorrectStructure()
    {
        $response = Response::error('Error message', 400);
        
        $this->assertIsArray($response);
        $this->assertFalse($response['success']);
        $this->assertEquals('Error message', $response['message']);
        $this->assertEquals(400, $response['code']);
    }
    
    /**
     * @group fast
     */
    public function testValidationErrorReturnsCorrectStructure()
    {
        $errors = ['field' => 'error message'];
        $response = Response::validationError($errors);
        
        $this->assertIsArray($response);
        $this->assertFalse($response['success']);
        $this->assertEquals('Validation failed', $response['message']);
        $this->assertEquals($errors, $response['errors']);
    }
}
```

## 9. 快速测试性能优化

### 9.1 测试性能监控

使用 PHPUnit 的 `--testdox` 和 `--log-junit` 选项监控测试性能：

```bash
phpunit --testsuite=Fast --testdox --log-junit=tests/results/fast-tests.xml
```

分析测试结果，识别慢速测试。

### 9.2 并行测试

使用 PHPUnit 的并行测试功能：

```bash
phpunit --testsuite=Fast --parallel
```

### 9.3 测试缓存

使用 PHPUnit 的测试缓存：

```bash
phpunit --testsuite=Fast --cache-result
```

### 9.4 减少测试数量

定期审查快速测试套件，移除不必要的测试：

- 移除重复的测试
- 合并相似的测试
- 移除过时的测试

## 10. 快速测试集成

### 10.1 Pre-commit Hook

创建 `.git/hooks/pre-commit`：

```bash
#!/bin/bash

echo "Running fast tests..."
composer test-fast

if [ $? -ne 0 ]; then
    echo "Fast tests failed. Commit aborted."
    exit 1
fi

echo "Fast tests passed!"
exit 0
```

### 10.2 IDE 集成

#### VS Code

创建 `.vscode/tasks.json`：

```json
{
    "version": "2.0.0",
    "tasks": [
        {
            "label": "Run Fast Tests",
            "type": "shell",
            "command": "composer test-fast",
            "group": {
                "kind": "test",
                "isDefault": true
            },
            "problemMatcher": []
        }
    ]
}
```

#### PhpStorm

1. 打开 Run/Debug Configurations
2. 添加 PHPUnit 配置
3. 设置测试套件为 "Fast"
4. 设置快捷键（如 Ctrl+Shift+F）

### 10.3 CI/CD 集成

在 CI/CD 流程中首先运行快速测试：

```yaml
# GitHub Actions
- name: Run fast tests
  run: composer test-fast

- name: Run full tests
  if: success()
  run: composer test
```

## 11. 快速测试指标

### 11.1 关键指标

| 指标 | 目标 | 说明 |
|------|------|------|
| 执行时间 | < 30 秒 | 快速测试总执行时间 |
| 测试数量 | 50-100 | 快速测试数量 |
| 通过率 | 100% | 快速测试通过率 |
| 覆盖率 | 40-50% | 快速测试代码覆盖率 |

### 11.2 监控指标

定期监控以下指标：

- 测试执行时间趋势
- 测试数量变化
- 失败率
- 覆盖率变化

## 12. 总结

本快速测试方案为 Kindle Reading PHP 系统提供了完整的快速测试策略：

1. **明确定义**：快速测试的范围和特点
2. **组织方式**：测试套件和分组
3. **运行策略**：运行时机和命令
4. **最佳实践**：编写快速测试的指导
5. **性能优化**：提高测试执行速度
6. **集成方式**：集成到开发工作流

通过实施此快速测试方案，开发团队可以：
- 获得即时反馈
- 提高开发效率
- 减少回归错误
- 建立开发信心
- 加速迭代周期