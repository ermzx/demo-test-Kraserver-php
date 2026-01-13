<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 系统接口
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
    // 路由处理
    switch ($endpoint) {
        case 'health':
            handleHealth();
            break;

        case 'config':
            handleConfig();
            break;

        default:
            Response::notFound('Endpoint not found');
    }
} catch (\Exception $e) {
    Logger::error('System API error', [
        'endpoint' => $endpoint,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    Response::serverError('Internal server error');
}

/**
 * 处理健康检查接口
 * 
 * GET /system/health - 健康检查
 */
function handleHealth(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Response::methodNotAllowed();
    }

    $health = [
        'status' => 'ok',
        'timestamp' => date('Y-m-d\TH:i:s\Z'),
        'version' => '1.0.0',
    ];

    // 检查数据库连接
    try {
        $db = Database::getInstance();
        $health['database'] = 'connected';
    } catch (\Exception $e) {
        $health['status'] = 'error';
        $health['database'] = 'disconnected';
        $health['error'] = $e->getMessage();
    }

    // 检查上传目录
    $uploadPath = APP_ROOT . '/public/uploads';
    if (is_dir($uploadPath) && is_writable($uploadPath)) {
        $health['upload_dir'] = 'writable';
    } else {
        $health['status'] = 'warning';
        $health['upload_dir'] = 'not_writable';
    }

    // 检查日志目录
    $logPath = APP_ROOT . '/storage/logs';
    if (is_dir($logPath) && is_writable($logPath)) {
        $health['log_dir'] = 'writable';
    } else {
        $health['status'] = 'warning';
        $health['log_dir'] = 'not_writable';
    }

    // 根据状态返回不同的 HTTP 状态码
    $statusCode = $health['status'] === 'ok' ? 200 : 503;

    $response = new Response($statusCode);
    $response->json($health);
}

/**
 * 处理系统配置接口
 * 
 * GET /system/config - 获取公开的系统配置
 */
function handleConfig(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Response::methodNotAllowed();
    }

    // 获取公开的系统配置
    $config = [
        'app_name' => APP_NAME,
        'version' => '1.0.0',
        'max_file_size' => MAX_FILE_SIZE,
        'max_file_size_formatted' => formatBytes(MAX_FILE_SIZE),
        'allowed_extensions' => explode(',', ALLOWED_EXTENSIONS),
        'session_timeout' => SESSION_TIMEOUT,
    ];

    Response::success($config);
}

/**
 * 格式化字节大小
 * 
 * @param int $bytes 字节数
 * @return string 格式化后的字符串
 */
function formatBytes(int $bytes): string
{
    if ($bytes === 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $base = log($bytes, 1024);
    $index = min(floor($base), count($units) - 1);

    return round(pow(1024, $base - floor($base)), 2) . ' ' . $units[$index];
}