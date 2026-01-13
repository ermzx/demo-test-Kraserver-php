# Kindle Reading GTK 云同步服务端 - 更新后的设计

## 用户反馈总结

根据用户反馈，对原设计进行以下调整：

1. **Kindle 轮询机制**：Kindle 端轮询服务器获取登录状态（已实现）
2. **GitHub OAuth 配置**：Kindle 不需要配置，只需预留 client_id 和 secret 字段
3. **二维码**：编码网页链接，手机扫码完成授权即可
4. **日志存储**：原样存储，不需要解析
5. **PHP 版本**：PHP 8（最新版本）
6. **Nginx/HTTPS**：用户自行配置
7. **用户管理界面**：需要添加，但用户注册由 GitHub OAuth 完成
8. **多设备支持**：一个账号可绑定多个设备，记录合并
9. **日志格式**：Kindle 日志格式为 `metric_generic,timestamp,timer,appmgrd,logAppActiveDuration,com.lab126.booklet.reader.activeDuration,duration_ms`

---

## 更新后的数据库设计

### 简化后的表结构

#### 1. users 表 - GitHub 用户信息

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| id | INT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | 用户 ID |
| github_uid | VARCHAR(50) | NOT NULL, UNIQUE | GitHub 用户 ID |
| username | VARCHAR(100) | NOT NULL | GitHub 用户名 |
| avatar_url | VARCHAR(500) | NULL | GitHub 头像 URL |
| created_at | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | 创建时间 |
| last_login_at | DATETIME | NULL | 最后登录时间 |

**索引**:
- PRIMARY KEY: `id`
- UNIQUE: `github_uid`
- INDEX: `username`, `last_login_at`

---

#### 2. kindle_devices 表 - Kindle 设备信息

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| id | INT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | 设备 ID |
| user_id | INT UNSIGNED | NOT NULL, FOREIGN KEY | 关联用户 ID |
| device_id | VARCHAR(100) | NOT NULL, UNIQUE | Kindle 设备唯一标识 |
| device_name | VARCHAR(200) | NULL | 设备名称（用户自定义） |
| created_at | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | 绑定时间 |
| last_sync_at | DATETIME | NULL | 最后同步时间 |

**索引**:
- PRIMARY KEY: `id`
- UNIQUE: `device_id`
- INDEX: `user_id`, `last_sync_at`
- FOREIGN KEY: `user_id` → `users(id)` ON DELETE CASCADE

---

#### 3. reading_logs 表 - 阅读日志文件（原样存储）

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| id | INT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | 日志 ID |
| user_id | INT UNSIGNED | NOT NULL, FOREIGN KEY | 关联用户 ID |
| device_id | INT UNSIGNED | NOT NULL, FOREIGN KEY | 关联设备 ID |
| file_path | VARCHAR(500) | NOT NULL | 文件存储路径 |
| file_name | VARCHAR(255) | NOT NULL | 原始文件名 |
| file_size | BIGINT UNSIGNED | NOT NULL | 文件大小（字节） |
| file_hash | VARCHAR(64) | NOT NULL | 文件 SHA256 哈希值 |
| upload_at | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | 上传时间 |

**索引**:
- PRIMARY KEY: `id`
- INDEX: `user_id`, `device_id`, `upload_at`, `file_hash`
- FOREIGN KEY: `user_id` → `users(id)` ON DELETE CASCADE
- FOREIGN KEY: `device_id` → `kindle_devices(id)` ON DELETE CASCADE

---

#### 4. oauth_sessions 表 - OAuth 会话

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| id | INT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | 会话 ID |
| session_token | VARCHAR(64) | NOT NULL, UNIQUE | 会话令牌（UUID） |
| device_id | VARCHAR(100) | NOT NULL | Kindle 设备 ID |
| state | VARCHAR(64) | NOT NULL | OAuth state 参数 |
| status | ENUM | NOT NULL, DEFAULT 'pending' | 会话状态：pending/authorized/completed/expired |
| user_id | INT UNSIGNED | NULL, FOREIGN KEY | 授权后关联的用户 ID |
| created_at | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | 创建时间 |
| expires_at | DATETIME | NOT NULL | 过期时间 |
| completed_at | DATETIME | NULL | 完成时间 |

**索引**:
- PRIMARY KEY: `id`
- UNIQUE: `session_token`
- INDEX: `device_id`, `state`, `status`, `expires_at`
- FOREIGN KEY: `user_id` → `users(id)` ON DELETE SET NULL

