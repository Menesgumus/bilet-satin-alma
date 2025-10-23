<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Database.php';
require __DIR__ . '/../../src/Auth.php';

session_name((require __DIR__ . '/../../config/config.php')['session_name']);
session_start();

Auth::requireRole('firma_admin');
$pdo = Database::getConnection();

// Get company info
$stmt = $pdo->prepare('SELECT c.*, u.fullname FROM companies c JOIN users u ON u.company_id = c.id WHERE u.id = :id LIMIT 1');
$stmt->execute([':id' => $_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    http_response_code(400);
    echo 'Firma bilgisi bulunamadı.';
    exit;
}

// Get date range from request
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Revenue report
$revenueStmt = $pdo->prepare('
    SELECT 
        COUNT(t.id) as total_tickets,
        SUM(t.paid_amount) as total_revenue,
        AVG(t.paid_amount) as avg_ticket_price
    FROM tickets t 
    JOIN trips tr ON tr.id = t.trip_id 
    WHERE tr.company_id = :company_id 
    AND DATE(t.purchase_time) BETWEEN :start_date AND :end_date
');
$revenueStmt->execute([
    ':company_id' => $company['id'],
    ':start_date' => $startDate,
    ':end_date' => $endDate
]);
$revenue = $revenueStmt->fetch();

// Top routes
$routesStmt = $pdo->prepare('
    SELECT 
        tr.departure_location,
        tr.arrival_location,
        COUNT(t.id) as ticket_count,
        SUM(t.paid_amount) as revenue
    FROM tickets t 
    JOIN trips tr ON tr.id = t.trip_id 
    WHERE tr.company_id = :company_id 
    AND DATE(t.purchase_time) BETWEEN :start_date AND :end_date
    GROUP BY tr.departure_location, tr.arrival_location
    ORDER BY ticket_count DESC
    LIMIT 10
');
$routesStmt->execute([
    ':company_id' => $company['id'],
    ':start_date' => $startDate,
    ':end_date' => $endDate
]);
$topRoutes = $routesStmt->fetchAll();

// Recent tickets
$recentTicketsStmt = $pdo->prepare('
    SELECT 
        t.*,
        tr.departure_location,
        tr.arrival_location,
        tr.departure_time,
        u.fullname as passenger_name
    FROM tickets t 
    JOIN trips tr ON tr.id = t.trip_id 
    JOIN users u ON u.id = t.user_id
    WHERE tr.company_id = :company_id 
    ORDER BY t.purchase_time DESC
    LIMIT 20
');
$recentTicketsStmt->execute([':company_id' => $company['id']]);
$recentTickets = $recentTicketsStmt->fetchAll();

function formatDate($date) {
    return (new DateTime($date))->format('d.m.Y H:i');
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - <?=htmlspecialchars($company['name'])?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            min-height: 100vh;
            color: #333;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .header h1 {
            font-size: 2rem;
            color: #8b5cf6;
            margin-bottom: 1rem;
        }
        .nav {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .date-filter {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .date-form {
            display: flex;
            gap: 1rem;
            align-items: end;
        }
        .date-form input {
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
        }
        .date-form input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        .btn { 
            padding: 0.75rem 1.5rem; 
            border: none; 
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: #fff; 
            border-radius: 8px;
            text-decoration: none; 
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
        }
        .btn-secondary:hover {
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.4);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #8b5cf6;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            color: #6b7280;
            font-size: 1rem;
            font-weight: 600;
        }
        .report-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .section-title {
            font-size: 1.5rem;
            color: #8b5cf6;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        th {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }
        td {
            padding: 1rem;
            border-bottom: 1px solid #f1f3f4;
        }
        tr:hover td {
            background-color: #f8f9fa;
        }
        .revenue {
            color: #10b981;
            font-weight: bold;
        }
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Raporlar - <?=htmlspecialchars($company['name'])?></h1>
            <div class="nav">
                <a class="btn" href="/company/index.php">🏠 Firma Paneli</a>
                <a class="btn" href="/company/trips.php">🚌 Seferler</a>
                <a class="btn" href="/company/coupons.php">🎟️ Kuponlar</a>
                <a class="btn btn-secondary" href="/index.php">Ana Sayfa</a>
            </div>
        </div>
        
        <!-- Tarih Filtresi -->
        <div class="date-filter">
            <h3 style="margin-bottom: 1rem; color: #8b5cf6;">📅 Tarih Aralığı</h3>
            <form method="get" class="date-form">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Başlangıç Tarihi</label>
                    <input type="date" name="start_date" value="<?=htmlspecialchars($startDate)?>" />
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Bitiş Tarihi</label>
                    <input type="date" name="end_date" value="<?=htmlspecialchars($endDate)?>" />
                </div>
                <div>
                    <button type="submit" class="btn">🔍 Filtrele</button>
                </div>
            </form>
        </div>
        
        <!-- İstatistikler -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?=$revenue['total_tickets'] ?? 0?></div>
                <div class="stat-label">Toplam Bilet</div>
            </div>
            <div class="stat-card">
                <div class="stat-number revenue"><?=number_format($revenue['total_revenue'] ?? 0, 2)?> ₺</div>
                <div class="stat-label">Toplam Gelir</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?=number_format($revenue['avg_ticket_price'] ?? 0, 2)?> ₺</div>
                <div class="stat-label">Ortalama Bilet Fiyatı</div>
            </div>
        </div>
        
        <!-- En Popüler Güzergahlar -->
        <div class="report-section">
            <h3 class="section-title">🏆 En Popüler Güzergahlar</h3>
            <?php if ($topRoutes): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Güzergah</th>
                            <th>Bilet Sayısı</th>
                            <th>Gelir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topRoutes as $route): ?>
                            <tr>
                                <td><?=htmlspecialchars($route['departure_location'])?> → <?=htmlspecialchars($route['arrival_location'])?></td>
                                <td><?=$route['ticket_count']?></td>
                                <td class="revenue"><?=number_format($route['revenue'], 2)?> ₺</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <h3>📊 Veri Bulunamadı</h3>
                    <p>Seçilen tarih aralığında bilet satışı bulunmuyor.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Son Biletler -->
        <div class="report-section">
            <h3 class="section-title">🎫 Son Satılan Biletler</h3>
            <?php if ($recentTickets): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Yolcu</th>
                            <th>Güzergah</th>
                            <th>Kalkış</th>
                            <th>Koltuk</th>
                            <th>Fiyat</th>
                            <th>Satış Tarihi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTickets as $ticket): ?>
                            <tr>
                                <td><?=htmlspecialchars($ticket['passenger_name'])?></td>
                                <td><?=htmlspecialchars($ticket['departure_location'])?> → <?=htmlspecialchars($ticket['arrival_location'])?></td>
                                <td><?=formatDate($ticket['departure_time'])?></td>
                                <td><?=$ticket['seat_number']?></td>
                                <td class="revenue"><?=number_format($ticket['paid_amount'] ?? $ticket['price'], 2)?> ₺</td>
                                <td><?=formatDate($ticket['purchase_time'])?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <h3>🎫 Bilet Bulunamadı</h3>
                    <p>Henüz bilet satışı yapılmamış.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
