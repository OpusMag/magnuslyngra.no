<?php
// Trying to handle CORS issues with serving files 

while (ob_get_level()) {
    ob_end_clean();
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Range, Cache-Control');
header('Access-Control-Expose-Headers: Accept-Ranges, Content-Encoding, Content-Length, Content-Range');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

const MUSIC_DIR = 'music';
const ALLOWED_EXTENSIONS = ['mp3', 'wav', 'ogg', 'm4a', 'flac'];
const MIME_TYPES = [
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'ogg' => 'audio/ogg',
    'm4a' => 'audio/mp4',
    'flac' => 'audio/flac'
];

function logError($message, $context = []) {
    $logMessage = "[Audio Proxy] " . $message;
    if (!empty($context)) {
        $logMessage .= " Context: " . json_encode($context);
    }
    error_log($logMessage);
}

function validateFilename($filename) {
    if (empty($filename)) {
        return false;
    }
    
    $filename = basename($filename);
    
    if (!preg_match('/^[a-zA-Z0-9\-_.]+$/', $filename)) {
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
    if (!$filePath || !file_exists($filePath) || !is_file($filePath)) {
        logError("File not found", ['filename' => $filename, 'path' => $filePath]);
        http_response_code(404);
        exit('File not found');
    }

    $fileSize = filesize($filePath);
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mimeType = MIME_TYPES[$extension];

    $range = $_SERVER['HTTP_RANGE'] ?? null;
    $start = 0;
    $end = $fileSize - 1;

    if ($range && preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
        $start = (int)$matches[1];
        if (!empty($matches[2])) {
            $end = (int)$matches[2];
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
    $chunkSize = 8192;

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
    }

    fclose($file);

} catch (Exception $e) {
    logError("Unexpected error", ['error' => $e->getMessage()]);
    http_response_code(500);
    exit('Internal server error');
}
?>