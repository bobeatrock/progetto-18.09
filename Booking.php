<?php
class Booking {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function createBooking($userId, $data) {
        try {
            // Validate required fields
            $required = ['venue_id', 'event_date', 'event_time', 'guests', 'total_amount'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "Campo {$field} obbligatorio"];
                }
            }
            
            // Check venue availability
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE venue_id = ? AND event_date = ? AND event_time = ? 
                AND status NOT IN ('cancelled')
            ");
            $stmt->execute([$data['venue_id'], $data['event_date'], $data['event_time']]);
            
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Orario non disponibile'];
            }
            
            // Generate confirmation code
            $confirmationCode = 'FL' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Insert booking
            $stmt = $this->pdo->prepare("
                INSERT INTO bookings (
                    user_id, venue_id, event_date, event_time, guests, 
                    menu_type, notes, total_amount, deposit_amount, 
                    confirmation_code
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $data['venue_id'],
                $data['event_date'],
                $data['event_time'],
                $data['guests'],
                $data['menu_type'] ?? null,
                $data['notes'] ?? null,
                $data['total_amount'],
                $data['deposit_amount'],
                $confirmationCode
            ]);
            
            $bookingId = $this->pdo->lastInsertId();
            
            return [
                'success' => true,
                'booking_id' => $bookingId,
                'confirmation_code' => $confirmationCode
            ];
            
        } catch (Exception $e) {
            error_log("Create booking error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante la prenotazione'];
        }
    }
    
    public function getUserBookings($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT b.*, v.name as venue_name, v.address as venue_address, v.phone as venue_phone,
                       (CASE 
                        WHEN b.status = 'completed' AND b.event_date < CURDATE() 
                        AND NOT EXISTS (SELECT 1 FROM reviews WHERE booking_id = b.id)
                        THEN 1 ELSE 0 END) as can_review
                FROM bookings b
                JOIN venues v ON b.venue_id = v.id
                WHERE b.user_id = ?
                ORDER BY b.event_date DESC
            ");
            $stmt->execute([$userId]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'bookings' => $bookings];
            
        } catch (Exception $e) {
            error_log("Get user bookings error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel caricamento prenotazioni'];
        }
    }
    
    public function getVenueBookings($ownerId) {
        try {
            // Get venue ID
            $stmt = $this->pdo->prepare("SELECT id FROM venues WHERE owner_id = ?");
            $stmt->execute([$ownerId]);
            $venue = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$venue) {
                return ['success' => false, 'message' => 'Locale non trovato'];
            }
            
            $stmt = $this->pdo->prepare("
                SELECT b.*, u.name as user_name, u.email as user_email, u.phone as user_phone,
                       u.department, u.university
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                WHERE b.venue_id = ?
                ORDER BY b.event_date DESC
            ");
            $stmt->execute([$venue['id']]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'bookings' => $bookings];
            
        } catch (Exception $e) {
            error_log("Get venue bookings error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel caricamento prenotazioni'];
        }
    }
    
    public function updateBookingStatus($bookingId, $status, $userId) {
        try {
            // Verify that the user owns the venue for this booking
            $stmt = $this->pdo->prepare("
                SELECT b.id, b.event_date, b.user_id, b.venue_id
                FROM bookings b 
                JOIN venues v ON b.venue_id = v.id 
                WHERE b.id = ? AND v.owner_id = ?
            ");
            $stmt->execute([$bookingId, $userId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                return ['success' => false, 'message' => 'Non autorizzato'];
            }
            
            // Update status
            $stmt = $this->pdo->prepare("
                UPDATE bookings 
                SET status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $bookingId]);
            
            // Auto-complete bookings past their event date
            if ($status === 'confirmed') {
                $this->autoCompleteBookings();
            }
            
            // Send notification email
            if ($status === 'confirmed') {
                $this->sendStatusUpdateEmail($bookingId, $status);
            }
            
            return ['success' => true, 'message' => 'Stato aggiornato'];
            
        } catch (Exception $e) {
            error_log("Update booking status error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'aggiornamento'];
        }
    }
    
    public function autoCompleteBookings() {
        try {
            // Mark past confirmed bookings as completed
            $stmt = $this->pdo->prepare("
                UPDATE bookings 
                SET status = 'completed', updated_at = NOW()
                WHERE status = 'confirmed' 
                AND event_date < CURDATE()
            ");
            $stmt->execute();
            
            return ['success' => true, 'completed_count' => $stmt->rowCount()];
            
        } catch (Exception $e) {
            error_log("Auto complete bookings error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel completamento automatico'];
        }
    }
    
    public function getBookingById($bookingId, $userId = null) {
        try {
            $sql = "
                SELECT b.*, v.name as venue_name, v.address as venue_address, 
                       u.name as user_name, u.email as user_email
                FROM bookings b
                JOIN venues v ON b.venue_id = v.id
                JOIN users u ON b.user_id = u.id
                WHERE b.id = ?
            ";
            $params = [$bookingId];
            
            // If userId provided, ensure user owns booking or venue
            if ($userId) {
                $sql .= " AND (b.user_id = ? OR v.owner_id = ?)";
                $params[] = $userId;
                $params[] = $userId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                return ['success' => false, 'message' => 'Prenotazione non trovata'];
            }
            
            return ['success' => true, 'booking' => $booking];
            
        } catch (Exception $e) {
            error_log("Get booking error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel caricamento prenotazione'];
        }
    }
    
    public function cancelBooking($bookingId, $userId, $reason = null) {
        try {
            // Check if user owns the booking
            $stmt = $this->pdo->prepare("
                SELECT id, status, event_date FROM bookings 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$bookingId, $userId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                return ['success' => false, 'message' => 'Prenotazione non trovata'];
            }
            
            if ($booking['status'] === 'cancelled') {
                return ['success' => false, 'message' => 'Prenotazione già annullata'];
            }
            
            if ($booking['status'] === 'completed') {
                return ['success' => false, 'message' => 'Non puoi annullare una prenotazione completata'];
            }
            
            // Check cancellation policy (e.g., 24 hours before event)
            $eventDate = new DateTime($booking['event_date']);
            $now = new DateTime();
            $hoursUntilEvent = ($eventDate->getTimestamp() - $now->getTimestamp()) / 3600;
            
            if ($hoursUntilEvent < 24) {
                return ['success' => false, 'message' => 'Non puoi annullare meno di 24 ore prima dell\'evento'];
            }
            
            // Cancel booking
            $stmt = $this->pdo->prepare("
                UPDATE bookings 
                SET status = 'cancelled', cancelled_at = NOW(), cancellation_reason = ?
                WHERE id = ?
            ");
            $stmt->execute([$reason, $bookingId]);
            
            return ['success' => true, 'message' => 'Prenotazione annullata'];
            
        } catch (Exception $e) {
            error_log("Cancel booking error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante l\'annullamento'];
        }
    }
    
    private function sendStatusUpdateEmail($bookingId, $status) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT b.*, u.name as user_name, u.email as user_email, 
                       v.name as venue_name
                FROM bookings b 
                JOIN users u ON b.user_id = u.id 
                JOIN venues v ON b.venue_id = v.id 
                WHERE b.id = ?
            ");
            $stmt->execute([$bookingId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                $email = new Email();
                $email->sendBookingConfirmation(
                    $data,
                    ['name' => $data['user_name'], 'email' => $data['user_email']],
                    ['name' => $data['venue_name']]
                );
            }
        } catch (Exception $e) {
            error_log("Send status email error: " . $e->getMessage());
        }
    }
    
    public function getUpcomingBookings($userId, $days = 7) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT b.*, v.name as venue_name
                FROM bookings b
                JOIN venues v ON b.venue_id = v.id
                WHERE b.user_id = ? 
                AND b.status IN ('confirmed', 'pending')
                AND b.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY b.event_date ASC, b.event_time ASC
            ");
            $stmt->execute([$userId, $days]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'bookings' => $bookings];
            
        } catch (Exception $e) {
            error_log("Get upcoming bookings error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel caricamento prenotazioni imminenti'];
        }
    }
    
    public function getBookingStats($userId) {
        try {
            // Get user type first
            $stmt = $this->pdo->prepare("SELECT type FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userType = $stmt->fetchColumn();
            
            if ($userType === 'venue_owner') {
                return $this->getVenueBookingStats($userId);
            } else {
                return $this->getUserBookingStats($userId);
            }
            
        } catch (Exception $e) {
            error_log("Get booking stats error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel caricamento statistiche'];
        }
    }
    
    private function getUserBookingStats($userId) {
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
        
        // Favorite venue type
        $stmt = $this->pdo->prepare("
            SELECT v.type, COUNT(*) as count
            FROM bookings b
            JOIN venues v ON b.venue_id = v.id
            WHERE b.user_id = ?
            GROUP BY v.type
            ORDER BY count DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $favoriteType = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['favorite_venue_type'] = $favoriteType['type'] ?? 'N/A';
        
        return ['success' => true, 'stats' => $stats];
    }
    
    private function getVenueBookingStats($ownerId) {
        // Get venue ID first
        $stmt = $this->pdo->prepare("SELECT id FROM venues WHERE owner_id = ?");
        $stmt->execute([$ownerId]);
        $venueId = $stmt->fetchColumn();
        
        if (!$venueId) {
            return ['success' => false, 'message' => 'Venue not found'];
        }
        
        $stats = [];
        
        // Total bookings
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bookings WHERE venue_id = ?");
        $stmt->execute([$venueId]);
        $stats['total_bookings'] = $stmt->fetchColumn();
        
        // This month bookings
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM bookings 
            WHERE venue_id = ? 
            AND MONTH(event_date) = MONTH(CURDATE())
            AND YEAR(event_date) = YEAR(CURDATE())
        ");
        $stmt->execute([$venueId]);
        $stats['month_bookings'] = $stmt->fetchColumn();
        
        // Total revenue
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0) FROM bookings 
            WHERE venue_id = ? AND payment_status = 'paid'
        ");
        $stmt->execute([$venueId]);
        $stats['total_revenue'] = $stmt->fetchColumn();
        
        // Average group size
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(AVG(guests), 0) FROM bookings 
            WHERE venue_id = ? AND status IN ('confirmed', 'completed')
        ");
        $stmt->execute([$venueId]);
        $stats['avg_group_size'] = round($stmt->fetchColumn());
        
        // Busiest day of week
        $stmt = $this->pdo->prepare("
            SELECT DAYNAME(event_date) as day_name, COUNT(*) as count
            FROM bookings 
            WHERE venue_id = ?
            GROUP BY DAYOFWEEK(event_date), DAYNAME(event_date)
            ORDER BY count DESC
            LIMIT 1
        ");
        $stmt->execute([$venueId]);
        $busiestDay = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['busiest_day'] = $busiestDay['day_name'] ?? 'N/A';
        
        return ['success' => true, 'stats' => $stats];
    }
}
?>