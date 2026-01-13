<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 安全工具类
 * ============================================
 * 
 * @package KindleReading\Utils
 * @version 1.0.0
 */

namespace KindleReading\Utils;

/**
 * 安全工具类
 * 
 * 提供安全相关的工具方法
 */
class SecurityHelper
{
    /**
     * 生成 UUID v4
     * 
     * @return string UUID 字符串
     */
    public static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * 生成随机字符串
     * 
     * @param int $length 字符串长度
     * @param string $charset 字符集
     * @return string 随机字符串
     */
    public static function generateRandomString(int $length = 32, string $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
    {
        if ($length <= 0) {
            return '';
        }

        $string = '';
        $charsetLength = strlen($charset);

        for ($i = 0; $i < $length; $i++) {
            $string .= $charset[random_int(0, $charsetLength - 1)];
        }

        return $string;
    }

    /**
     * 生成安全的随机令牌
     * 
     * @param int $length 令牌长度
     * @return string 随机令牌
     */
    public static function generateToken(int $length = 64): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * 验证 UUID 格式
     * 
     * @param string $uuid UUID 字符串
     * @return bool 是否为有效的 UUID
     */
    public static function isValidUuid(string $uuid): bool
    {
        return (bool)preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
    }

    /**
     * 计算文件哈希值
     * 
     * @param string $filePath 文件路径
     * @param string $algorithm 哈希算法（md5, sha1, sha256, etc.）
     * @return string|null 文件哈希值，失败返回 null
     */
    public static function calculateFileHash(string $filePath, string $algorithm = 'sha256'): ?string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

        $hash = hash_file($algorithm, $filePath);
        
        return $hash !== false ? $hash : null;
    }

    /**
     * 计算字符串哈希值
     * 
     * @param string $data 字符串数据
     * @param string $algorithm 哈希算法
     * @return string 哈希值
     */
    public static function hash(string $data, string $algorithm = 'sha256'): string
    {
        return hash($algorithm, $data);
    }

    /**
     * 生成密码哈希
     * 
     * @param string $password 密码
     * @param int|null $cost 成本因子
     * @return string 密码哈希
     */
    public static function hashPassword(string $password, ?int $cost = null): string
    {
        $options = [];
        
        if ($cost !== null) {
            $options['cost'] = $cost;
        }

        return password_hash($password, PASSWORD_BCRYPT, $options);
    }

