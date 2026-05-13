let activeChart = null;

document.addEventListener('DOMContentLoaded', () => {
  const page = document.body.dataset.page;
  if (page === 'disponibilite') chargerDisponibilite();
  if (page === 'trp') chargerTRP();
  if (page === 'utilisation') chargerUtilisation();
});

function drawChart(type, labels, values, label) {
  const canvas = document.getElementById('statsChart');
  if (!canvas || !window.Chart) return;
  if (activeChart) activeChart.destroy();
  activeChart = new Chart(canvas, {
    type,
    data: { labels, datasets: [{ label, data: values, backgroundColor: ['#2980b9', '#27ae60', '#e67e22', '#e74c3c', '#8e44ad', '#16a085'], borderColor: '#1a5276', tension: 0.25 }] },
    options: { responsive: true, maintainAspectRatio: false }
  });
}

async function chargerDisponibilite() {
  const data = await fetchJSON(`${API_BASE}stats.php?action=disponibilite`);
  document.getElementById('globalDispo').textContent = `${data.global}%`;
  drawChart('bar', data.chart.map((i) => i.label), data.chart.map((i) => i.value), 'Disponibilite par famille');
  document.getElementById('statsBody').innerHTML = data.rows.map((r) => `
    <tr><td>${escapeHtml(r.nom)}</td><td>${escapeHtml(r.famille)}</td><td>${r.temps_service} h</td><td>${r.pannes}</td><td>${r.taux}%</td></tr>
  `).join('');
}

async function chargerTRP() {
  const data = await fetchJSON(`${API_BASE}stats.php?action=trp`);
  Object.entries(data.cards).forEach(([key, value]) => {
    const el = document.getElementById(key);
    if (el) el.textContent = `${value}%`;
  });
  drawChart('line', data.chart.map((i) => i.label), data.chart.map((i) => i.value), 'Evolution TRP');
}

async function chargerUtilisation() {
  const data = await fetchJSON(`${API_BASE}stats.php?action=utilisation`);
  drawChart('pie', data.chart.map((i) => i.label), data.chart.map((i) => i.value), 'Utilisation par famille');
  document.getElementById('statsBody').innerHTML = data.rows.map((r) => `
    <tr><td>${escapeHtml(r.nom)}</td><td>${r.interventions}</td><td>${r.heures} h</td><td>${r.taux}%</td></tr>
  `).join('');
}
