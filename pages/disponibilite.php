<?php require_once '../config/session.php'; require_page_access('disponibilite'); ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Disponibilite - GMAO ONDA</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body data-page="disponibilite">
<?php render_navbar('disponibilite'); ?>
<main class="page">
  <div class="page-header">Disponibilite des equipements</div>
  <section class="filtres-box"><div class="filtre-row"><span>Du</span><input type="date"><span>Au</span><input type="date"><select><option>Famille</option></select><select><option>Service</option><option>ESU</option></select><button class="btn-chercher" onclick="chargerDisponibilite()">Calculer</button></div></section>
  <section class="cards-row"><article class="card-indicateur"><div class="card-indicateur-header">Indicateur global</div><div class="card-indicateur-body">Taux de disponibilite = <strong id="globalDispo">0%</strong></div></article></section>
  <div class="chart-box"><canvas id="statsChart"></canvas></div>
  <div class="table-wrap"><table><thead><tr><th>Equipement</th><th>Famille</th><th>Temps en service</th><th>Pannes</th><th>Taux dispo %</th></tr></thead><tbody id="statsBody"></tbody></table></div>
</main>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="../assets/js/main.js"></script><script src="../assets/js/stats.js"></script>
</body>
</html>
