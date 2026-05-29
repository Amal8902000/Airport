<?php require_once '../config/session.php'; require_page_access('disponibilite'); ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Disponibilite - GMAO ONDA</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    /* === DISPONIBILITE === */
    .progress-cell {
      display: flex;
      flex-direction: column;
      gap: 4px;
      min-width: 90px;
    }
    .progress-value {
      font-weight: bold;
      font-size: 13px;
    }
    .progress-bar-bg {
      background: #ecf0f1;
      border-radius: 10px;
      height: 6px;
      width: 100%;
      overflow: hidden;
    }
    .progress-bar-fill {
      height: 6px;
      border-radius: 10px;
      transition: width 0.4s ease;
    }
    .fill-vert { background: #27ae60; color: #27ae60; }
    .fill-orange { background: #e67e22; color: #e67e22; }
    .fill-rouge { background: #e74c3c; color: #e74c3c; }
    tr.critique { background: #fdf2f2; }
    .duree-hs { color: #e74c3c; font-weight: bold; }
    .duree-ok { color: #27ae60; }
    .indicateur-valeur { font-size: 24px; font-weight: bold; display: block; margin-top: 6px; }
  </style>
</head>
<body data-page="disponibilite">
<?php render_navbar('disponibilite'); ?>
<main class="page">
  <div class="page-header">Disponibilite des equipements</div>

  <section class="filtres-box">
    <div class="filtre-row">
      <span class="filtre-label">Du</span><input id="d-du" type="date">
      <span class="filtre-label">Au</span><input id="d-au" type="date">
      <span class="filtre-label">Famille</span><select id="d-famille"><option value="">Tous</option></select>
      <span class="filtre-label">Service</span><select id="d-service"><option value="">Tous</option></select>
      <span class="filtre-label">Statut</span><select id="d-statut"><option value="">Tous</option><option>OK</option><option>Maintenance</option><option>HS</option></select>
      <button class="btn-chercher" onclick="chargerDisponibilite()">📊 Calculer</button>
      <button class="btn-secondary" onclick="resetDisponibilite()">🔄 Reset</button>
    </div>
  </section>

  <section class="cards-row">
    <article class="card-indicateur"><div class="card-indicateur-header">✅ Disponibilite moyenne</div><div class="card-indicateur-body"><span id="dispo-moy" class="indicateur-valeur">0%</span></div></article>
    <article class="card-indicateur"><div class="card-indicateur-header">🔴 Total duree HS</div><div class="card-indicateur-body"><span id="total-hs" class="indicateur-valeur fill-rouge">0min</span></div></article>
    <article class="card-indicateur"><div class="card-indicateur-header">⚡ Total pannes</div><div class="card-indicateur-body"><span id="total-pannes" class="indicateur-valeur fill-orange">0</span></div></article>
    <article class="card-indicateur"><div class="card-indicateur-header">📊 Taux de pannes moyen</div><div class="card-indicateur-body"><span id="panne-moy" class="indicateur-valeur">0%</span></div></article>
  </section>

  <div class="table-wrap">
    <table>
      <thead><tr><th>N°</th><th>Equipement</th><th>Famille</th><th>Service</th><th>Zone</th><th>Temps total</th><th>Temps en service</th><th>Duree HS</th><th>Nb pannes</th><th>Taux de pannes %</th><th>Taux de disponibilite %</th></tr></thead>
      <tbody id="disponibiliteBody"></tbody>
    </table>
  </div>
</main>
<script src="../assets/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  initialiserPeriode();
  chargerDisponibilite();
});

async function chargerDisponibilite() {
  const params = new URLSearchParams({ action: 'disponibilite' });
  ['du', 'au', 'famille', 'service', 'statut'].forEach((id) => {
    const value = document.getElementById(`d-${id}`).value;
    if (value) params.set(id, value);
  });
  const data = await fetchJSON(`${API_BASE}stats.php?${params}`);
  remplirSelect('d-famille', data.familles || []);
  remplirSelect('d-service', data.services || []);
  calculerIndicateurs(data.rows || []);
  afficherTableauDisponibilite(data.rows || []);
}

function afficherTableauDisponibilite(rows) {
  document.getElementById('disponibiliteBody').innerHTML = rows.map((row, index) => `
    <tr class="${Number(row.taux_disponibilite) < 70 ? 'critique' : ''}">
      <td>${index + 1}</td>
      <td>${escapeHtml(row.nom)}</td>
      <td>${escapeHtml(row.famille)}</td>
      <td>${escapeHtml(row.service)}</td>
      <td>${escapeHtml(row.zone)}</td>
      <td>${escapeHtml(row.temps_total_formate)}</td>
      <td><span class="duree-ok">${escapeHtml(row.temps_service_formate)}</span></td>
      <td>${Number(row.duree_hs_minutes) > 0 ? `<span class="duree-hs">${escapeHtml(row.duree_hs_formate)}</span>` : '<span class="duree-ok">Aucun arret</span>'}</td>
      <td>${Number(row.nb_pannes)}</td>
      <td>${cellulePourcentage(Number(row.taux_pannes), 'panne')}</td>
      <td>${cellulePourcentage(Number(row.taux_disponibilite), 'dispo')}</td>
    </tr>
  `).join('');
}

function cellulePourcentage(valeur, type) {
  let couleur;
  if (type === 'dispo') {
    couleur = valeur > 90 ? 'vert' : valeur > 70 ? 'orange' : 'rouge';
  } else {
    couleur = valeur <= 5 ? 'vert' : valeur <= 15 ? 'orange' : 'rouge';
  }
  return `
    <div class="progress-cell">
      <span class="progress-value fill-${couleur}">${valeur.toFixed(2)}%</span>
      <div class="progress-bar-bg">
        <div class="progress-bar-fill fill-${couleur}" style="width:${Math.min(valeur, 100)}%"></div>
      </div>
    </div>`;
}

function calculerIndicateurs(data) {
  const n = data.length || 1;
  const dispoMoy = data.reduce((s, r) => s + Number(r.taux_disponibilite), 0) / n;
  const totalHS = data.reduce((s, r) => s + Number(r.duree_hs_minutes), 0);
  const totalPannes = data.reduce((s, r) => s + Number(r.nb_pannes), 0);
  const panneMoy = data.reduce((s, r) => s + Number(r.taux_pannes), 0) / n;

  afficherCarteIndicateur('dispo-moy', dispoMoy.toFixed(2) + '%', 'dispo', dispoMoy);
  afficherCarteIndicateur('total-hs', minutesEnDureeJS(totalHS), 'rouge');
  afficherCarteIndicateur('total-pannes', totalPannes, 'orange');
  afficherCarteIndicateur('panne-moy', panneMoy.toFixed(2) + '%', 'panne', panneMoy);
}

function afficherCarteIndicateur(id, valeur, type, nombre = 0) {
  const target = document.getElementById(id);
  target.textContent = valeur;
  target.classList.remove('fill-vert', 'fill-orange', 'fill-rouge');
  if (type === 'rouge') target.classList.add('fill-rouge');
  else if (type === 'orange') target.classList.add('fill-orange');
  else if (type === 'dispo') target.classList.add(nombre > 90 ? 'fill-vert' : nombre > 70 ? 'fill-orange' : 'fill-rouge');
  else target.classList.add(nombre <= 5 ? 'fill-vert' : nombre <= 15 ? 'fill-orange' : 'fill-rouge');
}

function minutesEnDureeJS(minutes) {
  minutes = Number(minutes) || 0;
  const j = Math.floor(minutes / 1440);
  const h = Math.floor((minutes % 1440) / 60);
  const m = minutes % 60;
  if (j > 0) return `${j}j ${h}h ${m}min`;
  if (h > 0) return `${h}h ${m}min`;
  return `${m}min`;
}

function remplirSelect(id, values) {
  const select = document.getElementById(id);
  const current = select.value;
  if (select.options.length > 1) return;
  select.insertAdjacentHTML('beforeend', values.map((value) => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join(''));
  select.value = current;
}

function initialiserPeriode() {
  const now = new Date();
  const first = new Date(now.getFullYear(), now.getMonth(), 1);
  document.getElementById('d-du').value = first.toISOString().slice(0, 10);
  document.getElementById('d-au').value = now.toISOString().slice(0, 10);
}

function resetDisponibilite() {
  initialiserPeriode();
  ['famille', 'service', 'statut'].forEach((id) => document.getElementById(`d-${id}`).value = '');
  chargerDisponibilite();
}
</script>
</body>
</html>
