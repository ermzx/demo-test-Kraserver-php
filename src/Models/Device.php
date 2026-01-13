<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 设备模型
 * ============================================
 * 
 * @package KindleReading\Models
 * @version 1.0.0
 */

namespace KindleReading\Models;

use KindleReading\Core\Database;
use KindleReading\Core\Logger;

/**
 * 设备模型
 * 
 * 管理 Kindle 设备信息
 */
class Device
{
    /**
     * 设备 ID
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
     * Kindle 设备唯一标识
     * 
     * @var string|null
     */
    private ?string $deviceId = null;

    /**
     * 设备名称
     * 
     * @var string|null
     */
    private ?string $deviceName = null;

    /**
     * 绑定时间
     * 
     * @var string|null
     */
    private ?string $createdAt = null;

    /**
     * 最后同步时间
     * 
     * @var string|null
     */
    private ?string $lastSyncAt = null;

    /**
     * 构造函数
     * 
     * @param array|null $data 设备数据
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
     * @param array $data 设备数据
     * @return void
     */
    private function fill(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->userId = $data['user_id'] ?? null;
        $this->deviceId = $data['device_id'] ?? null;
        $this->deviceName = $data['device_name'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->lastSyncAt = $data['last_sync_at'] ?? null;
    }

    /**
     * 创建设备
     * 
     * @param array $data 设备数据
     * @return int 设备 ID
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO kindle_devices (
            user_id, device_id, device_name
        ) VALUES (
            :user_id, :device_id, :device_name
        )";

        $params = [
            'user_id' => $data['user_id'],
            'device_id' => $data['device_id'],
            'device_name' => $data['device_name'] ?? null,
        ];

        return (int)Database::insert($sql, $params);
    }

    /**
     * 获取或创建设备
     * 
     * @param int $userId 用户 ID
     * @param string $deviceId Kindle 设备 ID
     * @return self 设备实例
     */
    public static function getOrCreate(int $userId, string $deviceId): self
    {
        $device = self::findByDeviceId($deviceId);

        if ($device === null) {
            $deviceIdInt = self::create([
                'user_id' => $userId,
                'device_id' => $deviceId,
            ]);
            $device = self::findById($deviceIdInt);
            Logger::info('Created new device', ['device_id' => $deviceIdInt, 'user_id' => $userId]);
        } elseif ($device->getUserId() !== $userId) {
            // 设备已绑定到其他用户，更新用户 ID
            $device->update(['user_id' => $userId]);
            Logger::info('Updated device user', ['device_id' => $device->getId(), 'user_id' => $userId]);
        }

        return $device;
    }

    /**
     * 根据 ID 查询设备
     * 
     * @param int $id 设备 ID
     * @return self|null 设备实例，不存在返回 null
     */
    public static function findById(int $id): ?self
    {
        $sql = "SELECT * FROM kindle_devices WHERE id = :id LIMIT 1";
        $data = Database::queryOne($sql, ['id' => $id]);

        return $data !== null ? new self($data) : null;
    }

    /**
     * 根据 device_id 查询设备
     * 
     * @param string $deviceId Kindle 设备 ID
     * @return self|null 设备实例，不存在返回 null
     */
    public static function findByDeviceId(string $deviceId): ?self
    {
        $sql = "SELECT * FROM kindle_devices WHERE device_id = :device_id LIMIT 1";
        $data = Database::queryOne($sql, ['device_id' => $deviceId]);

        return $data !== null ? new self($data) : null;
    }

    /**
     * 根据用户 ID 查询所有设备
     * 
     * @param int $userId 用户 ID
     * @return array 设备实例数组
     */
    public static function findByUserId(int $userId): array
    {
        $sql = "SELECT * FROM kindle_devices WHERE user_id = :user_id ORDER BY created_at DESC";
        $results = Database::query($sql, ['user_id' => $userId]);

        return array_map(function ($data) {
            return new self($data);
        }, $results);
    }

    /**
     * 更新设备信息
     * 
     * @param array $data 更新数据
     * @return bool 是否成功
     */
    public function update(array $data): bool
    {
        if ($this->id === null) {
            return false;
        }

        $fields = [];
        $params = ['id' => $this->id];

        if (isset($data['user_id'])) {
            $fields[] = 'user_id = :user_id';
            $params['user_id'] = $data['user_id'];
        }

        if (isset($data['device_name'])) {
            $fields[] = 'device_name = :device_name';
            $params['device_name'] = $data['device_name'];
        }

        if (isset($data['last_sync_at'])) {
            $fields[] = 'last_sync_at = :last_sync_at';
            $params['last_sync_at'] = $data['last_sync_at'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE kindle_devices SET " . implode(', ', $fields) . " WHERE id = :id";
        $result = Database::execute($sql, $params);

        if ($result > 0) {
            if (isset($data['user_id'])) {
                $this->userId = $data['user_id'];
            }
            if (isset($data['device_name'])) {
                $this->deviceName = $data['device_name'];
            }
            if (isset($data['last_sync_at'])) {
                $this->lastSyncAt = $data['last_sync_at'];
            }
        }

        return $result > 0;
    }

    /**
     * 更新设备最后同步时间
     * 
     * @return bool 是否成功
     */
    public function updateLastSync(): bool
    {
        if ($this->id === null) {
            return false;
        }

        $sql = "UPDATE kindle_devices SET last_sync_at = NOW() WHERE id = :id";
        $result = Database::execute($sql, ['id' => $this->id]);

        if ($result > 0) {
            $this->lastSyncAt = date('Y-m-d H:i:s');
        }

        return $result > 0;
    }

    /**
     * 删除设备
     * 
     * @return bool 是否成功
     */
    public function delete(): bool
    {
        if ($this->id === null) {
            return false;
        }

        $sql = "DELETE FROM kindle_devices WHERE id = :id";
        $result = Database::execute($sql, ['id' => $this->id]);

        return $result > 0;
    }

    /**
     * 获取设备日志数量
     * 
     * @return int 日志数量
     */
    public function getLogsCount(): int
    {
        if ($this->id === null) {
            return 0;
        }

        $sql = "SELECT COUNT(*) as count FROM reading_logs WHERE device_id = :device_id";
        $result = Database::queryOne($sql, ['device_id' => $this->id]);

        return (int)($result['count'] ?? 0);
    }

    /**
     * 获取设备日志列表
     * 
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 日志列表
     */
    public function getLogs(int $limit = 20, int $offset = 0): array
    {
        if ($this->id === null) {
            return [];
        }

        $sql = "SELECT * FROM reading_logs 
                WHERE device_id = :device_id 
                ORDER BY upload_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = Database::executeStatement($sql, [
            'device_id' => $this->id,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * 获取设备 ID
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
     * 获取 Kindle 设备 ID
     * 
     * @return string|null
     */
    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    /**
     * 获取设备名称
     * 
     * @return string|null
     */
    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    /**
     * 获取绑定时间
     * 
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    /**
     * 获取最后同步时间
     * 
     * @return string|null
     */
    public function getLastSyncAt(): ?string
    {
        return $this->lastSyncAt;
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
            'device_name' => $this->deviceName,
            'created_at' => $this->createdAt,
            'last_sync_at' => $this->lastSyncAt,
        ];
    }
}