<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';

require_login();
require_admin();

// Handle Balance Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Jeton de sécurité invalide.');
    } else {
        if ($_POST['action'] === 'adjust_balance') {
            $user_to_adjust = (int)$_POST['user_id'];
            $amount = (float)$_POST['amount'];
            $adj_type = clean_input($_POST['type']); // 'credit' or 'debit'

            if ($user_to_adjust <= 0 || $amount <= 0) {
                set_flash_message('danger', 'Utilisateur ou montant invalide.');
            } elseif ($adj_type !== 'credit' && $adj_type !== 'debit') {
                set_flash_message('danger', 'Type d\'ajustement invalide.');
            } else {
                try {
                    // If debit, verify user has enough balance (or let it go negative if necessary, but preventing is safer)
                    if ($adj_type === 'debit') {
                        $stmtCheck = $db->prepare("SELECT solde FROM users WHERE id = :id");
                        $stmtCheck->execute(['id' => $user_to_adjust]);
                        $current_solde = (float)$stmtCheck->fetchColumn();
                        if ($current_solde < $amount) {
                            $amount = $current_solde; // Max out debit
                        }
                    }

                    $operator = ($adj_type === 'credit') ? '+' : '-';
                    $stmt = $db->prepare("UPDATE users SET solde = solde {$operator} :amount WHERE id = :id");
                    $stmt->execute(['amount' => $amount, 'id' => $user_to_adjust]);

                    $message = ($adj_type === 'credit') ? 'crédité de ' : 'débité de ';
                    set_flash_message('success', 'Le solde de l\'utilisateur a été ' . $message . format_money($amount) . '.');
                } catch (Exception $e) {
                    error_log("Failed to adjust user balance: " . $e->getMessage());
                    set_flash_message('danger', 'Erreur lors de l\'ajustement du solde.');
                }
            }
        }
    }
    header('Location: users.php');
    exit();
}

