<?php require_once '../config/session.php'; require_page_access('utilisation'); ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Utilisation - GMAO ONDA</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body data-page="utilisation">
<?php render_navbar('utilisation'); ?>
<main class="page">
  <div class="page-header">Utilisation des equipements</div>
  <div class="chart-box"><canvas id="statsChart"></canvas></div>
  <div class="table-wrap"><table><thead><tr><th>Equipement</th><th>Nb interventions</th><th>Heures d'utilisation</th><th>Taux %</th></tr></thead><tbody id="statsBody"></tbody></table></div>
</main>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script src="../assets/js/main.js"></script><script src="../assets/js/stats.js"></script>
</body>
</html>
