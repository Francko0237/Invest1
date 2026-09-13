<?php
// ============================================================
// index.php — Landing Page (BijouxInvest / Invest1)
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// If user is already logged in, redirect to dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit();
}

// Fetch public statistics
$stats = [
    'membres' => 0,
    'volume' => 0,
    'nb_plans' => 0,
    'gains_distribues' => 0
];

try {
    $rowMembres = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
    $rowVolume  = $db->query("SELECT COALESCE(SUM(montant),0) FROM investments")->fetchColumn();
    $rowPlans   = $db->query("SELECT COUNT(*) FROM plans WHERE statut=1")->fetchColumn();
    $rowGains   = $db->query("SELECT COALESCE(SUM(montant * (1 + (SELECT pourcentage FROM plans WHERE id=investments.plan_id)/100)),0) FROM investments WHERE status='finished'")->fetchColumn();

    $stats['membres']          = (int)$rowMembres;
    $stats['volume']           = (float)$rowVolume;
    $stats['nb_plans']         = (int)$rowPlans;
    $stats['gains_distribues'] = (float)$rowGains;
} catch (Exception $e) {
    // Fallback if queries fail
}

// Fetch featured plans
$plans = [];
try {
    $stmtPlans = $db->query("SELECT * FROM plans WHERE statut=1 ORDER BY prix ASC LIMIT 3");
    $plans = $stmtPlans->fetchAll();
} catch (Exception $e) {}

$plan_colors_land = [
    ['border'=>'#cd7f32', 'badge'=>'Bronze'],
    ['border'=>'#94a3b8', 'badge'=>'Argent'],
    ['border'=>'#f59e0b', 'badge'=>'Or'],
];

