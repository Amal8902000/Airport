<?php require_once '../config/session.php'; require_page_access('utilisation'); ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Utilisation - GMAO ONDA</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .utilisation-title { background: var(--bleu-tableau); color: white; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; font-weight: bold; }
    .utilisation-table thead tr.groupes th { background: white; color: #1a5276; text-align: center; border-bottom: 2px solid #1f618d; }
    .utilisation-table thead tr.sous-colonnes th { background: #f8f9fa; text-align: center; }
    .utilisation-table td { text-align: center; }
    .utilisation-table tbody tr:hover { background: #eef6fb; }
    .trp-badge { background: #27ae60; color: white; padding: 3px 10px; border-radius: 12px; font-weight: bold; display: inline-block; min-width: 44px; }
  </style>
</head>
<body data-page="utilisation">
<?php render_navbar('utilisation'); ?>
<main class="page">
  <div class="page-header">Suivi de l'utilisation du module Maintenance</div>
  <section class="filtres-box">
    <div class="filtre-row">
      <span class="filtre-label">Du</span><input id="u-du" type="date">
      <span class="filtre-label">Au</span><input id="u-au" type="date">
      <button class="btn-chercher" onclick="chargerUtilisation()">🔍 Chercher</button>
    </div>
  </section>

  <div class="utilisation-title">
    <span>Suivi de l'utilisation du module Maintenance</span>
    <span>👥 Utilisateurs du module</span>
  </div>
  <div class="table-wrap">
    <table class="utilisation-table">
      <thead>
        <tr class="groupes">
          <th rowspan="2">#</th>
          <th colspan="2">Aéroport</th>
          <th colspan="2">Utilisateurs</th>
          <th colspan="3">Anomalies signalées</th>
          <th colspan="3">Interventions</th>
          <th colspan="3">Actions préventives</th>
        </tr>
        <tr class="sous-colonnes">
          <th>Cat.</th><th>Aéroport</th>
          <th>Nombre</th><th>Admin</th>
          <th>Zones</th><th>Equipements</th><th>Anomalies</th>
          <th>Correctives</th><th>Préventives</th><th>Total</th>
          <th>Planifiées</th><th>Réalisées</th><th>TRP</th>
        </tr>
      </thead>
      <tbody id="utilisationBody"></tbody>
    </table>
  </div>
</main>
<script src="../assets/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const now = new Date();
  document.getElementById('u-du').value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
  document.getElementById('u-au').value = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);
  chargerUtilisation();
});

async function chargerUtilisation() {
  const params = new URLSearchParams({
    action: 'utilisation',
    du: document.getElementById('u-du').value,
    au: document.getElementById('u-au').value,
  });
  const data = await fetchJSON(`${API_BASE}stats.php?${params}`);
  renderTableauUtilisation(data.rows || []);
}

function renderTableauUtilisation(data) {
  document.getElementById('utilisationBody').innerHTML = data.map((row, index) => `
    <tr>
      <td>${index + 1}</td>
      <td>${cell(row.categorie)}</td>
      <td>${cell(row.aeroport)}</td>
      <td>${numberCell(row.nb_users)}</td>
      <td>${numberCell(row.nb_admins)}</td>
      <td>${numberCell(row.nb_zones)}</td>
      <td>${numberCell(row.nb_equipements)}</td>
      <td>${numberCell(row.nb_anomalies)}</td>
      <td>${numberCell(row.nb_correctives)}</td>
      <td>${numberCell(row.nb_preventives)}</td>
      <td>${numberCell(row.total_interventions)}</td>
      <td>${numberCell(row.planifiees)}</td>
      <td>${numberCell(row.realisees)}</td>
      <td>${row.trp === null || row.trp === undefined ? '' : `<span class="trp-badge">${Number(row.trp)} %</span>`}</td>
    </tr>
  `).join('');
}

function cell(value) {
  return escapeHtml(value ?? '');
}

function numberCell(value) {
  return Number(value || 0);
}
</script>
</body>
</html>
