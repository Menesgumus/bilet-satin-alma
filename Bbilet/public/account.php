<?php
declare(strict_types=1);

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Auth.php';

session_name((require __DIR__ . '/../config/config.php')['session_name']);
session_start();

Auth::requireRole('user');
$pdo = Database::getConnection();

$stmt = $pdo->prepare('SELECT fullname, email, balance, created_at FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) { http_response_code(404); echo 'Kullanıcı bulunamadı'; exit; }
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesabım</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
        .header h2 {
            font-size: 2rem;
            color: #10b981;
            margin-bottom: 0.5rem;
        }
        .nav {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }
        .card { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        .row { 
            display: grid; 
            grid-template-columns: 180px 1fr; 
            gap: 1rem 1.5rem; 
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f3f4;
        }
        .row:last-child {
            border-bottom: none;
        }
        .row div:first-child {
            font-weight: 600;
            color: #374151;
        }
        .row div:last-child {
            color: #6b7280;
        }
        .btn { 
            padding: 0.75rem 1.5rem; 
            border: none; 
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff; 
            text-decoration: none; 
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
        }
        .btn-secondary:hover {
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.4);
        }
        .balance { 
            font-size: 1.5rem; 
            font-weight: bold; 
            color: #10b981;
            background: linear-gradient(135deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .muted { 
            color: #6b7280;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        .info-icon {
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }
    </style>
    </head>
<body>
    <div class="container">
        <div class="header">
            <h2>👤 Hesabım</h2>
            <div class="nav">
                <a class="btn" href="/index.php">🏠 Ana Sayfa</a>
                <a class="btn btn-secondary" href="/tickets.php">🎫 Biletlerim</a>
            </div>
        </div>
        
        <div class="card">
            <div class="row">
                <div><span class="info-icon">👤</span>Ad Soyad</div>
                <div><?=htmlspecialchars($user['fullname'])?></div>
            </div>
            <div class="row">
                <div><span class="info-icon">📧</span>E-posta</div>
                <div><?=htmlspecialchars($user['email'])?></div>
            </div>
            <div class="row">
                <div><span class="info-icon">💰</span>Bakiye</div>
                <div class="balance"><?=number_format((float)$user['balance'], 2)?> ₺</div>
            </div>
            <div class="row">
                <div><span class="info-icon">📅</span>Kayıt Tarihi</div>
                <div><?=htmlspecialchars($user['created_at'])?></div>
            </div>
            
            <div class="muted">
                <strong>ℹ️ Bilgi:</strong> Bakiye yükleme bu sürümde pasif. Satın almalar bakiyenizden düşer, iptaller iade eder.
            </div>
        </div>
    </div>
</body>
</html>


