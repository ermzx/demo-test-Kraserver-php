# Kindle Reading GTK 云同步服务端

基于 PHP 8 和 GitHub OAuth 的 Kindle 阅读日志云同步服务端 Demo。

## 项目简介

Kindle Reading GTK 云同步服务端是一个用于同步 Kindle 阅读日志的云服务。用户可以通过 GitHub OAuth 认证登录，将 Kindle 设备上的阅读日志上传到云端，并通过 Web 界面管理设备和日志文件。

## 主要特性

- ✅ **GitHub OAuth 认证**：使用 GitHub 账号登录，安全便捷
- ✅ **多设备支持**：一个账号可绑定多个 Kindle 设备
- ✅ **日志上传**：支持上传 Kindle 阅读日志文件（.log, .txt）
- ✅ **用户管理界面**：Web 界面管理设备和日志
- ✅ **设备管理**：支持设备重命名和解绑
- ✅ **日志下载**：支持下载原始日志文件
- ✅ **安全验证**：文件类型、大小、哈希验证

## 技术栈

- **后端**：PHP 8.0+
- **数据库**：MySQL 5.7+ / MariaDB 10.2+
- **Web 服务器**：Nginx / Apache
- **认证**：GitHub OAuth 2.0
- **依赖管理**：Composer

## 项目结构

```
kindle-reading-php/
├── config/               # 配置文件目录
│   ├── config.php        # 主配置文件
│   ├── database.php      # 数据库配置
│   └── oauth.php         # OAuth 配置
├── src/                  # 源代码目录
│   ├── Core/             # 核心类
│   ├── Auth/             # 认证相关
│   ├── Models/           # 数据模型
│   ├── Services/         # 业务服务层
│   └── Utils/            # 工具类
├── api/                  # API 接口目录
├── public/               # Web 根目录
│   ├── assets/           # 静态资源
│   │   ├── css/
│   │   └── js/
│   └── uploads/          # 上传文件存储目录
├── storage/              # 存储目录
│   ├── logs/             # 日志文件
│   ├── cache/            # 缓存文件
│   └── sessions/         # 会话文件
├── database/             # 数据库相关
│   └── schema.sql        # 数据库结构
 ├── scripts/              # 脚本目录
 ├── nginx/                # Nginx 配置
 ├── tests/                # 测试目录
 ├── composer.json         # Composer 配置
 ├── .env.example          # 环境变量配置示例
 ├── QUICKSTART.md         # 快速开始指南
 ├── TESTING.md            # 测试文档
 ├── .gitignore            # Git 忽略文件
 └── readme.md             # 项目说明文档
```

## 数据库设计

项目使用 5 张表：

1. **users** - GitHub 用户信息表
2. **kindle_devices** - Kindle 设备信息表
3. **reading_logs** - 阅读日志文件表
4. **oauth_sessions** - OAuth 会话表
5. **system_config** - 系统配置表

详细数据库结构请参考 [`database/schema.sql`](database/schema.sql)

## 安装步骤

### 1. 环境要求

- PHP 8.0 或更高版本
- MySQL 5.7+ 或 MariaDB 10.2+
- Nginx 或 Apache
- Composer

### 2. 克隆项目

```bash
git clone https://github.com/your-username/kindle-reading-php.git
cd kindle-reading-php
```

### 3. 安装依赖

```bash
composer install
```

### 4. 配置环境变量

复制 `.env.example` 为 `.env` 并修改配置：

```bash
cp .env.example .env
```

编辑 `.env` 文件，配置以下关键参数：

```env
# 数据库配置
DB_HOST=localhost
DB_PORT=3306
DB_NAME=kindle_reading
DB_USER=your_db_user
DB_PASS=your_db_password

# GitHub OAuth 配置
GITHUB_CLIENT_ID=your_github_client_id
GITHUB_CLIENT_SECRET=your_github_client_secret
GITHUB_REDIRECT_URI=https://your-domain.com/api/callback.php

# 应用配置
APP_URL=https://your-domain.com
```

### 5. 创建数据库

```bash
mysql -u root -p < database/schema.sql
```

### 6. 配置 Web 服务器

#### Nginx 配置示例

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/kindle-reading-php/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 7. 设置目录权限

```bash
chmod -R 755 storage/
chmod -R 755 public/uploads/
```

### 8. 配置 GitHub OAuth 应用

1. 访问 [GitHub Developer Settings](https://github.com/settings/developers)
2. 点击 "New OAuth App"
3. 填写应用信息：
   - Application name: Kindle Reading GTK
   - Homepage URL: https://your-domain.com
   - Authorization callback URL: https://your-domain.com/api/callback.php
4. 获取 Client ID 和 Client Secret
5. 将其填入 `.env` 文件

## API 接口

### 认证接口

#### 请求登录
```
POST /api/auth/request
```

#### 查询登录状态
```
GET /api/auth/status?session_token=xxx
```

### 文件上传接口

#### 上传日志文件
```
POST /api/upload
```

### 用户管理接口

#### 获取用户信息
```
GET /api/user/profile
```

#### 获取设备列表
```
GET /api/user/devices
```

#### 更新设备名称
```
PUT /api/user/devices/{id}
```

#### 解绑设备
```
DELETE /api/user/devices/{id}
```

#### 获取日志文件列表
```
GET /api/user/logs
```

#### 下载日志文件
```
GET /api/user/logs/{id}/download
```

## 开发指南

### 代码规范

项目遵循 PSR-12 编码规范。

### 运行测试

```bash
composer test
```

### 代码检查

```bash
composer cs-check
```

### 代码格式化

```bash
composer cs-fix
```

## 安全建议

1. **HTTPS**：生产环境必须使用 HTTPS
2. **环境变量**：不要将 `.env` 文件提交到版本控制
3. **数据库密码**：使用强密码
4. **文件上传**：限制文件类型和大小
5. **会话管理**：定期清理过期会话

## 日志格式

Kindle 日志格式示例：

```
appmgrd[2530]: metric_generic,1767366804,timer,appmgrd,logAppActiveDuration,com.lab126.booklet.reader.activeDuration,4236321
```

日志文件原样存储，不进行解析。

## 故障排除

### 数据库连接失败

检查 `.env` 文件中的数据库配置是否正确。

### OAuth 回调失败

确保 GitHub OAuth 应用的回调 URL 配置正确。

### 文件上传失败

检查 `public/uploads` 目录权限是否正确。
