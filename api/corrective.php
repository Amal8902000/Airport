<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../config/mail.php';

$body = json_body();
$action = $_GET['action'] ?? ($_POST['action'] ?? ($body['action'] ?? ''));

function corrective_column_exists(PDO $conn, string $column): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'correctives' AND COLUMN_NAME = ?");
    $stmt->execute([$column]);
    return (int) $stmt->fetchColumn() > 0;
}

function ensure_corrective_schema(PDO $conn): void
{
    $columns = [
        'date_heure_debut' => 'DATETIME NULL',
        'date_heure_fin' => 'DATETIME NULL',
        'temps_arret_minutes' => 'INT NULL',
        'temps_disponibilite_minutes' => 'INT NULL',
        'ticket' => 'VARCHAR(20) NULL',
    ];

    $existing = array_map(fn($row) => $row['Field'], $conn->query('SHOW COLUMNS FROM correctives')->fetchAll(PDO::FETCH_ASSOC));
    foreach ($columns as $column => $definition) {
        if (in_array($column, $existing, true)) {
            continue;
        }
        try {
            $conn->exec("ALTER TABLE correctives ADD COLUMN $column $definition");
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 1060) {
                throw $e;
            }
        }
    }

    if (in_array('ticket', array_keys($columns), true)) {
        $items = $conn->query("SELECT id, YEAR(date_declaration) AS annee FROM correctives WHERE ticket IS NULL OR ticket = '' ORDER BY date_declaration, id")->fetchAll(PDO::FETCH_ASSOC);
        $counters = [];
        $existingTickets = $conn->query("SELECT ticket FROM correctives WHERE ticket IS NOT NULL AND ticket <> ''")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($existingTickets as $ticket) {
            if (preg_match('/^TKT-(\d{4})-(\d{4})$/', $ticket, $matches)) {
                $counters[$matches[1]] = max($counters[$matches[1]] ?? 0, (int) $matches[2]);
            }
        }
        $update = $conn->prepare('UPDATE correctives SET ticket = ? WHERE id = ?');
        foreach ($items as $item) {
            $annee = $item['annee'] ?: date('Y');
            $counters[$annee] = ($counters[$annee] ?? 0) + 1;
            $ticket = "TKT-{$annee}-" . str_pad((string) $counters[$annee], 4, '0', STR_PAD_LEFT);
            $update->execute([$ticket, $item['id']]);
        }
    }
}

function calculerTempsIntervention(?string $dateHeureDebut, ?string $dateHeureFin): array
{
    if (!$dateHeureDebut || !$dateHeureFin) {
        return [null, null];
    }

    $debut = new DateTime($dateHeureDebut);
    $fin = new DateTime($dateHeureFin);
    if ($fin < $debut) {
        fail('La date de fin doit etre apres la date de debut', 422);
    }

    $diff = $debut->diff($fin);
    $tempsArretMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    $joursConcernes = $diff->days + 1;
    $tempsTotal = $joursConcernes * 1440;
    $tempsDisponibiliteMinutes = max(0, $tempsTotal - $tempsArretMinutes);

    return [$tempsArretMinutes, $tempsDisponibiliteMinutes];
}

function minutesEnDuree(?int $minutes): string
{
    if ($minutes === null) {
        return '';
    }

    $j = intdiv($minutes, 1440);
    $h = intdiv($minutes % 1440, 60);
    $m = $minutes % 60;

    if ($j > 0) {
        return "{$j}j {$h}h {$m}min";
    }
    if ($h > 0) {
        return "{$h}h {$m}min";
    }
    return "{$m}min";
}

function genererTicket(PDO $conn): string
{
    $annee = date('Y');
    $stmt = $conn->query("SELECT COUNT(*) FROM correctives WHERE YEAR(date_declaration) = $annee");
    $n = (int) $stmt->fetchColumn() + 1;
    return "TKT-{$annee}-" . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
}

function envoyerNotificationPanne(string $technicienEmail, array $data): bool
{
    if ($technicienEmail === '') {
        return false;
    }

    $sujet = 'Nouvelle panne declaree - ' . $data['equipement_nom'];
    $corps = "Bonjour,\n\n"
        . "Une nouvelle anomalie vient d'etre declaree et vous a ete assignee.\n\n"
        . "DETAILS DE L'ANOMALIE\n"
        . "Equipement  : " . $data['equipement_nom'] . "\n"
        . "Zone/Service: " . $data['service'] . "\n"
        . "Priorite    : " . $data['priorite'] . "\n"
        . "Declarant   : " . $data['declarant'] . "\n"
        . "Date/Heure  : " . $data['date_heure_debut'] . "\n"
        . "Description : " . $data['description'] . "\n\n"
        . "Merci de prendre en charge cette intervention.\n\n"
        . "GMAO ONDA - Systeme de gestion de maintenance\n";

    $headers = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();

    return mail($technicienEmail, $sujet, $corps, $headers);
}

