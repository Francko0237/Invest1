<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$settings = get_all_settings();

$depot_min = isset($settings['depot_minimum']) ? (float)$settings['depot_minimum'] : 500.0;
$momo_num  = isset($settings['momo_number']) ? $settings['momo_number'] : '670000000';
$momo_nom  = isset($settings['momo_name']) ? $settings['momo_name'] : 'BijouxInvest MTN';
$om_num    = isset($settings['om_number']) ? $settings['om_number'] : '690000000';
$om_nom    = isset($settings['om_name']) ? $settings['om_name'] : 'BijouxInvest Orange';

// Handle POST request (deposit processing)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Jeton de sécurité invalide. Action annulée.');
        header('Location: deposit.php');
        exit();
    }

    $amount         = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'mtn_momo';
    $sender_phone   = isset($_POST['sender_phone']) ? trim($_POST['sender_phone']) : '';
    $transaction_ref= isset($_POST['transaction_ref']) ? trim($_POST['transaction_ref']) : '';

    // Validations
    if ($amount < $depot_min) {
        set_flash_message('danger', 'Le dépôt minimum est de ' . format_money($depot_min) . '.');
        header('Location: deposit.php');
        exit();
    }

    if (empty($sender_phone)) {
        set_flash_message('danger', 'Veuillez renseigner le numéro de téléphone qui a effectué le transfert.');
        header('Location: deposit.php');
        exit();
    }

    // Handle File Upload (Screenshot proof)
    $screenshot_path = null;
    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['screenshot']['tmp_name'];
        $fileName    = $_FILES['screenshot']['name'];
        $fileSize    = $_FILES['screenshot']['size'];
        $fileType    = $_FILES['screenshot']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            if ($fileSize <= 5 * 1024 * 1024) { // Max 5MB
                $uploadFileDir = __DIR__ . '/uploads/proofs/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                $newFileName = 'proof_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $screenshot_path = 'uploads/proofs/' . $newFileName;
                } else {
                    set_flash_message('warning', 'Échec du téléchargement de l\'image de preuve, mais votre demande sera enregistrée.');
                }
            } else {
                set_flash_message('danger', 'La taille de l\'image ne doit pas dépasser 5 Mo.');
                header('Location: deposit.php');
                exit();
            }
        } else {
            set_flash_message('danger', 'Format d\'image non supporté. Formats acceptés : JPG, PNG, WEBP.');
            header('Location: deposit.php');
            exit();
        }
    }

    try {
        $stmt = $db->prepare("INSERT INTO deposits (user_id, montant, payment_method, sender_phone, transaction_ref, screenshot, status) 
                              VALUES (:user_id, :montant, :payment_method, :sender_phone, :transaction_ref, :screenshot, 'pending')");
        $stmt->execute([
            'user_id'         => $user_id,
            'montant'         => $amount,
            'payment_method'  => $payment_method,
            'sender_phone'    => $sender_phone,
            'transaction_ref' => $transaction_ref,
            'screenshot'      => $screenshot_path
        ]);

        set_flash_message('success', 'Votre demande de dépôt de ' . format_money($amount) . ' a été soumise avec succès. L\'administrateur va la vérifier sous peu.');
        header('Location: deposit.php');
        exit();
    } catch (Exception $e) {
        error_log("Deposit submission failed: " . $e->getMessage());
        set_flash_message('danger', 'Erreur lors de l\'enregistrement du dépôt. Veuillez réessayer.');
        header('Location: deposit.php');
        exit();
    }
}

