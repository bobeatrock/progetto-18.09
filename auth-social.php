<?php
session_start();

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
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/SocialAuth.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $socialAuth = new SocialAuth($pdo);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', trim($uri, '/'));
    
    if ($method === 'GET') {
        // Get auth URLs
        if (isset($_GET['provider'])) {
            $provider = $_GET['provider'];
            
            switch ($provider) {
                case 'google':
                    $authUrl = $socialAuth->getGoogleAuthUrl();
                    echo json_encode(['success' => true, 'auth_url' => $authUrl]);
                    break;
                    
                case 'facebook':
                    $authUrl = $socialAuth->getFacebookAuthUrl();
                    echo json_encode(['success' => true, 'auth_url' => $authUrl]);
                    break;
                    
                default:
                    throw new Exception('Provider non supportato', 400);
            }
        } else {
            throw new Exception('Provider richiesto', 400);
        }
    }
    
    elseif ($method === 'POST') {
        // Handle callbacks
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['provider']) || !isset($data['code'])) {
            throw new Exception('Dati mancanti', 400);
        }
        
        $provider = $data['provider'];
        $code = $data['code'];
        $state = $data['state'] ?? '';
        
        switch ($provider) {
            case 'google':
                $result = $socialAuth->handleGoogleCallback($code, $state);
                echo json_encode($result);
                break;
                
            case 'facebook':
                $result = $socialAuth->handleFacebookCallback($code, $state);
                echo json_encode($result);
                break;
                
            default:
                throw new Exception('Provider non supportato', 400);
        }
    }
    
    else {
        throw new Exception('Metodo non consentito', 405);
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
