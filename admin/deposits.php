<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';

require_login();
require_admin();

// Handle Actions (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Jeton de sécurité invalide.');
        header('Location: deposits.php');
        exit();
    }

    $action     = isset($_POST['action']) ? $_POST['action'] : '';
    $deposit_id = isset($_POST['deposit_id']) ? (int)$_POST['deposit_id'] : 0;
    $admin_note = isset($_POST['admin_note']) ? trim($_POST['admin_note']) : '';

    if ($deposit_id > 0) {
        $stmtDep = $db->prepare("SELECT * FROM deposits WHERE id = :id");
        $stmtDep->execute(['id' => $deposit_id]);
        $dep = $stmtDep->fetch();

        if ($dep && $dep['status'] === 'pending') {
            if ($action === 'approve') {
                $db->beginTransaction();
                try {
                    // 1. Credit the user balance
                    $updUser = $db->prepare("UPDATE users SET solde = solde + :montant WHERE id = :user_id");
                    $updUser->execute([
                        'montant' => $dep['montant'],
                        'user_id' => $dep['user_id']
                    ]);

                    // 2. Mark deposit as approved
                    $updDep = $db->prepare("UPDATE deposits SET status = 'approved', admin_note = :note WHERE id = :id");
                    $updDep->execute([
                        'note' => $admin_note,
                        'id'   => $deposit_id
                    ]);

                    $db->commit();
                    set_flash_message('success', '✅ Dépôt #' . $deposit_id . ' de ' . format_money($dep['montant']) . ' approuvé et crédité au solde du membre !');
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Error approving deposit: " . $e->getMessage());
                    set_flash_message('danger', 'Erreur lors de l\'approbation du dépôt.');
                }
            } elseif ($action === 'reject') {
                try {
                    $updDep = $db->prepare("UPDATE deposits SET status = 'rejected', admin_note = :note WHERE id = :id");
                    $updDep->execute([
                        'note' => $admin_note,
                        'id'   => $deposit_id
                    ]);
                    set_flash_message('warning', '❌ Dépôt #' . $deposit_id . ' a été rejeté.');
                } catch (Exception $e) {
                    error_log("Error rejecting deposit: " . $e->getMessage());
                    set_flash_message('danger', 'Erreur lors du rejet du dépôt.');
                }
            }
        }
    }
    header('Location: deposits.php');
    exit();
}

// Fetch stats
$stmtPendingCount = $db->query("SELECT COUNT(*), SUM(montant) FROM deposits WHERE status = 'pending'");
$pending_info = $stmtPendingCount->fetch();
$pending_count  = (int)$pending_info['COUNT(*)'];
$pending_volume = (float)$pending_info['SUM(montant)'];

$stmtApprovedCount = $db->query("SELECT COUNT(*), SUM(montant) FROM deposits WHERE status = 'approved'");
$approved_info = $stmtApprovedCount->fetch();
$approved_count  = (int)$approved_info['COUNT(*)'];
$approved_volume = (float)$approved_info['SUM(montant)'];

