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
        $level1      = (float)$_POST['level1'];
        $level2      = (float)$_POST['level2'];
        $level3      = (float)$_POST['level3'];
        $theme       = clean_input($_POST['theme']);
        $depot_min   = (float)$_POST['depot_min'];
        $retrait_min = (float)$_POST['retrait_min'];

        $momo_num  = clean_input($_POST['momo_num']);
        $momo_nom  = clean_input($_POST['momo_nom']);
        $om_num    = clean_input($_POST['om_num']);
        $om_nom    = clean_input($_POST['om_nom']);

        if ($level1 < 0 || $level2 < 0 || $level3 < 0 || $depot_min < 0 || $retrait_min < 0) {
            set_flash_message('danger', 'Les valeurs numériques doivent être positives ou nulles.');
        } elseif ($theme !== 'dark' && $theme !== 'light') {
            set_flash_message('danger', 'Thème sélectionné invalide.');
        } else {
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("INSERT INTO settings (key_name, key_value) VALUES (:key, :val) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
                
                $stmt->execute(['val' => sprintf("%.2f", $level1), 'key' => 'ref_level_1_percent']);
                $stmt->execute(['val' => sprintf("%.2f", $level2), 'key' => 'ref_level_2_percent']);
                $stmt->execute(['val' => sprintf("%.2f", $level3), 'key' => 'ref_level_3_percent']);
                $stmt->execute(['val' => $theme, 'key' => 'site_theme']);
                $stmt->execute(['val' => sprintf("%.2f", $depot_min), 'key' => 'depot_minimum']);
                $stmt->execute(['val' => sprintf("%.2f", $retrait_min), 'key' => 'retrait_minimum']);

                $stmt->execute(['val' => $momo_num, 'key' => 'momo_number']);
                $stmt->execute(['val' => $momo_nom, 'key' => 'momo_name']);
                $stmt->execute(['val' => $om_num, 'key' => 'om_number']);
                $stmt->execute(['val' => $om_nom, 'key' => 'om_name']);
                
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

// Count pending items for badges
$stmtPendingDep = $db->query("SELECT COUNT(*) FROM deposits WHERE status = 'pending'");
$pending_deposits_count = (int)$stmtPendingDep->fetchColumn();

$stmtPendingWd = $db->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'");
$pending_withdrawals_count = (int)$stmtPendingWd->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Configuration</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
<body class="<?php echo get_theme_class(); ?>">
<?php render_theme_script(); ?>

<nav class="navbar admin-navbar">
    <a href="dashboard.php" class="nav-brand">BijouxInvest - Admin</a>
    <div class="nav-links">
        <a href="dashboard.php" class="nav-link">Dashboard</a>
        <a href="deposits.php" class="nav-link">Dépôts <?php if ($pending_deposits_count > 0): ?><span class="badge badge-pending" style="padding: 0.1rem 0.4rem; font-size: 0.7rem;"><?php echo $pending_deposits_count; ?></span><?php endif; ?></a>
        <a href="plans.php" class="nav-link">Gestion Plans</a>
        <a href="users.php" class="nav-link">Gestion Membres</a>
        <a href="withdrawals.php" class="nav-link">Retraits <?php if ($pending_withdrawals_count > 0): ?><span class="badge badge-pending" style="padding: 0.1rem 0.4rem; font-size: 0.7rem;"><?php echo $pending_withdrawals_count; ?></span><?php endif; ?></a>
        <a href="settings.php" class="nav-link active">Configuration</a>
        <a href="../dashboard.php" class="nav-btn-outline">Espace Client</a>
        <a href="../logout.php" class="nav-btn-outline" style="border-color: var(--danger); color: var(--danger);">Déconnexion</a>
    </div>
</nav>

<div class="container">
    
    <?php display_flash_messages(); ?>

    <div class="auth-wrapper" style="min-height: auto; padding: 2rem 0;">
        <div class="auth-card" style="max-width: 650px; margin: 0 auto; padding: 2rem;">
            <div class="auth-header">
                <h1>Configuration Globale</h1>
                <p>Gérez les numéros Mobile Money, les seuils financiers et le parrainage</p>
            </div>

            <form action="settings.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

                <!-- SECTION 1: MOBILE MONEY ACCOUNTS FOR DEPOSITS -->
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 1rem; color: var(--primary);">📲 Comptes Mobile Money de Réception</h3>
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                        Ces numéros s'afficheront sur la page de dépôt des membres.
                    </p>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <!-- MTN MoMo -->
                        <div style="background: rgba(0,0,0,0.15); padding: 1rem; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color);">
                            <h4 style="color: #ffcc00; margin-bottom: 0.6rem;">🟡 MTN Mobile Money</h4>
                            <div class="form-group">
                                <label class="form-label" style="font-size:0.8rem;">Numéro MTN</label>
                                <input type="text" name="momo_num" class="form-control" value="<?php echo e($settings['momo_number'] ?? '670000000'); ?>" required>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" style="font-size:0.8rem;">Nom du Compte</label>
                                <input type="text" name="momo_nom" class="form-control" value="<?php echo e($settings['momo_name'] ?? 'BijouxInvest MTN'); ?>" required>
                            </div>
                        </div>

                        <!-- Orange Money -->
                        <div style="background: rgba(0,0,0,0.15); padding: 1rem; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color);">
                            <h4 style="color: #ff6600; margin-bottom: 0.6rem;">🟠 Orange Money</h4>
                            <div class="form-group">
                                <label class="form-label" style="font-size:0.8rem;">Numéro Orange</label>
                                <input type="text" name="om_num" class="form-control" value="<?php echo e($settings['om_number'] ?? '690000000'); ?>" required>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" style="font-size:0.8rem;">Nom du Compte</label>
                                <input type="text" name="om_nom" class="form-control" value="<?php echo e($settings['om_name'] ?? 'BijouxInvest Orange'); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: VISUAL THEME -->
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

                <!-- SECTION 3: TRANSACTION LIMITS -->
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

                <!-- SECTION 4: REFERRAL RATES -->
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
