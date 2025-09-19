-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    department ENUM('ingegneria', 'medicina', 'economia', 'lettere', 'scienze', 'giurisprudenza', 'psicologia', 'scienze-politiche'),
    university VARCHAR(100) DEFAULT 'Università di Padova',
    graduation_year YEAR,
    password_hash VARCHAR(255) NOT NULL,
    type ENUM('student', 'venue_owner', 'admin') DEFAULT 'student',
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255),
    avatar_url VARCHAR(500),
    preferences JSON,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_type (type),
    INDEX idx_department (department)
);

-- Venues table
CREATE TABLE venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('restaurant', 'pub', 'club', 'outdoor', 'hotel', 'agriturismo', 'villa') NOT NULL,
    description TEXT,
    address VARCHAR(500) NOT NULL,
    city VARCHAR(100) DEFAULT 'Padova',
    province VARCHAR(10) DEFAULT 'PD',
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    phone VARCHAR(20),
    email VARCHAR(255),
    website VARCHAR(255),
    price_min DECIMAL(10,2) NOT NULL,
    price_max DECIMAL(10,2) NOT NULL,
    capacity_min INT NOT NULL,
    capacity_max INT NOT NULL,
    rating DECIMAL(3,2) DEFAULT 0.00,
    reviews_count INT DEFAULT 0,
    featured BOOLEAN DEFAULT FALSE,
    active BOOLEAN DEFAULT TRUE,
    images JSON,
    amenities JSON,
    menu_options JSON,
    business_hours JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_type (type),
    INDEX idx_city (city),
    INDEX idx_featured (featured),
    INDEX idx_active (active),
    FULLTEXT idx_search (name, description)
);

-- Bookings table
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    venue_id INT NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    guests INT NOT NULL,
    menu_type VARCHAR(50),
    notes TEXT,
    total_amount DECIMAL(10,2) NOT NULL,
    deposit_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    stripe_payment_intent_id VARCHAR(255),
    confirmation_code VARCHAR(20) UNIQUE,
    cancelled_at TIMESTAMP NULL,
    cancellation_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_venue (venue_id),
    INDEX idx_status (status),
    INDEX idx_event_date (event_date),
    UNIQUE KEY idx_venue_datetime (venue_id, event_date, event_time)
);

-- Reviews table (SISTEMA RECENSIONI REALI)
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    venue_id INT NOT NULL,
    booking_id INT,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(255),
    comment TEXT,
    photos JSON,
    helpful_count INT DEFAULT 0,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    INDEX idx_venue (venue_id),
    INDEX idx_rating (rating),
    INDEX idx_verified (verified),
    UNIQUE KEY idx_user_booking (user_id, booking_id)
);

-- Messages table
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    message TEXT NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_recipient (recipient_id),
    INDEX idx_read (read_at)
);

-- ========================================
-- SOLO UTENTE AMMINISTRATORE
-- ========================================
INSERT INTO users (name, email, password_hash, type, email_verified) VALUES
('Admin FestaLaurea', 'admin@festalaurea.eu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE);

-- ========================================
-- TRIGGER PER AGGIORNAMENTO AUTOMATICO RATING
-- ========================================
DELIMITER //

CREATE TRIGGER update_venue_rating_after_review_insert
AFTER INSERT ON reviews
FOR EACH ROW
BEGIN
    DECLARE avg_rating DECIMAL(3,2);
    DECLARE review_count INT;
    
    SELECT AVG(rating), COUNT(*) 
    INTO avg_rating, review_count
    FROM reviews 
    WHERE venue_id = NEW.venue_id AND verified = TRUE;
    
    UPDATE venues 
    SET rating = COALESCE(avg_rating, 0.00), 
        reviews_count = review_count
    WHERE id = NEW.venue_id;
END//

CREATE TRIGGER update_venue_rating_after_review_update
AFTER UPDATE ON reviews
FOR EACH ROW
BEGIN
    DECLARE avg_rating DECIMAL(3,2);
    DECLARE review_count INT;
    
    SELECT AVG(rating), COUNT(*) 
    INTO avg_rating, review_count
    FROM reviews 
    WHERE venue_id = NEW.venue_id AND verified = TRUE;
    
    UPDATE venues 
    SET rating = COALESCE(avg_rating, 0.00), 
        reviews_count = review_count
    WHERE id = NEW.venue_id;
END//

CREATE TRIGGER update_venue_rating_after_review_delete
AFTER DELETE ON reviews
FOR EACH ROW
BEGIN
    DECLARE avg_rating DECIMAL(3,2);
    DECLARE review_count INT;
    
    SELECT AVG(rating), COUNT(*) 
    INTO avg_rating, review_count
    FROM reviews 
    WHERE venue_id = OLD.venue_id AND verified = TRUE;
    
    UPDATE venues 
    SET rating = COALESCE(avg_rating, 0.00), 
        reviews_count = review_count
    WHERE id = OLD.venue_id;
END//

DELIMITER ;