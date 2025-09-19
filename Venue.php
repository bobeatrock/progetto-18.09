<?php
class Venue {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getAllVenues($filters = []) {
        $sql = "SELECT * FROM venues WHERE active = TRUE";
        $params = [];
        
        // Apply filters
        if (!empty($filters['type'])) {
            $sql .= " AND type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['price_min'])) {
            $sql .= " AND price_max >= ?";
            $params[] = $filters['price_min'];
        }
        
        if (!empty($filters['price_max'])) {
            $sql .= " AND price_min <= ?";
            $params[] = $filters['price_max'];
        }
        
        if (!empty($filters['capacity'])) {
            $sql .= " AND capacity_max >= ? AND capacity_min <= ?";
            $params[] = $filters['capacity'];
            $params[] = $filters['capacity'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE) OR name LIKE ?)";
            $params[] = $filters['search'];
            $params[] = '%' . $filters['search'] . '%';
        }
        
        $sql .= " ORDER BY featured DESC, rating DESC, reviews_count DESC";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // For venues with no reviews, show as "Nuovo" instead of 0.00
            foreach ($venues as &$venue) {
                $venue['rating_display'] = $venue['reviews_count'] > 0 ? $venue['rating'] : 'Nuovo';
                $venue['reviews_text'] = $venue['reviews_count'] == 1 ? '1 recensione' : 
                                       ($venue['reviews_count'] > 1 ? $venue['reviews_count'] . ' recensioni' : 'Nessuna recensione');
            }
            
