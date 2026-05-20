let equipementsCache = [];
let listes = { familles: [], zones: [], services: [] };
let editionId = null;
let planningEquipement = null;
let selectedPlanningDate = null;
let calendarDate = new Date();
let scanStream = null;
let scanDetector = null;
let scanActive = false;
let lastScanCode = '';

document.addEventListener('DOMContentLoaded', () => {
  chargerEquipements();
  document.getElementById('equipementForm')?.addEventListener('submit', soumettreEquipement);
  document.getElementById('planningForm')?.addEventListener('submit', soumettrePlanning);
  document.getElementById('scanForm')?.addEventListener('submit', soumettreScan);
});

async function chargerEquipements() {
  const params = new URLSearchParams({ action: 'liste' });
  ['nom', 'famille', 'zone', 'service'].forEach((id) => {
    const value = document.getElementById(`f-${id}`)?.value;
    if (value) params.set(id, value);
  });
  const enService = document.getElementById('f-en-service');
  if (enService?.checked) params.set('en_service', '1');
  const data = await fetchJSON(`${API_BASE}equipements.php?${params}`);
  equipementsCache = data.equipements;
  listes = { familles: data.familles, zones: data.zones, services: data.services };
  remplirFiltres();
  renderTableau(equipementsCache);
}

function remplirFiltres() {
  const famille = document.getElementById('f-famille');
  const zone = document.getElementById('f-zone');
  const service = document.getElementById('f-service');
  if (famille && famille.options.length <= 1) famille.insertAdjacentHTML('beforeend', optionList(listes.familles));
  if (zone && zone.options.length <= 1) zone.insertAdjacentHTML('beforeend', optionList(listes.zones));
  if (service && service.options.length <= 1) service.insertAdjacentHTML('beforeend', optionList(listes.services));
}

function renderTableau(items) {
  const canManage = ['admin', 'responsable'].includes(window.USER_ROLE);
  const canPlan = ['admin', 'responsable', 'superviseur'].includes(window.USER_ROLE);
  document.getElementById('equipementsCount').textContent = items.length;
  document.getElementById('equipementsMeta').textContent = `${listes.familles.length} familles | ${listes.zones.length} zones`;
  document.getElementById('equipementsBody').innerHTML = items.map((e) => `
    <tr>
      <td><button class="badge ${badgeClass(e.statut)}" onclick="ouvrirDetail(${e.id})">${escapeHtml(e.nom)}</button></td>
      <td><button class="btn-icon" onclick="ouvrirModalEdition(${e.id})">✏</button></td>
      <td>${escapeHtml(e.marque)}<br><small>${escapeHtml(e.code)} / SN ${escapeHtml(e.numero_serie)}</small></td>
      <td>${Number(e.prix_acquisition || 0).toLocaleString('fr-FR')}</td>
      <td>${Number(e.mode_integre) ? 'Oui' : 'Non'}</td>
      <td>${escapeHtml(e.code || '')}</td>
      <td>${escapeHtml(e.installation)}<br><small>${escapeHtml(e.zone)}</small></td>
      <td>${formatDate(e.mise_en_service)}</td>
      <td>${formatDate(e.date_remplacement_prevu)}</td>
      <td><span class="badge badge-esu">${escapeHtml(e.service)}</span><br>${escapeHtml(e.famille)}</td>
      <td>${escapeHtml(e.remarques)}</td>
      <td><button class="btn-icon" onclick="ouvrirModalPlanning(${e.id}, '${escapeHtml(e.nom).replace(/'/g, '&#039;')}')">📅</button></td>
    </tr>
  `).join('');
  document.querySelectorAll('#equipementsBody tr').forEach((row) => {
    if (!canManage) row.children[1].textContent = '';
    if (!canPlan) row.children[11].textContent = '';
  });
}

function badgeClass(statut) {
  if (statut === 'HS') return 'badge-hs';
  if (statut === 'Maintenance') return 'badge-maintenance';
  return 'badge-ok';
}

function ouvrirModalAjout() {
  editionId = null;
  document.getElementById('equipementModalTitle').textContent = "Ajout d'un equipement";
  document.getElementById('equipementForm').reset();
  remplirSelectsModal();
  showModal('equipementModal');
}

