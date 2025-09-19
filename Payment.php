<?php
require_once __DIR__ . '/../vendor/autoload.php';

class Payment {
    private $pdo;
    private $stripe;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
    }
    
    public function createPaymentIntent($bookingId, $amount) {
        try {
            // Get booking details
            $stmt = $this->pdo->prepare("
                SELECT b.*, u.name as user_name, u.email as user_email, v.name as venue_name 
                FROM bookings b 
                JOIN users u ON b.user_id = u.id 
                JOIN venues v ON b.venue_id = v.id 
                WHERE b.id = ?
            ");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                return ['success' => false, 'message' => 'Prenotazione non trovata'];
            }
            
            // Create Stripe PaymentIntent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $amount, // Amount in cents
                'currency' => 'eur',
                'metadata' => [
                    'booking_id' => $bookingId,
                    'user_email' => $booking['user_email'],
                    'venue_name' => $booking['venue_name']
                ],
                'description' => "FestaLaurea - Deposito prenotazione presso {$booking['venue_name']}"
            ]);
            
            // Update booking with payment intent ID
            $stmt = $this->pdo->prepare("UPDATE bookings SET stripe_payment_intent_id = ? WHERE id = ?");
            $stmt->execute([$paymentIntent->id, $bookingId]);
            
            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id
            ];
            
        } catch (Exception $e) {
            error_log("Payment creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nella creazione del pagamento'];
        }
    }
    
    public function handleWebhook() {
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, STRIPE_WEBHOOK_SECRET
            );
            
            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $paymentIntent = $event['data']['object'];
                    $this->handleSuccessfulPayment($paymentIntent);
                    break;
                    
                case 'payment_intent.payment_failed':
                    $paymentIntent = $event['data']['object'];
                    $this->handleFailedPayment($paymentIntent);
                    break;
                    
                default:
                    error_log('Received unknown event type: ' . $event['type']);
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Webhook error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Webhook processing failed'];
        }
    }
    
    private function handleSuccessfulPayment($paymentIntent) {
        $bookingId = $paymentIntent['metadata']['booking_id'];
        
        try {
            // Update booking status
            $stmt = $this->pdo->prepare("
                UPDATE bookings 
                SET payment_status = 'paid', status = 'confirmed', updated_at = NOW()
                WHERE id = ? AND stripe_payment_intent_id = ?
            ");
            $stmt->execute([$bookingId, $paymentIntent['id']]);
            
            // Send confirmation emails
            $this->sendConfirmationEmails($bookingId);
            
            // Log successful payment
            error_log("Payment successful for booking ID: {$bookingId}");
            
        } catch (Exception $e) {
            error_log("Error handling successful payment: " . $e->getMessage());
        }
    }
    
    private function handleFailedPayment($paymentIntent) {
        $bookingId = $paymentIntent['metadata']['booking_id'];
        
        try {
            $stmt = $this->pdo->prepare("
                UPDATE bookings 
                SET payment_status = 'failed', updated_at = NOW()
                WHERE id = ? AND stripe_payment_intent_id = ?
            ");
            $stmt->execute([$bookingId, $paymentIntent['id']]);
            
            // Log failed payment
            error_log("Payment failed for booking ID: {$bookingId}");
            
        } catch (Exception $e) {
            error_log("Error handling failed payment: " . $e->getMessage());
        }
    }
    
    private function sendConfirmationEmails($bookingId) {
        try {
            // Get booking, user, and venue data
            $stmt = $this->pdo->prepare("
                SELECT b.*, u.name as user_name, u.email as user_email, 
                       v.name as venue_name, v.email as venue_email
                FROM bookings b 
                JOIN users u ON b.user_id = u.id 
                JOIN venues v ON b.venue_id = v.id 
                WHERE b.id = ?
            ");
            $stmt->execute([$bookingId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($data) {
                $email = new Email();
                
                // Send confirmation to user
                $email->sendBookingConfirmation(
                    $data, 
                    ['name' => $data['user_name'], 'email' => $data['user_email']], 
                    ['name' => $data['venue_name']]
                );
                
                // Send notification to venue
                $email->sendVenueNotification(
                    $data, 
                    ['name' => $data['user_name'], 'email' => $data['user_email']], 
                    ['name' => $data['venue_name'], 'email' => $data['venue_email']]
                );
            }
        } catch (Exception $e) {
            error_log("Send confirmation emails error: " . $e->getMessage());
        }
    }
    
    public function processRefund($bookingId, $amount, $reason = null) {
        try {
            // Get booking with payment intent
            $stmt = $this->pdo->prepare("
                SELECT stripe_payment_intent_id, total_amount, deposit_amount 
                FROM bookings 
                WHERE id = ? AND payment_status = 'paid'
            ");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                return ['success' => false, 'message' => 'Prenotazione non trovata o non pagata'];
            }
            
            // Create refund in Stripe
            $refund = \Stripe\Refund::create([
                'payment_intent' => $booking['stripe_payment_intent_id'],
                'amount' => $amount * 100, // Convert to cents
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'booking_id' => $bookingId,
                    'refund_reason' => $reason
                ]
            ]);
            
            // Update booking status
            $stmt = $this->pdo->prepare("
                UPDATE bookings 
                SET payment_status = 'refunded', status = 'cancelled', 
                    cancelled_at = NOW(), cancellation_reason = ?
                WHERE id = ?
            ");
            $stmt->execute([$reason, $bookingId]);
            
            return [
                'success' => true,
                'refund_id' => $refund->id,
                'message' => 'Rimborso elaborato con successo'
            ];
            
        } catch (Exception $e) {
            error_log("Refund error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore durante il rimborso'];
        }
    }
    
    public function getPaymentStatus($bookingId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT payment_status, stripe_payment_intent_id, total_amount, deposit_amount
                FROM bookings 
                WHERE id = ?
            ");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                return ['success' => false, 'message' => 'Prenotazione non trovata'];
            }
            
            $response = [
                'success' => true,
                'payment_status' => $booking['payment_status'],
                'total_amount' => $booking['total_amount'],
                'deposit_amount' => $booking['deposit_amount']
            ];
            
            // If we have a Stripe payment intent, get additional details
            if ($booking['stripe_payment_intent_id']) {
                try {
                    $paymentIntent = \Stripe\PaymentIntent::retrieve($booking['stripe_payment_intent_id']);
                    $response['stripe_status'] = $paymentIntent->status;
                    $response['payment_method'] = $paymentIntent->payment_method ?? null;
                } catch (Exception $e) {
                    error_log("Error retrieving Stripe payment intent: " . $e->getMessage());
                }
            }
            
            return $response;
            
        } catch (Exception $e) {
            error_log("Get payment status error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nel recupero stato pagamento'];
        }
    }
    
    public function calculatePlatformFee($amount) {
        // Platform fee: 3% + €0.30 per transaction
        $percentageFee = $amount * 0.03;
        $fixedFee = 0.30;
        return round($percentageFee + $fixedFee, 2);
    }
    
    public function generatePaymentReport($startDate, $endDate) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(b.created_at) as date,
                    COUNT(*) as total_bookings,
                    COUNT(CASE WHEN b.payment_status = 'paid' THEN 1 END) as paid_bookings,
                    SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN b.payment_status = 'paid' THEN b.deposit_amount ELSE 0 END) as total_deposits,
                    v.name as venue_name
                FROM bookings b
                JOIN venues v ON b.venue_id = v.id
                WHERE b.created_at BETWEEN ? AND ?
                GROUP BY DATE(b.created_at), v.id
                ORDER BY date DESC, total_revenue DESC
            ");
            $stmt->execute([$startDate, $endDate]);
            $report = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate platform fees
            foreach ($report as &$row) {
                $row['platform_fee'] = $this->calculatePlatformFee($row['total_deposits']);
                $row['net_revenue'] = $row['total_deposits'] - $row['platform_fee'];
            }
            
            return ['success' => true, 'report' => $report];
            
        } catch (Exception $e) {
            error_log("Generate payment report error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Errore nella generazione del report'];
        }
    }
}
?>