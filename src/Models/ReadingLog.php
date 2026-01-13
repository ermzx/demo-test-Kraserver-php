<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 阅读日志模型
 * ============================================
 * 
 * @package KindleReading\Models
 * @version 1.0.0
 */

namespace KindleReading\Models;

use KindleReading\Core\Database;
use KindleReading\Core\Logger;

/**
 * 阅读日志模型
 * 
 * 管理阅读日志文件信息
 */
class ReadingLog
{
    /**
     * 日志 ID
     * 
     * @var int|null
     */
    private ?int $id = null;

    /**
     * 用户 ID
     * 
     * @var int|null
     */
    private ?int $userId = null;

    /**
     * 设备 ID
     * 
     * @var int|null
     */
    private ?int $deviceId = null;

    /**
     * 文件存储路径
     * 
     * @var string|null
     */
    private ?string $filePath = null;

    /**
     * 原始文件名
     * 
     * @var string|null
     */
    private ?string $fileName = null;

    /**
     * 文件大小（字节）
     * 
     * @var int|null
     */
    private ?int $fileSize = null;

    /**
     * 文件 SHA256 哈希值
     * 
     * @var string|null
     */
    private ?string $fileHash = null;

    /**
     * 上传时间
     * 
     * @var string|null
     */
    private ?string $uploadAt = null;

    /**
     * 构造函数
     * 
     * @param array|null $data 日志数据
     */
    public function __construct(?array $data = null)
    {
        if ($data !== null) {
            $this->fill($data);
        }
    }

    /**
     * 填充模型数据
     * 
     * @param array $data 日志数据
     * @return void
     */
    private function fill(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->userId = $data['user_id'] ?? null;
        $this->deviceId = $data['device_id'] ?? null;
        $this->filePath = $data['file_path'] ?? null;
        $this->fileName = $data['file_name'] ?? null;
        $this->fileSize = $data['file_size'] ?? null;
        $this->fileHash = $data['file_hash'] ?? null;
        $this->uploadAt = $data['upload_at'] ?? null;
    }

    /**
     * 创建日志记录
     * 
     * @param array $data 日志数据
     * @return int 日志 ID
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO reading_logs (
            user_id, device_id, file_path, file_name, file_size, file_hash
        ) VALUES (
            :user_id, :device_id, :file_path, :file_name, :file_size, :file_hash
        )";

        $params = [
            'user_id' => $data['user_id'],
            'device_id' => $data['device_id'],
            'file_path' => $data['file_path'],
            'file_name' => $data['file_name'],
            'file_size' => $data['file_size'],
            'file_hash' => $data['file_hash'],
        ];

        $logId = (int)Database::insert($sql, $params);
        
        Logger::info('Created reading log', [
            'log_id' => $logId,
            'user_id' => $data['user_id'],
            'device_id' => $data['device_id'],
            'file_name' => $data['file_name'],
        ]);

        return $logId;
    }

    /**
     * 根据 ID 查询日志
     * 
     * @param int $id 日志 ID
     * @return self|null 日志实例，不存在返回 null
     */
    public static function findById(int $id): ?self
    {
        $sql = "SELECT * FROM reading_logs WHERE id = :id LIMIT 1";
        $data = Database::queryOne($sql, ['id' => $id]);

        return $data !== null ? new self($data) : null;
    }

