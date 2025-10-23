<?php
declare(strict_types=1);

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Auth.php';

session_name((require __DIR__ . '/../config/config.php')['session_name']);
session_start();

Auth::requireRole('user');
$pdo = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$ticketId = $_POST['ticket_id'] ?? '';
if ($ticketId === '') { http_response_code(400); echo 'Geçersiz istek'; exit; }

try {
    $pdo->beginTransaction();

    $s = $pdo->prepare("SELECT tk.*, tr.departure_time, tr.price FROM tickets tk JOIN trips tr ON tr.id = tk.trip_id WHERE tk.id = :id AND tk.user_id = :uid LIMIT 1");
    $s->execute([':id' => $ticketId, ':uid' => $_SESSION['user_id']]);
    $t = $s->fetch();
    if (!$t) { throw new RuntimeException('Bilet bulunamadı'); }
    if ($t['status'] !== 'ACTIVE') { throw new RuntimeException('Bilet aktif değil'); }

    $dep = new DateTimeImmutable($t['departure_time']);
    $now = new DateTimeImmutable('now');
    if ($dep->getTimestamp() - $now->getTimestamp() < 3600) {
        throw new RuntimeException('Kalkışa 1 saat kala iptal edilemez');
    }

    // Cancel ticket
    $pdo->prepare("UPDATE tickets SET status='CANCELLED' WHERE id = :id")->execute([':id' => $ticketId]);
    // Refund paid amount
    $refundAmount = $t['paid_amount'] ?? (float)$t['price'];
    $pdo->prepare('UPDATE users SET balance = balance + :amt WHERE id = :id')->execute([
        ':amt' => $refundAmount,
        ':id' => $_SESSION['user_id'],
    ]);

    $pdo->commit();
    header('Location: /tickets.php');
    exit;
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo $e->getMessage();
}


