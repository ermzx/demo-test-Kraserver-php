# 测试目录结构方案

## 1. 概述

本文档详细说明了 Kindle Reading PHP 系统的测试目录结构设计，包括目录组织、文件命名规范、测试分类和最佳实践。

## 2. 完整目录结构

```
tests/
├── Unit/                           # 单元测试
│   ├── Core/                       # 核心组件测试
│   │   ├── ConfigTest.php
│   │   ├── DatabaseTest.php
│   │   ├── LoggerTest.php
│   │   └── ResponseTest.php
│   ├── Models/                     # 模型测试
│   │   ├── UserTest.php
│   │   ├── DeviceTest.php
│   │   ├── ReadingLogTest.php
│   │   └── OAuthSessionTest.php
│   ├── Services/                   # 服务层测试
│   │   ├── UploadServiceTest.php
│   │   └── OAuthTest.php
│   └── Utils/                      # 工具类测试
│       ├── SecurityHelperTest.php
│       └── FileHelperTest.php
├── Integration/                    # 集成测试
│   ├── Database/                   # 数据库集成测试
│   │   ├── UserIntegrationTest.php
│   │   ├── DeviceIntegrationTest.php
│   │   ├── ReadingLogIntegrationTest.php
│   │   └── OAuthSessionIntegrationTest.php
│   ├── OAuth/                      # OAuth 集成测试
│   │   └── OAuthFlowTest.php
│   └── Upload/                     # 上传集成测试
│       └── FileUploadIntegrationTest.php
├── API/                            # API 测试
│   ├── AuthApiTest.php
│   ├── CallbackApiTest.php
│   ├── UploadApiTest.php
│   ├── UserApiTest.php
│   ├── LogsApiTest.php
│   └── SystemApiTest.php
├── E2E/                            # 端到端测试
│   ├── CompleteOAuthFlowTest.php
│   ├── CompleteUploadFlowTest.php
│   └── CompleteUserFlowTest.php
├── Fixtures/                       # 测试数据（固定）
│   ├── users.php
│   ├── devices.php
│   ├── reading_logs.php
│   ├── oauth_sessions.php
│   └── sample_files/
│       ├── valid_log.txt
│       ├── invalid_log.php
│       ├── large_log.txt
│       └── empty_log.txt
├── Factories/                      # 测试数据工厂
│   ├── UserFactory.php
│   ├── DeviceFactory.php
│   ├── ReadingLogFactory.php
│   └── OAuthSessionFactory.php
├── Helpers/                        # 测试辅助类
│   ├── DatabaseTestCase.php
│   ├── ApiTestCase.php
│   ├── MockHelper.php
│   └── TestDataHelper.php
├── Database/                       # 数据库相关
│   ├── Migrations/
│   │   └── TestMigrationRunner.php
│   └── Seeds/
│       ├── UserSeeder.php
│       └── DeviceSeeder.php
├── storage/                        # 测试存储
│   ├── uploads/                    # 测试上传文件
│   ├── logs/                       # 测试日志
│   ├── cache/                      # 测试缓存
│   └── sessions/                   # 测试会话
├── coverage/                       # 覆盖率报告
│   ├── html/                       # HTML 格式报告
│   └── clover.xml                  # Clover XML 报告
├── results/                        # 测试结果
│   ├── junit.xml                   # JUnit XML 结果
│   ├── testdox.html                # TestDox HTML 报告
│   └── testdox.txt                 # TestDox 文本报告
├── bootstrap.php                   # 测试引导文件
└── phpunit.xml                     # PHPUnit 配置文件
```

## 3. 目录详细说明

### 3.1 Unit/ - 单元测试目录

**用途**：测试单个类或方法的功能

**特点**：
- 执行速度快（毫秒级）
- 无外部依赖
- 使用 Mock 和 Stub 隔离依赖
- 覆盖所有边界条件

**子目录结构**：

