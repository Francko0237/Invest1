<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

echo "<h2>Diagnostic Connexion Base de Données InfinityFree</h2>";
echo "<p><strong>DB_HOST:</strong> " . DB_HOST . "</p>";
echo "<p><strong>DB_NAME:</strong> " . DB_NAME . "</p>";
echo "<p><strong>DB_USER:</strong> " . DB_USER . "</p>";

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $db = new PDO($dsn, DB_USER, DB_PASS, $options);
    echo "<p style='color:green; font-weight:bold;'>✓ Connexion PDO réussie !</p>";

    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Tables trouvées (" . count($tables) . ") :</h3>";
    if (empty($tables)) {
        echo "<p style='color:red; font-weight:bold;'>⚠️ LA BASE DE DONNÉES EST VIDE (0 table) ! Vous devez importer schema.sql dans phpMyAdmin.</p>";
    } else {
        echo "<ul>";
        foreach ($tables as $t) {
            echo "<li>" . htmlspecialchars($t) . "</li>";
        }
        echo "</ul>";

        if (in_array('users', $tables)) {
            $users = $db->query("SELECT id, nom, email, telephone, role FROM users")->fetchAll();
            echo "<h3>Utilisateurs (" . count($users) . ") :</h3><pre>";
            print_r($users);
            echo "</pre>";
        } else {
            echo "<p style='color:red; font-weight:bold;'>⚠️ La table 'users' est manquante ! Veuillez importer schema.sql.</p>";
        }
    }

} catch (PDOException $e) {
    echo "<p style='color:red; font-weight:bold;'>❌ ERREUR PDO: " . htmlspecialchars($e->getMessage()) . "</p>";
}
