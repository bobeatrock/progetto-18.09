<?php
// ========================================
// FESTALAUREA - CONFIGURATION FILE
// ========================================

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Europe/Rome');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'festalaurea_db');
define('DB_USER', 'root');  // Change in production
define('DB_PASS', '');      // Change in production
define('DB_CHARSET', 'utf8mb4');

// Application URLs
define('BASE_URL', 'http://localhost/festalaurea');  // Change in production
define('API_URL', BASE_URL . '/api');
define('ADMIN_URL', BASE_URL . '/admin');
define('ASSETS_URL', BASE_URL . '/assets');

// Application Paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('CLASSES_PATH', ROOT_PATH . '/classes');
define('API_PATH', ROOT_PATH . '/api');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// Email Configuration (for SiteGround)
define('SMTP_HOST', 'mail.festalaurea.eu');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'noreply@festalaurea.eu');
define('SMTP_PASSWORD', 'your_email_password');  // Change this
define('SMTP_FROM_EMAIL', 'noreply@festalaurea.eu');
define('SMTP_FROM_NAME', 'FestaLaurea');

// Stripe Configuration
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_51234567890abcdef');  // Change this
define('STRIPE_SECRET_KEY', 'sk_test_51234567890abcdef');       // Change this
define('STRIPE_WEBHOOK_SECRET', 'whsec_1234567890abcdef');      // Change this

// PayPal Configuration
define('PAYPAL_CLIENT_ID', 'your_paypal_client_id');           // Change this
define('PAYPAL_SECRET', 'your_paypal_secret');                 // Change this
define('PAYPAL_MODE', 'sandbox');  // Change to 'live' in production

// Social Login Configuration
define('GOOGLE_CLIENT_ID', 'your_google_client_id.apps.googleusercontent.com');  // Change this
define('GOOGLE_CLIENT_SECRET', 'your_google_client_secret');                     // Change this

define('FACEBOOK_APP_ID', 'your_facebook_app_id');        // Change this
define('FACEBOOK_APP_SECRET', 'your_facebook_secret');    // Change this

// Security Configuration
define('JWT_SECRET', 'your_super_secret_jwt_key_change_this_in_production_123456');  // Change this!
define('JWT_EXPIRY', 86400);  // 24 hours
define('PASSWORD_SALT', 'festalaurea_salt_2024');  // Change this!
define('ENCRYPTION_KEY', 'your_32_character_encryption_key');  // Change this!

// Session Configuration
define('SESSION_LIFETIME', 3600);  // 1 hour
define('SESSION_NAME', 'festalaurea_session');
define('SESSION_SECURE', false);  // Set to true if using HTTPS
define('SESSION_HTTPONLY', true);

// Upload Configuration
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);  // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx']);

// Pagination
define('ITEMS_PER_PAGE', 12);
define('MAX_PAGINATION_LINKS', 5);

// Cache Configuration
define('CACHE_ENABLED', true);
define('CACHE_EXPIRY', 3600);  // 1 hour
define('CACHE_PATH', ROOT_PATH . '/cache');

// Business Configuration
define('COMMISSION_RATE', 0.10);  // 10% commission
define('MINIMUM_GUESTS', 10);
define('MAXIMUM_GUESTS', 500);
define('ADVANCE_BOOKING_DAYS', 7);  // Minimum days in advance
define('CANCELLATION_DAYS', 3);     // Days before event for free cancellation

// SEO Configuration
define('SITE_NAME', 'FestaLaurea');
define('SITE_TITLE', 'FestaLaurea - Organizza la Tua Festa di Laurea Perfetta');
define('SITE_DESCRIPTION', 'La piattaforma n°1 per organizzare feste di laurea. Confronta locali, prenota online, gestisci invitati.');
define('SITE_KEYWORDS', 'festa laurea, organizzare festa, locali roma, prenotazione ristorante, party laurea');
define('SITE_AUTHOR', 'FestaLaurea S.r.l.');

// API Rate Limiting
define('API_RATE_LIMIT', 100);  // Requests per hour
define('API_RATE_WINDOW', 3600);  // 1 hour

// Logging
define('LOG_ERRORS', true);
define('LOG_PATH', ROOT_PATH . '/logs');
define('LOG_LEVEL', 'debug');  // debug, info, warning, error

// Maintenance Mode
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', 'Il sito è in manutenzione. Torneremo presto!');

// Debug Mode (disable in production)
define('DEBUG_MODE', true);

// Load environment variables if .env file exists
if (file_exists(ROOT_PATH . '/.env')) {
    $env = parse_ini_file(ROOT_PATH . '/.env');
    foreach ($env as $key => $value) {
        if (!defined($key)) {
            define($key, $value);
        }
    }
}

// Auto-load classes
spl_autoload_register(function ($class) {
    $file = CLASSES_PATH . '/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});