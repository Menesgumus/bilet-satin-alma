<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Database.php';
require __DIR__ . '/../../src/Auth.php';

session_name((require __DIR__ . '/../../config/config.php')['session_name']);
session_start();

Auth::requireRole('admin');
$pdo = Database::getConnection();

$companies = $pdo->query('SELECT id, name FROM companies ORDER BY name ASC')->fetchAll();

// Aktif firma adminlerini listele
$firmaAdmins = $pdo->query("
    SELECT u.id, u.fullname, u.email, u.role, u.company_id, c.name as company_name 
    FROM users u 
    LEFT JOIN companies c ON c.id = u.company_id 
    WHERE u.role IN ('firma_admin', 'admin') 
    ORDER BY u.role DESC, u.fullname ASC
")->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    
    if ($action === 'assign') {
        // Firma admin atama
        $email = trim($_POST['email'] ?? '');
        $companyId = $_POST['company_id'] ?? '';
        if ($email !== '' && $companyId !== '') {
            $u = $pdo->prepare('SELECT id, role FROM users WHERE email = :email LIMIT 1');
            $u->execute([':email' => $email]);
            $user = $u->fetch();
            if ($user) {
                if ($user['role'] === 'admin') {
                    $error = 'Admin kullanıcıların rolü değiştirilemez.';
                } else {
                    $pdo->prepare("UPDATE users SET role='firma_admin', company_id = :cid WHERE id = :id")
                        ->execute([':cid' => $companyId, ':id' => $user['id']]);
                    $message = 'Kullanıcı firma admin olarak atandı.';
                }
            } else {
                $error = 'Kullanıcı bulunamadı.';
            }
        }
    } elseif ($action === 'change_role') {
        // Rol değiştirme
        $userId = $_POST['user_id'] ?? '';
        $newRole = $_POST['new_role'] ?? '';
        
        if ($userId !== '' && $newRole !== '') {
            // Mevcut kullanıcının rolünü kontrol et
            $currentUser = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
            $currentUser->execute([':id' => $userId]);
            $user = $currentUser->fetch();
            
            if ($user && $user['role'] !== 'admin') {
                if ($newRole === 'user') {
                    $pdo->prepare("UPDATE users SET role='user', company_id = NULL WHERE id = :id")
                        ->execute([':id' => $userId]);
                    $message = 'Kullanıcı normal kullanıcı seviyesine düşürüldü.';
                } elseif ($newRole === 'firma_admin') {
                    $companyId = $_POST['company_id'] ?? '';
                    if ($companyId !== '') {
                        $pdo->prepare("UPDATE users SET role='firma_admin', company_id = :cid WHERE id = :id")
                            ->execute([':cid' => $companyId, ':id' => $userId]);
                        $message = 'Kullanıcı firma admin olarak atandı.';
                    } else {
                        $error = 'Firma seçilmedi.';
                    }
                }
            } else {
                $error = 'Admin kullanıcıların rolü değiştirilemez.';
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
    <title>Firma Admin Atama</title>
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
            <h1>👨‍💼 Firma Admin Atama</h1>
            <div class="nav">
                <a class="btn btn-secondary" href="/admin/index.php">🏠 Admin Paneli</a>
            </div>
        </div>
        
        <?php if ($message): ?><div class="alert alert-success"><?=$message?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?=$error?></div><?php endif; ?>

        <!-- Aktif Admin ve Firma Adminleri Listesi -->
        <div class="form-section">
            <h3 class="form-title">👨‍💼 Aktif Admin ve Firma Adminleri</h3>
            <?php if ($firmaAdmins): ?>
                <table>
                    <thead>
                        <tr>
                            <th>👤 Ad Soyad</th>
                            <th>📧 E-posta</th>
                            <th>🎭 Rol</th>
                            <th>🏢 Firma</th>
                            <th>⚡ İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($firmaAdmins as $admin): ?>
                            <tr>
                                <td><?=htmlspecialchars($admin['fullname'])?></td>
                                <td><?=htmlspecialchars($admin['email'])?></td>
                                <td>
                                    <span class="role-badge role-<?=htmlspecialchars($admin['role'])?>">
                                        <?=htmlspecialchars($admin['role'] === 'firma_admin' ? '👨‍💼 Firma Admin' : '🛡️ Sistem Admin')?>
                                    </span>
                                </td>
                                <td><?=htmlspecialchars($admin['company_name'] ?? '—')?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($admin['role'] !== 'admin'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="_action" value="change_role" />
                                                <input type="hidden" name="user_id" value="<?=htmlspecialchars($admin['id'])?>" />
                                                <input type="hidden" name="new_role" value="user" />
                                                <button type="submit" class="btn btn-warning" onclick="return confirm('Bu kullanıcıyı normal kullanıcı seviyesine düşürmek istediğinizden emin misiniz?')">
                                                    👤 User Yap
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="muted">🛡️ Değiştirilemez</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="muted">
                    <h3>👨‍💼 Henüz Admin Yok</h3>
                    <p>İlk firma admininizi atamak için aşağıdaki formu kullanın.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Yeni Firma Admin Atama -->
        <div class="form-section">
            <h3 class="form-title">✨ Yeni Firma Admin Atama</h3>
            <form method="post" class="form-grid">
                <input type="hidden" name="_action" value="assign" />
                <div class="form-group">
                    <label for="email">Kullanıcı E-postası</label>
                    <input type="email" name="email" id="email" placeholder="kullanici@example.com" required />
                </div>
                <div class="form-group">
                    <label for="company_id">Firma</label>
                    <select name="company_id" id="company_id" required>
                        <option value="">Firma seçin</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?=htmlspecialchars($c['id'])?>">🏢 <?=htmlspecialchars($c['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button class="btn btn-success" type="submit">👨‍💼 Firma Admin Ata</button>
                </div>
            </form>
        </div>

        <!-- Mevcut Firma Adminlerini Farklı Firmaya Taşıma -->
        <div class="form-section">
            <h3 class="form-title">🔄 Firma Admin Firma Değiştirme</h3>
            <?php if ($firmaAdmins): ?>
                <form method="post" class="form-grid">
                    <input type="hidden" name="_action" value="change_role" />
                    <div class="form-group">
                        <label for="user_id">Firma Admin</label>
                        <select name="user_id" id="user_id" required>
                            <option value="">Firma Admin seçin</option>
                            <?php foreach ($firmaAdmins as $admin): ?>
                                <?php if ($admin['role'] === 'firma_admin'): ?>
                                    <option value="<?=htmlspecialchars($admin['id'])?>">
                                        👨‍💼 <?=htmlspecialchars($admin['fullname'])?> (<?=htmlspecialchars($admin['email'])?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="new_company_id">Yeni Firma</label>
                        <select name="company_id" id="new_company_id" required>
                            <option value="">Yeni firma seçin</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?=htmlspecialchars($c['id'])?>">🏢 <?=htmlspecialchars($c['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="hidden" name="new_role" value="firma_admin" />
                        <button class="btn" type="submit">🔄 Firmayı Değiştir</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="muted">
                    <h3>👨‍💼 Firma Admin Bulunamadı</h3>
                    <p>Önce bir firma admin atayın.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>


