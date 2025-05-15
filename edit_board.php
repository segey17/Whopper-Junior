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
    <script src="js/notifications.js" defer></script>
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
        /* Notification styles */
        .notifications-container {
            position: relative;
            display: inline-block;
            margin-right: 15px;
        }
        .notifications-icon {
            cursor: pointer;
            font-size: 1.5em;
            position: relative;
            user-select: none;
        }
        .notifications-badge {
            position: absolute;
            top: -5px;
            right: -10px;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.75em;
            display: none;
        }
        .notifications-dropdown {
            position: absolute;
            right: 0;
            top: 30px;
            background: white;
            border: 1px solid #ccc;
            width: 300px;
            max-height: 300px;
            overflow-y: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            display: none;
            z-index: 1000;
        }
        .notifications-dropdown ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .notifications-dropdown ul li {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
        }
        .notifications-dropdown ul li:last-child {
            border-bottom: none;
        }
        .notifications-button {
            width: 100%;
            padding: 8px;
            border: none;
            background-color: #007bff;
            color: white;
            cursor: pointer;
            font-size: 0.9em;
        }
        .notifications-button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
<header>
    <div class="logo">Задачник</div>
    <nav>
        <div class="notifications-container">
            <span id="notifications-icon" class="notifications-icon">🔔
                <span id="notifications-badge" class="notifications-badge">0</span>
            </span>
            <div id="notifications-dropdown" class="notifications-dropdown">
                <ul id="notifications-list">
                    <!-- Notifications will be populated by JavaScript -->
                </ul>
                <button id="mark-all-as-read" class="notifications-button">Отметить все как прочитанные</button>
            </div>
        </div>
        <ul>
            <li><a href="dashboard.php">Доски</a></li>
            <?php /* if ($user_role === 'manager' || $user_role === 'developer'): ?>
                <li><a href="admin.php">Админка</a></li>
            <?php endif; */ ?>
            <li><a href="logout.php">Выйти</a></li>
            <li><button id="theme-toggle-button">Сменить тему</button></li>
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
