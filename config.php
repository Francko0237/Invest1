<?php
// Secure Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // In production/HTTPS environment, set secure to 1
    // ini_set('session.cookie_secure', 1);
    
    session_start();
}

// Database Credentials (XAMPP default)
define('DB_HOST', 'localhost');
define('DB_NAME', 'invest_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Generate a CSRF token if one does not exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Output Escaping / XSS prevention
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Clean user input
 */
function clean_input($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * CSRF helpers
 */
function csrf_token() {
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash Messages
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type' => $type, // 'success', 'danger', 'info', 'warning'
        'text' => $message
    ];
}

function display_flash_messages() {
    if (!empty($_SESSION['flash_messages'])) {
        $msgs = json_encode($_SESSION['flash_messages'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        echo '<script>window.__flashMessages = ' . $msgs . ';</script>';
        unset($_SESSION['flash_messages']);
    }
}

/**
 * Authentication Helpers
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        set_flash_message('danger', 'Veuillez vous connecter pour accéder à cette page.');
        header('Location: login.php');
        exit();
    }
}

function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function require_admin() {
    if (!is_admin()) {
        set_flash_message('danger', 'Accès restreint. Vous devez être administrateur.');
        header('Location: ../login.php');
        exit();
    }
}

/**
 * Formats a currency value
 */
function format_money($amount) {
    return number_format($amount, 0, ',', "\u{00A0}") . "\u{00A0}FCFA";
}

/**
 * Returns the theme class name to put on the body element
 */
function get_theme_class() {
    global $db;
    if (!isset($db)) {
        return '';
    }
    static $theme_class = null;
    if ($theme_class !== null) {
        return $theme_class;
    }
    try {
        $stmt = $db->query("SELECT key_value FROM settings WHERE key_name = 'site_theme'");
        $theme = $stmt->fetchColumn();
        if ($theme === 'light') {
            $theme_class = 'theme-light';
        } else {
            $theme_class = '';
        }
    } catch (Exception $e) {
        $theme_class = '';
    }
    return $theme_class;
}

/**
 * Renders inline JS script to sync global admin theme & user preference instantly on page load
 */
function render_theme_script() {
    $global_theme = (get_theme_class() === 'theme-light') ? 'light' : 'dark';
    ?>
    <script>
    (function() {
        var adminDefault = '<?php echo $global_theme; ?>';
        var themeToApply = adminDefault;
        try {
            // User explicit preference priority over admin default
            var userChoice = localStorage.getItem('userThemeChoice');
            if (userChoice === 'light' || userChoice === 'dark') {
                themeToApply = userChoice;
            }
        } catch(e) {}
        if (themeToApply === 'light') {
            document.body.classList.add('theme-light');
        } else {
            document.body.classList.remove('theme-light');
        }
    })();
    </script>
    <?php
}

