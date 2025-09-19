<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Analytics.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $analytics = new Analytics($pdo);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = str_replace('/api/analytics/', '', $uri);
    $segments = explode('/', trim($uri, '/'));
    
    if ($method === 'GET') {
        switch ($segments[0] ?? '') {
            case 'platform':
                // Public platform statistics
                $result = $analytics->getPlatformStats();
                echo json_encode($result);
                break;
                
            case 'popular-venues':
                // Get popular venues based on real data
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 6;
                $result = $analytics->getPopularVenues($limit);
                echo json_encode($result);
                break;
                
            default:
                throw new Exception('Endpoint not found', 404);
        }
    }
    
    elseif ($method === 'POST') {
        switch ($segments[0] ?? '') {
            case 'page-view':
                // Record page view
                $data = json_decode(file_get_contents('php://input'), true);
                $page = $data['page'] ?? '';
                $userId = $data['user_id'] ?? null;
                $sessionId = $data['session_id'] ?? null;
                
                $result = $analytics->recordPageView($page, $userId, $sessionId);
                echo json_encode($result);
                break;
                
            default:
                throw new Exception('Endpoint not found', 404);
        }
    }
    
    else {
        throw new Exception('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
