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

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    
    if ($action === 'create') {
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
        
        if ($code !== '' && $discount > 0 && $discount <= 1 && $limit > 0 && $expiry !== '') {
            try {
                $pdo->prepare('INSERT INTO coupons (id, code, discount_rate, usage_limit, expiry_date, company_id) VALUES (:id,:code,:dr,:ul,:ed,:cid)')->execute([
                    ':id' => Util::generateUuidV4(),
                    ':code' => $code,
                    ':dr' => $discount,
                    ':ul' => $limit,
                    ':ed' => $expiry,
                    ':cid' => $companyId,
                ]);
                $message = 'Kupon başarıyla oluşturuldu.';
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'UNIQUE')) {
                    $error = 'Bu kupon kodu zaten mevcut.';
                } else {
                    $error = 'Kupon oluşturulamadı.';
                }
            }
        } else {
            $error = 'Tüm alanlar geçerli olmalıdır.';
        }
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? '';
        $code = trim($_POST['code'] ?? '');
        $discount = (float)($_POST['discount_rate'] ?? 0);
        $limit = (int)($_POST['usage_limit'] ?? 0);
        $expiryIn = trim($_POST['expiry_date'] ?? '');
        
        try {
            $dt = new DateTime($expiryIn);
            $expiry = $dt->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            $expiry = $expiryIn;
        }
        
        if ($id && $code !== '' && $discount > 0 && $discount <= 1 && $limit >= 0 && $expiry !== '') {
            try {
                $pdo->prepare('UPDATE coupons SET code=:code, discount_rate=:dr, usage_limit=:ul, expiry_date=:ed WHERE id=:id AND company_id=:cid')->execute([
                    ':id' => $id,
                    ':code' => $code,
                    ':dr' => $discount,
                    ':ul' => $limit,
                    ':ed' => $expiry,
                    ':cid' => $companyId,
                ]);
                $message = 'Kupon başarıyla güncellendi.';
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'UNIQUE')) {
                    $error = 'Bu kupon kodu zaten mevcut.';
                } else {
                    $error = 'Kupon güncellenemedi.';
                }
            }
        } else {
            $error = 'Tüm alanlar geçerli olmalıdır.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            $pdo->prepare('DELETE FROM coupons WHERE id = :id AND company_id = :cid')->execute([':id' => $id, ':cid' => $companyId]);
            $message = 'Kupon silindi.';
        }
    }
}

