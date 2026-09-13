<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';

require_login();
require_admin();

// 1. Process gains globally for all users
$processed_gains = check_and_update_investments($db, null);

// 2. Fetch platform statistics
// Total users
$stmtUsersCount = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$total_users = (int)$stmtUsersCount->fetchColumn();

// Total active investments
$stmtActiveCount = $db->query("SELECT COUNT(*), SUM(montant) FROM investments WHERE status = 'active'");
$active_info = $stmtActiveCount->fetch();
$active_investments_count = (int)$active_info['COUNT(*)'];
$active_investments_volume = (float)$active_info['SUM(montant)'];

// Total pending withdrawals
$stmtWithdrawalsCount = $db->query("SELECT COUNT(*), SUM(montant) FROM withdrawals WHERE status = 'pending'");
$withdraw_info = $stmtWithdrawalsCount->fetch();
$pending_withdrawals_count = (int)$withdraw_info['COUNT(*)'];
$pending_withdrawals_volume = (float)$withdraw_info['SUM(montant)'];

// 3. Fetch recent investments
$stmtRecentInv = $db->query("SELECT i.*, u.nom as user_nom, p.nom as plan_nom 
                             FROM investments i
                             JOIN users u ON i.user_id = u.id
                             JOIN plans p ON i.plan_id = p.id
                             ORDER BY i.start_time DESC LIMIT 5");
$recent_investments = $stmtRecentInv->fetchAll();

// 4. Fetch recent users
$stmtRecentUsers = $db->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 5");
$recent_users = $stmtRecentUsers->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Tableau de bord</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="<?php echo get_theme_class(); ?>">
<?php render_theme_script(); ?>

<nav class="navbar admin-navbar">
    <a href="dashboard.php" class="nav-brand">BijouxInvest - Admin</a>
    <div class="nav-links">
        <a href="dashboard.php" class="nav-link active">Dashboard</a>
        <a href="plans.php" class="nav-link">Gestion Plans</a>
        <a href="users.php" class="nav-link">Gestion Membres</a>
        <a href="withdrawals.php" class="nav-link">Retraits <?php if ($pending_withdrawals_count > 0): ?><span class="badge badge-pending" style="padding: 0.1rem 0.4rem; font-size: 0.7rem;"><?php echo $pending_withdrawals_count; ?></span><?php endif; ?></a>
        <a href="settings.php" class="nav-link">Configuration</a>
        <a href="../dashboard.php" class="nav-btn-outline">Espace Client</a>
        <a href="../logout.php" class="nav-btn-outline" style="border-color: var(--danger); color: var(--danger);">Déconnexion</a>
    </div>
</nav>

<div class="container">
    
    <?php display_flash_messages(); ?>

    <?php if ($processed_gains > 0): ?>
        <div class="alert alert-info">
            <strong>Mise à jour automatique :</strong> <?php echo $processed_gains; ?> investissement(s) arrivé(s) à terme ont été traités et soldés.
        </div>
    <?php endif; ?>

    <!-- Platform Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-label">Utilisateurs inscrits</span>
            <span class="stat-value highlight"><?php echo $total_users; ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Investissements Actifs</span>
            <span class="stat-value"><?php echo $active_investments_count; ?> (<?php echo format_money($active_investments_volume); ?>)</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Retraits en attente</span>
            <span class="stat-value" style="color: var(--warning);"><?php echo $pending_withdrawals_count; ?> (<?php echo format_money($pending_withdrawals_volume); ?>)</span>
            <div style="margin-top: 0.5rem;">
                <?php if ($pending_withdrawals_count > 0): ?>
                    <a href="withdrawals.php" class="nav-btn" style="text-align: center; display: block; width: 100%; font-size: 0.8rem; padding: 0.4rem;">Traiter les retraits</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Details Grid -->
    <div class="grid-2col">
        <!-- Recent Investments -->
        <div class="card">
            <h2 class="card-title">Récents Investissements</h2>
            <?php if (empty($recent_investments)): ?>
                <div class="empty-state">
                    <p>Aucun investissement enregistré.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="border: none;">
                    <table style="background: transparent;">
                        <thead>
                            <tr>
                                <th>Membre</th>
                                <th>Plan</th>
                                <th>Montant</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_investments as $inv): ?>
                                <tr>
                                    <td><strong><?php echo e($inv['user_nom']); ?></strong></td>
                                    <td><?php echo e($inv['plan_nom']); ?></td>
                                    <td><?php echo format_money($inv['montant']); ?></td>
                                    <td>
                                        <?php if ($inv['status'] === 'active'): ?>
                                            <span class="badge badge-active">Actif</span>
                                        <?php else: ?>
                                            <span class="badge badge-finished">Terminé</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Users -->
        <div class="card">
            <h2 class="card-title">Nouveaux Membres</h2>
            <?php if (empty($recent_users)): ?>
                <div class="empty-state">
                    <p>Aucun membre inscrit.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="border: none;">
                    <table style="background: transparent;">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Solde</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $ru): ?>
                                <tr>
                                    <td><strong><?php echo e($ru['nom']); ?></strong></td>
                                    <td><?php echo e($ru['email']); ?></td>
                                    <td style="color: var(--primary); font-weight: 600;"><?php echo format_money($ru['solde']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<footer class="site-footer">
    <p>&copy; 2026 BijouxInvest Admin. Tous droits réservés.</p>
</footer>

<script src="../assets/js/toast.js"></script>
</body>
</html>