---

#### 5. system_config 表 - 系统配置

| 字段名 | 类型 | 约束 | 说明 |
|--------|------|------|------|
| id | INT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | 配置 ID |
| config_key | VARCHAR(100) | NOT NULL, UNIQUE | 配置键 |
| config_value | TEXT | NULL | 配置值 |
| description | VARCHAR(500) | NULL | 配置说明 |
| created_at | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | 创建时间 |
| updated_at | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE | 更新时间 |

**索引**:
- PRIMARY KEY: `id`
- UNIQUE: `config_key`

**默认配置**:
| config_key | config_value | description |
|------------|--------------|-------------|
| max_file_size | 104857600 | 最大上传文件大小（字节），默认 100MB |
| allowed_file_extensions | log,txt | 允许上传的文件扩展名 |
| session_timeout | 300 | OAuth 会话超时时间（秒），默认 5 分钟 |

---

### 完整 SQL 建表语句

```sql
-- Kindle Reading GTK 云同步服务端数据库结构（简化版）
-- 字符集: utf8mb4
-- 排序规则: utf8mb4_unicode_ci

CREATE DATABASE IF NOT EXISTS kindle_reading CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE kindle_reading;

-- ============================================
-- 用户表 (users)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '用户 ID',
    github_uid VARCHAR(50) NOT NULL UNIQUE COMMENT 'GitHub 用户 ID',
    username VARCHAR(100) NOT NULL COMMENT 'GitHub 用户名',
    avatar_url VARCHAR(500) DEFAULT NULL COMMENT 'GitHub 头像 URL',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    last_login_at DATETIME DEFAULT NULL COMMENT '最后登录时间',
    
    INDEX idx_github_uid (github_uid),
    INDEX idx_username (username),
    INDEX idx_last_login_at (last_login_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GitHub 用户信息表';

-- ============================================
-- Kindle 设备表 (kindle_devices)
-- ============================================
CREATE TABLE IF NOT EXISTS kindle_devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '设备 ID',
    user_id INT UNSIGNED NOT NULL COMMENT '关联用户 ID',
    device_id VARCHAR(100) NOT NULL UNIQUE COMMENT 'Kindle 设备唯一标识',
    device_name VARCHAR(200) DEFAULT NULL COMMENT '设备名称',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '绑定时间',
    last_sync_at DATETIME DEFAULT NULL COMMENT '最后同步时间',
    
    INDEX idx_user_id (user_id),
    INDEX idx_device_id (device_id),
    INDEX idx_last_sync_at (last_sync_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kindle 设备信息表';

-- ============================================
-- 阅读日志文件表 (reading_logs) - 原样存储
-- ============================================
CREATE TABLE IF NOT EXISTS reading_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '日志 ID',
    user_id INT UNSIGNED NOT NULL COMMENT '关联用户 ID',
    device_id INT UNSIGNED NOT NULL COMMENT '关联设备 ID',
    file_path VARCHAR(500) NOT NULL COMMENT '文件存储路径',
    file_name VARCHAR(255) NOT NULL COMMENT '原始文件名',
    file_size BIGINT UNSIGNED NOT NULL COMMENT '文件大小（字节）',
    file_hash VARCHAR(64) NOT NULL COMMENT '文件 SHA256 哈希值',
    upload_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
    
    INDEX idx_user_id (user_id),
    INDEX idx_device_id (device_id),
    INDEX idx_upload_at (upload_at),
    INDEX idx_file_hash (file_hash),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES kindle_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='阅读日志文件表';

-- ============================================
-- OAuth 会话表 (oauth_sessions)
-- ============================================
CREATE TABLE IF NOT EXISTS oauth_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '会话 ID',
    session_token VARCHAR(64) NOT NULL UNIQUE COMMENT '会话令牌',
    device_id VARCHAR(100) NOT NULL COMMENT 'Kindle 设备 ID',
    state VARCHAR(64) NOT NULL COMMENT 'OAuth state 参数',
    status ENUM('pending', 'authorized', 'completed', 'expired') NOT NULL DEFAULT 'pending' COMMENT '会话状态',
    user_id INT UNSIGNED DEFAULT NULL COMMENT '授权后关联的用户 ID',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    expires_at DATETIME NOT NULL COMMENT '过期时间',
    completed_at DATETIME DEFAULT NULL COMMENT '完成时间',
    
    INDEX idx_session_token (session_token),
    INDEX idx_device_id (device_id),
    INDEX idx_state (state),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth 会话表';

-- ============================================
-- 系统配置表 (system_config)
-- ============================================
CREATE TABLE IF NOT EXISTS system_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '配置 ID',
    config_key VARCHAR(100) NOT NULL UNIQUE COMMENT '配置键',
    config_value TEXT COMMENT '配置值',
    description VARCHAR(500) DEFAULT NULL COMMENT '配置说明',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    
    INDEX idx_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- ============================================
-- 插入默认系统配置
-- ============================================
INSERT INTO system_config (config_key, config_value, description) VALUES
('max_file_size', '104857600', '最大上传文件大小（字节），默认 100MB'),
('allowed_file_extensions', 'log,txt', '允许上传的文件扩展名'),
('session_timeout', '300', 'OAuth 会话超时时间（秒），默认 5 分钟')
ON DUPLICATE KEY UPDATE config_value = VALUES(config_value);
```

