<?php
declare(strict_types=1);

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Util.php';

session_name((require __DIR__ . '/../config/config.php')['session_name']);
session_start();

Auth::requireRole('user');

$pdo = Database::getConnection();

$tripId = $_GET['trip'] ?? '';
if ($tripId === '') { http_response_code(400); echo 'Sefer belirtilmedi'; exit; }

// Fetch trip
$stmt = $pdo->prepare('SELECT t.*, c.name AS company_name FROM trips t JOIN companies c ON c.id = t.company_id WHERE t.id = :id LIMIT 1');
$stmt->execute([':id' => $tripId]);
$trip = $stmt->fetch();
if (!$trip) { http_response_code(404); echo 'Sefer bulunamadı'; exit; }

// Fetch taken seats
$takenStmt = $pdo->prepare("SELECT seat_number FROM tickets WHERE trip_id = :id AND status = 'ACTIVE'");
$takenStmt->execute([':id' => $tripId]);
$taken = array_map('intval', array_column($takenStmt->fetchAll(), 'seat_number'));

// Fetch user balance
$uStmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id LIMIT 1');
$uStmt->execute([':id' => $_SESSION['user_id']]);
$balance = (float)$uStmt->fetchColumn();

$message = '';
$error = '';
$previewPrice = null; // kullanıcıya gösterilen önizleme satırı
$calculatedPrice = null; // mevcut POST içinde geçerli kupondan hesaplanan fiyat (butonda kullanılır)
$selectedSeat = isset($_POST['seat']) ? (int)$_POST['seat'] : 0;

// Determine seat layout: prefer 2+2, but if seat_count divisible by 3, use 2+1
$seatCount = (int)$trip['seat_count'];
$layout = ($seatCount > 0 && $seatCount % 3 === 0) ? '2+1' : '2+2';
// Compute rows for visualization
function buildSeatRows(int $seatCount, string $layout): array {
    $seatsPerRow = $layout === '2+1' ? 3 : 4;
    $rows = [];
    $seat = 1;
    while ($seat <= $seatCount) {
        $row = [];
        for ($i = 0; $i < $seatsPerRow && $seat <= $seatCount; $i++, $seat++) {
            $row[] = $seat;
        }
        $rows[] = $row;
    }
    return $rows;
}
$seatRows = buildSeatRows($seatCount, $layout);

