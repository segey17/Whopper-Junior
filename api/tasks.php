<?php
session_start();
require '../db.php'; // Предполагается, что db.php в корне проекта

$currentUser = $_SESSION['user'] ?? null;
if (!$currentUser) {
    echo json_encode(['error' => 'Неавторизован']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$requestData = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE && !empty($_POST)) {
    $requestData = $_POST;
}


// --- Вспомогательные функции ---

function checkBoardAccess($pdo, $userSession, $board_id) {
    if (!$userSession) return false;
    $userId = $userSession['id'];
    $userRole = $userSession['role'];

    $stmt = $pdo->prepare("SELECT owner_id, is_private FROM boards WHERE id = ?");
    $stmt->execute([$board_id]);
    $board = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$board) return false; // Доска не найдена

    if ($userRole === 'manager') return true; // Менеджеры имеют доступ ко всем доскам (согласно исходной логике)
    if ($board['owner_id'] == $userId) return true; // Пользователь - владелец доски
    if ($board['is_private'] == FALSE) return true; // Доска публичная

    // Проверка членства в приватной доске
    $stmt_member = $pdo->prepare("SELECT COUNT(*) FROM board_members WHERE board_id = ? AND user_id = ?");
    $stmt_member->execute([$board_id, $userId]);
    return $stmt_member->fetchColumn() > 0;
}

