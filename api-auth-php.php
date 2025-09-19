<?php
// ========================================
// FESTALAUREA - LOGIN API
// ========================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/config.php';
require_once '../../classes/Database.php';
require_once '../../classes/Auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (empty($data['email']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email e password sono richiesti']);
    exit();
}

$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
$password = $data['password'];

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email non valida']);
    exit();
}

try {
    $db = Database::getInstance();
    $auth = new Auth($db);
    
    // Attempt login
    $result = $auth->login($email, $password);
    
    if ($result['success']) {
        // Update last login
        $db->update('users', 
            ['last_login' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $result['user']['id']]
        );
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login effettuato con successo',
            'token' => $result['token'],
            'user' => [
                'id' => $result['user']['id'],
                'email' => $result['user']['email'],
                'name' => $result['user']['first_name'] . ' ' . $result['user']['last_name'],
                'first_name' => $result['user']['first_name'],
                'last_name' => $result['user']['last_name'],
                'type' => $result['user']['user_type'],
                'avatar' => $result['user']['avatar'],
                'email_verified' => $result['user']['email_verified']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => DEBUG_MODE ? $e->getMessage() : 'Errore del server'
    ]);
}