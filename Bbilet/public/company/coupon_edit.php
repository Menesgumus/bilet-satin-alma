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

$couponId = $_GET['id'] ?? '';
$coupon = null;

if ($couponId) {
    $couponStmt = $pdo->prepare('SELECT * FROM coupons WHERE id = :id AND company_id = :cid LIMIT 1');
    $couponStmt->execute([':id' => $couponId, ':cid' => $companyId]);
    $coupon = $couponStmt->fetch();
}

if (!$coupon) {
    http_response_code(404);
    echo 'Kupon bulunamadı veya bu kuponu düzenleme yetkiniz yok.';
    exit;
}

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
    
    if ($code === '' || $discount <= 0 || $discount > 1 || $limit < 0 || $expiry === '') {
        $error = 'Tüm alanlar geçerli olmalıdır. İndirim oranı 0-1 arasında olmalıdır.';
    } else {
        try {
            $pdo->prepare('UPDATE coupons SET code=:code, discount_rate=:dr, usage_limit=:ul, expiry_date=:ed WHERE id=:id AND company_id=:cid')->execute([
                ':id' => $couponId,
                ':code' => $code,
                ':dr' => $discount,
                ':ul' => $limit,
                ':ed' => $expiry,
                ':cid' => $companyId,
            ]);
            $success = 'Kupon başarıyla güncellendi!';
            
            // Refresh coupon data
            $couponStmt = $pdo->prepare('SELECT * FROM coupons WHERE id = :id AND company_id = :cid LIMIT 1');
            $couponStmt->execute([':id' => $couponId, ':cid' => $companyId]);
            $coupon = $couponStmt->fetch();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                $error = 'Bu kupon kodu zaten mevcut. Lütfen farklı bir kod kullanın.';
            } else {
                $error = 'Kupon güncellenemedi: ' . $e->getMessage();
            }
        }
    }
}

// Format data for form
$expiryFormatted = '';
if ($coupon['expiry_date']) {
    try {
        $dt = new DateTime($coupon['expiry_date']);
        $expiryFormatted = $dt->format('Y-m-d\\TH:i');
    } catch (Throwable $e) {
        $expiryFormatted = str_replace(' ', 'T', $coupon['expiry_date']);
    }
}

$discountFormatted = rtrim(rtrim(sprintf('%.4f', (float)$coupon['discount_rate']), '0'), '.');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kupon Düzenle - <?=htmlspecialchars($coupon['code'])?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; }
        .container { max-width: 600px; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: .5rem 1rem; border: 1px solid #333; background: #2563eb; color: #fff; border-radius: 4px; text-decoration: none; display: inline-block; cursor: pointer; }
        .btn-success { background: #10b981; border-color: #10b981; }
        .btn-danger { background: #b91c1c; border-color: #b91c1c; }
        .btn-secondary { background: #6b7280; border-color: #6b7280; }
        .error { color: #b91c1c; background: #fef2f2; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .success { color: #059669; background: #f0fdf4; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .help-text { color: #666; font-size: 0.9rem; margin-top: 0.25rem; }
        .status-info { background: #f0f9ff; padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .status-active { color: #059669; font-weight: bold; }
        .status-expired { color: #b91c1c; font-weight: bold; }
        .status-exhausted { color: #6b7280; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Kupon Düzenle - <?=htmlspecialchars($coupon['code'])?></h2>
        
        <p>
            <a class="btn" href="/company/coupons.php">Kuponlar</a>
            <a class="btn" href="/company/trips.php">Seferler</a>
            <a class="btn btn-secondary" href="/index.php">Ana Sayfa</a>
        </p>

        <?php if ($error): ?><div class="error"><?=$error?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?=$success?></div><?php endif; ?>

        <!-- Kupon Durumu -->
        <div class="status-info">
            <h4>Kupon Durumu</h4>
            <?php
                $now = new DateTime();
                $expiry = new DateTime($coupon['expiry_date']);
                $isExpired = $expiry <= $now;
                $isExhausted = (int)$coupon['usage_limit'] <= 0;
                $status = $isExpired ? 'expired' : ($isExhausted ? 'exhausted' : 'active');
                $statusText = $isExpired ? 'Süresi Dolmuş' : ($isExhausted ? 'Limit Tükenmiş' : 'Aktif');
            ?>
            <p><strong>Durum:</strong> <span class="status-<?=$status?>"><?=$statusText?></span></p>
            <p><strong>Oluşturulma:</strong> <?=htmlspecialchars($coupon['created_at'])?></p>
            <p><strong>Son Kullanma:</strong> <?=htmlspecialchars($coupon['expiry_date'])?></p>
        </div>

        <form method="post">
            <div class="form-group">
                <label for="code">Kupon Kodu *</label>
                <input type="text" id="code" name="code" placeholder="Örn: YAZ2024, INDIRIM10" value="<?=htmlspecialchars($coupon['code'])?>" required />
                <div class="help-text">Benzersiz bir kupon kodu girin. Müşteriler bu kodu bilet satın alırken kullanacak.</div>
            </div>

            <div class="form-group">
                <label for="discount_rate">İndirim Oranı *</label>
                <input type="number" id="discount_rate" name="discount_rate" step="0.01" min="0.01" max="1" placeholder="0.10" value="<?=htmlspecialchars($discountFormatted)?>" required />
                <div class="help-text">0.10 = %10 indirim, 0.25 = %25 indirim, 0.50 = %50 indirim</div>
            </div>

            <div class="form-group">
                <label for="usage_limit">Kullanım Limiti *</label>
                <input type="number" id="usage_limit" name="usage_limit" min="0" placeholder="100" value="<?=htmlspecialchars($coupon['usage_limit'])?>" required />
                <div class="help-text">Bu kuponun kaç kez kullanılabileceğini belirtin. 0 = kullanım dışı.</div>
            </div>

            <div class="form-group">
                <label for="expiry_date">Son Kullanma Tarihi *</label>
                <input type="datetime-local" id="expiry_date" name="expiry_date" value="<?=htmlspecialchars($expiryFormatted)?>" required />
                <div class="help-text">Kuponun geçerliliğini yitireceği tarih ve saat.</div>
            </div>

            <div class="form-group">
                <button class="btn btn-success" type="submit">Güncelle</button>
                <a class="btn btn-secondary" href="/company/coupons.php">İptal</a>
            </div>
        </form>

        <!-- Kupon Silme -->
        <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #ddd;">
            <h4>Tehlikeli İşlemler</h4>
            <form method="post" action="/company/coupons.php" onsubmit="return confirm('Bu kuponu silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')">
                <input type="hidden" name="_action" value="delete" />
                <input type="hidden" name="id" value="<?=htmlspecialchars($coupon['id'])?>" />
                <button class="btn btn-danger" type="submit">Kuponu Sil</button>
            </form>
        </div>
    </div>
</body>
</html>
