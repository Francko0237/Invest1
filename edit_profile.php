<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

require_login();

$user_id = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT nom, telephone, email, role, solde FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch();

if (!$user) {
    set_flash_message('danger', 'Utilisateur introuvable.');
    header('Location: login.php');
    exit();
}

$display_phone = $user['telephone'] ?: $user['email'];

$icon_diamond = '<svg class="svg-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 13L2 9z"/></svg>';
$icon_edit    = '<svg class="svg-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
$icon_lock    = '<svg class="svg-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le Profil - BijouxInvest</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?php echo get_theme_class(); ?>">
<?php render_theme_script(); ?>

<nav class="navbar">
    <a href="dashboard.php" class="nav-brand"><?php echo $icon_diamond; ?> BijouxInvest</a>
    <div class="nav-links">
        <span class="nav-user-info" style="color: var(--text-primary);">
            <strong><?php echo e($user['nom']); ?></strong>
            <span style="color: var(--primary); margin-left: 0.5rem;">(<?php echo format_money($user['solde']); ?>)</span>
        </span>
    </div>
</nav>

<div class="container" style="max-width: 600px; margin-top: 2rem;">

    <?php display_flash_messages(); ?>

    <div class="card">
        <h2 class="card-title" style="display: flex; align-items: center; gap: 0.5rem;">
            <?php echo $icon_edit; ?> Modifier le Profil
        </h2>
        <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1.5rem;">
            Vous pouvez modifier votre nom et votre mot de passe. Le numéro de téléphone est votre identifiant unique et ne peut pas être modifié.
        </p>

        <form action="profile.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

            <div class="form-group">
                <label class="form-label">Nom complet *</label>
                <input type="text" name="nom" class="form-control" value="<?php echo e($user['nom']); ?>" required>
            </div>

            <!-- Telephone: read-only, shown for info -->
            <div class="form-group">
                <label class="form-label" style="display:flex; align-items:center; gap:0.3rem;">
                    <?php echo $icon_lock; ?> Numéro de téléphone
                    <span style="font-size:0.75rem; color:var(--text-muted); margin-left:0.3rem;">(non modifiable)</span>
                </label>
                <input type="text" class="form-control" value="<?php echo e($display_phone); ?>" readonly
                    style="opacity:0.6; cursor:not-allowed; background:rgba(255,255,255,0.03);">
            </div>

            <div class="form-group">
                <label class="form-label">Nouveau mot de passe <span style="color:var(--text-muted);">(laisser vide = inchangé)</span></label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Nouveau mot de passe" autocomplete="new-password">
                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password', this)" title="Afficher le mot de passe" aria-label="Afficher le mot de passe">
                        <svg class="eye-show" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-hide" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Confirmer le mot de passe</label>
                <div class="password-wrapper">
                    <input type="password" id="password2" name="password2" class="form-control" placeholder="Confirmer" autocomplete="new-password">
                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password2', this)" title="Afficher le mot de passe" aria-label="Afficher le mot de passe">
                        <svg class="eye-show" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-hide" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <div style="display:flex; gap:0.8rem; margin-top:1.5rem;">
                <button type="submit" class="btn-submit" style="flex:1; margin-top:0;">Enregistrer</button>
                <a href="dashboard.php#tab-compte" class="btn-cancel" style="flex:0.4; text-decoration:none; text-align:center; display:flex; align-items:center; justify-content:center;">Annuler</a>
            </div>
        </form>
    </div>

</div>

<script>
function togglePasswordVisibility(inputId, btn) {
    var input = document.getElementById(inputId);
    if (!input) return;
    var showIcon = btn.querySelector('.eye-show');
    var hideIcon = btn.querySelector('.eye-hide');
    if (input.type === 'password') {
        input.type = 'text';
        if (showIcon) showIcon.style.display = 'none';
        if (hideIcon) hideIcon.style.display = 'block';
        btn.setAttribute('title', 'Masquer le mot de passe');
    } else {
        input.type = 'password';
        if (showIcon) showIcon.style.display = 'block';
        if (hideIcon) hideIcon.style.display = 'none';
        btn.setAttribute('title', 'Afficher le mot de passe');
    }
}
</script>

<script src="assets/js/toast.js"></script>
</body>
</html>