$page_title = 'Investissez intelligemment — BijouxInvest';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="BijouxInvest — Plateforme d'investissement dans des bijoux d'exception avec gains automatiques et parrainage 3 niveaux.">
  <title><?php echo e($page_title); ?></title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23f59e0b' stroke-width='2'><path d='M6 3h12l4 6-10 12L2 9z'/><path d='M11 3 8 9l3 12 3-12-3-6z'/><path d='M2 9h20'/></svg>">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    /* ── Landing Nav & Theme Bar ── */
    .land-nav {
      position: sticky; top: 0; z-index: 100;
      background: var(--bg-panel);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border-color);
      padding: 0 2rem;
      height: 68px;
      display: flex; align-items: center; justify-content: space-between;
    }
    .land-brand {
      font-family: 'Inter', sans-serif;
      font-size: 1.4rem;
      font-weight: 700;
      color: var(--text-primary);
      display: flex;
      align-items: center;
      gap: .6rem;
      text-decoration: none;
    }
    .land-brand .gem-icon {
      font-size: 1.6rem;
      filter: drop-shadow(0 0 8px #f59e0b);
    }
    .land-actions {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    /* ── Hero section ── */
    .hero {
      min-height: 88vh;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 4rem 1.5rem;
      position: relative;
      overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(ellipse 70% 50% at 50% -5%, var(--primary-glow) 0%, transparent 70%),
        radial-gradient(ellipse 50% 40% at 80% 90%, rgba(245,158,11,0.08) 0%, transparent 60%);
      pointer-events: none;
    }
    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      background: var(--primary-glow);
      border: 1px solid var(--primary);
      border-radius: 50px;
      padding: .4rem 1.1rem;
      font-size: .82rem;
      color: var(--primary);
      font-weight: 600;
      margin-bottom: 1.75rem;
    }
    .hero-title {
      font-size: clamp(2.4rem, 6vw, 4.2rem);
      font-weight: 800;
      line-height: 1.15;
      margin-bottom: 1.5rem;
      background: linear-gradient(135deg, var(--text-primary) 30%, var(--primary) 65%, #f59e0b 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .hero-sub {
      font-size: 1.15rem;
      color: var(--text-secondary);
      max-width: 620px;
      margin: 0 auto 2.5rem;
      line-height: 1.7;
    }
    .hero-cta {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap;
    }
    .hero-gem {
      font-size: clamp(5rem, 12vw, 8.5rem);
      display: block;
      margin-bottom: 1.5rem;
      filter: drop-shadow(0 0 40px rgba(245,158,11,.4));
      animation: float 4s ease-in-out infinite;
    }
    @keyframes float {
      0%,100% { transform: translateY(0); }
      50%      { transform: translateY(-14px); }
    }

    /* ── Stats bar ── */
    .stats-bar {
      background: var(--bg-card);
      border-top: 1px solid var(--border-color);
      border-bottom: 1px solid var(--border-color);
      padding: 2.5rem 0;
    }
    .stats-bar-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      text-align: center;
    }
    .stat-num {
      font-size: 2rem;
      font-weight: 800;
      color: var(--text-primary);
    }
    .stat-lbl { font-size: .85rem; color: var(--text-muted); margin-top: .2rem; }

    /* ── Sections ── */
    .section { padding: 5rem 0; }
    .section-title {
      font-size: 2rem;
      font-weight: 700;
      text-align: center;
      margin-bottom: .75rem;
      color: var(--text-primary);
    }
    .section-sub { text-align: center; color: var(--text-muted); margin-bottom: 3rem; font-size: 1rem; }

    /* ── How it works ── */
    .steps-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 1.5rem;
    }
    .step-card {
      text-align: center;
      padding: 2rem 1.5rem;
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      border-radius: var(--border-radius);
    }
    .step-num {
      width: 52px; height: 52px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), #f59e0b);
      color: #fff;
      font-weight: 800;
      font-size: 1.2rem;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1.25rem;
      box-shadow: 0 4px 20px var(--primary-glow);
    }
    .step-icon { font-size: 2.5rem; margin-bottom: .75rem; }

    /* ── Referral visual ── */
    .ref-level {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.25rem;
      background: var(--bg-card);
      border-radius: var(--border-radius-sm);
      margin-bottom: .75rem;
      border: 1px solid var(--border-color);
    }
    .ref-pct {
      font-size: 1.6rem;
      font-weight: 800;
      min-width: 75px;
      color: var(--primary);
    }

    /* ── Grid plans landing ── */
    .landing-plans-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem;
    }

    /* Buttons */
    .btn-land-primary {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff;
      padding: 0.75rem 1.75rem;
      border-radius: var(--border-radius-sm);
      font-weight: 600;
      text-decoration: none;
      box-shadow: 0 4px 15px var(--primary-glow);
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    .btn-land-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px var(--primary-glow);
    }
    .btn-land-outline {
      border: 1px solid var(--border-color);
      color: var(--text-primary);
      padding: 0.75rem 1.75rem;
      border-radius: var(--border-radius-sm);
      font-weight: 600;
      text-decoration: none;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    .btn-land-outline:hover {
      background: var(--primary-glow);
      border-color: var(--primary);
    }

    /* Mobile centering & responsiveness fixes */
    @media (max-width: 768px) {
      .land-nav {
        padding: 0 1rem;
        height: auto;
        min-height: 60px;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: space-between;
      }
      .land-brand {
        font-size: 1.15rem;
      }
      .land-actions {
        gap: 0.5rem;
      }
      .land-actions .btn-land-outline,
      .land-actions .btn-land-primary {
        padding: 0.4rem 0.75rem !important;
        font-size: 0.8rem !important;
      }
      .hero {
        padding: 2.5rem 1rem;
        min-height: auto;
      }
      .hero-title {
        font-size: 1.8rem;
        word-break: break-word;
      }
      .hero-sub {
        font-size: 0.95rem;
        padding: 0 0.5rem;
      }
      .hero-gem {
        font-size: 4.5rem;
      }
      .hero-cta {
        flex-direction: column;
        width: 100%;
        max-width: 320px;
        margin: 0 auto;
      }
      .hero-cta a {
        width: 100%;
        justify-content: center;
      }
      .stats-bar {
        padding: 1.5rem 1rem;
      }
      .stats-bar-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
      }
      .stat-num {
        font-size: 1.4rem;
        word-break: break-word;
      }
      .section {
        padding: 3rem 1rem;
      }
      .section-title {
        font-size: 1.5rem;
      }
      .ref-level {
        flex-direction: column;
        text-align: center;
        padding: 1rem;
      }
      .ref-pct {
        min-width: auto;
      }
    }
  </style>
</head>
<body class="<?php echo get_theme_class(); ?>">
<?php render_theme_script(); ?>

