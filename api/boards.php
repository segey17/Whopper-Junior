<?php
session_start();
require '../db.php';

// Исправленная проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    exit(json_encode(['error' => 'Неавторизован']));
}
$current_user_id = $_SESSION['user_id']; // Используем ID текущего пользователя из сессии

$action = $_GET['action'] ?? '';

// Новый action для получения одной доски
if ($action === 'get_single') {
    $board_id = intval($_GET['board_id'] ?? 0);
    if ($board_id === 0) {
        http_response_code(400); // Bad Request
        exit(json_encode(['error' => 'ID доски не указан']));
    }

    // Пытаемся получить доску и проверяем права доступа
    // Доска доступна, если она публичная, или если пользователь владелец, или если пользователь участник
    $stmt = $pdo->prepare("
        SELECT b.*,
               (b.owner_id = :current_user_id) as is_owner
        FROM boards b
        LEFT JOIN board_members bm ON b.id = bm.board_id
        WHERE b.id = :board_id
          AND (b.is_private = 0 OR b.owner_id = :current_user_id OR bm.user_id = :current_user_id)
        GROUP BY b.id
    ");
    $stmt->execute(['board_id' => $board_id, 'current_user_id' => $current_user_id]);
    $board = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($board) {
        echo json_encode($board);
    } else {
        http_response_code(404); // Not Found or Forbidden
        exit(json_encode(['error' => 'Доска не найдена или доступ запрещен']));
    }
    exit;
}


// Редактирование доски
if ($action === 'update') {
    $data = json_decode(file_get_contents('php://input'), true);
    $board_id = intval($data['id'] ?? 0);
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    // Добавляем обработку is_private
    $is_private = isset($data['is_private']) ? filter_var($data['is_private'], FILTER_VALIDATE_BOOLEAN) : null;

    if ($board_id === 0 || empty($title)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Недостаточно данных: ID доски и название обязательны']));
    }
    // Проверка на права (только владелец может редактировать)
    $stmt = $pdo->prepare("SELECT owner_id FROM boards WHERE id = ?");
    $stmt->execute([$board_id]);
    $board_owner = $stmt->fetchColumn();

    if ($board_owner === false) {
        http_response_code(404);
        exit(json_encode(['error' => 'Доска не найдена']));
    }
    if ($board_owner != $current_user_id) {
        http_response_code(403); // Forbidden
        exit(json_encode(['error' => 'Доступ запрещён: только владелец может редактировать доску']));
    }

    // Формируем запрос на обновление
    $sql = "UPDATE boards SET title = :title, description = :description";
    $params = ['title' => $title, 'description' => $description, 'board_id' => $board_id];

    if ($is_private !== null) {
        $sql .= ", is_private = :is_private";
        $params['is_private'] = $is_private ? 1 : 0;
    }
    $sql .= " WHERE id = :board_id";

    $updateStmt = $pdo->prepare($sql);
    if ($updateStmt->execute($params)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Ошибка обновления доски']);
    }
    exit;
}

// Удаление доски
if ($action === 'delete') {
    $board_id = intval($_GET['board_id'] ?? 0);
    if ($board_id === 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'ID доски не указан']));
    }
    // Проверка на права (только владелец)
    $stmt = $pdo->prepare("SELECT owner_id FROM boards WHERE id = ?");
    $stmt->execute([$board_id]);
    $board_owner = $stmt->fetchColumn();

    if ($board_owner === false) {
        http_response_code(404);
        exit(json_encode(['error' => 'Доска не найдена']));
    }
    if ($board_owner != $current_user_id) {
        http_response_code(403);
        exit(json_encode(['error' => 'Доступ запрещён: только владелец может удалить доску']));
    }

    // Рекомендуется настроить ON DELETE CASCADE в БД для tasks и board_members
    // или удалить их здесь вручную перед удалением доски.
    // Пример:
    // $pdo->prepare("DELETE FROM tasks WHERE board_id = ?")->execute([$board_id]);
    // $pdo->prepare("DELETE FROM board_members WHERE board_id = ?")->execute([$board_id]);

    $deleteStmt = $pdo->prepare("DELETE FROM boards WHERE id = ?");
    if ($deleteStmt->execute([$board_id])) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка удаления доски']);
    }
    exit;
}