function getTaskDetails($pdo, $taskId) {
    $stmt = $pdo->prepare("
        SELECT t.*, b.owner_id AS board_owner_id, b.id AS task_board_id
        FROM tasks t
        JOIN boards b ON t.board_id = b.id
        WHERE t.id = ?
    ");
    $stmt->execute([$taskId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function isUserBoardMember($pdo, $userId, $boardId) {
    if (!$userId || !$boardId) return false;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM board_members WHERE board_id = ? AND user_id = ?");
    $stmt->execute([$boardId, $userId]);
    return $stmt->fetchColumn() > 0;
}

function doesUserExist($pdo, $userId) {
    if (!$userId) return false;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() > 0;
}

// --- Обработка действий ---

switch ($action) {
    case 'get': // Получение задач для доски
        $board_id = $_GET['board_id'] ?? null;
        if (!$board_id) {
            echo json_encode(['error' => 'board_id не указан']);
            exit;
        }
        if (!checkBoardAccess($pdo, $currentUser, $board_id)) {
            echo json_encode(['error' => 'Доступ к доске запрещён']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT tasks.*, users.username AS assigned_username FROM tasks LEFT JOIN users ON tasks.assigned_to = users.id WHERE board_id = ? ORDER BY created_at DESC");
        $stmt->execute([$board_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'create': // Создание новой задачи
        $board_id = $requestData['board_id'] ?? null;
        $title = $requestData['title'] ?? '';

        if (!$board_id || empty($title)) {
            echo json_encode(['error' => 'board_id и title обязательны']);
            exit;
        }
        if (!checkBoardAccess($pdo, $currentUser, $board_id)) {
            echo json_encode(['error' => 'Доступ к доске запрещён для создания задачи']);
            exit;
        }

        $description = $requestData['description'] ?? '';
        $status = $requestData['status'] ?? 'В ожидании';
        $priority = $requestData['priority'] ?? 'средний';
        $deadline = !empty($requestData['deadline']) ? $requestData['deadline'] : null;
        $assigned_to = $requestData['assigned_to'] ?? null;
        $progress = $requestData['progress'] ?? 0;

        // Проверка прав на назначение задачи и валидность assignee_id
        if ($assigned_to) {
            $boardDetailsForCreate = $pdo->prepare("SELECT owner_id FROM boards WHERE id = ?");
            $boardDetailsForCreate->execute([$board_id]);
            $boardOwner = $boardDetailsForCreate->fetchColumn();

            if ($currentUser['role'] !== 'manager' && $currentUser['id'] != $boardOwner) {
                echo json_encode(['error' => 'Только менеджер или владелец доски может назначать исполнителей при создании']);
                exit;
            }
            if (!doesUserExist($pdo, $assigned_to) || !isUserBoardMember($pdo, $assigned_to, $board_id)) {
                echo json_encode(['error' => 'Назначаемый пользователь не существует или не является участником доски']);
                exit;
            }
        }

        $sql = "INSERT INTO tasks (board_id, title, description, status, priority, deadline, assigned_to, progress, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([$board_id, $title, $description, $status, $priority, $deadline, $assigned_to, $progress]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Ошибка создания задачи: ' . $e->getMessage()]);
        }
        break;

    case 'update_details': // Обновление деталей задачи (title, description, priority, deadline)
        $taskId = $requestData['id'] ?? null;
        if (!$taskId) {
            echo json_encode(['error' => 'ID задачи не указан']);
            exit;
        }

        $task = getTaskDetails($pdo, $taskId);
        if (!$task) {
            echo json_encode(['error' => 'Задача не найдена']);
            exit;
        }

        // Права: Менеджер или Владелец доски
        $isManager = $currentUser['role'] === 'manager';
        $isBoardOwner = $currentUser['id'] == $task['board_owner_id'];

        if (!$isManager && !$isBoardOwner) {
            echo json_encode(['error' => 'Нет прав для редактирования деталей задачи']);
            exit;
        }

        $fieldsToUpdate = [];
        $params = [];
        if (isset($requestData['title'])) { $fieldsToUpdate[] = 'title = ?'; $params[] = $requestData['title']; }
        if (isset($requestData['description'])) { $fieldsToUpdate[] = 'description = ?'; $params[] = $requestData['description']; }
        if (isset($requestData['priority'])) { $fieldsToUpdate[] = 'priority = ?'; $params[] = $requestData['priority']; }
        if (array_key_exists('deadline', $requestData)) { // Позволяет установить deadline в NULL
            $fieldsToUpdate[] = 'deadline = ?';
            $params[] = !empty($requestData['deadline']) ? $requestData['deadline'] : null;
        }

        if (empty($fieldsToUpdate)) {
            echo json_encode(['success' => true, 'message' => 'Нет данных для обновления']);
            exit;
        }

        $sql = "UPDATE tasks SET " . implode(', ', $fieldsToUpdate) . " WHERE id = ?";
        $params[] = $taskId;

        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute($params);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Ошибка обновления деталей задачи: ' . $e->getMessage()]);
        }
        break;

    case 'assign': // Назначение/переназначение исполнителя задачи
        $taskId = $requestData['id'] ?? null;
        $assignee_user_id = $requestData['assignee_user_id'] ?? null; // Может быть null для снятия назначения

        if (!$taskId) {
            echo json_encode(['error' => 'ID задачи не указан']);
            exit;
        }
        $task = getTaskDetails($pdo, $taskId);
        if (!$task) {
            echo json_encode(['error' => 'Задача не найдена']);
            exit;
        }

        // Права: Менеджер или Владелец доски
        $isManager = $currentUser['role'] === 'manager';
        $isBoardOwner = $currentUser['id'] == $task['board_owner_id'];

        if (!$isManager && !$isBoardOwner) {
            echo json_encode(['error' => 'Нет прав для назначения исполнителя']);
            exit;
        }

        if ($assignee_user_id) {
            if (!doesUserExist($pdo, $assignee_user_id) || !isUserBoardMember($pdo, $assignee_user_id, $task['task_board_id'])) {
                 echo json_encode(['error' => 'Назначаемый пользователь не существует или не является участником доски']);
                 exit;
            }
        }

        $stmt = $pdo->prepare("UPDATE tasks SET assigned_to = ? WHERE id = ?");
        try {
            $stmt->execute([$assignee_user_id, $taskId]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Ошибка назначения исполнителя: ' . $e->getMessage()]);
        }
        break;

    case 'update_status': // Обновление статуса и прогресса задачи
        $taskId = $requestData['id'] ?? null;
        $newStatus = $requestData['status'] ?? null;
        // Прогресс может быть не передан, если он вычисляется на фронте или не меняется
        $newProgress = array_key_exists('progress', $requestData) ? (int)$requestData['progress'] : null;


        if (!$taskId || $newStatus === null) {
            echo json_encode(['error' => 'ID задачи и новый статус обязательны']);
            exit;
        }
        $task = getTaskDetails($pdo, $taskId);
        if (!$task) {
            echo json_encode(['error' => 'Задача не найдена']);
            exit;
        }

        // Права: Менеджер, Владелец доски, Разработчик (с доступом к доске), Назначенный пользователь
        $isManager = $currentUser['role'] === 'manager';
        $isBoardOwner = $currentUser['id'] == $task['board_owner_id'];
        $isDeveloperWithAccess = ($currentUser['role'] === 'developer' && checkBoardAccess($pdo, $currentUser, $task['task_board_id']));
        $isAssignedUser = $task['assigned_to'] == $currentUser['id'];

        if (!$isManager && !$isBoardOwner && !$isDeveloperWithAccess && !$isAssignedUser) {
            echo json_encode(['error' => 'Нет прав для обновления статуса задачи']);
            exit;
        }

        $updateFields = ['status = ?'];
        $params = [$newStatus];

        if ($newProgress !== null) {
            $updateFields[] = 'progress = ?';
            $params[] = $newProgress;
        }
        $params[] = $taskId;

        $stmt = $pdo->prepare("UPDATE tasks SET " . implode(', ', $updateFields) . " WHERE id = ?");
        try {
            $stmt->execute($params);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Ошибка обновления статуса: ' . $e->getMessage()]);
        }
        break;

    case 'delete': // Удаление задачи
        $taskId = $requestData['id'] ?? null;
        if (!$taskId) {
            echo json_encode(['error' => 'ID задачи не указан для удаления']);
            exit;
        }
        $task = getTaskDetails($pdo, $taskId);
        if (!$task) {
            echo json_encode(['error' => 'Задача не найдена']);
            exit;
        }

        // Права: Менеджер или Владелец доски
        $isManager = $currentUser['role'] === 'manager';
        $isBoardOwner = $currentUser['id'] == $task['board_owner_id'];

        if (!$isManager && !$isBoardOwner) {
            echo json_encode(['error' => 'Нет прав для удаления задачи']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        try {
            $stmt->execute([$taskId]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Ошибка удаления задачи: ' . $e->getMessage()]);
        }
        break;

    case 'get_details': // Получение деталей одной задачи
        $taskId = $_GET['task_id'] ?? null;
        if (!$taskId) {
            echo json_encode(['error' => 'ID задачи не указан']);
            exit;
        }

        $task = getTaskDetails($pdo, $taskId); // Эта функция уже есть и получает board_id и owner_id доски
        if (!$task) {
            echo json_encode(['error' => 'Задача не найдена']);
            exit;
        }

        // Проверка доступа к доске, на которой находится задача
        if (!checkBoardAccess($pdo, $currentUser, $task['task_board_id'])) {
            echo json_encode(['error' => 'Доступ к задаче запрещён']);
            exit;
        }

        // Также добавим имя назначенного пользователя, если есть
        if ($task['assigned_to']) {
            $stmt_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt_user->execute([$task['assigned_to']]);
            $assigned_username = $stmt_user->fetchColumn();
            if ($assigned_username) {
                $task['assigned_username'] = $assigned_username;
            }
        }

        echo json_encode($task);
        break;

    default:
        echo json_encode(['error' => 'Неизвестное действие: ' . htmlspecialchars($action)]);
        break;
}
?>
