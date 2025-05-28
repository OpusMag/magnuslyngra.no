<?php
/**
 * Audio Proxy Script
 * Hopefully sends the right CORS headers so this bloody thing works
 */

define('MAX_FILE_SIZE', 100 * 1024 * 1024);
define('MAX_REQUESTS_PER_IP', 60);
define('RATE_LIMIT_WINDOW', 60);
define('MAX_RANGE_REQUESTS', 10);
define('MIN_CHUNK_SIZE', 1024);
define('MAX_CHUNK_SIZE', 10 * 1024 * 1024);

// MIME verification to avoid faking of files
class AudioFileValidator {
    private static $allowedExtensions = [
        'mp3' => [
            'mime' => 'audio/mpeg',
            'signatures' => [
                'ID3' => [0x49, 0x44, 0x33],
                'MPEG' => [0xFF, 0xFB],
                'MPEG2' => [0xFF, 0xF3],
                'MPEG25' => [0xFF, 0xE3]
            ]
        ],
        'wav' => [
            'mime' => 'audio/wav',
            'signatures' => [
                'RIFF' => [0x52, 0x49, 0x46, 0x46],
                'WAVE' => [0x57, 0x41, 0x56, 0x45]
            ]
        ],
        'ogg' => [
            'mime' => 'audio/ogg',
            'signatures' => [
                'OGG' => [0x4F, 0x67, 0x67, 0x53]
            ]
        ],
        'm4a' => [
            'mime' => 'audio/mp4',
            'signatures' => [
                'FTYP' => [0x66, 0x74, 0x79, 0x70]
            ]
        ],
        'flac' => [
            'mime' => 'audio/flac',
            'signatures' => [
                'FLAC' => [0x66, 0x4C, 0x61, 0x43]
            ]
        ]
    ];

    public static function validateFile($filePath, $extension) {
        if (!isset(self::$allowedExtensions[$extension])) {
            return false;
        }

        $fileSize = filesize($filePath);
        if ($fileSize === false || $fileSize > MAX_FILE_SIZE) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return false;
        }

        $detectedMime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        $expectedMime = self::$allowedExtensions[$extension]['mime'];
        if ($detectedMime !== $expectedMime) {
            return false;
        }

        return self::verifyFileSignature($filePath, $extension);
    }

    private static function verifyFileSignature($filePath, $extension) {
        $file = fopen($filePath, 'rb');
        if ($file === false) {
            return false;
        }

        $signatures = self::$allowedExtensions[$extension]['signatures'];
        $isValid = false;

        foreach ($signatures as $name => $signature) {
            $offset = 0;
            
            if ($extension === 'wav' && $name === 'WAVE') {
                $offset = 8;
            } elseif ($extension === 'm4a' && $name === 'FTYP') {
                $offset = 4;
            }

            fseek($file, $offset);
            $header = fread($file, count($signature));
            
            if ($header !== false) {
                $headerBytes = array_values(unpack('C*', $header));
                if ($headerBytes === $signature) {
                    $isValid = true;
                    break;
                }
            }
        }

        fclose($file);
        return $isValid;
    }

    public static function getMimeType($extension) {
        return self::$allowedExtensions[$extension]['mime'] ?? null;
    }
}

// Limits rates to avoid DDOS
class RateLimiter {
    private static $dataFile = __DIR__ . '/rate_limit_data.json';

    public static function checkRateLimit($ip) {
        $data = self::loadData();
        $currentTime = time();
        
        $data = array_filter($data, function($entry) use ($currentTime) {
            return ($currentTime - $entry['timestamp']) < RATE_LIMIT_WINDOW;
        });

        $ipRequests = array_filter($data, function($entry) use ($ip) {
            return $entry['ip'] === $ip;
        });

        if (count($ipRequests) >= MAX_REQUESTS_PER_IP) {
            return false;
        }

        $data[] = [
            'ip' => $ip,
            'timestamp' => $currentTime
        ];

        self::saveData($data);
        return true;
    }

