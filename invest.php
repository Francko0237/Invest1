<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php#tab-accueil');
    exit();
}

// 1. Verify CSRF Token
if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    set_flash_message('danger', 'Jeton de sécurité invalide. Action annulée.');
    header('Location: dashboard.php#tab-accueil');
    exit();
}

$user_id = $_SESSION['user_id'];
$plan_id  = isset($_POST['plan_id'])  ? (int)$_POST['plan_id']  : 0;
// Fixed quantity: Exactly 1 product per investment
$quantity = 1;

if ($plan_id <= 0) {
    set_flash_message('danger', 'Plan d\'investissement invalide.');
    header('Location: dashboard.php#tab-accueil');
    exit();
}

try {
    // 2. Fetch plan details
    $stmtPlan = $db->prepare("SELECT * FROM plans WHERE id = :id AND statut = 1");
    $stmtPlan->execute(['id' => $plan_id]);
    $plan = $stmtPlan->fetch();

    if (!$plan) {
        set_flash_message('danger', 'Plan d\'investissement introuvable ou inactif.');
        header('Location: dashboard.php#tab-accueil');
        exit();
    }

    // Check if user already has an active investment on this specific product
    $stmtCheckActive = $db->prepare("SELECT COUNT(*) FROM investments WHERE user_id = :user_id AND plan_id = :plan_id AND status = 'active'");
    $stmtCheckActive->execute(['user_id' => $user_id, 'plan_id' => $plan_id]);
    if ((int)$stmtCheckActive->fetchColumn() > 0) {
        set_flash_message('danger', 'Vous avez déjà un investissement en cours sur le produit "' . $plan['nom'] . '". Attendez son échéance pour pouvoir réinvestir dessus.');
        header('Location: dashboard.php#tab-accueil');
        exit();
    }

    $montant = (float)$plan['prix'];
    $total_montant = $montant * $quantity;

    // 3. Fetch user balance
    $stmtUser = $db->prepare("SELECT solde FROM users WHERE id = :id");
    $stmtUser->execute(['id' => $user_id]);
    $solde = (float)$stmtUser->fetchColumn();

    if ($solde < $total_montant) {
        set_flash_message('danger', 'Solde insuffisant pour acheter ' . $quantity . ' bijou(x).');
        header('Location: dashboard.php#tab-accueil');
        exit();
    }

    // 4. Determine SQL Interval Addition Expression for dynamic date calculation
    $duree_valeur = (int)$plan['duree_valeur'];
    if ($plan['duree_type'] === 'jours') {
        $end_time_expr = "DATE_ADD(NOW(), INTERVAL :valeur_jours DAY)";
    } elseif ($plan['duree_type'] === 'mois') {
        $end_time_expr = "DATE_ADD(NOW(), INTERVAL :valeur_mois MONTH)";
    } else {
        throw new Exception("Type de durée de plan inconnu: " . $plan['duree_type']);
    }

    // 5. Execute purchase in a transaction
    $db->beginTransaction();

    // Deduct user balance (total = prix × quantity)
    $updUser = $db->prepare("UPDATE users SET solde = solde - :montant WHERE id = :user_id");
    $updUser->execute(['montant' => $total_montant, 'user_id' => $user_id]);

    // Create investment (stores total montant + quantity)
    $sqlInsert = "INSERT INTO investments (user_id, plan_id, montant, quantity, start_time, end_time, status)
                  VALUES (:user_id, :plan_id, :montant, :quantity, NOW(), $end_time_expr, 'active')";
    
    $ins = $db->prepare($sqlInsert);
    $params = [
        'user_id'  => $user_id,
        'plan_id'  => $plan_id,
        'montant'  => $total_montant,
        'quantity' => $quantity,
    ];
    if ($plan['duree_type'] === 'jours') {
        $params['valeur_jours'] = $duree_valeur;
    } else {
        $params['valeur_mois'] = $duree_valeur;
    }
    $ins->execute($params);

    $db->commit();

    // 6. Distribute referral commission (based on total montant invested)
    distribute_referral_commissions($db, $user_id, $total_montant);

    $qty_label = $quantity > 1 ? $quantity . '× ' : '';
    set_flash_message('success', 'Investissement de ' . $qty_label . format_money($total_montant) . ' dans le plan "' . $plan['nom'] . '" enregistré avec succès !');
    header('Location: dashboard.php#tab-plans');
    exit();

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Investment failed: " . $e->getMessage());
    set_flash_message('danger', 'Une erreur s\'est produite lors de l\'enregistrement de votre investissement. Veuillez réessayer.');
    header('Location: dashboard.php#tab-accueil');
    exit();
}
