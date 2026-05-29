<?php
require_once __DIR__ . '/_bootstrap.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'PhpSpreadsheet n est pas installe. Lancez composer require phpoffice/phpspreadsheet dans le dossier gmao-onda.';
    exit;
}

require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function minutesEnDureeExcel(?int $minutes): string
{
    if ($minutes === null) {
        return '';
    }
    $j = intdiv($minutes, 1440);
    $h = intdiv($minutes % 1440, 60);
    $m = $minutes % 60;
    if ($j > 0) return "{$j}j {$h}h {$m}min";
    if ($h > 0) return "{$h}h {$m}min";
    return "{$m}min";
}

function filterWhereExcel(array $source, array &$params): array
{
    $where = [];
    if (!empty($source['service'])) {
        $where[] = 'e.service = ?';
        $params[] = $source['service'];
    }
    if (!empty($source['ticket'])) {
        $where[] = 'c.ticket LIKE ?';
        $params[] = '%' . $source['ticket'] . '%';
    }
    if (!empty($source['equipement'])) {
        $where[] = 'e.nom LIKE ?';
        $params[] = '%' . $source['equipement'] . '%';
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

$params = [];
$where = filterWhereExcel($_GET, $params);
$sql = 'SELECT c.*, e.nom AS equipement, e.famille, e.service
    FROM correctives c
    LEFT JOIN equipements e ON e.id = c.equipement_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY COALESCE(c.date_heure_debut, c.date_declaration) DESC';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$headers = ['Ticket', 'Equipement', 'Famille', 'Service', 'Declarant', 'Technicien', 'Description', 'Priorite', 'Statut', 'Date debut', 'Date fin', 'Duree arret', 'Disponibilite'];
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:M1')->getFont()->setBold(true);

$line = 2;
foreach ($rows as $row) {
    $ticket = $row['ticket'] ?: ('TKT-' . substr((string) $row['date_declaration'], 0, 4) . '-' . str_pad((string) $row['id'], 4, '0', STR_PAD_LEFT));
    $sheet->fromArray([
        $ticket,
        $row['equipement'],
        $row['famille'],
        $row['service'],
        $row['declarant'],
        $row['technicien'],
        $row['description'],
        $row['priorite'],
        $row['statut'],
        $row['date_heure_debut'] ?: $row['date_declaration'],
        $row['date_heure_fin'] ?: $row['date_resolution'],
        minutesEnDureeExcel($row['temps_arret_minutes'] !== null ? (int) $row['temps_arret_minutes'] : null),
        minutesEnDureeExcel($row['temps_disponibilite_minutes'] !== null ? (int) $row['temps_disponibilite_minutes'] : null),
    ], null, 'A' . $line);
    $line++;
}

foreach (range('A', 'M') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

$du = $_GET['du'] ?? date('Y-m-d');
$au = $_GET['au'] ?? date('Y-m-d');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Anomalies_' . $du . '_' . $au . '.xlsx"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;
