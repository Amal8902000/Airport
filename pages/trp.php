<?php require_once '../config/session.php'; require_page_access('trp'); ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TRP - GMAO ONDA</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body data-page="trp">
<?php render_navbar('trp'); ?>
<main class="page">
  <div class="page-header">TRP - Taux de Rendement du Parc</div>
  <section class="filtres-box"><div class="filtre-row"><span>Du</span><input type="date"><span>Au</span><input type="date"><select><option>Famille</option></select><button class="btn-chercher" onclick="chargerTRP()">Calculer</button></div></section>
  <section class="cards-row">
    <article class="card-indicateur"><div class="card-indicateur-header">Disponibilite</div><div class="card-indicateur-body"><strong id="disponibilite">0%</strong></div></article>
    <article class="card-indicateur"><div class="card-indicateur-header">Performance</div><div class="card-indicateur-body"><strong id="performance">0%</strong></div></article>
    <article class="card-indicateur"><div class="card-indicateur-header">Qualite</div><div class="card-indicateur-body"><strong id="qualite">0%</strong></div></article>
    <article class="card-indicateur"><div class="card-indicateur-header">TRP</div><div class="card-indicateur-body"><strong id="trp">0%</strong></div></article>
  </section>
  <div class="chart-box"><canvas id="statsChart"></canvas></div>
</main>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="../assets/js/main.js"></script><script src="../assets/js/stats.js"></script>
</body>
</html>
