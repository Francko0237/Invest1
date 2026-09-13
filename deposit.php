<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

require_login();

$user_id = $_SESSION['user_id'];

// Handle POST request (deposit processing)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Jeton de sécurité invalide. Action annulée.');
        header('Location: deposit.php');
        exit();
    }

    $settings = get_all_settings();
    $depot_min = isset($settings['depot_minimum']) ? (float)$settings['depot_minimum'] : 500.0;

    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;

    // Minimum deposit check
    if ($amount < $depot_min) {
        set_flash_message('danger', 'Le dépôt minimum est de ' . format_money($depot_min) . '.');
        header('Location: deposit.php');
        exit();
    }

    try {
        $stmt = $db->prepare("UPDATE users SET solde = solde + :amount WHERE id = :id");
        $stmt->execute(['amount' => $amount, 'id' => $user_id]);

        set_flash_message('success', 'Dépôt de ' . format_money($amount) . ' crédité sur votre compte. (Fictif)');
        header('Location: dashboard.php#tab-compte');
        exit();
    } catch (Exception $e) {
        error_log("Deposit failed: " . $e->getMessage());
        set_flash_message('danger', 'Erreur lors du dépôt. Veuillez réessayer.');
        header('Location: deposit.php');
        exit();
    }
}

// Fetch current user details
$settings = get_all_settings();
$depot_min = isset($settings['depot_minimum']) ? (float)$settings['depot_minimum'] : 500.0;

$stmt = $db->prepare("SELECT nom, email, solde FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch();

$icon_diamond = '<svg class="svg-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 13L2 9z"/></svg>';
$icon_wallet  = '<svg class="svg-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"/><line x1="12" y1="18" x2="12" y2="18"/><path d="M16 8h6V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2h-6a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2z"/></svg>';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Effectuer un Dépôt - BijouxInvest</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?php echo get_theme_class(); ?>">
<?php render_theme_script(); ?>

<nav class="navbar">
    <a href="dashboard.php" class="nav-brand"><?php echo $icon_diamond; ?> BijouxInvest</a>
    <div class="nav-links">
        <span class="nav-user-info" style="color: var(--text-primary);">
            Bonjour, <strong><?php echo e($user['nom']); ?></strong>
            <span style="color: var(--primary); margin-left: 0.5rem; font-weight: bold;">(<?php echo format_money($user['solde']); ?>)</span>
        </span>
    </div>
</nav>

<div class="container" style="max-width: 600px; margin-top: 2rem;">
    
    <?php display_flash_messages(); ?>

    <div class="card">
        <h2 class="card-title" style="display: flex; align-items: center; gap: 0.5rem;">
            <?php echo $icon_wallet; ?> Effectuer un Dépôt
        </h2>
        <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1.5rem;">
            Le dépôt est fictif mais indispensable pour pouvoir souscrire à des plans. Le montant minimum est de <strong style="color:var(--primary)"><?php echo format_money($depot_min); ?></strong>.
        </p>

        <form action="deposit.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            
            <div class="form-group">
                <label class="form-label">Montant du dépôt (FCFA) *</label>
                <input type="number" step="1" min="<?php echo (int)$depot_min; ?>" name="amount" class="form-control" placeholder="Ex: <?php echo (int)$depot_min; ?>" required>
            </div>
            
            <div style="display:flex; gap:0.8rem; margin-top: 2rem;">
                <button type="submit" class="btn-submit" style="flex:1; margin-top:0; background: linear-gradient(135deg, #00e676, #00b0ff);">Déposer</button>
                <a href="dashboard.php#tab-compte" class="btn-cancel" style="flex:0.4; text-decoration:none; text-align:center; display:flex; align-items:center; justify-content:center;">Retour</a>
            </div>
        </form>
    </div>

</div>

<script src="assets/js/toast.js"></script>
</body>
</html>
