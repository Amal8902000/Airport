<?php require_once '../config/session.php'; require_page_access('accueil'); ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Accueil - GMAO ONDA</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php render_navbar('accueil'); ?>
<main class="page">
  <div class="page-header">GMAO Med - Tableau de bord</div>
  <?php if (isset($_GET['denied'])): ?>
    <section class="filtres-box" style="border-color:#e67e22;color:#9a5a12">Votre role ne donne pas acces a ce module.</section>
  <?php endif; ?>
  <section class="cards-row">
    <article class="card-indicateur"><div class="card-indicateur-header">Total equipements</div><div class="card-indicateur-body"><div class="big-number" id="totalEquipements">0</div></div></article>
    <article class="card-indicateur"><div class="card-indicateur-header">Pannes ouvertes</div><div class="card-indicateur-body"><div class="big-number" id="pannesOuvertes">0</div></div></article>
    <article class="card-indicateur"><div class="card-indicateur-header">Taux de disponibilite</div><div class="card-indicateur-body"><div class="big-number" id="tauxDispo">0%</div></div></article>
    <article class="card-indicateur"><div class="card-indicateur-header">Preventives en attente</div><div class="card-indicateur-body"><div class="big-number" id="prevAttente">0</div></div></article>
  </section>
  <section class="shortcuts">
    <?php foreach (role_pages(current_user()['role'] ?? '') as $key => $page): ?>
      <?php if ($key !== 'accueil'): ?>
        <a class="shortcut" href="<?= e($page['url']) ?>"><strong><?= e($page['label']) ?></strong><?= e($page['description']) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </section>
</main>
<script src="../assets/js/main.js"></script>
<script>
  Promise.all([
    fetchJSON('../api/equipements.php?action=liste'),
    fetchJSON('../api/corrective.php?action=stats'),
    fetchJSON('../api/preventive.php?action=stats'),
    fetchJSON('../api/stats.php?action=disponibilite')
  ]).then(([eq, co, pr, dis]) => {
    totalEquipements.textContent = eq.equipements.length;
    pannesOuvertes.textContent = co.stats.ouvert;
    prevAttente.textContent = pr.stats.attente;
    tauxDispo.textContent = `${dis.global}%`;
  }).catch((e) => showToast(e.message, 'error'));
</script>
</body>
</html>
