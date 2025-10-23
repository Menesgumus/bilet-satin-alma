<?php
declare(strict_types=1);

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Auth.php';

session_name((require __DIR__ . '/../config/config.php')['session_name']);
session_start();

Auth::requireRole('user');
$pdo = Database::getConnection();

$ticketId = $_GET['id'] ?? '';
if ($ticketId === '') { http_response_code(400); echo 'Geçersiz istek'; exit; }

$stmt = $pdo->prepare("SELECT tk.*, us.fullname, us.email, tr.departure_location, tr.arrival_location, tr.departure_time, tr.arrival_time, tr.price, co.name AS company_name
FROM tickets tk
JOIN users us ON us.id = tk.user_id
JOIN trips tr ON tr.id = tk.trip_id
JOIN companies co ON co.id = tr.company_id
WHERE tk.id = :id AND tk.user_id = :uid LIMIT 1");
$stmt->execute([':id' => $ticketId, ':uid' => $_SESSION['user_id']]);
$t = $stmt->fetch();
if (!$t) { http_response_code(404); echo 'Bilet bulunamadı'; exit; }

// Build HTML
$dep = (new DateTime($t['departure_time']))->format('d.m.Y H:i');
$arr = (new DateTime($t['arrival_time']))->format('d.m.Y H:i');

// Use paid_amount if available (discounted price), otherwise use original price
$actualPrice = $t['paid_amount'] ?? (float)$t['price'];
$price = number_format($actualPrice, 2) . ' ₺';

// Status label in Turkish
$statusLabel = $t['status'] === 'ACTIVE' ? 'Geçerli' : ($t['status'] === 'CANCELLED' ? 'İptal Edildi' : $t['status']);

$html = '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><style>
@page { margin: 0; size: A4; }
body { 
    font-family: "Segoe UI", "DejaVu Sans", Arial, sans-serif; 
    margin: 0; 
    padding: 20px; 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}
.ticket-container {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    overflow: hidden;
}
.ticket-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 30px;
    text-align: center;
    position: relative;
}
.ticket-header::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23ffffff" fill-opacity="0.1"%3E%3Ccircle cx="30" cy="30" r="2"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
}
.ticket-title {
    font-size: 32px;
    font-weight: bold;
    margin: 0 0 10px 0;
    position: relative;
    z-index: 1;
}
.ticket-subtitle {
    font-size: 16px;
    opacity: 0.9;
    position: relative;
    z-index: 1;
}
.ticket-body {
    padding: 40px;
}
.route-section {
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    text-align: center;
    border: 2px solid #e2e8f0;
}
.route-title {
    font-size: 24px;
    font-weight: bold;
    color: #1e293b;
    margin-bottom: 20px;
}
.route-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.departure, .arrival {
    text-align: center;
    flex: 1;
}
.city-name {
    font-size: 20px;
    font-weight: bold;
    color: #1e293b;
    margin-bottom: 5px;
}
.city-time {
    font-size: 16px;
    color: #64748b;
}
.arrow {
    font-size: 24px;
    color: #667eea;
    margin: 0 20px;
}
.company-info {
    background: #f1f5f9;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid #667eea;
}
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
.info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.info-label {
    font-size: 12px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}
