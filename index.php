<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/Venue.php';
require_once '../classes/Booking.php';
require_once '../classes/Email.php';
require_once '../classes/Payment.php';
require_once '../classes/Review.php';

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
                    // Handle logout (invalidate token if using JWT)
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
        $venue = new Venue($pdo);
        
        switch ($segments[1] ?? '') {
            case '':
                if ($method === 'GET') {
                    $result = $venue->getAllVenues($_GET);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'featured':
                if ($method === 'GET') {
                    $result = $venue->getFeaturedVenues();
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'my-venue':
                if ($method === 'GET') {
                    $token = getBearerToken();
                    $userId = validateToken($token);
                    $result = $venue->getVenueByOwner($userId);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'dashboard-stats':
                if ($method === 'GET') {
                    $token = getBearerToken();
                    $userId = validateToken($token);
                    $result = $venue->getDashboardStats($userId);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            default:
                if (is_numeric($segments[1])) {
                    $venueId = (int)$segments[1];
                    if ($method === 'GET') {
                        $result = $venue->getVenueById($venueId);
                        echo json_encode($result);
                    } else {
                        throw new Exception('Method not allowed', 405);
                    }
                } else {
                    throw new Exception('Endpoint not found', 404);
                }
        }
    }
    
    // Bookings routes
    elseif ($segments[0] === 'bookings') {
        $booking = new Booking($pdo);
        
        switch ($segments[1] ?? '') {
            case '':
                if ($method === 'POST') {
                    $token = getBearerToken();
                    $userId = validateToken($token);
                    $data = json_decode(file_get_contents('php://input'), true);
                    $result = $booking->createBooking($userId, $data);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'user':
                if ($method === 'GET') {
                    $token = getBearerToken();
                    $userId = validateToken($token);
                    $result = $booking->getUserBookings($userId);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'venue':
                if ($method === 'GET') {
                    $token = getBearerToken();
                    $userId = validateToken($token);
                    $result = $booking->getVenueBookings($userId);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'upcoming':
                if ($method === 'GET') {
                    $token = getBearerToken();
                    $userId = validateToken($token);
                    $days = $_GET['days'] ?? 7;
                    $result = $booking->getUpcomingBookings($userId, $days);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'stats':
                if ($method === 'GET') {
                    $token = getBearerToken();
                    $userId = validateToken($token);
                    $result = $booking->getBookingStats($userId);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            default:
                if (is_numeric($segments[1])) {
                    $bookingId = (int)$segments[1];
                    
                    if (isset($segments[2]) && $segments[2] === 'status' && $method === 'PUT') {
                        $token = getBearerToken();
                        $userId = validateToken($token);
                        $data = json_decode(file_get_contents('php://input'), true);
                        $result = $booking->updateBookingStatus($bookingId, $data['status'], $userId);
                        echo json_encode($result);
                    } elseif (isset($segments[2]) && $segments[2] === 'cancel' && $method === 'PUT') {
                        $token = getBearerToken();
                        $userId = validateToken($token);
                        $data = json_decode(file_get_contents('php://input'), true);
                        $result = $booking->cancelBooking($bookingId, $userId, $data['reason'] ?? null);
                        echo json_encode($result);
                    } elseif ($method === 'GET') {
                        $token = getBearerToken();
                        $userId = validateToken($token);
                        $result = $booking->getBookingById($bookingId, $userId);
                        echo json_encode($result);
                    } else {
                        throw new Exception('Endpoint not found', 404);
                    }
                } else {
                    throw new Exception('Endpoint not found', 404);
                }
        }
    }
    
    // Reviews routes (NUOVO SISTEMA RECENSIONI)
    elseif ($segments[0] === 'reviews') {
        $review = new Review($pdo);
        
        switch ($segments[1] ?? '') {
            case '':
                if ($method === 'POST') {
                    $token = getBearerToken();
                    $userId = validateToken($token);
                    $data = json_decode(file_get_contents('php://input'), true);
                    $result = $review->createReview($userId, $data);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'venue':
                if ($method === 'GET' && isset($segments[2])) {
                    $venueId = (int)$segments[2];
                    $limit = $_GET['limit'] ?? 10;
                    $offset = $_GET['offset'] ?? 0;
                    $result = $review->getVenueReviews($venueId, $limit, $offset);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'user':
                if ($method === 'GET') {
                    $token = getBearerToken();
                    $userId = validateToken($token);
                    $limit = $_GET['limit'] ?? 10;
                    $result = $review->getUserReviews($userId, $limit);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'can-review':
                if ($method === 'GET' && isset($_GET['venue_id'])) {
                    $token = getBearerToken();
                    $userId = validateToken($token);
                    $venueId = (int)$_GET['venue_id'];
                    $result = $review->canUserReview($userId, $venueId);
                    echo json_encode([
                        'success' => true,
                        'can_review' => $result['can_review'],
                        'reason' => $result['reason'],
                        'booking_id' => $result['booking_id']
                    ]);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            default:
                if (is_numeric($segments[1])) {
                    $reviewId = (int)$segments[1];
                    $token = getBearerToken();
                    $userId = validateToken($token);
                    
                    if ($method === 'PUT') {
                        $data = json_decode(file_get_contents('php://input'), true);
                        $result = $review->updateReview($reviewId, $userId, $data);
                        echo json_encode($result);
                    } elseif ($method === 'DELETE') {
                        $result = $review->deleteReview($reviewId, $userId);
                        echo json_encode($result);
                    } elseif (isset($segments[2]) && $segments[2] === 'helpful' && $method === 'POST') {
                        $result = $review->markReviewHelpful($reviewId, $userId);
                        echo json_encode($result);
                    } else {
                        throw new Exception('Method not allowed', 405);
                    }
                } else {
                    throw new Exception('Endpoint not found', 404);
                }
        }
    }
    
    // Payments routes
    elseif ($segments[0] === 'payments') {
        $payment = new Payment($pdo);
        
        switch ($segments[1] ?? '') {
            case 'create-intent':
                if ($method === 'POST') {
                    $token = getBearerToken();
                    $userId = validateToken($token);
                    $data = json_decode(file_get_contents('php://input'), true);
                    $result = $payment->createPaymentIntent($data['booking_id'], $data['amount']);
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            case 'webhook':
                if ($method === 'POST') {
                    $result = $payment->handleWebhook();
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            default:
                throw new Exception('Endpoint not found', 404);
        }
    }
    
    // Utility routes
    elseif ($segments[0] === 'utils') {
        switch ($segments[1] ?? '') {
            case 'autocomplete-bookings':
                if ($method === 'POST') {
                    $booking = new Booking($pdo);
                    $result = $booking->autoCompleteBookings();
                    echo json_encode($result);
                } else {
                    throw new Exception('Method not allowed', 405);
                }
                break;
                
            default:
                throw new Exception('Endpoint not found', 404);
        }
    }
    
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
    // In a real implementation, validate JWT token
    // For now, return a sample user ID
    if ($token === 'valid_token') {
        return 1;
    }
    
    // Simple token validation - in production use proper JWT
    if (strlen($token) > 10) {
        return 1; // Return sample user ID
    }
    
    throw new Exception('Invalid token', 401);
}
?>