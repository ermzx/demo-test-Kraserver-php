<?php
/**
 * ============================================
 * Kindle Reading GTK 云同步服务端 - 文件工具类
 * ============================================
 * 
 * @package KindleReading\Utils
 * @version 1.0.0
 */

namespace KindleReading\Utils;

/**
 * 文件工具类
 * 
 * 提供文件操作相关的工具方法
 */
class FileHelper
{
    /**
     * 获取文件扩展名
     * 
     * @param string $filename 文件名
     * @return string 文件扩展名（小写，不包含点）
     */
    public static function getExtension(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        return strtolower($extension);
    }

    /**
     * 获取文件名（不含扩展名）
     * 
     * @param string $filename 文件名
     * @return string 文件名（不含扩展名）
     */
    public static function getBasename(string $filename): string
    {
        return pathinfo($filename, PATHINFO_FILENAME);
    }

    /**
     * 验证文件类型
     * 
     * @param string $filename 文件名
     * @param array $allowedExtensions 允许的扩展名数组
     * @return bool 是否为允许的类型
     */
    public static function validateFileType(string $filename, array $allowedExtensions): bool
    {
        $extension = self::getExtension($filename);
        return in_array($extension, $allowedExtensions, true);
    }

    /**
     * 验证文件 MIME 类型
     * 
     * @param string $filePath 文件路径
     * @param array $allowedMimeTypes 允许的 MIME 类型数组
     * @return bool 是否为允许的 MIME 类型
     */
    public static function validateMimeType(string $filePath, array $allowedMimeTypes): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        return in_array($mimeType, $allowedMimeTypes, true);
    }

    /**
     * 格式化文件大小
     * 
     * @param int $bytes 文件大小（字节）
     * @param int $precision 小数位数
     * @return string 格式化后的文件大小
     */
    public static function formatFileSize(int $bytes, int $precision = 2): string
    {
        if ($bytes < 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * 确保目录存在
     * 
     * @param string $directory 目录路径
     * @param int $mode 目录权限
     * @return bool 是否成功
     */
    public static function ensureDirectoryExists(string $directory, int $mode = 0755): bool
    {
        if (!is_dir($directory)) {
            return mkdir($directory, $mode, true);
        }

        return true;
    }

    /**
     * 删除目录及其内容
     * 
     * @param string $directory 目录路径
     * @return bool 是否成功
     */
    public static function deleteDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        $files = array_diff(scandir($directory), ['.', '..']);

        foreach ($files as $file) {
            $path = $directory . '/' . $file;

            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($directory);
    }

    /**
     * 清空目录
     * 
     * @param string $directory 目录路径
     * @return bool 是否成功
     */
    public static function emptyDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        $files = array_diff(scandir($directory), ['.', '..']);

        foreach ($files as $file) {
            $path = $directory . '/' . $file;

            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return true;
    }

    /**
     * 复制目录
     * 
     * @param string $source 源目录
     * @param string $destination 目标目录
     * @return bool 是否成功
     */
    public static function copyDirectory(string $source, string $destination): bool
    {
        if (!is_dir($source)) {
            return false;
        }

        self::ensureDirectoryExists($destination);

        $files = scandir($source);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourcePath = $source . '/' . $file;
            $destPath = $destination . '/' . $file;

            if (is_dir($sourcePath)) {
                self::copyDirectory($sourcePath, $destPath);
            } else {
                copy($sourcePath, $destPath);
            }
        }

        return true;
    }

    /**
     * 移动文件
     * 
     * @param string $source 源文件路径
     * @param string $destination 目标文件路径
     * @return bool 是否成功
     */
    public static function moveFile(string $source, string $destination): bool
    {
        // 确保目标目录存在
        $destinationDir = dirname($destination);
        self::ensureDirectoryExists($destinationDir);

        return rename($source, $destination);
    }

    /**
     * 获取目录大小
     * 
     * @param string $directory 目录路径
     * @return int 目录大小（字节）
     */
    public static function getDirectorySize(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $size = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * 获取目录中的文件列表
     * 
     * @param string $directory 目录路径
     * @param bool $recursive 是否递归
     * @param array $extensions 文件扩展名过滤（空数组表示所有文件）
     * @return array 文件路径数组
     */
    public static function getFiles(string $directory, bool $recursive = false, array $extensions = []): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];

        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    if (empty($extensions) || in_array(self::getExtension($file->getFilename()), $extensions, true)) {
                        $files[] = $file->getPathname();
                    }
                }
            }
        } else {
            $iterator = new \DirectoryIterator($directory);

            foreach ($iterator as $file) {
                if ($file->isFile() && !$file->isDot()) {
                    if (empty($extensions) || in_array(self::getExtension($file->getFilename()), $extensions, true)) {
                        $files[] = $file->getPathname();
                    }
                }
            }
        }

        return $files;
    }

    /**
     * 获取目录中的子目录列表
     * 
     * @param string $directory 目录路径
     * @return array 子目录路径数组
     */
    public static function getDirectories(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $directories = [];
        $iterator = new \DirectoryIterator($directory);

        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isDot()) {
                $directories[] = $item->getPathname();
            }
        }

        return $directories;
    }

    /**
     * 计算文件哈希值
     * 
     * @param string $filePath 文件路径
     * @param string $algorithm 哈希算法
     * @return string|null 文件哈希值，失败返回 null
     */
    public static function calculateHash(string $filePath, string $algorithm = 'sha256'): ?string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

        $hash = hash_file($algorithm, $filePath);
        
        return $hash !== false ? $hash : null;
    }

    /**
     * 生成唯一的文件名
     * 
     * @param string $filename 原始文件名
     * @param string $directory 目录路径
     * @return string 唯一的文件名
     */
    public static function generateUniqueFilename(string $filename, string $directory = ''): string
    {
        $extension = self::getExtension($filename);
        $basename = self::getBasename($filename);
        $counter = 1;
        $newFilename = $filename;

        if (!empty($directory)) {
            while (file_exists($directory . '/' . $newFilename)) {
                $newFilename = $basename . '_' . $counter . '.' . $extension;
                $counter++;
            }
        }

        return $newFilename;
    }

    /**
     * 上传文件
     * 
     * @param array $file $_FILES 数组中的文件项
     * @param string $destination 目标目录
     * @param array $allowedExtensions 允许的扩展名
     * @param int $maxFileSize 最大文件大小（字节）
     * @return array 包含成功状态和文件信息的数组
     */
    public static function uploadFile(array $file, string $destination, array $allowedExtensions = [], int $maxFileSize = 0): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'file_path' => '',
            'file_name' => '',
            'file_size' => 0,
        ];

        // 检查上传错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['message'] = self::getUploadErrorMessage($file['error']);
            return $result;
        }

        // 检查文件是否存在
        if (!is_uploaded_file($file['tmp_name'])) {
            $result['message'] = 'Invalid file upload';
            return $result;
        }

        // 检查文件大小
        if ($maxFileSize > 0 && $file['size'] > $maxFileSize) {
            $result['message'] = 'File size exceeds maximum limit';
            return $result;
        }

        // 检查文件扩展名
        if (!empty($allowedExtensions) && !self::validateFileType($file['name'], $allowedExtensions)) {
            $result['message'] = 'File type not allowed';
            return $result;
        }

        // 确保目标目录存在
        if (!self::ensureDirectoryExists($destination)) {
            $result['message'] = 'Failed to create destination directory';
            return $result;
        }

        // 生成唯一文件名
        $filename = self::generateUniqueFilename($file['name'], $destination);
        $filePath = $destination . '/' . $filename;

        // 移动文件
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $result['success'] = true;
            $result['message'] = 'File uploaded successfully';
            $result['file_path'] = $filePath;
            $result['file_name'] = $filename;
            $result['file_size'] = $file['size'];
        } else {
            $result['message'] = 'Failed to move uploaded file';
        }

        return $result;
    }

    /**
     * 获取上传错误消息
     * 
     * @param int $errorCode 上传错误代码
     * @return string 错误消息
     */
    private static function getUploadErrorMessage(int $errorCode): string
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
     * 读取文件内容
     * 
     * @param string $filePath 文件路径
     * @return string|null 文件内容，失败返回 null
     */
    public static function readFile(string $filePath): ?string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        
        return $content !== false ? $content : null;
    }

    /**
     * 写入文件内容
     * 
     * @param string $filePath 文件路径
     * @param string $content 文件内容
     * @param int $flags 文件写入标志
     * @return bool 是否成功
     */
    public static function writeFile(string $filePath, string $content, int $flags = 0): bool
    {
        // 确保目录存在
        $directory = dirname($filePath);
        self::ensureDirectoryExists($directory);

        $result = file_put_contents($filePath, $content, $flags);
        
        return $result !== false;
    }

    /**
     * 追加文件内容
     * 
     * @param string $filePath 文件路径
     * @param string $content 文件内容
     * @return bool 是否成功
     */
    public static function appendFile(string $filePath, string $content): bool
    {
        return self::writeFile($filePath, $content, FILE_APPEND);
    }

    /**
     * 删除文件
     * 
     * @param string $filePath 文件路径
     * @return bool 是否成功
     */
    public static function deleteFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        return unlink($filePath);
    }

    /**
     * 检查文件是否可读
     * 
     * @param string $filePath 文件路径
     * @return bool 是否可读
     */
    public static function isReadable(string $filePath): bool
    {
        return is_readable($filePath);
    }

    /**
     * 检查文件是否可写
     * 
     * @param string $filePath 文件路径
     * @return bool 是否可写
     */
    public static function isWritable(string $filePath): bool
    {
        return is_writable($filePath);
    }

    /**
     * 获取文件修改时间
     * 
     * @param string $filePath 文件路径
     * @return int|null 文件修改时间（Unix 时间戳），失败返回 null
     */
    public static function getModificationTime(string $filePath): ?int
    {
        if (!file_exists($filePath)) {
            return null;
        }

        return filemtime($filePath);
    }

    /**
     * 获取文件创建时间
     * 
     * @param string $filePath 文件路径
     * @return int|null 文件创建时间（Unix 时间戳），失败返回 null
     */
    public static function getCreationTime(string $filePath): ?int
    {
        if (!file_exists($filePath)) {
            return null;
        }

        return filectime($filePath);
    }

    /**
     * 获取文件访问时间
     * 
     * @param string $filePath 文件路径
     * @return int|null 文件访问时间（Unix 时间戳），失败返回 null
     */
    public static function getAccessTime(string $filePath): ?int
    {
        if (!file_exists($filePath)) {
            return null;
        }

        return fileatime($filePath);
    }
}