---

## 更新后的 API 接口规范

### 认证接口

#### 1. 请求登录

**接口**: `POST /api/auth/request`

**请求参数**:
```json
{
  "device_id": "kindle-device-uuid-12345"
}
```

**响应**:
```json
{
  "success": true,
  "data": {
    "session_token": "550e8400-e29b-41d4-a716-446655440000",
    "auth_url": "https://github.com/login/oauth/authorize?client_id=xxx&redirect_uri=xxx&state=yyy",
    "expires_at": "2024-01-13T13:05:00Z"
  }
}
```

---

#### 2. 查询登录状态

**接口**: `GET /api/auth/status?session_token=xxx`

**响应 - pending**:
```json
{
  "success": true,
  "data": {
    "status": "pending"
  }
}
```

**响应 - authorized**:
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
    }
  }
}
```

---

### 文件上传接口

#### 上传日志文件

**接口**: `POST /api/upload`

**请求头**:
```
Authorization: Bearer {user_token}
Content-Type: multipart/form-data
```

**请求参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_id | string | 是 | 设备 UUID |
| files[] | file | 是 | 日志文件（支持多文件） |

**响应**:
```json
{
  "success": true,
  "data": {
    "uploaded_files": 5,
    "total_size": 1024000
  }
}
```

---

### 用户管理接口

#### 1. 获取用户信息

**接口**: `GET /api/user/profile`

**请求头**:
```
Authorization: Bearer {user_token}
```

**响应**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "username": "username",
    "avatar_url": "https://avatars.githubusercontent.com/u/12345678?v=4",
    "created_at": "2024-01-01T00:00:00Z",
    "last_login_at": "2024-01-13T12:00:00Z",
    "devices_count": 2
  }
}
```

---

#### 2. 获取设备列表

**接口**: `GET /api/user/devices`

**请求头**:
```
Authorization: Bearer {user_token}
```

**响应**:
```json
{
  "success": true,
  "data": {
    "devices": [
      {
        "id": 1,
        "device_id": "kindle-device-uuid-12345",
        "device_name": "My Kindle",
        "created_at": "2024-01-01T00:00:00Z",
        "last_sync_at": "2024-01-13T12:00:00Z"
      }
    ]
  }
}
```

---

#### 3. 更新设备名称

**接口**: `PUT /api/user/devices/{id}`

**请求头**:
```
Authorization: Bearer {user_token}
Content-Type: application/json
```

**请求参数**:
```json
{
  "device_name": "New Device Name"
}
```

---

#### 4. 解绑设备

**接口**: `DELETE /api/user/devices/{id}`

**请求头**:
```
Authorization: Bearer {user_token}
```

---

#### 5. 获取日志文件列表

**接口**: `GET /api/user/logs`

**请求头**:
```
Authorization: Bearer {user_token}
```

**请求参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_id | string | 否 | 设备 UUID（筛选） |
| page | integer | 否 | 页码，默认 1 |
| limit | integer | 否 | 每页数量，默认 20 |

**响应**:
```json
{
  "success": true,
  "data": {
    "logs": [
      {
        "id": 1,
        "device_id": "kindle-device-uuid-12345",
        "device_name": "My Kindle",
        "file_name": "appmgrd.log",
        "file_size": 204800,
        "upload_at": "2024-01-13T12:00:00Z"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 100,
      "total_pages": 5
    }
  }
}
```

---

#### 6. 下载日志文件

**接口**: `GET /api/user/logs/{id}/download`

