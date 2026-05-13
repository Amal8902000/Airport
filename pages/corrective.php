<?php require_once '../config/session.php'; require_page_access('corrective'); ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Maintenance corrective - GMAO ONDA</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php render_navbar('corrective'); ?>
<main class="page">
  <div class="page-header"><span>Maintenance corrective</span><button class="btn-primary" onclick="showModal('correctiveModal')">+ Declarer une panne</button></div>
  <section class="filtres-box">
    <div class="filtre-row">
      <select id="c-statut"><option value="">Statut</option><option>Ouvert</option><option>Clos</option></select>
      <select id="c-priorite"><option value="">Priorite</option><option>Urgente</option><option>Haute</option><option>Normale</option></select>
      <select id="c-service"><option value="">Service</option><option>ESU</option></select>
      <input id="c-equipement" type="text" placeholder="Equipement">
      <input id="c-du" type="date"><input id="c-au" type="date">
      <button class="btn-chercher" onclick="chargerCorrective()">🔍 Chercher</button>
    </div>
  </section>
  <div class="table-wrap">
    <table><thead><tr><th>Date</th><th>Equipement</th><th>Description</th><th>Priorite</th><th>Statut</th><th>Technicien</th><th>Duree</th><th>Cloturer</th></tr></thead><tbody id="correctiveBody"></tbody></table>
  </div>
</main>

<div class="modal-overlay" id="correctiveModal"><div class="modal"><div class="modal-header"><span>Declaration d'une panne</span><button class="modal-close" onclick="hideModal('correctiveModal')">×</button></div><form class="modal-body" id="correctiveForm">
  <div class="form-row"><label class="form-label">Equipement*</label><select class="form-input" name="equipement_id" required></select></div>
  <div class="form-row"><label class="form-label">Declarant</label><input class="form-input" name="declarant" value="<?= e(trim((current_user()['prenom'] ?? '') . ' ' . (current_user()['nom'] ?? ''))) ?>"></div>
  <div class="form-row"><label class="form-label">Description*</label><textarea class="form-input" name="description" required></textarea></div>
  <div class="form-row"><label class="form-label">Priorite</label><select class="form-input" name="priorite"><option>Urgente</option><option>Haute</option><option selected>Normale</option></select></div>
  <div class="form-row"><label class="form-label">Technicien</label><input class="form-input" name="technicien"></div>
  <div class="form-row"><label class="form-label">Remarques</label><textarea class="form-input" name="remarques"></textarea></div>
  <div class="modal-actions"><button class="btn-enregistrer" type="submit">💾 Enregistrer</button></div>
</form></div></div>

<script src="../assets/js/main.js"></script><script src="../assets/js/corrective.js"></script>
</body>
</html>
