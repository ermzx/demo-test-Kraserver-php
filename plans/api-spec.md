# Kindle Reading GTK 云同步服务端 - API 接口规范

## 概述

本文档定义了 Kindle Reading GTK 云同步服务端的 RESTful API 接口规范。

## 基础信息

### Base URL

```
生产环境: https://your-domain.com/api
开发环境: http://localhost:8000/api
```

### 通用响应格式

#### 成功响应

```json
{
  "success": true,
  "data": {
    // 响应数据
  },
  "message": "操作成功（可选）"
}
```

#### 错误响应

```json
{
  "success": false,
  "error": {
    "code": 1001,
    "message": "错误描述",
    "details": {
      // 详细错误信息（可选）
    }
  }
}
```

### HTTP 状态码

| 状态码 | 说明 |
|--------|------|
| 200 | 请求成功 |
| 201 | 创建成功 |
| 400 | 请求参数错误 |
| 401 | 未授权（Token 无效或过期） |
| 403 | 禁止访问（权限不足） |
| 404 | 资源不存在 |
| 429 | 请求频率超限 |
| 500 | 服务器内部错误 |

### 请求头

| 请求头 | 说明 | 必填 |
|--------|------|------|
| Content-Type | 请求内容类型 | 是（POST/PUT） |
| Authorization | Bearer Token | 是（需要认证的接口） |
| User-Agent | 用户代理 | 否 |

### 认证方式

使用 Bearer Token 认证：

```
Authorization: Bearer {user_token}
```

---

## 认证接口

### 1. 请求登录

创建 OAuth 登录会话，返回二维码 URL。

**接口**: `POST /auth/request`

**请求参数**:
```json
{
  "device_id": "kindle-device-uuid-12345"
}
```

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_id | string | 是 | Kindle 设备 UUID |

**响应**:
```json
{
  "success": true,
  "data": {
    "session_token": "550e8400-e29b-41d4-a716-446655440000",
    "qr_code_url": "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=...",
    "expires_at": "2024-01-13T13:05:00Z"
  }
}
```

**错误码**:
- 1001: 参数缺失或无效
- 1002: 设备 ID 格式错误
- 1010: 服务器内部错误

---

### 2. 查询登录状态

轮询查询 OAuth 登录状态。

**接口**: `GET /auth/status`

**请求参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| session_token | string | 是 | 会话令牌 |

**响应 - pending**:
```json
{
  "success": true,
  "data": {
    "status": "pending",
    "message": "等待用户授权..."
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
    },
    "device_id": 1
  }
}
```

**响应 - expired**:
```json
{
  "success": false,
  "error": {
    "code": 1004,
    "message": "会话已过期，请重新登录"
  }
}
```

**错误码**:
- 1001: 参数缺失或无效
- 1003: 会话不存在
- 1004: 会话已过期

---

### 3. OAuth 回调

处理 GitHub OAuth 回调（由 GitHub 调用）。

**接口**: `GET /callback.php`

**请求参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| code | string | 是 | GitHub 授权码 |
| state | string | 是 | OAuth state 参数 |

**响应**: HTML 页面（授权成功/失败）

---

### 4. 刷新令牌

刷新用户访问令牌。

**接口**: `POST /auth/refresh`

**请求头**:
```
Authorization: Bearer {user_token}
```

**响应**:
```json
{
  "success": true,
  "data": {
    "user_token": "new_user_token_xyz789",
    "expires_at": "2024-01-13T15:00:00Z"
  }
}
```

**错误码**:
- 1008: Token 无效或过期

---

### 5. 登出

登出当前用户。

**接口**: `POST /auth/logout`

**请求头**:
```
Authorization: Bearer {user_token}
```

**响应**:
```json
{
  "success": true,
  "message": "登出成功"
}
```

**错误码**:
- 1008: Token 无效或过期

---

## 设备管理接口

### 1. 获取设备列表

获取当前用户的所有设备。

**接口**: `GET /device/list`

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
        "device_type": "Kindle Paperwhite 5",
        "firmware_version": "5.16.2",
        "last_sync_at": "2024-01-13T12:00:00Z",
        "created_at": "2024-01-01T00:00:00Z",
        "is_active": true
      }
    ],
    "total": 1
  }
}
```

---

### 2. 获取设备详情

获取指定设备的详细信息。

**接口**: `GET /device/{id}`

**请求头**:
```
Authorization: Bearer {user_token}
```

**路径参数**:
| 参数 | 类型 | 说明 |
|------|------|------|
| id | integer | 设备 ID |

**响应**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "device_id": "kindle-device-uuid-12345",
    "device_name": "My Kindle",
    "device_type": "Kindle Paperwhite 5",
    "firmware_version": "5.16.2",
    "last_sync_at": "2024-01-13T12:00:00Z",
    "created_at": "2024-01-01T00:00:00Z",
    "is_active": true,
    "stats": {
      "total_logs": 100,
      "total_reading_time": 360000,
      "last_log_date": "2024-01-13"
    }
  }
}
```

