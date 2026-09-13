<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';

require_login();
require_admin();

// Auto-migrate schema: ensure 'niveau' column exists in 'plans' table
try {
    $db->exec("ALTER TABLE plans ADD COLUMN niveau VARCHAR(50) DEFAULT 'BRONZE'");
} catch (Exception $e) {
    // Column already exists
}

// Handle Actions (Add & Edit via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Jeton de sécurité invalide.');
    } else {
        $action       = $_POST['action'];
        $nom          = clean_input($_POST['nom']);
        $niveau       = clean_input($_POST['niveau'] ?? 'BRONZE');
        $prix         = (float)$_POST['prix'];
        $pourcentage  = (float)$_POST['pourcentage'];
        $duree_valeur = (int)$_POST['duree_valeur'];
        $duree_type   = clean_input($_POST['duree_type']);
        $statut       = isset($_POST['statut']) ? 1 : 0;

        if (empty($nom) || $prix <= 0 || $pourcentage <= 0 || $duree_valeur <= 0) {
            set_flash_message('danger', 'Veuillez remplir tous les champs obligatoires avec des valeurs supérieures à 0.');
        } elseif ($duree_type !== 'jours' && $duree_type !== 'mois') {
            set_flash_message('danger', 'Type de durée invalide.');
        } else {
            // Handle file upload if provided
            $image_path = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['image']['tmp_name'];
                $file_name = $_FILES['image']['name'];
                $file_size = $_FILES['image']['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (!in_array($file_ext, $allowed_extensions)) {
                    set_flash_message('danger', 'Format d\'image invalide (jpg, jpeg, png, gif, webp).');
                    header('Location: plans.php');
                    exit();
                }

                if ($file_size > 5 * 1024 * 1024) {
                    set_flash_message('danger', 'L\'image ne doit pas dépasser 5 Mo.');
                    header('Location: plans.php');
                    exit();
                }

                $new_filename = 'plan_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
                $dest_path = __DIR__ . '/../uploads/plans/' . $new_filename;

                if (move_uploaded_file($file_tmp, $dest_path)) {
                    $image_path = 'uploads/plans/' . $new_filename;
                } else {
                    set_flash_message('danger', 'Erreur lors du transfert de l\'image.');
                    header('Location: plans.php');
                    exit();
                }
            }

            if ($action === 'add') {
                if (!$image_path) {
                    set_flash_message('danger', 'Veuillez sélectionner une image pour le plan.');
                    header('Location: plans.php');
                    exit();
                }

                try {
                    $stmt = $db->prepare("INSERT INTO plans (nom, niveau, prix, pourcentage, duree_valeur, duree_type, statut, image) 
                                         VALUES (:nom, :niveau, :prix, :pourcentage, :duree_valeur, :duree_type, :statut, :image)");
                    $stmt->execute([
                        'nom'          => $nom,
                        'niveau'       => $niveau,
                        'prix'         => $prix,
                        'pourcentage'  => $pourcentage,
                        'duree_valeur' => $duree_valeur,
                        'duree_type'   => $duree_type,
                        'statut'       => $statut,
                        'image'        => $image_path
                    ]);
                    set_flash_message('success', 'Le plan d\'investissement a été créé avec succès.');
                    header('Location: plans.php');
                    exit();
                } catch (Exception $e) {
                    error_log("Failed to add plan: " . $e->getMessage());
                    set_flash_message('danger', 'Erreur lors de la création du plan.');
                }
            } elseif ($action === 'edit') {
                $plan_id = (int)$_POST['plan_id'];
                if ($plan_id <= 0) {
                    set_flash_message('danger', 'Identifiant de plan invalide.');
                } else {
                    try {
                        if ($image_path) {
                            $stmt = $db->prepare("UPDATE plans SET nom = :nom, niveau = :niveau, prix = :prix, pourcentage = :pourcentage, 
                                                 duree_valeur = :duree_valeur, duree_type = :duree_type, statut = :statut, image = :image 
                                                 WHERE id = :id");
                            $stmt->execute([
                                'nom'          => $nom,
                                'niveau'       => $niveau,
                                'prix'         => $prix,
                                'pourcentage'  => $pourcentage,
                                'duree_valeur' => $duree_valeur,
                                'duree_type'   => $duree_type,
                                'statut'       => $statut,
                                'image'        => $image_path,
                                'id'           => $plan_id
                            ]);
                        } else {
                            $stmt = $db->prepare("UPDATE plans SET nom = :nom, niveau = :niveau, prix = :prix, pourcentage = :pourcentage, 
                                                 duree_valeur = :duree_valeur, duree_type = :duree_type, statut = :statut 
                                                 WHERE id = :id");
                            $stmt->execute([
                                'nom'          => $nom,
                                'niveau'       => $niveau,
                                'prix'         => $prix,
                                'pourcentage'  => $pourcentage,
                                'duree_valeur' => $duree_valeur,
                                'duree_type'   => $duree_type,
                                'statut'       => $statut,
                                'id'           => $plan_id
                            ]);
                        }
                        set_flash_message('success', 'Le plan d\'investissement a été mis à jour avec succès.');
                        header('Location: plans.php');
                        exit();
                    } catch (Exception $e) {
                        error_log("Failed to update plan: " . $e->getMessage());
                        set_flash_message('danger', 'Erreur lors de la mise à jour du plan.');
                    }
                }
            }
        }
    }
}

// Handle GET Actions (Toggle / Delete)
if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['csrf_token'])) {
    if (!verify_csrf_token($_GET['csrf_token'])) {
        set_flash_message('danger', 'Jeton de sécurité invalide.');
    } else {
        $id = (int)$_GET['id'];
        if ($_GET['action'] === 'toggle') {
            try {
                $stmt = $db->prepare("UPDATE plans SET statut = 1 - statut WHERE id = :id");
                $stmt->execute(['id' => $id]);
                set_flash_message('success', 'Statut du plan mis à jour.');
            } catch (Exception $e) {
                error_log("Toggle plan failed: " . $e->getMessage());
                set_flash_message('danger', 'Erreur lors du changement de statut.');
            }
        } elseif ($_GET['action'] === 'delete') {
            try {
                $chk = $db->prepare("SELECT COUNT(*) FROM investments WHERE plan_id = :id AND status = 'active'");
                $chk->execute(['id' => $id]);
                if ($chk->fetchColumn() > 0) {
                    set_flash_message('danger', 'Impossible de supprimer ce plan : des investissements actifs y sont rattachés.');
                } else {
                    $stmt = $db->prepare("DELETE FROM plans WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    set_flash_message('success', 'Plan supprimé avec succès.');
                }
            } catch (Exception $e) {
                error_log("Delete plan failed: " . $e->getMessage());
                set_flash_message('danger', 'Erreur lors de la suppression du plan.');
            }
        }
        header('Location: plans.php');
        exit();
    }
}

// Check if currently editing a plan
$edit_plan = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmtE = $db->prepare("SELECT * FROM plans WHERE id = :id");
    $stmtE->execute(['id' => $edit_id]);
    $edit_plan = $stmtE->fetch();
}

