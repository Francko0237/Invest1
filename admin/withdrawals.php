<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';

require_login();
require_admin();

// Handle Actions (Approve / Reject)
if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['csrf_token'])) {
    if (!verify_csrf_token($_GET['csrf_token'])) {
        set_flash_message('danger', 'Jeton de sécurité invalide.');
    } else {
        $id = (int)$_GET['id'];
        $action = $_GET['action'];

        // Fetch withdrawal record
        $stmtWithdrawal = $db->prepare("SELECT * FROM withdrawals WHERE id = :id");
        $stmtWithdrawal->execute(['id' => $id]);
        $withdrawal = $stmtWithdrawal->fetch();

        if (!$withdrawal) {
            set_flash_message('danger', 'Demande de retrait introuvable.');
        } elseif ($withdrawal['status'] !== 'pending') {
            set_flash_message('danger', 'Cette demande a déjà été traitée.');
        } else {
            $db->beginTransaction();
            try {
                if ($action === 'approve') {
                    // Update status to approved (balance was already deducted when request was made)
                    $upd = $db->prepare("UPDATE withdrawals SET status = 'approved' WHERE id = :id");
                    $upd->execute(['id' => $id]);
                    $db->commit();
                    set_flash_message('success', 'La demande de retrait a été validée.');
                } elseif ($action === 'reject') {
                    // Update status to rejected
                    $upd = $db->prepare("UPDATE withdrawals SET status = 'rejected' WHERE id = :id");
                    $upd->execute(['id' => $id]);

                    // Refund the user's balance
                    $refund = $db->prepare("UPDATE users SET solde = solde + :amount WHERE id = :user_id");
                    $refund->execute([
                        'amount' => $withdrawal['montant'],
                        'user_id' => $withdrawal['user_id']
                    ]);

                    $db->commit();
                    set_flash_message('warning', 'La demande de retrait a été rejetée et le montant a été remboursé sur le solde de l\'utilisateur.');
                } else {
                    $db->rollBack();
                    set_flash_message('danger', 'Action inconnue.');
                }
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Failed to process withdrawal action: " . $e->getMessage());
                set_flash_message('danger', 'Erreur serveur lors du traitement du retrait.');
            }
        }
        header('Location: withdrawals.php');
        exit();
    }
}

// Fetch withdrawals
$stmtWithdrawals = $db->query("SELECT w.*, u.nom as user_nom, u.email as user_email 
                               FROM withdrawals w
                               JOIN users u ON w.user_id = u.id
                               ORDER BY CASE WHEN w.status = 'pending' THEN 1 ELSE 2 END ASC, w.created_at DESC");
$withdrawals = $stmtWithdrawals->fetchAll();
// Count pending items for badges
$stmtPendingDep = $db->query("SELECT COUNT(*) FROM deposits WHERE status = 'pending'");
$pending_deposits_count = (int)$stmtPendingDep->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Gestion des Retraits</title>
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
        <a href="withdrawals.php" class="nav-link active">Retraits <?php if ($pending_withdrawals_count > 0): ?><span class="badge badge-pending" style="padding: 0.1rem 0.4rem; font-size: 0.7rem;"><?php echo $pending_withdrawals_count; ?></span><?php endif; ?></a>
        <a href="settings.php" class="nav-link">Configuration</a>
        <a href="../dashboard.php" class="nav-btn-outline">Espace Client</a>
        <a href="../logout.php" class="nav-btn-outline" style="border-color: var(--danger); color: var(--danger);">Déconnexion</a>
    </div>
</nav>

<div class="container">
    
    <?php display_flash_messages(); ?>

    <div class="card">
        <h2 class="card-title">Traitement des Demandes de Retrait</h2>
        <?php if (empty($withdrawals)): ?>
            <div class="empty-state">
                <p>Aucune demande de retrait enregistrée.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive" style="border: none;">
                <table style="background: transparent;">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Numéro Réception</th>
                            <th>Montant demandé</th>
                            <th>Date Demande</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($withdrawals as $w): ?>
                            <tr>
                                <td><strong><?php echo e($w['user_nom']); ?></strong></td>
                                <td><?php echo e($w['user_email']); ?></td>
                                <td><code style="color: var(--secondary); font-weight: bold;"><?php echo e($w['telephone_retrait'] ?: 'Non spécifié'); ?></code></td>
                                <td style="font-weight: 600; color: var(--primary);"><?php echo format_money($w['montant']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($w['created_at'])); ?></td>
                                <td>
                                    <?php if ($w['status'] === 'pending'): ?>
                                        <span class="badge badge-pending">En attente</span>
                                    <?php elseif ($w['status'] === 'approved'): ?>
                                        <span class="badge badge-approved">Validé</span>
                                    <?php else: ?>
                                        <span class="badge badge-rejected">Refusé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($w['status'] === 'pending'): ?>
                                        <div class="actions-cell">
                                            <a href="withdrawals.php?action=approve&id=<?php echo $w['id']; ?>&csrf_token=<?php echo e(csrf_token()); ?>" class="btn-admin-action" onclick="return confirm('Voulez-vous vraiment valider ce retrait ?');">
                                                Valider
                                            </a>
                                            <a href="withdrawals.php?action=reject&id=<?php echo $w['id']; ?>&csrf_token=<?php echo e(csrf_token()); ?>" class="btn-admin-action-danger" onclick="return confirm('Voulez-vous vraiment rejeter ce retrait ?');">
                                                Rejeter
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size: 0.85rem; color: var(--text-muted);">Traité</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<footer class="site-footer">
    <p>&copy; 2026 BijouxInvest Admin. Tous droits réservés.</p>
</footer>

<script src="../assets/js/toast.js"></script>
</body>
</html>
