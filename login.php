<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Redirect if already logged in
if (is_logged_in()) {
    if (is_admin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

$telephone_full  = '';  // +237XXXXXXXXX
$telephone_short = '';  // XXXXXXXXX only

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Jeton de sécurité invalide. Veuillez réessayer.');
    } else {
        $tel_input = clean_input($_POST['telephone']);
        $password  = $_POST['password'];

        // Normalize input — strip all non-digits, build both formats
        $digits_only    = preg_replace('/\D/', '', $tel_input);
        $telephone_full  = '+237' . $digits_only;   // stored format: +237XXXXXXXXX
        $telephone_short = $digits_only;             // legacy fallback

        if (empty($tel_input) || empty($password)) {
            set_flash_message('danger', 'Veuillez saisir votre numéro de téléphone et votre mot de passe.');
        } else {
            // Search by: full phone, short phone (legacy), or email (admin fallback)
            $stmt = $db->prepare("
                SELECT * FROM users 
                WHERE telephone = ?
                   OR telephone = ?
                   OR email     = ?
                   OR email     = ?
                LIMIT 1
            ");
            $stmt->execute([
                $telephone_full,
                $telephone_short,
                $telephone_full,
                $telephone_short,
            ]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_nom']  = $user['nom'];
                $_SESSION['user_role'] = $user['role'];

                set_flash_message('success', 'Bienvenue, ' . $user['nom'] . ' !');

                if ($user['role'] === 'admin') {
                    header('Location: admin/dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            } else {
                set_flash_message('danger', 'Numéro ou mot de passe invalide.');
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
    <title>Connexion - BijouxInvest</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?php echo get_theme_class(); ?>">
<?php render_theme_script(); ?>

<div class="auth-wrapper">
    <!-- Theme Toggle — Sun/Moon SVG -->
    <button class="theme-toggle-btn sm" style="position: absolute; top: 1.5rem; right: 1.5rem; z-index: 10;" onclick="toggleSiteTheme()" title="Changer le thème" aria-label="Changer le thème">
      <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="5"/>
        <line x1="12" y1="1" x2="12" y2="3"/>
        <line x1="12" y1="21" x2="12" y2="23"/>
        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
        <line x1="1" y1="12" x2="3" y2="12"/>
        <line x1="21" y1="12" x2="23" y2="12"/>
        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
      </svg>
      <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
      </svg>
    </button>

    <div class="auth-card">
        <div class="auth-header">
            <div style="display:inline-flex; align-items:center; justify-content:center; width:56px; height:56px; border-radius:50%; background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.3); margin-bottom:0.75rem; color:var(--primary);">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 12L2 9z"/><path d="M11 3 8 9l3 12 3-12-3-6z"/><path d="M2 9h20"/></svg>
            </div>
            <h1>Connexion</h1>
            <p>Accédez à votre portefeuille et vos gains</p>
        </div>

        <?php display_flash_messages(); ?>

        <form action="login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

            <div class="form-group">
                <label for="telephone" class="form-label">Numéro de Téléphone</label>
                <div style="display:flex; gap:0;">
                    <span style="display:flex; align-items:center; padding: 0 0.75rem; background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); border-right:0; border-radius: var(--border-radius-sm) 0 0 var(--border-radius-sm); font-weight:600; color:var(--primary); white-space:nowrap; font-size:0.95rem;">+237</span>
                    <input type="tel" id="telephone" name="telephone" class="form-control" style="border-radius: 0 var(--border-radius-sm) var(--border-radius-sm) 0;" placeholder="6XXXXXXXX" maxlength="9" pattern="[0-9]{9}" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Mot de passe</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password', this)" title="Afficher le mot de passe" aria-label="Afficher le mot de passe">
                        <svg class="eye-show" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-hide" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit">Se connecter</button>
        </form>

        <div class="auth-footer">
            Pas encore de compte ? <a href="register.php">Créer un compte</a>
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
