<?php require_once '../config/session.php'; require_page_access('preventive'); ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Maintenance preventive - GMAO ONDA</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php render_navbar('preventive'); ?>
<main class="page">
  <div class="page-header">Maintenance preventive</div>
  <section class="filtres-box">
    <div class="filtre-row">
      <span class="filtre-label">Du</span><input class="preventive-filter" id="p-du" type="date" value="2015-04-01">
      <select class="preventive-filter" id="p-service"><option value="">Tous les services</option><option>ESU</option></select>
      <select class="preventive-filter" id="p-famille"><option value="">Familles d'equipements</option><option>Electronique</option><option>Informatique</option><option>Balisage</option><option>Telecoms</option><option>Rayon X</option><option>Detecteurs</option></select>
      <select class="preventive-filter" id="p-type"><option value="">Nature maintenance</option><option>Entretien</option><option>Etalonnage</option><option>Controles reglementaires</option></select>
      <select class="preventive-filter" id="p-etat"><option value="">Etat</option><option>Realise</option><option>En attente</option></select>
    </div>
    <div class="filtre-row">
      <span class="filtre-label">Au</span><input class="preventive-filter" id="p-au" type="date" value="2026-04-30">
      <input class="preventive-filter" id="p-equipement" type="text" placeholder="Equipement">
      <select class="preventive-filter" id="p-periodicite"><option value="">Periodicite</option><option>Annuelle</option><option>Semestrielle</option><option>Trimestrielle</option><option>Mensuelle</option><option>Hebdomadaire</option></select>
      <label><input class="preventive-filter" id="p-nuit" type="checkbox"> a realiser la nuit</label>
      <button class="btn-chercher" onclick="chargerPreventive()">🔍 Chercher</button>
      <button class="btn-secondary" onclick="resetPreventive()">🔄 Reset</button>
    </div>
  </section>
  <div class="table-header"><strong><span id="preventiveCount">0</span> elements trouves</strong><button class="btn-primary" onclick="toggleVue()">Planification</button></div>
  <div id="vueListe" class="table-wrap">
    <table><thead><tr><th></th><th>Equipement / Famille</th><th>AP/Service</th><th>Type</th><th>P</th><th>Date prevue</th><th>Etat</th><th>Date realisation</th><th>Details</th><th>Cloturer</th></tr></thead><tbody id="preventiveBody"></tbody></table>
  </div>
  <section id="vueIndicateurs" class="cards-row" style="display:none">
    <article class="card-indicateur"><div class="card-indicateur-header">Planification</div><div class="card-indicateur-body">Actions planifiees: <strong id="planifiees">0</strong><br>Actions en attente: <strong id="attente">0</strong></div></article>
    <article class="card-indicateur"><div class="card-indicateur-header">Realisation du planning</div><div class="card-indicateur-body">Actions realisees: <strong id="realisees">0</strong><br>Taux de realisation: <strong id="realisation">0%</strong></div></article>
    <article class="card-indicateur"><div class="card-indicateur-header">Respect du planning</div><div class="card-indicateur-body">Actions realisees a temps: <strong id="atemps">0</strong><br>Taux du respect: <strong id="respect">0%</strong></div></article>
  </section>
</main>
<script src="../assets/js/main.js"></script><script src="../assets/js/preventive.js"></script>
</body>
</html>
