<?php
// Database Configuration (SiteGround)
define('DB_HOST', 'localhost');
define('DB_NAME', 'db3f3fghmugr3m');        // ← Nome database SiteGround
define('DB_USER', 'u63ymqsoqe3ww');         // ← Utente SiteGround  
define('DB_PASS', 'Mi81099585!');      

// Site URLs
define('BASE_URL', 'https://festalaurea.eu');
define('API_URL', BASE_URL . '/api');

// Email Configuration (SiteGround SMTP)
define('SMTP_HOST', 'mail.festalaurea.eu');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@festalaurea.eu');
define('SMTP_PASSWORD', 'your_email_password');

// Stripe Configuration
define('STRIPE_SECRET_KEY', 'sk_test_your_stripe_secret_key');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_your_stripe_publishable_key');
define('STRIPE_WEBHOOK_SECRET', 'whsec_your_webhook_secret');

// Security
define('JWT_SECRET', 'your_super_secret_jwt_key_change_this_in_production');
define('PASSWORD_PEPPER', 'your_password_pepper_for_extra_security');

// Google Services
define('GOOGLE_MAPS_API_KEY', 'your_google_maps_api_key');

// Business Info
define('BUSINESS_NAME', 'FestaLaurea');
define('BUSINESS_EMAIL', 'info@festalaurea.eu');
define('BUSINESS_PHONE', '+39 049 123 4567');

// Development/Production
define('DEBUG_MODE', false);
define('ERROR_REPORTING', DEBUG_MODE ? E_ALL : 0);

// Set error reporting
error_reporting(ERROR_REPORTING);

// Timezone
date_default_timezone_set('Europe/Rome');
?>