// Fetch all plans
$stmtPlans = $db->query("SELECT * FROM plans ORDER BY prix ASC");
$plans = $stmtPlans->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Gestion des Plans</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="<?php echo get_theme_class(); ?>">
<?php render_theme_script(); ?>

<nav class="navbar admin-navbar">
    <a href="dashboard.php" class="nav-brand">BijouxInvest - Admin</a>
    <div class="nav-links">
        <a href="dashboard.php" class="nav-link">Dashboard</a>
        <a href="plans.php" class="nav-link active">Gestion Plans</a>
        <a href="users.php" class="nav-link">Gestion Membres</a>
        <a href="withdrawals.php" class="nav-link">Retraits</a>
        <a href="settings.php" class="nav-link">Configuration</a>
        <a href="../dashboard.php" class="nav-btn-outline">Espace Client</a>
        <a href="../logout.php" class="nav-btn-outline" style="border-color: var(--danger); color: var(--danger);">Déconnexion</a>
    </div>
</nav>

<div class="container">
    
    <?php display_flash_messages(); ?>

    <div class="grid-2col">
        <!-- Left Side: Plans List -->
        <div>
            <div class="card">
                <h2 class="card-title">Plans d'Investissement Existants</h2>
                <?php if (empty($plans)): ?>
                    <div class="empty-state">
                        <p>Aucun plan créé pour le moment.</p>
                    </div>
                <?php else: ?>
                    <!-- ── Desktop Table ───────────────────────────────── -->
                    <div class="table-responsive desktop-only" style="border: none;">
                        <table style="background: transparent;">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Nom & Niveau</th>
                                    <th>Prix</th>
                                    <th>Gain</th>
                                    <th>Durée</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plans as $i => $p): 
                                    $tier = get_plan_tier_info($i, $p['nom'], $p['niveau'] ?? '');
                                ?>
                                    <tr <?php echo ($edit_plan && $edit_plan['id'] == $p['id']) ? 'style="background: rgba(0, 242, 254, 0.08);"' : ''; ?>>
                                        <td>
                                            <?php if ($p['image']): ?>
                                                <img src="../<?php echo e($p['image']); ?>" style="width:44px;height:44px;border-radius:8px;object-fit:cover;border:1px solid <?php echo $tier['border']; ?>;" alt="">
                                            <?php else: ?>
                                                <span style="display:inline-flex;color:var(--primary);"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h12l4 6-10 12L2 9z"/><path d="M11 3 8 9l3 12 3-12-3-6z"/><path d="M2 9h20"/></svg></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo e($p['nom']); ?></strong><br>
                                            <span style="font-size:0.75rem; color:<?php echo $tier['border']; ?>; font-weight:700;">
                                                <?php echo $tier['badge']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo format_money($p['prix']); ?></td>
                                        <td style="color: var(--success); font-weight: 600;">+<?php echo e($p['pourcentage']); ?>%</td>
                                        <td><?php echo e($p['duree_valeur']); ?> <?php echo e($p['duree_type']); ?></td>
                                        <td>
                                            <?php if ($p['statut'] == 1): ?>
                                                <span class="badge badge-approved">Actif</span>
                                            <?php else: ?>
                                                <span class="badge badge-rejected">Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="actions-cell">
                                                <a href="plans.php?edit_id=<?php echo $p['id']; ?>" class="btn-admin-action" style="background: rgba(2,132,199,0.15); border-color: #0284c7; color: #38bdf8;">
                                                    Modifier
                                                </a>
                                                <a href="plans.php?action=toggle&id=<?php echo $p['id']; ?>&csrf_token=<?php echo e(csrf_token()); ?>" class="btn-admin-action">
                                                    <?php echo $p['statut'] == 1 ? 'Désactiver' : 'Activer'; ?>
                                                </a>
                                                <a href="plans.php?action=delete&id=<?php echo $p['id']; ?>&csrf_token=<?php echo e(csrf_token()); ?>" class="btn-admin-action-danger" onclick="return confirm('Voulez-vous vraiment supprimer ce plan ?');">
                                                    Supprimer
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- ── Mobile Cards ────────────────────────────────── -->
                    <div class="mobile-only" style="display:flex;flex-direction:column;gap:1rem;margin-top:0.5rem;">
                        <?php foreach ($plans as $i => $p): 
                            $tier = get_plan_tier_info($i, $p['nom'], $p['niveau'] ?? '');
                        ?>
                            <div style="background:rgba(255,255,255,0.03);border:1px solid <?php echo $tier['border']; ?>;border-radius:var(--border-radius);overflow:hidden;">
                                <?php if ($p['image']): ?>
                                    <div style="width:100%;height:140px;overflow:hidden;position:relative;">
                                        <img src="../<?php echo e($p['image']); ?>" style="width:100%;height:100%;object-fit:cover;" alt="<?php echo e($p['nom']); ?>">
                                        <span style="position:absolute;top:8px;left:8px;background:rgba(0,0,0,0.8);color:<?php echo $tier['border']; ?>;border:1px solid <?php echo $tier['border']; ?>;padding:0.2rem 0.6rem;border-radius:20px;font-size:0.75rem;font-weight:700;">
                                            <?php echo $tier['badge']; ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div style="width:100%;height:80px;display:flex;align-items:center;justify-content:center;color:var(--primary);background:rgba(255,255,255,0.04);">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h12l4 6-10 12L2 9z"/><path d="M11 3 8 9l3 12 3-12-3-6z"/><path d="M2 9h20"/></svg>
                                    </div>
                                <?php endif; ?>
                                <div style="padding:0.9rem;">
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                                        <h3 style="margin:0;font-size:1.05rem;color:var(--text-primary);"><?php echo e($p['nom']); ?></h3>
                                        <?php if ($p['statut'] == 1): ?>
                                            <span class="badge badge-approved">Actif</span>
                                        <?php else: ?>
                                            <span class="badge badge-rejected">Inactif</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:0.88rem;color:var(--text-secondary);display:flex;flex-wrap:wrap;gap:0.4rem 1rem;">
                                        <span>Prix : <strong style="color:var(--primary);"><?php echo format_money($p['prix']); ?></strong></span>
                                        <span>Profit : <strong style="color:var(--success);">+<?php echo e($p['pourcentage']); ?>%</strong></span>
                                        <span>Durée : <?php echo e($p['duree_valeur']); ?> <?php echo e($p['duree_type']); ?></span>
                                    </div>
                                    <div class="actions-cell" style="margin-top:0.8rem;border-top:1px solid rgba(255,255,255,0.06);padding-top:0.7rem;">
                                        <a href="plans.php?edit_id=<?php echo $p['id']; ?>" class="btn-admin-action" style="background: rgba(2,132,199,0.15); border-color: #0284c7; color: #38bdf8;">
                                            Modifier
                                        </a>
                                        <a href="plans.php?action=toggle&id=<?php echo $p['id']; ?>&csrf_token=<?php echo e(csrf_token()); ?>" class="btn-admin-action">
                                            <?php echo $p['statut'] == 1 ? 'Désactiver' : 'Activer'; ?>
                                        </a>
                                        <a href="plans.php?action=delete&id=<?php echo $p['id']; ?>&csrf_token=<?php echo e(csrf_token()); ?>" class="btn-admin-action-danger" onclick="return confirm('Voulez-vous vraiment supprimer ce plan ?');">
                                            Supprimer
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Side: Add or Edit Plan Form -->
        <div>
            <div class="card">
                <h2 class="card-title">
                    <?php echo $edit_plan ? 'Modifier le Plan : ' . e($edit_plan['nom']) : 'Nouveau Plan ("Bijou")'; ?>
                </h2>
                <form action="plans.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="<?php echo $edit_plan ? 'edit' : 'add'; ?>">
                    <?php if ($edit_plan): ?>
                        <input type="hidden" name="plan_id" value="<?php echo $edit_plan['id']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="nom" class="form-label">Nom du plan *</label>
                        <input type="text" id="nom" name="nom" class="form-control" placeholder="Ex: Bague Rubis" value="<?php echo $edit_plan ? e($edit_plan['nom']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="niveau" class="form-label">Niveau / Tier du plan *</label>
                        <select id="niveau" name="niveau" class="form-control" style="background: rgba(14, 18, 27, 0.8);" required>
                            <?php 
                                $cur_niveau = $edit_plan ? strtoupper($edit_plan['niveau'] ?? 'BRONZE') : 'BRONZE'; 
                            ?>
                            <option value="BRONZE" <?php echo ($cur_niveau === 'BRONZE') ? 'selected' : ''; ?>>BRONZE</option>
                            <option value="ARGENT" <?php echo ($cur_niveau === 'ARGENT') ? 'selected' : ''; ?>>ARGENT</option>
                            <option value="OR" <?php echo ($cur_niveau === 'OR') ? 'selected' : ''; ?>>OR</option>
                            <option value="DIAMANT" <?php echo ($cur_niveau === 'DIAMANT') ? 'selected' : ''; ?>>DIAMANT</option>
                            <option value="RUBIS" <?php echo ($cur_niveau === 'RUBIS') ? 'selected' : ''; ?>>RUBIS</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="image" class="form-label">Image du plan (Bijou) <?php echo $edit_plan ? '<span style="color:var(--text-muted);">(optionnel)</span>' : '*'; ?></label>
                        <input type="file" id="image" name="image" class="form-control" accept="image/*" <?php echo $edit_plan ? '' : 'required'; ?>>
                        <?php if ($edit_plan && !empty($edit_plan['image'])): ?>
                            <div style="margin-top:0.5rem; display:flex; align-items:center; gap:0.5rem;">
                                <span style="font-size:0.8rem; color:var(--text-muted);">Image actuelle :</span>
                                <img src="../<?php echo e($edit_plan['image']); ?>" style="width:36px; height:36px; border-radius:6px; object-fit:cover;" alt="">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="prix" class="form-label">Prix (FCFA) *</label>
                        <input type="number" step="0.01" min="0.01" id="prix" name="prix" class="form-control" placeholder="Ex: 100.00" value="<?php echo $edit_plan ? e($edit_plan['prix']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="pourcentage" class="form-label">Pourcentage de gain (%) *</label>
                        <input type="number" step="0.1" min="0.1" id="pourcentage" name="pourcentage" class="form-control" placeholder="Ex: 15.0" value="<?php echo $edit_plan ? e($edit_plan['pourcentage']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="duree_valeur" class="form-label">Valeur de la Durée *</label>
                        <input type="number" min="1" id="duree_valeur" name="duree_valeur" class="form-control" placeholder="Ex: 7" value="<?php echo $edit_plan ? e($edit_plan['duree_valeur']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="duree_type" class="form-label">Type de la Durée *</label>
                        <select id="duree_type" name="duree_type" class="form-control" style="background: rgba(14, 18, 27, 0.8);" required>
                            <option value="jours" <?php echo ($edit_plan && $edit_plan['duree_type'] === 'jours') ? 'selected' : ''; ?>>Jours</option>
                            <option value="mois" <?php echo ($edit_plan && $edit_plan['duree_type'] === 'mois') ? 'selected' : ''; ?>>Mois</option>
                        </select>
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 1.5rem;">
                        <input type="checkbox" id="statut" name="statut" <?php echo (!$edit_plan || $edit_plan['statut'] == 1) ? 'checked' : ''; ?> style="width: auto;">
                        <label for="statut" class="form-label" style="margin-bottom: 0; cursor: pointer;">Actif (visible dans le catalogue)</label>
                    </div>

                    <div style="display:flex; gap:0.5rem; margin-top:1.5rem;">
                        <button type="submit" class="btn-submit" style="flex:1; margin-top:0;">
                            <?php echo $edit_plan ? 'Mettre à jour le Plan' : 'Créer le Plan'; ?>
                        </button>
                        <?php if ($edit_plan): ?>
                            <a href="plans.php" class="btn-cancel" style="padding:0.6rem 1rem; text-decoration:none; display:flex; align-items:center; justify-content:center;">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<footer class="site-footer">
    <p>&copy; 2026 BijouxInvest Admin. Tous droits réservés.</p>
</footer>

<script src="../assets/js/toast.js"></script>
</body>
</html>
