# Kindle Reading GTK 云同步服务端 - 综合实施计划

## 项目概述

### 项目目标

开发一个基于 PHP 的 Kindle Reading GTK 云同步服务端，实现以下核心功能：

1. **GitHub OAuth 2.0 认证**：通过二维码扫码登录，Kindle 设备轮询获取登录状态
2. **文件上传接收**：接收 Kindle 客户端上传的阅读时长 Log 目录（全量备份）
3. **用户数据隔离**：按用户 ID 隔离存储所有数据
4. **数据解析与统计**：解析阅读日志文件，生成统计数据

### 技术栈

| 类别 | 技术 |
|------|------|
| 后端语言 | PHP 8.1+ |
| 数据库 | MySQL/MariaDB 8.0+ |
| Web 服务器 | Nginx |
| 认证协议 | OAuth 2.0 (GitHub) |
| 数据库访问 | PDO (预处理语句) |
| 依赖管理 | Composer |

### 安全要求

1. **SQL 注入防护**：所有数据库操作必须使用 PDO 预处理语句
2. **文件上传安全**：
   - 严格过滤上传文件类型
   - 验证文件扩展名和 MIME 类型
   - 计算并存储文件 SHA256 哈希值
   - 限制文件大小
   - 禁止上传目录执行 PHP 文件
3. **XSS 防护**：输出数据时进行 HTML 转义
4. **CSRF 防护**：OAuth 流程使用 state 参数
5. **HTTPS**：生产环境必须使用 HTTPS

---

## 实施步骤

### 阶段 1: 项目初始化

#### 1.1 创建项目目录结构

根据 [`project-structure.md`](project-structure.md) 创建完整的目录结构。

```bash
mkdir -p config src/{Core,Auth,Models,Services,Middleware,Utils} api public/{assets/{css,js,images},uploads} storage/{logs,cache,sessions} database/{migrations,seeds} tests/{unit,integration} scripts nginx plans docs
```

#### 1.2 初始化 Composer

创建 [`composer.json`](../composer.json) 文件：

```json
{
    "name": "kindle-reading/server",
    "description": "Kindle Reading GTK 云同步服务端",
    "type": "project",
    "require": {
        "php": ">=8.1",
        "ext-pdo": "*",
        "ext-json": "*",
        "ext-mbstring": "*",
        "ext-openssl": "*",
        "ext-curl": "*"
    },
    "autoload": {
        "psr-4": {
            "KindleReading\\": "src/"
        }
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist"
    }
}
```

运行 `composer install` 安装依赖。

#### 1.3 创建环境变量配置

创建 [`.env.example`](../.env.example) 文件：

```env
# 应用配置
APP_NAME="Kindle Reading GTK"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# 数据库配置
DB_HOST=localhost
DB_PORT=3306
DB_NAME=kindle_reading
DB_USER=your_db_user
DB_PASS=your_db_password
DB_CHARSET=utf8mb4

# GitHub OAuth 配置
GITHUB_CLIENT_ID=your_github_client_id
GITHUB_CLIENT_SECRET=your_github_client_secret
GITHUB_REDIRECT_URI=https://your-domain.com/api/callback.php

# 文件上传配置
MAX_FILE_SIZE=104857600
ALLOWED_EXTENSIONS=json,gz,txt,log
UPLOAD_PATH=public/uploads

# 会话配置
SESSION_TIMEOUT=300
USER_TOKEN_LIFETIME=7200

# 日志配置
LOG_LEVEL=info
LOG_PATH=storage/logs

# 数据保留配置
DATA_RETENTION_DAYS=365
```

#### 1.4 创建 .gitignore

更新 [`.gitignore`](../.gitignore) 文件：

```
.env
vendor/
storage/logs/*.log
storage/cache/*
storage/sessions/*
public/uploads/*
!public/uploads/.gitkeep
.vscode/
.idea/
*.swp
*.swo
*~
```

---

### 阶段 2: 数据库实现

#### 2.1 创建数据库结构

根据 [`database-design.md`](database-design.md) 创建数据库结构文件。

将 SQL 建表语句保存到 `database/schema.sql` 文件。

#### 2.2 实现数据库连接类

创建 [`src/Core/Database.php`](../src/Core/Database.php)：

