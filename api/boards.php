<?php
session_start();
require '../db.php';
$user = $_SESSION['user'] ?? null;
if (!$user) exit(json_encode(['error' => 'Неавторизован']));
$action = $_GET['action'] ?? '';

// Редактирование доски
if ($action === 'update') {
    $data = json_decode(file_get_contents('php://input'), true);
    $board_id = intval($data['id'] ?? 0); // Приведение к целому числу
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');

    if ($board_id === 0 || empty($title)) {
        exit(json_encode(['error' => 'Недостаточно данных']));
    }

    // Проверка на права
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM boards WHERE id = ? AND owner_id = ?");
    $stmt->execute([$board_id, $user['id']]);
    if ($stmt->fetchColumn() == 0) {
        exit(json_encode(['error' => 'Доступ запрещён']));
    }

    $pdo->prepare("UPDATE boards SET title = ?, description = ? WHERE id = ?")
       ->execute([$title, $description, $board_id]);
    echo json_encode(['success' => true]);
}

// Удаление доски
if ($action === 'delete') {
    $board_id = intval($_GET['board_id'] ?? 0);
    if ($board_id === 0) exit(json_encode(['error' => 'ID доски не указан']));
    // Проверка на права
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM boards WHERE id = ? AND owner_id = ?");
    $stmt->execute([$board_id, $user['id']]);
    if ($stmt->fetchColumn() == 0) {
        exit(json_encode(['error' => 'Доступ запрещён']));
    }
    $pdo->prepare("DELETE FROM boards WHERE id = ?")->execute([$board_id]);
    echo json_encode(['success' => true]);
}

// Добавление участника к доске
if ($action === 'add_member') {
    $data = json_decode(file_get_contents('php://input'), true);
    $board_id = intval($data['board_id'] ?? 0);
    $user_id = intval($data['user_id'] ?? 0);

    if ($board_id === 0 || $user_id === 0) {
        exit(json_encode(['error' => 'Недостаточно данных']));
    }

    // Проверка на права
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM boards WHERE id = ? AND owner_id = ?");
    $stmt->execute([$board_id, $user['id']]);
    if ($stmt->fetchColumn() == 0) {
        exit(json_encode(['error' => 'Доступ запрещён']));
    }

    // Проверка, что участник уже не добавлен
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM board_members WHERE board_id = ? AND user_id = ?");
    $stmt->execute([$board_id, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        exit(json_encode(['error' => 'Участник уже добавлен']));
    }

    $pdo->prepare("INSERT INTO board_members (board_id, user_id) VALUES (?, ?)")
       ->execute([$board_id, $user_id]);
    echo json_encode(['success' => true]);
}

// Получение досок
if ($action === 'get') {
    $stmt = $pdo->prepare("SELECT * FROM boards WHERE is_private = 0 OR owner_id = ?");
    $stmt->execute([$user['id']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// Создание доски
if ($action === 'create') {
    $data = json_decode(file_get_contents('php://input'), true);
    $isPrivate = filter_var($data['is_private'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    $title = trim($data['title']);
    if (empty($title)) {
        exit(json_encode(['error' => 'Название доски не указано']));
    }
    $pdo->prepare("INSERT INTO boards (title, description, is_private, owner_id) VALUES (?, ?, ?, ?)")
       ->execute([
           $title,
           $data['description'] ?? '',
           $isPrivate,
           $user['id']
       ]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
}
?>
