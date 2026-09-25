<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

require_login();

$user_id = $_SESSION['user_id'];

// Get settings
$settings = get_all_settings();
$depot_min = isset($settings['depot_minimum']) ? (float)$settings['depot_minimum'] : 500.0;
$retrait_min = isset($settings['retrait_minimum']) ? (float)$settings['retrait_minimum'] : 500.0;
$ref_lvl1 = isset($settings['ref_level_1_percent']) ? (float)$settings['ref_level_1_percent'] : 10.0;
$ref_lvl2 = isset($settings['ref_level_2_percent']) ? (float)$settings['ref_level_2_percent'] : 5.0;
$ref_lvl3 = isset($settings['ref_level_3_percent']) ? (float)$settings['ref_level_3_percent'] : 2.0;

// Premium inline SVG Icons (Lucide outline style)
$icon_diamond = '<svg class="svg-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 13L2 9z"/></svg>';
$icon_shield  = '<svg class="svg-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
$icon_users   = '<svg class="svg-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
$icon_user    = '<svg class="svg-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
$icon_edit    = '<svg class="svg-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
$icon_wallet  = '<svg class="svg-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"/><line x1="12" y1="18" x2="12" y2="18"/><path d="M16 8h6V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2h-6a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2z"/></svg>';
$icon_history = '<svg class="svg-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/></svg>';
$icon_gift    = '<svg class="svg-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>';
$icon_logout  = '<svg class="svg-icon" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
$icon_trend   = '<svg class="svg-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>';
$icon_clock   = '<svg class="svg-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';

// 1. Automatically process gains for this user
check_and_update_investments($db, $user_id);

