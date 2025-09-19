# ========================================
# FESTALAUREA - ENVIRONMENT VARIABLES
# ========================================
# Copy this file to .env and update with your values

# Application
APP_NAME=FestaLaurea
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/festalaurea

# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=festalaurea_db
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4

# Email (SiteGround SMTP)
SMTP_HOST=mail.festalaurea.eu
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=noreply@festalaurea.eu
SMTP_PASSWORD=your_email_password
SMTP_FROM_EMAIL=noreply@festalaurea.eu
SMTP_FROM_NAME=FestaLaurea

# Stripe
STRIPE_PUBLISHABLE_KEY=pk_test_your_publishable_key
STRIPE_SECRET_KEY=sk_test_your_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret

# PayPal
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_SECRET=your_paypal_secret
PAYPAL_MODE=sandbox

# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_google_client_secret

# Facebook OAuth
FACEBOOK_APP_ID=your_facebook_app_id
FACEBOOK_APP_SECRET=your_facebook_app_secret

# Security
JWT_SECRET=your_super_secret_jwt_key_change_this_in_production
JWT_EXPIRY=86400
PASSWORD_SALT=festalaurea_salt_2024
ENCRYPTION_KEY=your_32_character_encryption_key

# Session
SESSION_LIFETIME=3600
SESSION_NAME=festalaurea_session
SESSION_SECURE=false
SESSION_HTTPONLY=true

# Upload
MAX_UPLOAD_SIZE=10485760
UPLOAD_PATH=/uploads

# Cache
CACHE_ENABLED=true
CACHE_EXPIRY=3600
CACHE_PATH=/cache

# Business
COMMISSION_RATE=0.10
MINIMUM_GUESTS=10
MAXIMUM_GUESTS=500
ADVANCE_BOOKING_DAYS=7
CANCELLATION_DAYS=3

# API Rate Limiting
API_RATE_LIMIT=100
API_RATE_WINDOW=3600

# Logging
LOG_ERRORS=true
LOG_PATH=/logs
LOG_LEVEL=debug

# Maintenance
MAINTENANCE_MODE=false
MAINTENANCE_MESSAGE="Il sito è in manutenzione. Torneremo presto!"