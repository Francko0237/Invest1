<?php
require_once __DIR__ . '/db.php';

/**
 * Checks and updates expired active investments.
 * Adds principal + gains to the user's balance and marks investment as 'finished'.
 * 
 * @param PDO $db
 * @param int|null $user_id If specified, only checks for this user.
 * @return int Number of processed investments.
 */
function check_and_update_investments($db, $user_id = null) {
    try {
        $sql = "SELECT i.id, i.user_id, i.montant, p.nom as plan_nom, p.pourcentage 
                FROM investments i 
                JOIN plans p ON i.plan_id = p.id 
                WHERE i.status = 'active' AND NOW() >= i.end_time";
        
        if ($user_id !== null) {
            $sql .= " AND i.user_id = :user_id";
        }
        
        $stmt = $db->prepare($sql);
        if ($user_id !== null) {
            $stmt->execute(['user_id' => $user_id]);
        } else {
            $stmt->execute();
        }
        
        $expired = $stmt->fetchAll();
        if (empty($expired)) {
            return 0;
        }
        
        $processed = 0;
        foreach ($expired as $inv) {
            $db->beginTransaction();
            try {
                $gain = $inv['montant'] * ($inv['pourcentage'] / 100);
                $total_payout = $inv['montant'] + $gain;
                
                // 1. Credit the user's balance
                $updUser = $db->prepare("UPDATE users SET solde = solde + :payout WHERE id = :user_id");
                $updUser->execute([
                    'payout' => $total_payout,
                    'user_id' => $inv['user_id']
                ]);
                
                // 2. Mark investment as finished
                $updInv = $db->prepare("UPDATE investments SET status = 'finished' WHERE id = :inv_id");
                $updInv->execute([
                    'inv_id' => $inv['id']
                ]);
                
                $db->commit();
                $processed++;
                
                // Log activity or message
                if ($user_id !== null) {
                    set_flash_message('success', "Votre investissement dans le plan '" . $inv['plan_nom'] . "' de " . format_money($inv['montant']) . " est terminé ! Gains crédités : " . format_money($total_payout));
                }
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error processing investment ID {$inv['id']}: " . $e->getMessage());
            }
        }
        return $processed;
    } catch (Exception $e) {
        error_log("check_and_update_investments error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Distributes referral commissions up to 3 levels.
 * 
 * @param PDO $db
 * @param int $user_id The investor ID
 * @param float $investment_amount Amount invested
 */
function distribute_referral_commissions($db, $user_id, $investment_amount) {
    try {
        // Fetch commission rates
        $rates = [];
        $stmt = $db->query("SELECT key_name, key_value FROM settings WHERE key_name IN ('ref_level_1_percent', 'ref_level_2_percent', 'ref_level_3_percent')");
        while ($row = $stmt->fetch()) {
            $rates[$row['key_name']] = (float)$row['key_value'];
        }
        
        $current_user_id = $user_id;
        $visited = [$user_id]; // Cycle prevention
        
        for ($level = 1; $level <= 3; $level++) {
            // Get parrain of current user
            $stmtUser = $db->prepare("SELECT parrain_id, nom FROM users WHERE id = :id");
            $stmtUser->execute(['id' => $current_user_id]);
            $user = $stmtUser->fetch();
            
            if (!$user || empty($user['parrain_id'])) {
                break; // No parent
            }
            
            $referrer_id = $user['parrain_id'];
            
            // Cycle prevention check
            if (in_array($referrer_id, $visited)) {
                break;
            }
            $visited[] = $referrer_id;
            
            // Fetch level percentage
            $rate_key = "ref_level_{$level}_percent";
            $percent = isset($rates[$rate_key]) ? $rates[$rate_key] : 0;
            
            if ($percent > 0) {
                $bonus = $investment_amount * ($percent / 100);
                
                $db->beginTransaction();
                try {
                    // 1. Credit referrer balance
                    $upd = $db->prepare("UPDATE users SET solde = solde + :bonus WHERE id = :ref_id");
                    $upd->execute(['bonus' => $bonus, 'ref_id' => $referrer_id]);
                    
                    // 2. Insert bonus record
                    $ins = $db->prepare("INSERT INTO referral_bonus (user_id, from_user_id, level, amount) VALUES (:user_id, :from_user_id, :level, :amount)");
                    $ins->execute([
                        'user_id' => $referrer_id,
                        'from_user_id' => $user_id,
                        'level' => $level,
                        'amount' => $bonus
                    ]);
                    
                    $db->commit();
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Referral distribution error at Level {$level} (user {$user_id} -> referrer {$referrer_id}): " . $e->getMessage());
                }
            }
            
            // Move up in tree
            $current_user_id = $referrer_id;
        }
    } catch (Exception $e) {
        error_log("distribute_referral_commissions top error: " . $e->getMessage());
    }
}

/**
 * Checks if a user has any active investments.
 * Used to lock withdrawal functionality.
 */
function has_active_investments($db, $user_id) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM investments WHERE user_id = :user_id AND status = 'active'");
    $stmt->execute(['user_id' => $user_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Generates a unique referral code.
 */
function generate_referral_code($db) {
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE code_parrain = :code");
        $stmt->execute(['code' => $code]);
        $exists = $stmt->fetchColumn() > 0;
    } while ($exists);
    
    return $code;
}

/**
 * Get user stats: total earnings, total invested, total referrals
 */
function get_user_stats($db, $user_id) {
    // Total invested
    $stmt = $db->prepare("SELECT SUM(montant) FROM investments WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $total_invested = (float)$stmt->fetchColumn();

    // Total earnings from finished investments
    $stmt = $db->prepare("SELECT SUM(i.montant * p.pourcentage / 100) FROM investments i 
                          JOIN plans p ON i.plan_id = p.id 
                          WHERE i.user_id = :user_id AND i.status = 'finished'");
    $stmt->execute(['user_id' => $user_id]);
    $total_earned = (float)$stmt->fetchColumn();

    // Total referral bonuses
    $stmt = $db->prepare("SELECT SUM(amount) FROM referral_bonus WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $total_ref_bonus = (float)$stmt->fetchColumn();

    // Total referrals count (level 1 only)
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE parrain_id = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $referrals_count = (int)$stmt->fetchColumn();

    return [
        'total_invested' => $total_invested,
        'total_earned'   => $total_earned,
        'total_ref_bonus'=> $total_ref_bonus,
        'referrals_count'=> $referrals_count
    ];
}

/**
 * Returns all site settings as an associative array.
 * Results are statically cached so the DB is only queried once per request.
 */
function get_all_settings() {
    global $db;
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $stmt = $db->query("SELECT key_name, key_value FROM settings");
        $cache = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cache[$row['key_name']] = $row['key_value'];
        }
    } catch (Exception $e) {
        $cache = [];
    }
    return $cache;
}

/**
 * Returns tier styling metadata (badge text, border color, glow color) for a plan based on its tier level, name, or index.
 */
function get_plan_tier_info($i, $nom = '', $niveau = '') {
    $themes = [
        'BRONZE'  => ['badge' => '🥉 BRONZE',  'border' => '#cd7f32', 'glow' => 'rgba(205, 127, 50, 0.25)', 'badge_bg' => 'rgba(205, 127, 50, 0.15)'],
        'ARGENT'  => ['badge' => '🥈 ARGENT',  'border' => '#c0c0c0', 'glow' => 'rgba(192, 192, 192, 0.25)', 'badge_bg' => 'rgba(192, 192, 192, 0.15)'],
        'OR'      => ['badge' => '🥇 OR',      'border' => '#ffd700', 'glow' => 'rgba(255, 215, 0, 0.25)', 'badge_bg' => 'rgba(255, 215, 0, 0.15)'],
        'DIAMANT' => ['badge' => '💎 DIAMANT', 'border' => '#38bdf8', 'glow' => 'rgba(56, 189, 248, 0.25)', 'badge_bg' => 'rgba(56, 189, 248, 0.15)'],
        'RUBIS'   => ['badge' => '👑 RUBIS',   'border' => '#f43f5e', 'glow' => 'rgba(244, 63, 94, 0.25)', 'badge_bg' => 'rgba(244, 63, 94, 0.15)'],
    ];

    $key = strtoupper(trim($niveau));
    if (!empty($key) && isset($themes[$key])) {
        return $themes[$key];
    }

    $lower = mb_strtolower($nom);
    if (strpos($lower, 'bronze') !== false) return $themes['BRONZE'];
    if (strpos($lower, 'argent') !== false) return $themes['ARGENT'];
    if (strpos($lower, 'or') !== false) return $themes['OR'];
    if (strpos($lower, 'diamant') !== false) return $themes['DIAMANT'];
    if (strpos($lower, 'rubis') !== false) return $themes['RUBIS'];

    $list = array_values($themes);
    return $list[$i % count($list)];
}