.info-value {
    font-size: 16px;
    color: #1e293b;
    font-weight: 500;
}
.seat-price-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 20px;
}
.seat-info {
    text-align: center;
}
.seat-number {
    font-size: 36px;
    font-weight: bold;
    margin-bottom: 5px;
}
.seat-label {
    font-size: 14px;
    opacity: 0.9;
}
.price-info {
    text-align: center;
}
.price-amount {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
}
.price-label {
    font-size: 14px;
    opacity: 0.9;
}
.status-section {
    text-align: center;
    margin-bottom: 20px;
}
.status-badge {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: bold;
    font-size: 14px;
}
.status-active {
    background: #dcfce7;
    color: #166534;
    border: 2px solid #22c55e;
}
.status-cancelled {
    background: #fef2f2;
    color: #991b1b;
    border: 2px solid #ef4444;
}
.ticket-footer {
    background: #f8fafc;
    padding: 20px;
    text-align: center;
    border-top: 1px solid #e2e8f0;
}
.ticket-id {
    font-family: "Courier New", monospace;
    font-size: 12px;
    color: #64748b;
    word-break: break-all;
    background: white;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.qr-placeholder {
    width: 80px;
    height: 80px;
    background: #f1f5f9;
    border: 2px dashed #cbd5e1;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px auto;
    color: #64748b;
    font-size: 12px;
    text-align: center;
}
</style></head><body>';

$html .= '<div class="ticket-container">';
$html .= '<div class="ticket-header">';
$html .= '<div class="ticket-title">🚌 BBILET</div>';
$html .= '<div class="ticket-subtitle">Yolcu Bileti</div>';
$html .= '</div>';

$html .= '<div class="ticket-body">';
$html .= '<div class="route-section">';
$html .= '<div class="route-title">📍 Güzergah</div>';
$html .= '<div class="route-info">';
$html .= '<div class="departure">';
$html .= '<div class="city-name">' . htmlspecialchars($t['departure_location']) . '</div>';
$html .= '<div class="city-time">' . htmlspecialchars($dep) . '</div>';
$html .= '</div>';
$html .= '<div class="arrow">→</div>';
$html .= '<div class="arrival">';
$html .= '<div class="city-name">' . htmlspecialchars($t['arrival_location']) . '</div>';
$html .= '<div class="city-time">' . htmlspecialchars($arr) . '</div>';
$html .= '</div>';
$html .= '</div>';
$html .= '</div>';

$html .= '<div class="company-info">';
$html .= '<div class="info-label">Firma</div>';
$html .= '<div class="info-value">' . htmlspecialchars($t['company_name']) . '</div>';
$html .= '</div>';

$html .= '<div class="info-grid">';
$html .= '<div class="info-item">';
$html .= '<div class="info-label">Yolcu Adı</div>';
$html .= '<div class="info-value">' . htmlspecialchars($t['fullname']) . '</div>';
$html .= '</div>';
$html .= '<div class="info-item">';
$html .= '<div class="info-label">E-posta</div>';
$html .= '<div class="info-value">' . htmlspecialchars($t['email']) . '</div>';
$html .= '</div>';
$html .= '</div>';

$html .= '<div class="seat-price-section">';
$html .= '<div class="seat-info">';
$html .= '<div class="seat-number">' . htmlspecialchars((string)$t['seat_number']) . '</div>';
$html .= '<div class="seat-label">Koltuk No</div>';
$html .= '</div>';
$html .= '<div class="price-info">';
$html .= '<div class="price-amount">' . htmlspecialchars($price) . '</div>';
$html .= '<div class="price-label">Toplam Fiyat</div>';
$html .= '</div>';
$html .= '</div>';

$html .= '<div class="status-section">';
$html .= '<div class="status-badge status-' . strtolower($t['status']) . '">';
$html .= ($t['status'] === 'ACTIVE' ? '✅ ' : '❌ ') . htmlspecialchars($statusLabel);
$html .= '</div>';
$html .= '</div>';

$html .= '<div class="qr-placeholder">QR Kod<br/>Alanı</div>';

$html .= '</div>';

$html .= '<div class="ticket-footer">';
$html .= '<div class="ticket-id">Bilet No: ' . htmlspecialchars($t['id']) . '</div>';
$html .= '</div>';

$html .= '</div></body></html>';

// Try Dompdf if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

if (class_exists('Dompdf\\Dompdf')) {
    $dompdf = new Dompdf\Dompdf([ 'isRemoteEnabled' => false ]);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('bbilet-' . $t['id'] . '.pdf', ['Attachment' => true]);
    exit;
}

// Fallback: Create a simple PDF using basic PHP
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="bbilet-' . $t['id'] . '.pdf"');

// Function to convert Turkish characters to PDF-safe format
function convertToPdfText($text) {
    $turkish = ['ç', 'Ç', 'ğ', 'Ğ', 'ı', 'İ', 'ö', 'Ö', 'ş', 'Ş', 'ü', 'Ü'];
    $english = ['c', 'C', 'g', 'G', 'i', 'I', 'o', 'O', 's', 'S', 'u', 'U'];
    return str_replace($turkish, $english, $text);
}

// Convert all text to PDF-safe format
$companyName = convertToPdfText($t['company_name']);
$fullname = convertToPdfText($t['fullname']);
$email = convertToPdfText($t['email']);
$departureLocation = convertToPdfText($t['departure_location']);
$arrivalLocation = convertToPdfText($t['arrival_location']);
$statusLabel = convertToPdfText($statusLabel);

// Simple PDF generation
$pdf = "%PDF-1.4\n";
$pdf .= "1 0 obj\n";
$pdf .= "<<\n";
$pdf .= "/Type /Catalog\n";
$pdf .= "/Pages 2 0 R\n";
$pdf .= ">>\n";
$pdf .= "endobj\n";

$pdf .= "2 0 obj\n";
$pdf .= "<<\n";
$pdf .= "/Type /Pages\n";
$pdf .= "/Kids [3 0 R]\n";
$pdf .= "/Count 1\n";
$pdf .= ">>\n";
$pdf .= "endobj\n";

$pdf .= "3 0 obj\n";
$pdf .= "<<\n";
$pdf .= "/Type /Page\n";
$pdf .= "/Parent 2 0 R\n";
$pdf .= "/MediaBox [0 0 612 792]\n";
$pdf .= "/Contents 4 0 R\n";
$pdf .= ">>\n";
$pdf .= "endobj\n";

// Content stream with proper text positioning
$content = "BT\n";
$content .= "/F1 14 Tf\n";
$content .= "50 750 Td\n";
$content .= "(BBILET - YOLCU BILETI) Tj\n";
$content .= "0 -25 Td\n";
$content .= "/F1 12 Tf\n";
$content .= "(Firma: " . addslashes($companyName) . ") Tj\n";
$content .= "0 -20 Td\n";
$content .= "(Yolcu: " . addslashes($fullname) . " - " . addslashes($email) . ") Tj\n";
$content .= "0 -20 Td\n";
$content .= "(Guzergah: " . addslashes($departureLocation) . " -> " . addslashes($arrivalLocation) . ") Tj\n";
$content .= "0 -20 Td\n";
$content .= "(Kalkis: " . addslashes($dep) . ") Tj\n";
$content .= "0 -20 Td\n";
$content .= "(Varis: " . addslashes($arr) . ") Tj\n";
$content .= "0 -20 Td\n";
$content .= "(Koltuk: " . addslashes((string)$t['seat_number']) . ") Tj\n";
$content .= "0 -20 Td\n";
$content .= "(Fiyat: " . addslashes($price) . ") Tj\n";
$content .= "0 -20 Td\n";
$content .= "(Durum: " . addslashes($statusLabel) . ") Tj\n";
$content .= "0 -20 Td\n";
$content .= "(Bilet No: " . addslashes($t['id']) . ") Tj\n";
$content .= "ET\n";

$pdf .= "4 0 obj\n";
$pdf .= "<<\n";
$pdf .= "/Length " . strlen($content) . "\n";
$pdf .= ">>\n";
$pdf .= "stream\n";
$pdf .= $content . "\n";
$pdf .= "endstream\n";
$pdf .= "endobj\n";

$pdf .= "xref\n";
$pdf .= "0 5\n";
$pdf .= "0000000000 65535 f \n";
$pdf .= "0000000009 00000 n \n";
$pdf .= "0000000058 00000 n \n";
$pdf .= "0000000115 00000 n \n";
$pdf .= "0000000204 00000 n \n";
$pdf .= "trailer\n";
$pdf .= "<<\n";
$pdf .= "/Size 5\n";
$pdf .= "/Root 1 0 R\n";
$pdf .= ">>\n";
$pdf .= "startxref\n";
$pdf .= "400\n";
$pdf .= "%%EOF\n";

echo $pdf;
exit;


