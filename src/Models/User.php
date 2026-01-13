<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 用户模型
 * ============================================
 * 
 * @package KindleReading\Models
 * @version 1.0.0
 */

namespace KindleReading\Models;

use KindleReading\Core\Database;
use KindleReading\Core\Logger;
use KindleReading\Utils\SecurityHelper;

/**
 * 用户模型
 * 
 * 管理用户信息和访问令牌
 */
class User
{
    /**
     * 用户 ID
     * 
     * @var int|null
     */
    private ?int $id = null;

    /**
     * GitHub 用户 ID
     * 
     * @var string|null
     */
    private ?string $githubUid = null;

    /**
     * GitHub 用户名
     * 
     * @var string|null
     */
    private ?string $username = null;

    /**
     * GitHub 头像 URL
     * 
     * @var string|null
     */
    private ?string $avatarUrl = null;

    /**
     * 创建时间
     * 
     * @var string|null
     */
    private ?string $createdAt = null;

    /**
     * 最后登录时间
     * 
     * @var string|null
     */
    private ?string $lastLoginAt = null;

    /**
     * 构造函数
     * 
     * @param array|null $data 用户数据
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
     * @param array $data 用户数据
     * @return void
     */
    private function fill(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->githubUid = $data['github_uid'] ?? null;
        $this->username = $data['username'] ?? null;
        $this->avatarUrl = $data['avatar_url'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->lastLoginAt = $data['last_login_at'] ?? null;
    }

    /**
     * 创建用户
     * 
     * @param array $data 用户数据
     * @return int 用户 ID
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO users (
            github_uid, username, avatar_url
        ) VALUES (
            :github_uid, :username, :avatar_url
        )";

        $params = [
            'github_uid' => $data['github_uid'],
            'username' => $data['username'],
            'avatar_url' => $data['avatar_url'] ?? null,
        ];

        return (int)Database::insert($sql, $params);
    }

    /**
     * 根据 ID 查询用户
     * 
     * @param int $id 用户 ID
     * @return self|null 用户实例，不存在返回 null
     */
    public static function findById(int $id): ?self
    {
        $sql = "SELECT * FROM users WHERE id = :id LIMIT 1";
        $data = Database::queryOne($sql, ['id' => $id]);

        return $data !== null ? new self($data) : null;
    }

    /**
     * 根据 GitHub UID 查询用户
     * 
     * @param string $githubUid GitHub 用户 ID
     * @return self|null 用户实例，不存在返回 null
     */
    public static function findByGithubUid(string $githubUid): ?self
    {
        $sql = "SELECT * FROM users WHERE github_uid = :github_uid LIMIT 1";
        $data = Database::queryOne($sql, ['github_uid' => $githubUid]);

        return $data !== null ? new self($data) : null;
    }

    /**
     * 根据 token 查询用户
     * 
     * @param string $token 用户访问令牌
     * @return self|null 用户实例，不存在返回 null
     */
    public static function findByToken(string $token): ?self
    {
        $sql = "SELECT u.* FROM users u
                INNER JOIN user_tokens ut ON u.id = ut.user_id
                WHERE ut.token = :token 
                AND ut.expires_at > NOW()
                AND ut.revoked_at IS NULL
                LIMIT 1";
        
        $data = Database::queryOne($sql, ['token' => $token]);

        return $data !== null ? new self($data) : null;
    }

    /**
     * 更新用户信息
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

        if (isset($data['username'])) {
            $fields[] = 'username = :username';
            $params['username'] = $data['username'];
        }

        if (isset($data['avatar_url'])) {
            $fields[] = 'avatar_url = :avatar_url';
            $params['avatar_url'] = $data['avatar_url'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $result = Database::execute($sql, $params);

        if ($result > 0) {
            if (isset($data['username'])) {
                $this->username = $data['username'];
            }
            if (isset($data['avatar_url'])) {
                $this->avatarUrl = $data['avatar_url'];
            }
        }

        return $result > 0;
    }

    /**
     * 更新最后登录时间
     * 
     * @return bool 是否成功
     */
    public function updateLastLogin(): bool
    {
        if ($this->id === null) {
            return false;
        }

        $sql = "UPDATE users SET last_login_at = NOW() WHERE id = :id";
        $result = Database::execute($sql, ['id' => $this->id]);

        if ($result > 0) {
            $this->lastLoginAt = date('Y-m-d H:i:s');
        }

        return $result > 0;
    }

    /**
     * 生成用户访问令牌
     * 
     * @param int|null $expiresIn 有效期（秒），默认使用配置值
     * @return string 访问令牌
     */
    public function generateAccessToken(?int $expiresIn = null): string
    {
        if ($this->id === null) {
            throw new \Exception('Cannot generate token for user without ID');
        }

        $config = require APP_ROOT . '/config/oauth.php';
        $tokenConfig = $config['user_token'];
        
        $token = $tokenConfig['prefix'] . SecurityHelper::generateToken($tokenConfig['token_length']);
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiresIn ?? $tokenConfig['expires_in']));

