<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Database.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Util.php';

session_name((require __DIR__ . '/../../config/config.php')['session_name']);
session_start();

Auth::requireRole('admin');
$pdo = Database::getConnection();

$companies = $pdo->query('SELECT id, name FROM companies ORDER BY name ASC')->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    
    if ($action === 'create') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $companyId = $_POST['company_id'] !== '' ? $_POST['company_id'] : null;
        $balance = (float)($_POST['balance'] ?? 0);
        
        if ($fullname !== '' && $email !== '' && $password !== '' && $role !== '') {
            // Role validation
            if (!in_array($role, ['user', 'firma_admin', 'admin'], true)) {
                $error = 'Geçersiz rol seçildi.';
            } else {
                // Firma admin için company_id zorunlu
                if ($role === 'firma_admin' && $companyId === null) {
                    $error = 'Firma admin için firma seçimi zorunludur.';
                } else {
                    try {
                        $pdo->prepare('INSERT INTO users (id, fullname, email, password, role, company_id, balance) VALUES (:id, :fullname, :email, :password, :role, :company_id, :balance)')->execute([
                            ':id' => Util::generateUuidV4(),
                            ':fullname' => $fullname,
                            ':email' => $email,
                            ':password' => password_hash($password, PASSWORD_DEFAULT),
                            ':role' => $role,
                            ':company_id' => $companyId,
                            ':balance' => $balance,
                        ]);
                        $message = 'Kullanıcı başarıyla oluşturuldu.';
                    } catch (PDOException $e) {
                        if (str_contains($e->getMessage(), 'UNIQUE')) {
                            $error = 'Bu e-posta ile kayıt mevcut.';
                        } else {
                            $error = 'Kullanıcı oluşturulamadı.';
                        }
                    }
                }
            }
        } else {
            $error = 'Tüm alanlar zorunludur.';
        }
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? '';
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $companyId = $_POST['company_id'] !== '' ? $_POST['company_id'] : null;
        $balance = (float)($_POST['balance'] ?? 0);
        
        if ($id !== '' && $fullname !== '' && $email !== '' && $role !== '') {
            // Mevcut kullanıcının admin olup olmadığını kontrol et
            $currentUser = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
            $currentUser->execute([':id' => $id]);
            $user = $currentUser->fetch();
            
            if ($user && $user['role'] === 'admin') {
                $error = 'Admin kullanıcıların bilgileri değiştirilemez.';
            } else {
                if ($role === 'firma_admin' && $companyId === null) {
                    $error = 'Firma admin için firma seçimi zorunludur.';
                } else {
                    try {
                        $pdo->prepare('UPDATE users SET fullname=:fullname, email=:email, role=:role, company_id=:company_id, balance=:balance WHERE id=:id')->execute([
                            ':id' => $id,
                            ':fullname' => $fullname,
                            ':email' => $email,
                            ':role' => $role,
                            ':company_id' => $companyId,
                            ':balance' => $balance,
                        ]);
                        $message = 'Kullanıcı başarıyla güncellendi.';
                    } catch (PDOException $e) {
                        if (str_contains($e->getMessage(), 'UNIQUE')) {
                            $error = 'Bu e-posta ile kayıt mevcut.';
                        } else {
                            $error = 'Kullanıcı güncellenemedi.';
                        }
                    }
                }
            }
        } else {
            $error = 'Tüm alanlar zorunludur.';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id !== '') {
            // Mevcut kullanıcının admin olup olmadığını kontrol et
            $currentUser = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
            $currentUser->execute([':id' => $id]);
            $user = $currentUser->fetch();
            
            if ($user && $user['role'] === 'admin') {
                $error = 'Admin kullanıcılar silinemez.';
            } else {
                $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);
                $message = 'Kullanıcı silindi.';
            }
        }
    }
}