```php
<?php
namespace KindleReading\Core;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;
    private static array $config = [];

    public static function init(array $config): void {
        self::$config = $config;
    }

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    self::$config['host'],
                    self::$config['port'],
                    self::$config['name'],
                    self::$config['charset']
                );
                
                self::$instance = new PDO($dsn, self::$config['user'], self::$config['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                throw new \RuntimeException('数据库连接失败: ' . $e->getMessage());
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): \PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchOne(string $sql, array $params = []): ?array {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    public static function execute(string $sql, array $params = []): bool {
        return self::query($sql, $params)->rowCount() > 0;
    }

    public static function lastInsertId(): string|false {
        return self::getInstance()->lastInsertId();
    }

    public static function beginTransaction(): bool {
        return self::getInstance()->beginTransaction();
    }

    public static function commit(): bool {
        return self::getInstance()->commit();
    }

    public static function rollback(): bool {
        return self::getInstance()->rollBack();
    }
}
```

#### 2.3 实现配置管理类

创建 [`src/Core/Config.php`](../src/Core/Config.php)：

```php
<?php
namespace KindleReading\Core;

class Config {
    private static array $config = [];

    public static function load(string $envFile = '.env'): void {
        if (!file_exists($envFile)) {
            throw new \RuntimeException('环境变量文件不存在: ' . $envFile);
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // 移除引号
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            self::$config[$name] = $value;
            $_ENV[$name] = $value;
        }
    }

    public static function get(string $key, $default = null) {
        return self::$config[$key] ?? $default;
    }

    public static function getDatabaseConfig(): array {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'port' => self::get('DB_PORT', '3306'),
            'name' => self::get('DB_NAME', 'kindle_reading'),
            'user' => self::get('DB_USER', 'root'),
            'pass' => self::get('DB_PASS', ''),
            'charset' => self::get('DB_CHARSET', 'utf8mb4'),
        ];
    }

    public static function getOAuthConfig(): array {
        return [
            'client_id' => self::get('GITHUB_CLIENT_ID'),
            'client_secret' => self::get('GITHUB_CLIENT_SECRET'),
            'redirect_uri' => self::get('GITHUB_REDIRECT_URI'),
            'scope' => 'read:user,user:email',
        ];
    }
}
```

---

### 阶段 3: 核心类实现

#### 3.1 实现响应类

创建 [`src/Core/Response.php`](../src/Core/Response.php)：

```php
<?php
namespace KindleReading\Core;

class Response {
    public static function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success($data = null, string $message = ''): void {
        $response = ['success' => true];
        if ($data !== null) {
            $response['data'] = $data;
        }
        if ($message) {
            $response['message'] = $message;
        }
        self::json($response);
    }

    public static function error(int $code, string $message, array $details = null, int $statusCode = 400): void {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ]
        ];
        if ($details !== null) {
            $response['error']['details'] = $details;
        }
        self::json($response, $statusCode);
    }
}
```

#### 3.2 实现日志类

创建 [`src/Core/Logger.php`](../src/Core/Logger.php)：

```php
<?php
namespace KindleReading\Core;

class Logger {
    private static string $logPath;
    private static string $level = 'info';

    public static function init(string $logPath, string $level = 'info'): void {
        self::$logPath = rtrim($logPath, '/');
        self::$level = $level;
        
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
    }

    private static function write(string $level, string $message, array $context = []): void {
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        
        if ($levels[$level] < $levels[self::$level]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logLine = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
        
        $logFile = self::$logPath . '/' . $level . '.log';
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    public static function debug(string $message, array $context = []): void {
        self::write('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::write('error', $message, $context);
    }
}
```

#### 3.3 实现工具类

创建 [`src/Utils/SecurityHelper.php`](../src/Utils/SecurityHelper.php)：

```php
<?php
namespace KindleReading\Utils;

class SecurityHelper {
    public static function generateUuid(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function generateState(int $length = 32): string {
        return bin2hex(random_bytes($length / 2));
    }

    public static function generateUserToken(): string {
        return hash('sha256', random_bytes(32) . microtime(true));
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    public static function sanitizeInput(string $input): string {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function validateUuid(string $uuid): bool {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }

    public static function calculateFileHash(string $filePath): string {
        return hash_file('sha256', $filePath);
    }
}
```

创建 [`src/Utils/FileHelper.php`](../src/Utils/FileHelper.php)：

```php
<?php
namespace KindleReading\Utils;

class FileHelper {
    public static function getExtension(string $fileName): string {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    }

    public static function isAllowedExtension(string $fileName, array $allowedExtensions): bool {
        $extension = self::getExtension($fileName);
        return in_array($extension, $allowedExtensions, true);
    }

    public static function formatFileSize(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public static function ensureDirectory(string $path): void {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    public static function deleteFile(string $filePath): bool {
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
}
```

---

