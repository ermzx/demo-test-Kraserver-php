<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - API 路由入口
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

use KindleReading\Core\Response;
use KindleReading\Core\Logger;

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

// 获取请求路径
$path = $_SERVER['PATH_INFO'] ?? '/';

// 解析路径
$pathParts = explode('/', trim($path, '/'));
$module = $pathParts[0] ?? '';

try {
    // 路由分发
    switch ($module) {
        case 'auth':
            // 认证接口
            require_once APP_ROOT . '/api/auth.php';
            break;

        case 'user':
            // 用户管理接口
            require_once APP_ROOT . '/api/user.php';
            break;

        case 'system':
            // 系统接口
            require_once APP_ROOT . '/api/system.php';
            break;

        case 'upload':
            // 文件上传接口
            require_once APP_ROOT . '/api/upload.php';
            break;

        case 'logs':
            // 日志接口
            require_once APP_ROOT . '/api/logs.php';
            break;

        case 'callback':
            // OAuth 回调接口
            require_once APP_ROOT . '/api/callback.php';
            break;

        default:
            // 根路径 - 返回 API 信息
            if ($path === '/' || $path === '') {
                handleRoot();
            } else {
                Response::notFound('Endpoint not found');
            }
    }
} catch (\Exception $e) {
    Logger::error('API error', [
        'path' => $path,
        'module' => $module,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    Response::serverError('Internal server error');
}

/**
 * 处理根路径请求
 * 
 * GET / - 返回 API 信息
 */
function handleRoot(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Response::methodNotAllowed();
    }

    $info = [
        'name' => APP_NAME,
        'version' => '1.0.0',
        'description' => 'Kindle Reading GTK 云同步服务端 API',
        'endpoints' => [
            'auth' => [
                'POST /api/auth/request' => '请求登录',
                'GET /api/auth/status' => '查询登录状态',
                'POST /api/auth/refresh' => '刷新令牌',
                'POST /api/auth/logout' => '登出',
            ],
            'user' => [
                'GET /api/user/profile' => '获取用户信息',
                'PUT /api/user/profile' => '更新用户信息（预留）',
                'GET /api/user/devices' => '获取设备列表',
                'PUT /api/user/devices/{id}' => '更新设备名称',
                'DELETE /api/user/devices/{id}' => '解绑设备',
                'GET /api/user/stats' => '获取用户统计数据',
            ],
            'system' => [
                'GET /api/system/health' => '健康检查',
                'GET /api/system/config' => '获取系统配置',
            ],
            'upload' => [
                'POST /api/upload' => '上传日志文件',
            ],
            'logs' => [
                'GET /api/logs' => '获取日志列表',
                'GET /api/logs/{id}/download' => '下载日志文件',
            ],
        ],
        'documentation' => 'https://github.com/yourusername/kindle-reading-php',
    ];

    Response::success($info);
}