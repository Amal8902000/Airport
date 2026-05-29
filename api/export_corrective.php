<?php
require_once __DIR__ . '/_bootstrap.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'PHPWord n est pas installe. Lancez composer require phpoffice/phpword dans le dossier gmao-onda.';
    exit;
}

require_once $autoload;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

function minutesEnDureeExport(?int $minutes): string
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

function nomFichier(string $value): string
{
    return preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($value));
}

function correctiveQueryBase(): string
{
    return 'SELECT c.*, e.nom AS equipement, e.service, e.famille, e.zone, e.code
        FROM correctives c
        LEFT JOIN equipements e ON e.id = c.equipement_id';
}

function correctiveFilters(array $source, array &$params): array
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

function envoyerDocx(PhpWord $phpWord, string $filename): void
{
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    IOFactory::createWriter($phpWord, 'Word2007')->save('php://output');
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'individuel') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $conn->prepare(correctiveQueryBase() . ' WHERE c.id = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        http_response_code(404);
        echo 'Anomalie introuvable';
        exit;
    }

    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    $logo = __DIR__ . '/../assets/img/logo-onda.png';
    if (file_exists($logo)) {
        $section->addImage($logo, ['width' => 90]);
    }
    $section->addText("FICHE D'ANOMALIE - MAINTENANCE CORRECTIVE", ['bold' => true, 'size' => 15], ['alignment' => Jc::CENTER]);
    $section->addText('Office National Des Aeroports - GMAO', ['size' => 11], ['alignment' => Jc::CENTER]);
    $section->addText('Date export: ' . date('d/m/Y H:i'));
    $section->addText(str_repeat('_', 70));

    $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 90]);
    $rows = [
        ['Equipement', $item['equipement']],
        ['Zone / Service', ($item['zone'] ?? '') . ' / ' . ($item['service'] ?? '')],
        ['Famille', $item['famille']],
        ['Date de panne', $item['date_heure_debut'] ?: $item['date_declaration']],
        ['Date de cloture', $item['date_heure_fin'] ?: $item['date_resolution']],
        ["Duree d'arret", minutesEnDureeExport($item['temps_arret_minutes'] !== null ? (int) $item['temps_arret_minutes'] : null)],
        ['Disponibilite', minutesEnDureeExport($item['temps_disponibilite_minutes'] !== null ? (int) $item['temps_disponibilite_minutes'] : null)],
        ['Priorite', $item['priorite']],
        ['Declarant', $item['declarant']],
        ['Technicien', $item['technicien']],
        ['Statut', $item['statut']],
        ['Description', $item['description']],
        ['Remarques', $item['remarques']],
    ];
    foreach ($rows as [$label, $value]) {
        $table->addRow();
        $table->addCell(3000)->addText($label, ['bold' => true]);
        $table->addCell(6500)->addText((string) $value);
    }

    $section->addTextBreak();
    $section->addText('Document genere automatiquement par GMAO ONDA');
    $section->addText('Confidentiel - Usage interne');
    envoyerDocx($phpWord, 'Anomalie_' . $id . '_' . nomFichier($item['equipement']) . '_' . date('Ymd') . '.docx');
}

if ($action === 'liste') {
    $params = [];
    $where = correctiveFilters($_GET, $params);
    $sql = correctiveQueryBase() . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY COALESCE(c.date_heure_debut, c.date_declaration) DESC';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    $logo = __DIR__ . '/../assets/img/logo-onda.png';
    if (file_exists($logo)) {
        $section->addImage($logo, ['width' => 90]);
    }
    $section->addText("RAPPORT D'ANOMALIES - MAINTENANCE CORRECTIVE", ['bold' => true, 'size' => 15], ['alignment' => Jc::CENTER]);
    $section->addText('Periode: Du ' . ($_GET['du'] ?? '') . ' Au ' . ($_GET['au'] ?? ''));
    $section->addText("Nombre d'anomalies: " . count($items));
    $section->addText('Filtres appliques: statut=' . ($_GET['statut'] ?? 'Tous') . ', priorite=' . ($_GET['priorite'] ?? 'Tous') . ', service=' . ($_GET['service'] ?? 'Tous'));

    $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 70]);
    $headers = ['N°', 'Equipement', 'Service', 'Priorite', 'Declarant', 'Date panne', 'Date cloture', 'Duree arret', 'Statut', 'Technicien'];
    $table->addRow();
    foreach ($headers as $header) {
        $table->addCell(1200, ['bgColor' => '1A5276'])->addText($header, ['bold' => true, 'color' => 'FFFFFF']);
    }
    foreach ($items as $index => $item) {
        $bg = $index % 2 === 0 ? 'FFFFFF' : 'F4F6F7';
        $table->addRow();
        $values = [
            $index + 1,
            $item['equipement'],
            $item['service'],
            $item['priorite'],
            $item['declarant'],
            $item['date_heure_debut'] ?: $item['date_declaration'],
            $item['date_heure_fin'] ?: $item['date_resolution'],
            minutesEnDureeExport($item['temps_arret_minutes'] !== null ? (int) $item['temps_arret_minutes'] : null),
            $item['statut'],
            $item['technicien'],
        ];
        foreach ($values as $cellIndex => $value) {
            $style = [];
            if ($cellIndex === 3 && $value === 'Urgente') {
                $style = ['bold' => true, 'color' => 'E74C3C'];
            }
            if ($cellIndex === 8) {
                $style = ['color' => in_array($value, ['Clos', 'Clôturé'], true) ? '27AE60' : 'E74C3C'];
            }
            $table->addCell(1200, ['bgColor' => $bg])->addText((string) $value, $style);
        }
    }

    $footer = $section->addFooter();
    $name = trim(($_SESSION['user']['prenom'] ?? '') . ' ' . ($_SESSION['user']['nom'] ?? '')) ?: ($_SESSION['user']['email'] ?? 'Utilisateur');
    $footer->addText('Rapport genere le ' . date('d/m/Y H:i') . ' par ' . $name);
    $footer->addPreserveText('Page {PAGE} / {NUMPAGES}', null, ['alignment' => Jc::CENTER]);

    envoyerDocx($phpWord, 'Rapport_Anomalies_' . ($_GET['du'] ?? date('Ymd')) . '_' . ($_GET['au'] ?? date('Ymd')) . '.docx');
}

http_response_code(404);
echo 'Action inconnue';
