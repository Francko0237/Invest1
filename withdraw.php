<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

require_login();

$user_id = $_SESSION['user_id'];

// 1. Process gains automatically
check_and_update_investments($db, $user_id);

// 2. Fetch total investments count (any status)
$stmtTotalInv = $db->prepare("SELECT COUNT(*) FROM investments WHERE user_id = :user_id");
$stmtTotalInv->execute(['user_id' => $user_id]);
$total_investments_count = (int)$stmtTotalInv->fetchColumn();

// 3. Fetch active investments count
$stmtActiveInv = $db->prepare("SELECT COUNT(*) FROM investments WHERE user_id = :user_id AND status = 'active'");
$stmtActiveInv->execute(['user_id' => $user_id]);
$active_investments_count = (int)$stmtActiveInv->fetchColumn();
$has_active = ($active_investments_count > 0);

// 4. Calculate maximum withdrawable limit
// a) Sum of payouts from finished investments: Capital + Gains
$stmtFinished = $db->prepare("SELECT SUM(i.montant * (1 + p.pourcentage / 100)) 
                              FROM investments i 
                              JOIN plans p ON i.plan_id = p.id 
                              WHERE i.user_id = :user_id AND i.status = 'finished'");
$stmtFinished->execute(['user_id' => $user_id]);
$total_finished_payout = (float)$stmtFinished->fetchColumn();

// b) Sum of already submitted withdrawals (pending or approved)
$stmtWithdrawn = $db->prepare("SELECT SUM(montant) FROM withdrawals WHERE user_id = :user_id AND status IN ('pending', 'approved')");
$stmtWithdrawn->execute(['user_id' => $user_id]);
$total_withdrawn = (float)$stmtWithdrawn->fetchColumn();

// c) Net withdrawable limit based on finished plans
$solde_retirable_limit = max(0.0, $total_finished_payout - $total_withdrawn);

// Fetch settings for minimum withdrawal
$settings = get_all_settings();
$retrait_min = isset($settings['retrait_minimum']) ? (float)$settings['retrait_minimum'] : 500.0;

// Fetch current user details
$stmtUser = $db->prepare("SELECT nom, telephone, email, solde FROM users WHERE id = :id");
$stmtUser->execute(['id' => $user_id]);
$user = $stmtUser->fetch();

$user_telephone = $user['telephone'] ?: $user['email'];
$max_withdrawable = min((float)$user['solde'], $solde_retirable_limit);

// Handle POST request (withdrawal submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Jeton de sécurité invalide. Action annulée.');
        header('Location: withdraw.php');
        exit();
    }

    $amount          = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;
    $tel_retrait     = clean_input($_POST['telephone_retrait'] ?? '');

    if (empty($tel_retrait)) {
        set_flash_message('danger', 'Veuillez indiquer le numéro de téléphone de réception.');
        header('Location: withdraw.php');
        exit();
    }

    // Rule 1: Must have invested at least once
    if ($total_investments_count === 0) {
        set_flash_message('danger', 'Vous devez effectuer au moins un investissement avant de pouvoir demander un retrait.');
        header('Location: withdraw.php');
        exit();
    }

    // Rule 2: Cannot withdraw if active investments are running
    if ($has_active) {
        set_flash_message('danger', 'Retrait refusé : vous devez attendre la fin de vos investissements actifs.');
        header('Location: withdraw.php');
        exit();
    }

    // Rule 3: Balance validation
    if ($amount < $retrait_min) {
        set_flash_message('danger', 'Le retrait minimum est de ' . format_money($retrait_min) . '.');
        header('Location: withdraw.php');
        exit();
    }

    if ($amount > $user['solde']) {
        set_flash_message('danger', 'Votre solde de compte est insuffisant pour cette demande.');
        header('Location: withdraw.php');
        exit();
    }

    if ($amount > $solde_retirable_limit) {
        set_flash_message('danger', 'Seuls les fonds issus d\'investissements terminés peuvent être retirés. Votre limite retirable est de ' . format_money($max_withdrawable) . '.');
        header('Location: withdraw.php');
        exit();
    }

    // Process withdrawal
    $db->beginTransaction();
    try {
        // Deduct balance immediately
        $upd = $db->prepare("UPDATE users SET solde = solde - :amount WHERE id = :id");
        $upd->execute(['amount' => $amount, 'id' => $user_id]);

        // Insert pending withdrawal record with telephone
        $ins = $db->prepare("INSERT INTO withdrawals (user_id, montant, telephone_retrait, status, created_at) VALUES (:user_id, :amount, :telephone, 'pending', NOW())");
        $ins->execute([
            'user_id'   => $user_id,
            'amount'    => $amount,
            'telephone' => $tel_retrait
        ]);

        $db->commit();
        set_flash_message('success', 'Demande de retrait de ' . format_money($amount) . ' enregistrée ! Votre solde a été immédiatement débité. L\'administrateur validera et effectuera le transfert vers votre numéro Mobile Money sous peu.');
        header('Location: dashboard.php#tab-compte');
        exit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Withdrawal processing failed: " . $e->getMessage());
        set_flash_message('danger', 'Une erreur est survenue lors de l\'enregistrement de votre retrait.');
        header('Location: withdraw.php');
        exit();
    }
}