function corrective_base_query(): string
{
    return 'SELECT c.*, e.nom AS equipement, e.service, e.famille, e.zone, e.code
        FROM correctives c
        LEFT JOIN equipements e ON e.id = c.equipement_id';
}

function build_corrective_filters(array $source, array &$params): array
{
    $where = [];
    foreach (['priorite' => 'c.priorite', 'service' => 'e.service'] as $key => $column) {
        if (!empty($source[$key])) {
            $where[] = "$column = ?";
            $params[] = $source[$key];
        }
    }
    if (!empty($source['statut'])) {
        if ($source['statut'] === 'Clôturé' || $source['statut'] === 'Cloture') {
            $where[] = "(c.statut = 'Clôturé' OR c.statut = 'Clos')";
        } else {
            $where[] = 'c.statut = ?';
            $params[] = $source['statut'];
        }
    }
    if (!empty($source['equipement'])) {
        $where[] = 'e.nom LIKE ?';
        $params[] = '%' . $source['equipement'] . '%';
    }
    if (!empty($source['ticket'])) {
        $where[] = 'c.ticket LIKE ?';
        $params[] = '%' . $source['ticket'] . '%';
    }
    if (!empty($source['hs']) && (int) $source['hs'] === 1) {
        $where[] = 'e.statut = ?';
        $params[] = 'HS';
    }
    if (!empty($source['ok']) && (int) $source['ok'] === 1) {
        $where[] = 'e.statut = ?';
        $params[] = 'OK';
    }
    if (!empty($source['du'])) {
        $where[] = 'DATE(COALESCE(c.date_heure_debut, c.date_declaration)) >= ?';
        $params[] = $source['du'];
    }
    if (!empty($source['au'])) {
        $where[] = 'DATE(COALESCE(c.date_heure_debut, c.date_declaration)) <= ?';
        $params[] = $source['au'];
    }
    return $where;
}

ensure_corrective_schema($conn);

if ($action === 'meta') {
    $equipements = $conn->query('SELECT id, nom, service, zone, famille FROM equipements ORDER BY nom')->fetchAll();
    $techniciens = $conn->query("SELECT nom, prenom, email FROM users WHERE role IN ('technicien', 'responsable', 'superviseur') ORDER BY prenom, nom")->fetchAll();
    ok(['equipements' => $equipements, 'techniciens' => $techniciens]);
}

if ($action === 'liste') {
    $params = [];
    $where = build_corrective_filters($_GET, $params);
    $sql = corrective_base_query()
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY COALESCE(c.date_heure_debut, c.date_declaration) DESC';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    foreach ($items as &$item) {
        $item['temps_arret_format'] = minutesEnDuree($item['temps_arret_minutes'] !== null ? (int) $item['temps_arret_minutes'] : null);
        $item['temps_disponibilite_format'] = minutesEnDuree($item['temps_disponibilite_minutes'] !== null ? (int) $item['temps_disponibilite_minutes'] : null);
    }
    ok(['correctives' => $items]);
}

if ($action === 'stats') {
    $ouvert = (int) $conn->query("SELECT COUNT(*) FROM correctives WHERE statut = 'Ouvert'")->fetchColumn();
    $clos = (int) $conn->query("SELECT COUNT(*) FROM correctives WHERE statut IN ('Clos', 'Clôturé')")->fetchColumn();
    ok(['stats' => ['ouvert' => $ouvert, 'clos' => $clos]]);
}

