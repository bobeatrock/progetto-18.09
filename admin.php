<?php
session_start();
require_once 'config/config.php';
require_once 'classes/Database.php';

// Simple admin authentication (enhance in production)
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
        if ($_POST['username'] === 'admin' && $_POST['password'] === 'festalaurea2025') {
            $_SESSION['admin_logged_in'] = true;
        } else {
            $error = "Credenziali non valide";
        }
    }
    
    if (!isset($_SESSION['admin_logged_in'])) {
        ?>
        <!DOCTYPE html>
        <html lang="it">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Login - FestaLaurea</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
                .login-card { background: white; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
                .form-group { margin-bottom: 1rem; }
                label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
                input[type="text"], input[type="password"] { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.25rem; box-sizing: border-box; }
                .btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.25rem; cursor: pointer; width: 100%; font-size: 1rem; }
                .error { color: #dc2626; margin-bottom: 1rem; padding: 0.5rem; background: #fee2e2; border-radius: 0.25rem; }
                h2 { text-align: center; margin-bottom: 1.5rem; color: #374151; }
            </style>
        </head>
        <body>
            <div class="login-card">
                <h2>🎓 FestaLaurea Admin</h2>
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Password:</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" name="admin_login" class="btn">Accedi</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Get statistics
try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get stats
    $stats = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE type = 'student'");
    $stats['total_users'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
    $stats['total_bookings'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'paid'");
    $stats['total_revenue'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM venues WHERE active = FALSE");
    $stats['pending_partners'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM venues WHERE active = TRUE");
    $stats['active_venues'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM reviews WHERE verified = TRUE");
    $stats['total_reviews'] = $stmt->fetchColumn();
    
    // Get recent bookings
    $stmt = $pdo->query("
        SELECT b.*, u.name as user_name, v.name as venue_name 
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN venues v ON b.venue_id = v.id 
        ORDER BY b.created_at DESC 
        LIMIT 10
    ");
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending partner requests
    $stmt = $pdo->query("
        SELECT v.*, u.name as owner_name, u.email as owner_email 
        FROM venues v 
        JOIN users u ON v.owner_id = u.id 
        WHERE v.active = FALSE
        ORDER BY v.created_at DESC
    ");
    $pending_partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get system health metrics
    $health = [];
    
    // Database connection test
    $health['database'] = 'ok';
    
    // Recent activity (bookings in last 24h)
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $recent_activity = $stmt->fetchColumn();
    $health['activity'] = $recent_activity > 0 ? 'ok' : 'warning';
    
    // Payment success rate (last 7 days)
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid
        FROM bookings 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $payment_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $payment_rate = $payment_stats['total'] > 0 ? ($payment_stats['paid'] / $payment_stats['total']) * 100 : 100;
    $health['payments'] = $payment_rate >= 80 ? 'ok' : 'warning';
    
} catch (Exception $e) {
    $error = "Errore nel caricamento dati: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - FestaLaurea</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f3f4f6; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .header h1 { font-size: 1.5rem; }
        .main { padding: 2rem 0; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #1f2937; }
        .stat-label { color: #6b7280; margin-top: 0.5rem; font-size: 0.9rem; }
        .section { background: white; border-radius: 0.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .section h3 { padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb; font-size: 1.25rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; }
        .status { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
        .status.pending { background: #fef3c7; color: #92400e; }
        .status.confirmed { background: #d1fae5; color: #065f46; }
        .status.cancelled { background: #fee2e2; color: #991b1b; }
        .status.completed { background: #dbeafe; color: #1e40af; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 0.25rem; cursor: pointer; font-size: 0.875rem; text-decoration: none; display: inline-block; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .logout { float: right; background: #6b7280; }
        .health-indicator { padding: 0.5rem; margin: 0.5rem 0; border-radius: 0.25rem; }
        .health-ok { background: #d1fae5; color: #065f46; }
        .health-warning { background: #fef3c7; color: #92400e; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>🎓 FestaLaurea - Pannello Amministratore</h1>
            <a href="?logout=1" class="btn logout">Logout</a>
        </div>
    </div>

    <div class="main">
        <div class="container">
            <?php if (isset($error)): ?>
                <div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 0.5rem; margin-bottom: 2rem;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_users'] ?? '0'; ?></div>
                    <div class="stat-label">Studenti Registrati</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['active_venues'] ?? '0'; ?></div>
                    <div class="stat-label">Locali Attivi</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_bookings'] ?? '0'; ?></div>
                    <div class="stat-label">Prenotazioni Totali</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_reviews'] ?? '0'; ?></div>
                    <div class="stat-label">Recensioni Verificate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">€<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                    <div class="stat-label">Ricavi Totali</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending_partners'] ?? '0'; ?></div>
                    <div class="stat-label">Partner in Attesa</div>
                </div>
            </div>

            <div class="grid-2">
                <div class="section">
                    <h3>Stato Sistema</h3>
                    <div style="padding: 1.5rem;">
                        <div class="health-indicator health-<?php echo $health['database'] ?? 'ok'; ?>">
                            <strong>Database:</strong> 
                            <?php echo $health['database'] === 'ok' ? '✅ Connesso' : '❌ Problemi'; ?>
                        </div>
                        
                        <div class="health-indicator health-<?php echo $health['activity'] ?? 'ok'; ?>">
                            <strong>Attività Recente:</strong> 
                            <?php echo $health['activity'] === 'ok' ? '✅ Normale' : '⚠️ Bassa attività'; ?>
                        </div>
                        
                        <div class="health-indicator health-<?php echo $health['payments'] ?? 'ok'; ?>">
                            <strong>Tasso Pagamenti:</strong> 
                            <?php echo $health['payments'] === 'ok' ? '✅ Normale' : '⚠️ Sotto soglia'; ?>
                        </div>
                        
                        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
                            <small><strong>Ultimo aggiornamento:</strong> <?php echo date('d/m/Y H:i'); ?></small>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h3>Azioni Rapide</h3>
                    <div style="padding: 1.5rem;">
                        <div style="display: grid; gap: 1rem;">
                            <a href="?export_data=1" class="btn btn-primary">📊 Esporta Dati</a>
                            <a href="?send_newsletter=1" class="btn btn-primary">📧 Invia Newsletter</a>
                            <a href="?cleanup_old_data=1" class="btn btn-danger">🗑️ Pulizia Database</a>
                            <a href="/venue-manager.html" class="btn btn-primary">🏢 Testa Pannello Gestori</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section">
                <h3>Prenotazioni Recenti</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Studente</th>
                            <th>Locale</th>
                            <th>Data Evento</th>
                            <th>Ospiti</th>
                            <th>Totale</th>
                            <th>Stato</th>
                            <th>Pagamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_bookings)): ?>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <tr>
                                    <td>#<?php echo $booking['id']; ?></td>
                                    <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['venue_name']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($booking['event_date'])); ?></td>
                                    <td><?php echo $booking['guests']; ?></td>
                                    <td>€<?php echo number_format($booking['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="status <?php echo $booking['status']; ?>">
                                            <?php
                                            switch($booking['status']) {
                                                case 'pending': echo 'In attesa'; break;
                                                case 'confirmed': echo 'Confermata'; break;
                                                case 'cancelled': echo 'Annullata'; break;
                                                case 'completed': echo 'Completata'; break;
                                                default: echo ucfirst($booking['status']); break;
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status <?php echo $booking['payment_status']; ?>">
                                            <?php echo ucfirst($booking['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align: center; padding: 2rem; color: #6b7280;">Nessuna prenotazione trovata</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($pending_partners)): ?>
                <div class="section">
                    <h3>Richieste Partner in Attesa (<?php echo count($pending_partners); ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Nome Locale</th>
                                <th>Proprietario</th>
                                <th>Email</th>
                                <th>Tipo</th>
                                <th>Data Richiesta</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_partners as $partner): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($partner['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($partner['owner_name']); ?></td>
                                    <td><a href="mailto:<?php echo $partner['owner_email']; ?>"><?php echo htmlspecialchars($partner['owner_email']); ?></a></td>
                                    <td><?php echo ucfirst($partner['type']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($partner['created_at'])); ?></td>
                                    <td>
                                        <a href="?approve_venue=<?php echo $partner['id']; ?>" class="btn btn-success" onclick="return confirm('Approvare questo locale?')">✅ Approva</a>
                                        <a href="?reject_venue=<?php echo $partner['id']; ?>" class="btn btn-danger" onclick="return confirm('Rifiutare questo locale?')">❌ Rifiuta</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="section">
                <h3>Log Attività Sistema</h3>
                <div style="padding: 1.5rem;">
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 0.5rem; font-family: monospace; font-size: 0.9rem; max-height: 300px; overflow-y: auto;">
                        <?php
                        // Simple activity log (in production, use proper logging system)
                        echo date('Y-m-d H:i:s') . " - Sistema operativo\n";
                        echo date('Y-m-d H:i:s') . " - " . ($stats['total_users'] ?? 0) . " utenti registrati\n";
                        echo date('Y-m-d H:i:s') . " - " . ($stats['active_venues'] ?? 0) . " locali attivi\n";
                        echo date('Y-m-d H:i:s') . " - " . ($stats['total_bookings'] ?? 0) . " prenotazioni elaborate\n";
                        if ($stats['pending_partners'] > 0) {
                            echo date('Y-m-d H:i:s') . " - ⚠️ " . $stats['pending_partners'] . " richieste partner in attesa\n";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh stats ogni 30 secondi
        setTimeout(function() {
            location.reload();
        }, 30000);
        
        // Conferma azioni critiche
        document.querySelectorAll('.btn-danger').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                if (!confirm('Sei sicuro di voler eseguire questa azione?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>

<?php
// Handle actions
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (isset($_GET['approve_venue'])) {
    try {
        $stmt = $pdo->prepare("UPDATE venues SET active = TRUE WHERE id = ?");
        $stmt->execute([$_GET['approve_venue']]);
        
        // Send approval email to venue owner
        $stmt = $pdo->prepare("
            SELECT v.name, u.email, u.name as owner_name 
            FROM venues v 
            JOIN users u ON v.owner_id = u.id 
            WHERE v.id = ?
        ");
        $stmt->execute([$_GET['approve_venue']]);
        $venue_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($venue_data) {
            $email = new Email();
            $subject = "Locale approvato - FestaLaurea";
            $message = "
                <h2>🎉 Congratulazioni!</h2>
                <p>Il tuo locale <strong>{$venue_data['name']}</strong> è stato approvato ed è ora visibile su FestaLaurea.</p>
                <p>Puoi iniziare a ricevere prenotazioni e gestire il tuo locale tramite il pannello gestori.</p>
                <p><a href='" . BASE_URL . "/venue-manager.html'>Accedi al Pannello Gestori</a></p>
            ";
            mail($venue_data['email'], $subject, $message, "From: " . BUSINESS_EMAIL . "\r\nContent-Type: text/html");
        }
        
        header('Location: admin.php?success=venue_approved');
        exit;
    } catch (Exception $e) {
        $error = "Errore nell'approvazione: " . $e->getMessage();
    }
}

if (isset($_GET['reject_venue'])) {
    try {
        // Get venue data before deletion
        $stmt = $pdo->prepare("
            SELECT v.name, u.email, u.name as owner_name 
            FROM venues v 
            JOIN users u ON v.owner_id = u.id 
            WHERE v.id = ?
        ");
        $stmt->execute([$_GET['reject_venue']]);
        $venue_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete venue
        $stmt = $pdo->prepare("DELETE FROM venues WHERE id = ?");
        $stmt->execute([$_GET['reject_venue']]);
        
        // Send rejection email
        if ($venue_data) {
            $subject = "Richiesta locale non approvata - FestaLaurea";
            $message = "
                <h2>Richiesta non approvata</h2>
                <p>La tua richiesta per il locale <strong>{$venue_data['name']}</strong> non è stata approvata.</p>
                <p>Puoi contattarci per maggiori informazioni o per correggere eventuali problemi.</p>
                <p>Email: " . BUSINESS_EMAIL . "</p>
            ";
            mail($venue_data['email'], $subject, $message, "From: " . BUSINESS_EMAIL . "\r\nContent-Type: text/html");
        }
        
        header('Location: admin.php?success=venue_rejected');
        exit;
    } catch (Exception $e) {
        $error = "Errore nel rifiuto: " . $e->getMessage();
    }
}

if (isset($_GET['export_data'])) {
    try {
        // Export basic statistics as CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="festalaurea_stats_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Metrica', 'Valore', 'Data']);
        fputcsv($output, ['Studenti Registrati', $stats['total_users'], date('Y-m-d H:i:s')]);
        fputcsv($output, ['Locali Attivi', $stats['active_venues'], date('Y-m-d H:i:s')]);
        fputcsv($output, ['Prenotazioni Totali', $stats['total_bookings'], date('Y-m-d H:i:s')]);
        fputcsv($output, ['Recensioni Verificate', $stats['total_reviews'], date('Y-m-d H:i:s')]);
        fputcsv($output, ['Ricavi Totali (EUR)', $stats['total_revenue'], date('Y-m-d H:i:s')]);
        fclose($output);
        exit;
    } catch (Exception $e) {
        $error = "Errore nell'esportazione: " . $e->getMessage();
    }
}

if (isset($_GET['cleanup_old_data'])) {
    try {
        // Cleanup old cancelled bookings (older than 6 months)
        $stmt = $pdo->prepare("
            DELETE FROM bookings 
            WHERE status = 'cancelled' 
            AND cancelled_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)
        ");
        $stmt->execute();
        $deleted_bookings = $stmt->rowCount();
        
        // Cleanup old unverified users (older than 1 month)
        $stmt = $pdo->prepare("
            DELETE FROM users 
            WHERE email_verified = FALSE 
            AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ");
        $stmt->execute();
        $deleted_users = $stmt->rowCount();
        
        header("Location: admin.php?success=cleanup&deleted_bookings=$deleted_bookings&deleted_users=$deleted_users");
        exit;
    } catch (Exception $e) {
        $error = "Errore nella pulizia: " . $e->getMessage();
    }
}

if (isset($_GET['success'])) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            let message = '';
            switch('" . $_GET['success'] . "') {
                case 'venue_approved':
                    message = '✅ Locale approvato con successo!';
                    break;
                case 'venue_rejected':
                    message = '❌ Locale rifiutato.';
                    break;
                case 'cleanup':
                    message = '🗑️ Pulizia completata: ' + ('" . ($_GET['deleted_bookings'] ?? 0) . "' + ' prenotazioni e ' + '" . ($_GET['deleted_users'] ?? 0) . "' + ' utenti eliminati.');
                    break;
            }
            if (message) {
                let div = document.createElement('div');
                div.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 1rem; border-radius: 0.5rem; z-index: 1000;';
                div.textContent = message;
                document.body.appendChild(div);
                setTimeout(() => div.remove(), 5000);
            }
        });
    </script>";
}
?>