#### 3.1.1 Unit/Core/ - 核心组件测试

测试核心组件的功能，包括：

- **ConfigTest.php**：测试配置管理
  - 配置加载
  - 配置获取和设置
  - 环境变量解析
  - 嵌套配置访问

- **DatabaseTest.php**：测试数据库连接（使用 Mock）
  - 连接建立
  - 查询执行
  - 事务处理
  - 错误处理

- **LoggerTest.php**：测试日志记录
  - 日志级别
  - 日志格式
  - 日志文件写入
  - 日志轮转

- **ResponseTest.php**：测试响应处理
  - JSON 响应
  - 错误响应
  - HTTP 状态码
  - 响应头设置

#### 3.1.2 Unit/Models/ - 模型测试

测试数据模型的业务逻辑：

- **UserTest.php**：测试用户模型
  - 用户创建
  - 用户查询
  - 用户更新
  - 令牌生成和撤销
  - 设备关联

- **DeviceTest.php**：测试设备模型
  - 设备创建
  - 设备查询
  - 设备更新
  - 最后同步时间更新

- **ReadingLogTest.php**：测试阅读日志模型
  - 日志创建
  - 日志查询
  - 文件哈希查询
  - 用户日志列表

- **OAuthSessionTest.php**：测试 OAuth 会话模型
  - 会话创建
  - 会话查询
  - 会话状态更新
  - 会话过期检查

#### 3.1.3 Unit/Services/ - 服务层测试

测试业务服务逻辑：

- **UploadServiceTest.php**：测试上传服务
  - 文件验证
  - 文件上传
  - 多文件上传
  - 文件哈希计算
  - 错误处理

- **OAuthTest.php**：测试 OAuth 服务
  - 授权 URL 生成
  - Access Token 获取
  - 用户信息获取
  - 用户创建和更新
  - OAuth 流程处理

#### 3.1.4 Unit/Utils/ - 工具类测试

测试工具类方法：

- **SecurityHelperTest.php**：测试安全工具
  - UUID 生成
  - 随机字符串生成
  - 令牌生成
  - 哈希计算
  - 加密和解密
  - 输入清理
  - 文件验证

- **FileHelperTest.php**：测试文件工具
  - 文件扩展名获取
  - 文件大小格式化
  - 目录创建
  - 文件删除
  - 路径处理

### 3.2 Integration/ - 集成测试目录

**用途**：测试多个组件协同工作

**特点**：
- 使用真实测试数据库
- 测试数据库交互
- 测试组件集成
- 执行速度中等（秒级）

**子目录结构**：

#### 3.2.1 Integration/Database/ - 数据库集成测试

测试模型与数据库的交互：

- **UserIntegrationTest.php**：用户数据库集成
  - 创建用户并验证数据库
  - 查询用户并验证数据
  - 更新用户并验证变更
  - 删除用户并验证删除
  - 事务回滚测试

- **DeviceIntegrationTest.php**：设备数据库集成
  - 创建设备并验证数据库
  - 查询设备并验证数据
  - 更新设备并验证变更
  - 用户设备关联测试

- **ReadingLogIntegrationTest.php**：阅读日志数据库集成
  - 创建日志并验证数据库
  - 查询日志并验证数据
  - 文件哈希唯一性测试
  - 用户日志列表测试

- **OAuthSessionIntegrationTest.php**：OAuth 会话数据库集成
  - 创建会话并验证数据库
  - 查询会话并验证数据
  - 更新会话状态
  - 会话过期测试

#### 3.2.2 Integration/OAuth/ - OAuth 集成测试

测试 OAuth 完整流程：

- **OAuthFlowTest.php**：OAuth 流程测试
  - 发起认证
  - 处理回调
  - 用户创建和更新
  - 设备关联
  - 令牌生成

#### 3.2.3 Integration/Upload/ - 上传集成测试

测试文件上传流程：

