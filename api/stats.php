<?php
require_once __DIR__ . '/_bootstrap.php';

$action = $_GET['action'] ?? '';

if ($action === 'disponibilite') {
    $columns = [
        'date_heure_debut' => 'DATETIME NULL',
        'date_heure_fin' => 'DATETIME NULL',
        'temps_arret_minutes' => 'INT NULL',
        'temps_disponibilite_minutes' => 'INT NULL',
    ];
    $columnCheck = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'correctives' AND COLUMN_NAME = ?");
    foreach ($columns as $column => $definition) {
        $columnCheck->execute([$column]);
        if ((int) $columnCheck->fetchColumn() === 0) {
            $conn->exec("ALTER TABLE correctives ADD COLUMN $column $definition");
        }
    }

    $dateDebut = $_GET['du'] ?? date('Y-m-01');
    $dateFin = $_GET['au'] ?? date('Y-m-d');
    $famille = $_GET['famille'] ?? '';
    $service = $_GET['service'] ?? '';
    $statut = $_GET['statut'] ?? '';

    $debut = new DateTime($dateDebut);
    $fin = new DateTime($dateFin);
    if ($fin < $debut) {
        fail('La date de fin doit etre apres la date de debut', 422);
    }
    $joursPeriode = max(1, (int) $debut->diff($fin)->days);
    $tempsTotalMinutes = $joursPeriode * 1440;

    $sql = "SELECT
          e.id,
          e.nom,
          e.famille,
          e.service,
          e.zone,
          e.statut,
          e.date_arret,
          COUNT(c.id) AS nb_pannes,
          COALESCE(SUM(c.temps_arret_minutes), 0) AS duree_hs_minutes,
          ? AS temps_total_minutes,
          (SELECT COUNT(*) FROM equipements WHERE en_service = 1) AS total_actifs
        FROM equipements e
        LEFT JOIN correctives c
          ON c.equipement_id = e.id
          AND c.date_heure_debut >= ?
          AND c.date_heure_debut < DATE_ADD(?, INTERVAL 1 DAY)
        WHERE e.en_service = 1
          AND (? = '' OR e.famille = ?)
          AND (? = '' OR e.service = ?)
          AND (? = '' OR e.statut = ?)
        GROUP BY e.id, e.nom, e.famille, e.service, e.zone, e.statut, e.date_arret
        ORDER BY e.nom";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $tempsTotalMinutes,
        $dateDebut,
        $dateFin,
        $famille,
        $famille,
        $service,
        $service,
        $statut,
        $statut,
    ]);
    $rows = $stmt->fetchAll();

    function minutesEnDuree($minutes) {
        $minutes = max(0, (int) $minutes);
        $j = floor($minutes / 1440);
        $h = floor(($minutes % 1440) / 60);
        $m = $minutes % 60;
        if ($j > 0) return "{$j}j {$h}h {$m}min";
        if ($h > 0) return "{$h}h {$m}min";
        return "{$m}min";
    }

    foreach ($rows as &$row) {
        $total = (int) $row['temps_total_minutes'];
        $hs = (int) $row['duree_hs_minutes'];
        $totalActifs = (int) ($row['total_actifs'] ?: 1);

        if ($row['statut'] === 'HS' && $row['date_arret']) {
            $arret = new DateTime($row['date_arret']);
            $finArret = new DateTime($dateFin);
            if ($arret <= $finArret) {
                $diff = $arret->diff($finArret);
                $hs += ($diff->days * 1440) + ($diff->h * 60) + $diff->i;
            }
        }

        $hs = min($hs, $total);
        $dispo = $total - $hs;
        $nb = (int) $row['nb_pannes'];

        $row['duree_hs_minutes'] = $hs;
        $row['temps_service_minutes'] = $dispo;
        $row['taux_disponibilite'] = $total > 0 ? round(($dispo / $total) * 100, 2) : 100;
        $row['taux_pannes'] = round(($nb / $totalActifs) * 100, 2);
        $row['duree_hs_formate'] = minutesEnDuree($hs);
        $row['temps_service_formate'] = minutesEnDuree($dispo);
        $row['temps_total_formate'] = minutesEnDuree($total);
        $row['critique'] = $row['taux_disponibilite'] < 70;
    }
    unset($row);

    usort($rows, fn($a, $b) => $a['taux_disponibilite'] <=> $b['taux_disponibilite']);

    ok([
        'rows' => $rows,
        'familles' => $conn->query('SELECT DISTINCT famille FROM equipements WHERE en_service = 1 AND famille IS NOT NULL ORDER BY famille')->fetchAll(PDO::FETCH_COLUMN),
        'services' => $conn->query('SELECT DISTINCT service FROM equipements WHERE en_service = 1 AND service IS NOT NULL ORDER BY service')->fetchAll(PDO::FETCH_COLUMN),
    ]);
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
    $conn->exec("CREATE TABLE IF NOT EXISTS services (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nom VARCHAR(100) NOT NULL UNIQUE,
      categorie CHAR(1) DEFAULT 'B',
      aeroport VARCHAR(100)
    )");
    $stmt = $conn->prepare("INSERT INTO services (nom, categorie, aeroport) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE categorie = VALUES(categorie), aeroport = VALUES(aeroport)");
    foreach ([
        ['ESU', 'A', 'Mohammed V'],
        ['Agadir', 'B', 'Agadir'],
        ['Fes', 'B', 'Fes'],
        ['Oujda', 'B', 'Oujda'],
        ['Marrakech', 'B', 'Marrakech'],
        ['Tanger', 'B', 'Tanger'],
        ['Rabat', 'C', 'Rabat'],
    ] as $service) {
        $stmt->execute($service);
    }

    $du = $_GET['du'] ?? date('Y-m-01');
    $au = $_GET['au'] ?? date('Y-m-t');
    $sql = "SELECT
          s.categorie,
          s.aeroport,
          COUNT(DISTINCT u.id) AS nb_users,
          COUNT(DISTINCT CASE WHEN u.role = 'admin' THEN u.id END) AS nb_admins,
          COUNT(DISTINCT e.zone) AS nb_zones,
          COUNT(DISTINCT e.id) AS nb_equipements,
          COUNT(DISTINCT c.id) AS nb_anomalies,
          COUNT(DISTINCT CASE WHEN c.id IS NOT NULL THEN c.id END) AS nb_correctives,
          COUNT(DISTINCT p.id) AS nb_preventives,
          (COUNT(DISTINCT c.id) + COUNT(DISTINCT p.id)) AS total_interventions,
          COUNT(DISTINCT p.id) AS planifiees,
          COUNT(DISTINCT CASE WHEN p.etat IN ('Réalisé', 'Realise') THEN p.id END) AS realisees,
          CASE WHEN COUNT(DISTINCT p.id) > 0
            THEN ROUND(COUNT(DISTINCT CASE WHEN p.etat IN ('Réalisé', 'Realise') THEN p.id END) * 100.0 / COUNT(DISTINCT p.id), 0)
            ELSE NULL
          END AS trp
        FROM services s
        LEFT JOIN users u ON u.service = s.nom
        LEFT JOIN equipements e ON e.service = s.nom
        LEFT JOIN correctives c
          ON c.equipement_id = e.id
          AND COALESCE(c.date_heure_debut, c.date_declaration) BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        LEFT JOIN preventives p
          ON p.equipement_id = e.id
          AND p.date_prevue BETWEEN ? AND ?
        GROUP BY s.categorie, s.aeroport
        ORDER BY s.categorie, s.aeroport";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$du, $au, $du, $au]);
    ok(['rows' => $stmt->fetchAll()]);
}

fail('Action inconnue', 404);
