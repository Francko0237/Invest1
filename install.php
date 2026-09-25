<?php
// ============================================================
// BijouxInvest — Script d'installation (à supprimer après usage)
// ============================================================
// Accès sécurisé par token
define('INSTALL_TOKEN', 'bijoux2024secure');
if (!isset($_GET['token']) || $_GET['token'] !== INSTALL_TOKEN) {
    http_response_code(403);
    die('Accès refusé. Ajoutez ?token=bijoux2024secure à l\'URL.');
}

// Config DB InfinityFree
$DB_HOST = 'sql312.infinityfree.com';
$DB_NAME = 'if0_41582591_invest_db';
$DB_USER = 'if0_41582591';
$DB_PASS = 'Xir8cvZrzKXWJ';

$errors = [];
$success = [];

echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Installation BijouxInvest</title>';
echo '<style>body{font-family:monospace;background:#111;color:#eee;padding:20px;}
.ok{color:#4ade80;} .err{color:#f87171;} .warn{color:#fbbf24;}
pre{background:#222;padding:10px;border-radius:6px;white-space:pre-wrap;word-break:break-all;}
</style></head><body>';
echo '<h2>🔧 Installation BijouxInvest</h2>';

try {
    $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
    $db = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo '<p class="ok">✅ Connexion à la base de données réussie.</p>';
} catch (PDOException $e) {
    echo '<p class="err">❌ Connexion échouée : ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</body></html>';
    exit;
}

// ── Tables à créer ───────────────────────────────────────────

$tables = [

'users' => "CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(100) NOT NULL,
  `telephone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `solde` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `code_parrain` VARCHAR(20) NOT NULL,
  `parrain_id` INT DEFAULT NULL,
  `role` VARCHAR(10) NOT NULL DEFAULT 'user',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_telephone` (`telephone`),
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_code_parrain` (`code_parrain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'plans' => "CREATE TABLE IF NOT EXISTS `plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(100) NOT NULL,
  `prix` DECIMAL(15,2) NOT NULL,
  `pourcentage` DECIMAL(5,2) NOT NULL,
  `duree_valeur` INT NOT NULL,
  `duree_type` VARCHAR(10) NOT NULL,
  `statut` TINYINT(1) NOT NULL DEFAULT 1,
  `niveau` VARCHAR(50) DEFAULT 'BRONZE',
  `description` TEXT DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'investments' => "CREATE TABLE IF NOT EXISTS `investments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `plan_id` INT NOT NULL,
  `montant` DECIMAL(15,2) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'withdrawals' => "CREATE TABLE IF NOT EXISTS `withdrawals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `montant` DECIMAL(15,2) NOT NULL,
  `telephone_retrait` VARCHAR(20) DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'referral_bonus' => "CREATE TABLE IF NOT EXISTS `referral_bonus` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `from_user_id` INT NOT NULL,
  `level` INT NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'deposits' => "CREATE TABLE IF NOT EXISTS `deposits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `montant` DECIMAL(15,2) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `sender_phone` VARCHAR(20) NOT NULL,
  `transaction_ref` VARCHAR(100) DEFAULT NULL,
  `screenshot` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `admin_note` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'settings' => "CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `key_name` VARCHAR(50) NOT NULL UNIQUE,
  `key_value` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

];

// Créer d'abord users (sans FK pour éviter les soucis d'ordre)
foreach ($tables as $name => $sql) {
    try {
        $db->exec($sql);
        echo '<p class="ok">✅ Table <strong>' . $name . '</strong> créée/vérifiée.</p>';
    } catch (PDOException $e) {
        echo '<p class="err">❌ Table <strong>' . $name . '</strong> : ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

// Ajouter FK investments après création des tables
$fks = [
    "ALTER TABLE `investments` ADD CONSTRAINT `fk_inv_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE",
    "ALTER TABLE `investments` ADD CONSTRAINT `fk_inv_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE CASCADE",
    "ALTER TABLE `withdrawals` ADD CONSTRAINT `fk_wd_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE",
    "ALTER TABLE `deposits` ADD CONSTRAINT `fk_dep_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE",
    "ALTER TABLE `referral_bonus` ADD CONSTRAINT `fk_rb_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE",
    "ALTER TABLE `referral_bonus` ADD CONSTRAINT `fk_rb_from` FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE",
    "ALTER TABLE `users` ADD CONSTRAINT `fk_users_parrain` FOREIGN KEY (`parrain_id`) REFERENCES `users`(`id`) ON DELETE SET NULL",
];
foreach ($fks as $fk) {
    try { $db->exec($fk); } catch (PDOException $e) { /* Ignore — FK existe déjà */ }
}

// ── Seed settings ────────────────────────────────────────────
$settings = [
    ['ref_level_1_percent', '10.00'],
    ['ref_level_2_percent', '5.00'],
    ['ref_level_3_percent', '2.00'],
    ['site_theme', 'dark'],
    ['depot_minimum', '500.00'],
    ['retrait_minimum', '500.00'],
    ['momo_number', '670000000'],
    ['momo_name', 'BijouxInvest MTN'],
    ['om_number', '690000000'],
    ['om_name', 'BijouxInvest Orange'],
];
foreach ($settings as [$k, $v]) {
    try {
        $db->prepare("INSERT INTO `settings` (key_name, key_value) VALUES (?,?) ON DUPLICATE KEY UPDATE key_value=VALUES(key_value)")
           ->execute([$k, $v]);
    } catch (PDOException $e) {}
}
echo '<p class="ok">✅ Paramètres par défaut insérés.</p>';

// ── Seed plans ────────────────────────────────────────────────
$plans = [
    [1, 'Diamant Bronze',  100.00,  15.00, 7, 'jours', 1, 'BRONZE',  'Plan débutant — 15% de gain en 7 jours',        'assets/images/bijou_bronze.png'],
    [2, 'Diamant Argent',  500.00,  25.00,14, 'jours', 1, 'ARGENT',  'Plan intermédiaire — 25% de gain en 14 jours',   'assets/images/bijou_argent.png'],
    [3, 'Diamant Or',     1000.00,  40.00, 1, 'mois',  1, 'OR',      'Plan avancé — 40% de gain en 1 mois',           'assets/images/bijou_or.png'],
    [4, 'Diamant Platine',5000.00,  80.00, 2, 'mois',  1, 'PLATINE', 'Plan premium — 80% de gain en 2 mois',          'assets/images/bijou_platine.png'],
    [5, 'Diamant Royal', 10000.00, 150.00, 3, 'mois',  1, 'DIAMANT', 'Plan élite — 150% de gain en 3 mois',           'assets/images/bijou_royal.png'],
];
foreach ($plans as $p) {
    try {
        $db->prepare("INSERT INTO `plans` (id,nom,prix,pourcentage,duree_valeur,duree_type,statut,niveau,description,image)
                      VALUES (?,?,?,?,?,?,?,?,?,?)
                      ON DUPLICATE KEY UPDATE nom=VALUES(nom),prix=VALUES(prix),pourcentage=VALUES(pourcentage),
                      duree_valeur=VALUES(duree_valeur),duree_type=VALUES(duree_type),statut=VALUES(statut),
                      niveau=VALUES(niveau),description=VALUES(description),image=VALUES(image)")
           ->execute($p);
    } catch (PDOException $e) {
        echo '<p class="err">❌ Plan ' . htmlspecialchars($p[1]) . ' : ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}
echo '<p class="ok">✅ Plans d\'investissement insérés.</p>';

// ── Admin user — hash généré en PHP (pas bash) ───────────────
$admin_pass = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 10]);
try {
    $stmt = $db->prepare(
        "INSERT INTO `users` (id, nom, telephone, email, password, solde, code_parrain, role)
         VALUES (1, 'Administrateur', '+237666666666', 'admin@invest.com', ?, 0.00, 'ADMIN001', 'admin')
         ON DUPLICATE KEY UPDATE
           telephone = VALUES(telephone),
           password  = VALUES(password),
           role      = VALUES(role)"
    );
    $stmt->execute([$admin_pass]);
    echo '<p class="ok">✅ Compte admin créé/mis à jour.</p>';
    echo '<p class="warn">📋 Hash généré : <code>' . htmlspecialchars($admin_pass) . '</code></p>';
} catch (PDOException $e) {
    echo '<p class="err">❌ Admin : ' . htmlspecialchars($e->getMessage()) . '</p>';
}

// ── Vérification finale ──────────────────────────────────────
echo '<hr><h3>Vérification finale</h3>';
$tables_check = ['users','plans','investments','withdrawals','referral_bonus','settings'];
foreach ($tables_check as $t) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo '<p class="ok">✅ ' . $t . ' : ' . $count . ' enregistrement(s)</p>';
    } catch (PDOException $e) {
        echo '<p class="err">❌ ' . $t . ' MANQUANTE : ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

// Vérifier le mot de passe admin
try {
    $row = $db->query("SELECT password FROM users WHERE id = 1")->fetch();
    if ($row && password_verify('admin123', $row['password'])) {
        echo '<p class="ok">✅ Mot de passe admin123 vérifié avec succès !</p>';
    } else {
        echo '<p class="err">❌ Vérification mot de passe admin échouée.</p>';
    }
} catch (PDOException $e) {
    echo '<p class="err">❌ ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<hr>';
echo '<h3 class="ok">✅ Installation terminée !</h3>';
echo '<p>Vous pouvez maintenant vous connecter :</p>';
echo '<ul>';
echo '<li>📞 Téléphone : <strong>666666666</strong></li>';
echo '<li>📧 Email : <strong>admin@invest.com</strong></li>';
echo '<li>🔑 Mot de passe : <strong>admin123</strong></li>';
echo '</ul>';
echo '<p><a href="login.php" style="color:#f59e0b;">→ Aller à la page de connexion</a></p>';
echo '<p class="warn">⚠️ <strong>IMPORTANT : Supprimez ce fichier install.php après usage !</strong></p>';
echo '</body></html>';
