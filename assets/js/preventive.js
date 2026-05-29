document.addEventListener('DOMContentLoaded', () => {
  initPreventiveDates();
  chargerPreventive();
});

function preventiveParams() {
  const params = new URLSearchParams({ action: 'liste' });
  ['du', 'au', 'famille', 'equipement', 'periodicite', 'etat'].forEach((id) => {
    const value = document.getElementById(`p-${id}`)?.value;
    if (value) params.set(id, value);
  });
  const service = document.getElementById('p-service2')?.value || document.getElementById('p-service')?.value;
  if (service) params.set('service', service);
  const type = document.getElementById('p-type')?.value;
  if (type) params.set('type_maintenance', type);
  if (document.getElementById('p-nuit')?.checked) params.set('nuit', '1');
  return params;
}

async function chargerPreventive() {
  const params = preventiveParams();
  const data = await fetchJSON(`${API_BASE}preventive.php?${params}`);
  document.getElementById('preventiveCount').textContent = data.preventives.length;
  document.getElementById('preventiveBody').innerHTML = data.preventives.map((p) => `
    <tr>
      <td><span class="badge badge-esu">${escapeHtml(p.service)}</span></td>
      <td>${escapeHtml(p.equipement)}<br><small>${escapeHtml(p.famille)}</small></td>
      <td>${escapeHtml(p.code || '')}</td>
      <td>${escapeHtml(p.type_maintenance)}</td>
      <td>${escapeHtml(p.periodicite)}</td>
      <td>${formatDate(p.date_prevue)}</td>
      <td>${p.etat === 'Realise' ? '<span class="badge badge-ok">✓ Realise</span>' : '<span class="badge badge-maintenance">○ En attente</span>'}</td>
      <td>${formatDate(p.date_realisation)}</td>
      <td>${escapeHtml(p.details)}</td>
      <td>${p.etat === 'Realise' ? '' : `<button class="btn-primary" onclick="cloturerAction(${p.id})">Cloturer</button>`}</td>
    </tr>
  `).join('');
}

async function chargerStats() {
  const params = preventiveParams();
  params.set('action', 'stats');
  const data = await fetchJSON(`${API_BASE}preventive.php?${params}`);
  afficherStats(data.stats || {});
}

function afficherStats(stats) {
  const planifiees = Number(stats.planifiees || 0);
  const enAttente = Number(stats.en_attente || stats.attente || 0);
  const realisees = Number(stats.realisees || 0);
  const tauxRealisation = Number(stats.taux_realisation || stats.realisation || 0);
  const aTemps = Number(stats.a_temps || 0);
  const tauxRespect = Number(stats.taux_respect || stats.respect || 0);

  document.getElementById('stat-planifiees').textContent = planifiees;
  document.getElementById('stat-attente').textContent = enAttente;
  document.getElementById('stat-realisees').textContent = realisees;
  document.getElementById('stat-taux-real').textContent = tauxRealisation.toFixed(2) + ' %';
  document.getElementById('stat-a-temps').textContent = aTemps;
  document.getElementById('stat-taux-respect').textContent = tauxRespect.toFixed(2) + ' %';
}

async function calculerPreventive() {
  await chargerPreventive();
  await chargerStats();
  document.getElementById('vueIndicateurs').style.display = 'flex';
}

async function cloturerAction(id) {
  await fetchJSON(`${API_BASE}preventive.php?action=cloturer&id=${id}`, { method: 'POST', body: JSON.stringify({ date_realisation: new Date().toISOString().slice(0, 10) }) });
  showToast('Action preventive cloturee');
  chargerPreventive();
  if (document.getElementById('vueIndicateurs').style.display !== 'none') chargerStats();
}

function resetPreventive() {
  document.querySelectorAll('.preventive-filter').forEach((el) => {
    if (el.type === 'checkbox') el.checked = false;
    else el.value = '';
  });
  initPreventiveDates();
  document.getElementById('vueIndicateurs').style.display = 'none';
  chargerPreventive();
}

function initPreventiveDates() {
  const now = new Date();
  const first = new Date(now.getFullYear(), now.getMonth(), 1);
  const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  document.getElementById('p-du').value = first.toISOString().slice(0, 10);
  document.getElementById('p-au').value = last.toISOString().slice(0, 10);
}