**错误码**:
- 1001: 参数缺失或无效
- 1008: Token 无效或过期
- 1011: 设备不存在

---

### 3. 更新设备信息

更新设备名称等信息。

**接口**: `PUT /device/{id}`

**请求头**:
```
Authorization: Bearer {user_token}
Content-Type: application/json
```

**路径参数**:
| 参数 | 类型 | 说明 |
|------|------|------|
| id | integer | 设备 ID |

**请求参数**:
```json
{
  "device_name": "My New Kindle Name"
}
```

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_name | string | 否 | 设备名称 |

**响应**:
```json
{
  "success": true,
  "message": "设备信息更新成功"
}
```

**错误码**:
- 1001: 参数缺失或无效
- 1008: Token 无效或过期
- 1011: 设备不存在

---

### 4. 解绑设备

解绑指定设备。

**接口**: `DELETE /device/{id}`

**请求头**:
```
Authorization: Bearer {user_token}
```

**路径参数**:
| 参数 | 类型 | 说明 |
|------|------|------|
| id | integer | 设备 ID |

**响应**:
```json
{
  "success": true,
  "message": "设备解绑成功"
}
```

**错误码**:
- 1001: 参数缺失或无效
- 1008: Token 无效或过期
- 1011: 设备不存在

---

## 文件上传接口

### 1. 上传日志文件

上传 Kindle 阅读日志文件。

**接口**: `POST /upload`

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
    "total_size": 1024000,
    "files": [
      {
        "id": 1,
        "file_name": "metrics_reader_20240113.json",
        "file_size": 204800,
        "file_hash": "a1b2c3d4e5f6...",
        "log_date": "2024-01-13"
      }
    ],
    "message": "文件上传成功"
  }
}
```

**错误码**:
- 1001: 参数缺失或无效
- 1008: Token 无效或过期
- 1012: 文件类型不允许
- 1013: 文件大小超限
- 1014: 设备不存在
- 1015: 文件上传失败

---

### 2. 获取上传记录

获取文件上传记录列表。

**接口**: `GET /upload/logs`

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
        "file_name": "metrics_reader_20240113.json",
        "file_size": 204800,
        "log_date": "2024-01-13",
        "upload_at": "2024-01-13T12:00:00Z",
        "is_parsed": true,
        "parsed_at": "2024-01-13T12:01:00Z"
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

## 统计数据接口

### 1. 获取用户统计

获取当前用户的阅读统计数据。

**接口**: `GET /stats/user`

**请求头**:
```
Authorization: Bearer {user_token}
```

**请求参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| start_date | string | 否 | 开始日期（YYYY-MM-DD） |
| end_date | string | 否 | 结束日期（YYYY-MM-DD） |

**响应**:
```json
{
  "success": true,
  "data": {
    "total_reading_time": 360000,
    "total_books": 25,
    "total_days": 30,
    "longest_streak": 7,
    "current_streak": 3,
    "last_reading_date": "2024-01-13",
    "daily_stats": [
      {
        "date": "2024-01-13",
        "reading_time": 7200,
        "books_count": 2
      }
    ],
    "hourly_distribution": [
      {
        "hour": 8,
        "reading_time": 3600
      },
      {
        "hour": 20,
        "reading_time": 3600
      }
    ],
    "top_books": [
      {
        "title": "Book Title",
        "author": "Author Name",
        "reading_time": 180000
      }
    ]
  }
}
```

---

### 2. 获取排行榜

获取阅读时长排行榜。

**接口**: `GET /stats/leaderboard`

**请求头**:
```
Authorization: Bearer {user_token}
```

**请求参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| period | string | 否 | 时间周期：week/month/all，默认 all |
| limit | integer | 否 | 返回数量，默认 10 |

**响应**:
```json
{
  "success": true,
  "data": {
    "leaderboard": [
      {
        "rank": 1,
        "user_id": 1,
        "username": "username",
        "avatar_url": "https://avatars.githubusercontent.com/u/12345678?v=4",
        "total_reading_time": 720000,
        "total_days": 45,
        "longest_streak": 15
      }
    ],
    "current_user": {
      "rank": 5,
      "total_reading_time": 360000,
      "total_days": 30
    }
  }
}
```

---

### 3. 获取设备统计

获取指定设备的统计数据。

**接口**: `GET /stats/device/{id}`

**请求头**:
```
Authorization: Bearer {user_token}
```

**路径参数**:
| 参数 | 类型 | 说明 |
|------|------|------|
| id | integer | 设备 ID |

**请求参数**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| start_date | string | 否 | 开始日期（YYYY-MM-DD） |
| end_date | string | 否 | 结束日期（YYYY-MM-DD） |

**响应**:
```json
{
  "success": true,
  "data": {
    "device_id": 1,
    "device_name": "My Kindle",
    "total_reading_time": 360000,
    "total_logs": 100,
    "first_log_date": "2024-01-01",
    "last_log_date": "2024-01-13",
    "daily_stats": [
      {
        "date": "2024-01-13",
        "reading_time": 7200
      }
    ]
  }
}
```

**错误码**:
- 1001: 参数缺失或无效
- 1008: Token 无效或过期
- 1011: 设备不存在

---

## 用户信息接口

### 1. 获取用户信息

获取当前用户的信息。

**接口**: `GET /user/profile`

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
    "devices_count": 1
  }
}
```

