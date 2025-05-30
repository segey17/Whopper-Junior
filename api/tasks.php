<?php
session_start();
require '../db.php'; // Предполагается, что db.php в корне проекта
require_once __DIR__ . '/notifications_helper.php';
require_once __DIR__ . '/pusher_config.php'; // Подключаем конфигурацию Pusher

$currentUser = $_SESSION['user'] ?? null;
if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['error' => 'Неавторизован']);
    exit;
}

// Определяем действие (action)
$action = $_REQUEST['action'] ?? null; // $_REQUEST = $_GET + $_POST + $_COOKIE

// Получаем данные в зависимости от метода и action
$requestData = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $jsonData = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $requestData = $jsonData;
        } else {
            // Если это не JSON, но есть POST-данные (например, из обычной формы)
            // Хотя для API ожидается JSON
            if (!empty($_POST)) {
                 $requestData = $_POST;
            }
        }
    } elseif (!empty($_POST)) { // Если тело пусто, но есть POST данные (маловероятно для нашего JSON API)
        $requestData = $_POST;
    }
}

// Если action был в POST-данных, а не в URL, извлекаем его
if (!$action && isset($requestData['action'])) {
    $action = $requestData['action'];
}

if (!$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Действие (action) не указано']);
    exit;
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

// Получение роли пользователя на доске для проверок (кроме action 'get')
// Эта функция будет вызываться внутри case, где нужен $board_id
function getCurrentUserRoleOnBoard($pdo, $userId, $boardId) {
    if (!$boardId) return 'viewer'; // Если нет ID доски, считаем гостем

    $stmt_board_owner = $pdo->prepare("SELECT owner_id FROM boards WHERE id = :board_id");
    $stmt_board_owner->execute(['board_id' => $boardId]);
    $board_owner_id = $stmt_board_owner->fetchColumn();

    if ($board_owner_id == $userId) {
        return 'owner';
    }

    $stmt_check_member = $pdo->prepare("SELECT COUNT(*) FROM board_members WHERE board_id = :board_id AND user_id = :user_id");
    $stmt_check_member->execute(['board_id' => $boardId, 'user_id' => $userId]);
    if ($stmt_check_member->fetchColumn() > 0) {
        return 'participant_developer';
    }

    // Если доска публичная, но пользователь не владелец и не участник - можно считать 'viewer'
    // Но для простоты и безопасности, если не владелец и не участник, то нет особых прав
    // Для случаев где checkBoardAccess разрешает (например публичные доски), роль все равно будет ниже owner/participant
    return 'viewer'; // или 'public_viewer' или другой более специфичный ключ, если нужно
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

        // Получаем ID владельца доски
        $stmt_board_owner = $pdo->prepare("SELECT owner_id FROM boards WHERE id = :board_id");
        $stmt_board_owner->execute(['board_id' => $board_id]);
        $board_owner_id = $stmt_board_owner->fetchColumn();

        $is_owner = ($board_owner_id == $currentUser['id']);
        $user_is_member = false;

        if (!$is_owner) {
            $stmt_check_member = $pdo->prepare("SELECT COUNT(*) FROM board_members WHERE board_id = :board_id AND user_id = :user_id");
            $stmt_check_member->execute(['board_id' => $board_id, 'user_id' => $currentUser['id']]);
            if ($stmt_check_member->fetchColumn() > 0) {
                $user_is_member = true;
            }
        }

        $effective_role_on_board_key = 'viewer'; // Ключ для программной логики
        $effective_role_on_board_display = 'Гость'; // Строка для отображения
        $filter_tasks_for_assignee = false;

        if ($is_owner) {
            $effective_role_on_board_key = 'owner';
            $effective_role_on_board_display = 'Владелец (Менеджер)';
            // Владелец видит все задачи, $filter_tasks_for_assignee = false;
        } elseif ($user_is_member) {
            $effective_role_on_board_key = 'participant_developer';
            $effective_role_on_board_display = 'Участник (Разработчик)';
            $filter_tasks_for_assignee = true; // Участник (в контексте разработчика) видит только свои задачи
        } else {
            // Не владелец и не участник (например, публичный просмотр, если checkBoardAccess это разрешил)
            // Видит все задачи, $filter_tasks_for_assignee = false;
            // $effective_role_on_board_display уже 'Гость'
        }

        $sql = "SELECT tasks.*, users.username AS assigned_username
                FROM tasks
                LEFT JOIN users ON tasks.assigned_to_user_id = users.id
                WHERE board_id = :board_id";

        $params = ['board_id' => $board_id];

        if ($filter_tasks_for_assignee) {
            $sql .= " AND tasks.assigned_to_user_id = :current_user_id";
            $params['current_user_id'] = $currentUser['id'];
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'tasks' => $tasks,
            'currentUserEffectiveRoleOnBoard' => $effective_role_on_board_display,
            'currentUserEffectiveRoleKey' => $effective_role_on_board_key // Добавим ключ для возможной логики на фронте
        ]);
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

        // ПРОВЕРКА ПРАВ НА СОЗДАНИЕ ЗАДАЧИ: Только владелец доски может создавать задачи
        $userRoleOnBoard = getCurrentUserRoleOnBoard($pdo, $currentUser['id'], $board_id);
        if ($userRoleOnBoard !== 'owner') {
            http_response_code(403); // Forbidden
            echo json_encode(['error' => 'Только владелец доски может создавать задачи.']);
            exit;
        }

        $description = $requestData['description'] ?? '';
        $status = $requestData['status'] ?? 'В ожидании';
        $priority = $requestData['priority'] ?? 'средний';
        $deadline = !empty($requestData['deadline']) ? $requestData['deadline'] : null;
        $assigned_to_user_id = $requestData['assigned_to_user_id'] ?? $requestData['assigned_to'] ?? null; // Принимаем новое или старое имя для обратной совместимости, но лучше чтобы клиент слал assigned_to_user_id
        $progress = $requestData['progress'] ?? 0;

        // Проверка прав на назначение задачи и валидность assignee_id
        if ($assigned_to_user_id) {
            $boardDetailsForCreate = $pdo->prepare("SELECT owner_id FROM boards WHERE id = ?");
            $boardDetailsForCreate->execute([$board_id]);
            $boardOwner = $boardDetailsForCreate->fetchColumn();

            if ($currentUser['role'] !== 'manager' && $currentUser['id'] != $boardOwner) {
                echo json_encode(['error' => 'Только менеджер или владелец доски может назначать исполнителей при создании']);
                exit;
            }
            if (!doesUserExist($pdo, $assigned_to_user_id) || !isUserBoardMember($pdo, $assigned_to_user_id, $board_id)) {
                echo json_encode(['error' => 'Назначаемый пользователь не существует или не является участником доски']);
                exit;
            }
        }

        $sql = "INSERT INTO tasks (board_id, title, description, status, priority, deadline, assigned_to_user_id, progress, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([$board_id, $title, $description, $status, $priority, $deadline, $assigned_to_user_id, $progress]);
            $last_task_id = $pdo->lastInsertId();

            // Получаем созданную задачу для отправки через Pusher
            $stmt_get_task = $pdo->prepare("SELECT tasks.*, users.username AS assigned_username FROM tasks LEFT JOIN users ON tasks.assigned_to_user_id = users.id WHERE tasks.id = ?");
            $stmt_get_task->execute([$last_task_id]);
            $newTaskData = $stmt_get_task->fetch(PDO::FETCH_ASSOC);

            if ($newTaskData) {
                global $pusher; // Делаем $pusher доступным
                $pusher->trigger('task-events', 'task_created', $newTaskData);
            }

            echo json_encode(['success' => true, 'id' => $last_task_id]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Ошибка создания задачи: ' . $e->getMessage()]);
        }
        break;

    case 'update_details': // Обновление деталей задачи (title, description, priority, deadline, progress)
        $taskId = $requestData['id'] ?? null;
        if (!$taskId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID задачи не указан']);
            exit;
        }

        $task = getTaskDetails($pdo, $taskId);
        if (!$task) {
            http_response_code(404);
            echo json_encode(['error' => 'Задача не найдена']);
            exit;
        }

        $isManager = $currentUser['role'] === 'manager';
        $isBoardOwner = $currentUser['id'] == $task['board_owner_id'];
        $isAssignedUser = $task['assigned_to_user_id'] == $currentUser['id'];

        $canEditAllDetails = $isManager || $isBoardOwner;

        // Определяем, какие поля пытается обновить пользователь
        $requestedFieldsToUpdate = [];
        if (isset($requestData['title'])) $requestedFieldsToUpdate['title'] = $requestData['title'];
        if (isset($requestData['description'])) $requestedFieldsToUpdate['description'] = $requestData['description'];
        if (isset($requestData['priority'])) $requestedFieldsToUpdate['priority'] = $requestData['priority'];
        if (array_key_exists('deadline', $requestData)) $requestedFieldsToUpdate['deadline'] = $requestData['deadline'];
        if (array_key_exists('progress', $requestData)) $requestedFieldsToUpdate['progress'] = $requestData['progress'];
        if (array_key_exists('status', $requestData)) $requestedFieldsToUpdate['status'] = $requestData['status'];

        $sqlSetParts = [];
        $sqlParams = [];

        $hasUnauthorizedChanges = false;

        foreach ($requestedFieldsToUpdate as $field => $value) {
            if ($field === 'description') {
                if ($canEditAllDetails || $isAssignedUser) {
                    $sqlSetParts[] = "description = ?";
                    $sqlParams[] = $value;
                } else {
                    $hasUnauthorizedChanges = true; break;
                }
            } elseif ($field === 'title' || $field === 'priority' || $field === 'deadline') {
                if ($canEditAllDetails) {
                    $sqlSetParts[] = "`{$field}` = ?";
                    $sqlParams[] = ($field === 'deadline' && empty($value)) ? null : $value;
                } else {
                    $hasUnauthorizedChanges = true; break;
                }
            }
            // progress и status будут обработаны ниже централизованно
        }

        // Дополнительная логика для синхронизации progress и status
        $final_progress = null;
        $final_status = null;

        if (array_key_exists('progress', $requestedFieldsToUpdate)) {
            $p_val = $requestedFieldsToUpdate['progress'];
            if ($p_val !== null && is_numeric($p_val)) {
                $final_progress = intval($p_val);
                if ($final_progress < 0) $final_progress = 0;
                if ($final_progress > 100) $final_progress = 100;
            }
        } else {
            $final_progress = $task['progress'];
        }

        if (array_key_exists('status', $requestedFieldsToUpdate)) {
            $final_status = $requestedFieldsToUpdate['status'];
        } else {
            $final_status = $task['status'];
        }

        // Синхронизация
        if ($final_progress === 100) {
            $final_status = 'Завершено';
        } elseif ($final_status === 'Завершено') {
            $final_progress = 100;
        } elseif ($final_status === 'В ожидании') {
            $final_progress = 0;
        } elseif ($final_progress > 0 && $final_progress < 100 && $final_status !== 'В работе') {
            $final_status = 'В работе';
        }

        $changed_fields_summary = [];
        if (isset($requestedFieldsToUpdate['title']) && $canEditAllDetails && $task['title'] !== $requestedFieldsToUpdate['title']) {
            $changed_fields_summary[] = "название изменено на '" . htmlspecialchars($requestedFieldsToUpdate['title']) . "'";
            $task_title_for_notification = $requestedFieldsToUpdate['title'];
        } else {
            $task_title_for_notification = $task['title'];
        }
        if (isset($requestedFieldsToUpdate['description']) && ($canEditAllDetails || $isAssignedUser) && $task['description'] !== $requestedFieldsToUpdate['description']) {
            $changed_fields_summary[] = "описание обновлено";
        }
        if (isset($requestedFieldsToUpdate['priority']) && $canEditAllDetails && $task['priority'] !== $requestData['priority']) {
            $changed_fields_summary[] = "приоритет изменен на '" . htmlspecialchars($requestedFieldsToUpdate['priority']) . "'";
        }
        if (array_key_exists('deadline', $requestedFieldsToUpdate) && $canEditAllDetails) {
            $new_deadline_value = !empty($requestedFieldsToUpdate['deadline']) ? $requestedFieldsToUpdate['deadline'] : null;
            if ($task['deadline'] !== $new_deadline_value) {
                 $deadline_text = $new_deadline_value ? "дедлайн изменен на '" . htmlspecialchars($new_deadline_value) . "'" : "дедлайн удален";
                 $changed_fields_summary[] = $deadline_text;
            }
        }

        // Пересобираем $sqlSetParts и $sqlParams с учетом $final_progress и $final_status
        // (нужно проверить, изменились ли они относительно $task['progress'] и $task['status'])
        if ($task['progress'] != $final_progress) {
            // Удаляем 'progress = ?' из $sqlSetParts, если был добавлен ранее
            foreach ($sqlSetParts as $key => $part) {
                if (strpos($part, 'progress = ?') !== false) {
                    unset($sqlSetParts[$key]);
                    unset($sqlParams[$key]);
                }
            }
            $sqlSetParts[] = "progress = ?";
            $sqlParams[] = $final_progress;
            if (!in_array("прогресс изменен на '".$final_progress."%'", $changed_fields_summary) && $final_progress !== null) {
                $changed_fields_summary[] = "прогресс изменен на '".$final_progress."%'";
            }
        }
        if ($task['status'] != $final_status) {
            // Удаляем 'status = ?' из $sqlSetParts, если был добавлен ранее
            foreach ($sqlSetParts as $key => $part) {
                if (strpos($part, 'status = ?') !== false) {
                    unset($sqlSetParts[$key]);
                    unset($sqlParams[$key]);
                }
            }
            $sqlSetParts[] = "status = ?";
            $sqlParams[] = $final_status;
            if (!in_array("статус изменен на '".htmlspecialchars($final_status)."'", $changed_fields_summary)) {
                $changed_fields_summary[] = "статус изменен на '".htmlspecialchars($final_status)."'";
            }
        }

        if ($hasUnauthorizedChanges) {
            http_response_code(403);
            echo json_encode(['error' => 'Нет прав для редактирования некоторых из указанных деталей задачи']);
            exit;
        }

        if (empty($sqlSetParts)) {
            echo json_encode(['success' => true, 'message' => 'Нет данных для обновления или нет прав на обновление указанных полей']);
            exit;
        }

        $sql = "UPDATE tasks SET " . implode(', ', $sqlSetParts) . " WHERE id = ?";
        $sqlParams[] = $taskId;

        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute($sqlParams);
            // Уведомления об изменении деталей
            $initiator_username = $currentUser['username'] ?? 'Пользователь';
            $original_assigned_user_id = $task['assigned_to_user_id'];
            $board_id_for_notification = $task['task_board_id'];

            if (!empty($changed_fields_summary) && $original_assigned_user_id && $original_assigned_user_id != $currentUser['id']) {
                // Получаем название доски
                $board_title_stmt = $pdo->prepare("SELECT title FROM boards WHERE id = ?");
                $board_title_stmt->execute([$board_id_for_notification]);
                $board_title = $board_title_stmt->fetchColumn() ?: 'безымянной доске';

                $notification_message = sprintf("%s обновил(а) задачу '%s' на доске '%s': %s.",
                    htmlspecialchars($initiator_username),
                    htmlspecialchars($task_title_for_notification),
                    htmlspecialchars($board_title),
                    implode(", ", $changed_fields_summary)
                );
                create_app_notification($pdo, $currentUser['id'], $original_assigned_user_id, $board_id_for_notification, $taskId, $notification_message);
            }
            // TODO: Уведомление менеджеру/владельцу доски, если разработчик обновил описание своей задачи (если это требуется)

            // Отправка события Pusher об обновлении деталей задачи
            $stmt_get_task_updated = $pdo->prepare("SELECT tasks.*, users.username AS assigned_username FROM tasks LEFT JOIN users ON tasks.assigned_to_user_id = users.id WHERE tasks.id = ?");
            $stmt_get_task_updated->execute([$taskId]);
            $updatedTaskDataDetails = $stmt_get_task_updated->fetch(PDO::FETCH_ASSOC);

            if ($updatedTaskDataDetails) {
                global $pusher;
                $pusher->trigger('task-events', 'task_updated', $updatedTaskDataDetails);
            }

            echo json_encode(['success' => true, 'message' => 'Детали задачи успешно обновлены.']);
        } catch (PDOException $e) {
            http_response_code(500);
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
        $task = getTaskDetails($pdo, $taskId); // $task содержит title, task_board_id, assigned_to_user_id (старый)
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

        $stmt = $pdo->prepare("UPDATE tasks SET assigned_to_user_id = ? WHERE id = ?");
        try {
            $stmt->execute([$assignee_user_id, $taskId]);

            // Создание уведомления, если новый исполнитель назначен
            if ($assignee_user_id && $assignee_user_id != $task['assigned_to_user_id']) { // Уведомляем, только если исполнитель реально изменился и это не снятие
                // Получаем имя текущего пользователя (инициатора)
                $initiator_username = $currentUser['username'] ?? 'Менеджер';

                // Получаем название доски
                $board_title_stmt = $pdo->prepare("SELECT title FROM boards WHERE id = ?");
                $board_title_stmt->execute([$task['task_board_id']]);
                $board_title = $board_title_stmt->fetchColumn() ?: 'безымянной доске';

                // Формируем сообщение
                $task_title_for_notification = $task['title'] ?? 'безымянной задаче';
                $message = sprintf("%s назначил(а) вам задачу '%s' на доске '%s'.",
                                   htmlspecialchars($initiator_username),
                                   htmlspecialchars($task_title_for_notification),
                                   htmlspecialchars($board_title));

                create_app_notification($pdo, $currentUser['id'], $assignee_user_id, $task['task_board_id'], $taskId, $message);
            }

            // Отправка события Pusher об изменении исполнителя
            $stmt_get_task_assigned = $pdo->prepare("SELECT tasks.*, users.username AS assigned_username FROM tasks LEFT JOIN users ON tasks.assigned_to_user_id = users.id WHERE tasks.id = ?");
            $stmt_get_task_assigned->execute([$taskId]);
            $assignedTaskData = $stmt_get_task_assigned->fetch(PDO::FETCH_ASSOC);
            if ($assignedTaskData) {
                 global $pusher;
                 $pusher->trigger('task-events', 'task_updated', $assignedTaskData); // Используем общее событие task_updated
            }

            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Ошибка назначения исполнителя: ' . $e->getMessage()]);
        }
        break;

    case 'update_status': // Обновление статуса и прогресса задачи
        $taskId = $requestData['id'] ?? null;
        $newStatus = $requestData['status'] ?? null;
        $newProgress = array_key_exists('progress', $requestData) ? $requestData['progress'] : null;

        if ($newProgress !== null && is_numeric($newProgress)) {
            $newProgress = intval($newProgress);
            if ($newProgress < 0) $newProgress = 0;
            if ($newProgress > 100) $newProgress = 100;
        } else {
            // Если прогресс не передан или некорректен, вычисляем его на основе статуса
            if ($newStatus === 'Завершено') {
                $newProgress = 100;
            } elseif ($newStatus === 'В ожидании') {
                $newProgress = 0;
            } else { // В работе или другой статус
                // Если был предыдущий прогресс и он не 0 и не 100, оставляем его
                // Иначе (если был 0 или 100, или не было), ставим например 10%
                $taskForProgress = getTaskDetails($pdo, $taskId); // Нужно получить текущий прогресс
                if ($taskForProgress && $taskForProgress['progress'] > 0 && $taskForProgress['progress'] < 100 && $newStatus === 'В работе') {
                     $newProgress = $taskForProgress['progress'];
                } elseif ($newStatus === 'В работе') {
                     $newProgress = 10; // или другое значение по умолчанию для "В работе"
                } else {
                     $newProgress = null; // Не меняем, если статус не ясен или прогресс не определен
                }
            }
        }

        // Синхронизация: если прогресс 100, статус должен быть "Завершено"
        if ($newProgress === 100) {
            $newStatus = 'Завершено';
        } elseif ($newStatus === 'Завершено' && $newProgress !== 100) { // Если статус "Завершено", но прогресс не 100
            $newProgress = 100;
        } elseif ($newStatus === 'В ожидании' && $newProgress !== 0) { // Если статус "В ожидании", но прогресс не 0
            $newProgress = 0;
        } elseif ($newStatus === 'В работе' && $newProgress === 100) { // Нелогично: В работе и 100%
            $newStatus = 'Завершено'; // Приводим к Завершено
        } elseif ($newStatus === 'В работе' && $newProgress === 0 && ($taskForProgress && $taskForProgress['status'] !== 'В ожидании') ) {
            // Если прогресс стал 0, но статус "В работе" (и не был "В ожидании"), возможно, вернуть "В ожидании"
            // $newStatus = 'В ожидании'; // Это может быть спорно, оставляем как есть, если юзер сам поставил 0
        }

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
        $isAssignedUser = $task['assigned_to_user_id'] == $currentUser['id'];

        if (!$isManager && !$isBoardOwner && !$isDeveloperWithAccess && !$isAssignedUser) {
            echo json_encode(['error' => 'Нет прав для обновления статуса задачи']);
            exit;
        }

        $updateFields = [];
        $params = [];

        if ($newStatus !== null) {
            $updateFields[] = 'status = ?';
            $params[] = $newStatus;
        }
        if ($newProgress !== null) { // Обновляем прогресс только если он определен
            $updateFields[] = 'progress = ?';
            $params[] = $newProgress;
        }

        if (empty($updateFields)) {
            echo json_encode(['success' => true, 'message' => 'Нет данных для обновления статуса или прогресса.']);
            exit;
        }
        $params[] = $taskId;

        $stmt = $pdo->prepare("UPDATE tasks SET " . implode(', ', $updateFields) . " WHERE id = ?");
        try {
            $stmt->execute($params);
            // Отправка события Pusher об обновлении статуса задачи
            $stmt_get_task_status = $pdo->prepare("SELECT tasks.*, users.username AS assigned_username FROM tasks LEFT JOIN users ON tasks.assigned_to_user_id = users.id WHERE tasks.id = ?");
            $stmt_get_task_status->execute([$taskId]);
            $updatedTaskDataStatus = $stmt_get_task_status->fetch(PDO::FETCH_ASSOC);

            if ($updatedTaskDataStatus) {
                global $pusher;
                $pusher->trigger('task-events', 'task_updated', $updatedTaskDataStatus); // Используем общее событие task_updated
            }
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
            // Отправка события Pusher об удалении задачи
            global $pusher;
            $pusher->trigger('task-events', 'task_deleted', ['id' => $taskId, 'board_id' => $task['task_board_id']]); // Отправляем ID задачи и ID доски

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
        if ($task['assigned_to_user_id']) {
            $stmt_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt_user->execute([$task['assigned_to_user_id']]);
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
