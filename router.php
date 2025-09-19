<?php
// router.php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Handle static files first
if (file_exists(__DIR__ . $uri) && is_file(__DIR__ . $uri)) {
    return false; // Let PHP serve the file
}

// Handle API routes
if (strpos($uri, '/api/') === 0) {
    // Route to appropriate API file
    if (strpos($uri, '/api/analytics/') === 0) {
        include __DIR__ . '/api/analytics.php';
        return;
    } elseif (strpos($uri, '/api/auth-social') === 0) {
        include __DIR__ . '/api/auth-social.php';
        return;
    } else {
        include __DIR__ . '/api/index.php';
        return;
    }
}

// Default routes for HTML pages
if ($uri === '/' || $uri === '') {
    include __DIR__ . '/index-mobile-optimized.html';
    return;
} elseif ($uri === '/how-it-works' || $uri === '/how-it-works.html') {
    include __DIR__ . '/how-it-works.html';
    return;
} elseif ($uri === '/faq' || $uri === '/faq.html') {
    include __DIR__ . '/faq.html';
    return;
} elseif ($uri === '/venue-management' || $uri === '/venue-management.html') {
    include __DIR__ . '/venue-management.html';
    return;
}

// 404 for other routes
http_response_code(404);
echo "404 Not Found";
?>
