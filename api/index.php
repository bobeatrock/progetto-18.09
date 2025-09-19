<?php
ini_set("display_errors", 1);
error_reporting(E_ALL);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Venue.php';
require_once __DIR__ . '/../classes/Booking.php';
require_once __DIR__ . '/../classes/Email.php';
require_once __DIR__ . '/../classes/Payment.php';
require_once __DIR__ . '/../classes/Review.php';
require_once __DIR__ . '/../classes/Analytics.php';
require_once __DIR__ . '/../classes/VenueManager.php';

// Router
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/api', '', $uri);
$segments = explode('/', trim($uri, '/'));

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Authentication routes
    if ($segments[0] === 'auth') {
        switch ($segments[1]) {
            case 'login':
                if ($method === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $user = new User($pdo);
                    $result = $user->login($data['email'], $data['password']);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'register':
                if ($method === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    $user = new User($pdo);
                    $result = $user->register($data);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'logout':
                if ($method === 'POST') {
                    // In a real JWT setup, logout is handled client-side by deleting the token.
                    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            default:
                throw new Exception('Endpoint not found', 404);
        }
    }
    
    // Venues routes
    elseif ($segments[0] === 'venues') {
        $venueManager = new VenueManager($pdo);
        $analytics = new Analytics($pdo);
        
        switch ($segments[1] ?? '') {
            case '': // GET /api/venues
                if ($method === 'GET') {
                    $result = $venueManager->getAllVenues($_GET);
                    echo json_encode($result);
                }
                elseif ($method === 'POST') { // POST /api/venues - Create new venue
                    $token = getBearerToken();
                    $decoded = validateToken($token);
                    $userId = $decoded->data->user_id;
                    $data = json_decode(file_get_contents('php://input'), true);
                    $result = $venueManager->createVenue($userId, $data);
                    echo json_encode($result);
                }
                else {
                    throw new Exception('Method not allowed', 405);
                }
                break;

            case 'featured':
                if ($method === 'GET') {
                    $result = $venueManager->getFeaturedVenues();
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'my-venue':
                if ($method === 'GET') {
                    $token = getBearerToken();
                    $decoded = validateToken($token);
                    $userId = $decoded->data->user_id;
                    $result = $venueManager->getVenueByOwner($userId);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'dashboard-stats':
                if ($method === 'GET') {
                    $token = getBearerToken();
                    $decoded = validateToken($token);
                    $userId = $decoded->data->user_id;
                    
                    // Get venue first
                    $venueResult = $venueManager->getVenueByOwner($userId);
                    if (!$venueResult['success']) {
                        echo json_encode(['success' => false, 'message' => 'Nessun locale trovato']);
                        break;
                    }
                    
                    $venueId = $venueResult['venue']['id'];
                    $result = $analytics->getVenueStats($venueId);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            default:
                if (is_numeric($segments[1])) {
                    $venueId = (int)$segments[1];
                    if ($method === 'GET') {
                        $result = $venueManager->getVenueById($venueId);
                        echo json_encode($result);
                    } elseif ($method === 'PUT') {
                        $token = getBearerToken();
                        $decoded = validateToken($token);
                        $userId = $decoded->data->user_id;
                        $data = json_decode(file_get_contents('php://input'), true);
                        $result = $venueManager->updateVenue($venueId, $userId, $data);
                        echo json_encode($result);
                    } else {
                        throw new Exception('Method not allowed', 405);
                    }
                } else {
                    throw new Exception('Endpoint not found', 404);
                }
        }
    }
    
    // ... (rest of the routes remain the same)
    
    else {
        throw new Exception('Endpoint not found', 404);
    }
    
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    throw new Exception('Token not provided', 401);
}

function validateToken($token) {
    try {
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        throw new Exception('Invalid token: ' . $e->getMessage(), 401);
    }
}
?>