function ouvrirModalEdition(id) {
  const e = equipementsCache.find((item) => Number(item.id) === Number(id));
  if (!e) return;
  editionId = id;
  remplirSelectsModal();
  document.getElementById('equipementModalTitle').textContent = 'Edition equipement';
  Object.keys(e).forEach((key) => {
    const input = document.querySelector(`[name="${key}"]`);
    if (!input) return;
    if (input.type === 'checkbox') input.checked = Number(e[key]) === 1;
    else input.value = e[key] ?? '';
  });
  showModal('equipementModal');
}

function remplirSelectsModal() {
  document.querySelector('[name="famille"]').innerHTML = optionList(['Electronique', 'Informatique', 'Balisage', 'Telecoms', 'Rayon X', 'Detecteurs']);
  document.querySelector('[name="zone"]').innerHTML = optionList(['Depart tri bagages', 'ESU', 'Depart sous douane', 'Administration', 'Arrivee']);
}

async function soumettreEquipement(event) {
  event.preventDefault();
  const form = new FormData(event.target);
  const payload = Object.fromEntries(form.entries());
  payload.mode_integre = document.querySelector('[name="mode_integre"]').checked ? 1 : 0;
  payload.en_service = document.querySelector('[name="en_service"]').checked ? 1 : 0;
  if (editionId) payload.id = editionId;
  await fetchJSON(`${API_BASE}equipements.php?action=${editionId ? 'modifier' : 'ajouter'}`, { method: 'POST', body: JSON.stringify(payload) });
  hideModal('equipementModal');
  showToast('Equipement enregistre');
  chargerEquipements();
}

function ouvrirDetail(id) {
  const e = equipementsCache.find((item) => Number(item.id) === Number(id));
  if (!e) return;
  document.getElementById('detailBody').innerHTML = Object.entries(e).map(([k, v]) => `<div class="form-row"><span class="form-label">${escapeHtml(k)}</span><strong>${escapeHtml(v)}</strong></div>`).join('');
  showModal('detailModal');
}

async function ouvrirModalScan() {
  lastScanCode = '';
  document.getElementById('scanCode').value = '';
  document.getElementById('scanResult').innerHTML = '';
  setScanMessage("Placez le code-barres ou QR code de l'equipement devant la camera.");
  showModal('scanModal');
  await demarrerScanner();
}

function fermerModalScan() {
  arreterScanner();
  hideModal('scanModal');
}

async function demarrerScanner() {
  const video = document.getElementById('scanVideo');
  if (!video) return;

  if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
    setScanMessage('Scanner camera non disponible sur ce navigateur. Saisissez le code ou numero serie.');
    return;
  }

  try {
    scanDetector = new BarcodeDetector({ formats: ['qr_code', 'code_128', 'code_39', 'ean_13'] });
    scanStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
    video.srcObject = scanStream;
    await video.play();
    scanActive = true;
    scannerFrame();
  } catch (error) {
    setScanMessage('Camera indisponible. Saisissez le code ou numero serie.');
  }
}

async function scannerFrame() {
  if (!scanActive || !scanDetector) return;
  const video = document.getElementById('scanVideo');
  try {
    const codes = await scanDetector.detect(video);
    const code = codes[0]?.rawValue?.trim();
    if (code && code !== lastScanCode) {
      lastScanCode = code;
      document.getElementById('scanCode').value = code;
      await rechercherEquipementScanne(code);
    }
  } catch (error) {
    setScanMessage('Lecture en cours...');
  }
  if (scanActive) requestAnimationFrame(scannerFrame);
}

function arreterScanner() {
  scanActive = false;
  scanStream?.getTracks().forEach((track) => track.stop());
  scanStream = null;
  const video = document.getElementById('scanVideo');
  if (video) video.srcObject = null;
}

async function soumettreScan(event) {
  event.preventDefault();
  const code = document.getElementById('scanCode')?.value.trim();
  if (!code) {
    showToast('Code equipement manquant', 'error');
    return;
  }
  await rechercherEquipementScanne(code);
}

