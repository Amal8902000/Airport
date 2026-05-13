<?php
$host = 'localhost';
$dbname = 'gmao_onda';
$user = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    $message = 'Erreur de connexion a la base de donnees: ' . $e->getMessage();
    $isApi = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;

    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    die(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}
