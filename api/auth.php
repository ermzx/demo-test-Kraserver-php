<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 认证接口
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
use KindleReading\Core\Response;
use KindleReading\Core\Logger;
use KindleReading\Auth\OAuth;
use KindleReading\Models\OAuthSession;
use KindleReading\Models\User;

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 获取请求方法和路径
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';

// 解析路径
$pathParts = explode('/', trim($path, '/'));
$endpoint = $pathParts[0] ?? '';

try {
    // 初始化数据库连接
    Database::getInstance();

    // 清理过期的 OAuth 会话
    OAuthSession::cleanupExpired();
    User::cleanupExpiredTokens();

    // 路由处理
    switch ($endpoint) {
        case 'request':
            handleAuthRequest();
            break;

        case 'status':
            handleAuthStatus();
            break;

        case 'refresh':
            handleAuthRefresh();
            break;

        case 'logout':
            handleAuthLogout();
            break;

        default:
            Response::notFound('Endpoint not found');
    }
} catch (\Exception $e) {
    Logger::error('Auth API error', [
        'endpoint' => $endpoint,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    Response::serverError('Internal server error');
}

/**
 * 处理登录请求
 * 
 * POST /auth/request
 */
function handleAuthRequest(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::methodNotAllowed();
    }

    // 获取请求数据
    $input = json_decode(file_get_contents('php://input'), true);

    // 验证必填参数
    if (empty($input['device_id'])) {
        Response::validationError(['device_id' => 'Device ID is required']);
    }

    $deviceId = $input['device_id'];

    // 验证设备 ID 格式
    if (strlen($deviceId) < 1 || strlen($deviceId) > 100) {
        Response::validationError(['device_id' => 'Invalid device ID']);
    }

    // 创建 OAuth 实例
    $oauth = new OAuth();

    // 发起 OAuth 认证
    $result = $oauth->initiateAuth($deviceId);

    Response::success($result, 'Auth request created');
}

/**
 * 处理登录状态查询
 * 
 * GET /auth/status?session_token=xxx
 */
function handleAuthStatus(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Response::methodNotAllowed();
    }

    // 获取 session_token
    $sessionToken = $_GET['session_token'] ?? '';

    if (empty($sessionToken)) {
        Response::validationError(['session_token' => 'Session token is required']);
    }

    // 查找会话
    $session = OAuthSession::findBySessionToken($sessionToken);

    if ($session === null) {
        Response::notFound('Session not found');
    }

    // 检查会话是否过期
    if ($session->isExpired()) {
        $session->updateStatus('expired');
        Response::error('Session expired', 410);
    }

    // 根据会话状态返回响应
    $status = $session->getStatus();

    if ($status === 'pending') {
        Response::success(['status' => 'pending']);
    } elseif ($status === 'authorized' || $status === 'completed') {
        // 获取用户信息
        $userId = $session->getUserId();
        if ($userId === null) {
            Response::error('User not found', 500);
        }

        $user = User::findById($userId);
        if ($user === null) {
            Response::error('User not found', 500);
        }

        // 生成新的访问令牌
        $userToken = $user->generateAccessToken();

        // 更新会话状态为 completed
        if ($status === 'authorized') {
            $session->updateStatus('completed');
        }

        Response::success([
            'status' => 'authorized',
            'user_token' => $userToken,
            'user_info' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'avatar_url' => $user->getAvatarUrl(),
            ],
        ]);
    } else {
        Response::error('Invalid session status', 400);
    }
}

/**
 * 处理令牌刷新
 * 
 * POST /auth/refresh
 */
function handleAuthRefresh(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::methodNotAllowed();
    }

    // 获取 Authorization 头
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (empty($authHeader) || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        Response::unauthorized('Authorization header is required');
    }

    $token = $matches[1];

    // 验证令牌
    $user = User::findByToken($token);

    if ($user === null) {
        Response::unauthorized('Invalid or expired token');
    }

    // 撤销旧令牌
    $user->revokeToken($token);

    // 生成新令牌
    $newToken = $user->generateAccessToken();

    Response::success([
        'user_token' => $newToken,
        'user_info' => [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'avatar_url' => $user->getAvatarUrl(),
        ],
    ], 'Token refreshed');
}

/**
 * 处理登出
 * 
 * POST /auth/logout
 */
function handleAuthLogout(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::methodNotAllowed();
    }

    // 获取 Authorization 头
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (empty($authHeader) || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        Response::unauthorized('Authorization header is required');
    }

    $token = $matches[1];

    // 验证令牌
    $user = User::findByToken($token);

    if ($user === null) {
        Response::unauthorized('Invalid or expired token');
    }

    // 撤销令牌
    $user->revokeToken($token);

    // 撤销用户所有令牌
    $user->revokeAllTokens();

    Response::success(null, 'Logged out successfully');
}