<?php
declare(strict_types=1);

// PHP built-in server router: API requests go to api.php; other paths are static files.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_starts_with($path, '/api/')) {
    require __DIR__ . '/api.php';
    return true;
}
if ($path === '/') {
    $path = '/index.html';
}
$publicFile = realpath(__DIR__ . '/public' . $path);
if ($publicFile && str_starts_with($publicFile, realpath(__DIR__ . '/public') . DIRECTORY_SEPARATOR) && is_file($publicFile)) {
    $mime = match (pathinfo($publicFile, PATHINFO_EXTENSION)) {
        'html' => 'text/html; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'png'  => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'svg'  => 'image/svg+xml',
        default => 'application/octet-stream',
    };
    header("Content-Type: $mime");
    readfile($publicFile);
    return true;
}
$file = realpath(__DIR__ . $path);
if ($file && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
if (!pathinfo($path, PATHINFO_EXTENSION)) {
    header('Content-Type: text/html; charset=utf-8');
    if (is_file(__DIR__ . '/public/index.html')) {
        readfile(__DIR__ . '/public/index.html');
    } else {
        readfile(__DIR__ . '/index.html');
    }
    return true;
}
http_response_code(404);
echo 'File not found';
return true;
