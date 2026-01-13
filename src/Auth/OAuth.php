<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - OAuth 认证类
 * ============================================
 * 
 * @package KindleReading\Auth
 * @version 1.0.0
 */

namespace KindleReading\Auth;

use KindleReading\Core\Database;
use KindleReading\Core\Logger;
use KindleReading\Models\OAuthSession;
use KindleReading\Models\User;
use KindleReading\Models\Device;
use KindleReading\Utils\SecurityHelper;

/**
 * OAuth 认证类
 * 
 * 处理 GitHub OAuth 2.0 认证流程
 */
class OAuth
{
    /**
     * OAuth 配置
     * 
     * @var array
     */
    private array $config;

    /**
     * 构造函数
     * 
     * @param array|null $config OAuth 配置
     */
    public function __construct(?array $config = null)
    {
        if ($config === null) {
            $this->config = require APP_ROOT . '/config/oauth.php';
        } else {
            $this->config = $config;
        }
    }

    /**
     * 生成 GitHub 授权 URL
     * 
     * @param string $state OAuth state 参数
     * @return string 授权 URL
     */
    public function generateAuthorizeUrl(string $state): string
    {
        $githubConfig = $this->config['github'];
        
        $params = [
            'client_id' => $githubConfig['client_id'],
            'redirect_uri' => $githubConfig['redirect_uri'],
            'scope' => $githubConfig['scope'],
            'state' => $state,
            'response_type' => $githubConfig['response_type'],
        ];

        return $githubConfig['authorize_url'] . '?' . http_build_query($params);
    }

    /**
     * 使用授权码获取 Access Token
     * 
     * @param string $code 授权码
     * @return array|null Access Token 信息，失败返回 null
     */
    public function getAccessToken(string $code): ?array
    {
        $githubConfig = $this->config['github'];
        
        $params = [
            'client_id' => $githubConfig['client_id'],
            'client_secret' => $githubConfig['client_secret'],
            'code' => $code,
            'redirect_uri' => $githubConfig['redirect_uri'],
        ];

        $response = $this->makeRequest($githubConfig['token_url'], $params);
        
        if ($response === null) {
            Logger::error('Failed to get access token from GitHub');
            return null;
        }

        // GitHub 返回格式为 application/x-www-form-urlencoded
        parse_str($response, $data);
        
        if (!isset($data['access_token'])) {
            Logger::error('Invalid access token response from GitHub', ['response' => $response]);
            return null;
        }

        return $data;
    }

    /**
     * 使用 Access Token 获取用户信息
     * 
     * @param string $accessToken Access Token
     * @return array|null 用户信息，失败返回 null
     */
    public function getUserInfo(string $accessToken): ?array
    {
        $githubConfig = $this->config['github'];
        
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'User-Agent: Kindle Reading GTK',
            'Accept: application/vnd.github.v3+json',
        ];

        $response = $this->makeRequest($githubConfig['user_url'], [], $headers);
        
        if ($response === null) {
            Logger::error('Failed to get user info from GitHub');
            return null;
        }

        $userInfo = json_decode($response, true);
        
        if ($userInfo === null || !isset($userInfo['id'])) {
            Logger::error('Invalid user info response from GitHub', ['response' => $response]);
            return null;
        }