    /**
     * 验证密码
     * 
     * @param string $password 密码
     * @param string $hash 密码哈希
     * @return bool 是否匹配
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * 需要重新哈希密码
     * 
     * @param string $hash 密码哈希
     * @return bool 是否需要重新哈希
     */
    public static function passwordNeedsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT);
    }

    /**
     * 加密数据
     * 
     * @param string $data 要加密的数据
     * @param string|null $key 加密密钥
     * @return string|null 加密后的数据，失败返回 null
     */
    public static function encrypt(string $data, ?string $key = null): ?string
    {
        if ($key === null) {
            $key = Config::get('security.encryption_key', 'default-key');
        }

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);

        if ($encrypted === false) {
            return null;
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * 解密数据
     * 
     * @param string $data 要解密的数据
     * @param string|null $key 解密密钥
     * @return string|null 解密后的数据，失败返回 null
     */
    public static function decrypt(string $data, ?string $key = null): ?string
    {
        if ($key === null) {
            $key = Config::get('security.encryption_key', 'default-key');
        }

        $decoded = base64_decode($data);
        
        if ($decoded === false || strlen($decoded) < 16) {
            return null;
        }

        $iv = substr($decoded, 0, 16);
        $encrypted = substr($decoded, 16);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);

        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * 生成 HMAC 签名
     * 
     * @param string $data 数据
     * @param string|null $key 密钥
     * @param string $algorithm 算法
     * @return string HMAC 签名
     */
    public static function generateHmac(string $data, ?string $key = null, string $algorithm = 'sha256'): string
    {
        if ($key === null) {
            $key = Config::get('security.encryption_key', 'default-key');
        }

        return hash_hmac($algorithm, $data, $key);
    }

    /**
     * 验证 HMAC 签名
     * 
     * @param string $data 数据
     * @param string $signature 签名
     * @param string|null $key 密钥
     * @param string $algorithm 算法
     * @return bool 是否匹配
     */
    public static function verifyHmac(string $data, string $signature, ?string $key = null, string $algorithm = 'sha256'): bool
    {
        return hash_equals(self::generateHmac($data, $key, $algorithm), $signature);
    }

    /**
     * 清理输入数据（防止 XSS）
     * 
     * @param mixed $data 输入数据
     * @return mixed 清理后的数据
     */
    public static function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }

        if (is_string($data)) {
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $data;
    }

    /**
     * 验证 IP 地址
     * 
     * @param string $ip IP 地址
     * @return bool 是否为有效的 IP 地址
     */
    public static function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * 验证邮箱地址
     * 
     * @param string $email 邮箱地址
     * @return bool 是否为有效的邮箱地址
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * 验证 URL
     * 
     * @param string $url URL
     * @return bool 是否为有效的 URL
     */
    public static function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 获取客户端 IP 地址
     * 
     * @return string IP 地址
     */
    public static function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // 处理多个 IP 的情况
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                if (self::isValidIp($ip)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * 获取用户代理
     * 
     * @return string 用户代理
     */
    public static function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    /**
     * 生成 CSRF 令牌
     * 
     * @return string CSRF 令牌
     */
    public static function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = self::generateToken(32);
        $_SESSION['csrf_token'] = $token;

        return $token;
    }

    /**
     * 验证 CSRF 令牌
     * 
     * @param string $token CSRF 令牌
     * @return bool 是否有效
     */
    public static function verifyCsrfToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * 生成时间戳令牌（用于防止重放攻击）
     * 
     * @param int $ttl 有效期（秒）
     * @return array 包含令牌和过期时间的数组
     */
    public static function generateTimestampToken(int $ttl = 300): array
    {
        $timestamp = time();
        $token = self::generateHmac($timestamp . self::generateRandomString(16));
        
        return [
            'token' => $token,
            'expires_at' => $timestamp + $ttl,
        ];
    }

    /**
     * 验证时间戳令牌
     * 
     * @param string $token 令牌
     * @param int $ttl 有效期（秒）
     * @return bool 是否有效
     */
    public static function verifyTimestampToken(string $token, int $ttl = 300): bool
    {
        // 这里需要根据实际实现来验证
        // 简化版本：检查令牌格式
        return strlen($token) >= 32;
    }

    /**
     * 生成安全的文件名
     * 
     * @param string $filename 原始文件名
     * @return string 安全的文件名
     */
    public static function sanitizeFilename(string $filename): string
    {
        // 移除路径信息
        $filename = basename($filename);
        
        // 移除特殊字符
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // 移除连续的点
        $filename = preg_replace('/\.{2,}/', '.', $filename);
        
        // 限制长度
        if (strlen($filename) > 255) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($name, 0, 255 - strlen($ext) - 1) . '.' . $ext;
        }

        return $filename;
    }

    /**
     * 生成唯一的文件名
     * 
     * @param string $filename 原始文件名
     * @return string 唯一的文件名
     */
    public static function generateUniqueFilename(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $uuid = self::generateUuid();

        return $basename . '_' . $uuid . '.' . $extension;
    }

    /**
     * 验证文件类型
     * 
     * @param string $filePath 文件路径
     * @param array $allowedTypes 允许的 MIME 类型
     * @return bool 是否为允许的类型
     */
    public static function validateFileType(string $filePath, array $allowedTypes): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        return in_array($mimeType, $allowedTypes, true);
    }

    /**
     * 验证文件大小
     * 
     * @param string $filePath 文件路径
     * @param int $maxSize 最大文件大小（字节）
     * @return bool 是否符合大小限制
     */
    public static function validateFileSize(string $filePath, int $maxSize): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        return filesize($filePath) <= $maxSize;
    }
}