    public static function checkRangeRequestLimit($ip, $filename) {
        $data = self::loadData();
        $currentTime = time();
        
        $rangeRequests = array_filter($data, function($entry) use ($ip, $filename, $currentTime) {
            return $entry['ip'] === $ip && 
                   isset($entry['filename']) && 
                   $entry['filename'] === $filename &&
                   isset($entry['range']) && 
                   $entry['range'] === true &&
                   ($currentTime - $entry['timestamp']) < RATE_LIMIT_WINDOW;
        });

        return count($rangeRequests) < MAX_RANGE_REQUESTS;
    }

    public static function logRangeRequest($ip, $filename) {
        $data = self::loadData();
        $data[] = [
            'ip' => $ip,
            'filename' => $filename,
            'range' => true,
            'timestamp' => time()
        ];
        self::saveData($data);
    }

    private static function loadData() {
        if (!file_exists(self::$dataFile)) {
            return [];
        }
        
        $json = file_get_contents(self::$dataFile);
        return $json ? json_decode($json, true) : [];
    }

    private static function saveData($data) {
        if (count($data) > 1000) {
            $data = array_slice($data, -1000);
        }
        
        file_put_contents(self::$dataFile, json_encode($data), LOCK_EX);
    }
}

class RangeValidator {
    public static function validateRange($range, $fileSize, $ip, $filename) {
        if (!preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
            return false;
        }

        $start = intval($matches[1]);
        $end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;

        if ($start > $end || $start >= $fileSize || $end >= $fileSize) {
            return false;
        }

        $chunkSize = $end - $start + 1;
        if ($chunkSize < MIN_CHUNK_SIZE || $chunkSize > MAX_CHUNK_SIZE) {
            return false;
        }

        if (!RateLimiter::checkRangeRequestLimit($ip, $filename)) {
            return false;
        }

        RateLimiter::logRangeRequest($ip, $filename);
        return ['start' => $start, 'end' => $end];
    }
}

// Ensure good IP
function getClientIP() {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
               'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

$clientIP = getClientIP();

if (!RateLimiter::checkRateLimit($clientIP)) {
    http_response_code(429);
    header('Retry-After: ' . RATE_LIMIT_WINDOW);
    exit('Rate limit exceeded');
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Range');
header('Access-Control-Expose-Headers: Accept-Ranges, Content-Encoding, Content-Length, Content-Range');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    http_response_code(405);
    header('Allow: GET, HEAD, OPTIONS');
    exit('Method not allowed');
}

$requestedFile = $_GET['file'] ?? null;

if (!$requestedFile) {
    http_response_code(400);
    exit('File parameter is required');
}

// Sanitize file name to prevent scullduggery
$filename = basename($requestedFile);
$filename = preg_replace('/[^a-zA-Z0-9\-_.]/', '', $filename);

if ($filename !== $requestedFile || empty($filename)) {
    http_response_code(400);
    exit('Invalid filename');
}

$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

$musicDirectory = __DIR__ . '/music/';
$filePath = $musicDirectory . $filename;

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    exit('File not found');
}

if (!AudioFileValidator::validateFile($filePath, $extension)) {
    http_response_code(400);
    exit('Invalid audio file');
}

$fileSize = filesize($filePath);
$mimeType = AudioFileValidator::getMimeType($extension);

$range = $_SERVER['HTTP_RANGE'] ?? null;
$start = 0;
$end = $fileSize - 1;

if ($range) {
    $rangeResult = RangeValidator::validateRange($range, $fileSize, $clientIP, $filename);
    
    if ($rangeResult === false) {
        http_response_code(416);
        header("Content-Range: bytes */$fileSize");
        exit('Range not satisfiable');
    }
    
    $start = $rangeResult['start'];
    $end = $rangeResult['end'];
    
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$fileSize");
    header('Accept-Ranges: bytes');
} else {
    http_response_code(200);
    header('Accept-Ranges: bytes');
}

header("Content-Type: $mimeType");
header("Content-Length: " . ($end - $start + 1));
header('Cache-Control: public, max-age=3600');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT');

if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}

$file = fopen($filePath, 'rb');
if ($file === false) {
    http_response_code(500);
    exit('Error opening file');
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
    
    if (connection_aborted()) {
        break;
    }
}

fclose($file);
?>