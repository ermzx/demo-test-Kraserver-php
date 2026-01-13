# Kindle Reading GTK 云同步服务端 - 快速部署指南

本指南帮助您在 5 分钟内完成服务端部署。

## 前置要求

- **PHP 8.0+** 及扩展：`pdo`, `json`, `mbstring`, `openssl`, `curl`
- **MySQL 5.7+** 或 **MariaDB 10.2+**
- **Nginx** 或 **Apache**
- **Composer**（PHP 依赖管理工具）
- **域名**（用于 HTTPS 和 OAuth 回调）

## 5 分钟快速部署

### 1. 克隆项目

```bash
git clone https://github.com/your-username/kindle-reading-php.git
cd kindle-reading-php
```

### 2. 安装依赖

```bash
composer install --no-dev --optimize-autoloader
```

### 3. 配置环境变量

```bash
cp .env.example .env
```

编辑 `.env` 文件，修改以下关键配置：

```env
# 应用配置
APP_URL=https://your-domain.com
APP_ENV=production
APP_DEBUG=false

# 数据库配置
DB_HOST=localhost
DB_PORT=3306
DB_NAME=kindle_reading
DB_USER=your_db_user
DB_PASS=your_strong_password

# 安全配置（请修改为随机字符串）
ENCRYPTION_KEY=your-random-encryption-key-32-chars
```

### 4. 创建数据库

```bash
mysql -u root -p < database/schema.sql
```

或手动执行：

```bash
mysql -u root -p
```

```sql
CREATE DATABASE kindle_reading CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kindle_reading;
SOURCE database/schema.sql;
EXIT;
```

### 5. 设置目录权限

```bash
chmod -R 755 storage/
chmod -R 755 public/uploads/
chown -R www-data:www-data storage/ public/uploads/
```

### 6. 配置 Nginx

复制 Nginx 配置文件：

```bash
sudo cp nginx/kindle-reading.conf /etc/nginx/sites-available/kindle-reading
```

编辑配置文件，替换以下内容：

```nginx
server_name your-domain.com;
root /var/www/kindle-reading-php/public;
ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
```

启用站点：

```bash
sudo ln -s /etc/nginx/sites-available/kindle-reading /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 7. 配置 SSL 证书（使用 Let's Encrypt）

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

## 配置 GitHub OAuth

### 1. 创建 GitHub OAuth 应用

1. 访问 [GitHub Developer Settings](https://github.com/settings/developers)
2. 点击 **"New OAuth App"**
3. 填写应用信息：
   - **Application name**: Kindle Reading GTK
   - **Homepage URL**: `https://your-domain.com`
   - **Authorization callback URL**: `https://your-domain.com/api/callback.php`
4. 点击 **"Register application"**
5. 复制 **Client ID** 和生成 **Client Secret**

### 2. 更新环境变量

编辑 `.env` 文件，添加 GitHub OAuth 配置：

```env
GITHUB_CLIENT_ID=your_github_client_id
GITHUB_CLIENT_SECRET=your_github_client_secret
GITHUB_REDIRECT_URI=https://your-domain.com/api/callback.php
```

### 3. 重启服务

```bash
sudo systemctl restart php8.0-fpm
sudo systemctl restart nginx
```

## 测试部署

### 1. 检查服务状态

```bash
# 检查 Nginx
sudo systemctl status nginx

# 检查 PHP-FPM
sudo systemctl status php8.0-fpm

# 检查 MySQL
sudo systemctl status mysql
```

### 2. 测试 API 接口

```bash
# 测试系统状态
curl https://your-domain.com/api/system

# 测试健康检查
curl https://your-domain.com/health
```

### 3. 测试 OAuth 认证

1. 访问 `https://your-domain.com`
2. 点击 "GitHub 登录"
3. 授权后应成功跳转回应用

### 4. 查看日志

```bash
# 应用日志
tail -f storage/logs/$(date +%Y-%m-%d).log

# Nginx 错误日志
sudo tail -f /var/log/nginx/kindle-reading-error.log

# PHP-FPM 错误日志
sudo tail -f /var/log/php8.0-fpm.log
```

