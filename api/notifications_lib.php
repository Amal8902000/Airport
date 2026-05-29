<?php

function ensure_notifications_schema(PDO $conn): void
{
    $conn->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notification_key VARCHAR(160) UNIQUE NOT NULL,
        titre VARCHAR(160) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(40) NOT NULL DEFAULT 'info',
        target_role VARCHAR(40) NULL,
        target_user_id INT NULL,
        lien VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $conn->exec("CREATE TABLE IF NOT EXISTS notification_reads (
        notification_id INT NOT NULL,
        user_id INT NOT NULL,
        read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (notification_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
}

function creer_notification(PDO $conn, array $data): void
{
    ensure_notifications_schema($conn);

    $stmt = $conn->prepare('INSERT IGNORE INTO notifications
        (notification_key, titre, message, type, target_role, target_user_id, lien)
        VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $data['key'],
        $data['titre'],
        $data['message'],
        $data['type'] ?? 'info',
        $data['target_role'] ?? null,
        $data['target_user_id'] ?? null,
        $data['lien'] ?? null,
    ]);
}

function creer_notifications_roles(PDO $conn, array $roles, array $data): void
{
    foreach ($roles as $role) {
        creer_notification($conn, array_merge($data, [
            'key' => $data['key'] . ':role:' . $role,
            'target_role' => $role,
        ]));
    }
}

function generer_notifications_preventives(PDO $conn): void
{
    ensure_notifications_schema($conn);

    $stmt = $conn->query("SELECT p.id, p.date_prevue, p.type_maintenance, p.periodicite, e.nom AS equipement
        FROM preventives p
        JOIN equipements e ON e.id = p.equipement_id
        WHERE p.etat = 'En attente'
          AND p.date_prevue BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)");

    foreach ($stmt->fetchAll() as $row) {
        $message = 'Action preventive ' . ($row['type_maintenance'] ?? '') . ' pour ' . ($row['equipement'] ?? '')
            . ' prevue le ' . ($row['date_prevue'] ?? '');
        creer_notifications_roles($conn, ['technicien', 'superviseur', 'responsable'], [
            'key' => 'preventive:due:' . $row['id'] . ':' . $row['date_prevue'],
            'titre' => 'Action preventive a echeance',
            'message' => $message,
            'type' => 'preventive',
            'lien' => '../pages/preventive.php',
        ]);
    }

    $stmt = $conn->query("SELECT p.id, p.date_prevue, p.type_maintenance, e.nom AS equipement
        FROM preventives p
        JOIN equipements e ON e.id = p.equipement_id
        WHERE p.etat = 'En attente'
          AND p.date_prevue < CURDATE()");

    foreach ($stmt->fetchAll() as $row) {
        $message = 'Action preventive en retard pour ' . ($row['equipement'] ?? '')
            . ' depuis le ' . ($row['date_prevue'] ?? '');
        creer_notifications_roles($conn, ['superviseur', 'responsable'], [
            'key' => 'preventive:late:' . $row['id'] . ':' . $row['date_prevue'],
            'titre' => 'Action preventive en retard',
            'message' => $message,
            'type' => 'retard',
            'lien' => '../pages/preventive.php',
        ]);
    }
}
