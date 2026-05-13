<?php
$host = 'localhost';
$dbname = 'gmao_onda';
$user = 'root';
$password = '';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

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

    $users = [
        ['Administrateur', '', 'admin@gmao-onda.local', 'Admin123!', 'admin', 'ESU'],
        ['TIJANI', 'Tarik', 'tarik@gmao-onda.local', 'Tech123!', 'technicien', 'ESU'],
        ['Responsable', 'Maintenance', 'responsable@gmao-onda.local', 'Resp123!', 'responsable', 'ESU'],
        ['Superviseur', 'Exploitation', 'superviseur@gmao-onda.local', 'Sup123!', 'superviseur', 'ESU'],
        ['Agent', 'Exploitation', 'agent@gmao-onda.local', 'Agent123!', 'agent_exploitation', 'ESU'],
    ];

    $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, email, password_hash, role, service)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          nom = VALUES(nom),
          prenom = VALUES(prenom),
          password_hash = VALUES(password_hash),
          role = VALUES(role),
          service = VALUES(service)");

    foreach ($users as [$nom, $prenom, $email, $plainPassword, $role, $service]) {
        $stmt->execute([$nom, $prenom, $email, password_hash($plainPassword, PASSWORD_DEFAULT), $role, $service]);
    }

    $check = $pdo->prepare('SELECT email, password_hash, role FROM users WHERE email = ?');

    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Reset users</title>';
    echo '<body style="font-family:Arial;background:#f4f6f7;color:#2c3e50;padding:30px">';
    echo '<h1>Comptes GMAO reinitialises</h1>';
    echo '<p>Les 5 comptes ont ete crees/mis a jour dans la base <strong>gmao_onda</strong>.</p>';
    echo '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;background:white">';
    echo '<tr><th>Email</th><th>Mot de passe</th><th>Role</th><th>Verification</th></tr>';

    foreach ($users as [, , $email, $plainPassword]) {
        $check->execute([$email]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        $ok = $row && password_verify($plainPassword, $row['password_hash']);
        echo '<tr>';
        echo '<td>' . h($email) . '</td>';
        echo '<td>' . h($plainPassword) . '</td>';
        echo '<td>' . h($row['role'] ?? '') . '</td>';
        echo '<td style="color:' . ($ok ? 'green' : 'red') . '">' . ($ok ? 'OK' : 'ECHEC') . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '<p><a href="../">Aller a la connexion</a></p>';
    echo '</body></html>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<pre>Erreur reset users: ' . h($e->getMessage()) . '</pre>';
}
