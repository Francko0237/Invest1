<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: edit_profile.php');
    exit();
}

if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    set_flash_message('danger', 'Jeton de sécurité invalide. Action annulée.');
    header('Location: edit_profile.php');
    exit();
}

$user_id   = $_SESSION['user_id'];
$nom       = clean_input($_POST['nom'] ?? '');
$password  = $_POST['password'] ?? '';
$password2 = $_POST['password2'] ?? '';

// Telephone is NOT modifiable — ignored from POST

if (empty($nom)) {
    set_flash_message('danger', 'Le nom est obligatoire.');
    header('Location: edit_profile.php');
    exit();
}

try {
    if (!empty($password)) {
        if ($password !== $password2) {
            set_flash_message('danger', 'Les mots de passe ne correspondent pas.');
            header('Location: edit_profile.php');
            exit();
        }
        if (strlen($password) < 6) {
            set_flash_message('danger', 'Le mot de passe doit contenir au moins 6 caractères.');
            header('Location: edit_profile.php');
            exit();
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET nom = :nom, password = :password WHERE id = :id");
        $stmt->execute(['nom' => $nom, 'password' => $hash, 'id' => $user_id]);
    } else {
        $stmt = $db->prepare("UPDATE users SET nom = :nom WHERE id = :id");
        $stmt->execute(['nom' => $nom, 'id' => $user_id]);
    }

    set_flash_message('success', 'Votre profil a été mis à jour avec succès.');
} catch (Exception $e) {
    error_log("Profile update failed: " . $e->getMessage());
    set_flash_message('danger', 'Erreur lors de la mise à jour du profil.');
    header('Location: edit_profile.php');
    exit();
}

header('Location: dashboard.php#tab-compte');
exit();
