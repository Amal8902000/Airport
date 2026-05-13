<?php
require_once __DIR__ . '/_bootstrap.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$body = json_body();

if ($action === 'liste') {
    $where = [];
    $params = [];
    foreach (['statut' => 'c.statut', 'priorite' => 'c.priorite', 'service' => 'e.service'] as $key => $column) {
        if (!empty($_GET[$key])) {
            $where[] = "$column = ?";
            $params[] = $_GET[$key];
        }
    }
    if (!empty($_GET['equipement'])) {
        $where[] = 'e.nom LIKE ?';
        $params[] = '%' . $_GET['equipement'] . '%';
    }
    if (!empty($_GET['du'])) {
        $where[] = 'DATE(c.date_declaration) >= ?';
        $params[] = $_GET['du'];
    }
    if (!empty($_GET['au'])) {
        $where[] = 'DATE(c.date_declaration) <= ?';
        $params[] = $_GET['au'];
    }
    $sql = 'SELECT c.*, e.nom AS equipement, e.service, e.famille FROM correctives c LEFT JOIN equipements e ON e.id = c.equipement_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY c.date_declaration DESC';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    ok(['correctives' => $stmt->fetchAll()]);
}

if ($action === 'stats') {
    $ouvert = (int) $conn->query("SELECT COUNT(*) FROM correctives WHERE statut = 'Ouvert'")->fetchColumn();
    $clos = (int) $conn->query("SELECT COUNT(*) FROM correctives WHERE statut = 'Clos'")->fetchColumn();
    ok(['stats' => ['ouvert' => $ouvert, 'clos' => $clos]]);
}

if ($action === 'ajouter') {
    require_roles(['admin', 'responsable', 'superviseur', 'technicien', 'agent_exploitation']);

    if (empty($body['equipement_id']) || empty($body['description'])) {
        fail('Equipement et description obligatoires', 422);
    }
    $stmt = $conn->prepare('INSERT INTO correctives (equipement_id, declarant, description, priorite, statut, technicien, remarques) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $body['equipement_id'],
        $body['declarant'] ?? '',
        $body['description'],
        $body['priorite'] ?? 'Normale',
        'Ouvert',
        $body['technicien'] ?? '',
        $body['remarques'] ?? '',
    ]);
    ok(['id' => (int) $conn->lastInsertId()]);
}

if ($action === 'cloturer') {
    require_roles(['admin', 'responsable', 'superviseur', 'technicien']);

    $id = (int) ($_GET['id'] ?? $body['id'] ?? 0);
    $duree = $body['duree_heures'] ?? $_GET['duree_heures'] ?? 1;
    $stmt = $conn->prepare("UPDATE correctives SET statut = 'Clos', date_resolution = NOW(), duree_heures = ? WHERE id = ?");
    $stmt->execute([$duree, $id]);
    ok();
}

fail('Action inconnue', 404);
