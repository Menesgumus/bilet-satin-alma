<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Database.php';
require __DIR__ . '/../../src/Auth.php';

session_name((require __DIR__ . '/../../config/config.php')['session_name']);
session_start();

Auth::requireRole('firma_admin');
$pdo = Database::getConnection();

// Company id
$stmt = $pdo->prepare('SELECT company_id FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $_SESSION['user_id']]);
$companyId = $stmt->fetchColumn();
if (!$companyId) { http_response_code(400); echo 'Firma ataması yok'; exit; }

$id = $_GET['id'] ?? '';
$trip = null;
if ($id) {
    $s = $pdo->prepare('SELECT * FROM trips WHERE id = :id AND company_id = :cid LIMIT 1');
    $s->execute([':id' => $id, ':cid' => $companyId]);
    $trip = $s->fetch();
}
if (!$trip) { http_response_code(404); echo 'Sefer bulunamadı'; exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dep = trim($_POST['departure_location'] ?? '');
    $arr = trim($_POST['arrival_location'] ?? '');
    $dt = trim($_POST['departure_time'] ?? '');
    $at = trim($_POST['arrival_time'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $seats = (int)($_POST['seat_count'] ?? 0);
    if ($dep === '' || $arr === '' || $dt === '' || $at === '' || $price <= 0 || $seats <= 0) {
        $error = 'Alanlar geçerli olmalıdır.';
    } else {
        $pdo->prepare('UPDATE trips SET departure_location=:dep, arrival_location=:arr, departure_time=:dt, arrival_time=:at, price=:price, seat_count=:seats WHERE id=:id AND company_id=:cid')->execute([
            ':dep' => $dep,
            ':arr' => $arr,
            ':dt' => $dt,
            ':at' => $at,
            ':price' => $price,
            ':seats' => $seats,
            ':id' => $id,
            ':cid' => $companyId,
        ]);
        header('Location: /company/trips.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sefer Düzenle</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; }
        form { display: grid; gap: .5rem; max-width: 520px; }
        .btn { padding: .4rem .8rem; border: 1px solid #333; background: #2563eb; color: #fff; border-radius: 4px; }
        .error { color: #b91c1c; }
    </style>
</head>
<body>
    <h2>Sefer Düzenle</h2>
    <?php if ($error): ?><p class="error"><?=$error?></p><?php endif; ?>
    <form method="post">
        <input name="departure_location" placeholder="Kalkış" value="<?=htmlspecialchars($trip['departure_location'])?>" required />
        <input name="arrival_location" placeholder="Varış" value="<?=htmlspecialchars($trip['arrival_location'])?>" required />
        <input type="datetime-local" name="departure_time" value="<?=str_replace(' ','T',htmlspecialchars($trip['departure_time']))?>" required />
        <input type="datetime-local" name="arrival_time" value="<?=str_replace(' ','T',htmlspecialchars($trip['arrival_time']))?>" required />
        <input type="number" step="0.01" name="price" value="<?=htmlspecialchars($trip['price'])?>" required />
        <input type="number" name="seat_count" value="<?=htmlspecialchars($trip['seat_count'])?>" required />
        <button class="btn" type="submit">Kaydet</button>
        <a class="btn" href="/company/trips.php" style="background:#6b7280;border-color:#6b7280">İptal</a>
    </form>
</body>
</html>