---

### 2. 更新用户信息

更新用户信息（预留接口）。

**接口**: `PUT /user/profile`

**请求头**:
```
Authorization: Bearer {user_token}
Content-Type: application/json
```

**请求参数**:
```json
{
  "device_name": "My Kindle"
}
```

**响应**:
```json
{
  "success": true,
  "message": "用户信息更新成功"
}
```

---

## 系统接口

### 1. 健康检查

检查服务健康状态。

**接口**: `GET /health`

**响应**:
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "timestamp": "2024-01-13T12:00:00Z",
    "version": "1.0.0"
  }
}
```

---

### 2. 获取系统配置

获取公开的系统配置。

**接口**: `GET /config`

**响应**:
```json
{
  "success": true,
  "data": {
    "max_file_size": 104857600,
    "allowed_extensions": ["json", "gz", "txt", "log"],
    "session_timeout": 300,
    "version": "1.0.0"
  }
}
```

---

## 错误码定义

| 错误码 | 说明 | HTTP 状态码 |
|--------|------|-------------|
| 1001 | 参数缺失或无效 | 400 |
| 1002 | 设备 ID 格式错误 | 400 |
| 1003 | 会话不存在 | 404 |
| 1004 | 会话已过期 | 400 |
| 1005 | OAuth 授权失败 | 400 |
| 1006 | 用户创建失败 | 500 |
| 1007 | 设备绑定失败 | 500 |
| 1008 | Token 无效或过期 | 401 |
| 1009 | 请求频率超限 | 429 |
| 1010 | 服务器内部错误 | 500 |
| 1011 | 设备不存在 | 404 |
| 1012 | 文件类型不允许 | 400 |
| 1013 | 文件大小超限 | 400 |
| 1014 | 设备不存在 | 404 |
| 1015 | 文件上传失败 | 500 |

---

## 限流规则

| 接口类型 | 限制 | 时间窗口 |
|----------|------|----------|
| 登录请求 | 10 次 | 1 分钟 |
| 轮询请求 | 1 次 | 1 秒 |
| 文件上传 | 5 次 | 1 分钟 |
| 其他接口 | 60 次 | 1 分钟 |

**限流响应**:
```json
{
  "success": false,
  "error": {
    "code": 1009,
    "message": "请求频率超限",
    "details": {
      "retry_after": 30
    }
  }
}
```

---

## 数据验证规则

### device_id

- 格式: UUID v4
- 示例: `550e8400-e29b-41d4-a716-446655440000`
- 正则: `/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i`

### 日期格式

- 格式: YYYY-MM-DD
- 示例: `2024-01-13`
- 正则: `/^\d{4}-\d{2}-\d{2}$/`

### 时间格式

- 格式: ISO 8601
- 示例: `2024-01-13T12:00:00Z`

### 文件类型

- 允许的扩展名: `json`, `gz`, `txt`, `log`
- 最大文件大小: 100MB（可配置）

---

## 示例代码

### PHP cURL 示例

```php
<?php
// 请求登录
$ch = curl_init('https://your-domain.com/api/auth/request');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'device_id' => '550e8400-e29b-41d4-a716-446655440000'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
print_r($data);
?>
```

### JavaScript fetch 示例

```javascript
// 请求登录
fetch('https://your-domain.com/api/auth/request', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        device_id: '550e8400-e29b-41d4-a716-446655440000'
    })
})
.then(response => response.json())
.then(data => console.log(data));

// 查询登录状态
fetch('https://your-domain.com/api/auth/status?session_token=550e8400-e29b-41d4-a716-446655440000')
.then(response => response.json())
.then(data => console.log(data));

// 上传文件
const formData = new FormData();
formData.append('device_id', '550e8400-e29b-41d4-a716-446655440000');
formData.append('files[]', fileInput.files[0]);

fetch('https://your-domain.com/api/upload', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer user_access_token_abc123'
    },
    body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

---

## 版本控制

当前 API 版本: `v1`

未来版本将通过 URL 路径进行区分：
- `https://your-domain.com/api/v1/...`
- `https://your-domain.com/api/v2/...`

---

## 更新日志

### v1.0.0 (2024-01-13)

- 初始版本发布
- 实现基础认证接口
- 实现设备管理接口
- 实现文件上传接口
- 实现统计数据接口