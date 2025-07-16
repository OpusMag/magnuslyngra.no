<?php
// Trying to handle CORS issues with serving files 

while (ob_get_level()) {
    ob_end_clean();
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Range, Cache-Control');
header('Access-Control-Expose-Headers: Accept-Ranges, Content-Encoding, Content-Length, Content-Range');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

const MUSIC_DIR = 'music';
const ALLOWED_EXTENSIONS = ['mp3', 'wav', 'ogg', 'm4a', 'flac'];
const MIME_TYPES = [
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'x-wav' => 'audio/x-wav',
    'wave' => 'audio/wave',
    'vnd.wave' => 'audio/vnd.wave',
    'ogg' => 'audio/ogg',
    'm4a' => 'audio/mp4',
    'flac' => 'audio/flac'
];

const RATE_LIMIT_REQUESTS = 60;
const RATE_LIMIT_WINDOW = 60;
const MAX_FILE_SIZE = 100 * 1024 * 1024;
const MAX_CHUNK_SIZE = 1024 * 1024;

class SecurityManager {
    private static $rateLimitFile = __DIR__ . '/rate_limits.json';
    
    public static function checkRateLimit(): bool {
        $clientIP = self::getClientIP();
        $currentTime = time();
        
        $rateLimits = self::loadRateLimits();
        
        $rateLimits = array_filter($rateLimits, function($data) use ($currentTime) {
            return ($currentTime - $data['first_request']) < RATE_LIMIT_WINDOW;
        });
        
        if (isset($rateLimits[$clientIP])) {
            $ipData = $rateLimits[$clientIP];
            
            if ($ipData['requests'] >= RATE_LIMIT_REQUESTS) {
                self::logSecurityEvent("Rate limit exceeded", ['ip' => $clientIP]);
                return false;
            }
            
            $rateLimits[$clientIP]['requests']++;
        } else {
            $rateLimits[$clientIP] = [
                'requests' => 1,
                'first_request' => $currentTime
            ];
        }
        
        self::saveRateLimits($rateLimits);
        return true;
    }
    
    private static function getClientIP(): string {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',    
            'HTTP_X_FORWARDED_FOR',      
            'HTTP_X_REAL_IP',            
            'REMOTE_ADDR'                
        ];
        
        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    private static function loadRateLimits(): array {
        if (!file_exists(self::$rateLimitFile)) {
            return [];
        }
        
        $data = file_get_contents(self::$rateLimitFile);
        return $data ? json_decode($data, true) : [];
    }
    
    private static function saveRateLimits(array $data): void {
        file_put_contents(self::$rateLimitFile, json_encode($data), LOCK_EX);
    }
    
    private static function logSecurityEvent(string $event, array $context = []): void {
        $logMessage = "[SECURITY] $event - " . json_encode($context);
        error_log($logMessage);
    }
}

function logError($message, $context = []) {
    $logMessage = "[Audio Proxy] " . $message;
    if (!empty($context)) {
        $logMessage .= " Context: " . json_encode($context);
    }
    error_log($logMessage);
}

function validateFile($filePath): bool {
    if (!file_exists($filePath) || !is_readable($filePath) || !is_file($filePath)) {
        return false;
    }
    
    $fileSize = filesize($filePath);
    if ($fileSize === false || $fileSize > MAX_FILE_SIZE) {
        return false;
    }

    $fileContent = finfo_file($finfo = finfo_open(FILEINFO_MIME_TYPE), $filePath);
    if (!$fileContent) {
        logError("Failed to open fileinfo", ['file' => $filePath]);
        return false;
    }
    
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return false;
    }
    
    return true;
}

function validateFilename($filename): string|false {
    if (empty($filename) || strlen($filename) > 255) {
        return false;
    }
    
    $filename = basename(str_replace("\0", '', $filename));
    
    if (!preg_match('/^[a-zA-Z0-9\-_.]{1,100}$/', $filename)) {
        return false;
    }
    
    if (str_starts_with($filename, '.') || in_array($filename, ['CON', 'PRN', 'AUX', 'NUL'])) {
        return false;
    }
    
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return false;
    }
    
    return $filename;
}