// Fetch user details
$stmt = $db->prepare("SELECT nom, email, solde FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch();

// Fetch user's deposit history
$stmtDep = $db->prepare("SELECT * FROM deposits WHERE user_id = :user_id ORDER BY created_at DESC");
$stmtDep->execute(['user_id' => $user_id]);
$my_deposits = $stmtDep->fetchAll();

$icon_diamond = '<svg class="svg-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 13L2 9z"/></svg>';
$icon_wallet  = '<svg class="svg-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"/><line x1="12" y1="18" x2="12" y2="18"/><path d="M16 8h6V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2h-6a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2z"/></svg>';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Effectuer un Dépôt - BijouxInvest</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <style>
        .payment-card {
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1.2rem;
            margin-bottom: 1.2rem;
            background: var(--bg-card);
            cursor: pointer;
            transition: var(--transition);
        }
        .payment-card.active {
            border-color: var(--primary);
            box-shadow: 0 0 15px var(--primary-glow);
        }
        .copy-badge {
            background: rgba(0, 242, 254, 0.15);
            color: var(--primary);
            border: 1px dashed var(--primary);
            padding: 0.35rem 0.75rem;
            border-radius: var(--border-radius-sm);
            font-family: monospace;
            font-size: 1.1rem;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        .copy-badge:hover {
            background: var(--primary);
            color: #000;
        }
    </style>
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

<div class="container" style="max-width: 680px; margin-top: 1.5rem;">
    
    <?php display_flash_messages(); ?>

    <div class="card">
        <h2 class="card-title" style="display: flex; align-items: center; gap: 0.5rem;">
            <?php echo $icon_wallet; ?> Effectuer un Dépôt Manuel
        </h2>
        <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1.5rem;">
            Effectuez le transfert vers l'un de nos comptes ci-dessous, puis renseignez le formulaire et joignez votre reçu d'écran.
            Montant minimum : <strong style="color:var(--primary)"><?php echo format_money($depot_min); ?></strong>.
        </p>

        <!-- Instructions Comptes Mobile Money -->
        <div style="margin-bottom: 1.5rem;">
            <label class="form-label" style="margin-bottom:0.6rem;">1. Choisissez le moyen de paiement et effectuez le transfert :</label>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <!-- MTN MoMo -->
                <div class="payment-card active" id="card-mtn" onclick="selectPayment('mtn_momo')">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem;">
                        <strong style="color:#ffcc00; font-size:1rem;">🟡 MTN Mobile Money</strong>
                        <input type="radio" name="payment_method_choice" value="mtn_momo" checked style="accent-color: var(--primary);">
                    </div>
                    <div style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.4rem;"><?php echo e($momo_nom); ?></div>
                    <div class="copy-badge" onclick="copyNumber(event, '<?php echo e($momo_num); ?>')">
                        <span><?php echo e($momo_num); ?></span>
                        <small style="font-size:0.7rem; text-transform:uppercase;">Copier</small>
                    </div>
                </div>

                <!-- Orange Money -->
                <div class="payment-card" id="card-om" onclick="selectPayment('orange_money')">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem;">
                        <strong style="color:#ff6600; font-size:1rem;">🟠 Orange Money</strong>
                        <input type="radio" name="payment_method_choice" value="orange_money" style="accent-color: var(--primary);">
                    </div>
                    <div style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.4rem;"><?php echo e($om_nom); ?></div>
                    <div class="copy-badge" onclick="copyNumber(event, '<?php echo e($om_num); ?>')">
                        <span><?php echo e($om_num); ?></span>
                        <small style="font-size:0.7rem; text-transform:uppercase;">Copier</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire de soumission -->
        <form action="deposit.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="payment_method" id="selected_method" value="mtn_momo">

            <label class="form-label" style="margin-bottom:0.6rem;">2. Renseignez les détails du paiement effectué :</label>

            <div class="form-group">
                <label class="form-label">Montant transféré (FCFA) *</label>
                <input type="number" step="1" min="<?php echo (int)$depot_min; ?>" name="amount" class="form-control" placeholder="Ex: 5000" required>
            </div>

            <div class="form-group">
                <label class="form-label">Numéro de l'expéditeur (votre numéro MoMo/OM) *</label>
                <input type="text" name="sender_phone" class="form-control" placeholder="Ex: 6XXXXXXXX" required>
            </div>

            <div class="form-group">
                <label class="form-label">Référence / ID de transaction SMS (Optionnel)</label>
                <input type="text" name="transaction_ref" class="form-control" placeholder="Ex: 254891023 ou TxID...">
            </div>

            <div class="form-group">
                <label class="form-label">Capture d'écran du reçu SMS / Preuve de paiement *</label>
                <input type="file" name="screenshot" accept="image/*" class="form-control" required style="padding: 0.5rem;">
                <small style="color: var(--text-muted); display:block; margin-top:0.3rem;">Formats acceptés : JPG, PNG, WEBP (Max 5 Mo).</small>
            </div>
            
            <div style="display:flex; gap:0.8rem; margin-top: 2rem;">
                <button type="submit" class="btn-submit" style="flex:1; margin-top:0; background: linear-gradient(135deg, #00f2fe, #4facfe);">Soumettre le Dépôt</button>
                <a href="dashboard.php#tab-compte" class="btn-cancel" style="flex:0.4; text-decoration:none; text-align:center; display:flex; align-items:center; justify-content:center;">Retour</a>
            </div>
        </form>
    </div>

    <!-- Historique des Dépôts -->
    <div class="card" style="margin-top: 2rem;">
        <h3 class="card-title">Historique de mes Dépôts</h3>
        
        <?php if (empty($my_deposits)): ?>
            <p class="text-secondary" style="font-size:0.9rem;">Vous n'avez effectué aucun dépôt pour le moment.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid var(--border-color);">
                            <th style="padding:0.6rem;">Date</th>
                            <th style="padding:0.6rem;">Montant</th>
                            <th style="padding:0.6rem;">Méthode</th>
                            <th style="padding:0.6rem;">Expéditeur</th>
                            <th style="padding:0.6rem;">Preuve</th>
                            <th style="padding:0.6rem;">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_deposits as $d): 
                            $badge_class = 'badge-pending';
                            $status_label = 'En attente';
                            if ($d['status'] === 'approved') {
                                $badge_class = 'badge-approved';
                                $status_label = 'Approuvé';
                            } elseif ($d['status'] === 'rejected') {
                                $badge_class = 'badge-finished';
                                $status_label = 'Rejeté';
                            }
                        ?>
                            <tr style="border-bottom:1px solid var(--border-color);">
                                <td style="padding:0.6rem;"><?php echo date('d/m/Y H:i', strtotime($d['created_at'])); ?></td>
                                <td style="padding:0.6rem; font-weight:bold; color:var(--primary);"><?php echo format_money($d['montant']); ?></td>
                                <td style="padding:0.6rem;"><?php echo $d['payment_method'] === 'orange_money' ? 'Orange Money' : 'MTN MoMo'; ?></td>
                                <td style="padding:0.6rem;"><?php echo e($d['sender_phone']); ?></td>
                                <td style="padding:0.6rem;">
                                    <?php if (!empty($d['screenshot'])): ?>
                                        <a href="<?php echo e($d['screenshot']); ?>" target="_blank" style="color:var(--primary); text-decoration:underline;">Voir image</a>
                                    <?php else: ?>
                                        <span class="text-muted">Aucune</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:0.6rem;">
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $status_label; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
function selectPayment(method) {
    document.getElementById('selected_method').value = method;
    var cardMtn = document.getElementById('card-mtn');
    var cardOm  = document.getElementById('card-om');
    
    if (method === 'mtn_momo') {
        cardMtn.classList.add('active');
        cardOm.classList.remove('active');
        cardMtn.querySelector('input[type="radio"]').checked = true;
    } else {
        cardOm.classList.add('active');
        cardMtn.classList.remove('active');
        cardOm.querySelector('input[type="radio"]').checked = true;
    }
}

function copyNumber(event, text) {
    event.stopPropagation();
    navigator.clipboard.writeText(text).then(function() {
        if (window.BijouxToast) {
            window.BijouxToast.show('success', 'Numéro copié : ' + text);
        } else {
            alert('Numéro copié : ' + text);
        }
    });
}
</script>
<script src="assets/js/toast.js"></script>
</body>
</html>