// Fetch Pending Deposits
$stmtPending = $db->query("SELECT d.*, u.nom as user_nom, u.telephone as user_phone, u.email as user_email 
                           FROM deposits d 
                           JOIN users u ON d.user_id = u.id 
                           WHERE d.status = 'pending' 
                           ORDER BY d.created_at ASC");
$pending_deposits = $stmtPending->fetchAll();

// Fetch History Deposits
$stmtHistory = $db->query("SELECT d.*, u.nom as user_nom, u.telephone as user_phone 
                           FROM deposits d 
                           JOIN users u ON d.user_id = u.id 
                           WHERE d.status != 'pending' 
                           ORDER BY d.updated_at DESC LIMIT 30");
$history_deposits = $stmtHistory->fetchAll();

// Count pending withdrawals for badge
$stmtPendingWd = $db->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'");
$pending_withdrawals_count = (int)$stmtPendingWd->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Gestion des Dépôts</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <style>
        .proof-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .proof-thumb:hover {
            transform: scale(1.1);
            border-color: var(--primary);
        }
    </style>
</head>
<body class="<?php echo get_theme_class(); ?>">
<?php render_theme_script(); ?>

<nav class="navbar admin-navbar">
    <a href="dashboard.php" class="nav-brand">BijouxInvest - Admin</a>
    <div class="nav-links">
        <a href="dashboard.php" class="nav-link">Dashboard</a>
        <a href="deposits.php" class="nav-link active">Dépôts <?php if ($pending_count > 0): ?><span class="badge badge-pending" style="padding: 0.1rem 0.4rem; font-size: 0.7rem;"><?php echo $pending_count; ?></span><?php endif; ?></a>
        <a href="plans.php" class="nav-link">Gestion Plans</a>
        <a href="users.php" class="nav-link">Gestion Membres</a>
        <a href="withdrawals.php" class="nav-link">Retraits <?php if ($pending_withdrawals_count > 0): ?><span class="badge badge-pending" style="padding: 0.1rem 0.4rem; font-size: 0.7rem;"><?php echo $pending_withdrawals_count; ?></span><?php endif; ?></a>
        <a href="settings.php" class="nav-link">Configuration</a>
        <a href="../dashboard.php" class="nav-btn-outline">Espace Client</a>
        <a href="../logout.php" class="nav-btn-outline" style="border-color: var(--danger); color: var(--danger);">Déconnexion</a>
    </div>
</nav>

<div class="container" style="margin-top: 1.5rem;">
    
    <?php display_flash_messages(); ?>

    <h2 style="margin-bottom: 1.2rem; font-weight:600;">Gestion des Dépôts Manuels</h2>

    <!-- Stat cards -->
    <div class="stats-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; margin-bottom: 1.5rem;">
        <div class="card stat-card">
            <span class="text-secondary" style="font-size:0.85rem;">Dépôts en Attente</span>
            <div style="font-size:1.4rem; font-weight:bold; color:var(--warning); margin-top:0.3rem;"><?php echo $pending_count; ?></div>
            <span style="font-size:0.8rem; color:var(--text-muted);"><?php echo format_money($pending_volume); ?> total</span>
        </div>
        <div class="card stat-card">
            <span class="text-secondary" style="font-size:0.85rem;">Dépôts Approuvés</span>
            <div style="font-size:1.4rem; font-weight:bold; color:var(--success); margin-top:0.3rem;"><?php echo $approved_count; ?></div>
            <span style="font-size:0.8rem; color:var(--text-muted);"><?php echo format_money($approved_volume); ?> crédités</span>
        </div>
    </div>

    <!-- Section 1: Demandes en Attente -->
    <div class="card" style="margin-bottom: 2rem;">
        <h3 class="card-title" style="display:flex; align-items:center; justify-content:space-between;">
            <span>Demandes de Dépôt en Attente (<?php echo $pending_count; ?>)</span>
        </h3>

        <?php if (empty($pending_deposits)): ?>
            <p class="text-secondary" style="padding:1rem 0;">Aucune demande de dépôt en attente pour le moment. 🎉</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:0.88rem;">
                    <thead>
                        <tr style="text-align:left; border-bottom:2px solid var(--border-color); background:rgba(0,0,0,0.1);">
                            <th style="padding:0.75rem;">Client</th>
                            <th style="padding:0.75rem;">Montant</th>
                            <th style="padding:0.75rem;">Méthode</th>
                            <th style="padding:0.75rem;">Numéro Payeur</th>
                            <th style="padding:0.75rem;">Réf SMS</th>
                            <th style="padding:0.75rem;">Preuve Reçu</th>
                            <th style="padding:0.75rem;">Date</th>
                            <th style="padding:0.75rem; text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_deposits as $d): ?>
                            <tr style="border-bottom:1px solid var(--border-color);">
                                <td style="padding:0.75rem;">
                                    <strong><?php echo e($d['user_nom']); ?></strong><br>
                                    <small class="text-muted"><?php echo e($d['user_phone'] ?: $d['user_email']); ?></small>
                                </td>
                                <td style="padding:0.75rem; font-weight:bold; color:var(--primary); font-size:1rem;">
                                    <?php echo format_money($d['montant']); ?>
                                </td>
                                <td style="padding:0.75rem;">
                                    <span class="badge" style="background:<?php echo $d['payment_method'] === 'orange_money' ? 'rgba(255,102,0,0.15)' : 'rgba(255,204,0,0.15)'; ?>; color:<?php echo $d['payment_method'] === 'orange_money' ? '#ff6600' : '#ffcc00'; ?>;">
                                        <?php echo $d['payment_method'] === 'orange_money' ? 'Orange Money' : 'MTN MoMo'; ?>
                                    </span>
                                </td>
                                <td style="padding:0.75rem; font-weight:600;"><?php echo e($d['sender_phone']); ?></td>
                                <td style="padding:0.75rem;">
                                    <code><?php echo e($d['transaction_ref'] ?: 'Non fournie'); ?></code>
                                </td>
                                <td style="padding:0.75rem;">
                                    <?php if (!empty($d['screenshot'])): ?>
                                        <img src="../<?php echo e($d['screenshot']); ?>" alt="Preuve" class="proof-thumb" onclick="openModal('../<?php echo e($d['screenshot']); ?>')">
                                    <?php else: ?>
                                        <span class="text-muted">Sans image</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:0.75rem; font-size:0.8rem; color:var(--text-secondary);">
                                    <?php echo date('d/m/Y H:i', strtotime($d['created_at'])); ?>
                                </td>
                                <td style="padding:0.75rem; text-align:center;">
                                    <form action="deposits.php" method="POST" style="display:inline-flex; gap:0.4rem;">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                        <input type="hidden" name="deposit_id" value="<?php echo $d['id']; ?>">
                                        <button type="submit" name="action" value="approve" onclick="return confirm('Confirmer le dépôt de <?php echo format_money($d['montant']); ?> pour <?php echo e($d['user_nom']); ?> ?')" style="background:#00e676; color:#000; border:none; padding:0.4rem 0.8rem; border-radius:var(--border-radius-sm); font-weight:bold; cursor:pointer;">
                                            ✔ Valider
                                        </button>
                                        <button type="submit" name="action" value="reject" onclick="return confirm('Rejeter cette demande de dépôt ?')" style="background:#ff1744; color:#fff; border:none; padding:0.4rem 0.8rem; border-radius:var(--border-radius-sm); font-weight:bold; cursor:pointer;">
                                            ✖ Rejeter
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section 2: Historique des Dépôts Traités -->
    <div class="card">
        <h3 class="card-title">Historique des Dépôts Traités (30 derniers)</h3>
        
        <?php if (empty($history_deposits)): ?>
            <p class="text-secondary">Aucun dépôt n'a encore été traité.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid var(--border-color);">
                            <th style="padding:0.6rem;">ID</th>
                            <th style="padding:0.6rem;">Client</th>
                            <th style="padding:0.6rem;">Montant</th>
                            <th style="padding:0.6rem;">Méthode</th>
                            <th style="padding:0.6rem;">Numéro Exp.</th>
                            <th style="padding:0.6rem;">Preuve</th>
                            <th style="padding:0.6rem;">Statut</th>
                            <th style="padding:0.6rem;">Date Traitement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history_deposits as $h): ?>
                            <tr style="border-bottom:1px solid var(--border-color);">
                                <td style="padding:0.6rem;">#<?php echo $h['id']; ?></td>
                                <td style="padding:0.6rem;"><?php echo e($h['user_nom']); ?></td>
                                <td style="padding:0.6rem; font-weight:bold; color:var(--primary);"><?php echo format_money($h['montant']); ?></td>
                                <td style="padding:0.6rem;"><?php echo $h['payment_method'] === 'orange_money' ? 'Orange Money' : 'MTN MoMo'; ?></td>
                                <td style="padding:0.6rem;"><?php echo e($h['sender_phone']); ?></td>
                                <td style="padding:0.6rem;">
                                    <?php if (!empty($h['screenshot'])): ?>
                                        <a href="../<?php echo e($h['screenshot']); ?>" target="_blank" style="color:var(--primary); text-decoration:underline;">Preuve</a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:0.6rem;">
                                    <span class="badge <?php echo $h['status'] === 'approved' ? 'badge-approved' : 'badge-finished'; ?>">
                                        <?php echo $h['status'] === 'approved' ? 'Approuvé' : 'Rejeté'; ?>
                                    </span>
                                </td>
                                <td style="padding:0.6rem; color:var(--text-muted); font-size:0.8rem;">
                                    <?php echo date('d/m/Y H:i', strtotime($h['updated_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Modal Visualiseur d'Image -->
<div id="imageModal" class="custom-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:99999; justify-content:center; align-items:center;">
    <div style="position:relative; max-width:90%; max-height:90%;">
        <button onclick="closeModal()" style="position:absolute; top:-15px; right:-15px; background:var(--danger); color:#fff; border:none; width:35px; height:35px; border-radius:50%; font-weight:bold; cursor:pointer; font-size:1.2rem;">×</button>
        <img id="modalImg" src="" style="max-width:100%; max-height:80vh; border-radius:var(--border-radius); border:2px solid var(--border-color);">
    </div>
</div>

<script>
function openModal(imgSrc) {
    document.getElementById('modalImg').src = imgSrc;
    document.getElementById('imageModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('imageModal').style.display = 'none';
}
</script>
<script src="../assets/js/toast.js"></script>
</body>
</html>
