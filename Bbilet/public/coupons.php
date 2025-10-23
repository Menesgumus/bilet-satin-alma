<?php
declare(strict_types=1);

require __DIR__ . '/../src/Database.php';

session_name((require __DIR__ . '/../config/config.php')['session_name']);
session_start();

$pdo = Database::getConnection();

$sql = "SELECT cp.*, co.name AS company_name FROM coupons cp LEFT JOIN companies co ON co.id = cp.company_id WHERE cp.expiry_date > CURRENT_TIMESTAMP AND cp.usage_limit > 0 ORDER BY cp.created_at DESC";
$coupons = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktif Kuponlar</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: .5rem; border-bottom: 1px solid #ddd; text-align: left; }
        .btn { padding: .3rem .6rem; border: 1px solid #333; background: #f5f5f5; text-decoration: none; color: #111; border-radius: 4px; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <h2>Aktif Kuponlar</h2>
    <p><a class="btn" href="/index.php">Ana Sayfa</a></p>
    <table>
        <thead>
            <tr>
                <th>Kod</th>
                <th>İndirim Oranı</th>
                <th>Kalan Kullanım</th>
                <th>Son Kullanma</th>
                <th>Kapsam</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($coupons as $cp): ?>
            <tr>
                <td><?=htmlspecialchars($cp['code'])?></td>
                <td><?=number_format((float)$cp['discount_rate'] * 100, 0)?>%</td>
                <td><?=htmlspecialchars((string)$cp['usage_limit'])?></td>
                <td><?=htmlspecialchars($cp['expiry_date'])?></td>
                <td><?= $cp['company_id'] ? htmlspecialchars($cp['company_name'] ?? '') : 'Genel' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>


