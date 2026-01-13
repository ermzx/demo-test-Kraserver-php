# Kindle Reading GTK 云同步服务端 - OAuth 2.0 流程设计

## 概述

本文档定义了 Kindle Reading GTK 云同步服务端的 GitHub OAuth 2.0 认证流程设计。

## OAuth 2.0 流程概述

本项目使用 **Authorization Code Flow**（授权码流程）实现 GitHub OAuth 2.0 认证，采用 **轮询机制** 让 Kindle 设备获取登录状态。

## 流程图

```mermaid
sequenceDiagram
    participant Kindle as Kindle 设备
    participant Server as 服务器
    participant GitHub as GitHub OAuth
    participant User as 用户（手机）

    Note over Kindle,User: 步骤 1: Kindle 请求登录
    Kindle->>Server: POST /api/auth/request<br/>{device_id}
    Server->>Server: 生成 session_token 和 state
    Server->>Server: 创建 OAuth 会话记录
    Server-->>Kindle: {session_token, qr_code_url}

    Note over Kindle,User: 步骤 2: 用户扫码授权
    Kindle->>User: 显示二维码
    User->>GitHub: 扫码访问授权页面
    GitHub->>User: 显示授权确认页面
    User->>GitHub: 确认授权
    GitHub->>Server: GET /api/callback.php?code=xxx&state=yyy
    Server->>GitHub: POST /login/oauth/access_token<br/>{code, client_id, client_secret}
    GitHub-->>Server: {access_token, refresh_token}
    Server->>GitHub: GET /user<br/>{Authorization: Bearer token}
    GitHub-->>Server: {id, login, avatar_url}
    Server->>Server: 创建/更新用户记录
    Server->>Server: 更新 OAuth 会话状态为 authorized
    Server-->>User: 授权成功页面

    Note over Kindle,User: 步骤 3: Kindle 轮询登录状态
    loop 轮询（每 2 秒）
        Kindle->>Server: GET /api/auth/status?session_token=xxx
        Server->>Server: 查询 OAuth 会话状态
        alt 状态为 pending
            Server-->>Kindle: {status: pending}
        else 状态为 authorized
            Server->>Server: 生成用户访问令牌
            Server->>Server: 绑定设备到用户
            Server->>Server: 更新会话状态为 completed
            Server-->>Kindle: {status: authorized, user_token, user_info}
        else 状态为 expired
            Server-->>Kindle: {status: expired}
        end
    end

    Note over Kindle,User: 步骤 4: Kindle 使用令牌上传数据
    Kindle->>Server: POST /api/upload<br/>{user_token, files}
    Server->>Server: 验证用户令牌
    Server->>Server: 处理文件上传
    Server-->>Kindle: {success: true}
```

## 详细流程说明

### 步骤 1: Kindle 请求登录

**请求**: `POST /api/auth/request`

**请求参数**:
```json
{
  "device_id": "kindle-device-uuid-12345"
}
```

**服务器处理**:
1. 验证 `device_id` 格式（UUID 格式）
2. 生成 `session_token`（UUID v4）
3. 生成 `state`（随机字符串，用于 CSRF 防护）
4. 创建 OAuth 会话记录到 `oauth_sessions` 表
5. 生成授权 URL：`https://github.com/login/oauth/authorize?client_id={client_id}&redirect_uri={redirect_uri}&state={state}&scope=read:user,user:email`
6. 生成二维码 URL（包含授权 URL）

**响应**:
```json
{
  "success": true,
  "data": {
    "session_token": "550e8400-e29b-41d4-a716-446655440000",
    "qr_code_url": "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=https%3A%2F%2Fgithub.com%2Flogin%2Foauth%2Fauthorize%3F...",
    "expires_at": "2024-01-13T13:05:00Z"
  }
}
```

**数据库记录** (`oauth_sessions` 表):
```sql
INSERT INTO oauth_sessions (session_token, device_id, state, status, expires_at)
VALUES ('550e8400-e29b-41d4-a716-446655440000', 'kindle-device-uuid-12345', 'random_state_123', 'pending', DATE_ADD(NOW(), INTERVAL 5 MINUTE));
```

---

### 步骤 2: 用户扫码授权

**用户操作**:
1. Kindle 显示二维码
2. 用户使用手机扫描二维码
3. 手机浏览器跳转到 GitHub 授权页面
4. 用户确认授权

**GitHub 回调**: `GET /api/callback.php?code=xxx&state=yyy`

**服务器处理**:
1. 验证 `state` 参数是否匹配
2. 使用 `code` 向 GitHub 请求 Access Token
3. 使用 Access Token 获取用户信息
4. 创建或更新用户记录
5. 更新 OAuth 会话状态为 `authorized`

**请求 GitHub Access Token**:
```http
POST https://github.com/login/oauth/access_token
Content-Type: application/json

{
  "client_id": "your_client_id",
  "client_secret": "your_client_secret",
  "code": "authorization_code_from_github"
}
```

**GitHub 响应**:
```json
{
  "access_token": "gho_xxxxxxxxxxxx",
  "token_type": "bearer",
  "scope": "read:user,user:email"
}
```

