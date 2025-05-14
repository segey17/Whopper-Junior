<?php
session_start();
// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'] ?? 'Пользователь';
// $user_role = $_SESSION['role'] ?? 'user'; // Раскомментировать и настроить, если роль нужна и есть в сессии
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать доску</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
    <!-- Подключаем внешний JS файл -->
    <script src="js/edit_board.js" defer></script>
    <style>
        .members-list {
            list-style-type: none;
            padding-left: 0;
        }
        .members-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .members-list li:last-child {
            border-bottom: none;
        }
        .members-list button {
            padding: 4px 8px;
            font-size: 0.9em;
            cursor: pointer;
        }
    </style>
</head>
<body>
<header>
    <div class="logo">Задачник</div>
    <nav>
        <ul>
            <li><a href="dashboard.php">Доски</a></li>
            <?php /* if ($user_role === 'manager' || $user_role === 'developer'): ?>
                <li><a href="admin.php">Админка</a></li>
            <?php endif; */ ?>
            <li><a href="logout.php">Выйти</a></li>
            <li><button onclick="toggleTheme()">Сменить тему</button></li>
            <li><span id="user-info">Привет, <?php echo htmlspecialchars($username); ?>!</span></li>
        </ul>
    </nav>
</header>

<main style="max-width: 600px; margin: 50px auto;">
    <h2>Редактировать доску</h2>
    <form id="editBoardForm">
        <input type="hidden" id="boardId">
        <div class="input-group">
            <label for="boardTitle">Название:</label>
            <input type="text" id="boardTitle" required>
        </div>
        <div class="input-group">
            <label for="boardDescription">Описание:</label>
            <input type="text" id="boardDescription">
        </div>
        <div class="input-group">
            <input type="checkbox" id="boardIsPrivate" name="boardIsPrivate" value="1">
            <label for="boardIsPrivate" style="margin-left: 5px;">Приватная доска</label>
        </div>
        <!-- Поле для добавления участника -->
        <div class="input-group" style="margin-top: 20px;">
            <label for="memberUsername">Добавить участника по логину:</label>
            <input type="text" id="memberUsername" placeholder="Введите логин">
            <button type="button" onclick="addMemberByUsername()">Добавить</button> <?php /* type="button" чтобы не сабмитить форму */?>
        </div>
        <div id="memberMessage"></div>
        <button type="submit">Сохранить изменения</button>
        <div id="message" style="margin-top: 10px;"></div>
    </form>

    <!-- Секция для отображения участников -->
    <div id="boardMembersSection" style="margin-top: 30px;">
        <h3>Участники доски</h3>
        <ul id="boardMembersList" class="members-list">
            <!-- Сюда будут добавляться участники через JS -->
        </ul>
        <div id="membersMessage" style="margin-top: 10px;"></div>
    </div>

</main>

</body>
</html>
