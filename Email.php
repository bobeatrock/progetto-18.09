<?php
class Email {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    
    public function __construct() {
        $this->smtp_host = SMTP_HOST;
        $this->smtp_port = SMTP_PORT;
        $this->smtp_username = SMTP_USERNAME;
        $this->smtp_password = SMTP_PASSWORD;
    }
    
    public function sendBookingConfirmation($booking, $user, $venue) {
        $subject = "Conferma Prenotazione - FestaLaurea";
        $template = $this->loadTemplate('booking_confirmation', [
            'user_name' => $user['name'],
            'venue_name' => $venue['name'],
            'event_date' => date('d/m/Y', strtotime($booking['event_date'])),
            'event_time' => $booking['event_time'],
            'guests' => $booking['guests'],
            'total_amount' => $booking['total_amount'],
            'confirmation_code' => $booking['confirmation_code']
        ]);
        
        return $this->sendEmail($user['email'], $subject, $template);
    }
    
    public function sendVenueNotification($booking, $user, $venue) {
        $subject = "Nuova Prenotazione - " . $venue['name'];
        $template = $this->loadTemplate('venue_notification', [
            'venue_name' => $venue['name'],
            'user_name' => $user['name'],
            'user_email' => $user['email'],
            'event_date' => date('d/m/Y', strtotime($booking['event_date'])),
            'event_time' => $booking['event_time'],
            'guests' => $booking['guests'],
            'total_amount' => $booking['total_amount']
        ]);
        
        return $this->sendEmail($venue['email'], $subject, $template);
    }
    
    public function sendReviewNotification($review, $venue) {
        $subject = "Nuova Recensione per " . $venue['name'];
        $template = $this->loadTemplate('review_notification', [
            'venue_name' => $venue['name'],
            'rating' => $review['rating'],
            'comment' => $review['comment'],
            'user_name' => $review['user_name']
        ]);
        
        return $this->sendEmail($venue['email'], $subject, $template);
    }
    
    public function sendWelcomeEmail($user) {
        $subject = "Benvenuto in FestaLaurea!";
        $template = $this->loadTemplate('welcome', [
            'user_name' => $user['name'],
            'login_url' => BASE_URL
        ]);
        
        return $this->sendEmail($user['email'], $subject, $template);
    }
    