$icon_diamond = '<svg class="svg-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 13L2 9z"/></svg>';
$icon_wallet  = '<svg class="svg-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"/><line x1="12" y1="18" x2="12" y2="18"/><path d="M16 8h6V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2h-6a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2z"/></svg>';
$icon_clock   = '<svg class="svg-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demander un Retrait - BijouxInvest</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
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

    <?php if ($total_investments_count === 0): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <strong>Information :</strong> Vous n'avez effectué aucun investissement pour le moment. Souscrivez à un plan avant de pouvoir retirer des fonds.
        </div>
    <?php elseif ($has_active): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <strong>Retraits verrouillés :</strong> Vous avez des investissements en cours. Attendez leur échéance pour accéder au retrait.
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 class="card-title" style="display: flex; align-items: center; gap: 0.5rem;">
            <?php echo $icon_wallet; ?> Demander un Retrait
        </h2>

        <!-- Balance summary -->
        <div style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.5rem; font-size: 0.88rem; line-height: 1.6;">
            <div style="display:flex; justify-content:space-between; margin-bottom:0.3rem;">
                <span style="color:var(--text-secondary);">Solde du compte :</span>
                <strong style="color:var(--text-primary);"><?php echo format_money($user['solde']); ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:0.3rem;">
                <span style="color:var(--text-secondary);">Gains d'investissements terminés :</span>
                <strong style="color:var(--success);"><?php echo format_money($total_finished_payout); ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:0.3rem;">
                <span style="color:var(--text-secondary);">Déjà retiré :</span>
                <strong style="color:var(--accent);">- <?php echo format_money($total_withdrawn); ?></strong>
            </div>
            <hr style="border:0; border-top:1px solid var(--border-color); margin:0.5rem 0;">
            <div style="display:flex; justify-content:space-between; font-weight:bold;">
                <span style="color:var(--text-primary);">Solde retirable :</span>
                <strong style="color:var(--primary);"><?php echo format_money($max_withdrawable); ?></strong>
            </div>
        </div>

        <!-- Always-active form — validation happens server-side on submit -->
        <form action="withdraw.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

            <div class="form-group">
                <label class="form-label">Numéro de réception (Mobile Money) *</label>
                <div style="display:flex; gap:0;">
                    <span style="display:flex; align-items:center; padding: 0 0.75rem; background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); border-right:0; border-radius: var(--border-radius-sm) 0 0 var(--border-radius-sm); font-weight:600; color:var(--primary); white-space:nowrap; font-size:0.95rem;">+237</span>
                    <input type="tel" name="telephone_retrait" class="form-control" style="border-radius: 0 var(--border-radius-sm) var(--border-radius-sm) 0;" value="<?php echo e(ltrim(str_replace('+237', '', $user_telephone))); ?>" maxlength="9" pattern="[0-9]{9}" placeholder="6XXXXXXXX" required>
                </div>
                <span style="font-size:0.78rem; color:var(--text-muted); margin-top:0.3rem; display:block;">Numéro Mobile Money (Orange, MTN…) qui recevra le virement</span>
            </div>

            <div class="form-group">
                <label class="form-label">Montant à retirer (FCFA) *</label>
                <input type="number" step="1" min="<?php echo (int)$retrait_min; ?>" name="amount" class="form-control" placeholder="Ex: <?php echo (int)$retrait_min; ?>" required>
                <span style="font-size:0.78rem; color:var(--text-muted); margin-top:0.3rem; display:block;">
                    Retrait minimum : <strong><?php echo format_money($retrait_min); ?></strong> | Solde retirable disponible : <strong style="color:var(--primary);"><?php echo format_money($max_withdrawable); ?></strong>
                </span>
            </div>

            <div style="display:flex; gap:0.8rem; margin-top:1.5rem;">
                <button type="submit" class="btn-submit" style="flex:1; margin-top:0;">Soumettre</button>
                <a href="dashboard.php#tab-compte" class="btn-cancel" style="flex:0.4; text-decoration:none; text-align:center; display:flex; align-items:center; justify-content:center;">Retour</a>
            </div>
        </form>
    </div>

</div>

<script src="assets/js/toast.js"></script>
</body>
</html>
