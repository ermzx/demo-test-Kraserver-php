<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 文件上传服务
 * ============================================
 * 
 * @package KindleReading\Services
 * @version 1.0.0
 */

namespace KindleReading\Services;

use KindleReading\Core\Config;
use KindleReading\Core\Logger;
use KindleReading\Models\ReadingLog;
use KindleReading\Models\Device;
use KindleReading\Utils\FileHelper;
use KindleReading\Utils\SecurityHelper;

/**
 * 文件上传服务
 * 
 * 处理文件上传、验证和存储
 */
class UploadService
{
    /**
     * 允许的文件扩展名
     * 
     * @var array
     */
    private array $allowedExtensions;

    /**
     * 最大文件大小（字节）
     * 
     * @var int
     */
    private int $maxFileSize;

    /**
     * 允许的 MIME 类型
     * 
     * @var array
     */
    private array $allowedMimeTypes;

    /**
     * 上传目录
     * 
     * @var string
     */
    private string $uploadPath;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $uploadConfig = Config::getUploadConfig();
        
        $this->allowedExtensions = $uploadConfig['allowed_extensions'] ?? ['log', 'txt'];
        $this->maxFileSize = $uploadConfig['max_file_size'] ?? 104857600; // 默认 100MB
        $this->uploadPath = $uploadConfig['upload_path'] ?? APP_ROOT . '/public/uploads';
        