        return $userInfo;
    }

    /**
     * 创建或更新用户记录
     * 
     * @param array $githubUserInfo GitHub 用户信息
     * @return User 用户模型实例
     */
    public function createOrUpdateUser(array $githubUserInfo): User
    {
        $githubUid = (string)$githubUserInfo['id'];
        $username = $githubUserInfo['login'] ?? '';
        $avatarUrl = $githubUserInfo['avatar_url'] ?? null;

        // 尝试根据 GitHub UID 查找用户
        $user = User::findByGithubUid($githubUid);

        if ($user === null) {
            // 创建新用户
            $userId = User::create([
                'github_uid' => $githubUid,
                'username' => $username,
                'avatar_url' => $avatarUrl,
            ]);
            
            $user = User::findById($userId);
            Logger::info('Created new user', ['user_id' => $userId, 'github_uid' => $githubUid]);
        } else {
            // 更新用户信息
            $user->update([
                'username' => $username,
                'avatar_url' => $avatarUrl,
            ]);
            
            Logger::info('Updated existing user', ['user_id' => $user->getId(), 'github_uid' => $githubUid]);
        }

        // 更新最后登录时间
        $user->updateLastLogin();

        return $user;
    }

    /**
     * 发起 OAuth 认证请求
     * 
     * @param string $deviceId 设备 ID
     * @return array 包含 session_token 和 auth_url 的数组
     */
    public function initiateAuth(string $deviceId): array
    {
        // 生成 session_token 和 state
        $sessionToken = SecurityHelper::generateUuid();
        $state = SecurityHelper::generateRandomString($this->config['github']['state_length']);
        
        // 计算过期时间
        $expiresAt = date('Y-m-d H:i:s', time() + $this->config['session']['expires_in']);
        
        // 创建 OAuth 会话
        $sessionId = OAuthSession::create([
            'session_token' => $sessionToken,
            'device_id' => $deviceId,
            'state' => $state,
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ]);
        
        // 生成授权 URL
        $authUrl = $this->generateAuthorizeUrl($state);
        
        Logger::info('OAuth auth initiated', [
            'session_id' => $sessionId,
            'session_token' => $sessionToken,
            'device_id' => $deviceId,
        ]);
        
        return [
            'session_token' => $sessionToken,
            'auth_url' => $authUrl,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * 处理 OAuth 回调
     * 
     * @param string $code 授权码
     * @param string $state OAuth state 参数
     * @return array|null 处理结果，失败返回 null
     */
    public function handleCallback(string $code, string $state): ?array
    {
        // 根据 state 查找会话
        $session = OAuthSession::findByState($state);
        
        if ($session === null) {
            Logger::error('OAuth session not found', ['state' => $state]);
            return null;
        }
        
        // 检查会话是否过期
        if ($session->isExpired()) {
            Logger::error('OAuth session expired', ['session_id' => $session->getId()]);
            $session->updateStatus('expired');
            return null;
        }
        
        // 检查会话状态
        if ($session->getStatus() !== 'pending') {
            Logger::error('OAuth session already processed', ['session_id' => $session->getId(), 'status' => $session->getStatus()]);
            return null;
        }
        
        // 获取 Access Token
        $tokenInfo = $this->getAccessToken($code);
        
        if ($tokenInfo === null) {
            Logger::error('Failed to get access token', ['session_id' => $session->getId()]);
            $session->updateStatus('expired');
            return null;
        }
        
        // 获取用户信息
        $userInfo = $this->getUserInfo($tokenInfo['access_token']);
        
        if ($userInfo === null) {
            Logger::error('Failed to get user info', ['session_id' => $session->getId()]);
            $session->updateStatus('expired');
            return null;
        }
        
        // 创建或更新用户
        $user = $this->createOrUpdateUser($userInfo);
        
        // 创建或更新设备
        $device = Device::getOrCreate($user->getId(), $session->getDeviceId());
        
        // 生成用户访问令牌
        $userToken = $user->generateAccessToken();
        
        // 更新会话状态
        $session->updateStatus('authorized', $user->getId());
        
        Logger::info('OAuth callback processed successfully', [
            'session_id' => $session->getId(),
            'user_id' => $user->getId(),
            'device_id' => $device->getId(),
        ]);
        
        return [
            'session' => $session,
            'user' => $user,
            'device' => $device,
            'user_token' => $userToken,
        ];
    }

    /**
     * 发起 HTTP 请求
     * 
     * @param string $url 请求 URL
     * @param array $params 请求参数
     * @param array $headers 请求头
     * @return string|null 响应内容，失败返回 null
     */
    private function makeRequest(string $url, array $params = [], array $headers = []): ?string
    {
        $ch = curl_init();
        
        if (!empty($params)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($error !== '') {
            Logger::error('CURL request failed', [
                'url' => $url,
                'error' => $error,
                'http_code' => $httpCode,
            ]);
            return null;
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            Logger::error('HTTP request failed', [
                'url' => $url,
                'http_code' => $httpCode,
                'response' => $response,
            ]);
            return null;
        }
        
        return $response;
    }
}