### 阶段 4: OAuth 认证实现

#### 4.1 实现 OAuth 类

创建 [`src/Auth/OAuth.php`](../src/Auth/OAuth.php)：

```php
<?php
namespace KindleReading\Auth;

use KindleReading\Core\Database;
use KindleReading\Core\Config;
use KindleReading\Core\Logger;
use KindleReading\Utils\SecurityHelper;

class OAuth {
    private array $config;

    public function __construct() {
        $this->config = Config::getOAuthConfig();
    }

    public function getAuthorizeUrl(string $state): string {
        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'state' => $state,
            'scope' => $this->config['scope'],
        ];
        return 'https://github.com/login/oauth/authorize?' . http_build_query($params);
    }

    public function getAccessToken(string $code): ?array {
        $url = 'https://github.com/login/oauth/access_token';
        $data = [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'code' => $code,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Logger::error('GitHub OAuth 获取 Access Token 失败', ['http_code' => $httpCode, 'response' => $response]);
            return null;
        }

        return json_decode($response, true);
    }

    public function getUserInfo(string $accessToken): ?array {
        $url = 'https://api.github.com/user';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'User-Agent: Kindle-Reading-GTK'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Logger::error('GitHub OAuth 获取用户信息失败', ['http_code' => $httpCode, 'response' => $response]);
            return null;
        }

        return json_decode($response, true);
    }

    public function createOrUpdateUser(array $githubUser, string $accessToken): ?int {
        $sql = "INSERT INTO users (github_uid, username, avatar_url, access_token, last_login_at) 
                VALUES (:github_uid, :username, :avatar_url, :access_token, NOW())
                ON DUPLICATE KEY UPDATE 
                    username = VALUES(username),
                    avatar_url = VALUES(avatar_url),
                    access_token = VALUES(access_token),
                    last_login_at = VALUES(last_login_at)";
        
        Database::execute($sql, [
            ':github_uid' => $githubUser['id'],
            ':username' => $githubUser['login'],
            ':avatar_url' => $githubUser['avatar_url'] ?? null,
            ':access_token' => $accessToken,
        ]);

        $userId = Database::fetchOne("SELECT id FROM users WHERE github_uid = :github_uid", [
            ':github_uid' => $githubUser['id']
        ]);

        return $userId ? (int)$userId['id'] : null;
    }
}
```

#### 4.2 实现 OAuth 会话模型

创建 [`src/Models/OAuthSession.php`](../src/Models/OAuthSession.php)：

```php
<?php
namespace KindleReading\Models;

use KindleReading\Core\Database;
use KindleReading\Utils\SecurityHelper;

class OAuthSession {
    public static function create(string $deviceId, string $state): ?string {
        $sessionToken = SecurityHelper::generateUuid();
        $expiresAt = date('Y-m-d H:i:s', time() + (int)Config::get('SESSION_TIMEOUT', 300));

        $sql = "INSERT INTO oauth_sessions (session_token, device_id, state, status, expires_at) 
                VALUES (:session_token, :device_id, :state, 'pending', :expires_at)";
        
        if (Database::execute($sql, [
            ':session_token' => $sessionToken,
            ':device_id' => $deviceId,
            ':state' => $state,
            ':expires_at' => $expiresAt,
        ])) {
            return $sessionToken;
        }

        return null;
    }

    public static function getByToken(string $sessionToken): ?array {
        return Database::fetchOne("SELECT * FROM oauth_sessions WHERE session_token = :session_token", [
            ':session_token' => $sessionToken
        ]);
    }

    public static function getByState(string $state): ?array {
        return Database::fetchOne("SELECT * FROM oauth_sessions WHERE state = :state", [
            ':state' => $state
        ]);
    }

    public static function updateStatus(string $sessionToken, string $status, ?int $userId = null): bool {
        $sql = "UPDATE oauth_sessions SET status = :status";
        $params = [':session_token' => $sessionToken, ':status' => $status];

        if ($userId !== null) {
            $sql .= ", user_id = :user_id";
            $params[':user_id'] = $userId;
        }

        if ($status === 'completed') {
            $sql .= ", completed_at = NOW()";
        }

        $sql .= " WHERE session_token = :session_token";

        return Database::execute($sql, $params);
    }

    public static function isExpired(array $session): bool {
        return strtotime($session['expires_at']) < time();
    }

    public static function cleanupExpired(): int {
        $sql = "UPDATE oauth_sessions SET status = 'expired' 
                WHERE status = 'pending' AND expires_at < NOW()";
        $stmt = Database::query($sql);
        return $stmt->rowCount();
    }
}
```

