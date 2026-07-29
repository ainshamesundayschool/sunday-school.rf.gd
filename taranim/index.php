<?php
// Entry point for Apache / Nginx / PHP Web Hosts
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (strpos($uri, '/api/') !== false) {
    require __DIR__ . '/api.php';
    exit;
}

if (strpos($uri, 'install.html') !== false && file_exists(__DIR__ . '/public/install.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/public/install.html');
    exit;
}

if (strpos($uri, 'manifest.webmanifest') !== false && file_exists(__DIR__ . '/public/manifest.webmanifest')) {
    header('Content-Type: application/manifest+json; charset=utf-8');
    readfile(__DIR__ . '/public/manifest.webmanifest');
    exit;
}

if (strpos($uri, 'obs.html') !== false && file_exists(__DIR__ . '/public/obs.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/public/obs.html');
    exit;
}

if (strpos($uri, 'sw.js') !== false && file_exists(__DIR__ . '/public/sw.js')) {
    header('Content-Type: application/javascript; charset=utf-8');
    readfile(__DIR__ . '/public/sw.js');
    exit;
}

if (strpos($uri, 'style.css') !== false && file_exists(__DIR__ . '/public/style.css')) {
    header('Content-Type: text/css; charset=utf-8');
    readfile(__DIR__ . '/public/style.css');
    exit;
}

if (strpos($uri, 'app.js') !== false && file_exists(__DIR__ . '/public/app.js')) {
    header('Content-Type: application/javascript; charset=utf-8');
    readfile(__DIR__ . '/public/app.js');
    exit;
}

if (strpos($uri, 'logo.png') !== false && file_exists(__DIR__ . '/public/logo.png')) {
    header('Content-Type: image/png');
    readfile(__DIR__ . '/public/logo.png');
    exit;
}

// Default Index Page
if (file_exists(__DIR__ . '/public/index.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/public/index.html');
    exit;
}
