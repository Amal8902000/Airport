<?php
require_once __DIR__ . '/_bootstrap.php';

$body = json_body();
$action = $_GET['action'] ?? ($_POST['action'] ?? ($body['action'] ?? ''));

function ajusterWeekend(DateTime $date, bool $eviter): string
{
    if (!$eviter) {
        return $date->format('Y-m-d');
    }

    $dow = (int) $date->format('N');
    if ($dow === 6) {
        $date->modify('+2 days');
    }
    if ($dow === 7) {
        $date->modify('+1 day');
    }

    return $date->format('Y-m-d');
}

function monthlySameWeekday(DateTime $start, int $monthsToAdd): DateTime
{
    $target = (clone $start)->modify('first day of this month')->modify('+' . $monthsToAdd . ' months');
    $weekday = (int) $start->format('N');
    $weekNumber = (int) ceil(((int) $start->format('j')) / 7);
    $matches = [];
    $daysInMonth = (int) $target->format('t');

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $candidate = (clone $target)->setDate((int) $target->format('Y'), (int) $target->format('m'), $day);
        if ((int) $candidate->format('N') === $weekday) {
            $matches[] = $candidate;
        }
    }

    return clone ($matches[$weekNumber - 1] ?? $matches[count($matches) - 1]);
}

function genererDates(string $dateDebut, string $periodicite, bool $eviterWeekend, bool $memeJourSemaine, bool $uneSeuleJournee): array
{
    $dates = [];
    $start = new DateTime($dateDebut);
    $dateFin = (clone $start)->modify('+1 year');

    if ($uneSeuleJournee || $periodicite === 'Annuelle') {
        return [ajusterWeekend(clone $start, $eviterWeekend)];
    }

    if ($memeJourSemaine && $periodicite === 'Mensuelle') {
        for ($month = 0; $month < 12; $month++) {
            $current = monthlySameWeekday($start, $month);
            $dates[] = ajusterWeekend($current, $eviterWeekend);
        }
        return array_values(array_unique($dates));
    }

    $intervals = [
        'Hebdomadaire' => '+7 days',
        'Mensuelle' => '+1 month',
        'Trimestrielle' => '+3 months',
        'Semestrielle' => '+6 months',
    ];
    if (!isset($intervals[$periodicite])) {
        fail('Periodicite invalide', 422);
    }

    $current = clone $start;
    while ($current < $dateFin) {
        $dates[] = ajusterWeekend(clone $current, $eviterWeekend);
        $current->modify($intervals[$periodicite]);
    }

    return array_values(array_unique($dates));
}

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
    $sql = "SELECT
          COUNT(*) AS planifiees,
          SUM(CASE WHEN p.etat IN ('Réalisé', 'Realise') THEN 1 ELSE 0 END) AS realisees,
          SUM(CASE WHEN p.etat IN ('Réalisé', 'Realise') AND (p.date_realisation IS NULL OR p.date_realisation <= p.date_prevue) THEN 1 ELSE 0 END) AS a_temps
        FROM preventives p
        JOIN equipements e ON e.id = p.equipement_id"
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch() ?: [];
    $planifiees = (int) ($row['planifiees'] ?? 0);
    $realisees = (int) ($row['realisees'] ?? 0);
    $aTemps = (int) ($row['a_temps'] ?? 0);
    $tauxRealisation = $planifiees > 0 ? round(($realisees / $planifiees) * 100, 2) : 0;
    $tauxRespect = $planifiees > 0 ? round(($aTemps / $planifiees) * 100, 2) : 0;
    $enAttente = $planifiees - $realisees;
    if ($enAttente < 0) {
        $enAttente = 0;
    }
    ok([
        'stats' => [
            'planifiees' => $planifiees,
            'en_attente' => $enAttente,
            'realisees' => $realisees,
            'taux_realisation' => $tauxRealisation,
            'a_temps' => $aTemps,
            'taux_respect' => $tauxRespect,
            'attente' => $enAttente,
            'realisation' => $tauxRealisation,
            'respect' => $tauxRespect,
        ],
    ]);
}

if ($action === 'ajouter') {
    require_roles(['admin', 'responsable', 'superviseur']);

    $dateDebut = $body['date_debut'] ?? ($body['date_prevue'] ?? '');
    foreach (['equipement_id', 'type_maintenance'] as $field) {
        if (empty($body[$field])) {
            fail('Champs obligatoires manquants', 422);
        }
    }
    if (empty($dateDebut)) {
        fail('Date de debut obligatoire', 422);
    }

    $uneSeuleJournee = !empty($body['une_seule_journee']);
    $periodicite = $uneSeuleJournee ? ($body['periodicite'] ?? 'Une seule journee') : ($body['periodicite'] ?? '');
    if (!$uneSeuleJournee && $periodicite === '') {
        fail('Periodicite obligatoire', 422);
    }

    try {
        $dates = genererDates(
            $dateDebut,
            $periodicite,
            !empty($body['eviter_weekend']),
            !empty($body['meme_jour_semaine']),
            $uneSeuleJournee
        );
    } catch (Exception $e) {
        fail('Date de debut invalide', 422);
    }

    $stmt = $conn->prepare("INSERT INTO preventives (equipement_id, type_maintenance, periodicite, date_prevue, etat, nuit, service) VALUES (?, ?, ?, ?, 'En attente', ?, ?)");
    foreach ($dates as $datePrevue) {
        $stmt->execute([
            (int) $body['equipement_id'],
            $body['type_maintenance'],
            $periodicite,
            $datePrevue,
            !empty($body['nuit']) ? 1 : 0,
            $body['service'] ?? 'ESU',
        ]);
    }

    ok([
        'nb_dates_generees' => count($dates),
        'dates' => $dates,
    ]);
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
