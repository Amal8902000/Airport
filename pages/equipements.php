<?php require_once '../config/session.php'; require_page_access('equipements'); ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Equipements - GMAO ONDA</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php render_navbar('equipements'); ?>
<main class="page">
  <div class="page-header">
    <span>Gestion des equipements</span>
    <div class="page-actions">
      <button class="btn-secondary" onclick="ouvrirModalScan()">Scanner</button>
      <?php if (has_role(['admin', 'responsable'])): ?><button class="btn-primary" onclick="ouvrirModalAjout()">+ Ajouter</button><?php endif; ?>
    </div>
  </div>
  <section class="filtres-box">
    <div class="filtre-row">
      <span class="filtre-label">Equipement</span><input id="f-nom" type="text">
      <span class="filtre-label">Service</span><select id="f-service"><option value="">Tous</option></select>
      <label><input id="f-en-service" type="checkbox" checked> En service</label>
    </div>
    <div class="filtre-row">
      <span class="filtre-label">Famille</span><select id="f-famille"><option value="">Tous</option></select>
      <span class="filtre-label">Zone</span><select id="f-zone"><option value="">Toutes</option></select>
      <button class="btn-chercher" onclick="chargerEquipements()">🔍 Chercher</button>
    </div>
  </section>
  <div class="table-header"><strong>Liste des equipements: <span id="equipementsCount">0</span></strong><span id="equipementsMeta">Familles | Zones</span></div>
  <div class="table-wrap">
    <table><thead><tr><th>Equipement</th><th>✏</th><th>Marque/Code/SN</th><th>Prix acq.</th><th>Mode integre</th><th>AP</th><th>Installation/Zone</th><th>Mis en service</th><th>Remplacement prevu</th><th>Service/Famille</th><th>Remarques</th><th>📅</th></tr></thead><tbody id="equipementsBody"></tbody></table>
  </div>
</main>

<div class="modal-overlay" id="equipementModal"><div class="modal"><div class="modal-header"><span id="equipementModalTitle">Ajout d'un equipement</span><button class="modal-close" onclick="hideModal('equipementModal')">×</button></div><form class="modal-body" id="equipementForm">
  <div class="form-row"><label class="form-label">Equipement*</label><input class="form-input" name="nom" required></div>
  <div class="form-row"><label class="form-label">Code</label><input class="form-input" name="code"></div>
  <div class="form-row"><label class="form-label">Marque</label><input class="form-input" name="marque"></div>
  <div class="form-row"><label class="form-label">Numero serie</label><input class="form-input" name="numero_serie"></div>
  <div class="form-row"><label class="form-label">Prix d'acquisition</label><input class="form-input" type="number" step="0.01" name="prix_acquisition"></div>
  <div class="form-row"><label class="form-label">Mode integre</label><label><input type="checkbox" name="mode_integre"> Oui</label></div>
  <div class="form-row"><label class="form-label">Famille*</label><select class="form-input" name="famille" required></select></div>
  <div class="form-row"><label class="form-label">Zone*</label><select class="form-input" name="zone" required></select></div>
  <div class="form-row"><label class="form-label">Service</label><input class="form-input" name="service" value="ESU"></div>
  <div class="form-row"><label class="form-label">Installation</label><input class="form-input" name="installation"></div>
  <div class="form-row"><label class="form-label">Mise en service</label><input class="form-input" type="date" name="mise_en_service"></div>
  <div class="form-row"><label class="form-label">Date remplacement</label><input class="form-input" type="date" name="date_remplacement_prevu"></div>
  <div class="form-row"><label class="form-label">Date d'arret</label><input class="form-input" type="date" name="date_arret"></div>
  <div class="form-row"><label class="form-label">Statut</label><select class="form-input" name="statut"><option>OK</option><option>Maintenance</option><option>HS</option></select></div>
  <div class="form-row"><label class="form-label">En service</label><label><input type="checkbox" name="en_service" checked> Oui</label></div>
  <div class="form-row"><label class="form-label">Remarques</label><textarea class="form-input" name="remarques"></textarea></div>
  <div class="modal-actions"><button class="btn-enregistrer" type="submit">💾 Enregistrer</button></div>
</form></div></div>

<div class="modal-overlay" id="detailModal"><div class="modal"><div class="modal-header"><span>Fiche detail equipement</span><button class="modal-close" onclick="hideModal('detailModal')">×</button></div><div class="modal-body" id="detailBody"></div></div></div>

<div class="modal-overlay" id="scanModal"><div class="modal"><div class="modal-header"><span>Scanner un equipement</span><button class="modal-close" onclick="fermerModalScan()">×</button></div><div class="modal-body">
  <div class="scan-box">
    <video id="scanVideo" class="scan-video" muted playsinline></video>
    <div id="scanMessage" class="scan-message">Placez le code-barres ou QR code de l'equipement devant la camera.</div>
  </div>
  <form id="scanForm" class="scan-form">
    <label class="form-label" for="scanCode">Code / numero serie</label>
    <input class="form-input" id="scanCode" name="scanCode" autocomplete="off" placeholder="Ex: RX-CAB-01">
    <button class="btn-chercher" type="submit">Chercher</button>
  </form>
  <div id="scanResult" class="scan-result"></div>
</div></div></div>

<div class="modal-overlay" id="planningModal"><div class="modal modal-lg"><div class="modal-header"><span id="planningTitle">Ajouter un planning</span><button class="modal-close" onclick="hideModal('planningModal')">×</button></div><form class="modal-body" id="planningForm">
  <div class="planning-grid">
    <div class="option-list"><strong>Type de maintenance</strong><label><input type="radio" name="type_maintenance" value="Entretien" checked> Entretien</label><label><input type="radio" name="type_maintenance" value="Etalonnage"> Etalonnage</label><label><input type="radio" name="type_maintenance" value="Controles reglementaires"> Controles reglementaires</label></div>
    <div><strong>Date de la maintenance</strong><input type="hidden" id="planningDate" name="date_prevue"><div class="calendar"><div class="calendar-head"><button type="button" class="btn-icon" onclick="moveCalendar(-1)">‹</button><strong id="calendarMonth"></strong><button type="button" class="btn-icon" onclick="moveCalendar(1)">›</button></div><div class="calendar-grid" id="calendarGrid"></div></div></div>
    <div class="option-list"><strong>Periodicite</strong><label><input type="radio" name="periodicite" value="Annuelle" checked> Annuelle</label><label><input type="radio" name="periodicite" value="Semestrielle"> Semestrielle</label><label><input type="radio" name="periodicite" value="Trimestrielle"> Trimestrielle</label><label><input type="radio" name="periodicite" value="Mensuelle"> Mensuelle</label><label><input type="radio" name="periodicite" value="Hebdomadaire"> Hebdomadaire</label><hr><label><input type="checkbox"> Eviter Samedi et Dimanche</label><label><input type="checkbox"> Meme jour de la semaine</label></div>
  </div>
  <div class="filtre-row" style="margin-top:16px"><label><input type="checkbox" name="nuit"> A realiser seulement pendant la nuit</label><button class="btn-enregistrer" type="submit">💾 Enregistrer</button><label><input type="checkbox"> Une seule journee seulement</label></div>
</form></div></div>

<script src="../assets/js/main.js"></script><script src="../assets/js/equipements.js"></script>
</body>
</html>
