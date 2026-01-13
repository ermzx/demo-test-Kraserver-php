# 测试环境配置方案

## 1. 概述

本文档详细说明了 Kindle Reading PHP 系统的测试环境配置，包括数据库配置、文件存储配置、环境变量配置、Docker 配置和本地开发环境设置。

## 2. 测试环境要求

### 2.1 系统要求

| 组件 | 最低版本 | 推荐版本 |
|------|---------|---------|
| PHP | 8.0 | 8.2 |
| MySQL | 5.7 | 8.0 |
| Composer | 2.0 | 2.5 |
| PHPUnit | 9.5 | 9.6 |
| Xdebug | 3.0 | 3.2 |

### 2.2 PHP 扩展

必需的 PHP 扩展：
- `pdo_mysql`：MySQL 数据库连接
- `mbstring`：多字节字符串处理
- `json`：JSON 编解码
- `openssl`：加密功能
- `curl`：HTTP 客户端
- `fileinfo`：文件类型检测

可选的 PHP 扩展：
- `xdebug`：代码覆盖率（推荐）
- `zip`：压缩功能

### 2.3 磁盘空间

- 源代码：~50 MB
- 依赖包：~200 MB
- 测试数据库：~100 MB
- 测试文件：~50 MB
- 覆盖率报告：~100 MB
- **总计**：~500 MB

## 3. 测试数据库配置

### 3.1 数据库创建

#### 3.1.1 手动创建数据库

```sql
-- 创建测试数据库
CREATE DATABASE kindle_reading_test 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- 创建测试用户
CREATE USER 'test_user'@'localhost' IDENTIFIED BY 'test_password';

-- 授予权限
GRANT ALL PRIVILEGES ON kindle_reading_test.* TO 'test_user'@'localhost';

-- 刷新权限
FLUSH PRIVILEGES;
```

#### 3.1.2 使用脚本创建数据库

创建脚本 `scripts/create-test-database.sh`：

```bash
#!/bin/bash

# 数据库配置
DB_NAME="kindle_reading_test"
DB_USER="test_user"
DB_PASS="test_password"
DB_HOST="localhost"

# 创建数据库
mysql -h "$DB_HOST" -u root -p <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'$DB_HOST' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'$DB_HOST';
FLUSH PRIVILEGES;
EOF

echo "Test database created successfully!"
```

使用方法：
```bash
chmod +x scripts/create-test-database.sh
./scripts/create-test-database.sh
```

### 3.2 数据库连接配置

在 `.env.testing` 文件中配置：

```env
# 数据库配置
DB_HOST=localhost
DB_PORT=3306
DB_NAME=kindle_reading_test
DB_USER=test_user
DB_PASS=test_password
DB_CHARSET=utf8mb4
```

### 3.3 数据库迁移

#### 3.3.1 自动迁移

在 `tests/bootstrap.php` 中设置：

```php
// 自动运行迁移
if (getenv('RUN_MIGRATIONS') === 'true') {
    require_once APP_ROOT . '/tests/Database/Migrations/TestMigrationRunner.php';
    TestMigrationRunner::run();
}
```

在 `.env.testing` 中启用：

```env
RUN_MIGRATIONS=true
```

#### 3.3.2 手动迁移

创建迁移脚本 `scripts/run-test-migrations.php`：

```php
<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';

// 加载测试环境配置
$envFile = APP_ROOT . '/.env.testing';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            putenv(sprintf('%s=%s', trim($name), trim($value)));
        }
    }
}

// 运行迁移
require_once APP_ROOT . '/tests/Database/Migrations/TestMigrationRunner.php';
TestMigrationRunner::run();

echo "Test database migrations completed!\n";
```

使用方法：
```bash
php scripts/run-test-migrations.php
```

### 3.4 数据库清理策略

#### 3.4.1 事务回滚（推荐）

在测试基类中使用事务：

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

**优点**：
- 快速
- 不需要重建表
- 测试之间完全隔离

**缺点**：
- 不能测试事务相关的代码

#### 3.4.2 表重建

在每次测试前重建表：

```php
abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestMigrationRunner::run();
    }
}
```

**优点**：
- 完全隔离
- 可以测试事务相关的代码

**缺点**：
- 较慢
- 需要重建表结构

#### 3.4.3 数据清理

在每次测试后清理数据：

```php
abstract class DatabaseTestCase extends TestCase
{
    protected function tearDown(): void
    {
        $pdo = Database::getInstance();
        
        // 清空所有表
        $tables = ['user_tokens', 'reading_logs', 'kindle_devices', 'oauth_sessions', 'users'];
        foreach ($tables as $table) {
            $pdo->exec("TRUNCATE TABLE `{$table}`");
        }
        
        parent::tearDown();
    }
}
```

