<?php
declare(strict_types=1);

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Util.php';

echo "🔄 Veritabanı sıfırlanıyor...\n";

$pdo = Database::getConnection();

// Tüm tabloları temizle
$tables = ['tickets', 'coupons', 'trips', 'companies', 'users'];

foreach ($tables as $table) {
    echo "🗑️ $table tablosu temizleniyor...\n";
    $pdo->exec("DELETE FROM $table");
}

echo "✅ Veritabanı başarıyla sıfırlandı!\n";
echo "🚀 Artık temiz bir sistemle başlayabilirsiniz!\n";