**请求头**:
```
Authorization: Bearer {user_token}
```

**响应**: 文件流

---

## 用户管理界面设计

### 页面结构

```
public/
├── index.php              # 用户管理界面入口
├── assets/
│   ├── css/
│   │   └── dashboard.css  # 界面样式
│   └── js/
│       └── dashboard.js   # 界面交互
```

### 界面功能

1. **用户信息展示**
   - 显示用户名、头像
   - 显示注册时间、最后登录时间
   - 显示绑定的设备数量

2. **设备管理**
   - 设备列表展示
   - 设备名称编辑
   - 设备解绑
   - 显示设备最后同步时间

3. **日志文件管理**
   - 日志文件列表（分页）
   - 按设备筛选
   - 日志文件下载
   - 显示文件大小、上传时间

4. **数据统计**
   - 总文件数
   - 总存储大小
   - 设备数量

### 界面布局

```
+--------------------------------------------------+
|  Kindle Reading GTK - 用户管理                   |
+--------------------------------------------------+
|  [用户头像] 用户名                               |
|  注册时间: 2024-01-01  最后登录: 2024-01-13     |
+--------------------------------------------------+
|  统计信息: 2 设备 | 100 文件 | 50 MB           |
+--------------------------------------------------+
|  设备管理                                         |
|  +--------------------------------------------+  |
|  | 设备名称        | 最后同步      | 操作    |  |
|  | My Kindle       | 2024-01-13    | 编辑 解绑|  |
|  | Kindle Paperwhite | 2024-01-12  | 编辑 解绑|  |
|  +--------------------------------------------+  |
+--------------------------------------------------+
|  日志文件                                         |
|  筛选: [所有设备 ▼]                               |
|  +--------------------------------------------+  |
|  | 文件名          | 设备        | 大小 | 操作|  |
|  | appmgrd.log     | My Kindle   | 2MB  | 下载|  |
|  | appmgrd.log     | Kindle PW   | 1MB  | 下载|  |
|  +--------------------------------------------+  |
|  [上一页] 1 / 5 [下一页]                         |
+--------------------------------------------------+
```

---

## 日志格式说明

### Kindle 日志格式

```
appmgrd[2530]: metric_generic,1767366804,timer,appmgrd,logAppActiveDuration,com.lab126.booklet.reader.activeDuration,4236321
```

**格式解析**:
- `appmgrd[2530]`: 进程名和 PID
- `metric_generic`: 指标类型
- `1767366804`: Unix 时间戳
- `timer`: 指标类别
- `appmgrd`: 来源
- `logAppActiveDuration`: 指标名称
- `com.lab126.booklet.reader.activeDuration`: 应用标识
- `4236321`: 阅读时长（毫秒）

**存储方式**: 原样存储，不解析

---

## 环境变量配置

### .env.example

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

# GitHub OAuth 配置（预留字段）
GITHUB_CLIENT_ID=your_github_client_id
GITHUB_CLIENT_SECRET=your_github_client_secret
GITHUB_REDIRECT_URI=https://your-domain.com/api/callback.php

# 文件上传配置
MAX_FILE_SIZE=104857600
ALLOWED_EXTENSIONS=log,txt
UPLOAD_PATH=public/uploads

# 会话配置
SESSION_TIMEOUT=300
USER_TOKEN_LIFETIME=7200

# 日志配置
LOG_LEVEL=info
LOG_PATH=storage/logs
```

---

## 更新后的实施要点

### 简化的功能

1. **日志存储**: 原样存储，不需要解析
2. **数据库**: 5 张表（移除 reading_records、user_stats）
3. **API 接口**: 简化为认证、上传、用户管理
4. **文件类型**: 只允许 .log 和 .txt 文件

### 新增功能

1. **用户管理界面**: Web 界面管理设备和日志
2. **日志下载**: 支持下载原始日志文件
3. **设备管理**: 支持重命名和解绑设备

### 保持不变

1. **GitHub OAuth 认证流程**
2. **Kindle 轮询机制**
3. **文件上传安全验证**
4. **多设备支持**

---

## 总结

根据用户反馈，设计已更新为：

1. ✅ 简化数据库结构（5 张表）
2. ✅ 日志原样存储，不解析
3. ✅ 添加用户管理界面设计
4. ✅ 更新 API 接口规范
5. ✅ 支持多设备绑定和记录合并
6. ✅ 预留 GitHub OAuth 配置字段

所有设计文档已更新完成，可以开始实现代码。