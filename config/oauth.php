<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - OAuth 配置文件
 * ============================================
 * 
 * 此文件包含 GitHub OAuth 认证配置
 * 所有配置项都可以通过环境变量覆盖
 */

// 防止直接访问
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// ============================================
// GitHub OAuth 配置
// ============================================
return [
    // OAuth 提供商
    'provider' => 'github',
    
    // GitHub OAuth 配置
    'github' => [
        // GitHub OAuth 应用 Client ID
        // 注意：Kindle 端不需要配置此字段，只需在服务端配置
        'client_id' => getenv('GITHUB_CLIENT_ID') ?: '',
        
        // GitHub OAuth 应用 Client Secret
        // 注意：Kindle 端不需要配置此字段，只需在服务端配置
        'client_secret' => getenv('GITHUB_CLIENT_SECRET') ?: '',
        
        // OAuth 回调 URL
        'redirect_uri' => getenv('GITHUB_REDIRECT_URI') ?: 'https://your-domain.com/api/callback.php',
        
        // GitHub OAuth 授权端点
        'authorize_url' => 'https://github.com/login/oauth/authorize',
        
        // GitHub OAuth 令牌端点
        'token_url' => 'https://github.com/login/oauth/access_token',
        
        // GitHub 用户信息端点
        'user_url' => 'https://api.github.com/user',
        
        // OAuth 授权范围
        'scope' => 'read:user user:email',
        
        // OAuth 响应类型
        'response_type' => 'code',
        
        // OAuth 状态参数长度
        'state_length' => 32,
    ],
    
    // ============================================
    // OAuth 会话配置
    // ============================================
    'session' => [
        // 会话令牌长度（字节）
        'token_length' => 32,
        
        // 会话过期时间（秒）
        'expires_in' => (int)(getenv('SESSION_TIMEOUT') ?: 300),
        
        // 会话状态
        'statuses' => [
            'pending' => 'pending',       // 等待授权
            'authorized' => 'authorized', // 已授权
            'completed' => 'completed',   // 已完成
            'expired' => 'expired',       // 已过期
        ],
    ],
    
    // ============================================
    // 用户令牌配置
    // ============================================
    'user_token' => [
        // 用户令牌长度（字节）
        'token_length' => 32,
        
        // 用户令牌有效期（秒）
        'expires_in' => (int)(getenv('USER_TOKEN_LIFETIME') ?: 7200),
        
        // 令牌前缀
        'prefix' => getenv('USER_TOKEN_PREFIX') ?: 'ur_',
    ],
    
    // ============================================
    // OAuth 流程配置
    // ============================================
    'flow' => [
        // 是否启用 PKCE（Proof Key for Code Exchange）
        'pkce_enabled' => false,
        
        // PKCE 代码挑战方法
        'code_challenge_method' => 'S256',
        
        // 是否启用状态参数验证
        'state_validation' => true,
        
        // 是否启用 HTTPS 重定向
        'https_redirect' => true,
    ],
    
    // ============================================
    // OAuth 错误处理配置
    // ============================================
    'errors' => [
        // 错误码映射
        'error_codes' => [
            'invalid_request' => 400,
            'unauthorized_client' => 401,
            'access_denied' => 403,
            'unsupported_response_type' => 400,
            'invalid_scope' => 400,
            'server_error' => 500,
            'temporarily_unavailable' => 503,
        ],
        
        // 错误消息
        'error_messages' => [
            'invalid_request' => '请求参数无效',
            'unauthorized_client' => '未授权的客户端',
            'access_denied' => '访问被拒绝',
            'unsupported_response_type' => '不支持的响应类型',
            'invalid_scope' => '无效的授权范围',
            'server_error' => '服务器错误',
            'temporarily_unavailable' => '服务暂时不可用',
        ],
    ],
];