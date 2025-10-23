<?php
declare(strict_types=1);

require __DIR__ . '/../../src/Database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::getConnection();
    $departureCity = $_GET['from'] ?? '';

    if ($departureCity === '') {
        echo json_encode(['cities' => []]);
        exit;
    }

    // Get arrival cities for the selected departure city (case-insensitive)
    $stmt = $pdo->prepare('SELECT DISTINCT arrival_location as city FROM trips WHERE LOWER(departure_location) = LOWER(:from) ORDER BY arrival_location ASC');
    $stmt->execute([':from' => $departureCity]);
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['cities' => $cities], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