- **FileUploadIntegrationTest.php**：文件上传集成测试
  - 文件上传到数据库
  - 文件存储验证
  - 文件哈希验证
  - 重复文件处理
  - 错误处理

### 3.3 API/ - API 测试目录

**用途**：测试 API 端点

**特点**：
- 使用 HTTP 请求
- 测试完整 API 流程
- 测试认证和授权
- 测试错误处理

**测试文件**：

- **AuthApiTest.php**：认证 API 测试
  - 发起认证请求
  - 验证响应格式
  - 验证会话创建
  - 错误处理测试

- **CallbackApiTest.php**：回调 API 测试
  - 处理 GitHub 回调
  - 验证用户创建
  - 验证令牌生成
  - 错误处理测试

- **UploadApiTest.php**：上传 API 测试
  - 文件上传请求
  - 认证验证
  - 文件验证
  - 响应格式验证
  - 错误处理测试

- **UserApiTest.php**：用户 API 测试
  - 获取用户信息
  - 更新用户信息
  - 获取用户设备
  - 权限验证

- **LogsApiTest.php**：日志 API 测试
  - 获取阅读日志
  - 分页测试
  - 过滤测试
  - 权限验证

- **SystemApiTest.php**：系统 API 测试
  - 系统状态检查
  - 健康检查
  - 版本信息

### 3.4 E2E/ - 端到端测试目录

**用途**：测试完整的用户场景

**特点**：
- 测试完整业务流程
- 模拟真实用户操作
- 测试多个 API 端点
- 执行速度较慢

**测试文件**：

- **CompleteOAuthFlowTest.php**：完整 OAuth 流程测试
  1. 设备发起认证请求
  2. 获取授权 URL
  3. 模拟 GitHub 回调
  4. 验证用户创建
  5. 验证设备关联
  6. 验证令牌生成
  7. 使用令牌访问 API

- **CompleteUploadFlowTest.php**：完整上传流程测试
  1. 用户认证
  2. 获取访问令牌
  3. 注册设备
  4. 上传文件
  5. 验证文件存储
  6. 验证数据库记录
  7. 查询上传日志

- **CompleteUserFlowTest.php**：完整用户流程测试
  1. 用户注册
  2. 设备绑定
  3. 上传多个文件
  4. 查询阅读日志
  5. 更新用户信息
  6. 撤销令牌
  7. 删除设备

### 3.5 Fixtures/ - 测试数据目录

**用途**：存储固定的测试数据

**特点**：
- 静态数据
- 快速加载
- 易于维护

**文件说明**：

- **users.php**：用户测试数据
  ```php
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

- **devices.php**：设备测试数据
- **reading_logs.php**：阅读日志测试数据
- **oauth_sessions.php**：OAuth 会话测试数据

**sample_files/**：示例文件
- **valid_log.txt**：有效的日志文件
- **invalid_log.php**：无效的日志文件（包含 PHP 代码）
- **large_log.txt**：大文件（测试文件大小限制）
- **empty_log.txt**：空文件

### 3.6 Factories/ - 测试数据工厂目录

**用途**：动态生成测试数据

**特点**：
- 灵活生成
- 支持参数化
- 支持关联数据

**文件说明**：

- **UserFactory.php**：用户数据工厂
  ```php
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

- **DeviceFactory.php**：设备数据工厂
- **ReadingLogFactory.php**：阅读日志数据工厂
- **OAuthSessionFactory.php**：OAuth 会话数据工厂

### 3.7 Helpers/ - 测试辅助类目录

**用途**：提供公共测试功能

**文件说明**：

- **DatabaseTestCase.php**：数据库测试基类
  - 事务管理
  - 数据库断言方法
  - 数据清理

- **ApiTestCase.php**：API 测试基类
  - HTTP 客户端封装
  - 请求方法（GET, POST, PUT, DELETE）
  - 响应断言方法
  - 认证管理

