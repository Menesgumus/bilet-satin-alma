<?php
declare(strict_types=1);

require __DIR__ . '/../src/Database.php';

session_name((require __DIR__ . '/../config/config.php')['session_name']);
session_start();

function currentUserRole(): string
{
    return $_SESSION['role'] ?? 'guest';
}

$pdo = Database::getConnection();

// Get all unique departure and arrival cities (case-insensitive)
$departureCities = $pdo->query('SELECT DISTINCT LOWER(departure_location) as city FROM trips ORDER BY city ASC')->fetchAll(PDO::FETCH_COLUMN);
$arrivalCities = $pdo->query('SELECT DISTINCT LOWER(arrival_location) as city FROM trips ORDER BY city ASC')->fetchAll(PDO::FETCH_COLUMN);

// Simple home page with search form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from = trim($_POST['from'] ?? '');
    $to = trim($_POST['to'] ?? '');
    $params = [];
    $where = [];
    if ($from !== '') { $where[] = 'LOWER(departure_location) = LOWER(:from)'; $params[':from'] = $from; }
    if ($to !== '') { $where[] = 'LOWER(arrival_location) = LOWER(:to)'; $params[':to'] = $to; }
    $sql = 'SELECT t.*, c.name AS company_name FROM trips t JOIN companies c ON c.id = t.company_id';
    if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
    $sql .= ' ORDER BY departure_time ASC LIMIT 100';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $trips = $stmt->fetchAll();
} else {
    $trips = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bbilet - Ana Sayfa</title>
    <link rel="stylesheet" href="/style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        header { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            display: flex; 
            justify-content: space-between; 
            align-items: center;
        }
        .logo { font-size: 2rem; font-weight: bold; color: #667eea; }
        .nav { display: flex; gap: 1rem; align-items: center; }
        .search-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .search-title { font-size: 1.5rem; margin-bottom: 1.5rem; color: #333; }
        form.search { 
            display: grid; 
            grid-template-columns: 1fr 1fr auto; 
            gap: 1rem; 
            max-width: 800px; 
        }
        form.search select { 
            padding: 1rem; 
            border: 2px solid #e1e5e9; 
            border-radius: 10px; 
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        form.search select:focus { 
            outline: none; 
            border-color: #667eea; 
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .results-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 1rem;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        th { 
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }
        td { 
            padding: 1rem; 
            border-bottom: 1px solid #f1f3f4;
            transition: background-color 0.2s ease;
        }
        tr:hover td { background-color: #f8f9fa; }
        .btn { 
            padding: 0.75rem 1.5rem; 
            border: none; 
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; 
            text-decoration: none; 
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-block;
        }
        .btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-primary { 
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .btn-primary:hover {
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        .muted { color: #6b7280; }
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
            font-size: 1.1rem;
        }
        .info-text {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            color: #1e40af;
        }
    </style>
    <script>
        function requireLogin(){
            alert('Lütfen giriş yapın.');
            window.location.href = '/login.php';
        }

        // City selection functionality
        document.addEventListener('DOMContentLoaded', function() {
            const fromCity = document.getElementById('fromCity');
            const toCity = document.getElementById('toCity');

            fromCity.addEventListener('change', function() {
                const selectedFrom = this.value;
                
                // Reset to city selection
                toCity.innerHTML = '<option value="">Varış Şehri Seçin</option>';
                
                if (selectedFrom === '') {
                    return;
                }

                // Fetch available arrival cities (case-insensitive)
                fetch(`/api/get_cities.php?from=${encodeURIComponent(selectedFrom.toLowerCase())}`)
                    .then(response => response.json())
                    .then(data => {
                        data.cities.forEach(city => {
                            const option = document.createElement('option');
                            option.value = city;
                            option.textContent = city.charAt(0).toUpperCase() + city.slice(1); // Capitalize first letter
                            toCity.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching cities:', error);
                    });
            });

            // Prevent form submission if both cities are not selected
            document.getElementById('searchForm').addEventListener('submit', function(e) {
                if (fromCity.value === '' || toCity.value === '') {
                    e.preventDefault();
                    alert('Lütfen hem kalkış hem de varış şehrini seçin.');
                }
            });
        });
    </script>
    </head>
<body>
    <div class="container">
        <header>
            <div class="logo">🚌 Bbilet</div>
            <nav class="nav">
                <?php if (currentUserRole() === 'guest'): ?>
                    <a class="btn" href="/login.php">Giriş Yap</a>
                    <a class="btn" href="/register.php">Kayıt Ol</a>
                    <a class="btn" href="/ticket_lookup.php">Bilet Sorgula</a>
                <?php else: ?>
                    <span class="muted">Rol: <?=htmlspecialchars(currentUserRole())?></span>
                    <?php if (currentUserRole() === 'admin'): ?>
                        <a class="btn btn-primary" href="/admin/index.php">Admin Paneli</a>
                    <?php endif; ?>
                    <?php if (currentUserRole() === 'user'): ?>
                        <a class="btn" href="/account.php">Hesabım</a>
                    <?php endif; ?>
                <?php if (currentUserRole() === 'firma_admin'): ?>
                    <a class="btn btn-primary" href="/company/index.php">Firma Paneli</a>
                <?php endif; ?>
                    <a class="btn" href="/ticket_lookup.php">Bilet Sorgula</a>
                    <a class="btn" href="/tickets.php">Biletlerim</a>
                    <a class="btn" href="/logout.php">Çıkış</a>
                <?php endif; ?>
            </nav>
        </header>

        <div class="search-card">
            <h2 class="search-title">🔍 Sefer Ara</h2>
            <form class="search" method="post" id="searchForm">
                <select name="from" id="fromCity" required>
                    <option value="">Kalkış Şehri Seçin</option>
                    <?php foreach ($departureCities as $city): ?>
                        <option value="<?=htmlspecialchars($city)?>" <?=strtolower($_POST['from'] ?? '') === strtolower($city) ? 'selected' : ''?>>
                            <?=htmlspecialchars(ucfirst($city))?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="to" id="toCity" required>
                    <option value="">Varış Şehri Seçin</option>
                    <?php foreach ($arrivalCities as $city): ?>
                        <option value="<?=htmlspecialchars($city)?>" <?=strtolower($_POST['to'] ?? '') === strtolower($city) ? 'selected' : ''?>>
                            <?=htmlspecialchars(ucfirst($city))?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit">🔍 Ara</button>
            </form>
        </div>

        <?php if ($trips): ?>
            <div class="results-card">
                <h3 style="margin-bottom: 1.5rem; color: #333;">🚌 Bulunan Seferler</h3>
                <table>
                    <thead>
                        <tr>
                            <th>🏢 Firma</th>
                            <th>📍 Kalkış</th>
                            <th>📍 Varış</th>
                            <th>🕐 Kalkış Saati</th>
                            <th>💰 Fiyat</th>
                            <th>⚡ İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($trips as $trip): ?>
                        <tr>
                            <td><strong><?=htmlspecialchars($trip['company_name'])?></strong></td>
                            <td><?=htmlspecialchars($trip['departure_location'])?></td>
                            <td><?=htmlspecialchars($trip['arrival_location'])?></td>
                            <td><?=htmlspecialchars($trip['departure_time'])?></td>
                            <td><strong style="color: #059669; font-size: 1.1rem;"><?=number_format((float)$trip['price'], 2)?> ₺</strong></td>
                            <td>
                                <?php if (currentUserRole() === 'user'): ?>
                                    <a class="btn btn-primary" href="/purchase.php?trip=<?=urlencode($trip['id'])?>">🎫 Satın Al</a>
                                <?php else: ?>
                                    <button class="btn" type="button" onclick="requireLogin()">🎫 Satın Al</button>
                                <?php endif; ?>
                                <a class="btn" href="/coupons.php" style="margin-left: 0.5rem; background: linear-gradient(135deg, #f59e0b, #d97706);">🎟️ Kuponlar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="results-card">
                <div class="no-results">
                    <h3>😔 Sonuç bulunamadı</h3>
                    <p>Farklı şehirler deneyebilir veya daha sonra tekrar kontrol edebilirsiniz.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="info-text">
            <strong>ℹ️ Bilgi:</strong> Ziyaretçiler seferleri görebilir, satın almak için giriş gerekir.
        </div>
    </div>
</body>
</html>


