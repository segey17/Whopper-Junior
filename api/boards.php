<?php
session_start();
require '../db.php';

$user = $_SESSION['user'] ?? null;
if (!$user) exit(json_encode(['error' => 'Неавторизован']));

$action = $_GET['action'] ?? '';

if ($action === 'get') {
    $stmt = $pdo->prepare("SELECT * FROM boards WHERE owner_id = ? OR is_private = FALSE");
    $stmt->execute([$user['id']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($action === 'create') {
    $data = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("INSERT INTO boards (title, description, is_private, owner_id) VALUES (?, ?, ?, ?)")
       ->execute([$data['title'], $data['description'], $data['is_private'], $user['id']]);
    echo json_encode(['success' => true]);
}
?>