// Fetch all users along with their referrer's name
$stmtUsers = $db->query("SELECT u1.*, u2.nom as parrain_nom 
                         FROM users u1 
                         LEFT JOIN users u2 ON u1.parrain_id = u2.id 
                         ORDER BY u1.created_at DESC");
// Count pending items for badges
$stmtPendingDep = $db->query("SELECT COUNT(*) FROM deposits WHERE status = 'pending'");
$pending_deposits_count = (int)$stmtPendingDep->fetchColumn();

$stmtPendingWd = $db->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'");
$pending_withdrawals_count = (int)$stmtPendingWd->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Gestion des Membres</title>
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
        <a href="users.php" class="nav-link active">Gestion Membres</a>
        <a href="withdrawals.php" class="nav-link">Retraits <?php if ($pending_withdrawals_count > 0): ?><span class="badge badge-pending" style="padding: 0.1rem 0.4rem; font-size: 0.7rem;"><?php echo $pending_withdrawals_count; ?></span><?php endif; ?></a>
        <a href="settings.php" class="nav-link">Configuration</a>
        <a href="../dashboard.php" class="nav-btn-outline">Espace Client</a>
        <a href="../logout.php" class="nav-btn-outline" style="border-color: var(--danger); color: var(--danger);">Déconnexion</a>
    </div>
</nav>

<div class="container">
    
    <?php display_flash_messages(); ?>

    <div class="card">
        <h2 class="card-title">Gestion des Membres Inscrits</h2>
        <?php if (empty($users)): ?>
            <div class="empty-state">
                <p>Aucun membre inscrit.</p>
            </div>
        <?php else: ?>
            <!-- ── Desktop Table ───────────────────────────────── -->
            <div class="table-responsive desktop-only" style="border: none;">
                <table style="background: transparent;">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Solde</th>
                            <th>Code Parrain</th>
                            <th>Parrain</th>
                            <th>Date Inscription</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><strong><?php echo e($u['nom']); ?></strong></td>
                                <td><?php echo e($u['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $u['role'] === 'admin' ? 'badge-approved' : 'badge-finished'; ?>">
                                        <?php echo e($u['role']); ?>
                                    </span>
                                </td>
                                <td style="color: var(--primary); font-weight: 600;"><?php echo format_money($u['solde']); ?></td>
                                <td><code style="color: var(--secondary); font-weight: bold;"><?php echo e($u['code_parrain']); ?></code></td>
                                <td>
                                    <?php if ($u['parrain_nom']): ?>
                                        <span style="font-size: 0.9rem;"><?php echo e($u['parrain_nom']); ?></span>
                                    <?php else: ?>
                                        <span style="font-size: 0.85rem; color: var(--text-muted);">Aucun</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($u['created_at'])); ?></td>
                                <td>
                                    <?php if ($u['role'] !== 'admin'): ?>
                                        <form action="users.php" method="POST" style="display: flex; gap: 0.25rem; align-items: center;">
                                            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                            <input type="hidden" name="action" value="adjust_balance">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <input type="number" step="0.01" min="0.01" name="amount" placeholder="Montant" class="form-control" style="padding: 0.3rem 0.5rem; font-size: 0.8rem; width: 80px;" required>
                                            <select name="type" class="form-control" style="padding: 0.3rem 0.5rem; font-size: 0.8rem; width: 85px; background: rgba(14, 18, 27, 0.8);" required>
                                                <option value="credit">Créditer</option>
                                                <option value="debit">Débiter</option>
                                            </select>
                                            <button type="submit" class="btn-admin-action" style="padding: 0.35rem 0.6rem; font-size: 0.8rem;">OK</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size: 0.85rem; color: var(--text-muted);">Non applicable</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ── Mobile Cards ────────────────────────────────── -->
            <div class="mobile-only" style="display:flex;flex-direction:column;gap:1rem;margin-top:0.5rem;">
                <?php foreach ($users as $u): ?>
                    <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border-color);border-radius:var(--border-radius);padding:1rem;">
                        <!-- Header: name + role badge -->
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;">
                            <div>
                                <div style="font-weight:700;color:var(--text-primary);font-size:1rem;"><?php echo e($u['nom']); ?></div>
                                <div style="font-size:0.8rem;color:var(--text-muted);margin-top:0.1rem;"><?php echo e($u['email']); ?></div>
                            </div>
                            <span class="badge <?php echo $u['role'] === 'admin' ? 'badge-approved' : 'badge-finished'; ?>">
                                <?php echo e($u['role']); ?>
                            </span>
                        </div>
                        <!-- Info row -->
                        <div style="display:flex;flex-wrap:wrap;gap:0.4rem 1.2rem;font-size:0.85rem;color:var(--text-secondary);padding:0.6rem 0;border-top:1px solid rgba(255,255,255,0.05);border-bottom:1px solid rgba(255,255,255,0.05);margin-bottom:0.7rem;">
                            <span>Solde : <strong style="color:var(--primary);"><?php echo format_money($u['solde']); ?></strong></span>
                            <span>Code : <code style="color:var(--secondary);font-weight:bold;"><?php echo e($u['code_parrain']); ?></code></span>
                            <span>Parrain : <?php echo e($u['parrain_nom'] ?: 'Aucun'); ?></span>
                            <span>Inscrit le : <?php echo date('d/m/Y', strtotime($u['created_at'])); ?></span>
                        </div>
                        <!-- Balance action -->
                        <?php if ($u['role'] !== 'admin'): ?>
                            <form action="users.php" method="POST" style="display:flex;gap:0.4rem;align-items:center;">
                                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="adjust_balance">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <input type="number" step="0.01" min="0.01" name="amount" placeholder="Montant FCFA" class="form-control" style="flex:1;padding:0.4rem;font-size:0.85rem;" required>
                                <select name="type" class="form-control" style="width:105px;padding:0.4rem;font-size:0.85rem;background:rgba(14,18,27,0.8);" required>
                                    <option value="credit">Créditer</option>
                                    <option value="debit">Débiter</option>
                                </select>
                                <button type="submit" class="btn-admin-action" style="padding:0.4rem 0.8rem;">OK</button>
                            </form>
                        <?php else: ?>
                            <span style="font-size:0.82rem;color:var(--text-muted);">Non applicable</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
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
