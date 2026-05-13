<?php
$host = 'localhost';
$dbname = 'gmao_onda';
$user = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8 COLLATE utf8_general_ci");
    $pdo->exec("USE `$dbname`");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nom VARCHAR(100),
      prenom VARCHAR(100),
      email VARCHAR(100) UNIQUE NOT NULL,
      password_hash TEXT NOT NULL,
      role VARCHAR(50) DEFAULT 'technicien',
      service VARCHAR(100),
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS equipements (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nom VARCHAR(200) NOT NULL,
      code VARCHAR(100),
      marque VARCHAR(100),
      numero_serie VARCHAR(100),
      prix_acquisition DECIMAL(15,2),
      mode_integre TINYINT(1) DEFAULT 0,
      famille VARCHAR(100),
      zone VARCHAR(100),
      service VARCHAR(100),
      installation TEXT,
      mise_en_service DATE,
      date_remplacement_prevu DATE,
      date_arret DATE,
      en_service TINYINT(1) DEFAULT 1,
      statut VARCHAR(50) DEFAULT 'OK',
      remarques TEXT,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS correctives (
      id INT AUTO_INCREMENT PRIMARY KEY,
      equipement_id INT,
      date_declaration TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      declarant VARCHAR(100),
      description TEXT,
      priorite VARCHAR(50),
      statut VARCHAR(50) DEFAULT 'Ouvert',
      date_resolution TIMESTAMP NULL,
      technicien VARCHAR(100),
      duree_heures DECIMAL(5,2),
      remarques TEXT,
      FOREIGN KEY (equipement_id) REFERENCES equipements(id) ON DELETE SET NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS preventives (
      id INT AUTO_INCREMENT PRIMARY KEY,
      equipement_id INT,
      type_maintenance VARCHAR(100),
      periodicite VARCHAR(50),
      date_prevue DATE,
      date_realisation DATE NULL,
      etat VARCHAR(50) DEFAULT 'En attente',
      technicien VARCHAR(100),
      details TEXT,
      nuit TINYINT(1) DEFAULT 0,
      service VARCHAR(100),
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (equipement_id) REFERENCES equipements(id) ON DELETE CASCADE
    )");

    $defaultUsers = [
        ['Administrateur', '', 'admin@gmao-onda.local', 'Admin123!', 'admin', 'ESU'],
        ['TIJANI', 'Tarik', 'tarik@gmao-onda.local', 'Tech123!', 'technicien', 'ESU'],
        ['Responsable', 'Maintenance', 'responsable@gmao-onda.local', 'Resp123!', 'responsable', 'ESU'],
        ['Superviseur', 'Exploitation', 'superviseur@gmao-onda.local', 'Sup123!', 'superviseur', 'ESU'],
        ['Agent', 'Exploitation', 'agent@gmao-onda.local', 'Agent123!', 'agent_exploitation', 'ESU'],
    ];
    $findUser = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $insertUser = $pdo->prepare('INSERT INTO users (nom, prenom, email, password_hash, role, service) VALUES (?, ?, ?, ?, ?, ?)');
    $updateUser = $pdo->prepare('UPDATE users SET nom = ?, prenom = ?, password_hash = ?, role = ?, service = ? WHERE email = ?');
    foreach ($defaultUsers as [$nom, $prenom, $email, $plainPassword, $role, $service]) {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $findUser->execute([$email]);
        if ($findUser->fetch()) {
            $updateUser->execute([$nom, $prenom, $hash, $role, $service, $email]);
        } else {
            $insertUser->execute([$nom, $prenom, $email, $hash, $role, $service]);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM equipements')->fetchColumn() === 0) {
        $equipements = [
            ['RAYON X Bagage a soute depart', 'RX-SOUTE-01', 'Rapiscan model 928 DX', '6250705', 520000, 1, 'Rayon X', 'Depart tri bagages', 'ESU', 'Poste inspection bagage soute', '2025-10-01', '2032-10-01', null, 1, 'OK', 'Controle operationnel nominal'],
            ['ETD detecteur de traces des explosifs', 'ETD-01', 'ITEMISER 5X', '25-14355', 280000, 1, 'Detecteurs', 'Depart sous douane', 'ESU', 'Controle surete passagers', '2025-10-01', '2031-10-01', null, 1, 'Maintenance', 'Etalonnage renforce programme'],
            ['Imprimante administration', 'IMP-ADM-01', 'KYOCERA', 'KY-2025-019', 6500, 0, 'Informatique', 'Administration', 'ESU', 'Bureau maintenance', '2025-03-01', '2029-03-01', null, 1, 'OK', 'Toner remplace'],
            ['ECRAN TELEAFFICHAGE depart', 'AFF-DEP-01', 'LG 43UH5J-H', '82651', 18500, 1, 'Electronique', 'Depart sous douane', 'ESU', 'Affichage portes embarquement', '2025-02-01', '2030-02-01', null, 1, 'OK', 'Firmware a jour'],
            ['Balisage B01 seuil piste', 'BAL-B01', 'Electricite balisage', 'B01-2024', 42000, 1, 'Balisage', 'ESU', 'ESU', 'Feu seuil piste', '2024-07-12', '2034-07-12', null, 1, 'OK', 'Controle photometrique conforme'],
            ['Telephonie autocommutateur', 'TEL-PBX-01', 'Alcatel-Lucent', 'PBX-5591', 165000, 1, 'Telecoms', 'ESU', 'ESU', 'Salle technique telecom', '2022-09-15', '2030-09-15', null, 1, 'Maintenance', 'Redondance a verifier'],
            ['Serveur supervision GMAO', 'SRV-GMAO-01', 'Dell PowerEdge', 'DL-78840', 74000, 1, 'Informatique', 'Administration', 'ESU', 'Baie informatique', '2021-05-03', '2028-05-03', null, 1, 'OK', 'Sauvegardes quotidiennes'],
            ['Scanner bagage cabine ligne 1', 'RX-CAB-01', 'Smiths Detection', 'SDX-1148', 410000, 1, 'Rayon X', 'Depart sous douane', 'ESU', 'Ligne controle surete 1', '2020-11-20', '2028-11-20', null, 1, 'OK', 'Tube RX remplace en 2025'],
            ['Scanner bagage cabine ligne 2', 'RX-CAB-02', 'Smiths Detection', 'SDX-1149', 410000, 1, 'Rayon X', 'Depart sous douane', 'ESU', 'Ligne controle surete 2', '2020-11-20', '2028-11-20', null, 1, 'HS', 'Carte puissance indisponible'],
            ['Detecteur portique passagers P1', 'DPM-P1', 'Garrett', 'GT-3005', 62000, 1, 'Detecteurs', 'Depart sous douane', 'ESU', 'Portique surete P1', '2019-04-18', '2027-04-18', null, 1, 'OK', 'Sensibilite stable'],
            ['Detecteur portique passagers P2', 'DPM-P2', 'Garrett', 'GT-3006', 62000, 1, 'Detecteurs', 'Depart sous douane', 'ESU', 'Portique surete P2', '2019-04-18', '2027-04-18', null, 1, 'Maintenance', 'Controle reglementaire prevu'],
            ['Camera CCTV tri bagages', 'CCTV-TB-01', 'Hikvision', 'HK-7710', 9500, 0, 'Electronique', 'Depart tri bagages', 'ESU', 'Surveillance convoyeurs', '2023-06-09', '2029-06-09', null, 1, 'OK', 'Image claire'],
            ['Switch reseau baie surete', 'SW-SUR-01', 'Cisco', 'CS-9300-22', 36000, 1, 'Informatique', 'ESU', 'ESU', 'Baie surete', '2022-01-24', '2028-01-24', null, 1, 'OK', 'Ports critiques etiquetes'],
            ['Onduleur salle telecom', 'UPS-TEL-01', 'APC', 'APC-10K-02', 58000, 1, 'Electronique', 'ESU', 'ESU', 'Salle telecom', '2021-12-12', '2027-12-12', null, 1, 'Maintenance', 'Batteries a tester'],
            ['Convoyeur bagages entree 1', 'CV-BAG-01', 'Vanderlande', 'VL-5001', 210000, 1, 'Electronique', 'Depart tri bagages', 'ESU', 'Convoyage bagages', '2018-08-30', '2028-08-30', null, 1, 'OK', 'Courroie controlee'],
            ['Convoyeur bagages entree 2', 'CV-BAG-02', 'Vanderlande', 'VL-5002', 210000, 1, 'Electronique', 'Depart tri bagages', 'ESU', 'Convoyage bagages', '2018-08-30', '2028-08-30', null, 1, 'HS', 'Moteur a remplacer'],
            ['Borne check-in C01', 'BCH-C01', 'IER', 'IER-8840', 47000, 1, 'Informatique', 'Depart sous douane', 'ESU', 'Zone enregistrement C', '2020-02-14', '2028-02-14', null, 1, 'OK', 'Lecteur passeport operationnel'],
            ['Borne check-in C02', 'BCH-C02', 'IER', 'IER-8841', 47000, 1, 'Informatique', 'Depart sous douane', 'ESU', 'Zone enregistrement C', '2020-02-14', '2028-02-14', null, 1, 'Maintenance', 'Ecran tactile decalibre'],
            ['Radio VHF piste', 'VHF-PST-01', 'Motorola', 'MT-5100', 32000, 1, 'Telecoms', 'ESU', 'ESU', 'Communication piste', '2017-10-02', '2027-10-02', null, 1, 'OK', 'Essai liaison conforme'],
            ['Antenne radio secours', 'ANT-SEC-01', 'Kathrein', 'KT-4502', 24000, 0, 'Telecoms', 'ESU', 'ESU', 'Toiture bloc technique', '2016-05-21', '2026-05-21', null, 1, 'OK', 'Fixations verifiees'],
            ['Ecran arrivee hall public', 'AFF-ARR-01', 'Samsung', 'SM-5512', 21000, 1, 'Electronique', 'Arrivee', 'ESU', 'Hall arrivee', '2024-01-10', '2030-01-10', null, 1, 'OK', 'Luminosite adaptee'],
            ['Lecteur badge acces ESU', 'ACC-ESU-01', 'HID', 'HID-771', 7800, 0, 'Detecteurs', 'ESU', 'ESU', 'Acces local ESU', '2023-03-16', '2029-03-16', null, 1, 'OK', 'Journal acces synchronise'],
            ['Station meteo technique', 'MET-01', 'Vaisala', 'VS-9022', 98000, 1, 'Electronique', 'ESU', 'ESU', 'Plateforme technique', '2019-09-08', '2029-09-08', null, 1, 'OK', 'Capteurs nettoyes'],
            ['Balisage B02 axe taxiway', 'BAL-B02', 'Electricite balisage', 'B02-2024', 38500, 1, 'Balisage', 'ESU', 'ESU', 'Axe taxiway', '2024-07-12', '2034-07-12', null, 1, 'OK', 'Controle isolement conforme'],
            ['Balisage B03 bord piste', 'BAL-B03', 'Electricite balisage', 'B03-2024', 39500, 1, 'Balisage', 'ESU', 'ESU', 'Bord piste', '2024-07-12', '2034-07-12', null, 1, 'Maintenance', 'Nettoyage optique requis'],
            ['PC supervision affichage', 'PC-AFF-01', 'HP EliteDesk', 'HP-5580', 12500, 0, 'Informatique', 'Administration', 'ESU', 'Poste supervision affichage', '2022-04-07', '2027-04-07', null, 1, 'OK', 'Antivirus a jour'],
            ['Imprimante etiquettes bagages', 'IMP-BAG-01', 'Zebra', 'ZB-9901', 22000, 1, 'Informatique', 'Depart tri bagages', 'ESU', 'Edition etiquettes bagages', '2021-11-28', '2027-11-28', null, 1, 'OK', 'Tete impression nettoyee'],
            ['Routeur WAN aeroport', 'RTR-WAN-01', 'Cisco', 'ISR-4451-44', 69000, 1, 'Telecoms', 'ESU', 'ESU', 'Noeud WAN ONDA', '2020-06-05', '2028-06-05', null, 1, 'OK', 'Lien secondaire actif'],
            ['Portique detection fret', 'DPM-FRET-01', 'CEIA', 'CE-1010', 84000, 1, 'Detecteurs', 'Arrivee', 'ESU', 'Zone controle fret', '2018-12-19', '2028-12-19', null, 1, 'OK', 'Controle mensuel effectue'],
            ['RAYON X fret arrivee', 'RX-FRET-01', 'Rapiscan 632DV', 'RP-632-75', 455000, 1, 'Rayon X', 'Arrivee', 'ESU', 'Inspection fret arrivee', '2019-07-25', '2029-07-25', null, 1, 'Maintenance', 'Ventilation a surveiller'],
        ];
        $stmt = $pdo->prepare('INSERT INTO equipements (nom, code, marque, numero_serie, prix_acquisition, mode_integre, famille, zone, service, installation, mise_en_service, date_remplacement_prevu, date_arret, en_service, statut, remarques) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($equipements as $equipement) {
            $stmt->execute($equipement);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM preventives')->fetchColumn() === 0) {
        $ids = $pdo->query('SELECT id, service FROM equipements ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $types = ['Entretien', 'Etalonnage', 'Controles reglementaires'];
        $periodicites = ['Annuelle', 'Semestrielle', 'Trimestrielle', 'Mensuelle', 'Hebdomadaire'];
        $techniciens = ['e.jerrari', 't.tijani', 'm.elamrani', 's.bennani', 'a.elharti'];
        $stmt = $pdo->prepare('INSERT INTO preventives (equipement_id, type_maintenance, periodicite, date_prevue, date_realisation, etat, technicien, details, nuit, service) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        for ($i = 0; $i < 50; $i++) {
            $eq = $ids[$i % count($ids)];
            $year = 2015 + ($i % 12);
            $month = str_pad((string) (($i % 12) + 1), 2, '0', STR_PAD_LEFT);
            $day = str_pad((string) ((($i * 3) % 24) + 1), 2, '0', STR_PAD_LEFT);
            $datePrevue = "$year-$month-$day";
            $realise = $i % 3 !== 1;
            $dateRealisation = $realise ? date('Y-m-d', strtotime($datePrevue . ' +' . ($i % 5) . ' day')) : null;
            $stmt->execute([
                $eq['id'],
                $types[$i % count($types)],
                $periodicites[$i % count($periodicites)],
                $datePrevue,
                $dateRealisation,
                $realise ? 'Realise' : 'En attente',
                $techniciens[$i % count($techniciens)],
                $realise ? 'Operation realisee et controle final conforme' : 'Action planifiee en attente de realisation',
                $i % 4 === 0 ? 1 : 0,
                $eq['service'],
            ]);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM correctives')->fetchColumn() === 0) {
        $stmt = $pdo->prepare('INSERT INTO correctives (equipement_id, date_declaration, declarant, description, priorite, statut, date_resolution, technicien, duree_heures, remarques) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $items = [
            [9, '2026-01-08 08:30:00', 'Superviseur surete', 'Scanner cabine ligne 2 hors service au demarrage', 'Urgente', 'Ouvert', null, 't.tijani', null, 'Diagnostic carte puissance'],
            [16, '2026-02-11 15:10:00', 'Chef tri bagages', 'Arret convoyeur entree 2 avec alarme moteur', 'Haute', 'Ouvert', null, 'e.jerrari', null, 'Piece commandee'],
            [14, '2025-12-04 09:45:00', 'Technicien ESU', 'Autonomie onduleur faible', 'Normale', 'Clos', '2025-12-05 11:20:00', 'm.elamrani', 3.50, 'Batterie remplacee'],
            [18, '2026-03-21 10:00:00', 'Agent exploitation', 'Ecran tactile borne C02 decalibre', 'Normale', 'Ouvert', null, 's.bennani', null, 'Intervention planifiee'],
            [30, '2026-04-18 18:35:00', 'Controle fret', 'Temperature ventilation elevee', 'Haute', 'Clos', '2026-04-19 08:10:00', 'a.elharti', 2.25, 'Nettoyage ventilation'],
        ];
        foreach ($items as $item) {
            $stmt->execute($item);
        }
    }

    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Installation GMAO ONDA</title><body style="font-family:Arial;background:#f4f6f7;color:#2c3e50;padding:40px">';
    echo '<h1>Installation terminee</h1>';
    echo '<p>Base <strong>gmao_onda</strong>, tables et donnees de demonstration creees avec succes.</p>';
    echo '<p><a href="../">Aller a l application</a></p>';
    echo '<p>Comptes de demonstration:</p>';
    echo '<ul>';
    echo '<li>admin@gmao-onda.local / Admin123! - admin</li>';
    echo '<li>tarik@gmao-onda.local / Tech123! - technicien</li>';
    echo '<li>responsable@gmao-onda.local / Resp123! - responsable</li>';
    echo '<li>superviseur@gmao-onda.local / Sup123! - superviseur</li>';
    echo '<li>agent@gmao-onda.local / Agent123! - agent_exploitation</li>';
    echo '</ul>';
    echo '</body></html>';
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Erreur installation: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
