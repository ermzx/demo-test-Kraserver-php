<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 用户管理接口
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
$resourceId = $pathParts[1] ?? null;

try {
    // 初始化数据库连接
    Database::getInstance();

    // 验证用户令牌
    $user = authenticateUser();

    // 路由处理
    switch ($endpoint) {
        case 'profile':
            handleProfile($method, $user);
            break;

        case 'devices':
            handleDevices($method, $user, $resourceId);
            break;

        case 'stats':
            handleStats($method, $user);
            break;

        default:
            Response::notFound('Endpoint not found');
    }
} catch (\Exception $e) {
    Logger::error('User API error', [
        'endpoint' => $endpoint,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    Response::serverError('Internal server error');
}

/**
 * 验证用户令牌
 * 
 * @return User 用户实例
 */
function authenticateUser(): User
{
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

    return $user;
}

/**
 * 处理用户信息接口
 * 
 * GET /user/profile - 获取用户信息
 * PUT /user/profile - 更新用户信息（预留）
 */
function handleProfile(string $method, User $user): void
{
    switch ($method) {
        case 'GET':
            // 获取用户信息
            $data = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'avatar_url' => $user->getAvatarUrl(),
                'created_at' => $user->getCreatedAt(),
                'last_login_at' => $user->getLastLoginAt(),
                'devices_count' => $user->getDevicesCount(),
            ];
            Response::success($data);
            break;

        case 'PUT':
            // 更新用户信息（预留）
            Response::error('Profile update not implemented', 501);
            break;

        default:
            Response::methodNotAllowed();
    }
}

/**
 * 处理设备管理接口
 * 
 * GET /user/devices - 获取设备列表
 * PUT /user/devices/{id} - 更新设备名称
 * DELETE /user/devices/{id} - 解绑设备
 */
function handleDevices(string $method, User $user, ?string $resourceId): void
{
    switch ($method) {
        case 'GET':
            // 获取设备列表
            if ($resourceId !== null) {
                Response::notFound('Use GET /user/devices without ID');
            }

            $devices = $user->getDevices();
            $deviceList = array_map(function ($device) {
                return [
                    'id' => $device->getId(),
                    'device_id' => $device->getDeviceId(),
                    'device_name' => $device->getDeviceName(),
                    'created_at' => $device->getCreatedAt(),
                    'last_sync_at' => $device->getLastSyncAt(),
                ];
            }, $devices);

            Response::success(['devices' => $deviceList]);
            break;

        case 'PUT':
            // 更新设备名称
            if ($resourceId === null) {
                Response::validationError(['id' => 'Device ID is required']);
            }

            $deviceId = (int)$resourceId;

            // 验证设备 ID
            if ($deviceId <= 0) {
                Response::validationError(['id' => 'Invalid device ID']);
            }

            // 查找设备
            $device = Device::findById($deviceId);

            if ($device === null) {
                Response::notFound('Device not found');
            }

            // 验证设备归属
            if ($device->getUserId() !== $user->getId()) {
                Response::forbidden('You do not have permission to update this device');
            }

            // 获取请求数据
            $input = json_decode(file_get_contents('php://input'), true);

            // 验证必填参数
            if (empty($input['device_name'])) {
                Response::validationError(['device_name' => 'Device name is required']);
            }

            $deviceName = trim($input['device_name']);

            // 验证设备名称长度
            if (strlen($deviceName) < 1 || strlen($deviceName) > 200) {
                Response::validationError(['device_name' => 'Device name must be between 1 and 200 characters']);
            }

            // 更新设备名称
            $result = $device->update(['device_name' => $deviceName]);

            if (!$result) {
                Response::serverError('Failed to update device');
            }

            Logger::info('Updated device name', [
                'device_id' => $deviceId,
                'user_id' => $user->getId(),
                'device_name' => $deviceName,
            ]);

            Response::updated([
                'id' => $device->getId(),
                'device_id' => $device->getDeviceId(),
                'device_name' => $device->getDeviceName(),
            ]);
            break;

        case 'DELETE':
            // 解绑设备
            if ($resourceId === null) {
                Response::validationError(['id' => 'Device ID is required']);
            }

            $deviceId = (int)$resourceId;

            // 验证设备 ID
            if ($deviceId <= 0) {
                Response::validationError(['id' => 'Invalid device ID']);
            }

            // 查找设备
            $device = Device::findById($deviceId);

            if ($device === null) {
                Response::notFound('Device not found');
            }

            // 验证设备归属
            if ($device->getUserId() !== $user->getId()) {
                Response::forbidden('You do not have permission to delete this device');
            }

            // 删除设备
            $result = $device->delete();

            if (!$result) {
                Response::serverError('Failed to delete device');
            }

            Logger::info('Deleted device', [
                'device_id' => $deviceId,
                'user_id' => $user->getId(),
            ]);

            Response::deleted('Device unbound successfully');
            break;

        default:
            Response::methodNotAllowed();
    }
}

/**
 * 处理用户统计数据接口
 * 
 * GET /user/stats - 获取用户统计数据
 */
function handleStats(string $method, User $user): void
{
    if ($method !== 'GET') {
        Response::methodNotAllowed();
    }

    $userId = $user->getId();

    // 获取设备数量
    $devicesCount = $user->getDevicesCount();

    // 获取日志数量
    $logsCount = ReadingLog::countByUser($userId);

    // 获取总存储大小
    $totalSize = ReadingLog::getTotalSizeByUser($userId);

    // 格式化存储大小
    $formattedSize = formatBytes($totalSize);

    $data = [
        'devices_count' => $devicesCount,
        'logs_count' => $logsCount,
        'total_size' => $totalSize,
        'total_size_formatted' => $formattedSize,
    ];

    Response::success($data);
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