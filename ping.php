<?php
// Mini diagnostic — à supprimer après usage
error_reporting(E_ALL);
ini_set('display_errors', 1);

$DB_HOST = 'sql312.infinityfree.com';
$DB_NAME = 'if0_41582591_invest_db';
$DB_USER = 'if0_41582591';
$DB_PASS = 'Xir8cvZrzKXWJ';

echo "PHP " . PHP_VERSION . "\n";

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true]
    );
    echo "DB: OK\n";

    // Check tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n";

    // Check admin user
    if (in_array('users', $tables)) {
        $r = $pdo->query("SELECT id, nom, telephone, email, role, LEFT(password,20) as hash FROM users WHERE id=1")->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            echo "Admin: id={$r['id']} nom={$r['nom']} tel={$r['telephone']} email={$r['email']} role={$r['role']} hash={$r['hash']}...\n";
            $full = $pdo->query("SELECT password FROM users WHERE id=1")->fetchColumn();
            echo "pass_verify(admin123): " . (password_verify('admin123', $full) ? 'YES' : 'NO') . "\n";
        } else {
            echo "Admin: NOT FOUND\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