**优点**：
- 保留表结构
- 可以测试事务相关的代码

**缺点**：
- 需要手动管理外键约束
- 较慢

## 4. 测试文件存储配置

### 4.1 目录结构

```
tests/storage/
├── uploads/          # 测试上传文件
├── logs/             # 测试日志
├── cache/            # 测试缓存
└── sessions/         # 测试会话
```

### 4.2 目录创建

在 `tests/bootstrap.php` 中创建：

```php
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

### 4.3 文件清理

#### 4.3.1 每次测试后清理

```php
protected function tearDown(): void
{
    // 清理上传文件
    $uploadDir = APP_ROOT . '/tests/storage/uploads';
    if (is_dir($uploadDir)) {
        $files = glob($uploadDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    parent::tearDown();
}
```

#### 4.3.2 测试套件开始时清理

```php
// 在 tests/bootstrap.php 中
$uploadDir = APP_ROOT . '/tests/storage/uploads';
if (is_dir($uploadDir)) {
    $files = glob($uploadDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}
```

### 4.4 使用虚拟文件系统（可选）

使用 `vfsStream` 创建虚拟文件系统：

```bash
composer require --dev mikey179/vfsstream
```

使用示例：

```php
use org\bovigo\vfs\vfsStream;

public function testFileUploadWithVirtualFileSystem()
{
    // 创建虚拟文件系统
    vfsStream::setup('uploads');
    
    // 设置上传路径
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

**优点**：
- 不需要真实文件系统
- 测试更快
- 完全隔离

**缺点**：
- 不能测试真实的文件操作
- 需要额外的依赖

## 5. 环境变量配置

### 5.1 .env.testing 文件

完整的测试环境配置：

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

### 5.2 环境变量加载

在 `tests/bootstrap.php` 中加载：

```php
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
```

### 5.3 环境变量验证

创建验证脚本 `scripts/validate-test-env.php`：

```php
<?php
define('APP_ROOT', dirname(__DIR__));

// 加载环境变量
$envFile = APP_ROOT . '/.env.testing';
if (!file_exists($envFile)) {
    die("Error: .env.testing file not found\n");
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$env = [];
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    if (strpos($line, '=') !== false) {
        list($name, $value) = explode('=', $line, 2);
        $env[trim($name)] = trim($value);
    }
}

// 验证必需的环境变量
$required = [
    'APP_ENV',
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',
    'UPLOAD_PATH',
    'LOG_PATH',
    'ENCRYPTION_KEY',
];

$errors = [];
foreach ($required as $key) {
    if (!isset($env[$key]) || empty($env[$key])) {
        $errors[] = "Missing required environment variable: {$key}";
    }
}

if (!empty($errors)) {
    echo "Environment validation failed:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}

echo "Environment validation passed!\n";
```

使用方法：
```bash
php scripts/validate-test-env.php
```

## 6. Docker 配置

### 6.1 Docker Compose 配置

创建 `docker-compose.test.yml`：

```yaml
version: '3.8'

services:
  # PHP 测试环境
  php-test:
    build:
      context: .
      dockerfile: Dockerfile.test
    container_name: kindle-reading-php-test
    volumes:
      - .:/app
      - /app/vendor
      - /app/tests/storage
    environment:
      - APP_ENV=testing
      - DB_HOST=mysql-test
      - DB_NAME=kindle_reading_test
      - DB_USER=test_user
      - DB_PASS=test_password
    depends_on:
      - mysql-test
    command: tail -f /dev/null

  # MySQL 测试数据库
  mysql-test:
    image: mysql:8.0
    container_name: kindle-reading-mysql-test
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: kindle_reading_test
      MYSQL_USER: test_user
      MYSQL_PASSWORD: test_password
    ports:
      - "3307:3306"
    volumes:
      - mysql-test-data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  mysql-test-data:
```

### 6.2 Dockerfile.test

创建 `Dockerfile.test`：

```dockerfile
FROM php:8.2-cli

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# 安装 PHP 扩展
RUN docker-php-ext-install pdo_mysql mbstring xml curl

# 安装 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 设置工作目录
WORKDIR /app

# 复制依赖文件
COPY composer.json composer.lock ./

# 安装依赖
RUN composer install --no-scripts --no-autoloader

# 复制应用代码
COPY . .

# 生成自动加载文件
RUN composer dump-autoload --optimize

# 设置权限
RUN chmod -R 755 /app/tests/storage

# 默认命令
CMD ["php", "-a"]
```

### 6.3 使用 Docker 运行测试

#### 6.3.1 启动测试环境

```bash
# 构建并启动容器
docker-compose -f docker-compose.test.yml up -d

# 进入 PHP 容器
docker-compose -f docker-compose.test.yml exec php-test bash
```

#### 6.3.2 运行测试

```bash
# 在容器内运行测试
docker-compose -f docker-compose.test.yml exec php-test composer test

# 运行快速测试
docker-compose -f docker-compose.test.yml exec php-test composer test-fast

# 生成覆盖率报告
docker-compose -f docker-compose.test.yml exec php-test composer test-coverage
```

#### 6.3.3 停止测试环境

```bash
# 停止容器
docker-compose -f docker-compose.test.yml down

# 停止并删除数据卷
docker-compose -f docker-compose.test.yml down -v
```

## 7. 本地开发环境设置

### 7.1 初始化步骤

#### 7.1.1 克隆项目

```bash
git clone <repository-url>
cd kindle-reading-php
```

#### 7.1.2 安装依赖

```bash
composer install
```

#### 7.1.3 创建测试环境配置

```bash
cp .env.example .env.testing
```

编辑 `.env.testing` 文件，配置测试环境。

#### 7.1.4 创建测试数据库

```bash
# 使用脚本创建
./scripts/create-test-database.sh

# 或手动创建
mysql -u root -p
```

```sql
CREATE DATABASE kindle_reading_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'test_user'@'localhost' IDENTIFIED BY 'test_password';
GRANT ALL PRIVILEGES ON kindle_reading_test.* TO 'test_user'@'localhost';
FLUSH PRIVILEGES;
```

#### 7.1.5 运行数据库迁移

```bash
php scripts/run-test-migrations.php
```

#### 7.1.6 验证环境

```bash
# 验证环境变量
php scripts/validate-test-env.php

# 运行快速测试
composer test-fast
```

### 7.2 IDE 配置

#### 7.2.1 VS Code 配置

创建 `.vscode/settings.json`：

```json
{
    "phpunit.phpunit": "vendor/bin/phpunit",
    "phpunit.args": [
        "--configuration", "phpunit.xml"
    ],
    "phpunit.phpunitBinary": "vendor/bin/phpunit",
    "phpunit.xmlConfigurations": [
        "phpunit.xml"
    ]
}
```

创建 `.vscode/launch.json`：

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "PHPUnit: Run All Tests",
            "type": "php",
            "request": "launch",
            "program": "${workspaceFolder}/vendor/bin/phpunit",
            "args": [
                "--configuration",
                "${workspaceFolder}/phpunit.xml"
            ],
            "cwd": "${workspaceFolder}"
        },
        {
            "name": "PHPUnit: Run Current File",
            "type": "php",
            "request": "launch",
            "program": "${workspaceFolder}/vendor/bin/phpunit",
            "args": [
                "--configuration",
                "${workspaceFolder}/phpunit.xml",
                "${file}"
            ],
            "cwd": "${workspaceFolder}"
        }
    ]
}
```

#### 7.2.2 PhpStorm 配置

1. 打开 Settings/Preferences
2. 导航到 PHP > Test Frameworks
3. 点击 "+" 添加 PHPUnit
4. 选择 "Use Composer autoloader"
5. 指定路径到 `vendor/autoload.php`
6. 指定 PHPUnit 配置文件为 `phpunit.xml`

### 7.3 Git 配置

#### 7.3.1 .gitignore

确保 `.gitignore` 包含测试相关文件：

```
# 测试环境
.env.testing
tests/storage/*
!tests/storage/.gitkeep

# 测试报告
tests/coverage/*
!tests/coverage/.gitkeep
tests/results/*
!tests/results/.gitkeep

# 测试日志
tests/storage/logs/*
!tests/storage/logs/.gitkeep
```

#### 7.3.2 Pre-commit Hook

创建 `.git/hooks/pre-commit`：

```bash
#!/bin/bash

# 运行快速测试
echo "Running fast tests..."
composer test-fast

if [ $? -ne 0 ]; then
    echo "Fast tests failed. Commit aborted."
    exit 1
fi

# 运行代码规范检查
echo "Running code style check..."
composer cs-check

if [ $? -ne 0 ]; then
    echo "Code style check failed. Commit aborted."
    echo "Run 'composer cs-fix' to fix issues automatically."
    exit 1
fi

echo "Pre-commit checks passed!"
exit 0
```

设置可执行权限：
```bash
chmod +x .git/hooks/pre-commit
```

## 8. CI/CD 环境配置

### 8.1 GitHub Actions 配置

创建 `.github/workflows/tests.yml`：

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
          MYSQL_USER: test_user
          MYSQL_PASSWORD: test_password
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

### 8.2 GitLab CI 配置

创建 `.gitlab-ci.yml`：

```yaml
stages:
  - test
  - coverage

variables:
  MYSQL_DATABASE: kindle_reading_test
  MYSQL_USER: test_user
  MYSQL_PASSWORD: test_password
  MYSQL_ROOT_PASSWORD: root

test:
  stage: test
  image: php:8.2-cli
  
  services:
    - mysql:8.0
  
  before_script:
    - apt-get update && apt-get install -y git unzip libpng-dev libonig-dev libxml2-dev
    - docker-php-ext-install pdo_mysql mbstring xml curl
    - curl -sS https://getcomposer.org/installer | php
    - php composer.phar install --prefer-dist --no-progress
    - cp .env.example .env.testing
  
  script:
    - php composer.phar cs-check
    - php composer.phar test-fast
    - php composer.phar test
  
  coverage: '/^\s*Lines:\s*\d+.\d+\%/'
  
  artifacts:
    reports:
      coverage_report:
        coverage_format: cobertura
        path: tests/coverage/clover.xml

coverage:
  stage: coverage
  image: php:8.2-cli
  
  services:
    - mysql:8.0
  
  before_script:
    - apt-get update && apt-get install -y git unzip libpng-dev libonig-dev libxml2-dev
    - docker-php-ext-install pdo_mysql mbstring xml curl
    - curl -sS https://getcomposer.org/installer | php
    - php composer.phar install --prefer-dist --no-progress
    - cp .env.example .env.testing
  
  script:
    - php composer.phar test-coverage
  
  artifacts:
    paths:
      - tests/coverage/
    expire_in: 1 week
```

## 9. 故障排除

### 9.1 常见问题

#### 问题 1：数据库连接失败

**错误信息**：
```
SQLSTATE[HY000] [2002] Connection refused
```

**解决方案**：
1. 检查 MySQL 服务是否运行
2. 检查 `.env.testing` 中的数据库配置
3. 检查防火墙设置

```bash
# 检查 MySQL 服务
systemctl status mysql

# 测试连接
mysql -h localhost -u test_user -p kindle_reading_test
```

#### 问题 2：权限错误

**错误信息**：
```
Permission denied: tests/storage/uploads
```

**解决方案**：
```bash
# 设置正确的权限
chmod -R 755 tests/storage
chown -R www-data:www-data tests/storage
```

#### 问题 3：内存不足

**错误信息**：
```
Fatal error: Allowed memory size exhausted
```

**解决方案**：
```bash
# 增加 PHP 内存限制
php -d memory_limit=1G vendor/bin/phpunit

# 或在 phpunit.xml 中配置
<php>
    <ini name="memory_limit" value="1G"/>
</php>
```

#### 问题 4：Xdebug 未安装

**错误信息**：
```
Xdebug is not loaded
```

**解决方案**：
```bash
# 安装 Xdebug
pecl install xdebug

# 或使用包管理器
apt-get install php-xdebug

# 验证安装
php -m | grep xdebug
```

### 9.2 调试技巧

#### 9.2.1 启用详细输出

```bash
# 详细输出
phpunit --verbose

# 调试模式
phpunit --debug

# 显示未覆盖的文件
phpunit --coverage-text --show-uncovered-files
```

#### 9.2.2 运行单个测试

```bash
# 运行单个测试文件
phpunit tests/Unit/Models/UserTest.php

# 运行单个测试方法
phpunit --filter testCreateUserWithValidData

# 运行特定分组的测试
phpunit --group critical
```

#### 9.2.3 查看日志

```bash
# 查看测试日志
tail -f tests/storage/logs/*.log

# 查看错误日志
grep -i error tests/storage/logs/*.log
```

## 10. 总结

本测试环境配置方案为 Kindle Reading PHP 系统提供了完整的测试环境设置指南：

1. **数据库配置**：测试数据库创建、迁移和清理策略
2. **文件存储配置**：测试文件目录和清理策略
3. **环境变量配置**：完整的测试环境变量设置
4. **Docker 配置**：容器化测试环境
5. **本地开发环境**：IDE 配置和 Git 钩子
6. **CI/CD 配置**：GitHub Actions 和 GitLab CI 配置
7. **故障排除**：常见问题和调试技巧

通过遵循此配置方案，开发团队可以：
- 快速搭建测试环境
- 保持测试环境一致性
- 提高测试效率
- 集成到 CI/CD 流程