- **MockHelper.php**：Mock 辅助类
  - 创建 Mock 对象
  - 配置 Mock 行为
  - 验证 Mock 调用

- **TestDataHelper.php**：测试数据辅助类
  - 加载 Fixtures
  - 生成测试数据
  - 清理测试数据

### 3.8 Database/ - 数据库相关目录

**用途**：数据库迁移和种子数据

**子目录结构**：

#### 3.8.1 Database/Migrations/ - 测试迁移

- **TestMigrationRunner.php**：测试迁移运行器
  - 删除所有表
  - 创建所有表
  - 初始化测试数据库

#### 3.8.2 Database/Seeds/ - 测试种子数据

- **UserSeeder.php**：用户种子数据
- **DeviceSeeder.php**：设备种子数据

### 3.9 storage/ - 测试存储目录

**用途**：存储测试期间的临时文件

**子目录**：

- **uploads/**：测试上传文件
- **logs/**：测试日志文件
- **cache/**：测试缓存文件
- **sessions/**：测试会话文件

**清理策略**：
- 每次测试后清理上传文件
- 测试套件开始时清空所有目录

### 3.10 coverage/ - 覆盖率报告目录

**用途**：存储代码覆盖率报告

**文件**：

- **html/**：HTML 格式覆盖率报告
  - index.html：主报告
  - 各类和方法的详细报告

- **clover.xml**：Clover XML 格式报告
  - 用于 CI/CD 集成
  - 用于代码质量工具

### 3.11 results/ - 测试结果目录

**用途**：存储测试结果

**文件**：

- **junit.xml**：JUnit XML 格式结果
  - 用于 CI/CD 集成
  - 用于测试报告工具

- **testdox.html**：TestDox HTML 报告
  - 人类可读的测试报告

- **testdox.txt**：TestDox 文本报告
  - 文本格式的测试报告

## 4. 文件命名规范

### 4.1 测试文件命名

**规则**：`{ClassName}Test.php`

**示例**：
- `UserTest.php` - 测试 User 模型
- `UploadServiceTest.php` - 测试 UploadService
- `SecurityHelperTest.php` - 测试 SecurityHelper

### 4.2 测试方法命名

**规则**：`test{MethodName}{Scenario}`

**示例**：
```php
public function testCreateUserWithValidData()
public function testCreateUserWithInvalidData()
public function testCreateUserWithDuplicateGithubUid()
public function testGenerateAccessTokenWithDefaultExpiration()
public function testGenerateAccessTokenWithCustomExpiration()
```

### 4.3 Fixture 文件命名

**规则**：`{entityName}.php`（小写，复数）

**示例**：
- `users.php` - 用户数据
- `devices.php` - 设备数据
- `reading_logs.php` - 阅读日志数据

### 4.4 Factory 文件命名

**规则**：`{ClassName}Factory.php`

**示例**：
- `UserFactory.php` - 用户工厂
- `DeviceFactory.php` - 设备工厂

### 4.5 Helper 文件命名

**规则**：`{Purpose}TestCase.php` 或 `{Purpose}Helper.php`

**示例**：
- `DatabaseTestCase.php` - 数据库测试基类
- `ApiTestCase.php` - API 测试基类
- `MockHelper.php` - Mock 辅助类

## 5. 测试组织最佳实践

### 5.1 测试分组

使用 PHPUnit 的 `@group` 注解组织测试：

```php
/**
 * @group critical
 * @group database
 */
public function testCreateUser()
{
    // 测试代码
}
```

**常用分组**：
- `@group critical`：关键路径测试
- `@group database`：数据库测试
- `@group api`：API 测试
- `@group slow`：慢速测试

### 5.2 测试依赖

使用 `@depends` 注解定义测试依赖：

```php
public function testCreateUser()
{
    $userId = User::create([...]);
    return $userId;
}

/**
 * @depends testCreateUser
 */
