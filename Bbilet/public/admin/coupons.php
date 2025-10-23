<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Database.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Util.php';

session_name((require __DIR__ . '/../../config/config.php')['session_name']);
session_start();

Auth::requireRole('admin');
$pdo = Database::getConnection();

// Prepare companies for select
$companies = $pdo->query('SELECT id, name FROM companies ORDER BY name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    if ($action === 'create') {
        $id = Util::generateUuidV4();
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
        $cid = $_POST['company_id'] !== '' ? $_POST['company_id'] : null;
        if ($code !== '' && $discount > 0 && $limit > 0 && $expiry !== '') {
            $pdo->prepare('INSERT INTO coupons (id, code, discount_rate, usage_limit, expiry_date, company_id) VALUES (:id,:code,:dr,:ul,:ed,:cid)')->execute([
                ':id' => $id,
                ':code' => $code,
                ':dr' => $discount,
                ':ul' => $limit,
                ':ed' => $expiry,
                ':cid' => $cid,
            ]);
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
        $cid = $_POST['company_id'] !== '' ? $_POST['company_id'] : null;
        if ($id && $code !== '' && $discount > 0 && $limit >= 0 && $expiry !== '') {
            $pdo->prepare('UPDATE coupons SET code=:code, discount_rate=:dr, usage_limit=:ul, expiry_date=:ed, company_id=:cid WHERE id=:id')->execute([
                ':id' => $id,
                ':code' => $code,
                ':dr' => $discount,
                ':ul' => $limit,
                ':ed' => $expiry,
                ':cid' => $cid,
            ]);
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            $pdo->prepare('DELETE FROM coupons WHERE id = :id')->execute([':id' => $id]);
        }
    }
    header('Location: /admin/coupons.php');
    exit;
}

$coupons = $pdo->query('SELECT cp.*, co.name AS company_name FROM coupons cp LEFT JOIN companies co ON co.id = cp.company_id ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kupon Yönetimi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 100%);
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
        .btn-danger { 
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        .btn-danger:hover {
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 1rem 1.5rem; 
            align-items: end;
            margin-bottom: 1rem;
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
            background: linear-gradient(135deg, #1e3a8a, #3730a3);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
        .muted { 
            color: #6b7280; 
            text-align: center;
            padding: 2rem;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎟️ Kupon Yönetimi</h1>
            <div class="nav">
                <a class="btn btn-secondary" href="/admin/index.php">🏠 Admin Paneli</a>
            </div>
        </div>
        <!-- Yeni Kupon Oluşturma -->
        <div class="form-section">
            <h3 class="form-title">✨ Yeni Kupon Oluştur</h3>
            <form method="post" class="form-grid">
                <input type="hidden" name="_action" value="create" />
                <div class="form-group">
                    <label for="code">Kupon Kodu</label>
                    <input name="code" id="code" placeholder="KUPON2024" required />
                </div>
                <div class="form-group">
                    <label for="discount_rate">İndirim Oranı (0-1)</label>
                    <input name="discount_rate" id="discount_rate" type="number" step="0.01" min="0" max="1" value="0.1" required />
                </div>
                <div class="form-group">
                    <label for="usage_limit">Kullanım Limiti</label>
                    <input name="usage_limit" id="usage_limit" type="number" min="1" value="100" required />
                </div>
                <div class="form-group">
                    <label for="expiry_date">Son Kullanma Tarihi</label>
                    <input name="expiry_date" id="expiry_date" type="datetime-local" required />
                </div>
                <div class="form-group">
                    <label for="company_id">Firma</label>
                    <select name="company_id" id="company_id">
                        <option value="">🌐 Genel Kupon</option>
                        <?php foreach ($companies as $co): ?>
                            <option value="<?=htmlspecialchars($co['id'])?>">🏢 <?=htmlspecialchars($co['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button class="btn btn-success" type="submit">🎟️ Kupon Oluştur</button>
                </div>
            </form>
        </div>

        <!-- Kupon Listesi -->
        <div class="form-section">
            <h3 class="form-title">🎟️ Mevcut Kuponlar</h3>
            <?php if ($coupons): ?>
                <table>
                    <thead>
                        <tr>
                            <th>🏷️ Kupon Kodu</th>
                            <th>💰 İndirim Oranı</th>
                            <th>🔢 Kullanım Limiti</th>
                            <th>📅 Son Kullanma</th>
                            <th>🏢 Firma</th>
                            <th>📊 Durum</th>
                            <th>⚡ İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $cp): ?>
                            <?php
                                $drVal = rtrim(rtrim(sprintf('%.4f', (float)$cp['discount_rate']), '0'), '.');
                                try {
                                    $dt = new DateTime($cp['expiry_date']);
                                    $dtVal = $dt->format('Y-m-d\\TH:i');
                                } catch (Throwable $e) {
                                    $dtVal = str_replace(' ', 'T', (string)$cp['expiry_date']);
                                }
                            ?>
                            <?php
                                $isActive = false;
                                try {
                                    $now = new DateTime();
                                    $ed = new DateTime((string)$cp['expiry_date']);
                                    $isActive = ($ed > $now) && ((int)$cp['usage_limit'] > 0);
                                } catch (Throwable $e) { $isActive = ((int)$cp['usage_limit'] > 0); }
                            ?>
                            <tr>
                                <form method="post">
                                    <input type="hidden" name="_action" value="update" />
                                    <input type="hidden" name="id" value="<?=htmlspecialchars($cp['id'])?>" />
                                    <td>
                                        <input name="code" value="<?=htmlspecialchars($cp['code'])?>" class="coupon-code" style="width: 120px; padding: 0.5rem;" />
                                    </td>
                                    <td>
                                        <input name="discount_rate" type="number" step="0.01" min="0" max="1" value="<?=htmlspecialchars($drVal)?>" class="discount-rate" style="width: 100px; padding: 0.5rem;" />
                                    </td>
                                    <td>
                                        <input name="usage_limit" type="number" min="0" value="<?=htmlspecialchars((string)$cp['usage_limit'])?>" class="usage-limit" style="width: 80px; padding: 0.5rem;" />
                                    </td>
                                    <td>
                                        <input name="expiry_date" type="datetime-local" value="<?=htmlspecialchars($dtVal)?>" class="expiry-date" style="padding: 0.5rem;" />
                                    </td>
                                    <td>
                                        <select name="company_id" style="padding: 0.5rem;">
                                            <option value="" <?= $cp['company_id'] ? '' : 'selected' ?>>🌐 Genel</option>
                                            <?php foreach ($companies as $co): ?>
                                                <option value="<?=htmlspecialchars($co['id'])?>" <?= $cp['company_id'] === $co['id'] ? 'selected' : '' ?>><?=htmlspecialchars($co['name'])?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <?php if ($isActive): ?>
                                            <span class="status-active">✅ Aktif</span>
                                        <?php else: ?>
                                            <span class="status-expired">❌ Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <button class="btn" type="submit">✏️ Kaydet</button>
                                            <form method="post" style="display:inline" onsubmit="return confirm('Bu kuponu silmek istediğinizden emin misiniz?')">
                                                <input type="hidden" name="_action" value="delete" />
                                                <input type="hidden" name="id" value="<?=htmlspecialchars($cp['id'])?>" />
                                                <button class="btn btn-danger" type="submit">🗑️ Sil</button>
                                            </form>
                                        </div>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="muted">
                    <h3>🎟️ Henüz Kupon Yok</h3>
                    <p>İlk kuponunuzu oluşturmak için yukarıdaki formu kullanın.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>