---

### 阶段 5: API 接口实现

#### 5.1 实现认证接口

创建 [`api/auth.php`](../api/auth.php)：

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use KindleReading\Core\Config;
use KindleReading\Core\Database;
use KindleReading\Core\Response;
use KindleReading\Core\Logger;
use KindleReading\Auth\OAuth;
use KindleReading\Models\OAuthSession;
use KindleReading\Models\User;
use KindleReading\Models\Device;
use KindleReading\Utils\SecurityHelper;
use KindleReading\Utils\Validator;

// 加载配置
Config::load(__DIR__ . '/../.env');
Database::init(Config::getDatabaseConfig());
Logger::init(Config::get('LOG_PATH', 'storage/logs'), Config::get('LOG_LEVEL', 'info'));

// 设置错误处理
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    Logger::error('PHP Error', [
        'errno' => $errno,
        'errstr' => $errstr,
        'errfile' => $errfile,
        'errline' => $errline
    ]);
    Response::error(1010, '服务器内部错误');
});

// 获取请求方法和路径
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api/auth', '', $path);

// 路由分发
switch ($path) {
    case '/request':
        if ($method === 'POST') {
            handleRequest();
        } else {
            Response::error(1001, '不支持的请求方法');
        }
        break;

    case '/status':
        if ($method === 'GET') {
            handleStatus();
        } else {
            Response::error(1001, '不支持的请求方法');
        }
        break;

    case '/refresh':
        if ($method === 'POST') {
            handleRefresh();
        } else {
            Response::error(1001, '不支持的请求方法');
        }
        break;

    case '/logout':
        if ($method === 'POST') {
            handleLogout();
        } else {
            Response::error(1001, '不支持的请求方法');
        }
        break;

    default:
        Response::error(1001, '接口不存在');
}

// 处理登录请求
function handleRequest(): void {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['device_id'])) {
        Response::error(1001, '参数缺失', ['field' => 'device_id']);
    }

    $deviceId = $input['device_id'];

    if (!SecurityHelper::validateUuid($deviceId)) {
        Response::error(1002, '设备 ID 格式错误');
    }

    $state = SecurityHelper::generateState();
    $sessionToken = OAuthSession::create($deviceId, $state);

    if (!$sessionToken) {
        Response::error(1010, '创建会话失败');
    }

    $oauth = new OAuth();
    $authorizeUrl = $oauth->getAuthorizeUrl($state);
    $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($authorizeUrl);
    $expiresAt = date('c', time() + (int)Config::get('SESSION_TIMEOUT', 300));

    Response::success([
        'session_token' => $sessionToken,
        'qr_code_url' => $qrCodeUrl,
        'expires_at' => $expiresAt
    ]);
}

// 处理状态查询
function handleStatus(): void {
    $sessionToken = $_GET['session_token'] ?? '';

    if (!$sessionToken) {
        Response::error(1001, '参数缺失', ['field' => 'session_token']);
    }

    $session = OAuthSession::getByToken($sessionToken);

    if (!$session) {
        Response::error(1003, '会话不存在');
    }

    if (OAuthSession::isExpired($session)) {
        Response::error(1004, '会话已过期');
    }

    if ($session['status'] === 'pending') {
        Response::success([
            'status' => 'pending',
            'message' => '等待用户授权...'
        ]);
    }

    if ($session['status'] === 'authorized') {
        $user = User::getById($session['user_id']);
        $device = Device::getByDeviceId($session['device_id']);

        if (!$device) {
            $deviceId = Device::create($session['user_id'], $session['device_id']);
        } else {
            $deviceId = $device['id'];
        }

        $userToken = User::generateToken($session['user_id']);
        OAuthSession::updateStatus($sessionToken, 'completed');

        Response::success([
            'status' => 'authorized',
            'user_token' => $userToken,
            'user_info' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'avatar_url' => $user['avatar_url']
            ],
            'device_id' => $deviceId
        ]);
    }

    Response::error(1010, '未知会话状态');
}

// 处理令牌刷新
function handleRefresh(): void {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        Response::error(1008, 'Token 无效');
    }

    $userToken = $matches[1];
    $user = User::getByToken($userToken);

    if (!$user) {
        Response::error(1008, 'Token 无效或过期');
    }

    $newToken = User::generateToken($user['id']);
    $expiresAt = date('c', time() + (int)Config::get('USER_TOKEN_LIFETIME', 7200));

    Response::success([
        'user_token' => $newToken,
        'expires_at' => $expiresAt
    ]);
}

