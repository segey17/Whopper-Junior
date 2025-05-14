<?php
session_start();
require 'db.php'; // Adjusted path assuming db.php will be in the same directory as task.php after restructuring

$user = $_SESSION['user'] ?? null;
if (!$user) exit(json_encode(['error' => 'Неавторизован']));

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);

// Проверка доступа к доске
function checkBoardAccess($pdo, $user, $board_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM boards WHERE id = ? AND (owner_id = ? OR is_private = FALSE)");
    $stmt->execute([$board_id, $user['id']]);
    return $stmt->fetchColumn() > 0 || $user['role'] === 'manager';
}

if ($action === 'get') {
    $board_id = $_GET['board_id'] ?? null;
    if (!$board_id) exit(json_encode(['error' => 'board_id не указан']));

    if (!checkBoardAccess($pdo, $user, $board_id)) {
        exit(json_encode(['error' => 'Доступ запрещён']));
    }

    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE board_id = ?");
    $stmt->execute([$board_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($action === 'create') {
    $board_id = $data['board_id'] ?? null;
    if (!$board_id) exit(json_encode(['error' => 'board_id не указан']));

    if (!checkBoardAccess($pdo, $user, $board_id)) {
        exit(json_encode(['error' => 'Доступ запрещён']));
    }

    $pdo->prepare("INSERT INTO tasks (board_id, title, description, status, priority, deadline)
                   VALUES (?, ?, ?, ?, ?, ?)")
       ->execute([
           $data['board_id'],
           $data['title'],
           $data['description'],
           $data['status'] ?? 'В ожидании',
           $data['priority'] ?? 'низкий',
           $data['deadline'] ?? null
       ]);
    echo json_encode(['success' => true]);
}

if ($action === 'update_status') {
    $taskId = $data['id'] ?? null;
    $newStatus = $data['status'] ?? null;
    $newProgress = $data['progress'] ?? 0;

    if (!$taskId || !$newStatus) exit(json_encode(['error' => 'Недостаточно данных']));

    $pdo->prepare("UPDATE tasks SET status = ?, progress = ? WHERE id = ?")
       ->execute([$newStatus, $newProgress, $taskId]);
    echo json_encode(['success' => true]);
}

if ($action === 'update_priority') {
    $taskId = $data['id'] ?? null;
    $newPriority = $data['priority'] ?? null;

    if (!$taskId || !$newPriority) exit(json_encode(['error' => 'Недостаточно данных']));

    $pdo->prepare("UPDATE tasks SET priority = ? WHERE id = ?")
       ->execute([$newPriority, $taskId]);
    echo json_encode(['success' => true]);
}

if ($action === 'update_deadline') {
    $taskId = $data['id'] ?? null;
    $newDeadline = $data['deadline'] ?? null;

    if (!$taskId) exit(json_encode(['error' => 'Недостаточно данных']));

    $pdo->prepare("UPDATE tasks SET deadline = ? WHERE id = ?")
       ->execute([$newDeadline, $taskId]);
    echo json_encode(['success' => true]);
}

if ($action === 'delete') {
    $taskId = $data['id'] ?? null; // Изменил на POST
    if (!$taskId) exit(json_encode(['error' => 'ID задачи не указан']));

    $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$taskId]);
    echo json_encode(['success' => true]);
}
?>