<!-- Navigation bar -->
<header class="land-nav">
  <a href="index.php" class="land-brand">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--primary);"><path d="M6 3h12l4 6-10 12L2 9z"/><path d="M11 3 8 9l3 12 3-12-3-6z"/><path d="M2 9h20"/></svg>
    <span>BijouxInvest</span>
  </a>

  <div class="land-actions">
    <!-- Theme Toggle — Sun/Moon SVG -->
    <button class="theme-toggle-btn" onclick="toggleSiteTheme()" title="Changer le thème" aria-label="Changer le thème">
      <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="5"/>
        <line x1="12" y1="1" x2="12" y2="3"/>
        <line x1="12" y1="21" x2="12" y2="23"/>
        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
        <line x1="1" y1="12" x2="3" y2="12"/>
        <line x1="21" y1="12" x2="23" y2="12"/>
        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
      </svg>
      <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
      </svg>
    </button>

    <a href="login.php" class="btn-land-outline" style="padding: 0.5rem 1.1rem; font-size: 0.9rem;">Connexion</a>
    <a href="register.php" class="btn-land-primary" style="padding: 0.5rem 1.1rem; font-size: 0.9rem;">S'inscrire</a>
  </div>
</header>

<!-- Hero section -->
<section class="hero">
  <div style="max-width: 900px; width: 100%; margin: 0 auto; padding: 0 1rem; text-align: center;">
    <div class="hero-gem">💎</div>
    <div class="hero-badge">✨ Plateforme d'Investissement Bijoux N°1</div>
    <h1 class="hero-title">Faites Fructifier Votre Capital<br>Dans des Plans d'Exception</h1>
    <p class="hero-sub">
      Découvrez nos coffres de bijoux exclusifs. Investissez en toute sécurité et générez des rendements attractifs avec retraits simplifiés.
    </p>
    <div class="hero-cta">
      <a href="register.php" class="btn-land-primary" style="padding:0.9rem 2.2rem; font-size:1.05rem; display:inline-flex; align-items:center; gap:0.5rem;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Commencer Maintenant
      </a>
      <a href="#plans" class="btn-land-outline" style="padding:0.9rem 2.2rem; font-size:1.05rem;">
        Découvrir les Plans
      </a>
    </div>
  </div>
</section>

<!-- Stats Bar -->
<section class="stats-bar">
  <div style="max-width:1200px; margin:0 auto; padding:0 1.5rem;">
    <div class="stats-bar-grid">
      <div>
        <div class="stat-num"><?php echo number_format($stats['membres']); ?>+</div>
        <div class="stat-lbl">Membres Actifs</div>
      </div>
      <div>
        <div class="stat-num"><?php echo format_money($stats['volume']); ?></div>
        <div class="stat-lbl">Volume Investi</div>
      </div>
      <div>
        <div class="stat-num"><?php echo format_money($stats['gains_distribues']); ?></div>
        <div class="stat-lbl">Gains Distribués</div>
      </div>
      <div>
        <div class="stat-num">100%</div>
        <div class="stat-lbl">Transparence & Sécurité</div>
      </div>
    </div>
  </div>
</section>

<!-- Section Plans -->
<section class="section" id="plans">
  <div style="max-width:1200px; margin:0 auto; padding:0 1.5rem;">
    <h2 class="section-title">Nos Plans d'Investissement Phares</h2>
    <p class="section-sub">Choisissez la formule qui convient le mieux à vos ambitions financières</p>

    <div class="landing-plans-grid">
      <?php if (!empty($plans)): ?>
        <?php foreach ($plans as $i => $plan):
          $img = !empty($plan['image']) ? $plan['image'] : 'assets/images/bijou_bronze.png';
          $tier = get_plan_tier_info($i, $plan['nom'], $plan['niveau'] ?? '');
          $gain = round($plan['prix'] * $plan['pourcentage'] / 100, 2);
          $retour_total = $plan['prix'] + $gain;
          $duree = $plan['duree_valeur'] . ' ' . $plan['duree_type'];
        ?>
        <div class="boutique-card" style="border-color: <?php echo $tier['border']; ?>; box-shadow: 0 4px 20px <?php echo $tier['glow']; ?>;">
          
          <!-- Showcase Image Box -->
          <div class="bc-img-wrap">
            <img src="<?php echo e($img); ?>" alt="<?php echo e($plan['nom']); ?>">
            <span class="bc-badge" style="border-color: <?php echo $tier['border']; ?>; color: <?php echo $tier['border']; ?>; background: <?php echo $tier['badge_bg']; ?>;">
              <?php echo $tier['badge']; ?>
            </span>
            <span class="bc-price-pill">
              <?php echo format_money($plan['prix']); ?>
            </span>
          </div>

          <!-- Card Body -->
          <div class="bc-body">
            <div class="bc-main-info">
              <h3 class="bc-title"><?php echo e($plan['nom']); ?></h3>
            </div>

            <!-- Metrics panel -->
            <div class="bc-metrics">
              <div class="bc-metric-row">
                <span class="bc-label">Profit :</span>
                <strong class="bc-val-success">+<?php echo $plan['pourcentage']; ?>% (+<?php echo format_money($gain); ?>)</strong>
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

            <!-- Action Button -->
            <div class="bc-action">
              <a href="login.php" class="bc-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 12L2 9z"/><path d="M11 3 8 9l3 12 3-12-3-6z"/><path d="M2 9h20"/></svg>
                Investir Maintenant
              </a>
            </div>
          </div>

        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="text-align:center; grid-column: 1/-1; color:var(--text-muted);">Aucun plan affiché pour le moment.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Section Comment ça marche -->
