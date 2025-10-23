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

// Get company name
$companyStmt = $pdo->prepare('SELECT name FROM companies WHERE id = :id LIMIT 1');
$companyStmt->execute([':id' => $companyId]);
$companyName = $companyStmt->fetchColumn();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    $discount = (float)($_POST['discount_rate'] ?? 0);
    $limit = (int)($_POST['usage_limit'] ?? 0);
    $expiryIn = trim($_POST['expiry_date'] ?? '');
    
    // Normalize expiry to 'Y-m-d H:i:s'
    try {
        $dt = new DateTime($expiryIn);
        $expiry = $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        $expiry = $expiryIn;
    }
    
    if ($code === '' || $discount <= 0 || $discount > 1 || $limit <= 0 || $expiry === '') {
        $error = 'Tüm alanlar geçerli olmalıdır. İndirim oranı 0-1 arasında olmalıdır.';
    } else {
        try {
            $pdo->prepare('INSERT INTO coupons (id, code, discount_rate, usage_limit, expiry_date, company_id) VALUES (:id,:code,:dr,:ul,:ed,:cid)')->execute([
                ':id' => Util::generateUuidV4(),
                ':code' => $code,
                ':dr' => $discount,
                ':ul' => $limit,
                ':ed' => $expiry,
                ':cid' => $companyId,
            ]);
            $success = 'Kupon başarıyla oluşturuldu!';
            // Clear form
            $_POST = [];
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                $error = 'Bu kupon kodu zaten mevcut. Lütfen farklı bir kod kullanın.';
            } else {
                $error = 'Kupon oluşturulamadı: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Kupon Oluştur - <?=htmlspecialchars($companyName)?></title>
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
            flex-wrap: wrap;
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
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #065f46;
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
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        .form-group input:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }
        .help-text {
            color: #6b7280;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            font-style: italic;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        .example {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .example h4 {
            color: #1e3a8a;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        .example ul {
            list-style: none;
            padding: 0;
        }
        .example li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .example li:last-child {
            border-bottom: none;
        }
        .example strong {
            color: #1e3a8a;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎟️ Yeni Kupon Oluştur</h1>
            <p style="text-align: center; color: #6b7280; font-size: 1.1rem; margin-bottom: 1rem;"><?=htmlspecialchars($companyName)?></p>
            <div class="nav">
                <a class="btn btn-secondary" href="/company/index.php">🏠 Firma Paneli</a>
                <a class="btn" href="/company/coupons.php">🎟️ Kuponlarım</a>
                <a class="btn" href="/company/trips.php">🚌 Seferlerim</a>
                <a class="btn btn-secondary" href="/index.php">🏠 Ana Sayfa</a>
            </div>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?=$error?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?=$success?></div><?php endif; ?>

        <div class="form-section">
            <h3 class="form-title">✨ Kupon Bilgilerini Girin</h3>
            <form method="post">
                <div class="form-group">
                    <label for="code">🏷️ Kupon Kodu *</label>
                    <input type="text" id="code" name="code" placeholder="Örn: YAZ2024, INDIRIM10" value="<?=htmlspecialchars($_POST['code'] ?? '')?>" required />
                    <div class="help-text">Benzersiz bir kupon kodu girin. Müşteriler bu kodu bilet satın alırken kullanacak.</div>
                </div>

                <div class="form-group">
                    <label for="discount_rate">💰 İndirim Oranı *</label>
                    <input type="number" id="discount_rate" name="discount_rate" step="0.01" min="0.01" max="1" placeholder="0.10" value="<?=htmlspecialchars($_POST['discount_rate'] ?? '')?>" required />
                    <div class="help-text">0.10 = %10 indirim, 0.25 = %25 indirim, 0.50 = %50 indirim</div>
                </div>

                <div class="form-group">
                    <label for="usage_limit">🔢 Kullanım Limiti *</label>
                    <input type="number" id="usage_limit" name="usage_limit" min="1" placeholder="100" value="<?=htmlspecialchars($_POST['usage_limit'] ?? '')?>" required />
                    <div class="help-text">Bu kuponun kaç kez kullanılabileceğini belirtin.</div>
                </div>

                <div class="form-group">
                    <label for="expiry_date">📅 Son Kullanma Tarihi *</label>
                    <input type="datetime-local" id="expiry_date" name="expiry_date" value="<?=htmlspecialchars($_POST['expiry_date'] ?? '')?>" required />
                    <div class="help-text">Kuponun geçerliliğini yitireceği tarih ve saat.</div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-success" type="submit">🎟️ Kupon Oluştur</button>
                    <a class="btn btn-secondary" href="/company/coupons.php">❌ İptal</a>
                </div>
            </form>
        </div>

        <div class="example">
            <h4>💡 Örnek Kupon Ayarları:</h4>
            <ul>
                <li><strong>🏷️ Kod:</strong> YAZ2024</li>
                <li><strong>💰 İndirim:</strong> 0.15 (%15 indirim)</li>
                <li><strong>🔢 Limit:</strong> 50 kullanım</li>
                <li><strong>📅 Tarih:</strong> 2024-08-31 23:59</li>
            </ul>
        </div>
    </div>
</body>
</html>
