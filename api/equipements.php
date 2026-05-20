<?php
require_once __DIR__ . '/_bootstrap.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$body = json_body();

if ($action === 'liste') {
    $where = [];
    $params = [];
    foreach (['famille', 'zone', 'service'] as $field) {
        if (!empty($_GET[$field])) {
            $where[] = "$field = ?";
            $params[] = $_GET[$field];
        }
    }
    if (isset($_GET['en_service']) && $_GET['en_service'] !== '') {
        $where[] = 'en_service = ?';
        $params[] = (int) $_GET['en_service'];
    }
    if (!empty($_GET['nom'])) {
        $where[] = '(nom LIKE ? OR code LIKE ? OR numero_serie LIKE ?)';
        $term = '%' . $_GET['nom'] . '%';
        array_push($params, $term, $term, $term);
    }
    $sql = 'SELECT * FROM equipements' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY nom';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    ok([
        'equipements' => $stmt->fetchAll(),
        'familles' => $conn->query('SELECT DISTINCT famille FROM equipements WHERE famille IS NOT NULL ORDER BY famille')->fetchAll(PDO::FETCH_COLUMN),
        'zones' => $conn->query('SELECT DISTINCT zone FROM equipements WHERE zone IS NOT NULL ORDER BY zone')->fetchAll(PDO::FETCH_COLUMN),
        'services' => $conn->query('SELECT DISTINCT service FROM equipements WHERE service IS NOT NULL ORDER BY service')->fetchAll(PDO::FETCH_COLUMN),
    ]);
}

if ($action === 'detail') {
    $stmt = $conn->prepare('SELECT * FROM equipements WHERE id = ?');
    $stmt->execute([(int) ($_GET['id'] ?? 0)]);
    $equipement = $stmt->fetch();
    $equipement ? ok(['equipement' => $equipement]) : fail('Equipement introuvable', 404);
}

if ($action === 'scan') {
    $code = trim((string) ($_GET['code'] ?? ''));
    if ($code === '') {
        fail('Code scanne manquant', 422);
    }

    $stmt = $conn->prepare('SELECT * FROM equipements WHERE code = ? OR numero_serie = ? OR id = ? LIMIT 1');
    $stmt->execute([$code, $code, ctype_digit($code) ? (int) $code : 0]);
    $equipement = $stmt->fetch();
    $equipement ? ok(['equipement' => $equipement]) : fail('Aucun equipement trouve pour ce code', 404);
}

if ($action === 'ajouter' || $action === 'modifier') {
    require_roles(['admin', 'responsable']);

    if (empty($body['nom']) || empty($body['famille']) || empty($body['zone'])) {
        fail('Les champs Equipement, Famille et Zone sont obligatoires', 422);
    }
    $fields = ['nom', 'code', 'marque', 'numero_serie', 'prix_acquisition', 'mode_integre', 'famille', 'zone', 'service', 'installation', 'mise_en_service', 'date_remplacement_prevu', 'date_arret', 'en_service', 'statut', 'remarques'];
    $values = [];
    foreach ($fields as $field) {
        $values[$field] = $body[$field] ?? null;
    }
    $values['mode_integre'] = !empty($values['mode_integre']) ? 1 : 0;
    $values['en_service'] = isset($body['en_service']) ? (int) $body['en_service'] : 1;
    $values['service'] = $values['service'] ?: 'ESU';
    $values['statut'] = $values['statut'] ?: 'OK';

    if ($action === 'ajouter') {
        $cols = implode(',', $fields);
        $marks = implode(',', array_fill(0, count($fields), '?'));
        $stmt = $conn->prepare("INSERT INTO equipements ($cols) VALUES ($marks)");
        $stmt->execute(array_values($values));
        ok(['id' => (int) $conn->lastInsertId()]);
    }

    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        fail('Identifiant manquant', 422);
    }
    $sets = implode(',', array_map(fn($f) => "$f = ?", $fields));
    $stmt = $conn->prepare("UPDATE equipements SET $sets WHERE id = ?");
    $stmt->execute([...array_values($values), $id]);
    ok();
}

if ($action === 'supprimer') {
    require_roles(['admin', 'responsable']);

    $stmt = $conn->prepare('DELETE FROM equipements WHERE id = ?');
    $stmt->execute([(int) ($_GET['id'] ?? $body['id'] ?? 0)]);
    ok();
}

fail('Action inconnue', 404);