// 处理登出
function handleLogout(): void {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        Response::error(1008, 'Token 无效');
    }

    $userToken = $matches[1];
    User::revokeToken($userToken);

    Response::success(null, '登出成功');
}
```

#### 5.2 实现 OAuth 回调接口

创建 [`api/callback.php`](../api/callback.php)：

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use KindleReading\Core\Config;
use KindleReading\Core\Database;
use KindleReading\Core\Logger;
use KindleReading\Auth\OAuth;
use KindleReading\Models\OAuthSession;
use KindleReading\Models\User;

// 加载配置
Config::load(__DIR__ . '/../.env');
Database::init(Config::getDatabaseConfig());
Logger::init(Config::get('LOG_PATH', 'storage/logs'), Config::get('LOG_LEVEL', 'info'));

// 获取回调参数
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (!$code || !$state) {
    showError('参数缺失');
}

// 查找会话
$session = OAuthSession::getByState($state);

if (!$session) {
    showError('会话不存在或已过期');
}

if (OAuthSession::isExpired($session)) {
    showError('会话已过期');
}

// 获取 Access Token
$oauth = new OAuth();
$tokenData = $oauth->getAccessToken($code);

if (!$tokenData || !isset($tokenData['access_token'])) {
    Logger::error('GitHub OAuth 获取 Access Token 失败', ['token_data' => $tokenData]);
    showError('授权失败，请重试');
}

// 获取用户信息
$githubUser = $oauth->getUserInfo($tokenData['access_token']);

if (!$githubUser) {
    Logger::error('GitHub OAuth 获取用户信息失败', ['github_user' => $githubUser]);
    showError('获取用户信息失败，请重试');
}

// 创建或更新用户
$userId = $oauth->createOrUpdateUser($githubUser, $tokenData['access_token']);

if (!$userId) {
    Logger::error('创建或更新用户失败', ['github_user' => $githubUser]);
    showError('用户创建失败，请重试');
}

// 更新会话状态
OAuthSession::updateStatus($session['session_token'], 'authorized', $userId);

// 显示成功页面
showSuccess();

function showSuccess(): void {
    echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授权成功</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        p {
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">✅</div>
        <h1>授权成功！</h1>
        <p>您的 Kindle 设备已成功登录。</p>
        <p>请返回 Kindle 设备继续操作。</p>
    </div>
</body>
</html>';
}

function showError(string $message): void {
    echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授权失败</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        p {
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">❌</div>
        <h1>授权失败</h1>
        <p>' . htmlspecialchars($message) . '</p>
        <p>请返回 Kindle 设备重新尝试。</p>
    </div>
</body>
</html>';
    exit;
}
```

---

### 阶段 6: 文件上传实现

#### 6.1 实现文件上传服务

创建 [`src/Services/UploadService.php`](../src/Services/UploadService.php)：