// 2. Fetch current user information
$stmt = $db->prepare("SELECT nom, telephone, email, solde, code_parrain, role FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch();

// 3. Check for active investments (blocks withdrawals)
$has_active = has_active_investments($db, $user_id);

// 4. Get financial stats
$stats = get_user_stats($db, $user_id);

// 5. Fetch available investment plans
$stmtPlans = $db->prepare("SELECT * FROM plans WHERE statut = 1 ORDER BY prix ASC");
$stmtPlans->execute();
$plans = $stmtPlans->fetchAll();

// 6. Fetch active investments
$stmtActiveInv = $db->prepare("SELECT i.*, p.nom as plan_nom, p.pourcentage, p.image as plan_image, p.niveau, NOW() as current_db_time
                               FROM investments i 
                               JOIN plans p ON i.plan_id = p.id
                               WHERE i.user_id = :user_id AND i.status = 'active'
                               ORDER BY i.end_time ASC");
$stmtActiveInv->execute(['user_id' => $user_id]);
$active_investments = $stmtActiveInv->fetchAll();

// 7. Fetch finished investments
$stmtHistoryInv = $db->prepare("SELECT i.*, p.nom as plan_nom, p.pourcentage, p.image as plan_image, p.niveau 
                                 FROM investments i 
                                 JOIN plans p ON i.plan_id = p.id
                                 WHERE i.user_id = :user_id AND i.status = 'finished'
                                 ORDER BY i.end_time DESC LIMIT 10");
$stmtHistoryInv->execute(['user_id' => $user_id]);
$history_investments = $stmtHistoryInv->fetchAll();

// 8. Fetch referral bonuses
$stmtBonuses = $db->prepare("SELECT b.*, u.nom as from_user_nom 
                             FROM referral_bonus b
                             JOIN users u ON b.from_user_id = u.id
                             WHERE b.user_id = :user_id
                             ORDER BY b.created_at DESC LIMIT 10");
$stmtBonuses->execute(['user_id' => $user_id]);
$referral_bonuses = $stmtBonuses->fetchAll();

// 9. Fetch withdrawal history
$stmtWithdrawals = $db->prepare("SELECT * FROM withdrawals WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10");
$stmtWithdrawals->execute(['user_id' => $user_id]);
$withdrawals = $stmtWithdrawals->fetchAll();

// 10. Fetch active plan IDs for current user
$stmtActivePlanIds = $db->prepare("SELECT DISTINCT plan_id FROM investments WHERE user_id = :user_id AND status = 'active'");
$stmtActivePlanIds->execute(['user_id' => $user_id]);
$active_plan_ids = $stmtActivePlanIds->fetchAll(PDO::FETCH_COLUMN);

// Construct absolute referral link
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$domain = $_SERVER['HTTP_HOST'];
$dir = dirname($_SERVER['SCRIPT_NAME']);
$ref_link = $protocol . $domain . ($dir === '/' ? '' : $dir) . "/register.php?ref=" . $user['code_parrain'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - BijouxInvest</title>
    <script>
    window.addEventListener('error', function(e) {
        var errDiv = document.getElementById('js-error-banner');
        if (!errDiv) {
            errDiv = document.createElement('div');
            errDiv.id = 'js-error-banner';
            errDiv.style.position = 'fixed';
            errDiv.style.top = '0';
            errDiv.style.left = '0';
            errDiv.style.width = '100%';
            errDiv.style.background = '#ff1744';
            errDiv.style.color = '#fff';
            errDiv.style.padding = '12px';
            errDiv.style.zIndex = '999999';
            errDiv.style.fontSize = '13px';
            errDiv.style.fontWeight = 'bold';
            errDiv.style.fontFamily = 'monospace';
            errDiv.style.textAlign = 'center';
            document.body.insertBefore(errDiv, document.body.firstChild);
        }
        errDiv.textContent = 'Erreur JS: ' + e.message + ' (' + e.filename.split('/').pop() + ':' + e.lineno + ')';
    });
    </script>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
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
        <?php if ($user['role'] === 'admin'): ?>
            <a href="admin/dashboard.php" class="nav-btn-outline">Panneau Admin</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">
    
    <?php display_flash_messages(); ?>

    <!-- Desktop Navigation Tabs -->
    <div class="dashboard-tabs-wrapper">
        <div class="dashboard-tabs">
            <button class="tab-link active" data-tab="tab-accueil"><?php echo $icon_diamond; ?> Boutique Bijoux</button>
            <button class="tab-link" data-tab="tab-plans"><?php echo $icon_shield; ?> Mes Plans</button>
            <button class="tab-link" data-tab="tab-parrainage"><?php echo $icon_users; ?> Parrainage</button>
            <button class="tab-link" data-tab="tab-compte"><?php echo $icon_user; ?> Mon Compte</button>
        </div>
    </div>

    <!-- TAB CONTENTS -->
    
    <!-- 1. TAB: ACCUEIL / BOUTIQUE -->
    <div id="tab-accueil" class="tab-content active">
        <!-- Header row: title + layout toggles -->
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.8rem;margin-bottom:1.2rem;">
            <h2 style="margin:0;font-weight:600;background:linear-gradient(135deg,var(--primary),var(--secondary));-webkit-background-clip:text;-webkit-text-fill-color:transparent;display:flex;align-items:center;gap:0.5rem;">
                <?php echo $icon_diamond; ?> Catalogues de Plans
            </h2>
            <div style="display:flex;gap:0.4rem;">
                <button id="btn-list" class="layout-toggle-btn active" onclick="setLayout('list')" title="Vue liste">
                    ☰ Liste
                </button>
                <button id="btn-grid" class="layout-toggle-btn" onclick="setLayout('grid')" title="Vue grille">
                    ⊞ Grille
                </button>
            </div>
        </div>

        <!-- Plans container (id used by JS to toggle mode-list / mode-grid) -->
        <div id="plans-container" class="mode-list">
            <?php if (empty($plans)): ?>
                <div class="card" style="text-align:center;">
                    <p class="text-secondary">Aucun plan d'investissement disponible pour le moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($plans as $i => $p):
                    $can_afford        = ($user['solde'] >= $p['prix']);
                    $is_already_active = in_array($p['id'], $active_plan_ids);
                    $gain_amount       = round($p['prix'] * $p['pourcentage'] / 100, 2);
                    $retour_total      = $p['prix'] + $gain_amount;
                    $duree             = $p['duree_valeur'] . ' ' . $p['duree_type'];
                    $tier              = get_plan_tier_info($i, $p['nom'], $p['niveau'] ?? '');
                    $img               = !empty($p['image']) ? $p['image'] : 'assets/images/bijou_bronze.png';
                ?>
                
                <!-- LIST MODE CARD (100% Original compact horizontal row) -->
                <div class="plan-card show-in-list">
                    <div class="pc-img">
                        <?php if (!empty($p['image'])): ?>
                            <img src="<?php echo e($p['image']); ?>" alt="<?php echo e($p['nom']); ?>">
                        <?php else: ?>
                            <div class="pc-img-fallback" style="color:var(--primary);">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h12l4 6-10 12L2 9z"/><path d="M11 3 8 9l3 12 3-12-3-6z"/><path d="M2 9h20"/></svg>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="plan-badge"><?php echo $is_already_active ? 'En cours ⏳' : 'Disponible'; ?></span>
                    <div class="pc-body">
                        <div class="pc-name"><?php echo e($p['nom']); ?></div>
                        <div class="pc-meta"><?php echo e($p['duree_valeur']); ?> <?php echo e($p['duree_type']); ?></div>
                        <div class="pc-return">Retour total : <strong><?php echo format_money($retour_total); ?></strong></div>
                        <div class="pc-invest">Mise : <?php echo format_money($p['prix']); ?></div>
                    </div>
                    <div class="pc-action">
                        <form action="invest.php" method="POST" onsubmit="showInvestmentConfirm(event, this);"
                              data-prix="<?php echo (float)$p['prix']; ?>"
                              data-nom="<?php echo e($p['nom']); ?>"
                              data-pct="<?php echo (float)$p['pourcentage']; ?>"
                              data-solde="<?php echo (float)$user['solde']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                            <input type="hidden" name="plan_id" value="<?php echo e($p['id']); ?>">
                            <input type="hidden" name="quantity" value="1">
                            <?php if ($is_already_active): ?>
                                <button type="button" class="plan-btn" disabled style="opacity: 0.7; cursor: not-allowed; background: rgba(255,179,0,0.2); color: #ffb300; border-color: #ffb300;" title="Investissement déjà en cours sur ce produit">
                                    En cours ⏳
                                </button>
                            <?php elseif ($can_afford): ?>
                                <button type="submit" class="plan-btn">
                                    Investir
                                </button>
                            <?php else: ?>
                                <button type="submit" class="plan-btn" disabled title="Solde insuffisant">
                                    Insuffisant
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- GRID MODE CARD (Boutique Jewelry Product Card) -->
                <div class="boutique-card show-in-grid" style="border-color: <?php echo $tier['border']; ?>; box-shadow: 0 4px 20px <?php echo $tier['glow']; ?>;">
                    <div class="bc-img-wrap">
                        <img src="<?php echo e($img); ?>" alt="<?php echo e($p['nom']); ?>">
                        <span class="bc-badge" style="border-color: <?php echo $tier['border']; ?>; color: <?php echo $tier['border']; ?>; background: <?php echo $tier['badge_bg']; ?>;">
                            <?php echo $tier['badge']; ?>
                        </span>
                        <span class="bc-price-pill">
                            <?php echo format_money($p['prix']); ?>
                        </span>
                    </div>
                    <div class="bc-body">
                        <div class="bc-main-info">
                            <h3 class="bc-title"><?php echo e($p['nom']); ?></h3>
                        </div>
                        <div class="bc-metrics">
                            <div class="bc-metric-row">
                                <span class="bc-label">Profit :</span>
                                <strong class="bc-val-success">+<?php echo $p['pourcentage']; ?>% (+<?php echo format_money($gain_amount); ?>)</strong>
                            </div>
                            <div class="bc-metric-row">
                                <span class="bc-label">Durée :</span>
                                <strong><?php echo e($duree); ?></strong>
                            </div>
                            <div class="bc-metric-row">
                                <span class="bc-label">Total Retour :</span>
                                <strong class="bc-val-primary"><?php echo format_money($retour_total); ?></strong>
                            </div>
                        </div>
                        <div class="bc-action">
                            <form action="invest.php" method="POST" onsubmit="showInvestmentConfirm(event, this);"
                                  data-prix="<?php echo (float)$p['prix']; ?>"
                                  data-nom="<?php echo e($p['nom']); ?>"
                                  data-pct="<?php echo (float)$p['pourcentage']; ?>"
                                  data-solde="<?php echo (float)$user['solde']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                <input type="hidden" name="plan_id" value="<?php echo e($p['id']); ?>">
                                <input type="hidden" name="quantity" value="1">
                                <?php if ($is_already_active): ?>
                                    <button type="button" class="bc-btn disabled" disabled style="opacity: 0.7; cursor: not-allowed; background: rgba(255,179,0,0.15); color: #ffb300; border-color: #ffb300;" title="Vous avez déjà un investissement actif sur ce produit. Attendez son échéance pour réinvestir.">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        Investissement en cours
                                    </button>
                                <?php elseif ($can_afford): ?>
                                    <button type="submit" class="bc-btn">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 12L2 9z"/><path d="M11 3 8 9l3 12 3-12-3-6z"/><path d="M2 9h20"/></svg>
                                        Investir Maintenant
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="bc-btn disabled" disabled title="Solde insuffisant (<?php echo format_money($user['solde']); ?> / <?php echo format_money($p['prix']); ?>)">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        Solde Insuffisant
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. TAB: PLANS -->
    <div id="tab-plans" class="tab-content">
        <!-- Active Investments -->
        <div class="card" style="margin-bottom: 2rem;">
            <h2 class="card-title" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                <span style="display: flex; align-items: center; gap: 0.5rem;"><?php echo $icon_shield; ?> Investissements Actifs</span>
                <span class="badge badge-active" style="font-size: 0.82rem; padding: 0.3rem 0.75rem;"><?php echo count($active_investments); ?> En cours</span>
            </h2>
            
            <?php if (empty($active_investments)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><?php echo $icon_shield; ?></div>
                    <p>Aucun investissement en cours pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="active-plans-grid">
                    <?php foreach ($active_investments as $i => $inv): 
                        $end_ts = strtotime($inv['end_time']);
                        $start_ts = strtotime($inv['start_time']);
                        $current_ts = strtotime($inv['current_db_time']);
                        
                        $total_duration = $end_ts - $start_ts;
                        $elapsed = $current_ts - $start_ts;
                        $percent = 0;
                        if ($total_duration > 0) {
                            $percent = min(100, max(0, ($elapsed / $total_duration) * 100));
                        }
                        $gain = round($inv['montant'] * ($inv['pourcentage'] / 100), 2);
                        $payout = $inv['montant'] + $gain;
                        $tier = get_plan_tier_info($i, $inv['plan_nom'], $inv['niveau'] ?? '');
                        $img = !empty($inv['plan_image']) ? $inv['plan_image'] : 'assets/images/bijou_bronze.png';
                    ?>
                        <div class="active-plan-card active-investment-row" data-start="<?php echo $start_ts; ?>" data-end="<?php echo $end_ts; ?>" style="border-color: <?php echo $tier['border']; ?>; box-shadow: 0 4px 20px <?php echo $tier['glow']; ?>;">
                            
                            <!-- Header: Image + Plan Info -->
                            <div class="ap-header">
                                <div class="ap-img-wrap" style="border-color: <?php echo $tier['border']; ?>;">
                                    <img src="<?php echo e($img); ?>" alt="<?php echo e($inv['plan_nom']); ?>">
                                </div>
                                <div class="ap-info">
                                    <span class="ap-tier-badge" style="color: <?php echo $tier['border']; ?>; background: <?php echo $tier['badge_bg']; ?>;">
                                        <?php echo $tier['badge']; ?>
                                    </span>
                                    <h3 class="ap-title">
                                        <?php if (isset($inv['quantity']) && $inv['quantity'] > 1): ?>
                                            <span style="color:var(--primary); font-size:0.85em;"><?php echo (int)$inv['quantity']; ?>×</span>
                                        <?php endif; ?>
                                        <?php echo e($inv['plan_nom']); ?>
                                    </h3>
                                    <span style="font-size: 0.78rem; color: var(--success); font-weight: 700;">En Cours d'Acquisition</span>
                                </div>
                            </div>

                            <!-- Financial Metrics -->
                            <div class="ap-metrics">
                                <div class="ap-metric-row">
                                    <span style="color: var(--text-muted);">Capital Investi :</span>
                                    <strong><?php echo format_money($inv['montant']); ?></strong>
                                </div>
                                <div class="ap-metric-row">
                                    <span style="color: var(--text-muted);">Taux & Profit :</span>
                                    <strong style="color: var(--success);">+<?php echo $inv['pourcentage']; ?>% (+<?php echo format_money($gain); ?>)</strong>
                                </div>
                                <div class="ap-metric-row">
                                    <span style="color: var(--text-muted);">Gain Total à l'Échéance :</span>
                                    <strong style="color: var(--primary); font-size: 0.98rem;"><?php echo format_money($payout); ?></strong>
                                </div>
                            </div>

                            <!-- Countdown & Progress -->
                            <div class="ap-progress-sec">
                                <div class="ap-progress-header">
                                    <span>Temps Restant :</span>
                                    <span class="countdown-text" style="font-family: monospace; font-weight: bold; color: var(--primary);">Calcul...</span>
                                </div>
                                <div class="progress-container" style="margin-top: 0.3rem;">
                                    <div class="progress-bar" style="width: <?php echo $percent; ?>%;"></div>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Investment History -->
        <div class="card">
            <h2 class="card-title" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                <span style="display: flex; align-items: center; gap: 0.5rem;"><?php echo $icon_history; ?> Historique des Investissements</span>
                <span class="badge badge-finished" style="font-size: 0.82rem; padding: 0.3rem 0.75rem;"><?php echo count($history_investments); ?> Terminés</span>
            </h2>

            <?php if (empty($history_investments)): ?>
                <div class="empty-state">
                    <p>Aucun investissement terminé dans votre historique.</p>
                </div>
            <?php else: ?>
                <div class="history-plans-grid">
                    <?php foreach ($history_investments as $i => $inv): 
                        $gain = round($inv['montant'] * ($inv['pourcentage'] / 100), 2);
                        $payout = $inv['montant'] + $gain;
                        $tier = get_plan_tier_info($i, $inv['plan_nom'], $inv['niveau'] ?? '');
                        $img = !empty($inv['plan_image']) ? $inv['plan_image'] : 'assets/images/bijou_bronze.png';
                    ?>
                        <div class="active-plan-card" style="border-color: var(--border-color); opacity: 0.95;">
                            
                            <!-- Header: Image + Plan Info -->
                            <div class="ap-header">
                                <div class="ap-img-wrap" style="border-color: <?php echo $tier['border']; ?>;">
                                    <img src="<?php echo e($img); ?>" alt="<?php echo e($inv['plan_nom']); ?>">
                                </div>
                                <div class="ap-info">
                                    <span class="ap-tier-badge" style="color: <?php echo $tier['border']; ?>; background: <?php echo $tier['badge_bg']; ?>;">
                                        <?php echo $tier['badge']; ?>
                                    </span>
                                    <h3 class="ap-title">
                                        <?php if (isset($inv['quantity']) && $inv['quantity'] > 1): ?>
                                            <span style="color:var(--primary); font-size:0.85em;"><?php echo (int)$inv['quantity']; ?>×</span>
                                        <?php endif; ?>
                                        <?php echo e($inv['plan_nom']); ?>
                                    </h3>
                                    <span style="font-size: 0.78rem; color: var(--success); font-weight: 700;">Terminé &amp; Crédité</span>
                                </div>
                            </div>

                            <!-- Financial Metrics -->
                            <div class="ap-metrics">
                                <div class="ap-metric-row">
                                    <span style="color: var(--text-muted);">Mise Initiale :</span>
                                    <span><?php echo format_money($inv['montant']); ?></span>
                                </div>
                                <div class="ap-metric-row">
                                    <span style="color: var(--text-muted);">Profit (+<?php echo $inv['pourcentage']; ?>%) :</span>
                                    <span style="color: var(--success); font-weight: 600;">+<?php echo format_money($gain); ?></span>
                                </div>
                                <div class="ap-metric-row">
                                    <span style="color: var(--text-muted);">Total Remboursé :</span>
                                    <strong style="color: var(--success);"><?php echo format_money($payout); ?></strong>
                                </div>
                            </div>

                            <div style="font-size: 0.78rem; color: var(--text-muted); text-align: right;">
                                Clôturé le : <strong><?php echo date('d/m/Y H:i', strtotime($inv['end_time'])); ?></strong>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. TAB: PARRAINAGE / AFFILIATION -->
    <div id="tab-parrainage" class="tab-content">
        
        <!-- Hero & Share Card -->
        <div class="ref-hero-card">
            <div class="ref-hero-header">
                <div class="ref-hero-icon"><?php echo $icon_users; ?></div>
                <div>
                    <h2 class="ref-hero-title">Programme de Parrainage & Affiliation</h2>
                    <p class="ref-hero-sub">Invitez de nouveaux membres et percevez jusqu'à <?php echo (int)($ref_lvl1 + $ref_lvl2 + $ref_lvl3); ?>% de commissions sur 3 niveaux d'affiliation.</p>
                </div>
            </div>

            <!-- Referral Link Share Input -->
            <div class="ref-share-card">
                <label class="ref-share-label">Votre lien d'affiliation personnel</label>
                <div class="ref-link-group">
                    <div class="ref-input-wrapper">
                        <svg class="ref-link-icon" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        <input type="text" id="refLinkInput" class="ref-link-input" value="<?php echo e($ref_link); ?>" readonly>
                    </div>
                    <button class="btn-ref-copy" onclick="copyReferralLink()">
                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.2" fill="none"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <span>Copier</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Quick Stats Grid -->
        <div class="ref-stats-grid">
            <div class="ref-stat-card">
                <div class="ref-stat-icon">👥</div>
                <div>
                    <span class="ref-stat-label">Filleuls Directs (Niveau 1)</span>
                    <strong class="ref-stat-val"><?php echo (int)$stats['referrals_count']; ?></strong>
                </div>
            </div>
            <div class="ref-stat-card">
                <div class="ref-stat-icon">💎</div>
                <div>
                    <span class="ref-stat-label">Commissions Accumulées</span>
                    <strong class="ref-stat-val accent"><?php echo format_money($stats['total_ref_bonus']); ?></strong>
                </div>
            </div>
        </div>

        <!-- Explanation Section: How it works -->
        <div class="card ref-explanation-card" style="margin-bottom: 1.25rem;">
            <h3 class="ref-section-title">
                <span>💡</span> Comment fonctionne le Parrainage ?
            </h3>
            
            <!-- 3 Levels Cards Grid -->
            <div class="ref-levels-grid">
                <div class="ref-level-card lvl-1">
                    <span class="ref-badge-lvl">Niveau 1</span>
                    <div class="ref-rate"><?php echo (float)$ref_lvl1; ?>%</div>
                    <div class="ref-lvl-name">Filleuls Directs</div>
                    <p class="ref-lvl-desc">Personnes qui s'inscrivent directement via votre lien personnel.</p>
                </div>

                <div class="ref-level-card lvl-2">
                    <span class="ref-badge-lvl">Niveau 2</span>
                    <div class="ref-rate"><?php echo (float)$ref_lvl2; ?>%</div>
                    <div class="ref-lvl-name">Filleuls Indirects</div>
                    <p class="ref-lvl-desc">Membres invités par vos filleuls directs de Niveau 1.</p>
                </div>

                <div class="ref-level-card lvl-3">
                    <span class="ref-badge-lvl">Niveau 3</span>
                    <div class="ref-rate"><?php echo (float)$ref_lvl3; ?>%</div>
                    <div class="ref-lvl-name">Réseau Étendu</div>
                    <p class="ref-lvl-desc">Membres invités par vos filleuls du Niveau 2.</p>
                </div>
            </div>

            <div class="ref-note-box">
                <span style="font-size: 1.1rem; flex-shrink: 0;">ℹ️</span>
                <span>Les commissions de parrainage sont générées automatiquement et versées sur votre solde dès qu'un filleul active un plan d'investissement.</span>
            </div>
        </div>

        <!-- History Table -->
        <div class="card">
            <h3 class="ref-section-title">
                <span>🎁</span> Historique des Commissions
            </h3>
            <?php if (empty($referral_bonuses)): ?>
                <div class="empty-state">
                    <p>Aucune commission reçue pour le moment. Partagez votre lien pour commencer à percevoir des gains !</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="ref-table">
                        <thead>
                            <tr>
                                <th>Filleul</th>
                                <th>Niveau</th>
                                <th>Date d'attribution</th>
                                <th style="text-align: right;">Montant Bonus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($referral_bonuses as $bonus): ?>
                                <tr>
                                    <td>
                                        <div class="ref-user-cell">
                                            <span class="ref-user-avatar"><?php echo mb_strtoupper(mb_substr($bonus['from_user_nom'], 0, 1)); ?></span>
                                            <strong><?php echo e($bonus['from_user_nom']); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-lvl-pill lvl-<?php echo (int)$bonus['level']; ?>">
                                            Niveau <?php echo (int)$bonus['level']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($bonus['created_at'])); ?></td>
                                    <td style="text-align: right; color: var(--success); font-weight: 700;">+<?php echo format_money($bonus['amount']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- 4. TAB: MON COMPTE & RETRAIT -->
    <div id="tab-compte" class="tab-content">
        <!-- Profile header card -->
        <div class="profile-hero-card">
            <div class="profile-avatar"><?php echo mb_strtoupper(mb_substr($user['nom'], 0, 1)); ?></div>
            <div class="profile-hero-info">
                <h2><?php echo e($user['nom']); ?></h2>
                <p><?php echo e($user['telephone'] ?: $user['email']); ?></p>
                <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-approved' : 'badge-finished'; ?>"><?php echo $user['role'] === 'admin' ? 'Admin' : 'Membre'; ?></span>
            </div>
            <a href="edit_profile.php" class="btn-edit-profile"><?php echo $icon_edit; ?> Modifier</a>
        </div>
        
        <!-- Stats cards inside Account Tab -->
        <div class="stats-grid" style="margin-bottom: 2rem;">
            <div class="stat-card">
                <span class="stat-label">Solde Disponible</span>
                <span class="stat-value highlight"><?php echo format_money($user['solde']); ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Total Investi</span>
                <span class="stat-value"><?php echo format_money($stats['total_invested']); ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Gains Totaux</span>
                <span class="stat-value" style="color: var(--success);"><?php echo format_money($stats['total_earned']); ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Commissions</span>
                <span class="stat-value" style="color: var(--accent);"><?php echo format_money($stats['total_ref_bonus']); ?></span>
            </div>
        </div>

        <!-- Deposit + Withdraw Action Buttons -->
        <div style="display: flex; gap: 0.8rem; margin-bottom: 1.5rem;">

            <!-- Dépôt -->
            <a href="deposit.php" style="text-decoration: none; flex: 1;">
                <div style="display: flex; align-items: center; gap: 0.6rem; padding: 0.7rem 1rem; border-radius: 50px; border: 1.5px solid var(--success); background: var(--success-bg); cursor: pointer; transition: var(--transition);">
                    <div style="width: 32px; height: 32px; flex-shrink:0; border-radius: 50%; background: linear-gradient(135deg, var(--success), #00b0ff); display: flex; align-items: center; justify-content: center;">
                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="#000" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </div>
                    <div>
                        <div style="font-size:0.88rem; font-weight:700; color:var(--text-primary);">Dépôt</div>
                        <div style="font-size:0.72rem; color:var(--text-muted);">Min. <?php echo number_format($depot_min, 0, ',', ' '); ?> F</div>
                    </div>
                </div>
            </a>

            <!-- Retrait -->
            <a href="withdraw.php" style="text-decoration: none; flex: 1;">
                <div style="display: flex; align-items: center; gap: 0.6rem; padding: 0.7rem 1rem; border-radius: 50px; border: 1.5px solid var(--primary); background: rgba(255,193,7,0.07); cursor: pointer; transition: var(--transition);">
                    <div style="width: 32px; height: 32px; flex-shrink:0; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center;">
                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="#000" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                    </div>
                    <div>
                        <div style="font-size:0.88rem; font-weight:700; color:var(--text-primary);">Retrait</div>
                        <div style="font-size:0.72rem; color:var(--text-muted);">Min. <?php echo number_format($retrait_min, 0, ',', ' '); ?> F</div>
                    </div>
                </div>
            </a>

        </div>

        <!-- Theme Toggle Card -->
        <div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.2rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <span style="font-size: 1.2rem;">🎨</span>
                    <div>
                        <div style="font-size: 0.95rem; font-weight: 600; color: var(--text-primary);">Apparence</div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary);">Thème Clair / Sombre</div>
                    </div>
                </div>
                <!-- Theme Toggle Button SVG -->
                <button class="theme-toggle-btn" onclick="toggleSiteTheme()" title="Changer le thème" aria-label="Changer le thème">
                  <svg class="icon-sun" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                  <svg class="icon-moon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
            </div>
        </div>


        <!-- Withdrawal History -->
        <div class="card">
            <h2 class="card-title" style="display: flex; align-items: center; gap: 0.5rem;"><?php echo $icon_history; ?> Historique des Retraits</h2>
            <?php if (empty($withdrawals)): ?>
                <div class="empty-state">
                    <p>Aucun retrait pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr><th>Date</th><th>Montant</th><th>Statut</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($withdrawals as $w): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($w['created_at'])); ?></td>
                                    <td style="font-weight:600;"><?php echo format_money($w['montant']); ?></td>
                                    <td>
                                        <?php if ($w['status'] === 'pending'): ?>
                                            <span class="badge badge-pending">En attente</span>
                                        <?php elseif ($w['status'] === 'approved'): ?>
                                            <span class="badge badge-approved">Validé</span>
                                        <?php else: ?>
                                            <span class="badge badge-rejected">Refusé</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Logout button -->
        <div style="text-align:center; margin: 2rem 0 1rem;">
            <a href="logout.php" class="btn-logout" style="display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;"><?php echo $icon_logout; ?> Se Déconnecter</a>
        </div>

    </div>

</div>

<!-- Mobile Navigation / Tab Bar -->
<div class="mobile-tab-bar">
    <button class="tab-btn active" data-tab="tab-accueil">
        <span class="tab-icon"><?php echo $icon_diamond; ?></span>
        <span class="tab-label">Boutique</span>
    </button>
    <button class="tab-btn" data-tab="tab-plans">
        <span class="tab-icon"><?php echo $icon_shield; ?></span>
        <span class="tab-label">Mes Plans</span>
    </button>
    <button class="tab-btn" data-tab="tab-parrainage">
        <span class="tab-icon"><?php echo $icon_users; ?></span>
        <span class="tab-label">Filiation</span>
    </button>
    <button class="tab-btn" data-tab="tab-compte">
        <span class="tab-icon"><?php echo $icon_user; ?></span>
        <span class="tab-label">Compte</span>
    </button>
</div>

<!-- Quantity Investment Modal -->
<div id="confirmModal" class="custom-modal">
    <div class="custom-modal-content" style="max-width:440px;">

        <!-- Header -->
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.2rem;">
            <h3 class="custom-modal-title" style="margin:0; display:flex; align-items:center; gap:0.5rem;">
                <?php echo $icon_diamond; ?>
                <span id="modalPlanName">Investissement</span>
            </h3>
            <button id="modalCancelBtn" style="background:none;border:none;cursor:pointer;color:var(--text-muted);padding:4px;" aria-label="Fermer">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Live Recap -->
        <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border-color); border-radius:12px; overflow:hidden; margin-bottom:1.2rem;">
            <div style="display:flex; justify-content:space-between; padding:0.65rem 1rem; border-bottom:1px solid var(--border-color);">
                <span style="color:var(--text-muted); font-size:0.88rem;">Produit / Bijou</span>
                <strong id="recapUnit" style="font-size:0.88rem;">—</strong>
            </div>
            <div style="display:flex; justify-content:space-between; padding:0.65rem 1rem; border-bottom:1px solid var(--border-color);">
                <span style="color:var(--text-muted); font-size:0.88rem;">Montant souscrit</span>
                <strong id="recapCost" style="font-size:0.88rem; color:var(--primary);">—</strong>
            </div>
            <div style="display:flex; justify-content:space-between; padding:0.65rem 1rem; border-bottom:1px solid var(--border-color);">
                <span style="color:var(--text-muted); font-size:0.88rem;">Gains estimés</span>
                <strong id="recapGain" style="font-size:0.88rem; color:var(--success);">—</strong>
            </div>
            <div style="display:flex; justify-content:space-between; padding:0.75rem 1rem; background:rgba(245,158,11,0.06);">
                <span style="font-weight:600; font-size:0.9rem;">Retour total</span>
                <strong id="recapTotal" style="color:var(--primary); font-size:0.95rem;">—</strong>
            </div>
        </div>

        <!-- Solde warning -->
        <div id="modalSoldeWarn" style="display:none; background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.3); border-radius:8px; padding:0.6rem 0.9rem; margin-bottom:1rem; font-size:0.85rem; color:#f87171;">
            Solde insuffisant pour souscrire à ce produit.
        </div>

        <div class="custom-modal-actions">
            <button id="modalConfirmBtn" class="bc-btn" style="flex:1; justify-content:center;">Confirmer l'investissement</button>
        </div>
    </div>
</div>

<script src="assets/js/main.js"></script>
<script>
    /* ── Plan layout toggle ────────────────────────────── */
    function setLayout(mode) {
        var c   = document.getElementById('plans-container');
        var bL  = document.getElementById('btn-list');
        var bG  = document.getElementById('btn-grid');
        if (!c) return;
        if (mode === 'grid') {
            c.classList.replace('mode-list', 'mode-grid');
            bL && bL.classList.remove('active');
            bG && bG.classList.add('active');
        } else {
            c.classList.replace('mode-grid', 'mode-list');
            bG && bG.classList.remove('active');
            bL && bL.classList.add('active');
        }
        try { localStorage.setItem('planLayout', mode); } catch(e) {}
    }
    /* Restore saved preference on load (default = list) */
    (function() {
        var saved = 'list';
        try { saved = localStorage.getItem('planLayout') || 'list'; } catch(e) {}
        setLayout(saved);
    })();

    /* ── Theme switcher (Sombre / Clair) ────────────────────── */
    function toggleSiteTheme() {
        var isLight = document.body.classList.toggle('theme-light');
        try { localStorage.setItem('userThemeChoice', isLight ? 'light' : 'dark'); } catch(e) {}
    }

    /* ── Investment Modal Logic ─────────────────────────────────── */
    let formToSubmit = null;
    let currentQty   = 1;
    let planPrix     = 0;
    let planPct      = 0;
    let planSolde    = 0;

    function fmtMoney(n) {
        return n.toLocaleString('fr-FR', { maximumFractionDigits: 0 }) + '\u00a0FCFA';
    }

    function updateModalRecap() {
        var warn   = document.getElementById('modalSoldeWarn');
        var btnOk  = document.getElementById('modalConfirmBtn');
        var totalCost  = planPrix;
        var gain       = planPrix * (planPct / 100);
        var totalRet   = totalCost + gain;

        document.getElementById('recapUnit').textContent  = fmtMoney(planPrix);
        document.getElementById('recapCost').textContent  = fmtMoney(totalCost);
        document.getElementById('recapGain').textContent  = '+' + fmtMoney(gain);
        document.getElementById('recapTotal').textContent = fmtMoney(totalRet);

        var insufficient = totalCost > planSolde;
        if (warn)  warn.style.display  = insufficient ? 'block' : 'none';
        if (btnOk) btnOk.disabled      = insufficient;
        if (btnOk) btnOk.style.opacity = insufficient ? '0.4' : '1';
    }

    function showInvestmentConfirm(event, formElement) {
        event.preventDefault();
        formToSubmit = formElement;

        planPrix   = parseFloat(formElement.dataset.prix)   || 0;
        planPct    = parseFloat(formElement.dataset.pct)    || 0;
        planSolde  = parseFloat(formElement.dataset.solde)  || 0;
        var nom    = formElement.dataset.nom || 'ce plan';

        document.getElementById('modalPlanName').textContent = nom;

        updateModalRecap();

        var modal = document.getElementById('confirmModal');
        if (modal) modal.classList.add('show');
    }

    document.addEventListener('DOMContentLoaded', function() {
        var modal     = document.getElementById('confirmModal');
        var confirmBtn = document.getElementById('modalConfirmBtn');
        var cancelBtn  = document.getElementById('modalCancelBtn');

        function closeModal() {
            if (modal) modal.classList.remove('show');
            formToSubmit = null;
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                if (!formToSubmit || confirmBtn.disabled) return;
                var qInput = formToSubmit.querySelector('[name="quantity"]');
                if (qInput) qInput.value = 1;
                var targetForm = formToSubmit;
                closeModal();
                targetForm.submit();
            });
        }

        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });
        }
    });

    function copyReferralLink() {
        var copyText = document.getElementById("refLinkInput");
        if (!copyText) return;
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value).then(function() {
            if (window.BijouxToast) {
                window.BijouxToast.show('success', 'Lien de parrainage copié dans le presse-papiers !');
            }
        }).catch(function() {
            // Fallback for older browsers
            document.execCommand('copy');
            if (window.BijouxToast) {
                window.BijouxToast.show('success', 'Lien de parrainage copié !');
            }
        });
    }
</script>
<script src="assets/js/toast.js"></script>
</body>
</html>
