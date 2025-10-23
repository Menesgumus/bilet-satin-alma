<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Database.php';
require __DIR__ . '/../../src/Auth.php';
require __DIR__ . '/../../src/Util.php';

session_name((require __DIR__ . '/../../config/config.php')['session_name']);
session_start();

Auth::requireRole('admin');
$pdo = Database::getConnection();

// Create / Update / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $pdo->prepare('INSERT INTO companies (id, name) VALUES (:id,:name)')->execute([
                ':id' => Util::generateUuidV4(),
                ':name' => $name,
            ]);
        }
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        if ($id && $name !== '') {
            $pdo->prepare('UPDATE companies SET name = :name WHERE id = :id')->execute([
                ':name' => $name,
                ':id' => $id,
            ]);
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id) {
            $pdo->prepare('DELETE FROM companies WHERE id = :id')->execute([':id' => $id]);
        }
    }
    header('Location: /admin/companies.php');
    exit;
}

$companies = $pdo->query('SELECT * FROM companies ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Yönetimi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 100%);
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
        .form-group {
            display: flex;
            gap: 1rem;
            align-items: end;
            margin-bottom: 1rem;
        }
        .form-group input {
            flex: 1;
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
        .company-form {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .company-form input {
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        .company-form input:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
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
            <h1>🏢 Firma Yönetimi</h1>
            <div class="nav">
                <a class="btn btn-secondary" href="/admin/index.php">🏠 Admin Paneli</a>
            </div>
        </div>
        <!-- Yeni Firma Ekleme -->
        <div class="form-section">
            <h3 class="form-title">✨ Yeni Firma Ekle</h3>
            <form method="post" class="form-group">
                <input type="hidden" name="_action" value="create" />
                <input name="name" placeholder="Firma adı girin" required />
                <button class="btn btn-success" type="submit">🏢 Firma Ekle</button>
            </form>
        </div>

        <!-- Firma Listesi -->
        <div class="form-section">
            <h3 class="form-title">🏢 Mevcut Firmalar</h3>
            <?php if ($companies): ?>
                <table>
                    <thead>
                        <tr>
                            <th>🏢 Firma Adı</th>
                            <th>📅 Oluşturulma Tarihi</th>
                            <th>⚡ İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $c): ?>
                            <tr>
                                <td>
                                    <form method="post" class="company-form">
                                        <input type="hidden" name="_action" value="update" />
                                        <input type="hidden" name="id" value="<?=htmlspecialchars($c['id'])?>" />
                                        <input name="name" value="<?=htmlspecialchars($c['name'])?>" style="flex: 1;" />
                                        <button class="btn" type="submit">✏️ Kaydet</button>
                                    </form>
                                </td>
                                <td style="font-size: 0.9rem;"><?=htmlspecialchars($c['created_at'])?></td>
                                <td>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Bu firmayı silmek istediğinizden emin misiniz?')">
                                        <input type="hidden" name="_action" value="delete" />
                                        <input type="hidden" name="id" value="<?=htmlspecialchars($c['id'])?>" />
                                        <button class="btn btn-danger" type="submit">🗑️ Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="muted">
                    <h3>🏢 Henüz Firma Yok</h3>
                    <p>İlk firmanızı eklemek için yukarıdaki formu kullanın.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>