            return ['success' => true, 'venues' => $venues];
            
        } catch (Exception $e) {
            error_log("Get venues error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel caricamento locali'];
        }
    }
    
    public function getFeaturedVenues($limit = 6) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT *, 
                       CASE WHEN reviews_count > 0 THEN rating ELSE 'Nuovo' END as rating_display,
                       CASE 
                           WHEN reviews_count = 0 THEN 'Nessuna recensione'
                           WHEN reviews_count = 1 THEN '1 recensione' 
                           ELSE CONCAT(reviews_count, ' recensioni')
                       END as reviews_text
                FROM venues 
                WHERE active = TRUE AND featured = TRUE 
                ORDER BY rating DESC, reviews_count DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'venues' => $venues];
            
        } catch (Exception $e) {
            error_log("Get featured venues error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel caricamento locali in evidenza'];
        }
    }
    
    public function getVenueById($id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT v.*, u.name as owner_name, u.email as owner_email
                FROM venues v
                LEFT JOIN users u ON v.owner_id = u.id
                WHERE v.id = ? AND v.active = TRUE
            ");
            $stmt->execute([$id]);
            $venue = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$venue) {
                return ['success' => false, 'message' => 'Locale non trovato'];
            }
            
            // Get verified reviews with rating distribution
            $review = new Review($this->pdo);
            $reviewsData = $review->getVenueReviews($id, 10);
            
            $venue['reviews'] = $reviewsData['reviews'] ?? [];
            $venue['total_reviews'] = $reviewsData['total_reviews'] ?? 0;
            $venue['rating_distribution'] = $reviewsData['rating_distribution'] ?? [];
            $venue['rating_display'] = $venue['reviews_count'] > 0 ? $venue['rating'] : 'Nuovo';
            
            // Get availability for next 30 days (simplified)
            $stmt = $this->pdo->prepare("
                SELECT event_date, COUNT(*) as bookings_count
                FROM bookings 
                WHERE venue_id = ? 
                AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                AND status NOT IN ('cancelled')
                GROUP BY event_date
            ");
            $stmt->execute([$id]);
            $availability = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $venue['availability'] = $availability;
            
            return ['success' => true, 'venue' => $venue];
            
        } catch (Exception $e) {
            error_log("Get venue error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel caricamento locale'];
        }
    }
    
    public function getVenueByOwner($ownerId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM venues WHERE owner_id = ?");
            $stmt->execute([$ownerId]);
            $venue = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$venue) {
                return ['success' => false, 'message' => 'Nessun locale associato'];
            }
            
            return ['success' => true, 'venue' => $venue];
            
        } catch (Exception $e) {
            error_log("Get venue by owner error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel caricamento locale'];
        }
    }
    
    public function getDashboardStats($ownerId) {
        try {
            // Get venue ID
            $stmt = $this->pdo->prepare("SELECT id FROM venues WHERE owner_id = ?");
            $stmt->execute([$ownerId]);
            $venue = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$venue) {
                return ['success' => false, 'message' => 'Locale non trovato'];
            }
            
            $venueId = $venue['id'];
            
            // Today's bookings
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM bookings 
                WHERE venue_id = ? AND event_date = CURDATE()
                AND status NOT IN ('cancelled')
            ");
            $stmt->execute([$venueId]);
            $todayBookings = $stmt->fetchColumn();
            
            // Month revenue (only paid bookings)
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0) as revenue 
                FROM bookings 
                WHERE venue_id = ? 
                AND MONTH(event_date) = MONTH(CURDATE()) 
                AND YEAR(event_date) = YEAR(CURDATE())
                AND payment_status = 'paid'
            ");
            $stmt->execute([$venueId]);
            $monthRevenue = $stmt->fetchColumn();
            
            // Occupancy rate (bookings in next 30 days vs available slots)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as bookings 
                FROM bookings 
                WHERE venue_id = ? 
                AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                AND status NOT IN ('cancelled')
            ");
            $stmt->execute([$venueId]);
            $futureBookings = $stmt->fetchColumn();
            $occupancyRate = min(($futureBookings / 30) * 100, 100);
            
            // Current rating (real from reviews)
            $stmt = $this->pdo->prepare("SELECT rating, reviews_count FROM venues WHERE id = ?");
            $stmt->execute([$venueId]);
            $ratingData = $stmt->fetch(PDO::FETCH_ASSOC);
            $averageRating = $ratingData['rating'] ?: 0;
            $reviewsCount = $ratingData['reviews_count'] ?: 0;
            
            // Pending bookings (require action)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE venue_id = ? AND status = 'pending'
            ");
            $stmt->execute([$venueId]);
            $pendingBookings = $stmt->fetchColumn();
            
            return [
                'success' => true,
                'stats' => [
                    'today_bookings' => $todayBookings,
                    'month_revenue' => number_format($monthRevenue, 0),
                    'occupancy_rate' => round($occupancyRate),
                    'average_rating' => $reviewsCount > 0 ? number_format($averageRating, 1) : 'Nuovo',
                    'reviews_count' => $reviewsCount,
                    'pending_bookings' => $pendingBookings
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get dashboard stats error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel caricamento statistiche'];
        }
    }
    
    public function updateVenueRating($venueId) {
        try {
            // Calculate real rating from verified reviews
            $stmt = $this->pdo->prepare("
                SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
                FROM reviews 
                WHERE venue_id = ? AND verified = TRUE
            ");
            $stmt->execute([$venueId]);
            $result = $stmt->fetch();
            
            // Update venue with authentic data
            $stmt = $this->pdo->prepare("
                UPDATE venues 
                SET rating = ?, reviews_count = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $result['avg_rating'] ? round($result['avg_rating'], 2) : 0.00,
                $result['total_reviews'] ?: 0,
                $venueId
            ]);
            
            return ['success' => true, 'message' => 'Rating aggiornato'];
            
        } catch (Exception $e) {
            error_log("Update rating error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore aggiornamento rating'];
        }
    }
    
    public function createVenue($ownerId, $data) {
        try {
            // Validate required fields
            $required = ['name', 'type', 'description', 'address', 'phone', 'email', 'price_min', 'price_max', 'capacity_min', 'capacity_max'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Campo {$field} obbligatorio"];
                }
            }
            
            // Generate slug
            $slug = $this->generateSlug($data['name']);
            
            // Insert venue (starts inactive for admin approval)
            $stmt = $this->pdo->prepare("
                INSERT INTO venues (
                    owner_id, slug, name, type, description, address, city, 
                    phone, email, website, price_min, price_max, capacity_min, capacity_max,
                    active, featured
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FALSE, FALSE)
            ");
            
            $stmt->execute([
                $ownerId,
                $slug,
                $data['name'],
                $data['type'],
                $data['description'],
                $data['address'],
                $data['city'] ?? 'Padova',
                $data['phone'],
                $data['email'],
                $data['website'] ?? null,
                $data['price_min'],
                $data['price_max'],
                $data['capacity_min'],
                $data['capacity_max']
            ]);
            
            $venueId = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'venue_id' => $venueId,
                'message' => 'Locale creato. In attesa di approvazione amministratore.'
            ];
            
        } catch (Exception $e) {
            error_log("Create venue error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante la creazione del locale'];
        }
    }
    
    public function updateVenue($venueId, $ownerId, $data) {
        try {
            // Verify ownership
            $stmt = $this->pdo->prepare("SELECT id FROM venues WHERE id = ? AND owner_id = ?");
            $stmt->execute([$venueId, $ownerId]);
            
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Locale non trovato o non autorizzato'];
            }
            
            // Update venue data
            $stmt = $this->pdo->prepare("
                UPDATE venues 
                SET name = ?, description = ?, address = ?, phone = ?, 
                    email = ?, website = ?, price_min = ?, price_max = ?, 
                    capacity_min = ?, capacity_max = ?, updated_at = NOW()
                WHERE id = ? AND owner_id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['address'],
                $data['phone'],
                $data['email'],
                $data['website'] ?? null,
                $data['price_min'],
                $data['price_max'],
                $data['capacity_min'],
                $data['capacity_max'],
                $venueId,
                $ownerId
            ]);
            
            return ['success' => true, 'message' => 'Locale aggiornato'];
            
        } catch (Exception $e) {
            error_log("Update venue error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'aggiornamento'];
        }
    }
    
    public function getVenueAnalytics($ownerId, $period = '30') {
        try {
            // Get venue ID
            $stmt = $this->pdo->prepare("SELECT id FROM venues WHERE owner_id = ?");
            $stmt->execute([$ownerId]);
            $venueId = $stmt->fetchColumn();
            
            if (!$venueId) {
                return ['success' => false, 'message' => 'Locale non trovato'];
            }
            
            $analytics = [];
            
            // Bookings trend (last 30 days)
            $stmt = $this->pdo->prepare("
                SELECT DATE(created_at) as date, COUNT(*) as bookings
                FROM bookings 
                WHERE venue_id = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date
            ");
            $stmt->execute([$venueId, $period]);
            $analytics['bookings_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Revenue trend
            $stmt = $this->pdo->prepare("
                SELECT DATE(created_at) as date, SUM(total_amount) as revenue
                FROM bookings 
                WHERE venue_id = ? 
                AND payment_status = 'paid'
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date
            ");
            $stmt->execute([$venueId, $period]);
            $analytics['revenue_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Popular time slots
            $stmt = $this->pdo->prepare("
                SELECT event_time, COUNT(*) as bookings
                FROM bookings 
                WHERE venue_id = ? 
                AND status IN ('confirmed', 'completed')
                GROUP BY event_time
                ORDER BY bookings DESC
                LIMIT 5
            ");
            $stmt->execute([$venueId]);
            $analytics['popular_times'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group size distribution
            $stmt = $this->pdo->prepare("
                SELECT 
                    CASE 
                        WHEN guests <= 20 THEN '10-20'
                        WHEN guests <= 30 THEN '21-30'
                        WHEN guests <= 50 THEN '31-50'
                        ELSE '50+'
                    END as group_size,
                    COUNT(*) as count
                FROM bookings 
                WHERE venue_id = ?
                GROUP BY group_size
                ORDER BY count DESC
            ");
            $stmt->execute([$venueId]);
            $analytics['group_sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Recent reviews summary
            $stmt = $this->pdo->prepare("
                SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews,
                       SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive_reviews
                FROM reviews 
                WHERE venue_id = ? AND verified = TRUE
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$venueId, $period]);
            $reviewStats = $stmt->fetch(PDO::FETCH_ASSOC);
            $analytics['review_stats'] = $reviewStats;
            
            return ['success' => true, 'analytics' => $analytics];
            
        } catch (Exception $e) {
            error_log("Get venue analytics error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel caricamento analytics'];
        }
    }
    
    public function searchVenues($query, $filters = []) {
        try {
            $sql = "
                SELECT *, 
                       MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM venues 
                WHERE active = TRUE 
                AND (MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE) OR name LIKE ?)
            ";
            $params = [$query, $query, '%' . $query . '%'];
            
            // Apply additional filters
            if (!empty($filters['type'])) {
                $sql .= " AND type = ?";
                $params[] = $filters['type'];
            }
            
            if (!empty($filters['min_price'])) {
                $sql .= " AND price_min >= ?";
                $params[] = $filters['min_price'];
            }
            
            if (!empty($filters['max_price'])) {
                $sql .= " AND price_max <= ?";
                $params[] = $filters['max_price'];
            }
            
            if (!empty($filters['capacity'])) {
                $sql .= " AND capacity_min <= ? AND capacity_max >= ?";
                $params[] = $filters['capacity'];
                $params[] = $filters['capacity'];
            }
            
            $sql .= " ORDER BY relevance DESC, rating DESC, reviews_count DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add display formatting
            foreach ($venues as &$venue) {
                $venue['rating_display'] = $venue['reviews_count'] > 0 ? $venue['rating'] : 'Nuovo';
                $venue['reviews_text'] = $venue['reviews_count'] == 1 ? '1 recensione' : 
                                       ($venue['reviews_count'] > 1 ? $venue['reviews_count'] . ' recensioni' : 'Nessuna recensione');
            }
            
            return ['success' => true, 'venues' => $venues, 'total' => count($venues)];
            
        } catch (Exception $e) {
            error_log("Search venues error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nella ricerca'];
        }
    }
    
    private function generateSlug($name) {
        // Convert to lowercase and replace spaces/special chars with hyphens
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Check if slug exists and make unique
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    private function slugExists($slug) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM venues WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetchColumn() > 0;
    }
}
?>