    /**
     * 根据用户 ID 查询所有日志
     * 
     * @param int $userId 用户 ID
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 日志实例数组
     */
    public static function findByUserId(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM reading_logs 
                WHERE user_id = :user_id 
                ORDER BY upload_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = Database::executeStatement($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $results = $stmt->fetchAll();

        return array_map(function ($data) {
            return new self($data);
        }, $results);
    }

    /**
     * 根据设备 ID 查询所有日志
     * 
     * @param int $deviceId 设备 ID
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 日志实例数组
     */
    public static function findByDeviceId(int $deviceId, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM reading_logs 
                WHERE device_id = :device_id 
                ORDER BY upload_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = Database::executeStatement($sql, [
            'device_id' => $deviceId,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $results = $stmt->fetchAll();

        return array_map(function ($data) {
            return new self($data);
        }, $results);
    }

    /**
     * 根据用户 ID 和设备 ID 查询日志（分页）
     * 
     * @param int $userId 用户 ID
     * @param int|null $deviceId 设备 ID（可选）
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array 包含日志列表和分页信息的数组
     */
    public static function paginateByUser(int $userId, ?int $deviceId = null, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        // 构建查询条件
        $where = ['user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($deviceId !== null) {
            $where[] = 'device_id = :device_id';
            $params['device_id'] = $deviceId;
        }

        $whereClause = implode(' AND ', $where);

        // 查询总数
        $countSql = "SELECT COUNT(*) as total FROM reading_logs WHERE {$whereClause}";
        $countResult = Database::queryOne($countSql, $params);
        $total = (int)($countResult['total'] ?? 0);

        // 查询日志列表
        $sql = "SELECT * FROM reading_logs 
                WHERE {$whereClause} 
                ORDER BY upload_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $stmt = Database::executeStatement($sql, $params);
        $results = $stmt->fetchAll();

        $logs = array_map(function ($data) {
            return new self($data);
        }, $results);

        return [
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int)ceil($total / $limit),
            ],
        ];
    }

    /**
     * 根据文件哈希值查询日志
     * 
     * @param string $fileHash 文件哈希值
     * @return self|null 日志实例，不存在返回 null
     */
    public static function findByFileHash(string $fileHash): ?self
    {
        $sql = "SELECT * FROM reading_logs WHERE file_hash = :file_hash LIMIT 1";
        $data = Database::queryOne($sql, ['file_hash' => $fileHash]);

        return $data !== null ? new self($data) : null;
    }

    /**
     * 获取用户日志总数
     * 
     * @param int $userId 用户 ID
     * @param int|null $deviceId 设备 ID（可选）
     * @return int 日志总数
     */
    public static function countByUser(int $userId, ?int $deviceId = null): int
    {
        $where = ['user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($deviceId !== null) {
            $where[] = 'device_id = :device_id';
            $params['device_id'] = $deviceId;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) as count FROM reading_logs WHERE {$whereClause}";
        $result = Database::queryOne($sql, $params);

        return (int)($result['count'] ?? 0);
    }

    /**
     * 获取用户日志总大小
     * 
     * @param int $userId 用户 ID
     * @param int|null $deviceId 设备 ID（可选）
     * @return int 总大小（字节）
     */
    public static function getTotalSizeByUser(int $userId, ?int $deviceId = null): int
    {
        $where = ['user_id = :user_id'];
        $params = ['user_id' => $userId];

        if ($deviceId !== null) {
            $where[] = 'device_id = :device_id';
            $params['device_id'] = $deviceId;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT SUM(file_size) as total_size FROM reading_logs WHERE {$whereClause}";
        $result = Database::queryOne($sql, $params);

        return (int)($result['total_size'] ?? 0);
    }

    /**
     * 删除日志记录
     * 
     * @return bool 是否成功
     */
    public function delete(): bool
    {
        if ($this->id === null) {
            return false;
        }

        // 删除物理文件
        if ($this->filePath !== null && file_exists($this->filePath)) {
            unlink($this->filePath);
            Logger::info('Deleted log file', ['file_path' => $this->filePath]);
        }

        // 删除数据库记录
        $sql = "DELETE FROM reading_logs WHERE id = :id";
        $result = Database::execute($sql, ['id' => $this->id]);

        if ($result > 0) {
            Logger::info('Deleted reading log', ['log_id' => $this->id]);
        }

        return $result > 0;
    }

    /**
     * 批量删除日志记录
     * 
     * @param array $logIds 日志 ID 数组
     * @return int 删除的记录数
     */
    public static function deleteMultiple(array $logIds): int
    {
        if (empty($logIds)) {
            return 0;
        }

        // 查询日志信息以删除物理文件
        $placeholders = implode(',', array_fill(0, count($logIds), '?'));
        $sql = "SELECT * FROM reading_logs WHERE id IN ({$placeholders})";
        $logs = Database::query($sql, $logIds);

        // 删除物理文件
        foreach ($logs as $log) {
            if (!empty($log['file_path']) && file_exists($log['file_path'])) {
                unlink($log['file_path']);
            }
        }

        // 删除数据库记录
        $sql = "DELETE FROM reading_logs WHERE id IN ({$placeholders})";
        $result = Database::execute($sql, $logIds);

        Logger::info('Deleted multiple reading logs', ['count' => $result]);

        return $result;
    }

    /**
     * 获取日志 ID
     * 
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * 获取用户 ID
     * 
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * 获取设备 ID
     * 
     * @return int|null
     */
    public function getDeviceId(): ?int
    {
        return $this->deviceId;
    }

    /**
     * 获取文件路径
     * 
     * @return string|null
     */
    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    /**
     * 获取文件名
     * 
     * @return string|null
     */
    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    /**
     * 获取文件大小
     * 
     * @return int|null
     */
    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    /**
     * 获取文件哈希值
     * 
     * @return string|null
     */
    public function getFileHash(): ?string
    {
        return $this->fileHash;
    }

    /**
     * 获取上传时间
     * 
     * @return string|null
     */
    public function getUploadAt(): ?string
    {
        return $this->uploadAt;
    }

    /**
     * 转换为数组
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'device_id' => $this->deviceId,
            'file_path' => $this->filePath,
            'file_name' => $this->fileName,
            'file_size' => $this->fileSize,
            'file_hash' => $this->fileHash,
            'upload_at' => $this->uploadAt,
        ];
    }

    /**
     * 转换为详细数组（包含设备信息）
     * 
     * @return array
     */
    public function toDetailArray(): array
    {
        $data = $this->toArray();

        // 获取设备信息
        if ($this->deviceId !== null) {
            $device = Device::findById($this->deviceId);
            if ($device !== null) {
                $data['device'] = [
                    'id' => $device->getId(),
                    'device_id' => $device->getDeviceId(),
                    'device_name' => $device->getDeviceName(),
                ];
            }
        }

        return $data;
    }
}