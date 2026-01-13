<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 响应类
 * ============================================
 * 
 * @package KindleReading\Core
 * @version 1.0.0
 */

namespace KindleReading\Core;

/**
 * 响应类
 * 
 * 提供统一的 JSON 响应格式
 */
class Response
{
    /**
     * HTTP 状态码
     * 
     * @var int
     */
    private int $statusCode;

    /**
     * 响应头
     * 
     * @var array
     */
    private array $headers;

    /**
     * 构造函数
     * 
     * @param int $statusCode HTTP 状态码
     */
    public function __construct(int $statusCode = 200)
    {
        $this->statusCode = $statusCode;
        $this->headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Powered-By' => 'Kindle Reading GTK',
        ];
    }

    /**
     * 设置 HTTP 状态码
     * 
     * @param int $code HTTP 状态码
     * @return self
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * 获取 HTTP 状态码
     * 
     * @return int HTTP 状态码
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 设置响应头
     * 
     * @param string $name 头名称
     * @param string $value 头值
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * 设置多个响应头
     * 
     * @param array $headers 头数组
     * @return self
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * 获取响应头
     * 
     * @return array 响应头数组
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * 发送响应头
     * 
     * @return void
     */
    private function sendHeaders(): void
    {
        // 设置 HTTP 状态码
        http_response_code($this->statusCode);

        // 发送响应头
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    /**
     * 发送 JSON 响应
     * 
     * @param array $data 响应数据
     * @param int $statusCode HTTP 状态码
     * @return void
     */
    public function json(array $data, int $statusCode = null): void
    {
        if ($statusCode !== null) {
            $this->setStatusCode($statusCode);
        }

        $this->sendHeaders();
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * 成功响应
     * 
     * @param mixed $data 响应数据
     * @param string $message 成功消息
     * @param int $statusCode HTTP 状态码
     * @return void
     */
    public static function success($data = null, string $message = 'Success', int $statusCode = 200): void
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        $instance = new self($statusCode);
        $instance->json($response);
    }

    /**
     * 错误响应
     * 
     * @param string $message 错误消息
     * @param int $statusCode HTTP 状态码
     * @param mixed $errors 错误详情
     * @return void
     */
    public static function error(string $message = 'Error', int $statusCode = 400, $errors = null): void
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        $instance = new self($statusCode);
        $instance->json($response);
    }

    /**
     * 验证错误响应
     * 
     * @param array $errors 验证错误数组
     * @param string $message 错误消息
     * @return void
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): void
    {
        self::error($message, 422, $errors);
    }

    /**
     * 未授权响应
     * 
     * @param string $message 错误消息
     * @return void
     */
    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, 401);
    }

    /**
     * 禁止访问响应
     * 
     * @param string $message 错误消息
     * @return void
     */
    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, 403);
    }

    /**
     * 未找到响应
     * 
     * @param string $message 错误消息
     * @return void
     */
    public static function notFound(string $message = 'Not found'): void
    {
        self::error($message, 404);
    }

    /**
     * 方法不允许响应
     * 
     * @param string $message 错误消息
     * @return void
     */
    public static function methodNotAllowed(string $message = 'Method not allowed'): void
    {
        self::error($message, 405);
    }

    /**
     * 服务器错误响应
     * 
     * @param string $message 错误消息
     * @return void
     */
    public static function serverError(string $message = 'Internal server error'): void
    {
        self::error($message, 500);
    }

    /**
     * 服务不可用响应
     * 
     * @param string $message 错误消息
     * @return void
     */
    public static function serviceUnavailable(string $message = 'Service unavailable'): void
    {
        self::error($message, 503);
    }

    /**
     * 分页响应
     * 
     * @param array $items 数据项
     * @param int $total 总数
     * @param int $page 当前页码
     * @param int $limit 每页数量
     * @param string $message 成功消息
     * @return void
     */
    public static function paginate(array $items, int $total, int $page, int $limit, string $message = 'Success'): void
    {
        $totalPages = (int)ceil($total / $limit);

        $data = [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];

        self::success($data, $message);
    }

    /**
     * 创建成功响应
     * 
     * @param mixed $data 创建的数据
     * @param string $message 成功消息
     * @return void
     */
    public static function created($data = null, string $message = 'Created successfully'): void
    {
        self::success($data, $message, 201);
    }

    /**
     * 更新成功响应
     * 
     * @param mixed $data 更新的数据
     * @param string $message 成功消息
     * @return void
     */
    public static function updated($data = null, string $message = 'Updated successfully'): void
    {
        self::success($data, $message, 200);
    }

    /**
     * 删除成功响应
     * 
     * @param string $message 成功消息
     * @return void
     */
    public static function deleted(string $message = 'Deleted successfully'): void
    {
        self::success(null, $message, 200);
    }

    /**
     * 无内容响应
     * 
     * @return void
     */
    public static function noContent(): void
    {
        $instance = new self(204);
        $instance->sendHeaders();
        exit;
    }

    /**
     * 重定向响应
     * 
     * @param string $url 重定向 URL
     * @param int $statusCode HTTP 状态码
     * @return void
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        header("Location: {$url}", true, $statusCode);
        exit;
    }

    /**
     * 下载文件响应
     * 
     * @param string $filePath 文件路径
     * @param string|null $fileName 下载文件名
     * @return void
     */
    public static function download(string $filePath, ?string $fileName = null): void
    {
        if (!file_exists($filePath)) {
            self::notFound('File not found');
        }

        if ($fileName === null) {
            $fileName = basename($filePath);
        }

        // 获取文件大小
        $fileSize = filesize($filePath);

        // 设置响应头
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // 输出文件
        readfile($filePath);
        exit;
    }

    /**
     * 发送原始响应
     * 
     * @param string $content 响应内容
     * @param string $contentType 内容类型
     * @param int $statusCode HTTP 状态码
     * @return void
     */
    public static function raw(string $content, string $contentType = 'text/plain', int $statusCode = 200): void
    {
        $instance = new self($statusCode);
        $instance->setHeader('Content-Type', $contentType);
        $instance->sendHeaders();
        echo $content;
        exit;
    }
}