<?php
require_once __DIR__ . '/_bootstrap.php';

$action = $_GET['action'] ?? '';

if ($action === 'disponibilite') {
    $rows = $conn->query("SELECT e.id, e.nom, e.famille, COUNT(c.id) AS pannes, COALESCE(SUM(c.duree_heures), 0) AS indispo
        FROM equipements e
        LEFT JOIN correctives c ON c.equipement_id = e.id AND c.statut = 'Clos'
        GROUP BY e.id, e.nom, e.famille
        ORDER BY e.famille, e.nom")->fetchAll();
    foreach ($rows as &$row) {
        $total = 24 * 365;
        $row['temps_service'] = round($total - (float) $row['indispo'], 2);
        $row['taux'] = round($row['temps_service'] * 100 / $total, 2);
    }
    $families = [];
    foreach ($rows as $row) {
        $families[$row['famille']][] = $row['taux'];
    }
    $chart = [];
    foreach ($families as $family => $values) {
        $chart[] = ['label' => $family, 'value' => round(array_sum($values) / count($values), 2)];
    }
    ok(['rows' => $rows, 'chart' => $chart, 'global' => $rows ? round(array_sum(array_column($rows, 'taux')) / count($rows), 2) : 0]);
}

if ($action === 'trp') {
    $disponibilite = 96.5;
    $performance = 91.2;
    $qualite = 98.1;
    ok([
        'cards' => [
            'disponibilite' => $disponibilite,
            'performance' => $performance,
            'qualite' => $qualite,
            'trp' => round($disponibilite * $performance * $qualite / 10000, 2),
        ],
        'chart' => [
            ['label' => 'Jan', 'value' => 82.4],
            ['label' => 'Fev', 'value' => 84.1],
            ['label' => 'Mar', 'value' => 85.8],
            ['label' => 'Avr', 'value' => 86.3],
            ['label' => 'Mai', 'value' => 87.1],
            ['label' => 'Juin', 'value' => 88.0],
        ],
    ]);
}

if ($action === 'utilisation') {
    $rows = $conn->query("SELECT e.nom, e.famille, COUNT(c.id) + COUNT(p.id) AS interventions,
        ROUND(120 + (COUNT(c.id) * 8) + (COUNT(p.id) * 3), 2) AS heures
        FROM equipements e
        LEFT JOIN correctives c ON c.equipement_id = e.id
        LEFT JOIN preventives p ON p.equipement_id = e.id
        GROUP BY e.id, e.nom, e.famille
        ORDER BY interventions DESC")->fetchAll();
    $total = array_sum(array_column($rows, 'heures')) ?: 1;
    foreach ($rows as &$row) {
        $row['taux'] = round($row['heures'] * 100 / $total, 2);
    }
    $families = [];
    foreach ($rows as $row) {
        $families[$row['famille']] = ($families[$row['famille']] ?? 0) + $row['heures'];
    }
    $chart = [];
    foreach ($families as $family => $hours) {
        $chart[] = ['label' => $family, 'value' => round($hours, 2)];
    }
    ok(['rows' => $rows, 'chart' => $chart]);
}

fail('Action inconnue', 404);
