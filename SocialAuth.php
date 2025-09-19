<?php
class SocialAuth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getGoogleAuthUrl() {
        $params = [
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => BASE_URL . '/auth/google/callback',
            'scope' => 'openid email profile',
            'response_type' => 'code',
            'state' => bin2hex(random_bytes(16))
        ];
        
        $_SESSION['google_state'] = $params['state'];
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    public function getFacebookAuthUrl() {
        $params = [
            'client_id' => FACEBOOK_APP_ID,
            'redirect_uri' => BASE_URL . '/auth/facebook/callback',
            'scope' => 'email,public_profile',
            'response_type' => 'code',
            'state' => bin2hex(random_bytes(16))
        ];
        
        $_SESSION['facebook_state'] = $params['state'];
        
        return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query($params);
    }
    
    public function handleGoogleCallback($code, $state) {
        try {
            // Verify state parameter
            if (!isset($_SESSION['google_state']) || $state !== $_SESSION['google_state']) {
                throw new Exception('Invalid state parameter');
            }
            
            // Exchange code for access token
            $tokenData = $this->exchangeGoogleCode($code);
            
            // Get user info from Google
            $userInfo = $this->getGoogleUserInfo($tokenData['access_token']);
            
            // Create or update user
            $user = $this->createOrUpdateSocialUser($userInfo, 'google');
            
            return [
                'success' => true,
                'user' => $user,
                'token' => $this->generateJWTToken($user)
            ];
            
        } catch (Exception $e) {
            error_log("Google auth error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore autenticazione Google'];
        }
    }
    
    public function handleFacebookCallback($code, $state) {
        try {
            // Verify state parameter
            if (!isset($_SESSION['facebook_state']) || $state !== $_SESSION['facebook_state']) {
                throw new Exception('Invalid state parameter');
            }
            
            // Exchange code for access token
            $tokenData = $this->exchangeFacebookCode($code);
            
            // Get user info from Facebook
            $userInfo = $this->getFacebookUserInfo($tokenData['access_token']);
            
            // Create or update user
            $user = $this->createOrUpdateSocialUser($userInfo, 'facebook');
            
            return [
                'success' => true,
                'user' => $user,
                'token' => $this->generateJWTToken($user)
            ];
            
        } catch (Exception $e) {
            error_log("Facebook auth error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore autenticazione Facebook'];
        }
    }
    
    private function exchangeGoogleCode($code) {
        $data = [
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => BASE_URL . '/auth/google/callback'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Failed to exchange Google code for token');
        }
        
        return json_decode($response, true);
    }
    
    private function exchangeFacebookCode($code) {
        $params = [
            'client_id' => FACEBOOK_APP_ID,
            'client_secret' => FACEBOOK_APP_SECRET,
            'code' => $code,
            'redirect_uri' => BASE_URL . '/auth/facebook/callback'
        ];
        
        $url = 'https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query($params);
        
        $response = file_get_contents($url);
        if (!$response) {
            throw new Exception('Failed to exchange Facebook code for token');
        }
        
        return json_decode($response, true);
    }
    
    private function getGoogleUserInfo($accessToken) {
        $url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $accessToken;
        
        $response = file_get_contents($url);
        if (!$response) {
            throw new Exception('Failed to get Google user info');
        }
        
        $userInfo = json_decode($response, true);
        
        return [
            'id' => $userInfo['id'],
            'email' => $userInfo['email'],
            'name' => $userInfo['name'],
            'picture' => $userInfo['picture'] ?? null,
            'provider' => 'google'
        ];
    }
    
    private function getFacebookUserInfo($accessToken) {
        $url = 'https://graph.facebook.com/me?fields=id,name,email,picture&access_token=' . $accessToken;
        
        $response = file_get_contents($url);
        if (!$response) {
            throw new Exception('Failed to get Facebook user info');
        }
        
        $userInfo = json_decode($response, true);
        
        return [
            'id' => $userInfo['id'],
            'email' => $userInfo['email'] ?? null,
            'name' => $userInfo['name'],
            'picture' => $userInfo['picture']['data']['url'] ?? null,
            'provider' => 'facebook'
        ];
    }
    
    private function createOrUpdateSocialUser($socialUserInfo, $provider) {
        try {
            // Check if user exists by email
            if ($socialUserInfo['email']) {
                $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$socialUserInfo['email']]);
                $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingUser) {
                    // Update existing user with social info
                    $stmt = $this->pdo->prepare("
                        UPDATE users 
                        SET avatar_url = ?, last_login = NOW(), updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $socialUserInfo['picture'],
                        $existingUser['id']
                    ]);
                    
                    return $existingUser;
                }
            }
            
            // Create new user
            $stmt = $this->pdo->prepare("
                INSERT INTO users (
                    name, email, avatar_url, type, email_verified, 
                    password_hash, created_at, updated_at
                ) VALUES (?, ?, ?, 'student', TRUE, '', NOW(), NOW())
            ");
            
            $stmt->execute([
                $socialUserInfo['name'],
                $socialUserInfo['email'],
                $socialUserInfo['picture']
            ]);
            
            $userId = $this->pdo->lastInsertId();
            
            // Get the created user
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Create/update social user error: " . $e->getMessage());
            throw new Exception('Errore durante la creazione/aggiornamento utente');
        }
    }
    
    private function generateJWTToken($user) {
        $userClass = new User($this->pdo);
        return $userClass->generateToken($user);
    }
}
?>