**请求 GitHub 用户信息**:
```http
GET https://api.github.com/user
Authorization: Bearer gho_xxxxxxxxxxxx
```

**GitHub 响应**:
```json
{
  "id": 12345678,
  "login": "username",
  "avatar_url": "https://avatars.githubusercontent.com/u/12345678?v=4",
  "email": "user@example.com"
}
```

**数据库操作**:
```sql
-- 创建或更新用户
INSERT INTO users (github_uid, username, avatar_url, access_token, last_login_at)
VALUES (12345678, 'username', 'https://avatars.githubusercontent.com/u/12345678?v=4', 'gho_xxxxxxxxxxxx', NOW())
ON DUPLICATE KEY UPDATE 
  username = VALUES(username),
  avatar_url = VALUES(avatar_url),
  access_token = VALUES(access_token),
  last_login_at = VALUES(last_login_at);

-- 更新 OAuth 会话状态
UPDATE oauth_sessions 
SET status = 'authorized', user_id = (SELECT id FROM users WHERE github_uid = 12345678)
WHERE state = 'random_state_123';
```

**用户浏览器响应**:
```html
<!DOCTYPE html>
<html>
<head>
    <title>授权成功</title>
    <meta charset="utf-8">
</head>
<body>
    <h1>授权成功！</h1>
    <p>您的 Kindle 设备已成功登录。</p>
    <p>请返回 Kindle 设备继续操作。</p>
</body>
</html>
```

---

### 步骤 3: Kindle 轮询登录状态

**请求**: `GET /api/auth/status?session_token=xxx`

**服务器处理**:
1. 验证 `session_token` 是否存在
2. 检查会话是否过期
3. 返回当前会话状态

**响应 - 状态为 pending**:
```json
{
  "success": true,
  "data": {
    "status": "pending",
    "message": "等待用户授权..."
  }
}
```

**响应 - 状态为 authorized**:
```json
{
  "success": true,
  "data": {
    "status": "authorized",
    "user_token": "user_access_token_abc123",
    "user_info": {
      "id": 1,
      "username": "username",
      "avatar_url": "https://avatars.githubusercontent.com/u/12345678?v=4"
    },
    "device_id": 1
  }
}
```

**服务器处理（状态为 authorized 时）**:
1. 生成用户访问令牌（`user_token`）
2. 绑定设备到用户（创建或更新 `kindle_devices` 记录）
3. 更新 OAuth 会话状态为 `completed`
4. 记录完成时间

**数据库操作**:
```sql
-- 绑定设备到用户
INSERT INTO kindle_devices (user_id, device_id, device_name, last_sync_at)
VALUES (1, 'kindle-device-uuid-12345', 'My Kindle', NOW())
ON DUPLICATE KEY UPDATE 
  user_id = VALUES(user_id),
  last_sync_at = VALUES(last_sync_at);

-- 更新会话状态
UPDATE oauth_sessions 
SET status = 'completed', completed_at = NOW()
WHERE session_token = '550e8400-e29b-41d4-a716-446655440000';
```

**响应 - 状态为 expired**:
```json
{
  "success": false,
  "error": {
    "code": "session_expired",
    "message": "会话已过期，请重新登录"
  }
}
```

---

### 步骤 4: Kindle 使用令牌上传数据

**请求**: `POST /api/upload`

**请求头**:
```
Authorization: Bearer user_access_token_abc123
Content-Type: multipart/form-data
```

**请求参数**:
```
files[]: [上传的日志文件]
```

**服务器处理**:
1. 验证 `user_token`
2. 获取用户 ID 和设备 ID
3. 验证文件类型、大小
4. 存储文件
5. 记录文件信息到数据库
6. 解析日志文件（异步或同步）
7. 更新统计数据

**响应**:
```json
{
  "success": true,
  "data": {
    "uploaded_files": 5,
    "total_size": 1024000,
    "message": "文件上传成功"
  }
}
```

---

## API 接口规范

### 1. 请求登录

**接口**: `POST /api/auth/request`

**请求参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_id | string | 是 | Kindle 设备 UUID |

**响应**:
```json
{
  "success": true,
  "data": {
    "session_token": "uuid",
    "qr_code_url": "https://...",
    "expires_at": "ISO 8601 datetime"
  }
}
```

---

### 2. 查询登录状态

**接口**: `GET /api/auth/status`

**请求参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| session_token | string | 是 | 会话令牌 |

**响应**:
```json
{
  "success": true,
  "data": {
    "status": "pending|authorized|expired",
    "user_token": "string (仅 authorized 时)",
    "user_info": {
      "id": 1,
      "username": "string",
      "avatar_url": "string"
    },
    "device_id": 1
  }
}
```

---

### 3. OAuth 回调

**接口**: `GET /api/callback.php`

**请求参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| code | string | 是 | GitHub 授权码 |
| state | string | 是 | OAuth state 参数 |

**响应**: HTML 页面（授权成功/失败）

---

### 4. 刷新令牌

**接口**: `POST /api/auth/refresh`

**请求头**:
```
Authorization: Bearer user_access_token
```

