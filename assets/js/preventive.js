document.addEventListener('DOMContentLoaded', () => {
  chargerPreventive();
  chargerStats();
});

async function chargerPreventive() {
  const params = new URLSearchParams({ action: 'liste' });
  ['du', 'au', 'service', 'famille', 'equipement', 'periodicite', 'etat'].forEach((id) => {
    const value = document.getElementById(`p-${id}`)?.value;
    if (value) params.set(id, value);
  });
  const type = document.getElementById('p-type')?.value;
  if (type) params.set('type_maintenance', type);
  if (document.getElementById('p-nuit')?.checked) params.set('nuit', '1');
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
  const data = await fetchJSON(`${API_BASE}preventive.php?action=stats`);
  const s = data.stats;
  document.getElementById('planifiees').textContent = s.planifiees;
  document.getElementById('attente').textContent = s.attente;
  document.getElementById('realisees').textContent = s.realisees;
  document.getElementById('realisation').textContent = `${s.realisation}%`;
  document.getElementById('atemps').textContent = s.a_temps;
  document.getElementById('respect').textContent = `${s.respect}%`;
}

function toggleVue() {
  document.getElementById('vueListe').style.display = document.getElementById('vueListe').style.display === 'none' ? 'block' : 'none';
  document.getElementById('vueIndicateurs').style.display = document.getElementById('vueListe').style.display === 'none' ? 'flex' : 'none';
}

async function cloturerAction(id) {
  await fetchJSON(`${API_BASE}preventive.php?action=cloturer&id=${id}`, { method: 'POST', body: JSON.stringify({ date_realisation: new Date().toISOString().slice(0, 10) }) });
  showToast('Action preventive cloturee');
  chargerPreventive();
  chargerStats();
}

function resetPreventive() {
  document.querySelectorAll('.preventive-filter').forEach((el) => {
    if (el.type === 'checkbox') el.checked = false;
    else el.value = '';
  });
  chargerPreventive();
}
