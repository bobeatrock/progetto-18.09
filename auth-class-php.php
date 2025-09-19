<?php
// ========================================
// FESTALAUREA - AUTHENTICATION CLASS
// ========================================

require_once 'JWT.php';

class Auth {
    private $db;
    private $jwt;
    
    public function __construct($db) {
        $this->db = $db;
        $this->jwt = new JWT();
    }
    
    /**
     * Login user
     */
    public function login($email, $password) {
        // Get user by email
        $user = $this->db->selectOne(
            "SELECT * FROM users WHERE email = :email AND status = 'active'",
            ['email' => $email]
        );
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Email o password non corretti'
            ];
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Email o password non corretti'
            ];
        }
        
        // Generate JWT token
        $token = $this->jwt->encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'user_type' => $user['user_type'],
            'exp' => time() + JWT_EXPIRY
        ]);
        
        return [
            'success' => true,
            'token' => $token,
            'user' => $user
        ];
    }
    
    /**
     * Register new user
     */
    public function register($data) {
        // Check if email already exists
        $existing = $this->db->selectOne(
            "SELECT id FROM users WHERE email = :email",
            ['email' => $data['email']]
        );
        
        if ($existing) {
            return [
                'success' => false,
                'message' => 'Email già registrata'
            ];
        }
        
        // Hash password
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Generate verification token
        $data['email_verification_token'] = bin2hex(random_bytes(32));
        
        // Insert user
        try {
            $userId = $this->db->insert('users', [
                'email' => $data['email'],
                'password' => $data['password'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'user_type' => $data['user_type'] ?? 'student',
                'email_verification_token' => $data['email_verification_token']
            ]);
            
            // If venue type, create venue record
            if ($data['user_type'] === 'venue' && !empty($data['business_name'])) {
                $this->db->insert('venues', [
                    'user_id' => $userId,
                    'name' => $data['first_name'] . ' ' . $data['last_name'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'type' => $data['user_type'] ?? 'student'
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Errore durante la registrazione: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Social login
     */
    public function socialLogin($provider, $socialId, $email, $name, $avatar = null) {
        // Check if user exists
        $user = $this->db->selectOne(
            "SELECT * FROM users WHERE email = :email OR {$provider}_id = :social_id",
            ['email' => $email, 'social_id' => $socialId]
        );
        
        if ($user) {
            // Update social ID if needed
            if (!$user[$provider . '_id']) {
                $this->db->update('users',
                    [$provider . '_id' => $socialId],
                    'id = :id',
                    ['id' => $user['id']]
                );
            }
        } else {
            // Create new user
            $nameParts = explode(' ', $name, 2);
            $userId = $this->db->insert('users', [
                'email' => $email,
                'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
                'avatar' => $avatar,
                $provider . '_id' => $socialId,
                'email_verified' => 1,
                'user_type' => 'student'
            ]);
            
            $user = $this->db->selectOne(
                "SELECT * FROM users WHERE id = :id",
                ['id' => $userId]
            );
        }
        
        // Generate token
        $token = $this->jwt->encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'user_type' => $user['user_type'],
            'exp' => time() + JWT_EXPIRY
        ]);
        
        return [
            'success' => true,
            'token' => $token,
            'user' => $user
        ];
    }
    
    /**
     * Verify JWT token
     */
    public function verifyToken($token) {
        try {
            $decoded = $this->jwt->decode($token);
            
            if ($decoded['exp'] < time()) {
                return ['success' => false, 'message' => 'Token expired'];
            }
            
            return ['success' => true, 'data' => $decoded];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Invalid token'];
        }
    }
    
    /**
     * Reset password request
     */
    public function requestPasswordReset($email) {
        $user = $this->db->selectOne(
            "SELECT id, email, first_name FROM users WHERE email = :email",
            ['email' => $email]
        );
        
        if (!$user) {
            return ['success' => false, 'message' => 'Email non trovata'];
        }
        
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Save token
        $this->db->update('users',
            [
                'password_reset_token' => $token,
                'password_reset_expires' => $expires
            ],
            'id = :id',
            ['id' => $user['id']]
        );
        
        // Send reset email
        $this->sendPasswordResetEmail($user['email'], $user['first_name'], $token);
        
        return ['success' => true, 'message' => 'Email di reset inviata'];
    }
    
    /**
     * Reset password
     */
    public function resetPassword($token, $newPassword) {
        $user = $this->db->selectOne(
            "SELECT id FROM users 
             WHERE password_reset_token = :token 
             AND password_reset_expires > NOW()",
            ['token' => $token]
        );
        
        if (!$user) {
            return ['success' => false, 'message' => 'Token non valido o scaduto'];
        }
        
        // Update password
        $this->db->update('users',
            [
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'password_reset_token' => null,
                'password_reset_expires' => null
            ],
            'id = :id',
            ['id' => $user['id']]
        );
        
        return ['success' => true, 'message' => 'Password aggiornata con successo'];
    }
    
    /**
     * Create URL slug
     */
    private function createSlug($text) {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
    
    /**
     * Send verification email
     */
    private function sendVerificationEmail($email, $token) {
        // TODO: Implement email sending
        $verifyUrl = BASE_URL . "/verify-email.php?token=" . $token;
        // Send email with verification link
    }
    
    /**
     * Send password reset email
     */
    private function sendPasswordResetEmail($email, $name, $token) {
        // TODO: Implement email sending
        $resetUrl = BASE_URL . "/reset-password.php?token=" . $token;
        // Send email with reset link
    }
} => $data['business_name'],
                    'slug' => $this->createSlug($data['business_name']),
                    'vat_number' => $data['vat_number'] ?? null,
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'status' => 'pending'
                ]);
            }
            
            // Generate token for auto-login
            $token = $this->jwt->encode([
                'user_id' => $userId,
                'email' => $data['email'],
                'user_type' => $data['user_type'] ?? 'student',
                'exp' => time() + JWT_EXPIRY
            ]);
            
            // Send verification email (implement email sending)
            $this->sendVerificationEmail($data['email'], $data['email_verification_token']);
            
            return [
                'success' => true,
                'message' => 'Registrazione completata con successo',
                'token' => $token,
                'user' => [
                    'id' => $userId,
                    'email' => $data['email'],
                    'name'