async function rechercherEquipementScanne(code) {
  try {
    setScanMessage('Recherche de l equipement...');
    const data = await fetchJSON(`${API_BASE}equipements.php?action=scan&code=${encodeURIComponent(code)}`);
    afficherResultatScan(data.equipement);
    setScanMessage('Equipement trouve.');
  } catch (error) {
    document.getElementById('scanResult').innerHTML = `<div class="scan-empty">${escapeHtml(error.message)}</div>`;
    setScanMessage('Aucun equipement trouve pour ce code.');
  }
}

function afficherResultatScan(e) {
  if (!equipementsCache.some((item) => Number(item.id) === Number(e.id))) {
    equipementsCache.push(e);
  }
  document.getElementById('scanResult').innerHTML = `
    <div class="scan-card">
      <div class="scan-card-head">
        <strong>${escapeHtml(e.nom)}</strong>
        <span class="badge ${badgeClass(e.statut)}">${escapeHtml(e.statut)}</span>
      </div>
      <div class="scan-lines">
        <span>Code</span><strong>${escapeHtml(e.code)}</strong>
        <span>Numero serie</span><strong>${escapeHtml(e.numero_serie)}</strong>
        <span>Service / Famille</span><strong>${escapeHtml(e.service)} / ${escapeHtml(e.famille)}</strong>
        <span>Zone</span><strong>${escapeHtml(e.zone)}</strong>
        <span>En service</span><strong>${Number(e.en_service) ? 'Oui' : 'Non'}</strong>
        <span>Remarques</span><strong>${escapeHtml(e.remarques)}</strong>
      </div>
      <button class="btn-secondary" type="button" onclick="ouvrirDetailDepuisScan(${Number(e.id)})">Voir la fiche detail</button>
    </div>
  `;
}

function ouvrirDetailDepuisScan(id) {
  fermerModalScan();
  const equipement = equipementsCache.find((item) => Number(item.id) === Number(id));
  if (equipement) {
    ouvrirDetail(id);
    return;
  }
  chargerEquipements().then(() => ouvrirDetail(id));
}

function setScanMessage(message) {
  const target = document.getElementById('scanMessage');
  if (target) target.textContent = message;
}

function ouvrirModalPlanning(id, nom) {
  planningEquipement = id;
  selectedPlanningDate = null;
  document.getElementById('planningTitle').textContent = `Ajouter un planning pour : ${nom}`;
  calendarDate = new Date();
  generateCalendar(calendarDate.getMonth(), calendarDate.getFullYear());
  showModal('planningModal');
}

function generateCalendar(month, year) {
  const names = ['Janvier', 'Fevrier', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Aout', 'Septembre', 'Octobre', 'Novembre', 'Decembre'];
  document.getElementById('calendarMonth').textContent = `${names[month]} ${year}`;
  const first = new Date(year, month, 1).getDay() || 7;
  const days = new Date(year, month + 1, 0).getDate();
  let html = ['L', 'M', 'M', 'J', 'V', 'S', 'D'].map((d) => `<span class="dow">${d}</span>`).join('');
  for (let i = 1; i < first; i++) html += '<span></span>';
  for (let day = 1; day <= days; day++) {
    const date = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    html += `<button type="button" class="${date === selectedPlanningDate ? 'selected' : ''}" onclick="selectCalendarDate('${date}')">${day}</button>`;
  }
  document.getElementById('calendarGrid').innerHTML = html;
}

function moveCalendar(delta) {
  calendarDate.setMonth(calendarDate.getMonth() + delta);
  generateCalendar(calendarDate.getMonth(), calendarDate.getFullYear());
}

function selectCalendarDate(date) {
  selectedPlanningDate = date;
  document.getElementById('planningDate').value = date;
  generateCalendar(calendarDate.getMonth(), calendarDate.getFullYear());
}

async function soumettrePlanning(event) {
  event.preventDefault();
  const form = new FormData(event.target);
  const payload = Object.fromEntries(form.entries());
  payload.equipement_id = planningEquipement;
  payload.date_prevue = selectedPlanningDate || payload.date_prevue;
  payload.nuit = document.querySelector('[name="nuit"]').checked ? 1 : 0;
  await fetchJSON(`${API_BASE}preventive.php?action=ajouter`, { method: 'POST', body: JSON.stringify(payload) });
  hideModal('planningModal');
  showToast('Planning preventif ajoute');
}
