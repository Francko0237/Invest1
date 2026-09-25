<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit();
}

$ref_code = isset($_GET['ref']) ? clean_input($_GET['ref']) : '';
$nom = '';
$telephone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Jeton de sécurité invalide. Veuillez réessayer.');
    } else {
        $nom       = clean_input($_POST['nom']);
        $tel_input = clean_input($_POST['telephone']); // digits only (without +237)
        $password  = $_POST['password'];
        $confirm   = $_POST['confirm_password'];
        $ref_input = clean_input($_POST['ref_code']);

        // Build full telephone with country code
        $telephone = '+237' . preg_replace('/\D/', '', $tel_input);

        // Validation
        if (empty($nom) || empty($tel_input) || empty($password)) {
            set_flash_message('danger', 'Veuillez remplir tous les champs obligatoires.');
        } elseif (!preg_match('/^\+237[0-9]{9}$/', $telephone)) {
            set_flash_message('danger', 'Numéro de téléphone invalide. Saisissez 9 chiffres après +237.');
        } elseif (strlen($password) < 6) {
            set_flash_message('danger', 'Le mot de passe doit contenir au moins 6 caractères.');
        } elseif ($password !== $confirm) {
            set_flash_message('danger', 'Les deux mots de passe ne correspondent pas.');
        } else {
            // Check if telephone already exists
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE telephone = :telephone");
            $stmt->execute(['telephone' => $telephone]);
            if ($stmt->fetchColumn() > 0) {
                set_flash_message('danger', 'Ce numéro de téléphone est déjà enregistré.');
            } else {
                // Determine parrain_id
                $parrain_id = null;
                if (!empty($ref_input)) {
                    $stmtParrain = $db->prepare("SELECT id FROM users WHERE code_parrain = :code");
                    $stmtParrain->execute(['code' => $ref_input]);
                    $parrain = $stmtParrain->fetch();
                    if ($parrain) {
                        $parrain_id = $parrain['id'];
                    } else {
                        set_flash_message('warning', 'Le code de parrainage est invalide et a été ignoré.');
                    }
                }

                $new_ref_code    = generate_referral_code($db);
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                try {
                    // email kept as telephone for compatibility (or NULL)
                    $ins = $db->prepare("INSERT INTO users (nom, telephone, email, password, code_parrain, parrain_id, solde, role) 
                                         VALUES (:nom, :telephone, :email, :password, :code_parrain, :parrain_id, 0.00, 'user')");
                    $ins->execute([
                        'nom'         => $nom,
                        'telephone'   => $telephone,
                        'email'       => $telephone, // keep email col consistent
                        'password'    => $hashed_password,
                        'code_parrain'=> $new_ref_code,
                        'parrain_id'  => $parrain_id
                    ]);

                    set_flash_message('success', 'Compte créé avec succès. Connectez-vous pour commencer.');
                    header('Location: login.php');
                    exit();
                } catch (Exception $e) {
                    error_log("Registration failed: " . $e->getMessage());
                    set_flash_message('danger', 'Erreur lors de la création du compte. Réessayez.');
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - BijouxInvest</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
</head>
<body class="<?php echo get_theme_class(); ?>">
<?php render_theme_script(); ?>

<div class="auth-wrapper">
    <!-- Theme Toggle — Sun/Moon SVG -->
    <button class="theme-toggle-btn sm" style="position: absolute; top: 1.5rem; right: 1.5rem; z-index: 10;" onclick="toggleSiteTheme()" title="Changer le thème" aria-label="Changer le thème">
      <svg class="icon-sun" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      <svg class="icon-moon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>

    <div class="auth-card">
        <div class="auth-header">
            <div style="display:inline-flex; align-items:center; justify-content:center; width:56px; height:56px; border-radius:50%; background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.3); margin-bottom:0.75rem; color:var(--primary);">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 12L2 9z"/><path d="M11 3 8 9l3 12 3-12-3-6z"/><path d="M2 9h20"/></svg>
            </div>
            <h1>Créez votre compte</h1>
            <p>Commencez à investir dans des plans d'exception</p>
        </div>

        <?php display_flash_messages(); ?>

        <form action="register.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

            <div class="form-group">
                <label for="nom" class="form-label">Nom Complet *</label>
                <input type="text" id="nom" name="nom" class="form-control" placeholder="Jean Dupont" value="<?php echo e($nom); ?>" required>
            </div>

            <div class="form-group">
                <label for="telephone" class="form-label">Numéro de Téléphone *</label>
                <div style="display:flex; gap:0;">
                    <span style="display:flex; align-items:center; padding: 0 0.75rem; background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); border-right:0; border-radius: var(--border-radius-sm) 0 0 var(--border-radius-sm); font-weight:600; color:var(--primary); white-space:nowrap; font-size:0.95rem;">+237</span>
                    <input type="tel" id="telephone" name="telephone" class="form-control" style="border-radius: 0 var(--border-radius-sm) var(--border-radius-sm) 0;" placeholder="6XXXXXXXX" maxlength="9" pattern="[0-9]{9}" value="<?php echo e(ltrim(str_replace('+237','',$telephone))); ?>" required>
                </div>
                <small style="color:var(--text-muted); font-size:0.78rem;">Format : +237 suivi de 9 chiffres</small>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Mot de passe *</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password', this)" title="Afficher le mot de passe" aria-label="Afficher le mot de passe">
                        <svg class="eye-show" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-hide" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirmer le mot de passe *</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('confirm_password', this)" title="Afficher le mot de passe" aria-label="Afficher le mot de passe">
                        <svg class="eye-show" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-hide" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="ref_code" class="form-label">Code de parrainage <span style="color:var(--text-muted)">(Optionnel)</span></label>
                <input type="text" id="ref_code" name="ref_code" class="form-control" placeholder="CODE123" value="<?php echo e($ref_code); ?>">
            </div>

            <button type="submit" class="btn-submit">S'inscrire</button>
        </form>

        <div class="auth-footer">
            Déjà inscrit ? <a href="login.php">Se connecter</a>
        </div>
        <div style="text-align:center; margin-top: 1rem;">
            <a href="index.php" style="font-size:0.85rem; color:var(--text-muted); text-decoration:none;">← Accueil</a>
        </div>
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

function toggleSiteTheme() {
    var isLight = document.body.classList.toggle('theme-light');
    try { localStorage.setItem('userThemeChoice', isLight ? 'light' : 'dark'); } catch(e) {}
}
</script>

<script src="assets/js/toast.js"></script>
</body>
</html>
