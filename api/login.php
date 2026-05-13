<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$email = strtolower(trim($payload['email'] ?? ''));
$password = (string) ($payload['password'] ?? '');

$defaults = [
    'admin@gmao-onda.local' => ['Administrateur', '', 'Admin123!', 'admin', 'ESU'],
    'tarik@gmao-onda.local' => ['TIJANI', 'Tarik', 'Tech123!', 'technicien', 'ESU'],
    'responsable@gmao-onda.local' => ['Responsable', 'Maintenance', 'Resp123!', 'responsable', 'ESU'],
    'superviseur@gmao-onda.local' => ['Superviseur', 'Exploitation', 'Sup123!', 'superviseur', 'ESU'],
    'agent@gmao-onda.local' => ['Agent', 'Exploitation', 'Agent123!', 'agent_exploitation', 'ESU'],
];

function connect_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) ($user['id'] ?? 0),
        'nom' => $user['nom'],
        'prenom' => $user['prenom'],
        'email' => $user['email'],
        'role' => $user['role'],
        'service' => $user['service'],
    ];

    echo json_encode(['success' => true, 'user' => $_SESSION['user']]);
    exit;
}

if ($email === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Email et mot de passe obligatoires']);
    exit;
}

if (isset($defaults[$email]) && $password === $defaults[$email][2]) {
    [$nom, $prenom, , $role, $service] = $defaults[$email];
    connect_user([
        'id' => 0,
        'nom' => $nom,
        'prenom' => $prenom,
        'email' => $email,
        'role' => $role,
        'service' => $service,
    ]);
}

require_once __DIR__ . '/../config/db.php';

$stmt = $conn->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (isset($defaults[$email]) && $password === $defaults[$email][2]) {
    [$nom, $prenom, $defaultPassword, $role, $service] = $defaults[$email];

    if (!$user) {
        $insert = $conn->prepare('INSERT INTO users (nom, prenom, email, password_hash, role, service) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->execute([$nom, $prenom, $email, password_hash($defaultPassword, PASSWORD_DEFAULT), $role, $service]);
    } else {
        $repair = $conn->prepare('UPDATE users SET nom = ?, prenom = ?, password_hash = ?, role = ?, service = ? WHERE email = ?');
        $repair->execute([$nom, $prenom, password_hash($defaultPassword, PASSWORD_DEFAULT), $role, $service, $email]);
    }

    $stmt->execute([$email]);
    $user = $stmt->fetch();
}

$passwordOk = $user && password_verify($password, $user['password_hash']);

if (!$passwordOk && $user && hash_equals((string) $user['password_hash'], $password)) {
    $passwordOk = true;
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $update = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $update->execute([$newHash, $user['id']]);
    $user['password_hash'] = $newHash;
}

if (!$user || !$passwordOk) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Identifiants incorrects']);
    exit;
}

connect_user($user);
