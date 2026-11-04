<?php
declare(strict_types=1);

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$path = str_replace('\\', '/', $path);
$segments = array_map('strtolower', explode('/', trim($path, '/')));
$blockedDirectories = ['config', 'includes', 'sql', 'src'];

foreach ($segments as $segment) {
    if (in_array($segment, $blockedDirectories, true) || str_starts_with($segment, '.')) {
        http_response_code(404);
        exit;
    }
}

if (strtolower(basename($path)) === 'readme.md') {
    http_response_code(404);
    exit;
}

return false;