```php
<?php
namespace KindleReading\Services;

use KindleReading\Core\Config;
use KindleReading\Core\Database;
use KindleReading\Core\Logger;
use KindleReading\Utils\FileHelper;
use KindleReading\Utils\SecurityHelper;

class UploadService {
    private string $uploadPath;
    private int $maxFileSize;
    private array $allowedExtensions;

    public function __construct() {
        $this->uploadPath = Config::get('UPLOAD_PATH', 'public/uploads');
        $this->maxFileSize = (int)Config::get('MAX_FILE_SIZE', 104857600);
        $this->allowedExtensions = explode(',', Config::get('ALLOWED_EXTENSIONS', 'json,gz,txt,log'));
        
        FileHelper::ensureDirectory($this->uploadPath);
    }

    public function uploadFiles(int $userId, int $deviceId, array $files): array {
        $uploadedFiles = [];
        $totalSize = 0;

        foreach ($files['name'] as $index => $fileName) {
            $tmpName = $files['tmp_name'][$index];
            $fileSize = $files['size'][$index];
            $fileError = $files['error'][$index];

            if ($fileError !== UPLOAD_ERR_OK) {
                Logger::error('文件上传错误', [
                    'user_id' => $userId,
                    'device_id' => $deviceId,
                    'file_name' => $fileName,
                    'error_code' => $fileError
                ]);
                continue;
            }

            // 验证文件大小
            if ($fileSize > $this->maxFileSize) {
                Logger::warning('文件大小超限', [
                    'user_id' => $userId,
                    'file_name' => $fileName,
                    'file_size' => $fileSize,
                    'max_size' => $this->maxFileSize
                ]);
                continue;
            }

            // 验证文件扩展名
            if (!FileHelper::isAllowedExtension($fileName, $this->allowedExtensions)) {
                Logger::warning('文件类型不允许', [
                    'user_id' => $userId,
                    'file_name' => $fileName
                ]);
                continue;
            }

            // 验证 MIME 类型
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $tmpName);
            finfo_close($finfo);

            $allowedMimeTypes = [
                'application/json',
                'application/gzip',
                'text/plain',
                'text/x-log'
            ];

            if (!in_array($mimeType, $allowedMimeTypes)) {
                Logger::warning('MIME 类型不允许', [
                    'user_id' => $userId,
                    'file_name' => $fileName,
                    'mime_type' => $mimeType
                ]);
                continue;
            }

            // 生成新文件名
            $extension = FileHelper::getExtension($fileName);
            $newFileName = SecurityHelper::generateUuid() . '.' . $extension;
            $filePath = $this->uploadPath . '/' . $newFileName;

            // 移动文件
            if (!move_uploaded_file($tmpName, $filePath)) {
                Logger::error('文件移动失败', [
                    'user_id' => $userId,
                    'file_name' => $fileName,
                    'target_path' => $filePath
                ]);
                continue;
            }

            // 计算文件哈希
            $fileHash = SecurityHelper::calculateFileHash($filePath);

            // 提取日志日期（从文件名）
            $logDate = $this->extractLogDate($fileName);

            // 保存到数据库
            $sql = "INSERT INTO reading_logs (user_id, device_id, file_path, file_name, file_size, file_hash, log_date) 
                    VALUES (:user_id, :device_id, :file_path, :file_name, :file_size, :file_hash, :log_date)";
            
            Database::execute($sql, [
                ':user_id' => $userId,
                ':device_id' => $deviceId,
                ':file_path' => $filePath,
                ':file_name' => $fileName,
                ':file_size' => $fileSize,
                ':file_hash' => $fileHash,
                ':log_date' => $logDate,
            ]);

            $logId = Database::lastInsertId();

            $uploadedFiles[] = [
                'id' => $logId,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'file_hash' => $fileHash,
                'log_date' => $logDate
            ];

            $totalSize += $fileSize;

            Logger::info('文件上传成功', [
                'user_id' => $userId,
                'device_id' => $deviceId,
                'file_name' => $fileName,
                'file_size' => $fileSize
            ]);
        }

        return [
            'uploaded_files' => count($uploadedFiles),
            'total_size' => $totalSize,
            'files' => $uploadedFiles
        ];
    }

    private function extractLogDate(string $fileName): string {
        // 尝试从文件名提取日期（格式：metrics_reader_YYYYMMDD.json）
        if (preg_match('/(\d{8})/', $fileName, $matches)) {
            $dateStr = $matches[1];
            return substr($dateStr, 0, 4) . '-' . substr($dateStr, 4, 2) . '-' . substr($dateStr, 6, 2);
        }

        return date('Y-m-d');
    }
}
```

#### 6.2 实现上传接口

创建 [`api/upload.php`](../api/upload.php)：

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use KindleReading\Core\Config;
use KindleReading\Core\Database;
use KindleReading\Core\Response;
use KindleReading\Core\Logger;
use KindleReading\Models\User;
use KindleReading\Models\Device;
use KindleReading\Services\UploadService;

// 加载配置
Config::load(__DIR__ . '/../.env');
Database::init(Config::getDatabaseConfig());
Logger::init(Config::get('LOG_PATH', 'storage/logs'), Config::get('LOG_LEVEL', 'info'));

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(1001, '不支持的请求方法');
}

// 验证认证
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    Response::error(1008, 'Token 无效');
}

$userToken = $matches[1];
$user = User::getByToken($userToken);

if (!$user) {
    Response::error(1008, 'Token 无效或过期');
}

// 验证设备
$deviceId = $_POST['device_id'] ?? '';

if (!$deviceId) {
    Response::error(1001, '参数缺失', ['field' => 'device_id']);
}

$device = Device::getByDeviceId($deviceId);

if (!$device || $device['user_id'] != $user['id']) {
    Response::error(1014, '设备不存在');
}

// 验证文件
if (!isset($_FILES['files']) || !is_array($_FILES['files']['name'])) {
    Response::error(1001, '参数缺失', ['field' => 'files']);
}

// 上传文件
$uploadService = new UploadService();
$result = $uploadService->uploadFiles($user['id'], $device['id'], $_FILES['files']);