**响应**:
```json
{
  "success": true,
  "data": {
    "user_token": "new_user_token",
    "expires_at": "ISO 8601 datetime"
  }
}
```

---

### 5. 登出

**接口**: `POST /api/auth/logout`

**请求头**:
```
Authorization: Bearer user_access_token
```

**响应**:
```json
{
  "success": true,
  "message": "登出成功"
}
```

---

## 安全考虑

### 1. CSRF 防护

- 使用 `state` 参数防止 CSRF 攻击
- `state` 必须是随机生成的唯一字符串
- 验证回调时检查 `state` 是否匹配

### 2. 会话管理

- OAuth 会话默认 5 分钟过期
- 超时后自动标记为 `expired`
- 完成后自动标记为 `completed`

### 3. Token 安全

- Access Token 加密存储在数据库
- 用户访问令牌使用 JWT 或随机字符串
- Token 设置合理的过期时间
- 支持 Token 刷新机制

### 4. 限流保护

- 限制登录请求频率（例如：每分钟最多 10 次）
- 限制轮询请求频率（例如：每秒最多 1 次）
- 使用 IP 限流防止暴力攻击

### 5. HTTPS 要求

- 生产环境必须使用 HTTPS
- OAuth 回调 URL 必须是 HTTPS
- 防止中间人攻击

---

## 错误处理

### 错误码定义

| 错误码 | 说明 |
|--------|------|
| 1001 | 参数缺失或无效 |
| 1002 | 设备 ID 格式错误 |
| 1003 | 会话不存在 |
| 1004 | 会话已过期 |
| 1005 | OAuth 授权失败 |
| 1006 | 用户创建失败 |
| 1007 | 设备绑定失败 |
| 1008 | Token 无效或过期 |
| 1009 | 请求频率超限 |
| 1010 | 服务器内部错误 |

### 错误响应格式

```json
{
  "success": false,
  "error": {
    "code": 1001,
    "message": "参数缺失或无效",
    "details": {
      "field": "device_id",
      "reason": "required"
    }
  }
}
```

---

## 配置参数

### OAuth 配置

| 参数 | 说明 | 默认值 |
|------|------|--------|
| GITHUB_CLIENT_ID | GitHub OAuth Client ID | - |
| GITHUB_CLIENT_SECRET | GitHub OAuth Client Secret | - |
| GITHUB_REDIRECT_URI | OAuth 回调 URL | - |
| GITHUB_SCOPE | OAuth 请求权限 | read:user,user:email |

### 会话配置

| 参数 | 说明 | 默认值 |
|------|------|--------|
| SESSION_TIMEOUT | OAuth 会话超时时间（秒） | 300 |
| USER_TOKEN_LIFETIME | 用户令牌有效期（秒） | 7200 |
| POLLING_INTERVAL | 轮询间隔（秒） | 2 |
| MAX_POLLING_ATTEMPTS | 最大轮询次数 | 150 |

### 限流配置

| 参数 | 说明 | 默认值 |
|------|------|--------|
| RATE_LIMIT_LOGIN | 登录请求限制（次/分钟） | 10 |
| RATE_LIMIT_POLLING | 轮询请求限制（次/秒） | 1 |
| RATE_LIMIT_UPLOAD | 上传请求限制（次/分钟） | 5 |

---

## 实现要点

### 1. UUID 生成

使用 PHP 的 `ramsey/uuid` 库或原生函数生成 UUID：

```php
function generateUuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
```

### 2. State 生成

生成随机字符串用于 CSRF 防护：

```php
function generateState(int $length = 32): string {
    return bin2hex(random_bytes($length / 2));
}
```

### 3. 用户令牌生成

生成安全的用户访问令牌：

```php
function generateUserToken(): string {
    return hash('sha256', random_bytes(32) . microtime(true));
}
```

### 4. 二维码生成

使用第三方 API 生成二维码：

```php
function generateQrCodeUrl(string $data, int $size = 300): string {
    $encodedData = urlencode($data);
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedData}";
}
```

---

## 测试场景

### 1. 正常流程测试

- Kindle 请求登录 → 获取二维码
- 用户扫码授权 → 授权成功
- Kindle 轮询 → 获取用户令牌
- Kindle 上传数据 → 上传成功

### 2. 异常流程测试

- 会话过期 → 返回 expired 状态
- State 不匹配 → 授权失败
- Token 无效 → 返回 401 错误
- 请求频率超限 → 返回 429 错误

### 3. 安全测试

- CSRF 攻击 → 被 state 验证拦截
- 重放攻击 → 会话状态检查拦截
- 中间人攻击 → HTTPS 保护

---

## 扩展功能

### 1. 多设备支持

- 一个 GitHub 账号可以绑定多个 Kindle 设备
- 设备管理界面（查看、解绑设备）

### 2. 设备命名

- 用户可以为设备设置自定义名称
- 设备信息显示在统计页面

### 3. 登录历史

- 记录用户登录历史
- 显示最后登录时间和设备

### 4. 通知功能

- 新设备绑定时发送邮件通知
- 异常登录时发送警告