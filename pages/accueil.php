<?php
require_once '../config/session.php';
require_page_access('accueil');

$user = current_user();
$fullName = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
$displayName = $fullName ?: ($user['email'] ?? 'Utilisateur');
$modules = [
    ['icon' => '🏭', 'title' => 'Parc des équipements', 'url' => 'equipements.php'],
    ['icon' => '🛠️', 'title' => 'Maintenance préventive', 'url' => 'preventive.php'],
    ['icon' => '⚡', 'title' => 'Maintenance corrective', 'url' => 'corrective.php'],
    ['icon' => '📊', 'title' => 'Disponibilité', 'url' => 'disponibilite.php'],
    ['icon' => '📈', 'title' => 'TRP', 'url' => 'trp.php'],
    ['icon' => '🔧', 'title' => 'Utilisation', 'url' => 'utilisation.php'],
];
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Accueil - GMAO ONDA</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    /* === ACCUEIL MODULES === */
    .welcome-header {
      background: #1a5276;
      color: white;
      padding: 28px 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 20px;
      text-align: center;
    }

    .welcome-header img {
      height: 52px;
      width: auto;
      object-fit: contain;
    }

    .welcome-header h1 {
      font-size: 22px;
      font-weight: 600;
      margin: 0;
    }

    .welcome-header p {
      font-size: 14px;
      opacity: 0.85;
      margin: 4px 0 0 0;
    }

    .modules-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 28px;
      padding: 48px 80px;
      max-width: 1100px;
      margin: 0 auto;
    }

    .module-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      padding: 48px 24px 36px 24px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      border: 2px solid transparent;
    }

    .module-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.15);
      border-color: #2980b9;
    }

    .module-card .card-icon {
      font-size: 58px;
      margin-bottom: 20px;
      line-height: 1;
    }

    .module-card .card-title {
      font-size: 16px;
      font-weight: 600;
      color: #1a5276;
      text-align: center;
      letter-spacing: 0.3px;
    }

    @media (max-width: 768px) {
      .modules-grid { grid-template-columns: repeat(2, 1fr); padding: 24px; }
      .welcome-header { padding: 24px; }
    }

    @media (max-width: 480px) {
      .modules-grid { grid-template-columns: 1fr; }
      .welcome-header { flex-direction: column; }
    }
  </style>
</head>
<body>
<?php render_navbar('accueil'); ?>
<main class="page">
  <header class="welcome-header">
    <img src="../assets/img/logo-onda.png" alt="ONDA">
    <div>
      <h1>Bienvenue, <?= e($displayName) ?></h1>
      <p>GMAO — Office National Des Aéroports</p>
    </div>
  </header>

  <section class="modules-grid" aria-label="Modules GMAO">
    <?php foreach ($modules as $module): ?>
      <a class="module-card" href="<?= e($module['url']) ?>">
        <span class="card-icon" aria-hidden="true"><?= e($module['icon']) ?></span>
        <span class="card-title"><?= e($module['title']) ?></span>
      </a>
    <?php endforeach; ?>
  </section>
</main>
<script src="../assets/js/main.js"></script>
</body>
</html>