public function testFindUser($userId)
{
    $user = User::findById($userId);
    $this->assertNotNull($user);
}
```

### 5.3 数据提供器

使用 `@dataProvider` 注解提供测试数据：

```php
/**
 * @dataProvider provideInvalidUserData
 */
public function testCreateUserWithInvalidData(array $data, string $expectedError)
{
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($expectedError);
    
    User::create($data);
}

public function provideInvalidUserData(): array
{
    return [
        'missing_github_uid' => [
            ['username' => 'test'],
            'GitHub UID is required',
        ],
        'missing_username' => [
            ['github_uid' => '123'],
            'Username is required',
        ],
    ];
}
```

### 5.4 测试基类使用

继承适当的测试基类：

```php
// 数据库测试
class UserIntegrationTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 额外的设置
    }
}

// API 测试
class UploadApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 额外的设置
    }
}
```

## 6. 测试文件模板

### 6.1 单元测试模板

```php
<?php
namespace KindleReading\Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use KindleReading\Models\User;
use KindleReading\Tests\Factories\UserFactory;

class UserTest extends TestCase
{
    public function testCreateUserWithValidData()
    {
        $data = UserFactory::create();
        $userId = User::create($data);
        
        $this->assertIsInt($userId);
        $this->assertGreaterThan(0, $userId);
    }
    
    public function testFindUserById()
    {
        $user = UserFactory::createInDatabase();
        $foundUser = User::findById($user->getId());
        
        $this->assertNotNull($foundUser);
        $this->assertEquals($user->getId(), $foundUser->getId());
    }
}
```

### 6.2 集成测试模板

```php
<?php
namespace KindleReading\Tests\Integration\Database;

use KindleReading\Tests\Helpers\DatabaseTestCase;
use KindleReading\Models\User;
use KindleReading\Tests\Factories\UserFactory;

class UserIntegrationTest extends DatabaseTestCase
{
    public function testCreateUserInDatabase()
    {
        $data = UserFactory::create();
        $userId = User::create($data);
        
        $this->assertRecordExists('users', ['id' => $userId]);
    }
    
    public function testUpdateUserInDatabase()
    {
        $user = UserFactory::createInDatabase();
        $user->update(['username' => 'updated']);
        
        $this->assertRecordExists('users', [
            'id' => $user->getId(),
            'username' => 'updated',
        ]);
    }
}
```

### 6.3 API 测试模板

```php
<?php
namespace KindleReading\Tests\API;

use KindleReading\Tests\Helpers\ApiTestCase;
use KindleReading\Tests\Factories\UserFactory;
use KindleReading\Models\User;

class UploadApiTest extends ApiTestCase
{
    private User $user;
    private string $token;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = UserFactory::createInDatabase();
        $this->token = $this->user->generateAccessToken();
        $this->setAccessToken($this->token);
    }
    
    public function testUploadFileWithValidToken()
    {
        $response = $this->post('/api/upload.php', [
            'device_id' => 'test-device',
            'files' => $this->createTestFile(),
        ]);
        
        $this->assertSuccessResponse($response);
    }
    
    public function testUploadFileWithoutToken()
    {
        $this->setAccessToken(null);
        
        $response = $this->post('/api/upload.php', [
            'device_id' => 'test-device',
            'files' => $this->createTestFile(),
        ]);
        
        $this->assertErrorResponse($response, 401);
    }
}
```

## 7. 总结

本测试目录结构方案为 Kindle Reading PHP 系统提供了清晰的测试组织方式：

1. **分层测试**：单元测试、集成测试、API 测试、E2E 测试
2. **清晰命名**：统一的文件和方法命名规范
3. **辅助工具**：Fixtures、Factories、Helpers 提高测试效率
4. **最佳实践**：测试分组、依赖、数据提供器等
5. **易于维护**：结构清晰，易于扩展和维护

通过遵循此目录结构，开发团队可以：
- 快速定位测试文件
- 保持测试代码组织良好
- 提高测试编写效率
- 降低维护成本