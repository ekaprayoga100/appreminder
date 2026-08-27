<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';

requireAdmin();

$fileName = 'kontak_export_' . date('Y-m-d_H-i-s') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="utf-8"><meta name="ProgId" content="Excel.Sheet"><meta name="ExcelWindowFrame" content="TRUE"><style>';
echo 'td { mso-number-format:"\@"; }';
echo 'table { border-collapse: collapse; }';
echo 'th { background-color: #4f46e5; color: #ffffff; font-weight: bold; }';
echo 'td, th { border: 1px solid #e2e8f0; padding: 6px 10px; font-size: 12px; font-family: Calibri, Arial, sans-serif; }';
echo 'tr:nth-child(even) { background-color: #f8fafc; }';
echo '</style></head><body>';
echo '<table>';
echo '<thead><tr><th>No</th><th>Nama</th><th>Email</th><th>WhatsApp</th><th>Dibuat</th></tr></thead>';
echo '<tbody>';

$stmt = $pdo->query('SELECT full_name, email, whatsapp, created_at FROM email_data ORDER BY full_name ASC');
$no = 1;
while ($row = $stmt->fetch()) {
    $whatsapp = $row['whatsapp'] ? preg_replace('/[^0-9+]/', '', $row['whatsapp']) : '';
    $createdAt = date('d M Y H:i', strtotime($row['created_at']));
    echo '<tr>';
    echo '<td>' . $no . '</td>';
    echo '<td>' . htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($whatsapp, ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') . '</td>';
    echo '</tr>';
    $no++;
}

echo '</tbody>';
echo '</table>';
echo '</body></html>';
exit;
