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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    
    if ($action === 'update_company') {
        $companyName = trim($_POST['company_name'] ?? '');
        
        if ($companyName !== '') {
            try {
                $pdo->prepare('UPDATE companies SET name = :name WHERE id = :id')->execute([
                    ':name' => $companyName,
                    ':id' => $company['id']
                ]);
                $message = 'Firma bilgileri başarıyla güncellendi.';
                // Refresh company data
                $stmt = $pdo->prepare('SELECT c.*, u.fullname FROM companies c JOIN users u ON u.company_id = c.id WHERE u.id = :id LIMIT 1');
                $stmt->execute([':id' => $_SESSION['user_id']]);
                $company = $stmt->fetch();
            } catch (PDOException $e) {
                $error = 'Firma bilgileri güncellenemedi.';
            }
        } else {
            $error = 'Firma adı boş olamaz.';
        }
    } elseif ($action === 'update_profile') {
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if ($fullname !== '' && $email !== '') {
            try {
                $updateData = [
                    ':id' => $_SESSION['user_id'],
                    ':fullname' => $fullname,
                    ':email' => $email
                ];
                
                $sql = 'UPDATE users SET fullname = :fullname, email = :email';
                $params = $updateData;
                
                if ($password !== '') {
                    $sql .= ', password = :password';
                    $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                }
                
                $sql .= ' WHERE id = :id';
                
                $pdo->prepare($sql)->execute($params);
                $message = 'Profil bilgileri başarıyla güncellendi.';
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'UNIQUE')) {
                    $error = 'Bu e-posta adresi zaten kullanılıyor.';
                } else {
                    $error = 'Profil bilgileri güncellenemedi.';
                }
            }
        } else {
            $error = 'Ad soyad ve e-posta alanları zorunludur.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Ayarları - <?=htmlspecialchars($company['name'])?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            min-height: 100vh;
            color: #333;
        }
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem; }
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
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        .nav {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        .settings-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .card-title {
            font-size: 1.3rem;
            color: #6b7280;
            margin-bottom: 1.5rem;
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
        }
        .form-group input:focus {
            outline: none;
            border-color: #6b7280;
            box-shadow: 0 0 0 3px rgba(107, 114, 128, 0.1);
        }
        .btn { 
            padding: 0.75rem 1.5rem; 
            border: none; 
            background: linear-gradient(135deg, #6b7280, #4b5563);
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
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }
        .btn-secondary:hover {
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
        }
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
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
        .info-text {
            color: #6b7280;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Firma Ayarları</h1>
            <div class="nav">
                <a class="btn" href="/company/index.php">🏠 Firma Paneli</a>
                <a class="btn" href="/company/trips.php">🚌 Seferler</a>
                <a class="btn" href="/company/coupons.php">🎟️ Kuponlar</a>
                <a class="btn" href="/company/reports.php">📊 Raporlar</a>
                <a class="btn btn-secondary" href="/index.php">Ana Sayfa</a>
            </div>
        </div>
        
        <?php if ($message): ?><div class="alert alert-success"><?=$message?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?=$error?></div><?php endif; ?>
        
        <div class="settings-grid">
            <!-- Firma Bilgileri -->
            <div class="settings-card">
                <h3 class="card-title">🏢 Firma Bilgileri</h3>
                <form method="post">
                    <input type="hidden" name="_action" value="update_company" />
                    <div class="form-group">
                        <label for="company_name">Firma Adı</label>
                        <input type="text" id="company_name" name="company_name" value="<?=htmlspecialchars($company['name'])?>" required />
                    </div>
                    <div class="form-group">
                        <label>Firma ID</label>
                        <input type="text" value="<?=htmlspecialchars($company['id'])?>" readonly style="background: #f9fafb; color: #6b7280;" />
                        <div class="info-text">Firma ID değiştirilemez</div>
                    </div>
                    <div class="form-group">
                        <label>Oluşturulma Tarihi</label>
                        <input type="text" value="<?=htmlspecialchars($company['created_at'])?>" readonly style="background: #f9fafb; color: #6b7280;" />
                    </div>
                    <button type="submit" class="btn">💾 Firma Bilgilerini Güncelle</button>
                </form>
            </div>
            
            <!-- Profil Bilgileri -->
            <div class="settings-card">
                <h3 class="card-title">👤 Profil Bilgileri</h3>
                <form method="post">
                    <input type="hidden" name="_action" value="update_profile" />
                    <div class="form-group">
                        <label for="fullname">Ad Soyad</label>
                        <input type="text" id="fullname" name="fullname" value="<?=htmlspecialchars($company['fullname'])?>" required />
                    </div>
                    <div class="form-group">
                        <label for="email">E-posta</label>
                        <input type="email" id="email" name="email" value="<?=htmlspecialchars($company['email'] ?? '')?>" required />
                    </div>
                    <div class="form-group">
                        <label for="password">Yeni Şifre</label>
                        <input type="password" id="password" name="password" placeholder="Değiştirmek istemiyorsanız boş bırakın" />
                        <div class="info-text">Şifre değiştirmek istemiyorsanız boş bırakın</div>
                    </div>
                    <button type="submit" class="btn">💾 Profil Bilgilerini Güncelle</button>
                </form>
            </div>
        </div>
        
        <!-- Firma İstatistikleri -->
        <div class="settings-card" style="margin-top: 2rem;">
            <h3 class="card-title">📊 Firma İstatistikleri</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div style="text-align: center; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 2rem; font-weight: bold; color: #6b7280;"><?=htmlspecialchars($company['name'])?></div>
                    <div style="color: #9ca3af; font-size: 0.9rem;">Firma Adı</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 2rem; font-weight: bold; color: #6b7280;"><?=htmlspecialchars($company['created_at'])?></div>
                    <div style="color: #9ca3af; font-size: 0.9rem;">Kuruluş Tarihi</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 2rem; font-weight: bold; color: #6b7280;">Firma Admin</div>
                    <div style="color: #9ca3af; font-size: 0.9rem;">Yetki Seviyesi</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
