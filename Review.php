<?php
class Review {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function createReview($userId, $data) {
        try {
            // Validate required fields
            $required = ['venue_id', 'rating'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Campo {$field} obbligatorio"];
                }
            }
            
            // Validate rating range
            if ($data['rating'] < 1 || $data['rating'] > 5) {
                return ['success' => false, 'message' => 'Rating deve essere tra 1 e 5'];
            }
            
            // Check if user can review this venue
            $canReview = $this->canUserReview($userId, $data['venue_id']);
            
            if (!$canReview['can_review']) {
                return ['success' => false, 'message' => $canReview['reason']];
            }
            
            // Insert verified review
            $stmt = $this->pdo->prepare("
                INSERT INTO reviews (user_id, venue_id, booking_id, rating, title, comment, verified) 
                VALUES (?, ?, ?, ?, ?, ?, TRUE)
            ");
            $stmt->execute([
                $userId,
                $data['venue_id'],
                $canReview['booking_id'],
                $data['rating'],
                $data['title'] ?? null,
                $data['comment'] ?? null
            ]);
            
            $reviewId = $this->pdo->lastInsertId();
            
            // Rating will be updated automatically by database trigger
            
            return [
                'success' => true, 
                'message' => 'Recensione pubblicata con successo',
                'review_id' => $reviewId
            ];
            
        } catch (Exception $e) {
            error_log("Create review error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante la pubblicazione della recensione'];
        }
    }
    
    public function canUserReview($userId, $venueId) {
        try {
            // Check if user has completed bookings at this venue
            $stmt = $this->pdo->prepare("
                SELECT b.id, b.event_date, b.confirmation_code
                FROM bookings b
                WHERE b.user_id = ? AND b.venue_id = ? 
                AND b.status = 'completed' 
                AND b.event_date < CURDATE()
                AND NOT EXISTS (
                    SELECT 1 FROM reviews r 
                    WHERE r.user_id = ? AND r.venue_id = ? AND r.booking_id = b.id
                )
                ORDER BY b.event_date DESC
                LIMIT 1
            ");
            $stmt->execute([$userId, $venueId, $userId, $venueId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                // Check if user has any completed bookings at all
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) FROM bookings 
                    WHERE user_id = ? AND venue_id = ? AND status = 'completed'
                ");
                $stmt->execute([$userId, $venueId]);
                $hasCompletedBookings = $stmt->fetchColumn() > 0;
                
                if (!$hasCompletedBookings) {
                    return [
                        'can_review' => false,
                        'reason' => 'Puoi recensire solo dopo aver completato un evento in questo locale',
                        'booking_id' => null
                    ];
                } else {
                    return [
                        'can_review' => false,
                        'reason' => 'Hai già recensito questo locale per tutte le tue prenotazioni',
                        'booking_id' => null
                    ];
                }
            }
            
            return [
                'can_review' => true,
                'reason' => 'Puoi lasciare una recensione',
                'booking_id' => $booking['id']
            ];
            
        } catch (Exception $e) {
            error_log("Can review check error: " . $e->getMessage());
            return [
                'can_review' => false, 
                'reason' => 'Errore nella verifica dei permessi',
                'booking_id' => null
            ];
        }
    }
    
    public function getVenueReviews($venueId, $limit = 10, $offset = 0) {
        try {
            // Get verified reviews with user info
            $stmt = $this->pdo->prepare("
                SELECT r.*, u.name as user_name, u.department,
                       b.event_date, b.confirmation_code
                FROM reviews r 
                JOIN users u ON r.user_id = u.id 
                LEFT JOIN bookings b ON r.booking_id = b.id
                WHERE r.venue_id = ? AND r.verified = TRUE
                ORDER BY r.created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$venueId, $limit, $offset]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM reviews 
                WHERE venue_id = ? AND verified = TRUE
            ");
            $stmt->execute([$venueId]);
            $totalReviews = $stmt->fetchColumn();
            
            // Get rating distribution
            $stmt = $this->pdo->prepare("
                SELECT rating, COUNT(*) as count
                FROM reviews 
                WHERE venue_id = ? AND verified = TRUE
                GROUP BY rating
                ORDER BY rating DESC
            ");
            $stmt->execute([$venueId]);
            $ratingDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true, 
                'reviews' => $reviews,
                'total_reviews' => $totalReviews,
                'rating_distribution' => $ratingDistribution
            ];
            
        } catch (Exception $e) {
            error_log("Get venue reviews error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel caricamento delle recensioni'];
        }
    }
    
    public function getUserReviews($userId, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT r.*, v.name as venue_name, v.slug as venue_slug
                FROM reviews r 
                JOIN venues v ON r.venue_id = v.id
                WHERE r.user_id = ?
                ORDER BY r.created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'reviews' => $reviews];
            
        } catch (Exception $e) {
            error_log("Get user reviews error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel caricamento delle tue recensioni'];
        }
    }
    
    public function updateReview($reviewId, $userId, $data) {
        try {
            // Verify review ownership
            $stmt = $this->pdo->prepare("
                SELECT id FROM reviews 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$reviewId, $userId]);
            
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Recensione non trovata o non autorizzato'];
            }
            
            // Update review
            $stmt = $this->pdo->prepare("
                UPDATE reviews 
                SET rating = ?, title = ?, comment = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $data['rating'],
                $data['title'] ?? null,
                $data['comment'] ?? null,
                $reviewId,
                $userId
            ]);
            
            // Rating will be updated automatically by database trigger
            
            return ['success' => true, 'message' => 'Recensione aggiornata'];
            
        } catch (Exception $e) {
            error_log("Update review error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'aggiornamento'];
        }
    }
    
    public function deleteReview($reviewId, $userId) {
        try {
            // Verify review ownership
            $stmt = $this->pdo->prepare("
                SELECT id FROM reviews 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$reviewId, $userId]);
            
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Recensione non trovata o non autorizzato'];
            }
            
            // Delete review
            $stmt = $this->pdo->prepare("DELETE FROM reviews WHERE id = ? AND user_id = ?");
            $stmt->execute([$reviewId, $userId]);
            
            // Rating will be updated automatically by database trigger
            
            return ['success' => true, 'message' => 'Recensione eliminata'];
            
        } catch (Exception $e) {
            error_log("Delete review error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'eliminazione'];
        }
    }
    
    public function markReviewHelpful($reviewId, $userId) {
        try {
            // Check if user already marked this review as helpful
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM review_helpful 
                WHERE review_id = ? AND user_id = ?
            ");
            $stmt->execute([$reviewId, $userId]);
            
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Hai già segnalato questa recensione come utile'];
            }
            
            // Add helpful mark
            $stmt = $this->pdo->prepare("
                INSERT INTO review_helpful (review_id, user_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$reviewId, $userId]);
            
            // Update helpful count
            $stmt = $this->pdo->prepare("
                UPDATE reviews 
                SET helpful_count = helpful_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$reviewId]);
            
            return ['success' => true, 'message' => 'Recensione segnalata come utile'];
            
        } catch (Exception $e) {
            error_log("Mark helpful error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante la segnalazione'];
        }
    }
}
?>