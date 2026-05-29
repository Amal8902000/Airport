<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/notifications_lib.php';

ensure_notifications_schema($conn);
generer_notifications_preventives($conn);

$body = json_body();
$action = $_GET['action'] ?? ($body['action'] ?? 'liste');
$userId = (int) ($_SESSION['user']['id'] ?? 0);
$role = $_SESSION['user']['role'] ?? '';

if ($action === 'liste') {
    $where = ['r.notification_id IS NULL'];
    $params = [$userId];

    if ($role !== 'admin') {
        $where[] = '((n.target_user_id IS NULL OR n.target_user_id = ?) AND (n.target_role IS NULL OR n.target_role = ?))';
        $params[] = $userId;
        $params[] = $role;
    }

    $baseWhere = implode(' AND ', $where);
    $countSql = 'SELECT COUNT(*)
        FROM notifications n
        LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = ?
        WHERE ' . $baseWhere;
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql = 'SELECT n.*
        FROM notifications n
        LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = ?
        WHERE ' . $baseWhere . '
        ORDER BY n.created_at DESC
        LIMIT 12';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    ok([
        'count' => $total,
        'notifications' => $items,
    ]);
}

if ($action === 'marquer_lue') {
    $id = (int) ($body['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        fail('Notification introuvable', 422);
    }
    $stmt = $conn->prepare('INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES (?, ?)');
    $stmt->execute([$id, $userId]);
    ok();
}

if ($action === 'tout_lire') {
    $where = [];
    $params = [];
    if ($role !== 'admin') {
        $where[] = '((target_user_id IS NULL OR target_user_id = ?) AND (target_role IS NULL OR target_role = ?))';
        $params[] = $userId;
        $params[] = $role;
    }
    $sql = 'SELECT id FROM notifications' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $insert = $conn->prepare('INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES (?, ?)');
    foreach ($stmt->fetchAll() as $row) {
        $insert->execute([(int) $row['id'], $userId]);
    }
    ok();
}

fail('Action inconnue', 404);
