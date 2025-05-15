<?php
session_start();
error_log("[TASK_DEBUG] Script task.php started. Action: " . ($_GET['action'] ?? 'NOT_SET') . ", Input: " . file_get_contents('php://input'));
require '../db.php'; // Adjusted path assuming db.php is in the root directory
require_once __DIR__ . '/pusher_config.php'; // Подключаем конфигурацию Pusher

$user = $_SESSION['user'] ?? null;
if (!$user) {
    error_log("[TASK_DEBUG] User not authorized. Exiting.");
    exit(json_encode(['error' => 'Неавторизован']));
}
error_log("[TASK_DEBUG] User authorized: " . print_r($user, true));

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);
error_log("[TASK_DEBUG] Action: " . $action . ", Decoded data: " . print_r($data, true));

// Проверка доступа к доске
function checkBoardAccess($pdo, $user, $board_id) {
    error_log("[TASK_DEBUG] checkBoardAccess called. Board ID: " . $board_id . ", User ID: " . ($user['id'] ?? 'UNKNOWN'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM boards WHERE id = ? AND (owner_id = ? OR is_private = FALSE)");
    $stmt->execute([$board_id, $user['id']]);
    $access = $stmt->fetchColumn() > 0 || ($user['role'] ?? '') === 'manager'; // Добавил проверку на существование role
    error_log("[TASK_DEBUG] checkBoardAccess result: " . ($access ? 'GRANTED' : 'DENIED'));
    return $access;
}

if ($action === 'get') {
    error_log("[TASK_DEBUG] Entering action 'get'.");
    $board_id = $_GET['board_id'] ?? null;
    if (!$board_id) {
        error_log("[TASK_DEBUG] 'get': board_id not provided. Exiting.");
        exit(json_encode(['error' => 'board_id не указан']));
    }
    error_log("[TASK_DEBUG] 'get': board_id is " . $board_id);

    if (!checkBoardAccess($pdo, $user, $board_id)) {
        error_log("[TASK_DEBUG] 'get': Board access denied for board_id " . $board_id . ". Exiting.");
        exit(json_encode(['error' => 'Доступ запрещён']));
    }
    error_log("[TASK_DEBUG] 'get': Board access granted for board_id " . $board_id . ". Fetching tasks.");

    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE board_id = ?");
    $stmt->execute([$board_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($action === 'create') {
    error_log("[TASK_DEBUG] Entering action 'create'.");
    $board_id = $data['board_id'] ?? null;
    if (!$board_id) {
        error_log("[TASK_DEBUG] 'create': board_id not provided in data. Exiting.");
        exit(json_encode(['error' => 'board_id не указан']));
    }
    error_log("[TASK_DEBUG] 'create': board_id from data is " . $board_id);

    if (!checkBoardAccess($pdo, $user, $board_id)) {
        error_log("[TASK_DEBUG] 'create': Board access denied for board_id " . $board_id . ". Exiting.");
        exit(json_encode(['error' => 'Доступ запрещён']));
    }
    error_log("[TASK_DEBUG] 'create': Board access granted. Proceeding to insert task.");

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
    $last_task_id = $pdo->lastInsertId();
    // Отправка события Pusher
    error_log("[PUSHER_DEBUG] Attempting to trigger task_created. Task ID: " . $last_task_id . ", Board ID: " . $data['board_id']);
    $pusher->trigger('task-events', 'task_created', [
        'id' => $last_task_id,
        'board_id' => $data['board_id'],
        'title' => $data['title'],
        'description' => $data['description'],
        'status' => $data['status'] ?? 'В ожидании',
        'priority' => $data['priority'] ?? 'низкий',
        'deadline' => $data['deadline'] ?? null
    ]);
    echo json_encode(['success' => true, 'id' => $last_task_id]);
}

if ($action === 'update_status') {
    error_log("[TASK_DEBUG] Entering action 'update_status'.");
    $taskId = $data['id'] ?? null;
    $newStatus = $data['status'] ?? null;
    $newProgress = $data['progress'] ?? 0;

    if (!$taskId || !$newStatus) {
        error_log("[TASK_DEBUG] 'update_status': Insufficient data (taskId or newStatus missing). TaskID: " . $taskId . ", NewStatus: " . $newStatus . ". Exiting.");
        exit(json_encode(['error' => 'Недостаточно данных']));
    }
    error_log("[TASK_DEBUG] 'update_status': Data sufficient. TaskID: " . $taskId . ", NewStatus: " . $newStatus . ". Proceeding.");

    $pdo->prepare("UPDATE tasks SET status = ?, progress = ? WHERE id = ?")
       ->execute([$newStatus, $newProgress, $taskId]);
    // Отправка события Pusher
    // Сначала получим board_id для этой задачи
    $stmt_board = $pdo->prepare("SELECT board_id FROM tasks WHERE id = ?");
    $stmt_board->execute([$taskId]);
    $task_board_id = $stmt_board->fetchColumn();

    if ($task_board_id) {
        error_log("[PUSHER_DEBUG] Attempting to trigger task_status_updated. Task ID: " . $taskId . ", Board ID: " . $task_board_id);
        $pusher->trigger('task-events', 'task_status_updated', ['id' => $taskId, 'status' => $newStatus, 'progress' => $newProgress, 'board_id' => $task_board_id]);
    } else {
        error_log("[TASK_DEBUG] 'update_status': task_board_id not found for taskId " . $taskId . ". Pusher event NOT triggered.");
    }
    echo json_encode(['success' => true]);
}

if ($action === 'update_priority') {
    error_log("[TASK_DEBUG] Entering action 'update_priority'.");
    $taskId = $data['id'] ?? null;
    $newPriority = $data['priority'] ?? null;

    if (!$taskId || !$newPriority) {
        error_log("[TASK_DEBUG] 'update_priority': Insufficient data. Exiting.");
        exit(json_encode(['error' => 'Недостаточно данных']));
    }
    error_log("[TASK_DEBUG] 'update_priority': Data sufficient. TaskID: " . $taskId . ". Proceeding.");

    $pdo->prepare("UPDATE tasks SET priority = ? WHERE id = ?")
       ->execute([$newPriority, $taskId]);

    $stmt_board = $pdo->prepare("SELECT board_id FROM tasks WHERE id = ?");
    $stmt_board->execute([$taskId]);
    $task_board_id = $stmt_board->fetchColumn();

    if ($task_board_id) {
        error_log("[PUSHER_DEBUG] Attempting to trigger task_priority_updated. Task ID: " . $taskId . ", Board ID: " . $task_board_id);
        $pusher->trigger('task-events', 'task_priority_updated', ['id' => $taskId, 'priority' => $newPriority, 'board_id' => $task_board_id]);
    } else {
        error_log("[TASK_DEBUG] 'update_priority': task_board_id not found. Pusher event NOT triggered.");
    }
    echo json_encode(['success' => true]);
}

if ($action === 'update_deadline') {
    error_log("[TASK_DEBUG] Entering action 'update_deadline'.");
    $taskId = $data['id'] ?? null;
    $newDeadline = $data['deadline'] ?? null; // может быть null

    if (!$taskId) { // Проверяем только taskId, т.к. дедлайн можно сбросить в null
        error_log("[TASK_DEBUG] 'update_deadline': Insufficient data (taskId missing). Exiting.");
        exit(json_encode(['error' => 'Недостаточно данных']));
    }
    error_log("[TASK_DEBUG] 'update_deadline': Data sufficient. TaskID: " . $taskId . ". Proceeding.");

    $pdo->prepare("UPDATE tasks SET deadline = ? WHERE id = ?")
       ->execute([$newDeadline, $taskId]);

    $stmt_board = $pdo->prepare("SELECT board_id FROM tasks WHERE id = ?");
    $stmt_board->execute([$taskId]);
    $task_board_id = $stmt_board->fetchColumn();

    if ($task_board_id) {
        error_log("[PUSHER_DEBUG] Attempting to trigger task_deadline_updated. Task ID: " . $taskId . ", Board ID: " . $task_board_id);
        $pusher->trigger('task-events', 'task_deadline_updated', ['id' => $taskId, 'deadline' => $newDeadline, 'board_id' => $task_board_id]);
    } else {
        error_log("[TASK_DEBUG] 'update_deadline': task_board_id not found. Pusher event NOT triggered.");
    }
    echo json_encode(['success' => true]);
}

if ($action === 'delete') {
    error_log("[TASK_DEBUG] Entering action 'delete'.");
    $taskId = $data['id'] ?? null;
    if (!$taskId) {
        error_log("[TASK_DEBUG] 'delete': ID задачи не указан. Exiting.");
        exit(json_encode(['error' => 'ID задачи не указан']));
    }
    error_log("[TASK_DEBUG] 'delete': Task ID is " . $taskId . ". Proceeding to fetch board_id.");

    $stmt_board = $pdo->prepare("SELECT board_id FROM tasks WHERE id = ?");
    $stmt_board->execute([$taskId]);
    $task_board_id = $stmt_board->fetchColumn();
    error_log("[TASK_DEBUG] 'delete': Fetched board_id: " . ($task_board_id ?: 'NOT_FOUND') . ". Proceeding to delete.");

    $deleteStmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $deleteStmt->execute([$taskId]);
    error_log("[TASK_DEBUG] 'delete': Delete statement executed. Row count: " . $deleteStmt->rowCount());

    if ($deleteStmt->rowCount() > 0 && $task_board_id) {
      error_log("[PUSHER_DEBUG] Attempting to trigger task_deleted. Task ID: " . $taskId . ", Board ID: " . $task_board_id);
      $pusher->trigger('task-events', 'task_deleted', ['id' => $taskId, 'board_id' => $task_board_id]);
    } else {
      error_log("[TASK_DEBUG] 'delete': Pusher event NOT triggered. Row count: " . $deleteStmt->rowCount() . ", Task Board ID: " . ($task_board_id ?: 'NOT_FOUND'));
    }
    echo json_encode(['success' => true]);
}
error_log("[TASK_DEBUG] Script task.php finished. Action: " . $action);
?>
