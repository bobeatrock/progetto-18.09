<?php
class User {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function register($data) {
        try {
            // Validate required fields
            $required = ['name', 'email', 'password'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Campo {$field} obbligatorio"];
                }
            }
            
            // Check if email already exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email già registrata'];
            }
            
            // Validate email domain for students
            if (!$this->isValidStudentEmail($data['email']) && !isset($data['type'])) {
                return ['success' => false, 'message' => 'Usa la tua email universitaria (@studenti.unipd.it)'];
            }
            
            // Hash password
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $this->pdo->prepare("
                INSERT INTO users (name, email, phone, department, password_hash, type, email_verified) 
                VALUES (?, ?, ?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['phone'] ?? null,
                $data['department'] ?? null,
                $passwordHash,
                $data['type'] ?? 'student'
            ]);
            
            $userId = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Registrazione completata',
                'user_id' => $userId
            ];
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante la registrazione'];
        }
    }
    
    public function login($email, $password) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name, email, type, password_hash 
                FROM users 
                WHERE email = ? AND email_verified = TRUE
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Credenziali non valide'];
            }
            
            // Update last login
            $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Generate token (in production, use proper JWT)
            $token = $this->generateToken($user);
            
            unset($user['password_hash']);
            
            return [
                'success' => true,
                'token' => $token,
                'user' => $user
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante il login'];
        }
    }
    
    public function getUserById($id) {
        $stmt = $this->pdo->prepare("
            SELECT id, name, email, phone, department, type, university, graduation_year, created_at 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateUser($id, $data) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET name = ?, phone = ?, department = ?, graduation_year = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['phone'] ?? null,
                $data['department'] ?? null,
                $data['graduation_year'] ?? null,
                $id
            ]);
            
            return ['success' => true, 'message' => 'Profilo aggiornato'];
            
        } catch (Exception $e) {
            error_log("Update user error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'aggiornamento'];
        }
    }
    
    public function changePassword($id, $currentPassword, $newPassword) {
        try {
            // Verify current password
            $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Password attuale non corretta'];
            }
            
            // Update password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newPasswordHash, $id]);
            
            return ['success' => true, 'message' => 'Password cambiata con successo'];
            
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante il cambio password'];
        }
    }
    
    public function getUserStats($id) {
        try {
            $stats = [];
            
            // Total bookings
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
            $stmt->execute([$id]);
            $stats['total_bookings'] = $stmt->fetchColumn();
            
            // Completed bookings
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE user_id = ? AND status = 'completed'
            ");
            $stmt->execute([$id]);
            $stats['completed_bookings'] = $stmt->fetchColumn();
            
            // Total spent
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0) FROM bookings 
                WHERE user_id = ? AND payment_status = 'paid'
            ");
            $stmt->execute([$id]);
            $stats['total_spent'] = $stmt->fetchColumn();
            
            // Reviews written
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = ?");
            $stmt->execute([$id]);
            $stats['reviews_written'] = $stmt->fetchColumn();
            
            return ['success' => true, 'stats' => $stats];
            
        } catch (Exception $e) {
            error_log("Get user stats error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel caricamento statistiche'];
        }
    }
    
    private function isValidStudentEmail($email) {
        return strpos($email, '@studenti.unipd.it') !== false;
    }
    
    private function generateToken($user) {
        // In production, use proper JWT token generation
        return base64_encode(json_encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'type' => $user['type'],
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ]));
    }
}
?>