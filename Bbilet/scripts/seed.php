<?php
declare(strict_types=1);

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Util.php';

$pdo = Database::getConnection();
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->beginTransaction();
try {
    // Seed companies
    $companies = [
        ['name' => 'HızlıTur', 'admin_email' => 'firma1@firma', 'admin_name' => 'HızlıTur Yetkilisi'],
        ['name' => 'Güvenli Yolculuk', 'admin_email' => 'firma2@firma', 'admin_name' => 'Güvenli Yolculuk Yetkilisi'],
        ['name' => 'Mega Turizm', 'admin_email' => 'firma3@firma', 'admin_name' => 'Mega Turizm Yetkilisi'],
        ['name' => 'Anadolu Express', 'admin_email' => 'firma4@firma', 'admin_name' => 'Anadolu Express Yetkilisi'],
        ['name' => 'Şehirler Arası', 'admin_email' => 'firma5@firma', 'admin_name' => 'Şehirler Arası Yetkilisi']
    ];

    $companyIds = [];
    foreach ($companies as $company) {
        $companyId = Util::generateUuidV4();
        $pdo->prepare('INSERT INTO companies (id, name) VALUES (:id, :name)')
            ->execute([':id' => $companyId, ':name' => $company['name']]);
        $companyIds[] = ['id' => $companyId, 'name' => $company['name'], 'admin_email' => $company['admin_email'], 'admin_name' => $company['admin_name']];
    }

    // Seed admin user
    $adminEmail = 'admin@admin';
    $pdo->prepare('INSERT INTO users (id, fullname, email, password, role, balance) VALUES (:id,:fullname,:email,:password,:role,:balance)')->execute([
        ':id' => Util::generateUuidV4(),
        ':fullname' => 'Sistem Yöneticisi',
        ':email' => $adminEmail,
        ':password' => password_hash('admin', PASSWORD_DEFAULT),
        ':role' => 'admin',
        ':balance' => 0,
    ]);

    // Seed company admins
    foreach ($companyIds as $company) {
        $pdo->prepare('INSERT INTO users (id, fullname, email, password, role, company_id, balance) VALUES (:id,:fullname,:email,:password,:role,:company_id,:balance)')->execute([
            ':id' => Util::generateUuidV4(),
            ':fullname' => $company['admin_name'],
            ':email' => $company['admin_email'],
            ':password' => password_hash('firma', PASSWORD_DEFAULT),
            ':role' => 'firma_admin',
            ':company_id' => $company['id'],
            ':balance' => 0,
        ]);
    }

    // Seed regular users
    $users = [
        ['email' => 'user@user', 'name' => 'Deneme Kullanıcı', 'balance' => 1000],
        ['email' => 'ahmet@test', 'name' => 'Ahmet Yılmaz', 'balance' => 750],
        ['email' => 'fatma@test', 'name' => 'Fatma Demir', 'balance' => 500],
        ['email' => 'mehmet@test', 'name' => 'Mehmet Kaya', 'balance' => 300],
        ['email' => 'ayse@test', 'name' => 'Ayşe Özkan', 'balance' => 1200]
    ];

    foreach ($users as $user) {
        $pdo->prepare('INSERT INTO users (id, fullname, email, password, role, balance) VALUES (:id,:fullname,:email,:password,:role,:balance)')->execute([
            ':id' => Util::generateUuidV4(),
            ':fullname' => $user['name'],
            ':email' => $user['email'],
            ':password' => password_hash('user', PASSWORD_DEFAULT),
            ':role' => 'user',
            ':balance' => $user['balance'],
        ]);
    }

    // Seed trips for each company
    $tripData = [
        // HızlıTur
        [['İstanbul', 'Ankara', '+1 day 08:00', '+1 day 12:30', 450.00, 40],
         ['İstanbul', 'Ankara', '+1 day 14:00', '+1 day 18:30', 450.00, 40],
         ['Ankara', 'İzmir', '+2 days 09:00', '+2 days 15:00', 520.00, 44],
         ['İzmir', 'İstanbul', '+3 days 07:30', '+3 days 14:00', 560.00, 46],
         ['İstanbul', 'Antalya', '+4 days 10:00', '+4 days 18:00', 680.00, 50]],

        // Güvenli Yolculuk
        [['İstanbul', 'Bursa', '+1 day 09:30', '+1 day 11:30', 180.00, 30],
         ['Bursa', 'İzmir', '+2 days 08:00', '+2 days 13:00', 350.00, 35],
         ['İzmir', 'Ankara', '+3 days 10:30', '+3 days 16:30', 420.00, 38],
         ['Ankara', 'İstanbul', '+4 days 07:00', '+4 days 11:00', 380.00, 42]],

        // Mega Turizm
        [['İstanbul', 'Trabzon', '+1 day 20:00', '+2 days 08:00', 450.00, 45],
         ['Trabzon', 'İstanbul', '+3 days 20:00', '+4 days 08:00', 450.00, 45],
         ['İstanbul', 'Erzurum', '+5 days 18:00', '+6 days 06:00', 380.00, 40],
         ['Erzurum', 'İstanbul', '+7 days 18:00', '+8 days 06:00', 380.00, 40]],

        // Anadolu Express
        [['İstanbul', 'Konya', '+1 day 11:00', '+1 day 16:00', 320.00, 35],
         ['Konya', 'İstanbul', '+2 days 12:00', '+2 days 17:00', 320.00, 35],
         ['İstanbul', 'Sivas', '+3 days 13:00', '+3 days 20:00', 280.00, 32],
         ['Sivas', 'İstanbul', '+4 days 14:00', '+4 days 21:00', 280.00, 32]],

        // Şehirler Arası
        [['İstanbul', 'Çanakkale', '+1 day 15:00', '+1 day 18:30', 200.00, 25],
         ['Çanakkale', 'İstanbul', '+2 days 16:00', '+2 days 19:30', 200.00, 25],
         ['İstanbul', 'Edirne', '+3 days 17:00', '+3 days 20:00', 150.00, 28],
         ['Edirne', 'İstanbul', '+4 days 18:00', '+4 days 21:00', 150.00, 28]]
    ];

    $tripInsert = $pdo->prepare('INSERT INTO trips (id, company_id, departure_location, arrival_location, departure_time, arrival_time, price, seat_count) VALUES (:id,:company_id,:dep,:arr,:dt,:at,:price,:seat_count)');
    
    foreach ($companyIds as $index => $company) {
        if (isset($tripData[$index])) {
            foreach ($tripData[$index] as [$dep, $arr, $drel, $arel, $price, $seats]) {
                $dt = (new DateTimeImmutable($drel, new DateTimeZone('Europe/Istanbul')))->format('Y-m-d H:i:s');
                $at = (new DateTimeImmutable($arel, new DateTimeZone('Europe/Istanbul')))->format('Y-m-d H:i:s');
                $tripInsert->execute([
                    ':id' => Util::generateUuidV4(),
                    ':company_id' => $company['id'],
                    ':dep' => $dep,
                    ':arr' => $arr,
                    ':dt' => $dt,
                    ':at' => $at,
                    ':price' => $price,
                    ':seat_count' => $seats,
                ]);
            }
        }
    }

    // Seed coupons
    $coupons = [
        ['code' => 'WELCOME10', 'discount_rate' => 0.10, 'usage_limit' => 100, 'expiry_date' => '+30 days', 'company_id' => null], // Genel kupon
        ['code' => 'HIZLI20', 'discount_rate' => 0.20, 'usage_limit' => 50, 'expiry_date' => '+15 days', 'company_id' => $companyIds[0]['id']], // HızlıTur
        ['code' => 'GUVEN15', 'discount_rate' => 0.15, 'usage_limit' => 30, 'expiry_date' => '+20 days', 'company_id' => $companyIds[1]['id']], // Güvenli Yolculuk
        ['code' => 'MEGA25', 'discount_rate' => 0.25, 'usage_limit' => 25, 'expiry_date' => '+10 days', 'company_id' => $companyIds[2]['id']], // Mega Turizm
        ['code' => 'ANADOLU12', 'discount_rate' => 0.12, 'usage_limit' => 40, 'expiry_date' => '+25 days', 'company_id' => $companyIds[3]['id']], // Anadolu Express
        ['code' => 'SEHIR5', 'discount_rate' => 0.05, 'usage_limit' => 200, 'expiry_date' => '+45 days', 'company_id' => $companyIds[4]['id']], // Şehirler Arası
        ['code' => 'YENI30', 'discount_rate' => 0.30, 'usage_limit' => 10, 'expiry_date' => '+7 days', 'company_id' => null], // Genel yeni müşteri
        ['code' => 'TATIL50', 'discount_rate' => 0.50, 'usage_limit' => 5, 'expiry_date' => '+3 days', 'company_id' => null] // Özel tatil kuponu
    ];

    $couponInsert = $pdo->prepare('INSERT INTO coupons (id, code, discount_rate, usage_limit, expiry_date, company_id) VALUES (:id, :code, :discount_rate, :usage_limit, :expiry_date, :company_id)');
    
    foreach ($coupons as $coupon) {
        $expiryDate = (new DateTimeImmutable($coupon['expiry_date'], new DateTimeZone('Europe/Istanbul')))->format('Y-m-d H:i:s');
        $couponInsert->execute([
            ':id' => Util::generateUuidV4(),
            ':code' => $coupon['code'],
            ':discount_rate' => $coupon['discount_rate'],
            ':usage_limit' => $coupon['usage_limit'],
            ':expiry_date' => $expiryDate,
            ':company_id' => $coupon['company_id'],
        ]);
    }

    $pdo->commit();
    echo "🎉 Kapsamlı seed verileri başarıyla eklendi!\n";
    echo "📊 Eklenen veriler:\n";
    echo "   🏢 " . count($companies) . " firma\n";
    echo "   👥 " . (1 + count($companies) + count($users)) . " kullanıcı\n";
    echo "   🚌 " . array_sum(array_map('count', $tripData)) . " sefer\n";
    echo "   🎫 " . count($coupons) . " kupon\n";
    echo "\n🔐 Giriş Bilgileri:\n";
    echo "   admin@admin (şifre: admin) - Sistem Yöneticisi\n";
    foreach ($companyIds as $company) {
        echo "   {$company['admin_email']} (şifre: firma) - {$company['name']} Yetkilisi\n";
    }
    echo "   user@user (şifre: user) - Deneme Kullanıcı\n";
    echo "   ahmet@test, fatma@test, mehmet@test, ayse@test (şifre: user) - Test Kullanıcıları\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seed hatası: ' . $e->getMessage() . "\n");
    exit(1);
}