function getSecureFilePath($filename) {
    $musicDir = __DIR__ . DIRECTORY_SEPARATOR . MUSIC_DIR . DIRECTORY_SEPARATOR;
    $filePath = $musicDir . $filename;
    
    $realMusicDir = realpath($musicDir);
    $realFilePath = realpath($filePath);
    
    if (!$realFilePath || !$realMusicDir || !str_starts_with($realFilePath, $realMusicDir)) {
        return false;
    }
    
    return $realFilePath;
}

try {
    if (!SecurityManager::checkRateLimit()) {
        http_response_code(429);
        header('Retry-After: ' . RATE_LIMIT_WINDOW);
        exit('Rate limit exceeded');
    }
    
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Range, Cache-Control');
    header('Access-Control-Expose-Headers: Accept-Ranges, Content-Encoding, Content-Length, Content-Range');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'])) {
        http_response_code(405);
        header('Allow: GET, HEAD, OPTIONS');
        exit('Method not allowed');
    }
    
    $requestedFile = $_GET['file'] ?? '';
    $filename = validateFilename($requestedFile);
    
    if (!$filename) {
        logError("Invalid filename requested", ['requested' => $requestedFile]);
        http_response_code(400);
        exit('Invalid filename');
    }
    
    $filePath = getSecureFilePath($filename);
    if (!$filePath) {
        logError("Path traversal attempt", ['filename' => $filename]);
        http_response_code(403);
        exit('Access denied');
    }
    
    if (!validateFile($filePath)) {
        logError("File validation failed", ['filename' => $filename]);
        http_response_code(404);
        exit('File not found');
    }
    
    $fileSize = filesize($filePath);
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $fileContent = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $filePath);
    if ($fileContent === false) {
        logError("Failed to get file MIME type", ['file' => $filePath]);
        http_response_code(500);
        exit('Internal server error');
    }
    if ($fileContent === 'application/octet-stream') {
        $fileContent = MIME_TYPES[$extension] ?? false;
    }

    $mimeType = $fileContent ?: false;
    if (!$mimeType || !in_array($mimeType, array_values(MIME_TYPES))) {
        logError("Unsupported MIME type", ['file' => $filePath, 'mime' => $mimeType]);
        http_response_code(415);
        exit('Unsupported media type');
    }
    
    $range = $_SERVER['HTTP_RANGE'] ?? null;
    $start = 0;
    $end = $fileSize - 1;
    
    if ($range && preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
        $start = (int)$matches[1];
        if (!empty($matches[2])) {
            $end = min((int)$matches[2], $fileSize - 1);
        }
        
        $rangeSize = $end - $start + 1;
        if ($rangeSize > MAX_CHUNK_SIZE) {
            http_response_code(416);
            header("Content-Range: bytes */$fileSize");
            exit('Range too large');
        }
        
        if ($start > $end || $start >= $fileSize || $end >= $fileSize) {
            http_response_code(416);
            header("Content-Range: bytes */$fileSize");
            exit('Range not satisfiable');
        }
        
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$fileSize");
    } else {
        http_response_code(200);
    }
    
    header("Content-Type: $mimeType");
    header("Content-Length: " . ($end - $start + 1));
    header("Accept-Ranges: bytes");
    header("Cache-Control: public, max-age=3600");
    header("Last-Modified: " . gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT');
    
    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
        exit;
    }
    
    $file = fopen($filePath, 'rb');
    if (!$file) {
        logError("Cannot open file", ['path' => $filePath]);
        http_response_code(500);
        exit('Cannot open file');
    }
    
    if ($start > 0) {
        fseek($file, $start);
    }
    
    $bytesRemaining = $end - $start + 1;
    $chunkSize = min(8192, $bytesRemaining);
    
    while (!feof($file) && $bytesRemaining > 0 && connection_status() === CONNECTION_NORMAL) {
        $readSize = min($chunkSize, $bytesRemaining);
        $chunk = fread($file, $readSize);
        
        if ($chunk === false || strlen($chunk) === 0) {
            break;
        }
        
        echo $chunk;
        $bytesRemaining -= strlen($chunk);
        
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        
        if (memory_get_usage() > 50 * 1024 * 1024) {
            break;
        }
    }
    
    fclose($file);
    
} catch (Exception $e) {
    logError("Unexpected error", ['error' => $e->getMessage()]);
    http_response_code(500);
    exit('Internal server error');
} catch (Error $e) {
    logError("Fatal error", ['error' => $e->getMessage()]);
    http_response_code(500);
    exit('Internal server error');
}
?>