<?php
require_once '../config/session.php';
require_page_access('corrective');
$currentName = trim((current_user()['prenom'] ?? '') . ' ' . (current_user()['nom'] ?? ''));
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Maintenance corrective - GMAO ONDA</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .corrective-header { background: var(--bleu-tableau); color: white; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; font-weight: bold; }
    .corrective-table td { vertical-align: top; }
    .suivi-line { display: block; margin-bottom: 4px; }
    .ticket-cell { font-weight: bold; color: #1a5276; white-space: nowrap; }
    .anomalie-desc { max-width: 360px; }
    .export-center { display: flex; justify-content: center; padding: 16px; }
    .btn-export-excel { background: #27ae60; color: white; border: 0; border-radius: 4px; padding: 8px 16px; cursor: pointer; }
    .badge-ouvert { background: #e74c3c; color: white; }
    .badge-en-cours { background: #f39c12; color: white; }
    .badge-cloture { background: #27ae60; color: white; }
    .badge-urgente { background: #e74c3c; color: white; }
    .badge-haute { background: #e67e22; color: white; }
    .badge-normale { background: #27ae60; color: white; }
    .datetime-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .datetime-group input[type="date"], .datetime-group input[type="time"] { border: 1px solid #ccc; border-radius: 4px; padding: 6px 10px; font-size: 13px; }
    .info-calcul { background: #eaf4fb; border-left: 4px solid #2980b9; padding: 10px 14px; border-radius: 4px; margin-top: 10px; font-size: 13px; }
    .info-calcul .arret { color: #e74c3c; font-weight: bold; }
    .form-error { color: #e74c3c; font-size: 13px; min-height: 18px; }
  </style>
</head>
<body>
<?php render_navbar('corrective'); ?>
<main class="page">
  <div class="page-header">Maintenance corrective</div>
  <section class="filtres-box">
    <div class="filtre-row">
      <span class="filtre-label">Du</span><input id="filtre_du" type="date" value="2011-02-01">
      <input id="filtre_ticket" type="text" placeholder="Ticket">
      <input id="filtre_service_texte" type="text" placeholder="Service">
      <label><input id="filtre_hygiene" type="checkbox"> Hygiene et Proprete</label>
      <label><input id="filtre_hs" type="checkbox" checked> HS</label>
    </div>
    <div class="filtre-row">
      <span class="filtre-label">Au</span><input id="filtre_au" type="date">
      <input id="filtre_equipement" type="text" placeholder="Equipement">
      <input id="filtre_autres" type="text" placeholder="Autres">
      <select id="filtre_service"><option value="">ESU</option><option value="ESU">ESU</option></select>
      <label><input id="filtre_ok" type="checkbox"> OK</label>
      <button class="btn-chercher" onclick="chargerCorrective()">🔍 Chercher</button>
      <button class="btn-secondary" onclick="resetCorrective()">🔄 Reset</button>
    </div>
  </section>

  <div class="corrective-header">
    <span>Elements trouves : <span id="correctiveCount">0</span></span>
    <button class="btn-primary" onclick="ouvrirDeclaration()">Signaler une anomalie</button>
  </div>
  <div class="table-wrap">
    <table class="corrective-table">
      <thead><tr><th>Ticket</th><th>Elements</th><th>Suivi</th><th>Duree</th><th>Anomalie</th></tr></thead>
      <tbody id="correctiveBody"></tbody>
    </table>
  </div>
  <div class="export-center"><button class="btn-export-excel" onclick="exporterExcel()">📥 Exporter les donnees vers Excel</button></div>
</main>

<div class="modal-overlay" id="correctiveModal"><div class="modal modal-lg"><div class="modal-header"><span>Declaration d'une panne</span><button class="modal-close" onclick="hideModal('correctiveModal')">×</button></div><form class="modal-body" id="correctiveForm">
  <div class="form-row"><label class="form-label">Equipement*</label><select class="form-input" name="equipement_id" required></select></div>
  <div class="form-row"><label class="form-label">Declarant</label><input class="form-input" name="declarant" value="<?= e($currentName) ?>"></div>
  <div class="form-row"><label class="form-label">Description*</label><textarea class="form-input" name="description" required></textarea></div>
  <div class="form-row"><label class="form-label">Priorite*</label><select class="form-input" name="priorite" required><option>Urgente</option><option>Haute</option><option selected>Normale</option></select></div>
  <div class="form-row"><label class="form-label">Technicien*</label><select class="form-input" name="technicien" required></select></div>
  <div class="form-row"><label class="form-label">Email technicien</label><input class="form-input" name="technicien_email" readonly></div>
  <div class="form-row"><label class="form-label">Debut de la panne*</label><div class="datetime-group"><input type="date" id="debut_date" required><input type="time" id="debut_heure" required></div></div>
  <div class="form-row"><label class="form-label">Fin de l'intervention</label><div class="datetime-group"><input type="date" id="fin_date"><input type="time" id="fin_heure"></div></div>
  <div class="info-calcul">⏱ Temps d'arret : <span class="arret" id="tempsArretPreview">En cours</span></div>
  <div class="form-error" id="periodeError"></div>
  <div class="form-row"><label class="form-label">Remarques</label><textarea class="form-input" name="remarques"></textarea></div>
  <div class="modal-actions"><button class="btn-enregistrer" type="submit">💾 Enregistrer</button></div>
</form></div></div>

<script src="../assets/js/main.js"></script>
<script>
let correctivesCache = [];
let techniciensMeta = [];

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('filtre_au').value = new Date().toISOString().slice(0, 10);
  chargerMetaCorrective().then(chargerCorrective);
  document.getElementById('correctiveForm').addEventListener('submit', soumettreDeclaration);
  ['debut_date', 'debut_heure', 'fin_date', 'fin_heure'].forEach((id) => document.getElementById(id).addEventListener('input', calculerPeriode));
  document.querySelector('[name="technicien"]').addEventListener('change', syncTechnicienEmail);
});

async function chargerMetaCorrective() {
  const data = await fetchJSON(`${API_BASE}corrective.php?action=meta`);
  const equipementSelect = document.querySelector('[name="equipement_id"]');
  equipementSelect.innerHTML = (data.equipements || []).map((e) => `<option value="${Number(e.id)}">${escapeHtml(e.nom)}</option>`).join('');
  techniciensMeta = data.techniciens || [];
  document.querySelector('[name="technicien"]').innerHTML = techniciensMeta.map((t) => {
    const nom = `${t.prenom || ''} ${t.nom || ''}`.trim() || t.email;
    return `<option value="${escapeHtml(nom)}" data-email="${escapeHtml(t.email)}">${escapeHtml(nom)}</option>`;
  }).join('');
  syncTechnicienEmail();
}

function recupererFiltres() {
  return {
    du: document.getElementById('filtre_du').value,
    au: document.getElementById('filtre_au').value,
    ticket: document.getElementById('filtre_ticket').value,
    service: document.getElementById('filtre_service').value || document.getElementById('filtre_service_texte').value,
    equipement: document.getElementById('filtre_equipement').value,
    hs: document.getElementById('filtre_hs').checked ? '1' : '',
    ok: document.getElementById('filtre_ok').checked ? '1' : '',
  };
}

async function chargerCorrective() {
  const params = new URLSearchParams({ action: 'liste', ...recupererFiltres() });
  const data = await fetchJSON(`${API_BASE}corrective.php?${params}`);
  correctivesCache = data.correctives || [];
  document.getElementById('correctiveCount').textContent = correctivesCache.length;
  document.getElementById('correctiveBody').innerHTML = correctivesCache.length ? correctivesCache.map(renderCorrectiveRow).join('') : '<tr><td colspan="5">Aucun element trouve!</td></tr>';
}

function renderCorrectiveRow(c) {
  const statut = normaliserStatut(c.statut);
  const ticket = c.ticket || `TKT-${String(c.date_declaration || '').slice(0, 4)}-${String(c.id).padStart(4, '0')}`;
  const description = String(c.description || '');
  return `
    <tr>
      <td class="ticket-cell">${escapeHtml(ticket)}</td>
      <td>${escapeHtml(c.equipement)}<br><small>${escapeHtml(c.famille)}</small></td>
      <td><span class="suivi-line">${escapeHtml(c.technicien || 'Non assigne')}</span><span class="suivi-line">${formatDateTime(c.date_heure_debut || c.date_declaration)}</span><span class="badge ${badgeStatut(statut)}">${escapeHtml(statut)}</span></td>
      <td>${c.date_heure_fin ? escapeHtml(c.temps_arret_format) : 'En cours'}</td>
      <td class="anomalie-desc">${escapeHtml(description.length > 50 ? description.slice(0, 50) + '...' : description)}<br><span class="badge ${badgePriorite(c.priorite)}">${escapeHtml(c.priorite)}</span></td>
    </tr>`;
}

function ouvrirDeclaration() {
  document.getElementById('correctiveForm').reset();
  const now = new Date();
  document.getElementById('debut_date').value = now.toISOString().slice(0, 10);
  document.getElementById('debut_heure').value = now.toTimeString().slice(0, 5);
  syncTechnicienEmail();
  calculerPeriode();
  showModal('correctiveModal');
}

async function soumettreDeclaration(event) {
  event.preventDefault();
  if (!calculerPeriode()) return;
  const payload = Object.fromEntries(new FormData(event.target).entries());
  payload.action = 'ajouter';
  payload.date_heure_debut = composerDateHeure('debut');
  payload.date_heure_fin = composerDateHeure('fin');
  const data = await fetchJSON(`${API_BASE}corrective.php`, { method: 'POST', body: JSON.stringify(payload) });
  hideModal('correctiveModal');
  showToast(data.email_envoye ? `Panne declaree — Email envoye a ${data.technicien_email}` : 'Panne declaree');
  chargerCorrective();
}

function calculerPeriode() {
  const debut = dateDepuisChamps('debut');
  const fin = dateDepuisChamps('fin');
  const error = document.getElementById('periodeError');
  error.textContent = '';
  if (!debut || !fin) {
    document.getElementById('tempsArretPreview').textContent = 'En cours';
    return true;
  }
  if (fin < debut) {
    error.textContent = 'La date de fin doit etre apres la date de debut';
    return false;
  }
  document.getElementById('tempsArretPreview').textContent = minutesEnDuree(Math.floor((fin - debut) / 60000));
  return true;
}

function dateDepuisChamps(prefix) {
  const date = document.getElementById(`${prefix}_date`).value;
  const heure = document.getElementById(`${prefix}_heure`).value;
  return date && heure ? new Date(`${date}T${heure}`) : null;
}

function composerDateHeure(prefix) {
  const date = document.getElementById(`${prefix}_date`).value;
  const heure = document.getElementById(`${prefix}_heure`).value;
  return date && heure ? `${date} ${heure}:00` : '';
}

function minutesEnDuree(minutes) {
  const j = Math.floor(minutes / 1440);
  const h = Math.floor((minutes % 1440) / 60);
  const m = minutes % 60;
  if (j > 0) return `${j}j ${h}h ${m}min`;
  if (h > 0) return `${h}h ${m}min`;
  return `${m}min`;
}

function syncTechnicienEmail() {
  const select = document.querySelector('[name="technicien"]');
  document.querySelector('[name="technicien_email"]').value = select?.selectedOptions?.[0]?.dataset.email || '';
}

function resetCorrective() {
  document.getElementById('filtre_du').value = '2011-02-01';
  document.getElementById('filtre_au').value = new Date().toISOString().slice(0, 10);
  ['ticket', 'service_texte', 'equipement', 'autres'].forEach((id) => document.getElementById(`filtre_${id}`).value = '');
  document.getElementById('filtre_hygiene').checked = false;
  document.getElementById('filtre_hs').checked = true;
  document.getElementById('filtre_ok').checked = false;
  document.getElementById('filtre_service').value = '';
  chargerCorrective();
}

function exporterExcel() {
  const params = new URLSearchParams(recupererFiltres());
  window.location.href = `${API_BASE}export_excel_corrective.php?${params}`;
}

function formatDateTime(value) {
  return value ? String(value).slice(0, 16).replace('T', ' ') : '';
}

function normaliserStatut(statut) {
  return statut === 'Clos' ? 'Clôturé' : (statut || 'Ouvert');
}

function badgeStatut(statut) {
  if (statut === 'Clôturé') return 'badge-cloture';
  if (statut === 'En cours') return 'badge-en-cours';
  return 'badge-ouvert';
}

function badgePriorite(priorite) {
  if (priorite === 'Urgente') return 'badge-urgente';
  if (priorite === 'Haute') return 'badge-haute';
  return 'badge-normale';
}
</script>
</body>
</html>