        $sql = "INSERT INTO user_tokens (
            user_id, token, expires_at
        ) VALUES (
            :user_id, :token, :expires_at
        )";

        $params = [
            'user_id' => $this->id,
            'token' => $token,
            'expires_at' => $expiresAt,
        ];

        Database::insert($sql, $params);

        Logger::info('Generated access token', ['user_id' => $this->id]);

        return $token;
    }

    /**
     * 撤销用户令牌
     * 
     * @param string $token 访问令牌
     * @return bool 是否成功
     */
    public function revokeToken(string $token): bool
    {
        if ($this->id === null) {
            return false;
        }

        $sql = "UPDATE user_tokens 
                SET revoked_at = NOW() 
                WHERE user_id = :user_id 
                AND token = :token 
                AND revoked_at IS NULL";
        
        $params = [
            'user_id' => $this->id,
            'token' => $token,
        ];

        $result = Database::execute($sql, $params);

        if ($result > 0) {
            Logger::info('Revoked access token', ['user_id' => $this->id]);
        }

        return $result > 0;
    }

    /**
     * 撤销用户所有令牌
     * 
     * @return int 撤销的令牌数量
     */
    public function revokeAllTokens(): int
    {
        if ($this->id === null) {
            return 0;
        }

        $sql = "UPDATE user_tokens 
                SET revoked_at = NOW() 
                WHERE user_id = :user_id 
                AND revoked_at IS NULL";
        
        $result = Database::execute($sql, ['user_id' => $this->id]);

        Logger::info('Revoked all access tokens', ['user_id' => $this->id, 'count' => $result]);

        return $result;
    }

    /**
     * 清理过期令牌
     * 
     * @return int 清理的令牌数量
     */
    public static function cleanupExpiredTokens(): int
    {
        $sql = "DELETE FROM user_tokens WHERE expires_at < NOW()";
        $result = Database::execute($sql);
        
        Logger::info('Cleaned up expired user tokens', ['count' => $result]);
        
        return $result;
    }

    /**
     * 获取用户设备数量
     * 
     * @return int 设备数量
     */
    public function getDevicesCount(): int
    {
        if ($this->id === null) {
            return 0;
        }

        $sql = "SELECT COUNT(*) as count FROM kindle_devices WHERE user_id = :user_id";
        $result = Database::queryOne($sql, ['user_id' => $this->id]);

        return (int)($result['count'] ?? 0);
    }

    /**
     * 获取用户设备列表
     * 
     * @return array 设备列表
     */
    public function getDevices(): array
    {
        if ($this->id === null) {
            return [];
        }

        $sql = "SELECT * FROM kindle_devices WHERE user_id = :user_id ORDER BY created_at DESC";
        $results = Database::query($sql, ['user_id' => $this->id]);

        return array_map(function ($data) {
            return new Device($data);
        }, $results);
    }

    /**
     * 删除用户
     * 
     * @return bool 是否成功
     */
    public function delete(): bool
    {
        if ($this->id === null) {
            return false;
        }

        $sql = "DELETE FROM users WHERE id = :id";
        $result = Database::execute($sql, ['id' => $this->id]);

        return $result > 0;
    }

    /**
     * 获取用户 ID
     * 
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * 获取 GitHub 用户 ID
     * 
     * @return string|null
     */
    public function getGithubUid(): ?string
    {
        return $this->githubUid;
    }

    /**
     * 获取用户名
     * 
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * 获取头像 URL
     * 
     * @return string|null
     */
    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
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
     * 获取最后登录时间
     * 
     * @return string|null
     */
    public function getLastLoginAt(): ?string
    {
        return $this->lastLoginAt;
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
            'github_uid' => $this->githubUid,
            'username' => $this->username,
            'avatar_url' => $this->avatarUrl,
            'created_at' => $this->createdAt,
            'last_login_at' => $this->lastLoginAt,
        ];
    }
}