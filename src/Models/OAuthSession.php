<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - OAuth 会话模型
 * ============================================
 * 
 * @package KindleReading\Models
 * @version 1.0.0
 */

namespace KindleReading\Models;

use KindleReading\Core\Database;
use KindleReading\Core\Logger;

/**
 * OAuth 会话模型
 * 
 * 管理 OAuth 认证会话
 */
class OAuthSession
{
    /**
     * 会话 ID
     * 
     * @var int|null
     */
    private ?int $id = null;

    /**
     * 会话令牌
     * 
     * @var string|null
     */
    private ?string $sessionToken = null;

    /**
     * 设备 ID
     * 
     * @var string|null
     */
    private ?string $deviceId = null;

    /**
     * OAuth state 参数
     * 
     * @var string|null
     */
    private ?string $state = null;

    /**
     * 会话状态
     * 
     * @var string|null
     */
    private ?string $status = null;

    /**
     * 用户 ID
     * 
     * @var int|null
     */
    private ?int $userId = null;

    /**
     * 创建时间
     * 
     * @var string|null
     */
    private ?string $createdAt = null;

    /**
     * 过期时间
     * 
     * @var string|null
     */
    private ?string $expiresAt = null;

    /**
     * 完成时间
     * 
     * @var string|null
     */
    private ?string $completedAt = null;

    /**
     * 构造函数
     * 
     * @param array|null $data 会话数据
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
     * @param array $data 会话数据
     * @return void
     */
    private function fill(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->sessionToken = $data['session_token'] ?? null;
        $this->deviceId = $data['device_id'] ?? null;
        $this->state = $data['state'] ?? null;
        $this->status = $data['status'] ?? null;
        $this->userId = $data['user_id'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->expiresAt = $data['expires_at'] ?? null;
        $this->completedAt = $data['completed_at'] ?? null;
    }

    /**
     * 创建 OAuth 会话
     * 
     * @param array $data 会话数据
     * @return int 会话 ID
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO oauth_sessions (
            session_token, device_id, state, status, user_id, expires_at
        ) VALUES (
            :session_token, :device_id, :state, :status, :user_id, :expires_at
        )";

        $params = [
            'session_token' => $data['session_token'],
            'device_id' => $data['device_id'],
            'state' => $data['state'],
            'status' => $data['status'] ?? 'pending',
            'user_id' => $data['user_id'] ?? null,
            'expires_at' => $data['expires_at'],
        ];

        return (int)Database::insert($sql, $params);
    }

    /**
     * 根据 session_token 查询会话
     * 
     * @param string $sessionToken 会话令牌
     * @return self|null 会话实例，不存在返回 null
     */
    public static function findBySessionToken(string $sessionToken): ?self
    {
        $sql = "SELECT * FROM oauth_sessions WHERE session_token = :session_token LIMIT 1";
        $data = Database::queryOne($sql, ['session_token' => $sessionToken]);

        return $data !== null ? new self($data) : null;
    }

    /**
     * 根据 state 查询会话
     * 
     * @param string $state OAuth state 参数
     * @return self|null 会话实例，不存在返回 null
     */
    public static function findByState(string $state): ?self
    {
        $sql = "SELECT * FROM oauth_sessions WHERE state = :state LIMIT 1";
        $data = Database::queryOne($sql, ['state' => $state]);

        return $data !== null ? new self($data) : null;
    }

    /**
     * 根据 ID 查询会话
     * 
     * @param int $id 会话 ID
     * @return self|null 会话实例，不存在返回 null
     */
    public static function findById(int $id): ?self
    {
        $sql = "SELECT * FROM oauth_sessions WHERE id = :id LIMIT 1";
        $data = Database::queryOne($sql, ['id' => $id]);

        return $data !== null ? new self($data) : null;
    }

    /**
     * 更新会话状态
     * 
     * @param string $status 新状态
     * @param int|null $userId 用户 ID（可选）
     * @return bool 是否成功
     */
    public function updateStatus(string $status, ?int $userId = null): bool
    {
        $sql = "UPDATE oauth_sessions SET 
            status = :status,
            user_id = :user_id,
            completed_at = :completed_at
        WHERE id = :id";

        $params = [
            'status' => $status,
            'user_id' => $userId ?? $this->userId,
            'completed_at' => $status === 'authorized' || $status === 'completed' ? date('Y-m-d H:i:s') : null,
            'id' => $this->id,
        ];

        $result = Database::execute($sql, $params);

        if ($result > 0) {
            $this->status = $status;
            $this->userId = $userId ?? $this->userId;
            if ($status === 'authorized' || $status === 'completed') {
                $this->completedAt = date('Y-m-d H:i:s');
            }
        }

        return $result > 0;
    }

    /**
     * 检查会话是否过期
     * 
     * @return bool 是否过期
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return true;
        }

        $expiresTime = strtotime($this->expiresAt);
        return $expiresTime < time();
    }

    /**
     * 清理过期会话
     * 
     * @return int 清理的会话数量
     */
    public static function cleanupExpired(): int
    {
        $sql = "UPDATE oauth_sessions SET status = 'expired' 
                WHERE expires_at < NOW() AND status IN ('pending', 'authorized')";
        
        $result = Database::execute($sql);
        
        Logger::info('Cleaned up expired OAuth sessions', ['count' => $result]);
        
        return $result;
    }

    /**
     * 删除会话
     * 
     * @return bool 是否成功
     */
    public function delete(): bool
    {
        if ($this->id === null) {
            return false;
        }

        $sql = "DELETE FROM oauth_sessions WHERE id = :id";
        $result = Database::execute($sql, ['id' => $this->id]);

        return $result > 0;
    }

    /**
     * 获取会话 ID
     * 
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * 获取会话令牌
     * 
     * @return string|null
     */
    public function getSessionToken(): ?string
    {
        return $this->sessionToken;
    }

    /**
     * 获取设备 ID
     * 
     * @return string|null
     */
    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    /**
     * 获取 state 参数
     * 
     * @return string|null
     */
    public function getState(): ?string
    {
        return $this->state;
    }

    /**
     * 获取会话状态
     * 
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->status;
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
     * 获取创建时间
     * 
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    /**
     * 获取过期时间
     * 
     * @return string|null
     */
    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    /**
     * 获取完成时间
     * 
     * @return string|null
     */
    public function getCompletedAt(): ?string
    {
        return $this->completedAt;
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
            'session_token' => $this->sessionToken,
            'device_id' => $this->deviceId,
            'state' => $this->state,
            'status' => $this->status,
            'user_id' => $this->userId,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
            'completed_at' => $this->completedAt,
        ];
    }
}