        // 允许的 MIME 类型
        $this->allowedMimeTypes = [
            'text/plain',
            'text/x-log',
            'application/octet-stream',
        ];
    }

    /**
     * 上传单个文件
     * 
     * @param array $file $_FILES 数组中的文件项
     * @param int $userId 用户 ID
     * @param int $deviceId 设备 ID
     * @return array 上传结果
     */
    public function uploadFile(array $file, int $userId, int $deviceId): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'log_id' => null,
            'file_name' => '',
            'file_size' => 0,
        ];

        try {
            // 验证文件上传
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                $result['message'] = $validation['message'];
                return $result;
            }

            // 验证设备归属
            $device = Device::findById($deviceId);
            if ($device === null || $device->getUserId() !== $userId) {
                $result['message'] = 'Device not found or does not belong to user';
                return $result;
            }

            // 计算文件哈希值
            $fileHash = SecurityHelper::calculateFileHash($file['tmp_name'], 'sha256');
            if ($fileHash === null) {
                $result['message'] = 'Failed to calculate file hash';
                return $result;
            }

            // 检查是否已存在相同哈希的文件
            $existingLog = ReadingLog::findByFileHash($fileHash);
            if ($existingLog !== null) {
                Logger::info('File already exists', [
                    'file_hash' => $fileHash,
                    'existing_log_id' => $existingLog->getId(),
                ]);
                $result['success'] = true;
                $result['message'] = 'File already exists';
                $result['log_id'] = $existingLog->getId();
                $result['file_name'] = $existingLog->getFileName();
                $result['file_size'] = $existingLog->getFileSize();
                return $result;
            }

            // 生成唯一文件名
            $uniqueFilename = $this->generateUniqueFilename($file['name']);
            
            // 确保上传目录存在
            if (!FileHelper::ensureDirectoryExists($this->uploadPath)) {
                $result['message'] = 'Failed to create upload directory';
                return $result;
            }

            // 移动文件到上传目录
            $filePath = $this->uploadPath . '/' . $uniqueFilename;
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                $result['message'] = 'Failed to move uploaded file';
                return $result;
            }

            // 保存文件信息到数据库
            $logId = ReadingLog::create([
                'user_id' => $userId,
                'device_id' => $deviceId,
                'file_path' => $filePath,
                'file_name' => $file['name'],
                'file_size' => $file['size'],
                'file_hash' => $fileHash,
            ]);

            // 更新设备最后同步时间
            $device->updateLastSync();

            $result['success'] = true;
            $result['message'] = 'File uploaded successfully';
            $result['log_id'] = $logId;
            $result['file_name'] = $file['name'];
            $result['file_size'] = $file['size'];

            Logger::info('File uploaded successfully', [
                'log_id' => $logId,
                'user_id' => $userId,
                'device_id' => $deviceId,
                'file_name' => $file['name'],
                'file_size' => $file['size'],
            ]);

        } catch (\Exception $e) {
            Logger::error('File upload failed', [
                'error' => $e->getMessage(),
                'file_name' => $file['name'] ?? 'unknown',
            ]);
            $result['message'] = 'File upload failed: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * 上传多个文件
     * 
     * @param array $files $_FILES 数组中的文件项（支持多文件）
     * @param int $userId 用户 ID
     * @param int $deviceId 设备 ID
     * @return array 上传结果
     */
    public function uploadMultipleFiles(array $files, int $userId, int $deviceId): array
    {
        $result = [
            'success' => true,
            'message' => '',
            'uploaded_files' => 0,
            'failed_files' => 0,
            'total_size' => 0,
            'results' => [],
        ];

        // 处理多文件上传
        $fileList = $this->normalizeFilesArray($files);

        foreach ($fileList as $file) {
            $uploadResult = $this->uploadFile($file, $userId, $deviceId);
            
            $result['results'][] = $uploadResult;

            if ($uploadResult['success']) {
                $result['uploaded_files']++;
                $result['total_size'] += $uploadResult['file_size'];
            } else {
                $result['failed_files']++;
                $result['success'] = false;
            }
        }

        if ($result['uploaded_files'] > 0) {
            $result['message'] = sprintf(
                'Uploaded %d file(s), %d failed',
                $result['uploaded_files'],
                $result['failed_files']
            );
        } else {
            $result['message'] = 'No files uploaded';
            $result['success'] = false;
        }

        return $result;
    }

    /**
     * 验证文件
     * 
     * @param array $file 文件数组
     * @return array 验证结果
     */
    private function validateFile(array $file): array
    {
        // 检查上传错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'message' => $this->getUploadErrorMessage($file['error']),
            ];
        }

        // 检查是否为上传的文件
        if (!is_uploaded_file($file['tmp_name'])) {
            return [
                'valid' => false,
                'message' => 'Invalid file upload',
            ];
        }

        // 检查文件是否存在
        if (!file_exists($file['tmp_name'])) {
            return [
                'valid' => false,
                'message' => 'File does not exist',
            ];
        }

        // 检查文件大小
        if ($file['size'] > $this->maxFileSize) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'File size exceeds maximum limit of %s',
                    FileHelper::formatFileSize($this->maxFileSize)
                ),
            ];
        }

        // 检查文件扩展名
        $extension = FileHelper::getExtension($file['name']);
        if (!in_array($extension, $this->allowedExtensions, true)) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'File type not allowed. Allowed types: %s',
                    implode(', ', $this->allowedExtensions)
                ),
            ];
        }

        // 检查文件 MIME 类型
        $mimeType = $this->detectMimeType($file['tmp_name']);
        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'File MIME type not allowed. Detected: %s',
                    $mimeType
                ),
            ];
        }

        // 检查文件内容（防止 webshell 上传）
        if (!$this->validateFileContent($file['tmp_name'])) {
            return [
                'valid' => false,
                'message' => 'File content validation failed',
            ];
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * 检测文件 MIME 类型
     * 
     * @param string $filePath 文件路径
     * @return string MIME 类型
     */
    private function detectMimeType(string $filePath): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);
        
        return $mimeType ?: 'application/octet-stream';
    }

    /**
     * 验证文件内容（防止 webshell 上传）
     * 
     * @param string $filePath 文件路径
     * @return bool 是否有效
     */
    private function validateFileContent(string $filePath): bool
    {
        // 读取文件前 1024 字节进行检测
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $content = fread($handle, 1024);
        fclose($handle);

        // 检查是否包含可疑的 PHP 标签
        $suspiciousPatterns = [
            '/<\?php/i',
            '/<\?=/i',
            '/<\s*\?/i',
            '/<script[^>]*>.*?<\/script>/is',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/passthru\s*\(/i',
            '/shell_exec\s*\(/i',
            '/base64_decode\s*\(/i',
            '/assert\s*\(/i',
            '/create_function\s*\(/i',
            '/preg_replace.*\/e/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                Logger::warning('Suspicious file content detected', [
                    'file_path' => $filePath,
                    'pattern' => $pattern,
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * 生成唯一文件名
     * 
     * @param string $originalFilename 原始文件名
     * @return string 唯一文件名
     */
    private function generateUniqueFilename(string $originalFilename): string
    {
        $extension = FileHelper::getExtension($originalFilename);
        $uuid = SecurityHelper::generateUuid();
        
        return $uuid . '.' . $extension;
    }

    /**
     * 规范化文件数组（处理多文件上传）
     * 
     * @param array $files $_FILES 数组
     * @return array 规范化后的文件数组
     */
    private function normalizeFilesArray(array $files): array
    {
        $normalized = [];

        if (!isset($files['name'])) {
            return $normalized;
        }

        // 单文件上传
        if (!is_array($files['name'])) {
            return [$files];
        }

        // 多文件上传
        $fileCount = count($files['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            $normalized[] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
        }

        return $normalized;
    }

    /**
     * 获取上传错误消息
     * 
     * @param int $errorCode 上传错误代码
     * @return string 错误消息
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
        ];

        return $errors[$errorCode] ?? 'Unknown upload error';
    }

    /**
     * 获取允许的文件扩展名
     * 
     * @return array
     */
    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    /**
     * 获取最大文件大小
     * 
     * @return int
     */
    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /**
     * 获取上传目录
     * 
     * @return string
     */
    public function getUploadPath(): string
    {
        return $this->uploadPath;
    }
}