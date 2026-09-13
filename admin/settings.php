<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';

require_login();
require_admin();

// Fetch settings
$settings = get_all_settings();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Jeton de sécurité invalide.');
    } else {
        $level1 = (float)$_POST['level1'];
        $level2 = (float)$_POST['level2'];
        $level3 = (float)$_POST['level3'];
        $theme = clean_input($_POST['theme']);
        $depot_min = (float)$_POST['depot_min'];
        $retrait_min = (float)$_POST['retrait_min'];

        if ($level1 < 0 || $level2 < 0 || $level3 < 0 || $depot_min < 0 || $retrait_min < 0) {
            set_flash_message('danger', 'Les valeurs numériques doivent être positives ou nulles.');
        } elseif ($theme !== 'dark' && $theme !== 'light') {
            set_flash_message('danger', 'Thème sélectionné invalide.');
        } else {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("UPDATE settings SET key_value = :val WHERE key_name = :key");
                
                $stmt->execute(['val' => sprintf("%.2f", $level1), 'key' => 'ref_level_1_percent']);
                $stmt->execute(['val' => sprintf("%.2f", $level2), 'key' => 'ref_level_2_percent']);
                $stmt->execute(['val' => sprintf("%.2f", $level3), 'key' => 'ref_level_3_percent']);
                $stmt->execute(['val' => $theme, 'key' => 'site_theme']);
                $stmt->execute(['val' => sprintf("%.2f", $depot_min), 'key' => 'depot_minimum']);
                $stmt->execute(['val' => sprintf("%.2f", $retrait_min), 'key' => 'retrait_minimum']);
                
                $db->commit();
                set_flash_message('success', 'La configuration de la plateforme a été mise à jour avec succès.');
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Failed to update settings: " . $e->getMessage());
                set_flash_message('danger', 'Erreur lors de la mise à jour des paramètres.');
            }
        }
    }
    header('Location: settings.php');
    exit();
}

// Fetch fresh settings for display
$settings = get_all_settings();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Configuration</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="<?php echo get_theme_class(); ?>">
<?php render_theme_script(); ?>

<nav class="navbar admin-navbar">
    <a href="dashboard.php" class="nav-brand">BijouxInvest - Admin</a>
    <div class="nav-links">
        <a href="dashboard.php" class="nav-link">Dashboard</a>
        <a href="plans.php" class="nav-link">Gestion Plans</a>
        <a href="users.php" class="nav-link">Gestion Membres</a>
        <a href="withdrawals.php" class="nav-link">Retraits</a>
        <a href="settings.php" class="nav-link active">Configuration</a>
        <a href="../dashboard.php" class="nav-btn-outline">Espace Client</a>
        <a href="../logout.php" class="nav-btn-outline" style="border-color: var(--danger); color: var(--danger);">Déconnexion</a>
    </div>
</nav>

<div class="container">
    
    <?php display_flash_messages(); ?>

    <div class="auth-wrapper" style="min-height: auto; padding: 2rem 0;">
        <div class="auth-card" style="max-width: 600px; margin: 0 auto; padding: 2rem;">
            <div class="auth-header">
                <h1>Configuration Globale</h1>
                <p>Gérez le thème visuel, les seuils financiers et le parrainage</p>
            </div>

            <form action="settings.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

                <!-- SECTION 1: VISUAL THEME -->
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 1rem; color: var(--primary);">Thème de la Plateforme</h3>
                    <div class="form-group">
                        <label for="theme" class="form-label">Thème Actif</label>
                        <select id="theme" name="theme" class="form-control" style="background: var(--bg-dark); color: var(--text-primary);" required>
                            <option value="dark" <?php if (($settings['site_theme'] ?? 'dark') === 'dark') echo 'selected'; ?>>Mode Sombre (Par défaut)</option>
                            <option value="light" <?php if (($settings['site_theme'] ?? 'dark') === 'light') echo 'selected'; ?>>Mode Clair</option>
                        </select>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Définit l'apparence par défaut pour tous les utilisateurs du site.</span>
                    </div>
                </div>

                <!-- SECTION 2: TRANSACTION LIMITS -->
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 1rem; color: var(--primary);">Seuils Financiers</h3>
                    
                    <div class="form-group">
                        <label for="depot_min" class="form-label">Dépôt Minimum (FCFA)</label>
                        <input type="number" step="1" min="0" id="depot_min" name="depot_min" class="form-control" value="<?php echo e($settings['depot_minimum'] ?? 500); ?>" required>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Montant minimal requis pour chaque demande de dépôt.</span>
                    </div>

                    <div class="form-group">
                        <label for="retrait_min" class="form-label">Retrait Minimum (FCFA)</label>
                        <input type="number" step="1" min="0" id="retrait_min" name="retrait_min" class="form-control" value="<?php echo e($settings['retrait_minimum'] ?? 500); ?>" required>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Montant minimal requis pour chaque demande de retrait.</span>
                    </div>
                </div>

                <!-- SECTION 3: REFERRAL RATES -->
                <div style="padding-bottom: 1rem; margin-bottom: 1rem;">
                    <h3 style="margin-bottom: 1rem; color: var(--primary);">👥 Taux de Parrainage</h3>

                    <div class="form-group">
                        <label for="level1" class="form-label">Niveau 1 - Filleuls directs (%)</label>
                        <input type="number" step="0.01" min="0" id="level1" name="level1" class="form-control" value="<?php echo e($settings['ref_level_1_percent'] ?? 10.00); ?>" required>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Pourcentage crédité au parrain direct lors d'un investissement.</span>
                    </div>

                    <div class="form-group">
                        <label for="level2" class="form-label">Niveau 2 - Filleuls de niveau 2 (%)</label>
                        <input type="number" step="0.01" min="0" id="level2" name="level2" class="form-control" value="<?php echo e($settings['ref_level_2_percent'] ?? 5.00); ?>" required>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Pourcentage crédité au parrain de niveau 2.</span>
                    </div>

                    <div class="form-group">
                        <label for="level3" class="form-label">Niveau 3 - Filleuls de niveau 3 (%)</label>
                        <input type="number" step="0.01" min="0" id="level3" name="level3" class="form-control" value="<?php echo e($settings['ref_level_3_percent'] ?? 2.00); ?>" required>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Pourcentage crédité au parrain de niveau 3.</span>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Sauvegarder les Paramètres</button>
            </form>
        </div>
    </div>

</div>

<footer class="site-footer">
    <p>&copy; 2026 BijouxInvest Admin. Tous droits réservés.</p>
</footer>

<script src="../assets/js/toast.js"></script>
</body>
</html>
