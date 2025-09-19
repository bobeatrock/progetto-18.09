<?php
class Analytics {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get real-time platform statistics
     */
    public function getPlatformStats() {
        try {
            $stats = [];
            
            // Total active venues
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM venues WHERE active = TRUE");
            $stmt->execute();
            $stats['total_venues'] = $stmt->fetchColumn();
            
            // Total registered users
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email_verified = TRUE");
            $stmt->execute();
            $stats['total_users'] = $stmt->fetchColumn();
            
            // Total completed bookings
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'completed'");
            $stmt->execute();
            $stats['total_bookings'] = $stmt->fetchColumn();
            
            // Total reviews
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reviews WHERE approved = TRUE");
            $stmt->execute();
            $stats['total_reviews'] = $stmt->fetchColumn();
            
            // Average rating across all venues
            $stmt = $this->pdo->prepare("
                SELECT ROUND(AVG(rating), 1) 
                FROM reviews 
                WHERE approved = TRUE AND rating > 0
            ");
            $stmt->execute();
            $avgRating = $stmt->fetchColumn();
            $stats['average_rating'] = $avgRating ?: 0;
            
            // Total revenue (completed bookings only)
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0) 
                FROM bookings 
                WHERE payment_status = 'paid' AND status = 'completed'
            ");
            $stmt->execute();
            $stats['total_revenue'] = $stmt->fetchColumn();
            
            // This month's bookings
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM bookings 
                WHERE YEAR(created_at) = YEAR(CURDATE()) 
                AND MONTH(created_at) = MONTH(CURDATE())
            ");
            $stmt->execute();
            $stats['month_bookings'] = $stmt->fetchColumn();
            
            // This month's revenue
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0) 
                FROM bookings 
                WHERE payment_status = 'paid' 
                AND YEAR(created_at) = YEAR(CURDATE()) 
                AND MONTH(created_at) = MONTH(CURDATE())
            ");
            $stmt->execute();
            $stats['month_revenue'] = $stmt->fetchColumn();
            
            return [
                'success' => true,
                'stats' => $stats,
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Analytics error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Errore nel caricamento statistiche'
            ];
        }
    }
    
    /**
     * Get venue-specific statistics
     */
    public function getVenueStats($venueId) {
        try {
            $stats = [];
            
            // Total bookings for this venue
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE venue_id = ?");
            $stmt->execute([$venueId]);
            $stats['total_bookings'] = $stmt->fetchColumn();
            
            // Completed bookings
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE venue_id = ? AND status = 'completed'
            ");
            $stmt->execute([$venueId]);
            $stats['completed_bookings'] = $stmt->fetchColumn();
            
            // Total revenue
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0) FROM bookings 
                WHERE venue_id = ? AND payment_status = 'paid'
            ");
            $stmt->execute([$venueId]);
            $stats['total_revenue'] = $stmt->fetchColumn();
            
            // Average rating
            $stmt = $this->pdo->prepare("
                SELECT ROUND(AVG(rating), 1) FROM reviews 
                WHERE venue_id = ? AND approved = TRUE
            ");
            $stmt->execute([$venueId]);
            $avgRating = $stmt->fetchColumn();
            $stats['average_rating'] = $avgRating ?: 0;
            
            // Total reviews
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM reviews 
                WHERE venue_id = ? AND approved = TRUE
            ");
            $stmt->execute([$venueId]);
            $stats['total_reviews'] = $stmt->fetchColumn();
            
            // This month's bookings
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE venue_id = ? 
                AND YEAR(created_at) = YEAR(CURDATE()) 
                AND MONTH(created_at) = MONTH(CURDATE())
            ");
            $stmt->execute([$venueId]);
            $stats['month_bookings'] = $stmt->fetchColumn();
            
            // This month's revenue
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0) FROM bookings 
                WHERE venue_id = ? AND payment_status = 'paid'
                AND YEAR(created_at) = YEAR(CURDATE()) 
                AND MONTH(created_at) = MONTH(CURDATE())
            ");
            $stmt->execute([$venueId]);
            $stats['month_revenue'] = $stmt->fetchColumn();
            
            // Today's bookings
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE venue_id = ? AND DATE(created_at) = CURDATE()
            ");
            $stmt->execute([$venueId]);
            $stats['today_bookings'] = $stmt->fetchColumn();
            
            // Calculate occupancy rate (simplified)
            $stmt = $this->pdo->prepare("
                SELECT capacity_max FROM venues WHERE id = ?
            ");
            $stmt->execute([$venueId]);
            $maxCapacity = $stmt->fetchColumn();
            
            if ($maxCapacity > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT COALESCE(AVG(guests), 0) FROM bookings 
                    WHERE venue_id = ? AND status IN ('confirmed', 'completed')
                    AND YEAR(created_at) = YEAR(CURDATE()) 
                    AND MONTH(created_at) = MONTH(CURDATE())
                ");
                $stmt->execute([$venueId]);
                $avgGuests = $stmt->fetchColumn();
                $stats['occupancy_rate'] = round(($avgGuests / $maxCapacity) * 100, 1);
            } else {
                $stats['occupancy_rate'] = 0;
            }
            
            return [
                'success' => true,
                'stats' => $stats,
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Venue analytics error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Errore nel caricamento statistiche venue'
            ];
        }
    }
    
    /**
     * Get user-specific statistics
     */
    public function getUserStats($userId) {
        try {
            $stats = [];
            
            // Total bookings
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stats['total_bookings'] = $stmt->fetchColumn();
            
            // Completed bookings
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE user_id = ? AND status = 'completed'
            ");
            $stmt->execute([$userId]);
            $stats['completed_bookings'] = $stmt->fetchColumn();
            
            // Total spent
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(total_amount), 0) FROM bookings 
                WHERE user_id = ? AND payment_status = 'paid'
            ");
            $stmt->execute([$userId]);
            $stats['total_spent'] = $stmt->fetchColumn();
            
            // Reviews written
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stats['reviews_written'] = $stmt->fetchColumn();
            
            // Favorite venues count
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM user_favorites 
                WHERE user_id = ? AND active = TRUE
            ");
            $stmt->execute([$userId]);
            $stats['favorite_venues'] = $stmt->fetchColumn();
            
            // Upcoming bookings
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE user_id = ? AND event_date >= CURDATE() 
                AND status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$userId]);
            $stats['upcoming_bookings'] = $stmt->fetchColumn();
            
            return [
                'success' => true,
                'stats' => $stats,
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("User analytics error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Errore nel caricamento statistiche utente'
            ];
        }
    }
    
    /**
     * Record a page view for analytics
     */
    public function recordPageView($page, $userId = null, $sessionId = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO page_views (page, user_id, session_id, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $page,
                $userId,
                $sessionId ?: session_id(),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Page view recording error: " . $e->getMessage());
            return ['success' => false];
        }
    }
    
    /**
     * Get popular venues based on real data
     */
    public function getPopularVenues($limit = 6) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT v.*, 
                       COUNT(DISTINCT b.id) as booking_count,
                       COUNT(DISTINCT r.id) as review_count,
                       COALESCE(AVG(r.rating), 0) as avg_rating
                FROM venues v
                LEFT JOIN bookings b ON v.id = b.venue_id AND b.status = 'completed'
                LEFT JOIN reviews r ON v.id = r.venue_id AND r.approved = TRUE
                WHERE v.active = TRUE
                GROUP BY v.id
                ORDER BY booking_count DESC, review_count DESC, avg_rating DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format the data
            foreach ($venues as &$venue) {
                $venue['rating_display'] = $venue['avg_rating'] > 0 ? 
                    number_format($venue['avg_rating'], 1) : 'Nuovo';
                $venue['reviews_count'] = (int)$venue['review_count'];
                $venue['bookings_count'] = (int)$venue['booking_count'];
            }
            
            return [
                'success' => true,
                'venues' => $venues
            ];
            
        } catch (Exception $e) {
            error_log("Popular venues error: " . $e->getMessage());
            return [
                'success' => false,
                'venues' => []
            ];
        }
    }
    
    /**
     * Clean up old analytics data
     */
    public function cleanupOldData($daysToKeep = 365) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM page_views 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$daysToKeep]);
            
            return [
                'success' => true,
                'message' => 'Cleanup completato'
            ];
            
        } catch (Exception $e) {
            error_log("Analytics cleanup error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Errore durante il cleanup'
            ];
        }
    }
}
?>