// Load applicable coupons for hint
$applicableCouponsStmt = $pdo->prepare('SELECT code, discount_rate, usage_limit, expiry_date FROM coupons WHERE (company_id IS NULL OR company_id = :cid) AND usage_limit > 0 AND expiry_date > CURRENT_TIMESTAMP ORDER BY created_at DESC LIMIT 50');
$applicableCouponsStmt->execute([':cid' => $trip['company_id']]);
$applicableCoupons = $applicableCouponsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seat = (int)($_POST['seat'] ?? 0);
    $couponCode = trim($_POST['coupon'] ?? '');
    $action = $_POST['_action'] ?? 'buy';

    $seatValid = $seat > 0 && $seat <= (int)$trip['seat_count'] && !in_array($seat, $taken, true);
    if ($action === 'buy' && !$seatValid) {
        $error = $seat <= 0 ? 'Koltuk seçiniz.' : (in_array($seat, $taken, true) ? 'Koltuk dolu.' : 'Geçersiz koltuk.');
    }

    if ($error === '' && ($action === 'preview' || $action === 'buy')) {
        $price = (float)$trip['price'];
        // Apply coupon if present and valid
        if ($couponCode !== '') {
            $c = $pdo->prepare('SELECT * FROM coupons WHERE code = :code LIMIT 1');
            $c->execute([':code' => $couponCode]);
            $coupon = $c->fetch();
            if (!$coupon) {
                $error = 'Kupon bulunamadı.';
            } else {
                // validate scope
                if (!empty($coupon['company_id']) && $coupon['company_id'] !== $trip['company_id']) {
                    $error = 'Kupon bu firmada geçerli değil.';
                }
                // validate expiry
                if ($error === '') {
                    try {
                        $now = new DateTimeImmutable('now');
                        $ed = new DateTimeImmutable((string)$coupon['expiry_date']);
                        if ($ed <= $now) { $error = 'Kuponun süresi dolmuş.'; }
                    } catch (Throwable $e) { /* ignore, rely on DB check below */ }
                }
                // validate usage
                if ($error === '' && (int)$coupon['usage_limit'] <= 0) {
                    $error = 'Kupon kullanım limiti tükenmiş.';
                }
                if ($error === '') {
                    $discount = max(0.0, min(1.0, (float)$coupon['discount_rate']));
                    $price = round($price * (1 - $discount), 2);
                }
            }
        }

        if ($error === '') {
            // Bu POST isteği için hesaplanan fiyatı sakla (buton metni için)
            $calculatedPrice = $price;
            if ($action === 'preview') {
                $previewPrice = $price;
            } elseif ($balance < $price) {
                $error = 'Bakiyeniz yetersiz.';
            } else {
                {
                    try {
                    $pdo->beginTransaction();

                    // Lock seat by inserting ticket
                    $ticketId = Util::generateUuidV4();
                    $pdo->prepare('INSERT INTO tickets (id, user_id, trip_id, seat_number, status, paid_amount) VALUES (:id,:uid,:tid,:seat,\'ACTIVE\',:paid)')->execute([
                        ':id' => $ticketId,
                        ':uid' => $_SESSION['user_id'],
                        ':tid' => $tripId,
                        ':seat' => $seat,
                        ':paid' => $price,
                    ]);

                    // Decrement user balance
                    $pdo->prepare('UPDATE users SET balance = balance - :amt WHERE id = :id')->execute([
                        ':amt' => $price,
                        ':id' => $_SESSION['user_id'],
                    ]);

                    // Decrease coupon usage if used
                    if (!empty($coupon ?? null)) {
                        $pdo->prepare('UPDATE coupons SET usage_limit = usage_limit - 1 WHERE id = :id AND usage_limit > 0')->execute([':id' => $coupon['id']]);
                    }

                        $pdo->commit();
                        header('Location: /tickets.php?success=1');
                        exit;
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        $error = 'Satın alma başarısız: tekrar deneyin.';
                    }
                }
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
    <title>Bilet Satın Al</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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
            color: #f59e0b;
            margin-bottom: 0.5rem;
        }
        .trip-info {
            color: #6b7280;
            font-size: 1.1rem;
        }
        .grid { 
            display: grid; 
            grid-template-columns: 2fr 1fr; 
            gap: 2rem; 
            align-items: start; 
        }
        .seat-selection {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .coach { 
            display: inline-block; 
            padding: 20px; 
            border: 2px solid #e5e7eb; 
            border-radius: 15px; 
            background: #fafafa;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        .seat { 
            width: 50px; 
            height: 50px; 
            margin: 6px; 
            border-radius: 10px; 
            border: 2px solid #9ca3af; 
            background: #f3f4f6; 
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .seat:hover:not([disabled]) {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .seat[disabled] { 
            background: #e5e7eb; 
            color: #9ca3af; 
            cursor: not-allowed;
            opacity: 0.6;
        }
        .seat.occupied { 
            background: linear-gradient(135deg, #ef4444, #dc2626); 
            color: #fff; 
            border-color: #ef4444;
        }
        .seat.selected { 
            background: linear-gradient(135deg, #10b981, #059669); 
            color: #fff; 
            border-color: #10b981;
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        .seat-row { 
            display: flex; 
            align-items: center; 
            margin-bottom: 8px;
        }
        .aisle { 
            width: 30px; 
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 0.8rem;
        }
        .legend { 
            margin-top: 15px; 
            color: #6b7280; 
            font-size: 0.9rem;
            background: rgba(59, 130, 246, 0.1);
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        .summary {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .summary h3 {
            color: #f59e0b;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f3f4;
        }
        .summary-item:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .coupon-section {
            margin: 1.5rem 0;
            padding: 1.5rem;
            background: rgba(59, 130, 246, 0.05);
            border-radius: 10px;
            border: 1px solid rgba(59, 130, 246, 0.1);
        }
        .coupon-input {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .coupon-input input {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .coupon-input input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .btn { 
            padding: 0.75rem 1.5rem; 
            border: none; 
            background: linear-gradient(135deg, #f59e0b, #d97706);
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
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
        }
        .btn-secondary:hover {
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.4);
        }
        .error { 
            color: #dc2626;
            background: rgba(220, 38, 38, 0.1);
            border: 1px solid rgba(220, 38, 38, 0.2);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .price-display {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 600;
            margin: 1rem 0;
        }
        .coupons-list {
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border-radius: 8px;
            border: 1px solid #e1e5e9;
        }
        .coupon-item {
            padding: 0.75rem;
            border-bottom: 1px solid #f1f3f4;
            font-size: 0.9rem;
        }
        .coupon-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🎫 Bilet Satın Al</h2>
            <div class="trip-info">
                <strong><?=htmlspecialchars($trip['company_name'])?></strong> - 
                <?=htmlspecialchars($trip['departure_location'])?> → <?=htmlspecialchars($trip['arrival_location'])?>
            </div>
        </div>

    <?php if ($error): ?><p class="error"><?=$error?></p><?php endif; ?>

    <div class="grid">
            <div class="seat-selection">
                <h3>🪑 Koltuk Seçimi</h3>
            <form method="post">
                <div class="coach">
                <?php foreach ($seatRows as $row): ?>
                    <div class="seat-row">
                        <?php if ($layout === '2+2'): ?>
                            <?php for ($i = 0; $i < 2; $i++): if (!isset($row[$i])) break; $s = (int)$row[$i]; $occupied = in_array($s, $taken, true); $isSelected = (!$occupied && $selectedSeat === $s); $cls = 'seat' . ($isSelected ? ' selected' : '') . ($occupied ? ' occupied' : ''); ?>
                                <label>
                                    <input type="radio" name="seat" value="<?=$s?>" style="display:none" <?= $occupied ? 'disabled' : '' ?> <?= $isSelected ? 'checked' : '' ?>>
                                    <button type="button" class="<?=$cls?>" <?= $occupied ? 'disabled' : '' ?> onclick="selectSeat(<?=$s?>, this)"><?=$s?></button>
                                </label>
                            <?php endfor; ?>
                            <div class="aisle"></div>
                            <?php for ($i = 2; $i < 4; $i++): if (!isset($row[$i])) break; $s = (int)$row[$i]; $occupied = in_array($s, $taken, true); $isSelected = (!$occupied && $selectedSeat === $s); $cls = 'seat' . ($isSelected ? ' selected' : '') . ($occupied ? ' occupied' : ''); ?>
                                <label>
                                    <input type="radio" name="seat" value="<?=$s?>" style="display:none" <?= $occupied ? 'disabled' : '' ?> <?= $isSelected ? 'checked' : '' ?>>
                                    <button type="button" class="<?=$cls?>" <?= $occupied ? 'disabled' : '' ?> onclick="selectSeat(<?=$s?>, this)"><?=$s?></button>
                                </label>
                            <?php endfor; ?>
                        <?php else: /* 2+1 */ ?>
                            <?php for ($i = 0; $i < 2; $i++): if (!isset($row[$i])) break; $s = (int)$row[$i]; $occupied = in_array($s, $taken, true); $isSelected = (!$occupied && $selectedSeat === $s); $cls = 'seat' . ($isSelected ? ' selected' : '') . ($occupied ? ' occupied' : ''); ?>
                                <label>
                                    <input type="radio" name="seat" value="<?=$s?>" style="display:none" <?= $occupied ? 'disabled' : '' ?> <?= $isSelected ? 'checked' : '' ?>>
                                    <button type="button" class="<?=$cls?>" <?= $occupied ? 'disabled' : '' ?> onclick="selectSeat(<?=$s?>, this)"><?=$s?></button>
                                </label>
                            <?php endfor; ?>
                            <div class="aisle"></div>
                            <?php if (isset($row[2])): $s = (int)$row[2]; $occupied = in_array($s, $taken, true); $isSelected = (!$occupied && $selectedSeat === $s); $cls = 'seat' . ($isSelected ? ' selected' : '') . ($occupied ? ' occupied' : ''); ?>
                                <label>
                                    <input type="radio" name="seat" value="<?=$s?>" style="display:none" <?= $occupied ? 'disabled' : '' ?> <?= $isSelected ? 'checked' : '' ?>>
                                    <button type="button" class="<?=$cls?>" <?= $occupied ? 'disabled' : '' ?> onclick="selectSeat(<?=$s?>, this)"><?=$s?></button>
                                </label>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                        <div class="legend">
                            <strong>🎯 Koltuk Durumu:</strong> 
                            <span style="color: #10b981;">● Yeşil: Seçili</span> | 
                            <span style="color: #ef4444;">● Kırmızı: Dolu</span> | 
                            <span style="color: #6b7280;">● Gri: Boş</span> | 
                            <span style="color: #9ca3af;">| Koridor</span>
                        </div>
                    </div>
                    
                    <div class="coupon-section">
                        <h4>🎟️ Kupon Kullan</h4>
                        <div class="coupon-input">
                            <input name="coupon" placeholder="Kupon kodunuzu girin" value="<?=htmlspecialchars($_POST['coupon'] ?? '')?>" />
                            <button class="btn btn-secondary" name="_action" value="preview" type="submit">Doğrula</button>
                </div>
                <?php if ($previewPrice !== null): ?>
                            <div class="price-display">
                                İndirimsiz: <?=number_format((float)$trip['price'],2)?> ₺ → 
                                İndirimli: <?=number_format($previewPrice,2)?> ₺
                            </div>
                <?php endif; ?>
                    </div>
                    
                <?php $buttonPrice = $calculatedPrice !== null ? $calculatedPrice : (float)$trip['price']; ?>
                    <button class="btn" name="_action" value="buy" type="submit" style="width: 100%; font-size: 1.1rem; padding: 1rem;">
                        🎫 Satın Al (<?=number_format($buttonPrice,2)?> ₺)
                    </button>
            </form>
        </div>
            
            <div class="summary">
                <h3>📋 Özet</h3>
                <div class="summary-item">
                    <span>💰 Bakiye:</span>
                    <span><?=number_format($balance,2)?> ₺</span>
                </div>
                <div class="summary-item">
                    <span>🕐 Kalkış:</span>
                    <span><?=htmlspecialchars($trip['departure_time'])?></span>
                </div>
                <div class="summary-item">
                    <span>🕐 Varış:</span>
                    <span><?=htmlspecialchars($trip['arrival_time'])?></span>
                </div>
                
                <h3 style="margin-top: 2rem; margin-bottom: 1rem;">🎟️ Uygulanabilir Kuponlar</h3>
            <?php if ($applicableCoupons): ?>
                    <div class="coupons-list">
                    <?php foreach ($applicableCoupons as $ac): ?>
                            <div class="coupon-item">
                                <strong><?=htmlspecialchars($ac['code'])?></strong> - 
                                %<?=number_format((float)$ac['discount_rate']*100,0)?> indirim 
                                (Kalan: <?=htmlspecialchars((string)$ac['usage_limit'])?>)
                            </div>
                    <?php endforeach; ?>
                    </div>
            <?php else: ?>
                    <p style="color: #6b7280; text-align: center; padding: 1rem;">Uygulanabilir kupon bulunmuyor</p>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function selectSeat(num, btn){
            const inputs = document.querySelectorAll('input[name="seat"]');
            inputs.forEach(i => { if(!i.disabled){ i.checked = (parseInt(i.value)===num); }});
            const buttons = document.querySelectorAll('.seat');
            buttons.forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
        }
    </script>
</body>
</html>