<section class="section" style="background:var(--bg-panel); border-top:1px solid var(--border-color); border-bottom:1px solid var(--border-color);">
  <div style="max-width:1200px; margin:0 auto; padding:0 1.5rem;">
    <h2 class="section-title">Comment Ça Marche ?</h2>
    <p class="section-sub">3 étapes simples pour démarrer et percevoir vos premiers gains</p>

    <div class="steps-grid">
      <div class="step-card">
        <div class="step-num">1</div>
        <div class="step-icon" style="color:var(--primary);">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <h3 style="margin-bottom:0.5rem; color:var(--text-primary);">Créez un Compte</h3>
        <p style="font-size:0.9rem; color:var(--text-muted);">Inscrivez-vous en 30 secondes avec votre numéro de téléphone sans vérification complexe.</p>
      </div>

      <div class="step-card">
        <div class="step-num">2</div>
        <div class="step-icon" style="color:var(--primary);">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 12L2 9z"/><path d="M11 3 8 9l3 12 3-12-3-6z"/><path d="M2 9h20"/></svg>
        </div>
        <h3 style="margin-bottom:0.5rem; color:var(--text-primary);">Choisissez un Plan</h3>
        <p style="font-size:0.9rem; color:var(--text-muted);">Sélectionnez un bijou selon votre budget (de 100 FCFA à 10 000 FCFA) et validez.</p>
      </div>

      <div class="step-card">
        <div class="step-num">3</div>
        <div class="step-icon" style="color:var(--primary);">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <h3 style="margin-bottom:0.5rem; color:var(--text-primary);">Récoltez vos Gains</h3>
        <p style="font-size:0.9rem; color:var(--text-muted);">À l'échéance de votre plan, vos gains et le capital sont automatiquement crédités sur votre solde.</p>
      </div>
    </div>
  </div>
</section>

<!-- Section Parrainage -->
<section class="section">
  <div style="max-width:1000px; margin:0 auto; padding:0 1.5rem;">
    <h2 class="section-title">👥 Programme de Parrainage Multi-Niveaux</h2>
    <p class="section-sub">Invitez vos amis et gagnez des commissions sur 3 niveaux d'affiliation</p>

    <div>
      <div class="ref-level">
        <div class="ref-pct">10%</div>
        <div>
          <strong style="color:var(--text-primary);">Niveau 1 — Filleuls Directs</strong>
          <div style="font-size:0.85rem; color:var(--text-muted);">Chaque fois qu'un ami inscrit avec votre lien investit, recevez 10% cash immédiat.</div>
        </div>
      </div>

      <div class="ref-level">
        <div class="ref-pct" style="color:var(--secondary);">5%</div>
        <div>
          <strong style="color:var(--text-primary);">Niveau 2 — Filleuls de Niveau 2</strong>
          <div style="font-size:0.85rem; color:var(--text-muted);">Gagnez 5% sur les investissements des invités de vos filleuls.</div>
        </div>
      </div>

      <div class="ref-level">
        <div class="ref-pct" style="color:var(--accent);">2%</div>
        <div>
          <strong style="color:var(--text-primary);">Niveau 3 — Filleuls de Niveau 3</strong>
          <div style="font-size:0.85rem; color:var(--text-muted);">Percevez encore 2% de commission sur le 3ème niveau.</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer style="text-align:center; padding:2rem 1.5rem; border-top:1px solid var(--border-color); color:var(--text-muted); font-size:0.85rem;">
  <p>© <?php echo date('Y'); ?> BijouxInvest. Tous droits réservés.</p>
</footer>

<script>
function toggleSiteTheme() {
  var isLight = document.body.classList.toggle('theme-light');
  try { localStorage.setItem('userThemeChoice', isLight ? 'light' : 'dark'); } catch(e) {}
}
</script>
<script src="assets/js/toast.js"></script>
</body>
</html>