Response::success($result, '文件上传成功');
```

---

### 阶段 7: 数据模型实现

#### 7.1 实现用户模型

创建 [`src/Models/User.php`](../src/Models/User.php)：

```php
<?php
namespace KindleReading\Models;

use KindleReading\Core\Database;
use KindleReading\Utils\SecurityHelper;

class User {
    public static function getById(int $id): ?array {
        return Database::fetchOne("SELECT * FROM users WHERE id = :id", [':id' => $id]);
    }

    public static function getByGithubUid(int $githubUid): ?array {
        return Database::fetchOne("SELECT * FROM users WHERE github_uid = :github_uid", [':github_uid' => $githubUid]);
    }

    public static function getByToken(string $token): ?array {
        return Database::fetchOne("SELECT * FROM users WHERE access_token = :token", [':token' => $token]);
    }

    public static function generateToken(int $userId): string {
        $token = SecurityHelper::generateUserToken();
        $expiresAt = date('Y-m-d H:i:s', time() + (int)Config::get('USER_TOKEN_LIFETIME', 7200));
        
        Database::execute("UPDATE users SET access_token = :token, token_expires_at = :expires_at WHERE id = :id", [
            ':token' => $token,
            ':expires_at' => $expiresAt,
            ':id' => $userId
        ]);

        return $token;
    }

    public static function revokeToken(string $token): bool {
        return Database::execute("UPDATE users SET access_token = NULL, token_expires_at = NULL WHERE access_token = :token", [
            ':token' => $token
        ]);
    }
}
```

#### 7.2 实现设备模型

创建 [`src/Models/Device.php`](../src/Models/Device.php)：

```php
<?php
namespace KindleReading\Models;

use KindleReading\Core\Database;

class Device {
    public static function getById(int $id): ?array {
        return Database::fetchOne("SELECT * FROM kindle_devices WHERE id = :id", [':id' => $id]);
    }

    public static function getByDeviceId(string $deviceId): ?array {
        return Database::fetchOne("SELECT * FROM kindle_devices WHERE device_id = :device_id", [':device_id' => $deviceId]);
    }

    public static function getByUserId(int $userId): array {
        return Database::fetchAll("SELECT * FROM kindle_devices WHERE user_id = :user_id ORDER BY created_at DESC", [
            ':user_id' => $userId
        ]);
    }

    public static function create(int $userId, string $deviceId, string $deviceName = null): ?int {
        $sql = "INSERT INTO kindle_devices (user_id, device_id, device_name) 
                VALUES (:user_id, :device_id, :device_name)";
        
        if (Database::execute($sql, [
            ':user_id' => $userId,
            ':device_id' => $deviceId,
            ':device_name' => $deviceName ?: 'Kindle Device',
        ])) {
            return (int)Database::lastInsertId();
        }

        return null;
    }

    public static function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }

        $sql = "UPDATE kindle_devices SET " . implode(', ', $fields) . " WHERE id = :id";
        return Database::execute($sql, $params);
    }

    public static function delete(int $id): bool {
        return Database::execute("DELETE FROM kindle_devices WHERE id = :id", [':id' => $id]);
    }
}
```

---

### 阶段 8: Nginx 配置

创建 [`nginx/kindle-reading.conf`](../nginx/kindle-reading.conf)：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;

    # SSL 证书配置
    ssl_certificate /path/to/your/certificate.crt;
    ssl_certificate_key /path/to/your/private.key;

    # SSL 安全配置
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # 根目录
    root /var/www/kindle-reading-php/public;
    index index.php index.html;

    # 字符集
    charset utf-8;

    # 日志
    access_log /var/log/nginx/kindle-reading-access.log;
    error_log /var/log/nginx/kindle-reading-error.log;

    # 禁止访问敏感目录
    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~ /(config|src|storage|database|tests|scripts|plans|docs)/ {
        deny all;
    }

    # 禁止执行上传目录中的 PHP 文件
    location ~* ^/uploads/.*\.php$ {
        deny all;
    }

    # PHP 处理
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # 超时设置
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }

    # 文件上传大小限制
    client_max_body_size 100M;

    # 静态文件缓存
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # API 接口
    location /api/ {
        try_files $uri $uri/ /api/index.php?$query_string;
    }

    # 前端路由
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # 安全头
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
```

---

### 阶段 9: 部署文档

创建 [`docs/deployment.md`](../docs/deployment.md)：