## 常见问题

### 1. 数据库连接失败

**问题**：`SQLSTATE[HY000] [2002] Connection refused`

**解决方案**：
- 检查 `.env` 中的数据库配置是否正确
- 确认 MySQL 服务正在运行：`sudo systemctl status mysql`
- 检查数据库用户权限：`GRANT ALL PRIVILEGES ON kindle_reading.* TO 'your_db_user'@'localhost';`

### 2. OAuth 回调失败

**问题**：GitHub 授权后无法跳转回应用

**解决方案**：
- 确认 GitHub OAuth 应用的回调 URL 与 `.env` 中的 `GITHUB_REDIRECT_URI` 完全一致
- 检查 HTTPS 证书是否有效
- 确认 Nginx 配置中的 `server_name` 与域名匹配

### 3. 文件上传失败

**问题**：上传日志文件时返回错误

**解决方案**：
- 检查 `public/uploads` 目录权限：`ls -la public/uploads`
- 确认 Nginx 配置中的 `client_max_body_size` 足够大（默认 100M）
- 检查 PHP 配置中的 `upload_max_filesize` 和 `post_max_size`

### 4. 404 错误

**问题**：访问页面或 API 时返回 404

**解决方案**：
- 确认 Nginx 配置中的 `root` 路径正确
- 检查 `try_files` 配置是否正确
- 确认 PHP-FPM socket 路径正确

### 5. 权限错误

**问题**：写入日志或上传文件时权限不足

**解决方案**：
```bash
# 重新设置权限
sudo chown -R www-data:www-data storage/ public/uploads/
sudo chmod -R 755 storage/ public/uploads/
```

### 6. Composer 依赖安装失败

**问题**：`composer install` 执行失败

**解决方案**：
```bash
# 更新 Composer
composer self-update

# 清除缓存
composer clear-cache

# 重新安装
composer install --no-dev --optimize-autoloader
```

### 7. PHP 扩展缺失

**问题**：`Class 'PDO' not found` 或类似错误

**解决方案**：
```bash
# 安装必需的 PHP 扩展
sudo apt install php8.0-pdo php8.0-mysql php8.0-mbstring php8.0-xml php8.0-curl

# 重启 PHP-FPM
sudo systemctl restart php8.0-fpm
```

## 最小配置示例

```env
# .env 最小配置
APP_NAME="Kindle Reading GTK"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_HOST=localhost
DB_PORT=3306
DB_NAME=kindle_reading
DB_USER=kindle_user
DB_PASS=StrongPassword123!

GITHUB_CLIENT_ID=ghp_xxxxxxxxxxxx
GITHUB_CLIENT_SECRET=ghs_xxxxxxxxxxxx
GITHUB_REDIRECT_URI=https://your-domain.com/api/callback.php

ENCRYPTION_KEY=32-char-random-encryption-key-here
```

## 验证命令清单

```bash
# 1. 检查 PHP 版本
php -v

# 2. 检查 PHP 扩展
php -m | grep -E "pdo|json|mbstring|openssl|curl"

# 3. 检查 Composer
composer --version

# 4. 检查数据库连接
mysql -u kindle_user -p kindle_reading -e "SELECT 1;"

# 5. 检查 Nginx 配置
sudo nginx -t

# 6. 检查 SSL 证书
sudo certbot certificates

# 7. 测试 API
curl -I https://your-domain.com/api/system
```

## 下一步

- 阅读完整文档：[`readme.md`](readme.md)
- 查看 API 规范：[`plans/api-spec.md`](plans/api-spec.md)
- 了解数据库设计：[`plans/database-design.md`](plans/database-design.md)

## 安全建议

1. **定期更新**：保持 PHP、MySQL、Nginx 为最新版本
2. **强密码**：使用强密码保护数据库和系统
3. **HTTPS**：生产环境必须使用 HTTPS
4. **备份**：定期备份数据库和上传文件
5. **监控**：监控日志和系统资源使用情况

---

**部署成功后，您可以通过 Kindle GTK 客户端连接到服务端开始同步阅读日志！**