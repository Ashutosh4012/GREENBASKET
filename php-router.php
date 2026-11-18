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
$file = realpath(__DIR__ . $path);
if ($file && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
if (!pathinfo($path, PATHINFO_EXTENSION)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    return true;
}
http_response_code(404);
echo 'File not found';
return true;
