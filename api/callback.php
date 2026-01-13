<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - OAuth 回调接口
 * ============================================
 * 
 * @package KindleReading\API
 * @version 1.0.0
 */

// 定义应用根目录
define('APP_ROOT', dirname(__DIR__));

// 加载自动加载器
require_once APP_ROOT . '/vendor/autoload.php';

// 加载配置
require_once APP_ROOT . '/config/config.php';

use KindleReading\Core\Database;
use KindleReading\Core\Logger;
use KindleReading\Auth\OAuth;
use KindleReading\Models\OAuthSession;

// 初始化数据库连接
try {
    Database::getInstance();
} catch (\Exception $e) {
    renderErrorPage('Database Error', 'Failed to connect to database. Please try again later.');
    exit;
}

// 获取回调参数
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';
$errorDescription = $_GET['error_description'] ?? '';

// 检查是否有错误
if (!empty($error)) {
    Logger::error('OAuth callback error', [
        'error' => $error,
        'error_description' => $errorDescription,
    ]);
    renderErrorPage('Authorization Failed', $errorDescription ?: 'An error occurred during authorization.');
    exit;
}

// 验证必填参数
if (empty($code) || empty($state)) {
    Logger::error('OAuth callback missing parameters', [
        'code' => $code,
        'state' => $state,
    ]);
    renderErrorPage('Invalid Request', 'Missing required parameters.');
    exit;
}

try {
    // 创建 OAuth 实例
    $oauth = new OAuth();

    // 处理 OAuth 回调
    $result = $oauth->handleCallback($code, $state);

    if ($result === null) {
        renderErrorPage('Authorization Failed', 'Failed to complete authorization. The session may have expired.');
        exit;
    }

    // 授权成功，显示成功页面
    renderSuccessPage($result['user'], $result['device']);

} catch (\Exception $e) {
    Logger::error('OAuth callback exception', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    renderErrorPage('Server Error', 'An unexpected error occurred. Please try again later.');
}

/**
 * 渲染成功页面
 * 
 * @param \KindleReading\Models\User $user 用户实例
 * @param \KindleReading\Models\Device $device 设备实例
 */
function renderSuccessPage($user, $device): void
{
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授权成功 - Kindle Reading GTK</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }

        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
        }

        .icon svg {
            width: 40px;
            height: 40px;
            fill: white;
        }

        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 30px;
        }

        .user-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: left;
        }

        .user-info-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .user-info-item:last-child {
            margin-bottom: 0;
        }

        .user-info-label {
            color: #666;
            font-size: 14px;
            width: 80px;
            flex-shrink: 0;
        }

        .user-info-value {
            color: #333;
            font-size: 14px;
            font-weight: 500;
        }

        .avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 auto 20px;
            object-fit: cover;
            border: 3px solid #667eea;
        }

        .device-info {
            background: #e8f4f8;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }

        .device-info-title {
            color: #667eea;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .device-info-item {
            color: #333;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .device-info-item:last-child {
            margin-bottom: 0;
        }

        .note {
            color: #999;
            font-size: 13px;
            margin-top: 20px;
        }

        @keyframes checkmark {
            0% {
                stroke-dashoffset: 100;
            }
            100% {
                stroke-dashoffset: 0;
            }
        }

        .checkmark {
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
            animation: checkmark 0.5s ease-in-out forwards;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path class="checkmark" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        
        <h1>授权成功</h1>
        <p class="subtitle">您的 Kindle 设备已成功绑定</p>
        
        <?php if ($user->getAvatarUrl()): ?>
        <img src="<?php echo htmlspecialchars($user->getAvatarUrl()); ?>" alt="Avatar" class="avatar">
        <?php endif; ?>
        
        <div class="user-info">
            <div class="user-info-item">
                <span class="user-info-label">用户名:</span>
                <span class="user-info-value"><?php echo htmlspecialchars($user->getUsername()); ?></span>
            </div>
            <div class="user-info-item">
                <span class="user-info-label">GitHub ID:</span>
                <span class="user-info-value"><?php echo htmlspecialchars($user->getGithubUid()); ?></span>
            </div>
        </div>
        
        <div class="device-info">
            <div class="device-info-title">设备信息</div>
            <div class="device-info-item">设备 ID: <?php echo htmlspecialchars($device->getDeviceId()); ?></div>
            <div class="device-info-item">绑定时间: <?php echo htmlspecialchars($device->getCreatedAt()); ?></div>
        </div>
        
        <p class="note">您可以关闭此页面，Kindle 设备将自动完成登录。</p>
    </div>
</body>
</html>
    <?php
}

/**
 * 渲染错误页面
 * 
 * @param string $title 错误标题
 * @param string $message 错误消息
 */
function renderErrorPage(string $title, string $message): void
{
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - Kindle Reading GTK</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }

        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
        }

        .icon svg {
            width: 40px;
            height: 40px;
            fill: white;
        }

        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 30px;
        }

        .error-message {
            background: #fff5f5;
            border-left: 4px solid #f5576c;
            padding: 15px 20px;
            border-radius: 8px;
            text-align: left;
            margin-bottom: 30px;
        }

        .error-message p {
            color: #c53030;
            font-size: 14px;
            line-height: 1.6;
        }

        .note {
            color: #999;
            font-size: 13px;
            margin-top: 20px;
        }

        .retry-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .retry-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="white"/>
            </svg>
        </div>
        
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <p class="subtitle">授权过程中出现问题</p>
        
        <div class="error-message">
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
        
        <p class="note">请在您的 Kindle 设备上重新发起登录请求。</p>
    </div>
</body>
</html>
    <?php
}