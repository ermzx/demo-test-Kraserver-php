# Kindle Reading GTK 云同步服务端 - 项目目录结构

## 概述

本文档定义了 Kindle Reading GTK 云同步服务端的项目目录结构设计。

## 目录结构

```
kindle-reading-php/
├── .env                          # 环境变量配置文件（不提交到版本控制）
├── .env.example                  # 环境变量配置示例
├── .gitignore                    # Git 忽略文件配置
├── LICENSE                       # 许可证文件
├── readme.md                     # 项目说明文档
│
├── config/                       # 配置文件目录
│   ├── config.php                # 主配置文件
│   ├── database.php              # 数据库配置
│   └── oauth.php                 # OAuth 配置
│
├── src/                          # 源代码目录
│   ├── Core/                     # 核心类
│   │   ├── Database.php          # 数据库连接类
│   │   ├── Config.php            # 配置管理类
│   │   ├── Logger.php            # 日志记录类
│   │   └── Response.php          # API 响应类
│   │
│   ├── Auth/                     # 认证相关
│   │   ├── OAuth.php             # OAuth 2.0 处理类
│   │   ├── Session.php           # 会话管理类
│   │   └── Token.php             # Token 管理类
│   │
│   ├── Models/                   # 数据模型
│   │   ├── User.php              # 用户模型
│   │   ├── Device.php            # 设备模型
│   │   ├── ReadingLog.php        # 阅读日志模型
│   │   ├── ReadingRecord.php     # 阅读记录模型
│   │   ├── UserStats.php         # 用户统计模型
│   │   └── OAuthSession.php      # OAuth 会话模型
│   │
│   ├── Services/                 # 业务服务层
│   │   ├── AuthService.php       # 认证服务
│   │   ├── DeviceService.php     # 设备管理服务
│   │   ├── UploadService.php     # 文件上传服务
│   │   ├── LogParser.php         # 日志解析服务
│   │   ├── StatsService.php      # 统计服务
│   │   └── CleanupService.php    # 数据清理服务
│   │
│   ├── Middleware/               # 中间件
│   │   ├── AuthMiddleware.php    # 认证中间件
│   │   ├── RateLimitMiddleware.php # 限流中间件
│   │   └── CorsMiddleware.php    # CORS 中间件
│   │
│   └── Utils/                    # 工具类
│       ├── FileHelper.php        # 文件操作工具
│       ├── SecurityHelper.php    # 安全工具
│       ├── Validator.php         # 数据验证工具
│       └── DateHelper.php        # 日期处理工具
│
├── api/                          # API 接口目录
│   ├── index.php                 # API 入口文件
│   ├── auth.php                  # 认证相关接口
│   ├── device.php                # 设备管理接口
│   ├── upload.php                # 文件上传接口
│   ├── stats.php                 # 统计数据接口
│   └── callback.php              # OAuth 回调接口
│
├── public/                       # Web 根目录
│   ├── index.php                 # 前端入口
│   ├── assets/                   # 静态资源
│   │   ├── css/
│   │   │   └── style.css
│   │   ├── js/
│   │   │   ├── app.js
│   │   │   └── oauth.js
│   │   └── images/
│   └── uploads/                  # 上传文件存储目录（需配置 Web 服务器）
│       └── .gitkeep
│
├── storage/                      # 存储目录（Web 根目录之外）
│   ├── logs/                     # 日志文件
│   │   ├── app.log
│   │   ├── error.log
│   │   └── access.log
│   ├── cache/                    # 缓存文件
│   │   └── .gitkeep
│   └── sessions/                 # 会话文件
│       └── .gitkeep
│
├── database/                     # 数据库相关
│   ├── schema.sql                # 数据库结构
│   ├── migrations/               # 数据库迁移文件
│   │   └── .gitkeep
│   └── seeds/                    # 数据填充文件
│       └── .gitkeep
│
├── tests/                        # 测试目录
│   ├── unit/                     # 单元测试
│   │   └── .gitkeep
│   └── integration/              # 集成测试
│       └── .gitkeep
│
├── scripts/                      # 脚本目录
│   ├── setup.php                 # 安装脚本
│   ├── migrate.php               # 数据库迁移脚本
│   └── cleanup.php               # 数据清理脚本
│
├── nginx/                        # Nginx 配置
│   └── kindle-reading.conf       # Nginx 配置文件
│
├── plans/                        # 计划文档目录
│   ├── database-design.md        # 数据库设计文档
│   ├── project-structure.md      # 项目结构文档（本文件）
│   ├── oauth-flow.md             # OAuth 流程设计
│   └── api-spec.md               # API 接口规范
│
└── docs/                         # 文档目录
    ├── deployment.md             # 部署文档
    ├── api.md                    # API 文档
    └── troubleshooting.md        # 故障排查文档
```