// Get company's coupons
$coupons = $pdo->prepare('SELECT * FROM coupons WHERE company_id = :cid ORDER BY created_at DESC');
$coupons->execute([':cid' => $companyId]);
$coupons = $coupons->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Kuponları - <?=htmlspecialchars($companyName)?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            color: #10b981;
            margin-bottom: 1rem;
        }
        .nav {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .coupons-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        .coupons-title {
            font-size: 1.5rem;
            color: #10b981;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .form-section { 
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .form-title {
            font-size: 1.3rem;
            color: #10b981;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .form-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
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
        }
        .form-grid input:focus,
        .form-grid select:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
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
            background: linear-gradient(135deg, #10b981, #059669);
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
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff; 
            border-radius: 8px;
            text-decoration: none; 
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.85rem;
            margin: 0.25rem;
            cursor: pointer;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        .btn-success { 
            background: linear-gradient(135deg, #10b981, #059669);
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
        .error { 
            color: #991b1b;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-weight: 500;
        }
        .success { 
            color: #059669;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-weight: 500;
        }
        .coupon-code {
            font-family: 'Courier New', monospace;
            background: #f1f3f4;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        .discount-rate {
            color: #10b981;
            font-weight: bold;
        }
        .usage-limit {
            color: #6b7280;
        }
        .expiry-date {
            color: #f59e0b;
            font-weight: 500;
        }
        .status-active { 
            color: #059669; 
            font-weight: bold;
            background: rgba(16, 185, 129, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .status-expired { 
            color: #991b1b; 
            font-weight: bold;
            background: rgba(239, 68, 68, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .status-exhausted { 
            color: #6b7280; 
            font-weight: bold;
            background: rgba(107, 114, 128, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .no-coupons {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        .no-coupons h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #10b981;
        }
        .no-coupons p {
            font-size: 1.1rem;
        }
    </style>
    </head>
<body>
    <div class="container">
        <div class="header">
            <h2>🎟️ Firma Kuponları - <?=htmlspecialchars($companyName)?></h2>
            <div class="nav">
                <a class="btn btn-success" href="/company/coupon_new.php">➕ Yeni Kupon</a>
                <a class="btn" href="/company/trips.php">🚌 Seferler</a>
                <a class="btn" href="/company/index.php">🏠 Firma Paneli</a>
                <a class="btn btn-secondary" href="/index.php">Ana Sayfa</a>
            </div>
        </div>

        <?php if ($message): ?><div class="success"><?=$message?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?=$error?></div><?php endif; ?>

        <!-- Yeni Kupon Oluşturma Formu -->
        <div class="form-section">
            <h3 class="form-title">🎟️ Yeni Kupon Oluştur</h3>
            <form method="post" class="form-grid">
                <input type="hidden" name="_action" value="create" />
                <div class="form-group">
                    <label for="code">Kupon Kodu</label>
                    <input name="code" id="code" placeholder="Örn: YAZ2024" required />
                </div>
                <div class="form-group">
                    <label for="discount_rate">İndirim Oranı (0.1 = %10)</label>
                    <input name="discount_rate" id="discount_rate" type="number" step="0.01" min="0.01" max="1" placeholder="0.1" required />
                </div>
                <div class="form-group">
                    <label for="usage_limit">Kullanım Limiti</label>
                    <input name="usage_limit" id="usage_limit" type="number" min="1" placeholder="100" required />
                </div>
                <div class="form-group">
                    <label for="expiry_date">Son Kullanma Tarihi</label>
                    <input name="expiry_date" id="expiry_date" type="datetime-local" required />
                </div>
                <div class="form-group">
                    <button class="btn btn-success" type="submit">🎟️ Kupon Oluştur</button>
                </div>
            </form>
        </div>

        <!-- Kupon Listesi -->
        <div class="coupons-card">
            <h3 class="coupons-title">Mevcut Kuponlar</h3>
            <?php if ($coupons): ?>
                <table>
                    <thead>
                        <tr>
                            <th>🎟️ Kod</th>
                            <th>💰 İndirim Oranı</th>
                            <th>📊 Kalan Kullanım</th>
                            <th>📅 Son Kullanma</th>
                            <th>📊 Durum</th>
                            <th>⚡ İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $cp): ?>
                            <?php
                                $now = new DateTime();
                                $expiry = new DateTime($cp['expiry_date']);
                                $isExpired = $expiry <= $now;
                                $isExhausted = (int)$cp['usage_limit'] <= 0;
                                $status = $isExpired ? 'expired' : ($isExhausted ? 'exhausted' : 'active');
                                $statusText = $isExpired ? 'Süresi Dolmuş' : ($isExhausted ? 'Limit Tükenmiş' : 'Aktif');
                                
                                $dtVal = $expiry->format('Y-m-d\\TH:i');
                                $drVal = rtrim(rtrim(sprintf('%.4f', (float)$cp['discount_rate']), '0'), '.');
                            ?>
                            <tr>
                                <form method="post">
                                    <input type="hidden" name="_action" value="update" />
                                    <input type="hidden" name="id" value="<?=htmlspecialchars($cp['id'])?>" />
                                    <td><input name="code" value="<?=htmlspecialchars($cp['code'])?>" class="coupon-code" style="width:100px;" /></td>
                                    <td><input name="discount_rate" type="number" step="0.01" min="0.01" max="1" value="<?=htmlspecialchars($drVal)?>" class="discount-rate" style="width:80px;" /></td>
                                    <td><input name="usage_limit" type="number" min="0" value="<?=htmlspecialchars((string)$cp['usage_limit'])?>" class="usage-limit" style="width:60px;" /></td>
                                    <td><input name="expiry_date" type="datetime-local" value="<?=htmlspecialchars($dtVal)?>" class="expiry-date" /></td>
                                    <td><span class="status-<?=$status?>"><?=$statusText?></span></td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn" type="submit" style="padding:.2rem .4rem; font-size:.8rem;">✏️ Güncelle</button>
                                            <form method="post" style="display:inline" onsubmit="return confirm('Bu kuponu silmek istediğinizden emin misiniz?')">
                                                <input type="hidden" name="_action" value="delete" />
                                                <input type="hidden" name="id" value="<?=htmlspecialchars($cp['id'])?>" />
                                                <button class="btn btn-danger" type="submit" style="padding:.2rem .4rem; font-size:.8rem;">🗑️ Sil</button>
                                            </form>
                                        </div>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-coupons">
                    <h3>🎟️ Henüz Kupon Yok</h3>
                    <p>İlk kuponunuzu oluşturmak için yukarıdaki formu kullanın.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
