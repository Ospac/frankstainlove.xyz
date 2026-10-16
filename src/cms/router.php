<?php

require_once __DIR__ . "/utils.php";

function normalize_request_path()
{
    $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    return '/' . ltrim(urldecode($requestUri), '/');
}

function is_protected_path($requestUri)
{
    return preg_match('#^/(src|page|dist)(/|$)#', $requestUri) === 1;
}

function handle_cms_request($requestUri)
{
    if (!preg_match('#^/cms/([^/]+\.php)(/.*)?$#', $requestUri, $matches)) {
        return false;
    }

    $script = CMS_DIR . '/' . $matches[1];
    if (!is_file($script)) {
        http_response_code(404);
        exit('404 Not Found');
    }

    $_SERVER['PATH_INFO'] = $matches[2] ?? '';
    if ($matches[1] === 'render.php') {
        unset($_SERVER['REDIRECT_URL']);
    }

    include $script;
    return true;
}

function static_path($requestUri)
{
    return str_starts_with($requestUri, '/assets/')
        ? PAGE_DIR . $requestUri
        : PROJECT_DIR . $requestUri;
}

function serve_static_asset($requestUri)
{
    if (!str_starts_with($requestUri, '/assets/')) {
        return false;
    }

    $path = static_path($requestUri);
    if (!is_file($path)) {
        return false;
    }

    $mimeTypes = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'mjs' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
    ];
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mimeType = $mimeTypes[$extension] ?? mime_content_type($path);

    if ($mimeType !== false) {
        header('Content-Type: ' . $mimeType);
    }
    readfile($path);
    return true;
}

function render_source_page($requestUri)
{
    $sourcePath = static_path($requestUri);
    if (is_file($sourcePath) && !str_ends_with(strtolower($sourcePath), '.md')) {
        return false;
    }

    $markdownPath = PAGE_DIR . $requestUri . '.md';
    $indexMarkdownPath = PAGE_DIR . rtrim($requestUri, '/') . '/index.md';

    if ($requestUri === '/' || str_ends_with($requestUri, '/')) {
        if (!is_file($indexMarkdownPath)) {
            return false;
        }

        $_SERVER['PATH_INFO'] = content_relative_path(rtrim($requestUri, '/') . '/index.md');
        unset($_SERVER['REDIRECT_URL']);
        include CMS_DIR . '/render.php';
        return true;
    }

    if (is_file($markdownPath)) {
        $_SERVER['PATH_INFO'] = content_relative_path($requestUri . '.md');
        unset($_SERVER['REDIRECT_URL']);
        include CMS_DIR . '/render.php';
        return true;
    }

    if (str_ends_with($requestUri, '.html')) {
        $markdownPath = PAGE_DIR . substr($requestUri, 0, -5) . '.md';
        if (is_file($markdownPath)) {
            $_SERVER['PATH_INFO'] = content_relative_path(substr($requestUri, 0, -5) . '.md');
            unset($_SERVER['REDIRECT_URL']);
            include CMS_DIR . '/render.php';
            return true;
        }
    }

    return false;
}

function serve_dist_fallback($requestUri)
{
    $distPath = DIST_DIR . $requestUri;
    if ($requestUri === '/' || str_ends_with($requestUri, '/')) {
        $distPath = DIST_DIR . rtrim($requestUri, '/') . '/index.html';
    } elseif (!pathinfo($requestUri, PATHINFO_EXTENSION)) {
        $distPath .= '.html';
    }

    if (!is_file($distPath)) {
        return false;
    }

    readfile($distPath);
    return true;
}

$requestUri = normalize_request_path();

if (str_contains($requestUri, '..')) {
    http_response_code(400);
    exit('Invalid path');
}

if (is_protected_path($requestUri)) {
    http_response_code(404);
    exit('404 Not Found');
}

if (handle_cms_request($requestUri)) {
    return true;
}

if (serve_static_asset($requestUri)) {
    return true;
}

if (render_source_page($requestUri)) {
    return true;
}

if (serve_dist_fallback($requestUri)) {
    return true;
}

http_response_code(404);
echo "404 Not Found";