## 目录说明

### 根目录文件

| 文件 | 说明 |
|------|------|
| `.env` | 环境变量配置文件，包含数据库连接、OAuth 凭据等敏感信息 |
| `.env.example` | 环境变量配置示例，用于复制创建 `.env` 文件 |
| `.gitignore` | Git 忽略文件配置，排除敏感文件和临时文件 |
| `LICENSE` | 项目许可证 |
| `readme.md` | 项目说明文档 |

### config/ - 配置文件目录

| 文件 | 说明 |
|------|------|
| `config.php` | 主配置文件，加载所有配置 |
| `database.php` | 数据库连接配置 |
| `oauth.php` | OAuth 2.0 相关配置 |

### src/ - 源代码目录

#### src/Core/ - 核心类

| 文件 | 说明 |
|------|------|
| `Database.php` | 数据库连接类，使用 PDO |
| `Config.php` | 配置管理类，支持环境变量覆盖 |
| `Logger.php` | 日志记录类，支持不同级别日志 |
| `Response.php` | API 响应类，统一 JSON 响应格式 |

#### src/Auth/ - 认证相关

| 文件 | 说明 |
|------|------|
| `OAuth.php` | OAuth 2.0 处理类，处理 GitHub OAuth 流程 |
| `Session.php` | 会话管理类，管理用户会话 |
| `Token.php` | Token 管理类，处理 Access Token 和 Refresh Token |

#### src/Models/ - 数据模型

| 文件 | 说明 |
|------|------|
| `User.php` | 用户模型，封装用户相关操作 |
| `Device.php` | 设备模型，封装 Kindle 设备相关操作 |
| `ReadingLog.php` | 阅读日志模型，封装日志文件相关操作 |
| `ReadingRecord.php` | 阅读记录模型，封装阅读记录相关操作 |
| `UserStats.php` | 用户统计模型，封装统计数据相关操作 |
| `OAuthSession.php` | OAuth 会话模型，封装 OAuth 会话相关操作 |

#### src/Services/ - 业务服务层

| 文件 | 说明 |
|------|------|
| `AuthService.php` | 认证服务，处理用户登录、注册等 |
| `DeviceService.php` | 设备管理服务，处理设备绑定、解绑等 |
| `UploadService.php` | 文件上传服务，处理文件上传、验证、存储 |
| `LogParser.php` | 日志解析服务，解析阅读日志文件 |
| `StatsService.php` | 统计服务，计算和更新用户统计数据 |
| `CleanupService.php` | 数据清理服务，清理过期数据 |

#### src/Middleware/ - 中间件

| 文件 | 说明 |
|------|------|
| `AuthMiddleware.php` | 认证中间件，验证用户身份 |
| `RateLimitMiddleware.php` | 限流中间件，防止 API 滥用 |
| `CorsMiddleware.php` | CORS 中间件，处理跨域请求 |

#### src/Utils/ - 工具类

| 文件 | 说明 |
|------|------|
| `FileHelper.php` | 文件操作工具，提供文件上传、删除等功能 |
| `SecurityHelper.php` | 安全工具，提供加密、哈希、XSS 防护等功能 |
| `Validator.php` | 数据验证工具，验证输入数据 |
| `DateHelper.php` | 日期处理工具，提供日期格式化、计算等功能 |

### api/ - API 接口目录

| 文件 | 说明 |
|------|------|
| `index.php` | API 入口文件，路由分发 |
| `auth.php` | 认证相关接口（登录、登出、刷新 Token） |
| `device.php` | 设备管理接口（绑定、解绑、查询设备） |
| `upload.php` | 文件上传接口（上传日志文件） |
| `stats.php` | 统计数据接口（获取用户统计、排行榜） |
| `callback.php` | OAuth 回调接口（处理 GitHub 回调） |

