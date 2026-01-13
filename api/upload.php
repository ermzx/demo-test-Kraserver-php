<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 文件上传接口
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
use KindleReading\Services\UploadService;

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 只允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

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

    // 获取设备 ID
    $deviceId = $_POST['device_id'] ?? '';

    if (empty($deviceId)) {
        Response::validationError(['device_id' => 'Device ID is required']);
    }

    // 查找设备
    $device = Device::findByDeviceId($deviceId);

    if ($device === null) {
        Response::notFound('Device not found');
    }

    // 验证设备归属
    if ($device->getUserId() !== $user->getId()) {
        Response::forbidden('Device does not belong to user');
    }

    // 检查是否有上传的文件
    if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
        Response::validationError(['files' => 'No files uploaded']);
    }

    // 创建上传服务实例
    $uploadService = new UploadService();

    // 上传文件
    $result = $uploadService->uploadMultipleFiles(
        $_FILES['files'],
        $user->getId(),
        $device->getId()
    );

    if ($result['success']) {
        Response::success([
            'uploaded_files' => $result['uploaded_files'],
            'failed_files' => $result['failed_files'],
            'total_size' => $result['total_size'],
        ], $result['message']);
    } else {
        Response::error($result['message'], 400, [
            'uploaded_files' => $result['uploaded_files'],
            'failed_files' => $result['failed_files'],
            'results' => $result['results'],
        ]);
    }

} catch (\Exception $e) {
    Logger::error('Upload API error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    Response::serverError('Internal server error');
}