    private function sendEmail($to, $subject, $body) {
        $headers = [
            'From: ' . $this->smtp_username,
            'Reply-To: ' . $this->smtp_username,
            'X-Mailer: PHP/' . phpversion(),
            'Content-Type: text/html; charset=UTF-8'
        ];
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    private function loadTemplate($templateName, $variables) {
        $templatePath = __DIR__ . "/../emails/{$templateName}.html";
        
        if (!file_exists($templatePath)) {
            return $this->getDefaultTemplate($templateName, $variables);
        }
        
        $template = file_get_contents($templatePath);
        
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        
        return $template;
    }
    
    private function getDefaultTemplate($templateName, $variables) {
        switch ($templateName) {
            case 'booking_confirmation':
                return "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 28px;'>🎓 Prenotazione Confermata!</h1>
                        </div>
                        
                        <div style='padding: 30px; background: #f8f9fa; margin: 20px 0; border-radius: 10px;'>
                            <p style='font-size: 18px; margin-bottom: 20px;'>Ciao <strong>{$variables['user_name']}</strong>,</p>
                            <p>La tua prenotazione presso <strong>{$variables['venue_name']}</strong> è stata confermata con successo!</p>
                            
                            <div style='background: white; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #667eea;'>
                                <h3 style='color: #667eea; margin-top: 0;'>📅 Dettagli Prenotazione</h3>
                                <table style='width: 100%; border-collapse: collapse;'>
                                    <tr><td style='padding: 8px 0; font-weight: bold;'>Data:</td><td>{$variables['event_date']}</td></tr>
                                    <tr><td style='padding: 8px 0; font-weight: bold;'>Orario:</td><td>{$variables['event_time']}</td></tr>
                                    <tr><td style='padding: 8px 0; font-weight: bold;'>Ospiti:</td><td>{$variables['guests']} persone</td></tr>
                                    <tr><td style='padding: 8px 0; font-weight: bold;'>Totale:</td><td style='color: #28a745; font-weight: bold;'>€{$variables['total_amount']}</td></tr>
                                    <tr><td style='padding: 8px 0; font-weight: bold;'>Codice Prenotazione:</td><td style='background: #e9ecef; padding: 5px 10px; border-radius: 4px; font-family: monospace;'>{$variables['confirmation_code']}</td></tr>
                                </table>
                            </div>
                            
                            <div style='background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 20px; margin: 20px 0;'>
                                <p style='margin: 0; color: #856404;'><strong>💡 Importante:</strong> Conserva questo codice prenotazione. Ti servirà il giorno dell'evento.</p>
                            </div>
                        </div>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='" . BASE_URL . "' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>Gestisci Prenotazione</a>
                        </div>
                        
                        <div style='border-top: 1px solid #dee2e6; padding-top: 20px; text-align: center; color: #6c757d; font-size: 14px;'>
                            <p>Grazie per aver scelto FestaLaurea per la tua festa di laurea! 🎉</p>
                            <p>Per assistenza: <a href='mailto:" . BUSINESS_EMAIL . "'>" . BUSINESS_EMAIL . "</a></p>
                        </div>
                    </div>
                </body>
                </html>";
                
            case 'venue_notification':
                return "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <div style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px; border-radius: 10px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 28px;'>🔔 Nuova Prenotazione</h1>
                        </div>
                        
                        <div style='padding: 30px; background: #f8f9fa; margin: 20px 0; border-radius: 10px;'>
                            <p style='font-size: 18px;'>Hai ricevuto una nuova prenotazione per <strong>{$variables['venue_name']}</strong>!</p>
                            
                            <div style='background: white; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #28a745;'>
                                <h3 style='color: #28a745; margin-top: 0;'>👤 Dettagli Cliente</h3>
                                <table style='width: 100%; border-collapse: collapse;'>
                                    <tr><td style='padding: 8px 0; font-weight: bold;'>Nome:</td><td>{$variables['user_name']}</td></tr>
                                    <tr><td style='padding: 8px 0; font-weight: bold;'>Email:</td><td><a href='mailto:{$variables['user_email']}'>{$variables['user_email']}</a></td></tr>
                                    <tr><td style='padding: 8px 0; font-weight: bold;'>Data Evento:</td><td>{$variables['event_date']}</td></tr>
                                    <tr><td style='padding: 8px 0; font-weight: bold;'>Orario:</td><td>{$variables['event_time']}</td></tr>
                                    <tr><td style='padding: 8px 0; font-weight: bold;'>Ospiti:</td><td>{$variables['guests']} persone</td></tr>
                                    <tr><td style='padding: 8px 0; font-weight: bold;'>Valore:</td><td style='color: #28a745; font-weight: bold;'>€{$variables['total_amount']}</td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='" . BASE_URL . "/venue-manager.html' style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>Gestisci Prenotazione</a>
                        </div>
                        
                        <div style='border-top: 1px solid #dee2e6; padding-top: 20px; text-align: center; color: #6c757d; font-size: 14px;'>
                            <p>Accedi al pannello gestori per confermare o gestire la prenotazione.</p>
                        </div>
                    </div>
                </body>
                </html>";
                
            case 'review_notification':
                return "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <div style='background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white; padding: 30px; border-radius: 10px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 28px;'>⭐ Nuova Recensione</h1>
                        </div>
                        
                        <div style='padding: 30px; background: #f8f9fa; margin: 20px 0; border-radius: 10px;'>
                            <p style='font-size: 18px;'>Hai ricevuto una nuova recensione per <strong>{$variables['venue_name']}</strong>!</p>
                            
                            <div style='background: white; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #ffc107;'>
                                <div style='display: flex; align-items: center; margin-bottom: 15px;'>
                                    <strong style='margin-right: 10px;'>Valutazione:</strong>
                                    <span style='color: #ffc107; font-size: 20px;'>" . str_repeat('⭐', $variables['rating']) . "</span>
                                    <span style='margin-left: 10px; color: #6c757d;'>({$variables['rating']}/5)</span>
                                </div>
                                <p><strong>Da:</strong> {$variables['user_name']}</p>
                                <p style='font-style: italic; background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;'>\"{$variables['comment']}\"</p>
                            </div>
                        </div>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='" . BASE_URL . "/venue-manager.html' style='background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>Visualizza Dashboard</a>
                        </div>
                    </div>
                </body>
                </html>";
                
            case 'welcome':
                return "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center;'>
                            <h1 style='margin: 0; font-size: 28px;'>🎉 Benvenuto in FestaLaurea!</h1>
                        </div>
                        
                        <div style='padding: 30px; background: #f8f9fa; margin: 20px 0; border-radius: 10px;'>
                            <p style='font-size: 18px;'>Ciao <strong>{$variables['user_name']}</strong>!</p>
                            <p>Il tuo account FestaLaurea è stato creato con successo. Ora puoi prenotare la tua festa di laurea nei migliori locali di Padova!</p>
                            
                            <div style='background: white; padding: 25px; border-radius: 8px; margin: 25px 0;'>
                                <h3 style='color: #667eea; margin-top: 0;'>🚀 Cosa puoi fare ora:</h3>
                                <ul style='color: #555;'>
                                    <li>Cerca tra centinaia di locali verificati</li>
                                    <li>Confronta prezzi e disponibilità</li>
                                    <li>Prenota con pagamento sicuro</li>
                                    <li>Gestisci tutte le tue prenotazioni</li>
                                    <li>Lascia recensioni dopo gli eventi</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='{$variables['login_url']}' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>Inizia a Esplorare</a>
                        </div>
                        
                        <div style='border-top: 1px solid #dee2e6; padding-top: 20px; text-align: center; color: #6c757d; font-size: 14px;'>
                            <p>Buona festa di laurea! 🎓</p>
                        </div>
                    </div>
                </body>
                </html>";
        }
        
        return "<p>Template non disponibile</p>";
    }
}
?>