if ($action === 'ajouter' || $action === 'modifier') {
    require_roles(['admin', 'responsable', 'superviseur', 'technicien', 'agent_exploitation']);

    if (empty($body['equipement_id']) || empty($body['description']) || empty($body['date_heure_debut'])) {
        fail('Equipement, description et debut de panne obligatoires', 422);
    }

    [$tempsArret, $tempsDisponibilite] = calculerTempsIntervention($body['date_heure_debut'], $body['date_heure_fin'] ?? null);
    $equipementStmt = $conn->prepare('SELECT nom, service FROM equipements WHERE id = ?');
    $equipementStmt->execute([(int) $body['equipement_id']]);
    $equipement = $equipementStmt->fetch() ?: ['nom' => '', 'service' => $body['service'] ?? 'ESU'];
    $statut = !empty($body['date_heure_fin']) ? 'Clôturé' : 'Ouvert';

    if ($action === 'ajouter') {
        $stmt = $conn->prepare('INSERT INTO correctives (equipement_id, date_declaration, declarant, description, priorite, statut, date_resolution, technicien, duree_heures, remarques, date_heure_debut, date_heure_fin, temps_arret_minutes, temps_disponibilite_minutes, ticket) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            (int) $body['equipement_id'],
            $body['date_heure_debut'],
            $body['declarant'] ?? '',
            $body['description'],
            $body['priorite'] ?? 'Normale',
            $statut,
            !empty($body['date_heure_fin']) ? $body['date_heure_fin'] : null,
            $body['technicien'] ?? '',
            $tempsArret !== null ? round($tempsArret / 60, 2) : null,
            $body['remarques'] ?? '',
            $body['date_heure_debut'],
            !empty($body['date_heure_fin']) ? $body['date_heure_fin'] : null,
            $tempsArret,
            $tempsDisponibilite,
            genererTicket($conn),
        ]);
        $id = (int) $conn->lastInsertId();

        $emailOk = envoyerNotificationPanne($body['technicien_email'] ?? '', [
            'equipement_nom' => $equipement['nom'],
            'service' => $equipement['service'],
            'priorite' => $body['priorite'] ?? 'Normale',
            'declarant' => $body['declarant'] ?? '',
            'date_heure_debut' => $body['date_heure_debut'],
            'description' => $body['description'],
        ]);

        ok([
            'message' => 'Panne declaree avec succes',
            'id' => $id,
            'email_envoye' => $emailOk,
            'technicien_email' => $body['technicien_email'] ?? '',
        ]);
    }

    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        fail('Identifiant manquant', 422);
    }

    $stmt = $conn->prepare('UPDATE correctives SET equipement_id = ?, date_declaration = ?, declarant = ?, description = ?, priorite = ?, statut = ?, date_resolution = ?, technicien = ?, duree_heures = ?, remarques = ?, date_heure_debut = ?, date_heure_fin = ?, temps_arret_minutes = ?, temps_disponibilite_minutes = ? WHERE id = ?');
    $stmt->execute([
        (int) $body['equipement_id'],
        $body['date_heure_debut'],
        $body['declarant'] ?? '',
        $body['description'],
        $body['priorite'] ?? 'Normale',
        $statut,
        !empty($body['date_heure_fin']) ? $body['date_heure_fin'] : null,
        $body['technicien'] ?? '',
        $tempsArret !== null ? round($tempsArret / 60, 2) : null,
        $body['remarques'] ?? '',
        $body['date_heure_debut'],
        !empty($body['date_heure_fin']) ? $body['date_heure_fin'] : null,
        $tempsArret,
        $tempsDisponibilite,
        $id,
    ]);
    ok(['message' => 'Panne modifiee avec succes']);
}

if ($action === 'cloturer') {
    require_roles(['admin', 'responsable', 'superviseur', 'technicien']);

    $id = (int) ($_GET['id'] ?? $body['id'] ?? 0);
    if ($id <= 0 || empty($body['date_heure_fin'])) {
        fail('Identifiant et date de cloture obligatoires', 422);
    }

    $stmt = $conn->prepare('SELECT date_heure_debut, date_declaration FROM correctives WHERE id = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        fail('Anomalie introuvable', 404);
    }

    $debut = $item['date_heure_debut'] ?: $item['date_declaration'];
    [$tempsArret, $tempsDisponibilite] = calculerTempsIntervention($debut, $body['date_heure_fin']);
    $stmt = $conn->prepare("UPDATE correctives SET statut = 'Clôturé', date_resolution = NOW(), date_heure_fin = ?, temps_arret_minutes = ?, temps_disponibilite_minutes = ?, duree_heures = ?, technicien = ?, remarques = ? WHERE id = ?");
    $stmt->execute([
        $body['date_heure_fin'],
        $tempsArret,
        $tempsDisponibilite,
        round($tempsArret / 60, 2),
        $body['technicien'] ?? '',
        trim(($body['details'] ?? '') . "\n" . ($body['remarques'] ?? '')),
        $id,
    ]);
    ok(['message' => 'Intervention cloturee avec succes']);
}

fail('Action inconnue', 404);
