document.addEventListener('DOMContentLoaded', () => {
  chargerCorrective();
  chargerEquipementsCorrective();
  document.getElementById('correctiveForm')?.addEventListener('submit', soumettreDeclaration);
});

async function chargerCorrective() {
  const params = new URLSearchParams({ action: 'liste' });
  ['statut', 'priorite', 'service', 'equipement', 'du', 'au'].forEach((id) => {
    const value = document.getElementById(`c-${id}`)?.value;
    if (value) params.set(id, value);
  });
  const data = await fetchJSON(`${API_BASE}corrective.php?${params}`);
  const canClose = ['admin', 'responsable', 'superviseur', 'technicien'].includes(window.USER_ROLE);
  document.getElementById('correctiveBody').innerHTML = data.correctives.map((c) => `
    <tr>
      <td>${formatDate(c.date_declaration)}</td>
      <td>${escapeHtml(c.equipement)}</td>
      <td>${escapeHtml(c.description)}</td>
      <td><span class="badge ${c.priorite === 'Urgente' ? 'badge-hs' : c.priorite === 'Haute' ? 'badge-maintenance' : 'badge-neutral'}">${escapeHtml(c.priorite)}</span></td>
      <td>${c.statut === 'Clos' ? '<span class="badge badge-ok">Clos</span>' : '<span class="badge badge-hs">Ouvert</span>'}</td>
      <td>${escapeHtml(c.technicien)}</td>
      <td>${escapeHtml(c.duree_heures || '')}</td>
      <td>${c.statut === 'Clos' || !canClose ? '' : `<button class="btn-primary" onclick="cloturerPanne(${c.id})">Cloturer</button>`}</td>
    </tr>
  `).join('');
}

async function chargerEquipementsCorrective() {
  const data = await fetchJSON(`${API_BASE}equipements.php?action=liste`);
  document.querySelector('[name="equipement_id"]').innerHTML = optionList(data.equipements.map((e) => ({ id: e.id, nom: e.nom })).map((e) => `${e.id}|${e.nom}`));
  const select = document.querySelector('[name="equipement_id"]');
  Array.from(select.options).forEach((option) => {
    const [id, label] = option.value.split('|');
    option.value = id;
    option.textContent = label;
  });
}

async function soumettreDeclaration(event) {
  event.preventDefault();
  const payload = Object.fromEntries(new FormData(event.target).entries());
  await fetchJSON(`${API_BASE}corrective.php?action=ajouter`, { method: 'POST', body: JSON.stringify(payload) });
  hideModal('correctiveModal');
  showToast('Panne declaree');
  chargerCorrective();
}

async function cloturerPanne(id) {
  await fetchJSON(`${API_BASE}corrective.php?action=cloturer&id=${id}`, { method: 'POST', body: JSON.stringify({ duree_heures: 1 }) });
  showToast('Panne cloturee');
  chargerCorrective();
}
