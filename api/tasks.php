<?php
session_start();
require '../db.php';

$user = $_SESSION['user'] ?? null;
if (!$user) exit(json_encode(['error' => 'Неавторизован']));

$action = $_GET['action'] ?? '';

if ($action === 'get') {
    $board_id = $_GET['board_id'];
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE board_id = ?");
    $stmt->execute([$board_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($action === 'create') {
    $data = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("INSERT INTO tasks (board_id, title, description, status, priority, deadline) VALUES (?, ?, ?, ?, ?, ?)")
       ->execute([$data['board_id'], $data['title'], $data['description'], $data['status'], $data['priority'], $data['deadline']]);
    echo json_encode(['success' => true]);
}

if ($action === 'update_status') {
    $data = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("UPDATE tasks SET status = ?, progress = ? WHERE id = ?")
       ->execute([$data['status'], $data['progress'], $data['id']]);
    echo json_encode(['success' => true]);
}

if ($action === 'update_priority') {
    $data = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("UPDATE tasks SET priority = ? WHERE id = ?")
       ->execute([$data['priority'], $data['id']]);
    echo json_encode(['success' => true]);
}

if ($action === 'update_deadline') {
    $data = json_decode(file_get_contents('php://input'), true);
    $pdo->prepare("UPDATE tasks SET deadline = ? WHERE id = ?")
       ->execute([$data['deadline'], $data['id']]);
    echo json_encode(['success' => true]);
}

if ($action === 'delete') {
    $taskId = $_GET['id'];
    $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$taskId]);
    echo json_encode(['success' => true]);
}
?>