<?php
session_start();
require '../db.php';

$user = $_SESSION['user'] ?? null;
if (!$user || $user['role'] !== 'manager') {
    exit(json_encode(['error' => 'Доступ запрещен']));
}

$action = $_GET['action'] ?? '';

if ($action === 'get') {
    $stmt = $pdo->query("SELECT * FROM users");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($action === 'update_role') {
    $data = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")
       ->execute([$data['role'], $data['id']]);
    echo json_encode(['success' => true]);
}
?>