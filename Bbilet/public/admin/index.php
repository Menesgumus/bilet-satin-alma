<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Auth.php';

session_name((require __DIR__ . '/../../config/config.php')['session_name']);
session_start();

Auth::requireRole('admin');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 100%);
            min-height: 100vh;
            color: #333;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .admin-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .admin-title {
            font-size: 2.5rem;
            color: #1e3a8a;
            margin-bottom: 0.5rem;
        }
        .admin-subtitle {
            color: #6b7280;
            font-size: 1.1rem;
        }
        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem; 
            max-width: 800px;
            margin: 0 auto;
        }
        .admin-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .admin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            border-color: #1e3a8a;
        }
        .card-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 0.5rem;
        }
        .card-description {
            color: #6b7280;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        a.btn { 
            padding: 0.75rem 1.5rem; 
            border: none; 
            background: linear-gradient(135deg, #1e3a8a, #3730a3);
            color: #fff; 
            border-radius: 10px;
            text-decoration: none; 
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        a.btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(30, 58, 138, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
        }
        .btn-secondary:hover {
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.4);
        }
    </style>
    </head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1 class="admin-title">🛡️ Admin Paneli</h1>
            <p class="admin-subtitle">Sistem yönetimi ve kontrol paneli</p>
        </div>
        
        <div class="grid">
            <div class="admin-card">
                <div class="card-icon">👥</div>
                <div class="card-title">Kullanıcı Yönetimi</div>
                <div class="card-description">Kullanıcı oluştur, düzenle ve yönet</div>
                <a class="btn" href="/admin/users.php">Yönet</a>
            </div>
            
            <div class="admin-card">
                <div class="card-icon">🏢</div>
                <div class="card-title">Firma Yönetimi</div>
                <div class="card-description">Otobüs firmalarını yönet</div>
                <a class="btn" href="/admin/companies.php">Yönet</a>
            </div>
            
            <div class="admin-card">
                <div class="card-icon">🎟️</div>
                <div class="card-title">Kupon Yönetimi</div>
                <div class="card-description">İndirim kuponlarını yönet</div>
                <a class="btn" href="/admin/coupons.php">Yönet</a>
            </div>
            
            <div class="admin-card">
                <div class="card-icon">👨‍💼</div>
                <div class="card-title">Firma Admin Atama</div>
                <div class="card-description">Firma adminlerini yönet</div>
                <a class="btn" href="/admin/assign_firma_admin.php">Yönet</a>
            </div>
            
            <div class="admin-card">
                <div class="card-icon">🏠</div>
                <div class="card-title">Ana Sayfa</div>
                <div class="card-description">Ana sayfaya dön</div>
                <a class="btn btn-secondary" href="/index.php">Dön</a>
            </div>
        </div>
    </div>
</body>
</html>