// Добавление участника к доске (обновлено для приема username)
if ($action === 'add_member') {
    $data = json_decode(file_get_contents('php://input'), true);
    $board_id = intval($data['board_id'] ?? 0);
    $username_to_add = trim($data['username'] ?? ''); // Принимаем username

    if ($board_id === 0 || empty($username_to_add)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Недостаточно данных: ID доски и логин пользователя обязательны']));
    }

    // Проверка на права (только владелец доски может добавлять участников)
    $stmtOwnerCheck = $pdo->prepare("SELECT owner_id FROM boards WHERE id = ?");
    $stmtOwnerCheck->execute([$board_id]);
    $board_owner_id = $stmtOwnerCheck->fetchColumn();

    if ($board_owner_id === false) {
        http_response_code(404);
        exit(json_encode(['error' => 'Доска не найдена']));
    }
    if ($board_owner_id != $current_user_id) {
        http_response_code(403); // Forbidden
        exit(json_encode(['error' => 'Доступ запрещён: только владелец может добавлять участников']));
    }

    // Найти user_id по username
    $stmtFindUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmtFindUser->execute([$username_to_add]);
    $user_to_add_id = $stmtFindUser->fetchColumn();

    if ($user_to_add_id === false) {
        http_response_code(404);
        exit(json_encode(['error' => 'Пользователь с логином \'' . htmlspecialchars($username_to_add) . '\' не найден']));
    }

    // Нельзя добавить самого себя как участника, если вы владелец (хотя это не строго обязательно, но логично)
    if ($user_to_add_id == $current_user_id && $board_owner_id == $current_user_id) {
        http_response_code(400); // Bad Request
        exit(json_encode(['error' => 'Владелец не может быть добавлен как участник отдельно']));
    }

    // Проверка, что участник уже не добавлен
    $stmtMemberExists = $pdo->prepare("SELECT COUNT(*) FROM board_members WHERE board_id = ? AND user_id = ?");
    $stmtMemberExists->execute([$board_id, $user_to_add_id]);
    if ($stmtMemberExists->fetchColumn() > 0) {
        http_response_code(409); // Conflict
        exit(json_encode(['error' => 'Участник уже добавлен к этой доске']));
    }

    $insertStmt = $pdo->prepare("INSERT INTO board_members (board_id, user_id) VALUES (?, ?)");
    if ($insertStmt->execute([$board_id, $user_to_add_id])) {
        echo json_encode(['success' => true, 'message' => 'Участник успешно добавлен']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка добавления участника']);
    }
    exit;
}

// Получение досок (обновлённая логика с учётом приватности и участия)
if ($action === 'get') {
    // Доски, где пользователь владелец
    // Доски, где пользователь участник
    // Публичные доски
    // Используем DISTINCT для избежания дубликатов, если доска публичная и пользователь в ней участник или владелец
    $stmt = $pdo->prepare("
        SELECT DISTINCT b.*
        FROM boards b
        LEFT JOIN board_members bm ON b.id = bm.board_id
        WHERE b.is_private = 0
           OR b.owner_id = :current_user_id
           OR bm.user_id = :current_user_id
        ORDER BY b.title
    ");
    $stmt->execute(['current_user_id' => $current_user_id]);
    $boards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($boards);
    exit;
}

// Создание доски
if ($action === 'create') {
    $data = json_decode(file_get_contents('php://input'), true);

    $title = trim($data['title'] ?? '');
    $description = $data['description'] ?? '';
    // Убедимся, что is_private обрабатывается корректно, даже если не передано
    $is_private_value = $data['is_private'] ?? false; // по умолчанию false, если не передано
    $is_private_db = filter_var($is_private_value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;


    if (empty($title)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Название доски не указано']));
    }

    $insertStmt = $pdo->prepare("INSERT INTO boards (title, description, is_private, owner_id) VALUES (?, ?, ?, ?)");
    if ($insertStmt->execute([$title, $description, $is_private_db, $current_user_id])) {
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка создания доски']);
    }
    exit;
}

// Новый action для получения списка участников доски
if ($action === 'get_members') {
    $board_id = intval($_GET['board_id'] ?? 0);
    if ($board_id === 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'ID доски не указан']));
    }

    // Проверка, имеет ли текущий пользователь доступ к информации о доске (владелец или участник)
    $stmtAccessCheck = $pdo->prepare("
        SELECT b.owner_id
        FROM boards b
        LEFT JOIN board_members bm ON b.id = bm.board_id
        WHERE b.id = :board_id AND (b.owner_id = :current_user_id OR bm.user_id = :current_user_id)
        GROUP BY b.id
    ");
    $stmtAccessCheck->execute(['board_id' => $board_id, 'current_user_id' => $current_user_id]);
    if ($stmtAccessCheck->fetchColumn() === false && !$stmtAccessCheck->rowCount()) { // rowCount для случая, если запрос ничего не вернул (нет такой доски или доступа)
        // Если пользователь не владелец и не участник, И доска не публичная (хотя для списка участников приватность не так важна, как сам факт доступа к доске)
        // Более строгая проверка: если доска приватная, то только владелец и участники. Если публичная - все, кто видит доску.
        // Для простоты, если запрос выше ничего не вернул, значит у пользователя нет связи с доской, чтобы видеть участников.
        http_response_code(403); // Forbidden
        exit(json_encode(['error' => 'Доступ к списку участников запрещен или доска не найдена']));
    }

    $stmtMembers = $pdo->prepare("
        SELECT u.id, u.username
        FROM users u
        JOIN board_members bm ON u.id = bm.user_id
        WHERE bm.board_id = :board_id
    ");
    $stmtMembers->execute(['board_id' => $board_id]);
    $members = $stmtMembers->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($members);
    exit;
}

// Новый action для удаления участника с доски
if ($action === 'remove_member') {
    $data = json_decode(file_get_contents('php://input'), true);
    $board_id = intval($data['board_id'] ?? 0);
    $user_to_remove_id = intval($data['user_id'] ?? 0); // ID пользователя, которого удаляем

    if ($board_id === 0 || $user_to_remove_id === 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'Недостаточно данных: ID доски и ID пользователя для удаления обязательны']));
    }

    // Проверка на права (только владелец доски может удалять участников)
    $stmtOwnerCheck = $pdo->prepare("SELECT owner_id FROM boards WHERE id = ?");
    $stmtOwnerCheck->execute([$board_id]);
    $board_owner_id = $stmtOwnerCheck->fetchColumn();

    if ($board_owner_id === false) {
        http_response_code(404);
        exit(json_encode(['error' => 'Доска не найдена']));
    }
    if ($board_owner_id != $current_user_id) {
        http_response_code(403); // Forbidden
        exit(json_encode(['error' => 'Доступ запрещён: только владелец может удалять участников']));
    }

    // Нельзя удалить владельца доски через этот механизм
    if ($user_to_remove_id == $board_owner_id) {
        http_response_code(400);
        exit(json_encode(['error' => 'Владельца доски нельзя удалить как участника']));
    }

    $deleteStmt = $pdo->prepare("DELETE FROM board_members WHERE board_id = :board_id AND user_id = :user_id");
    if ($deleteStmt->execute(['board_id' => $board_id, 'user_id' => $user_to_remove_id])) {
        if ($deleteStmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Участник успешно удален']);
        } else {
            http_response_code(404); // Not Found - если такой участник не был на доске
            echo json_encode(['error' => 'Участник не найден на этой доске или уже был удален']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка удаления участника']);
    }
    exit;
}

// Если никакой action не подошел
http_response_code(400);
echo json_encode(['error' => 'Неизвестное действие']);
?>