### public/ - Web 根目录

| 文件/目录 | 说明 |
|-----------|------|
| `index.php` | 前端入口文件 |
| `assets/` | 静态资源目录 |
| `assets/css/` | CSS 样式文件 |
| `assets/js/` | JavaScript 文件 |
| `assets/images/` | 图片资源 |
| `uploads/` | 上传文件存储目录（需配置 Web 服务器） |

### storage/ - 存储目录

| 目录 | 说明 |
|------|------|
| `logs/` | 日志文件目录 |
| `cache/` | 缓存文件目录 |
| `sessions/` | 会话文件目录 |

### database/ - 数据库相关

| 文件/目录 | 说明 |
|-----------|------|
| `schema.sql` | 数据库结构文件 |
| `migrations/` | 数据库迁移文件目录 |
| `seeds/` | 数据填充文件目录 |

### tests/ - 测试目录

| 目录 | 说明 |
|------|------|
| `unit/` | 单元测试目录 |
| `integration/` | 集成测试目录 |

### scripts/ - 脚本目录

| 文件 | 说明 |
|------|------|
| `setup.php` | 安装脚本，初始化项目 |
| `migrate.php` | 数据库迁移脚本 |
| `cleanup.php` | 数据清理脚本 |

### nginx/ - Nginx 配置

| 文件 | 说明 |
|------|------|
| `kindle-reading.conf` | Nginx 配置文件 |

### plans/ - 计划文档目录

| 文件 | 说明 |
|------|------|
| `database-design.md` | 数据库设计文档 |
| `project-structure.md` | 项目结构文档（本文件） |
| `oauth-flow.md` | OAuth 流程设计 |
| `api-spec.md` | API 接口规范 |

### docs/ - 文档目录

| 文件 | 说明 |
|------|------|
| `deployment.md` | 部署文档 |
| `api.md` | API 文档 |
| `troubleshooting.md` | 故障排查文档 |

## 安全考虑

### 目录权限

| 目录 | 权限 | 说明 |
|------|------|------|
| `storage/` | 755 | 存储目录，Web 服务器可读写 |
| `storage/logs/` | 755 | 日志目录，Web 服务器可写 |
| `storage/cache/` | 755 | 缓存目录，Web 服务器可写 |
| `storage/sessions/` | 755 | 会话目录，Web 服务器可写 |
| `public/uploads/` | 755 | 上传目录，Web 服务器可写 |
| `.env` | 600 | 环境变量文件，仅所有者可读写 |

### Web 服务器配置

1. **Web 根目录**: 设置为 `public/` 目录
2. **禁止访问**: 禁止访问 `storage/`、`config/`、`src/` 等目录
3. **上传目录**: `public/uploads/` 目录禁止执行 PHP 文件
4. **HTTPS**: 生产环境必须使用 HTTPS

### 文件上传安全

1. **存储位置**: 上传文件存储在 `public/uploads/` 目录
2. **文件验证**: 验证文件类型、大小、扩展名
3. **文件重命名**: 使用 UUID 重命名上传文件
4. **执行权限**: 上传目录禁止执行 PHP 文件

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
SESSION_LIFETIME=7200

# 日志配置
LOG_LEVEL=info
LOG_PATH=storage/logs

# 数据保留配置
DATA_RETENTION_DAYS=365
```

## 自动加载配置

### composer.json

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
        "ext-openssl": "*"
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

## 部署流程

1. 克隆代码仓库
2. 复制 `.env.example` 为 `.env` 并配置
3. 运行 `composer install` 安装依赖
4. 运行 `php scripts/setup.php` 初始化项目
5. 导入数据库结构 `mysql -u user -p < database/schema.sql`
6. 配置 Web 服务器（Nginx/Apache）
7. 设置目录权限
8. 访问应用

## 开发流程

1. 创建功能分支
2. 编写代码
3. 编写测试
4. 运行测试
5. 提交代码
6. 创建 Pull Request
7. 代码审查
8. 合并到主分支