```markdown
# 部署文档

## 环境要求

- PHP 8.1+
- MySQL/MariaDB 8.0+
- Nginx
- Composer

## 部署步骤

### 1. 克隆代码

```bash
git clone https://github.com/your-repo/kindle-reading-php.git
cd kindle-reading-php
```

### 2. 安装依赖

```bash
composer install --no-dev --optimize-autoloader
```

### 3. 配置环境变量

```bash
cp .env.example .env
nano .env
```

编辑 `.env` 文件，配置数据库和 OAuth 凭据。

### 4. 创建数据库

```bash
mysql -u root -p < database/schema.sql
```

### 5. 设置目录权限

```bash
chmod 755 storage
chmod 755 storage/logs
chmod 755 storage/cache
chmod 755 storage/sessions
chmod 755 public/uploads
chmod 600 .env
```

### 6. 配置 Nginx

复制 Nginx 配置文件：

```bash
sudo cp nginx/kindle-reading.conf /etc/nginx/sites-available/kindle-reading
sudo ln -s /etc/nginx/sites-available/kindle-reading /etc/nginx/sites-enabled/
sudo nano /etc/nginx/sites-available/kindle-reading
```

修改配置中的域名和 SSL 证书路径。

重启 Nginx：

```bash
sudo nginx -t
sudo systemctl reload nginx
```

### 7. 配置 GitHub OAuth

1. 访问 https://github.com/settings/developers
2. 点击 "New OAuth App"
3. 填写应用信息：
   - Application name: Kindle Reading GTK
   - Homepage URL: https://your-domain.com
   - Authorization callback URL: https://your-domain.com/api/callback.php
4. 获取 Client ID 和 Client Secret
5. 更新 `.env` 文件中的 `GITHUB_CLIENT_ID` 和 `GITHUB_CLIENT_SECRET`

### 8. 测试部署

访问 https://your-domain.com/api/health 检查服务状态。

## 维护

### 日志查看

```bash
tail -f storage/logs/error.log
tail -f storage/logs/info.log
```

### 数据备份

```bash
mysqldump -u root -p kindle_reading > backup_$(date +%Y%m%d).sql
```

### 清理过期数据

```bash
php scripts/cleanup.php
```
```

---

## 测试计划

### 单元测试

1. **数据库连接测试**
   - 测试数据库连接是否正常
   - 测试预处理语句是否正确执行

2. **OAuth 流程测试**
   - 测试会话创建
   - 测试状态查询
   - 测试令牌生成

3. **文件上传测试**
   - 测试文件类型验证
   - 测试文件大小限制
   - 测试文件哈希计算

### 集成测试

1. **完整 OAuth 流程**
   - 请求登录 → 获取二维码
   - 扫码授权 → 回调处理
   - 轮询状态 → 获取令牌

2. **文件上传流程**
   - 认证 → 上传文件 → 验证存储

### 安全测试

1. **SQL 注入测试**
   - 尝试注入恶意 SQL 语句

2. **文件上传测试**
   - 尝试上传 PHP 文件
   - 尝试上传超大文件
   - 尝试上传恶意文件

3. **XSS 测试**
   - 尝试注入恶意脚本

---

## 项目时间线

| 阶段 | 任务 | 预计时间 |
|------|------|----------|
| 1 | 项目初始化 | 1 天 |
| 2 | 数据库实现 | 1 天 |
| 3 | 核心类实现 | 2 天 |
| 4 | OAuth 认证实现 | 2 天 |
| 5 | API 接口实现 | 3 天 |
| 6 | 文件上传实现 | 2 天 |
| 7 | 数据模型实现 | 1 天 |
| 8 | Nginx 配置 | 0.5 天 |
| 9 | 测试与调试 | 2 天 |
| 10 | 文档编写 | 1 天 |
| **总计** | | **15.5 天** |

---

## 风险与应对

| 风险 | 影响 | 应对措施 |
|------|------|----------|
| GitHub OAuth 限制 | 无法使用 GitHub 登录 | 准备备用 OAuth 提供商 |
| 文件上传性能问题 | 上传速度慢 | 使用异步处理、分片上传 |
| 数据库性能问题 | 查询缓慢 | 添加索引、使用缓存 |
| 安全漏洞 | 数据泄露 | 代码审计、安全测试 |

---

## 总结

本实施计划详细描述了 Kindle Reading GTK 云同步服务端的完整开发流程，包括：

1. ✅ 数据库设计（7 张表）
2. ✅ 项目目录结构
3. ✅ OAuth 2.0 认证流程
4. ✅ API 接口规范
5. ✅ 核心类实现
6. ✅ 文件上传安全处理
7. ✅ Nginx 配置
8. ✅ 部署文档

所有设计文档已完成，可以开始进入 Code 模式进行代码实现。