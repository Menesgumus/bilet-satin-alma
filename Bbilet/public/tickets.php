<?php
declare(strict_types=1);

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Auth.php';

session_name((require __DIR__ . '/../config/config.php')['session_name']);
session_start();

Auth::requireRole('user');
$pdo = Database::getConnection();

function ticket_status_label(string $s): string {
    switch ($s) {
        case 'ACTIVE': return 'Geçerli';
        case 'CANCELLED': return 'İptal Edildi';
        default: return $s;
    }
}

function fmt_dt(string $s): string {
    try {
        $dt = new DateTime($s);
        return $dt->format('d.m.Y H:i');
    } catch (Throwable $e) {
        return htmlspecialchars(str_replace('T', ' ', $s));
    }
}

function title_tr(string $s): string {
    if (function_exists('mb_convert_case')) {
        return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
    }
    return ucwords($s);
}

// List tickets
$stmt = $pdo->prepare("SELECT tk.*, tr.departure_location, tr.arrival_location, tr.departure_time, tr.arrival_time, c.name AS company_name
FROM tickets tk
JOIN trips tr ON tr.id = tk.trip_id
JOIN companies c ON c.id = tr.company_id
WHERE tk.user_id = :uid
ORDER BY tk.purchase_time DESC");
$stmt->execute([':uid' => $_SESSION['user_id']]);
$tickets = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biletlerim</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
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
            text-align: center;
        }
        .header h2 {
            font-size: 2rem;
            color: #8b5cf6;
            margin-bottom: 0.5rem;
        }
        .nav {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }
        .tickets-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .tickets-title {
            font-size: 1.5rem;
            color: #8b5cf6;
            margin-bottom: 1.5rem;
            text-align: center;
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
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
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
        .btn { 
            padding: 0.5rem 1rem; 
            border: none; 
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: #fff; 
            text-decoration: none; 
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            font-size: 0.85rem;
            margin: 0.25rem;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
        }
        .btn-danger { 
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        .btn-danger:hover {
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
        }
        .btn-secondary:hover {
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.4);
        }
        .muted { 
            color: #6b7280;
            text-align: center;
            padding: 3rem;
            font-size: 1.1rem;
        }
        .ticket-id {
            font-family: 'Courier New', monospace;
            background: #f1f3f4;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            word-break: break-all;
            max-width: 200px;
        }
        .status-active {
            color: #10b981;
            font-weight: bold;
            background: rgba(16, 185, 129, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .status-cancelled {
            color: #ef4444;
            font-weight: bold;
            background: rgba(239, 68, 68, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .route {
            font-weight: 600;
            color: #374151;
        }
        .price {
            color: #10b981;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .no-tickets {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        .no-tickets h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #8b5cf6;
        }
        .no-tickets p {
            font-size: 1.1rem;
        }
    </style>
    </head>
<body>
    <div class="container">
        <div class="header">
            <h2>🎫 Biletlerim</h2>
            <div class="nav">
                <a class="btn btn-secondary" href="/index.php">🏠 Ana Sayfa</a>
                <a class="btn" href="/account.php">👤 Hesabım</a>
            </div>
        </div>
        
        <div class="tickets-card">
            <h3 class="tickets-title">Bilet Listesi</h3>
            
            <?php if (empty($tickets)): ?>
                <div class="no-tickets">
                    <h3>🎫 Henüz Biletiniz Yok</h3>
                    <p>İlk biletinizi satın almak için ana sayfaya gidin ve sefer arayın.</p>
                    <a class="btn" href="/index.php" style="margin-top: 1rem;">🔍 Sefer Ara</a>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>🎫 Bilet No</th>
                            <th>🏢 Firma</th>
                            <th>📍 Güzergah</th>
                            <th>🕐 Kalkış</th>
                            <th>🕐 Varış</th>
                            <th>🪑 Koltuk</th>
                            <th>📊 Durum</th>
                            <th>⚡ İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td>
                                <a href="/ticket_lookup.php?id=<?=urlencode($t['id'])?>" class="ticket-id">
                                    <?=htmlspecialchars(substr($t['id'], 0, 8))?>...
                                </a>
                            </td>
                            <td><strong><?=htmlspecialchars($t['company_name'])?></strong></td>
                            <td class="route"><?=htmlspecialchars(title_tr($t['departure_location']))?> → <?=htmlspecialchars(title_tr($t['arrival_location']))?></td>
                            <td><?=fmt_dt($t['departure_time'])?></td>
                            <td><?=fmt_dt($t['arrival_time'])?></td>
                            <td><strong><?=htmlspecialchars((string)$t['seat_number'])?></strong></td>
                            <td>
                                <?php if ($t['status'] === 'ACTIVE'): ?>
                                    <span class="status-active">✅ Geçerli</span>
                                <?php else: ?>
                                    <span class="status-cancelled">❌ İptal Edildi</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <?php if ($t['status'] === 'ACTIVE'): ?>
                                        <form action="/ticket_cancel.php" method="post" style="display:inline" onsubmit="return confirm('Bu bilet iptal edilsin mi?')">
                                            <input type="hidden" name="ticket_id" value="<?=htmlspecialchars($t['id'])?>" />
                                            <button type="submit" class="btn btn-danger">❌ İptal Et</button>
                                        </form>
                                        <a class="btn" href="/ticket_pdf.php?id=<?=urlencode($t['id'])?>">📄 PDF</a>
                                        <a class="btn" href="/ticket_lookup.php?id=<?=urlencode($t['id'])?>">👁️ Detay</a>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>


