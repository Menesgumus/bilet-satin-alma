<?php
declare(strict_types=1);

require __DIR__ . '/../src/Database.php';

session_name((require __DIR__ . '/../config/config.php')['session_name']);
session_start();

$pdo = Database::getConnection();

$ticketId = trim($_GET['id'] ?? '');
$ticket = null;
if ($ticketId !== '') {
    $stmt = $pdo->prepare("SELECT tk.*, us.fullname, us.email, tr.departure_location, tr.arrival_location, tr.departure_time, tr.arrival_time, tr.price, co.name AS company_name
FROM tickets tk
JOIN users us ON us.id = tk.user_id
JOIN trips tr ON tr.id = tk.trip_id
JOIN companies co ON co.id = tr.company_id
WHERE tk.id = :id LIMIT 1");
    $stmt->execute([':id' => $ticketId]);
    $ticket = $stmt->fetch();
}

function ticket_status_label_view(string $s): string {
    return $s === 'ACTIVE' ? 'Geçerli' : ($s === 'CANCELLED' ? 'İptal Edildi' : $s);
}

function fmt_dt_view(?string $s): string {
    if (!$s) return '';
    try { return (new DateTime($s))->format('d.m.Y H:i'); } catch (Throwable $e) { return (string)$s; }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilet Sorgula</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
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
            color: #3b82f6;
            margin-bottom: 0.5rem;
        }
        .search-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .search-form {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        .search-input {
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .btn { 
            padding: 1rem 2rem; 
            border: none; 
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: #fff; 
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
        }
        .btn-secondary:hover {
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.4);
        }
        .card { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        .card-title {
            font-size: 1.5rem;
            color: #3b82f6;
            margin-bottom: 1.5rem;
            text-align: center;
            border-bottom: 2px solid #e1e5e9;
            padding-bottom: 1rem;
        }
        .row { 
            display: grid; 
            grid-template-columns: 200px 1fr; 
            gap: 1rem 1.5rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f3f4;
        }
        .row:last-child {
            border-bottom: none;
        }
        .row div:first-child {
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
        }
        .row div:last-child {
            color: #6b7280;
        }
        .muted { 
            color: #6b7280;
            text-align: center;
            padding: 2rem;
            font-size: 1.1rem;
        }
        .info-icon {
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }
        .ticket-id {
            font-family: 'Courier New', monospace;
            background: #f1f3f4;
            padding: 0.5rem;
            border-radius: 5px;
            font-size: 0.9rem;
            word-break: break-all;
        }
        .status-active {
            color: #10b981;
            font-weight: bold;
            background: rgba(16, 185, 129, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        .status-cancelled {
            color: #ef4444;
            font-weight: bold;
            background: rgba(239, 68, 68, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }
    </style>
    </head>
<body>
    <div class="container">
        <div class="header">
            <h2>🔍 Bilet Sorgula</h2>
            <a class="btn btn-secondary" href="/index.php">🏠 Ana Sayfa</a>
        </div>
        
        <div class="search-card">
            <form method="get" class="search-form">
                <input name="id" class="search-input" placeholder="Bilet numaranızı girin (UUID)" value="<?=htmlspecialchars($ticketId)?>" />
                <button class="btn" type="submit">🔍 Sorgula</button>
            </form>
        </div>

        <?php if ($ticketId !== '' && !$ticket): ?>
            <div class="card">
                <div class="muted">
                    <h3>😔 Bilet Bulunamadı</h3>
                    <p>Lütfen bilet numaranızı kontrol edin ve tekrar deneyin.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($ticket): ?>
            <div class="card">
                <div class="card-title">🎫 Bilet Detayları</div>
                <div class="row">
                    <div><span class="info-icon">🎫</span>Bilet No</div>
                    <div class="ticket-id"><?=htmlspecialchars($ticket['id'])?></div>
                </div>
                <div class="row">
                    <div><span class="info-icon">👤</span>Yolcu</div>
                    <div><?=htmlspecialchars($ticket['fullname'])?> (<?=htmlspecialchars($ticket['email'])?>)</div>
                </div>
                <div class="row">
                    <div><span class="info-icon">🏢</span>Firma</div>
                    <div><?=htmlspecialchars($ticket['company_name'])?></div>
                </div>
                <div class="row">
                    <div><span class="info-icon">📍</span>Güzergah</div>
                    <div><?=htmlspecialchars($ticket['departure_location'])?> → <?=htmlspecialchars($ticket['arrival_location'])?></div>
                </div>
                <div class="row">
                    <div><span class="info-icon">🕐</span>Kalkış</div>
                    <div><?=fmt_dt_view($ticket['departure_time'])?></div>
                </div>
                <div class="row">
                    <div><span class="info-icon">🕐</span>Varış</div>
                    <div><?=fmt_dt_view($ticket['arrival_time'])?></div>
                </div>
                <div class="row">
                    <div><span class="info-icon">🪑</span>Koltuk</div>
                    <div><?=htmlspecialchars((string)$ticket['seat_number'])?></div>
                </div>
                <div class="row">
                    <div><span class="info-icon">📊</span>Durum</div>
                    <div>
                        <?php if ($ticket['status'] === 'ACTIVE'): ?>
                            <span class="status-active">✅ Geçerli</span>
                        <?php else: ?>
                            <span class="status-cancelled">❌ İptal Edildi</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row">
                    <div><span class="info-icon">📅</span>Satın Alım</div>
                    <div><?=fmt_dt_view($ticket['purchase_time'])?></div>
                </div>
                <div class="row">
                    <div><span class="info-icon">💰</span>Fiyat</div>
                    <div><strong style="color: #10b981; font-size: 1.1rem;"><?=number_format((float)($ticket['paid_amount'] ?? $ticket['price']),2)?> ₺</strong></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>


