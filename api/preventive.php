<?php
require_once __DIR__ . '/_bootstrap.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$body = json_body();

if ($action === 'liste') {
    $where = [];
    $params = [];
    $map = [
        'service' => 'p.service',
        'periodicite' => 'p.periodicite',
        'etat' => 'p.etat',
        'famille' => 'e.famille',
    ];
    foreach ($map as $key => $column) {
        if (!empty($_GET[$key])) {
            $where[] = "$column = ?";
            $params[] = $_GET[$key];
        }
    }
    if (!empty($_GET['du'])) {
        $where[] = 'p.date_prevue >= ?';
        $params[] = $_GET['du'];
    }
    if (!empty($_GET['au'])) {
        $where[] = 'p.date_prevue <= ?';
        $params[] = $_GET['au'];
    }
    if (!empty($_GET['equipement'])) {
        $where[] = 'e.nom LIKE ?';
        $params[] = '%' . $_GET['equipement'] . '%';
    }
    if (!empty($_GET['type_maintenance'])) {
        $where[] = 'p.type_maintenance = ?';
        $params[] = $_GET['type_maintenance'];
    }
    if (isset($_GET['nuit']) && $_GET['nuit'] !== '') {
        $where[] = 'p.nuit = ?';
        $params[] = (int) $_GET['nuit'];
    }
    $sql = 'SELECT p.*, e.nom AS equipement, e.famille, e.code FROM preventives p JOIN equipements e ON e.id = p.equipement_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY p.date_prevue DESC';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    ok(['preventives' => $stmt->fetchAll()]);
}

if ($action === 'stats') {
    $total = (int) $conn->query('SELECT COUNT(*) FROM preventives')->fetchColumn();
    $realise = (int) $conn->query("SELECT COUNT(*) FROM preventives WHERE etat = 'Realise'")->fetchColumn();
    $attente = (int) $conn->query("SELECT COUNT(*) FROM preventives WHERE etat = 'En attente'")->fetchColumn();
    $temps = (int) $conn->query("SELECT COUNT(*) FROM preventives WHERE etat = 'Realise' AND (date_realisation IS NULL OR date_realisation <= date_prevue)")->fetchColumn();
    ok([
        'stats' => [
            'planifiees' => $total,
            'realisees' => $realise,
            'attente' => $attente,
            'realisation' => $total ? round($realise * 100 / $total, 1) : 0,
            'a_temps' => $temps,
            'respect' => $realise ? round($temps * 100 / $realise, 1) : 0,
        ],
    ]);
}

if ($action === 'ajouter') {
    require_roles(['admin', 'responsable', 'superviseur']);

    foreach (['equipement_id', 'type_maintenance', 'periodicite', 'date_prevue'] as $field) {
        if (empty($body[$field])) {
            fail('Champs obligatoires manquants', 422);
        }
    }
    $stmt = $conn->prepare('INSERT INTO preventives (equipement_id, type_maintenance, periodicite, date_prevue, etat, technicien, details, nuit, service) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $body['equipement_id'],
        $body['type_maintenance'],
        $body['periodicite'],
        $body['date_prevue'],
        'En attente',
        $body['technicien'] ?? '',
        $body['details'] ?? '',
        !empty($body['nuit']) ? 1 : 0,
        $body['service'] ?? 'ESU',
    ]);
    ok(['id' => (int) $conn->lastInsertId()]);
}

if ($action === 'cloturer') {
    require_roles(['admin', 'responsable', 'superviseur', 'technicien']);

    $id = (int) ($_GET['id'] ?? $body['id'] ?? 0);
    $date = $_GET['date_realisation'] ?? $body['date_realisation'] ?? date('Y-m-d');
    $details = $_GET['details'] ?? $body['details'] ?? 'Maintenance realisee';
    $technicien = $_GET['technicien'] ?? $body['technicien'] ?? ($_SESSION['user']['prenom'] ?? '');
    $stmt = $conn->prepare("UPDATE preventives SET etat = 'Realise', date_realisation = ?, details = ?, technicien = ? WHERE id = ?");
    $stmt->execute([$date, $details, $technicien, $id]);
    ok();
}

if ($action === 'supprimer') {
    require_roles(['admin', 'responsable']);

    $stmt = $conn->prepare('DELETE FROM preventives WHERE id = ?');
    $stmt->execute([(int) ($_GET['id'] ?? $body['id'] ?? 0)]);
    ok();
}

fail('Action inconnue', 404);
