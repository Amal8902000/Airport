<?php require_once '../config/session.php'; require_page_access('preventive'); ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Maintenance preventive - GMAO ONDA</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .indicateurs-row { display: flex; gap: 16px; padding: 20px; flex-wrap: wrap; }
    .indicateur-card { flex: 1; border-radius: 6px; overflow: hidden; border: 1px solid #ddd; min-width: 200px; }
    .indicateur-card-header { background: #1f618d; color: white; padding: 10px 16px; font-weight: bold; font-size: 14px; }
    .indicateur-card-body { padding: 16px; font-size: 14px; line-height: 2.2; background: white; }
    .indicateur-card-body strong { font-weight: 700; }
  </style>
</head>
<body>
<?php render_navbar('preventive'); ?>
<main class="page">
  <div class="page-header">Maintenance preventive</div>
  <section class="filtres-box">
    <div class="filtre-row">
      <span class="filtre-label">Du</span><input class="preventive-filter" id="p-du" type="date">
      <select class="preventive-filter" id="p-service"><option value="">ESU</option><option>ESU</option></select>
      <select class="preventive-filter" id="p-famille"><option value="">Familles d'equipements</option><option>Electronique</option><option>Informatique</option><option>Balisage</option><option>Telecoms</option><option>Rayon X</option><option>Detecteurs</option></select>
      <select class="preventive-filter" id="p-type"><option value="">Nature maintenance</option><option>Entretien</option><option>Etalonnage</option><option>Controles reglementaires</option></select>
      <select class="preventive-filter" id="p-etat"><option value="">Etat</option><option>Realise</option><option>En attente</option></select>
    </div>
    <div class="filtre-row">
      <span class="filtre-label">Au</span><input class="preventive-filter" id="p-au" type="date">
      <select class="preventive-filter" id="p-service2"><option value="">Tous les services</option><option>ESU</option></select>
      <input class="preventive-filter" id="p-equipement" type="text" placeholder="Equipement">
      <select class="preventive-filter" id="p-periodicite"><option value="">Periodicite</option><option>Hebdomadaire</option><option>Mensuelle</option><option>Trimestrielle</option><option>Semestrielle</option><option>Annuelle</option></select>
      <label><input class="preventive-filter" id="p-nuit" type="checkbox"> a realiser la nuit</label>
      <button class="btn-chercher" onclick="calculerPreventive()">📊 Calculer</button>
      <button class="btn-secondary" onclick="resetPreventive()">🔄 Reset</button>
    </div>
  </section>

  <section id="vueIndicateurs" class="indicateurs-row" style="display:none">
    <article class="indicateur-card"><div class="indicateur-card-header">Planification</div><div class="indicateur-card-body">Actions planifiees : <strong id="stat-planifiees">0</strong><br>Actions en attente : <strong id="stat-attente">0</strong></div></article>
    <article class="indicateur-card"><div class="indicateur-card-header">Realisation du planning</div><div class="indicateur-card-body">Actions realisees : <strong id="stat-realisees">0</strong><br>Taux de realisation du planning : <strong id="stat-taux-real">0.00 %</strong></div></article>
    <article class="indicateur-card"><div class="indicateur-card-header">Respect du planning</div><div class="indicateur-card-body">Actions realisees a temps : <strong id="stat-a-temps">0</strong><br>Taux du respect du planning : <strong id="stat-taux-respect">0.00 %</strong></div></article>
  </section>

  <div class="table-header"><strong><span id="preventiveCount">0</span> elements trouves</strong></div>
  <div id="vueListe" class="table-wrap">
    <table><thead><tr><th></th><th>Equipement / Famille</th><th>AP/Service</th><th>Type</th><th>Periodicite</th><th>Date prevue</th><th>Etat</th><th>Date realisation</th><th>Details</th><th>Cloturer</th></tr></thead><tbody id="preventiveBody"></tbody></table>
  </div>
</main>
<script src="../assets/js/main.js"></script><script src="../assets/js/preventive.js"></script>
</body>
</html>
