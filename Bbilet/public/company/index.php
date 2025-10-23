<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Database.php';
require __DIR__ . '/../../src/Auth.php';

session_name((require __DIR__ . '/../../config/config.php')['session_name']);
session_start();

Auth::requireRole('firma_admin');
$pdo = Database::getConnection();

// Fetch company info
$stmt = $pdo->prepare('SELECT c.*, u.fullname FROM companies c JOIN users u ON u.company_id = c.id WHERE u.id = :id LIMIT 1');
$stmt->execute([':id' => $_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    http_response_code(400);
    echo 'Firma bilgisi bulunamadı.';
    exit;
}

// Get statistics
$tripCount = $pdo->prepare('SELECT COUNT(*) FROM trips WHERE company_id = :id');
$tripCount->execute([':id' => $company['id']]);
$totalTrips = $tripCount->fetchColumn();

$couponCount = $pdo->prepare('SELECT COUNT(*) FROM coupons WHERE company_id = :id');
$couponCount->execute([':id' => $company['id']]);
$totalCoupons = $couponCount->fetchColumn();

$activeCoupons = $pdo->prepare('SELECT COUNT(*) FROM coupons WHERE company_id = :id AND expiry_date > CURRENT_TIMESTAMP AND usage_limit > 0');
$activeCoupons->execute([':id' => $company['id']]);
$activeCouponsCount = $activeCoupons->fetchColumn();

$ticketCount = $pdo->prepare('SELECT COUNT(*) FROM tickets t JOIN trips tr ON tr.id = t.trip_id WHERE tr.company_id = :id');
$ticketCount->execute([':id' => $company['id']]);
$totalTickets = $ticketCount->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Admin Paneli</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            min-height: 100vh;
            color: #333;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .header h1 {
            font-size: 2.5rem;
            color: #f59e0b;
            margin-bottom: 0.5rem;
        }
        .company-info {
            color: #6b7280;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        .admin-name {
            color: #374151;
            font-weight: 600;
        }
        .nav {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
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
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #f59e0b;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            color: #6b7280;
            font-size: 1rem;
            font-weight: 600;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .action-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            border-color: #f59e0b;
        }
        .action-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .action-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #f59e0b;
            margin-bottom: 0.5rem;
        }
        .action-description {
            color: #6b7280;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .btn { 
            padding: 0.75rem 1.5rem; 
            border: none; 
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #fff; 
            border-radius: 10px;
            text-decoration: none; 
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
        }
        .btn-secondary:hover {
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏢 Firma Admin Paneli</h1>
            <div class="company-info">
                <strong><?=htmlspecialchars($company['name'])?></strong> - 
                <span class="admin-name"><?=htmlspecialchars($company['fullname'])?></span>
            </div>
            <div class="nav">
                <a class="btn btn-secondary" href="/index.php">🏠 Ana Sayfa</a>
                <a class="btn btn-secondary" href="/logout.php">🚪 Çıkış</a>
            </div>
        </div>
        
        <!-- İstatistikler -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🚌</div>
                <div class="stat-number"><?=$totalTrips?></div>
                <div class="stat-label">Toplam Sefer</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎟️</div>
                <div class="stat-number"><?=$totalCoupons?></div>
                <div class="stat-label">Toplam Kupon</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?=$activeCouponsCount?></div>
                <div class="stat-label">Aktif Kupon</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎫</div>
                <div class="stat-number"><?=$totalTickets?></div>
                <div class="stat-label">Satılan Bilet</div>
            </div>
        </div>
        
        <!-- İşlemler -->
        <div class="actions-grid">
            <div class="action-card">
                <div class="action-icon">🚌</div>
                <div class="action-title">Sefer Yönetimi</div>
                <div class="action-description">Sefer ekle, düzenle ve sil. Mevcut seferlerinizi yönetin.</div>
                <a class="btn" href="/company/trips.php">Seferleri Yönet</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">🎟️</div>
                <div class="action-title">Kupon Yönetimi</div>
                <div class="action-description">İndirim kuponları oluştur, düzenle ve yönet.</div>
                <a class="btn" href="/company/coupons.php">Kuponları Yönet</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">📊</div>
                <div class="action-title">Raporlar</div>
                <div class="action-description">Satış raporları ve istatistikleri görüntüle.</div>
                <a class="btn" href="/company/reports.php">Raporları Görüntüle</a>
            </div>
            
            <div class="action-card">
                <div class="action-icon">⚙️</div>
                <div class="action-title">Firma Ayarları</div>
                <div class="action-description">Firma bilgilerini düzenle ve ayarları yönet.</div>
                <a class="btn" href="/company/settings.php">Ayarları Düzenle</a>
            </div>
        </div>
    </div>
</body>
</html>
