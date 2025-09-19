<?php
class VenueManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get all venues with real statistics
     */
    public function getAllVenues($filters = []) {
        try {
            $whereConditions = ['v.active = TRUE'];
            $params = [];
            
            // Apply filters
            if (!empty($filters['type'])) {
                $whereConditions[] = 'v.type = ?';
                $params[] = $filters['type'];
            }
            
            if (!empty($filters['price_min'])) {
                $whereConditions[] = 'v.price_min >= ?';
                $params[] = $filters['price_min'];
            }
            
            if (!empty($filters['price_max'])) {
                $whereConditions[] = 'v.price_max <= ?';
                $params[] = $filters['price_max'];
            }
            
            if (!empty($filters['capacity_min'])) {
                $whereConditions[] = 'v.capacity_max >= ?';
                $params[] = $filters['capacity_min'];
            }
            
            if (!empty($filters['zone'])) {
                $whereConditions[] = 'v.zone = ?';
                $params[] = $filters['zone'];
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            $sql = "
                SELECT v.*,
                       COUNT(DISTINCT r.id) as reviews_count,
                       COALESCE(AVG(r.rating), 0) as average_rating,
                       COUNT(DISTINCT b.id) as bookings_count
                FROM venues v
                LEFT JOIN reviews r ON v.id = r.venue_id AND r.approved = TRUE
                LEFT JOIN bookings b ON v.id = b.venue_id AND b.status = 'completed'
                WHERE {$whereClause}
                GROUP BY v.id
                ORDER BY bookings_count DESC, average_rating DESC, reviews_count DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format the data
            foreach ($venues as &$venue) {
                $venue = $this->formatVenueData($venue);
            }
            
            return [
                'success' => true,
                'venues' => $venues,
                'total' => count($venues)
            ];
            
        } catch (Exception $e) {
            error_log("Get venues error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Errore nel caricamento locali: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get featured venues based on real performance metrics
     */
    public function getFeaturedVenues($limit = 6) {
        try {
            $sql = "
                SELECT v.*,
                       COUNT(DISTINCT r.id) as reviews_count,
                       COALESCE(AVG(r.rating), 0) as average_rating,
                       COUNT(DISTINCT b.id) as bookings_count,
                       COUNT(DISTINCT f.id) as favorites_count
                FROM venues v
                LEFT JOIN reviews r ON v.id = r.venue_id AND r.approved = TRUE
                LEFT JOIN bookings b ON v.id = b.venue_id AND b.status = 'completed'
                LEFT JOIN user_favorites f ON v.id = f.venue_id AND f.active = TRUE
                WHERE v.active = TRUE AND v.featured = TRUE
                GROUP BY v.id
                HAVING (bookings_count > 0 OR reviews_count > 0 OR favorites_count > 0)
                ORDER BY 
                    (bookings_count * 0.4 + reviews_count * 0.3 + favorites_count * 0.2 + average_rating * 0.1) DESC,
                    v.created_at DESC
                LIMIT ?
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);
            $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If no featured venues with activity, get newest venues
            if (empty($venues)) {
                $sql = "
                    SELECT v.*,
                           0 as reviews_count,
                           0 as average_rating,
                           0 as bookings_count,
                           0 as favorites_count
                    FROM venues v
                    WHERE v.active = TRUE
                    ORDER BY v.created_at DESC
                    LIMIT ?
                ";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$limit]);
                $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Format the data
            foreach ($venues as &$venue) {
                $venue = $this->formatVenueData($venue);
            }
            
            return [
                'success' => true,
                'venues' => $venues
            ];
            
        } catch (Exception $e) {
            error_log("Get featured venues error: " . $e->getMessage());
            return [
                'success' => false,
                'venues' => []
            ];
        }
    }
    
    /**
     * Get venue by ID with real statistics
     */
    public function getVenueById($id) {
        try {
            $sql = "
                SELECT v.*,
                       COUNT(DISTINCT r.id) as reviews_count,
                       COALESCE(AVG(r.rating), 0) as average_rating,
                       COUNT(DISTINCT b.id) as bookings_count
                FROM venues v
                LEFT JOIN reviews r ON v.id = r.venue_id AND r.approved = TRUE
                LEFT JOIN bookings b ON v.id = b.venue_id AND b.status = 'completed'
                WHERE v.id = ? AND v.active = TRUE
                GROUP BY v.id
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            $venue = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$venue) {
                return [
                    'success' => false,
                    'message' => 'Locale non trovato'
                ];
            }
            
            // Get recent reviews
            $reviewsSql = "
                SELECT r.*, u.name as user_name
                FROM reviews r
                JOIN users u ON r.user_id = u.id
                WHERE r.venue_id = ? AND r.approved = TRUE
                ORDER BY r.created_at DESC
                LIMIT 10
            ";
            
            $stmt = $this->pdo->prepare($reviewsSql);
            $stmt->execute([$id]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $venue = $this->formatVenueData($venue);
            $venue['reviews'] = $reviews;
            
            return [
                'success' => true,
                'venue' => $venue
            ];
            
        } catch (Exception $e) {
            error_log("Get venue by ID error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Errore nel caricamento locale'
            ];
        }
    }
    
    /**
     * Create a new venue
     */
    public function createVenue($ownerId, $data) {
        try {
            // Check if user already has a venue
            $stmt = $this->pdo->prepare("SELECT id FROM venues WHERE owner_id = ?");
            $stmt->execute([$ownerId]);
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Hai già un locale registrato. Contatta il supporto per gestire più locali.'
                ];
            }
            
            // Validate required fields
            $required = ['name', 'type', 'description', 'address', 'phone', 'email', 'price_min', 'price_max', 'capacity_min', 'capacity_max'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => "Campo {$field} obbligatorio"
                    ];
                }
            }
            
            // Insert venue
            $stmt = $this->pdo->prepare("
                INSERT INTO venues (
                    owner_id, name, type, description, address, phone, email, website,
                    price_min, price_max, capacity_min, capacity_max, zone,
                    active, featured, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FALSE, FALSE, NOW(), NOW())
            ");
            
            $stmt->execute([
                $ownerId,
                $data['name'],
                $data['type'],
                $data['description'],
                $data['address'],
                $data['phone'],
                $data['email'],
                $data['website'] ?? null,
                $data['price_min'],
                $data['price_max'],
                $data['capacity_min'],
                $data['capacity_max'],
                $this->extractZoneFromAddress($data['address'])
            ]);
            
            $venueId = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Locale creato con successo. Sarà attivato dopo la verifica da parte del nostro team.',
                'venue_id' => $venueId
            ];
            
        } catch (Exception $e) {
            error_log("Create venue error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Errore durante la creazione del locale'
            ];
        }
    }
    
    /**
     * Update venue information
     */
    public function updateVenue($venueId, $ownerId, $data) {
        try {
            // Verify ownership
            $stmt = $this->pdo->prepare("SELECT id FROM venues WHERE id = ? AND owner_id = ?");
            $stmt->execute([$venueId, $ownerId]);
            if (!$stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Non autorizzato a modificare questo locale'
                ];
            }
            
            // Update venue
            $stmt = $this->pdo->prepare("
                UPDATE venues SET
                    name = ?, type = ?, description = ?, address = ?, phone = ?, 
                    email = ?, website = ?, price_min = ?, price_max = ?, 
                    capacity_min = ?, capacity_max = ?, zone = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['name'],
                $data['type'],
                $data['description'],
                $data['address'],
                $data['phone'],
                $data['email'],
                $data['website'] ?? null,
                $data['price_min'],
                $data['price_max'],
                $data['capacity_min'],
                $data['capacity_max'],
                $this->extractZoneFromAddress($data['address']),
                $venueId
            ]);
            
            return [
                'success' => true,
                'message' => 'Locale aggiornato con successo'
            ];
            
        } catch (Exception $e) {
            error_log("Update venue error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Errore durante l\'aggiornamento del locale'
            ];
        }
    }
    
    /**
     * Get venue by owner
     */
    public function getVenueByOwner($ownerId) {
        try {
            $sql = "
                SELECT v.*,
                       COUNT(DISTINCT r.id) as reviews_count,
                       COALESCE(AVG(r.rating), 0) as average_rating,
                       COUNT(DISTINCT b.id) as bookings_count
                FROM venues v
                LEFT JOIN reviews r ON v.id = r.venue_id AND r.approved = TRUE
                LEFT JOIN bookings b ON v.id = b.venue_id
                WHERE v.owner_id = ?
                GROUP BY v.id
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ownerId]);
            $venue = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$venue) {
                return [
                    'success' => false,
                    'message' => 'Nessun locale trovato'
                ];
            }
            
            $venue = $this->formatVenueData($venue);
            
            return [
                'success' => true,
                'venue' => $venue
            ];
            
        } catch (Exception $e) {
            error_log("Get venue by owner error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Errore nel caricamento locale'
            ];
        }
    }
    
    /**
     * Format venue data for display
     */
    private function formatVenueData($venue) {
        $venue['reviews_count'] = (int)($venue['reviews_count'] ?? 0);
        $venue['bookings_count'] = (int)($venue['bookings_count'] ?? 0);
        $venue['average_rating'] = (float)($venue['average_rating'] ?? 0);
        
        // Format rating display
        if ($venue['average_rating'] > 0) {
            $venue['rating_display'] = number_format($venue['average_rating'], 1);
        } else {
            $venue['rating_display'] = 'Nuovo';
        }
        
        // Format price range
        $venue['price_range'] = "€{$venue['price_min']} - €{$venue['price_max']}";
        
        // Format capacity range
        $venue['capacity_range'] = "{$venue['capacity_min']}-{$venue['capacity_max']} persone";
        
        // Add status indicators
        $venue['has_reviews'] = $venue['reviews_count'] > 0;
        $venue['has_bookings'] = $venue['bookings_count'] > 0;
        $venue['is_popular'] = $venue['bookings_count'] >= 5 && $venue['average_rating'] >= 4.0;
        
        return $venue;
    }
    
    /**
     * Extract zone from address (simplified)
     */
    private function extractZoneFromAddress($address) {
        $address = strtolower($address);
        
        if (strpos($address, 'centro') !== false || strpos($address, 'piazza') !== false) {
            return 'centro';
        } elseif (strpos($address, 'stazione') !== false) {
            return 'stazione';
        } elseif (strpos($address, 'prato') !== false) {
            return 'prato';
        } elseif (strpos($address, 'arcella') !== false) {
            return 'arcella';
        } elseif (strpos($address, 'guizza') !== false) {
            return 'guizza';
        }
        
        return 'altro';
    }
}
?>
