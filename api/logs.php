<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 日志管理接口
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
use KindleReading\Models\User;
use KindleReading\Models\Device;
use KindleReading\Models\ReadingLog;
use KindleReading\Utils\FileHelper;

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE, OPTIONS');
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
$logId = $pathParts[1] ?? null;
$action = $pathParts[2] ?? null;

try {
    // 初始化数据库连接
    Database::getInstance();

    // 获取 Authorization 头
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (empty($authHeader) || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        Response::unauthorized('Authorization header is required');
    }

    $token = $matches[1];

    // 验证用户令牌
    $user = User::findByToken($token);

    if ($user === null) {
        Response::unauthorized('Invalid or expired token');
    }

    // 路由处理
    if (empty($logId)) {
        // 获取日志列表
        if ($method === 'GET') {
            handleGetLogsList($user);
        } else {
            Response::methodNotAllowed();
        }
    } else {
        // 处理特定日志的操作
        if ($action === 'download') {
            // 下载日志文件
            if ($method === 'GET') {
                handleDownloadLog($user, $logId);
            } else {
                Response::methodNotAllowed();
            }
        } else {
            // 获取日志详情或删除日志
            if ($method === 'GET') {
                handleGetLogDetail($user, $logId);
            } elseif ($method === 'DELETE') {
                handleDeleteLog($user, $logId);
            } else {
                Response::methodNotAllowed();
            }
        }
    }

} catch (\Exception $e) {
    Logger::error('Logs API error', [
        'endpoint' => $endpoint,
        'log_id' => $logId,
        'action' => $action,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    Response::serverError('Internal server error');
}

/**
 * 处理获取日志列表
 * 
 * GET /logs?device_id=xxx&page=1&limit=20
 */
function handleGetLogsList(User $user): void
{
    // 获取查询参数
    $deviceId = $_GET['device_id'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));

    // 验证设备 ID（如果提供）
    $dbDeviceId = null;
    if ($deviceId !== null) {
        $device = Device::findByDeviceId($deviceId);
        if ($device === null) {
            Response::notFound('Device not found');
        }
        if ($device->getUserId() !== $user->getId()) {
            Response::forbidden('Device does not belong to user');
        }
        $dbDeviceId = $device->getId();
    }

    // 获取日志列表
    $result = ReadingLog::paginateByUser($user->getId(), $dbDeviceId, $page, $limit);

    // 格式化日志数据
    $logs = array_map(function ($log) {
        $device = Device::findById($log->getDeviceId());
        return [
            'id' => $log->getId(),
            'device_id' => $device ? $device->getDeviceId() : null,
            'device_name' => $device ? $device->getDeviceName() : null,
            'file_name' => $log->getFileName(),
            'file_size' => $log->getFileSize(),
            'file_size_formatted' => FileHelper::formatFileSize($log->getFileSize()),
            'upload_at' => $log->getUploadAt(),
        ];
    }, $result['logs']);

    Response::success([
        'logs' => $logs,
        'pagination' => $result['pagination'],
    ]);
}

/**
 * 处理获取日志详情
 * 
 * GET /logs/{id}
 */
function handleGetLogDetail(User $user, string $logId): void
{
    // 验证日志 ID
    if (!is_numeric($logId)) {
        Response::validationError(['id' => 'Invalid log ID']);
    }

    $log = ReadingLog::findById((int)$logId);

    if ($log === null) {
        Response::notFound('Log not found');
    }

    // 验证日志归属
    if ($log->getUserId() !== $user->getId()) {
        Response::forbidden('Log does not belong to user');
    }

    // 获取设备信息
    $device = Device::findById($log->getDeviceId());

    Response::success([
        'id' => $log->getId(),
        'device' => $device ? [
            'id' => $device->getId(),
            'device_id' => $device->getDeviceId(),
            'device_name' => $device->getDeviceName(),
        ] : null,
        'file_name' => $log->getFileName(),
        'file_size' => $log->getFileSize(),
        'file_size_formatted' => FileHelper::formatFileSize($log->getFileSize()),
        'file_hash' => $log->getFileHash(),
        'upload_at' => $log->getUploadAt(),
    ]);
}

/**
 * 处理下载日志文件
 * 
 * GET /logs/{id}/download
 */
function handleDownloadLog(User $user, string $logId): void
{
    // 验证日志 ID
    if (!is_numeric($logId)) {
        Response::validationError(['id' => 'Invalid log ID']);
    }

    $log = ReadingLog::findById((int)$logId);

    if ($log === null) {
        Response::notFound('Log not found');
    }

    // 验证日志归属
    if ($log->getUserId() !== $user->getId()) {
        Response::forbidden('Log does not belong to user');
    }

    // 检查文件是否存在
    $filePath = $log->getFilePath();
    if ($filePath === null || !file_exists($filePath)) {
        Response::notFound('File not found');
    }

    // 下载文件
    Response::download($filePath, $log->getFileName());
}

/**
 * 处理删除日志
 * 
 * DELETE /logs/{id}
 */
function handleDeleteLog(User $user, string $logId): void
{
    // 验证日志 ID
    if (!is_numeric($logId)) {
        Response::validationError(['id' => 'Invalid log ID']);
    }

    $log = ReadingLog::findById((int)$logId);

    if ($log === null) {
        Response::notFound('Log not found');
    }

    // 验证日志归属
    if ($log->getUserId() !== $user->getId()) {
        Response::forbidden('Log does not belong to user');
    }

    // 删除日志
    $result = $log->delete();

    if ($result) {
        Response::success(null, 'Log deleted successfully');
    } else {
        Response::error('Failed to delete log', 500);
    }
}