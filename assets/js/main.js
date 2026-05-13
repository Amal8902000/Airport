const API_BASE = '../api/';

document.addEventListener('DOMContentLoaded', () => {
  const current = location.pathname.split('/').pop().replace('.php', '');
  document.querySelectorAll('.navbar a').forEach((link) => {
    if (link.dataset.page === current) link.classList.add('active');
  });
});

function showModal(id) {
  document.getElementById(id)?.classList.add('active');
}

function hideModal(id) {
  document.getElementById(id)?.classList.remove('active');
}

function showToast(message, type = 'success') {
  let toast = document.querySelector('.toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.className = 'toast';
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  toast.className = `toast ${type} show`;
  setTimeout(() => toast.classList.remove('show'), 2600);
}

async function fetchJSON(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.success === false) {
    throw new Error(data.message || 'Erreur serveur');
  }
  return data;
}

function optionList(values, selected = '') {
  return values.map((v) => `<option value="${escapeHtml(v)}" ${v === selected ? 'selected' : ''}>${escapeHtml(v)}</option>`).join('');
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function formatDate(value) {
  if (!value) return '';
  return String(value).slice(0, 10);
}
