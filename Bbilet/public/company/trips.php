<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Database.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Util.php';

session_name((require __DIR__ . '/../../config/config.php')['session_name']);
session_start();

Auth::requireRole('firma_admin');

$pdo = Database::getConnection();

// Fetch company_id of current firma_admin
$stmt = $pdo->prepare('SELECT company_id FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $_SESSION['user_id']]);
$companyId = $stmt->fetchColumn();
if (!$companyId) {
    http_response_code(400);
    echo 'Firma ataması bulunamadı.';
    exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'delete') {
    $id = $_POST['id'] ?? '';
    if ($id !== '') {
        $pdo->prepare('DELETE FROM trips WHERE id = :id AND company_id = :cid')->execute([':id' => $id, ':cid' => $companyId]);
    }
    header('Location: /company/trips.php');
    exit;
}

$trips = $pdo->prepare('SELECT * FROM trips WHERE company_id = :cid ORDER BY departure_time DESC');
$trips->execute([':cid' => $companyId]);
$trips = $trips->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Seferleri</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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
        .header h2 {
            font-size: 2rem;
            color: #f59e0b;
            margin-bottom: 1rem;
        }
        .nav {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .trips-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .trips-title {
            font-size: 1.5rem;
            color: #f59e0b;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 1rem;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        th { 
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }
        td { 
            padding: 1rem; 
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s ease;
        }
        tr:hover td { 
            background-color: #f8f9fa; 
        }
        tr:last-child td {
            border-bottom: none;
        }
        .btn { 
            padding: 0.5rem 1rem; 
            border: none; 
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #fff; 
            text-decoration: none; 
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            font-size: 0.85rem;
            margin: 0.25rem;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
        }
        .btn-primary {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        .btn-danger { 
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        .btn-danger:hover {
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
        }
        .btn-secondary:hover {
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.4);
        }
        .route {
            font-weight: 600;
            color: #374151;
        }
        .price {
            color: #10b981;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .no-trips {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        .no-trips h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #f59e0b;
        }
        .no-trips p {
            font-size: 1.1rem;
        }
    </style>
    </head>
<body>
    <div class="container">
        <div class="header">
            <h2>🚌 Firma Seferleri</h2>
            <div class="nav">
                <a class="btn btn-primary" href="/company/trip_new.php">➕ Yeni Sefer</a>
                <a class="btn" href="/company/coupons.php">🎟️ Kuponlar</a>
                <a class="btn" href="/company/index.php">🏠 Firma Paneli</a>
                <a class="btn btn-secondary" href="/index.php">Ana Sayfa</a>
            </div>
        </div>
        
        <div class="trips-card">
            <h3 class="trips-title">Sefer Listesi</h3>
            
            <?php if (empty($trips)): ?>
                <div class="no-trips">
                    <h3>🚌 Henüz Sefer Yok</h3>
                    <p>İlk seferinizi eklemek için "Yeni Sefer" butonuna tıklayın.</p>
                    <a class="btn btn-primary" href="/company/trip_new.php" style="margin-top: 1rem;">➕ Yeni Sefer Ekle</a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>📍 Kalkış</th>
                            <th>📍 Varış</th>
                            <th>🕐 Kalkış Zamanı</th>
                            <th>🕐 Varış Zamanı</th>
                            <th>💰 Fiyat</th>
                            <th>🪑 Koltuk</th>
                            <th>⚡ İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($trips as $t): ?>
                        <tr>
                            <td class="route"><?=htmlspecialchars($t['departure_location'])?></td>
                            <td class="route"><?=htmlspecialchars($t['arrival_location'])?></td>
                            <td><?=htmlspecialchars($t['departure_time'])?></td>
                            <td><?=htmlspecialchars($t['arrival_time'])?></td>
                            <td class="price"><?=number_format((float)$t['price'], 2)?> ₺</td>
                            <td><strong><?=htmlspecialchars((string)$t['seat_count'])?> koltuk</strong></td>
                            <td>
                                <div class="actions">
                                    <a class="btn" href="/company/trip_edit.php?id=<?=urlencode($t['id'])?>">✏️ Düzenle</a>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Bu sefer silinsin mi?')">
                                        <input type="hidden" name="_action" value="delete" />
                                        <input type="hidden" name="id" value="<?=htmlspecialchars($t['id'])?>" />
                                        <button class="btn btn-danger" type="submit">🗑️ Sil</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>


