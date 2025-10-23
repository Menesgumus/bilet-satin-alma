<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Database.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Util.php';

session_name((require __DIR__ . '/../../config/config.php')['session_name']);
session_start();

Auth::requireRole('firma_admin');
$pdo = Database::getConnection();

// Get company id for current user
$stmt = $pdo->prepare('SELECT company_id FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $_SESSION['user_id']]);
$companyId = $stmt->fetchColumn();
if (!$companyId) {
    http_response_code(400);
    echo 'Firma ataması bulunamadı.';
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dep = trim($_POST['departure_location'] ?? '');
    $arr = trim($_POST['arrival_location'] ?? '');
    $dt = trim($_POST['departure_time'] ?? '');
    $at = trim($_POST['arrival_time'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $seats = (int)($_POST['seat_count'] ?? 0);

    if ($dep === '' || $arr === '' || $dt === '' || $at === '' || $price <= 0 || $seats <= 0) {
        $error = 'Tüm alanlar zorunludur ve geçerli olmalıdır.';
    } else {
        try {
            $pdo->prepare('INSERT INTO trips (id, company_id, departure_location, arrival_location, departure_time, arrival_time, price, seat_count) VALUES (:id,:cid,:dep,:arr,:dt,:at,:price,:seats)')->execute([
                ':id' => Util::generateUuidV4(),
                ':cid' => $companyId,
                ':dep' => $dep,
                ':arr' => $arr,
                ':dt' => $dt,
                ':at' => $at,
                ':price' => $price,
                ':seats' => $seats,
            ]);
            header('Location: /company/trips.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Kayıt başarısız.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Sefer Oluştur</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 100%);
            min-height: 100vh;
            color: #333;
        }
        .container { max-width: 800px; margin: 0 auto; padding: 2rem; }
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .header h1 {
            font-size: 2.5rem;
            color: #1e3a8a;
            margin-bottom: 1rem;
            text-align: center;
        }
        .nav {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }
        .btn { 
            padding: 0.75rem 1.5rem; 
            border: none; 
            background: linear-gradient(135deg, #1e3a8a, #3730a3);
            color: #fff; 
            border-radius: 10px;
            text-decoration: none; 
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(30, 58, 138, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
        }
        .btn-secondary:hover {
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.4);
        }
        .btn-success { 
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .btn-success:hover {
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin: 1rem 0;
            font-weight: 500;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #991b1b;
        }
        .form-section { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .form-title {
            font-size: 1.5rem;
            color: #1e3a8a;
            margin-bottom: 1.5rem;
            text-align: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0.5rem;
        }
        .form-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 1rem 1.5rem; 
            align-items: end;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .form-grid input, 
        .form-grid select { 
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        .form-grid input:focus,
        .form-grid select:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚌 Yeni Sefer Oluştur</h1>
            <div class="nav">
                <a class="btn btn-secondary" href="/company/index.php">🏠 Firma Paneli</a>
                <a class="btn btn-secondary" href="/company/trips.php">🚌 Seferlerim</a>
            </div>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?=$error?></div><?php endif; ?>

        <div class="form-section">
            <h3 class="form-title">✨ Sefer Bilgilerini Girin</h3>
            <form method="post" class="form-grid">
                <div class="form-group">
                    <label for="departure_location">🚀 Kalkış Yeri</label>
                    <input name="departure_location" id="departure_location" placeholder="Örn: İstanbul" required />
                </div>
                <div class="form-group">
                    <label for="arrival_location">🏁 Varış Yeri</label>
                    <input name="arrival_location" id="arrival_location" placeholder="Örn: Ankara" required />
                </div>
                <div class="form-group">
                    <label for="departure_time">⏰ Kalkış Saati</label>
                    <input type="datetime-local" name="departure_time" id="departure_time" required />
                </div>
                <div class="form-group">
                    <label for="arrival_time">⏰ Varış Saati</label>
                    <input type="datetime-local" name="arrival_time" id="arrival_time" required />
                </div>
                <div class="form-group">
                    <label for="price">💰 Bilet Fiyatı (₺)</label>
                    <input type="number" step="0.01" name="price" id="price" placeholder="0.00" required />
                </div>
                <div class="form-group">
                    <label for="seat_count">🪑 Koltuk Sayısı</label>
                    <input type="number" name="seat_count" id="seat_count" placeholder="40" required />
                </div>
                <div class="form-actions">
                    <button class="btn btn-success" type="submit">💾 Sefer Oluştur</button>
                    <a class="btn btn-secondary" href="/company/trips.php">❌ İptal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>