// Tüm kullanıcıları listele
$users = $pdo->query('
    SELECT u.*, c.name as company_name 
    FROM users u 
    LEFT JOIN companies c ON c.id = u.company_id 
    ORDER BY u.role DESC, u.fullname ASC
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi</title>
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
        .btn-warning { 
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        .btn-warning:hover {
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
        }
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin: 1rem 0;
            font-weight: 500;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #065f46;
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
        .role-badge { 
            padding: 0.25rem 0.75rem; 
            border-radius: 20px; 
            font-size: 0.8rem; 
            font-weight: bold;
            display: inline-block;
        }
        .role-admin { 
            background: rgba(239, 68, 68, 0.1); 
            color: #991b1b; 
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .role-firma_admin { 
            background: rgba(245, 158, 11, 0.1); 
            color: #92400e; 
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        .role-user { 
            background: rgba(107, 114, 128, 0.1); 
            color: #374151; 
            border: 1px solid rgba(107, 114, 128, 0.2);
        }
        .muted { 
            color: #6b7280; 
            text-align: center;
            padding: 2rem;
            font-size: 1.1rem;
        }
        .balance-display {
            color: #10b981;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .actions .btn {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 Kullanıcı Yönetimi</h1>
            <div class="nav">
                <a class="btn btn-secondary" href="/admin/index.php">🏠 Admin Paneli</a>
            </div>
        </div>
        
        <?php if ($message): ?><div class="alert alert-success"><?=$message?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?=$error?></div><?php endif; ?>

        <!-- Yeni Kullanıcı Oluşturma -->
        <div class="form-section">
            <h3 class="form-title">✨ Yeni Kullanıcı Oluştur</h3>
            <form method="post" class="form-grid">
                <input type="hidden" name="_action" value="create" />
                <div class="form-group">
                    <label for="fullname">Ad Soyad</label>
                    <input name="fullname" id="fullname" placeholder="Ad Soyad" required />
                </div>
                <div class="form-group">
                    <label for="email">E-posta</label>
                    <input type="email" name="email" id="email" placeholder="E-posta" required />
                </div>
                <div class="form-group">
                    <label for="password">Şifre</label>
                    <input type="password" name="password" id="password" placeholder="Şifre" required />
                </div>
                <div class="form-group">
                    <label for="role">Rol</label>
                    <select name="role" id="role" required onchange="toggleCompanyField(this)">
                        <option value="">Rol Seçin</option>
                        <option value="user">👤 Kullanıcı</option>
                        <option value="firma_admin">👨‍💼 Firma Admin</option>
                        <option value="admin">🛡️ Sistem Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="companySelect">Firma</label>
                    <select name="company_id" id="companySelect" style="display:none;">
                        <option value="">Firma Seçin</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?=htmlspecialchars($c['id'])?>"><?=htmlspecialchars($c['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="balance">Bakiye (₺)</label>
                    <input name="balance" id="balance" type="number" step="0.01" min="0" placeholder="0.00" value="0" />
                </div>
                <div class="form-group">
                    <button class="btn btn-success" type="submit">👤 Kullanıcı Oluştur</button>
                </div>
            </form>
        </div>

        <!-- Kullanıcı Listesi -->
        <div class="form-section">
            <h3 class="form-title">👥 Mevcut Kullanıcılar</h3>
            <?php if ($users): ?>
                <table>
                    <thead>
                        <tr>
                            <th>👤 Ad Soyad</th>
                            <th>📧 E-posta</th>
                            <th>🎭 Rol</th>
                            <th>🏢 Firma</th>
                            <th>💰 Bakiye</th>
                            <th>📅 Kayıt Tarihi</th>
                            <th>✏️ Güncelle</th>
                            <th>🗑️ Sil</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <form method="post">
                                    <input type="hidden" name="_action" value="update" />
                                    <input type="hidden" name="id" value="<?=htmlspecialchars($user['id'])?>" />
                                    <td>
                                        <input name="fullname" value="<?=htmlspecialchars($user['fullname'])?>" style="width:140px; padding:0.5rem;" />
                                    </td>
                                    <td>
                                        <input type="email" name="email" value="<?=htmlspecialchars($user['email'])?>" style="width:180px; padding:0.5rem;" />
                                    </td>
                                    <td>
                                        <select name="role" onchange="toggleCompanyFieldUpdate(this, '<?=htmlspecialchars($user['id'])?>')" style="padding:0.5rem;">
                                            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>👤 Kullanıcı</option>
                                            <option value="firma_admin" <?= $user['role'] === 'firma_admin' ? 'selected' : '' ?>>👨‍💼 Firma Admin</option>
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>🛡️ Sistem Admin</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="company_id" id="companySelect_<?=htmlspecialchars($user['id'])?>" style="<?= $user['role'] === 'firma_admin' ? '' : 'display:none;' ?> padding:0.5rem;">
                                            <option value="">Firma Seçin</option>
                                            <?php foreach ($companies as $c): ?>
                                                <option value="<?=htmlspecialchars($c['id'])?>" <?= $user['company_id'] === $c['id'] ? 'selected' : '' ?>><?=htmlspecialchars($c['name'])?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span id="companyDisplay_<?=htmlspecialchars($user['id'])?>" style="<?= $user['role'] === 'firma_admin' ? 'display:none;' : '' ?> color:#6b7280;">
                                            <?= $user['company_name'] ? htmlspecialchars($user['company_name']) : '—' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input name="balance" type="number" step="0.01" min="0" value="<?=htmlspecialchars((string)$user['balance'])?>" style="width:90px; padding:0.5rem;" />
                                    </td>
                                    <td style="font-size:0.9rem;"><?=htmlspecialchars($user['created_at'])?></td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn" type="submit">✏️ Güncelle</button>
                                        </div>
                                    </td>
                                </form>
                                <td>
                                    <div class="actions">
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <form method="post" style="display:inline" onsubmit="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?')">
                                                <input type="hidden" name="_action" value="delete" />
                                                <input type="hidden" name="id" value="<?=htmlspecialchars($user['id'])?>" />
                                                <button class="btn btn-danger" type="submit">🗑️ Sil</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="muted">🛡️ Korumalı</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="muted">
                    <h3>👥 Henüz Kullanıcı Yok</h3>
                    <p>İlk kullanıcınızı oluşturmak için yukarıdaki formu kullanın.</p>
                </div>
            <?php endif; ?>
        </div>

        <script>
            function toggleCompanyField(select) {
                const companySelect = document.getElementById('companySelect');
                if (select.value === 'firma_admin') {
                    companySelect.style.display = 'block';
                    companySelect.required = true;
                } else {
                    companySelect.style.display = 'none';
                    companySelect.required = false;
                }
            }

            function toggleCompanyFieldUpdate(select, userId) {
                const companySelect = document.getElementById('companySelect_' + userId);
                const companyDisplay = document.getElementById('companyDisplay_' + userId);
                if (select.value === 'firma_admin') {
                    companySelect.style.display = 'block';
                    companyDisplay.style.display = 'none';
                    companySelect.required = true;
                } else {
                    companySelect.style.display = 'none';
                    companyDisplay.style.display = 'block';
                    companySelect.required = false;
                }
            }
        </script>
    </div>
</body>
</html>
