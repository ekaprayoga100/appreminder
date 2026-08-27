<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';

requireAdmin();

$backupDir = __DIR__ . '/storage';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}

$fileName = 'backup_appreminder_' . date('Y-m-d_H-i-s') . '.sql';
$filePath = $backupDir . '/' . $fileName;

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

$sql = '';
foreach ($tables as $table) {
    $row = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_NUM);
    if ($row) {
        $sql .= 'DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`;' . "\n";
        $sql .= $row[1] . ';' . "\n\n";
    }

    $stmt = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols = array_map(function($c) {
            return '`' . str_replace('`', '``', $c) . '`';
        }, array_keys($row));
        $vals = array_map(function($v) use ($pdo) {
            if ($v === null) {
                return 'NULL';
            }
            return "'" . str_replace("'", "''", $v) . "'";
        }, array_values($row));
        $sql .= 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ');' . "\n";
    }
    $sql .= "\n";
}

if (file_put_contents($filePath, $sql) === false) {
    exit('Gagal menulis file backup.');
}

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($filePath);
exit;
