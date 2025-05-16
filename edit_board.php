<?php
session_start();
// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'] ?? 'Пользователь';
$user_role = $_SESSION['user']['role'] ?? 'user'; // Получаем роль пользователя из сессии
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование доски</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="js/theme.js" defer></script>
    <script src="js/notifications.js" defer></script>
    <script src="js/edit_board.js" defer></script>
    <style>
        /* Styles from the design project's edit_board.php */
        .dashboard-header {
            margin: 40px auto 20px auto;
            max-width: 900px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dashboard-header .title-section h1 {
            margin: 0;
            font-size: 2.2em;
            font-weight: 700;
        }
        .dashboard-header .subtitle {
            color: #888;
            font-size: 1.1em;
            margin-top: 6px;
        }
        .board-edit-container {
            display: flex;
            flex-wrap: wrap;
            gap: 32px;
            max-width: 900px;
            margin: 0 auto 50px auto;
        }
        .board-edit-form-section, .board-members-section {
            flex: 1 1 350px;
            min-width: 320px;
        }
        .board-form-card {
            background: var(--card-bg, #fff);
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            padding: 32px 28px 28px 28px;
            margin-bottom: 20px;
        }
        .form-header h3 {
            margin: 0 0 4px 0;
            font-size: 1.3em;
            font-weight: 600;
        }
        .form-header .subtitle {
            color: #888;
            font-size: 0.98em;
            margin-bottom: 18px;
        }
        .input-group {
            margin-bottom: 22px;
            display: flex;
            flex-direction: column;
            gap: 7px;
        }
        .input-group label {
            font-weight: 500;
            margin-bottom: 2px;
        }
        .input-with-icon {
            position: relative;
            display: flex;
            background: var(--input-bg, #f7f7f7);
            border-radius: 6px;
            border: 1px solid var(--subtle-border-color, #e0e0e0);
            padding: 0;
        }
        .input-with-icon i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            font-size: 1.0em;
            pointer-events: none;
        }
        .input-with-icon input,
        .input-with-icon textarea {
            width: 100%;
            padding: 10px 12px 10px 40px;
            background: transparent;
            border: none;
            outline: none;
            font-size: 1em;
            color: var(--text-color);
        }
        .input-with-icon textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-actions {
            display: flex;
            gap: 16px;
            align-items: center;
            margin-top: 25px;
        }
        .primary-button, .action-button, .cancel-button {
            border: none;
            border-radius: 6px;
            padding: 10px 18px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
        }
        .primary-button {
            background: var(--accent-color, #007bff);
            color: #fff;
        }
        .primary-button:hover {
            background: var(--accent-color-darker, #0056b3);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .action-button {
            background: #28a745;
            color: #fff;
        }
        .action-button:hover {
            background: #218838;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .cancel-button {
            background: var(--button-secondary-bg, #f5f5f5);
            color: var(--button-secondary-text, #333);
            text-decoration: none;
        }
        .cancel-button:hover {
            background: var(--button-secondary-hover-bg, #e0e0e0);
        }
        .primary-button:active, .action-button:active, .cancel-button:active {
            transform: scale(0.98);
        }
        .message-box {
            margin-top: 15px;
            font-size: 0.98em;
            min-height: 20px;
        }
        .members-container {
            margin-top: 25px;
        }
        .members-container h4 {
            margin-bottom: 12px;
            font-size: 1.15em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .members-list {
            list-style-type: none;
            padding-left: 0;
            margin: 0;
        }
        .members-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 10px;
            border-radius: 6px;
            border-bottom: 1px solid var(--subtle-border-color, #eee);
            transition: background 0.2s;
        }
        .members-list li:hover {
            background: var(--bg-color-secondary-hover, #f0f0f0);
        }
        .members-list li:last-child {
            border-bottom: none;
        }
        .members-list .member-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .members-list .member-info i {
            font-size: 1.1em;
            width: 20px;
            text-align: center;
            color: var(--icon-color, #555);
        }
        .members-list .member-username {
            font-weight: 500;
        }
        .members-list .member-role {
            font-size: 0.9em;
            color: #777;
            margin-left: 5px;
        }
        .remove-member-btn {
            background: var(--danger-color, #dc3545);
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 7px 12px;
            font-size: 0.95em;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .remove-member-btn:hover {
            background: var(--danger-color-darker, #b52a37);
        }
        .input-with-icon:focus-within {
            border-color: var(--accent-color, #007bff);
            box-shadow: 0 0 0 2px rgba(var(--accent-color-rgb-tuple, 0, 123, 255), 0.2);
        }
        .input-with-icon:focus-within i {
            color: var(--accent-color, #007bff);
        }
        .input-with-icon input::placeholder,
        .input-with-icon textarea::placeholder {
            color: var(--placeholder-color, #999);
            opacity: 0.8;
        }
        /* General Notification styles (can be moved to style.css if not already similar) */
        .notifications-container {
            position: relative; display: inline-block; margin-right: 20px;
        }
        .notifications-icon {
            cursor: pointer; font-size: 1.6em;
            position: relative; user-select: none; color: var(--icon-color, #555);
        }
        .notifications-icon:hover { color: var(--accent-color, #007bff); }
        .notifications-badge {
            position: absolute; top: -6px; right: -8px;
            background-color: var(--danger-color, red); color: white;
            border-radius: 50%; padding: 3px 6px;
            font-size: 0.7em; display: none;
        }
        .notifications-dropdown {
            position: absolute; right: 0; top: 35px;
            background: var(--card-bg, white); border: 1px solid var(--subtle-border-color, #ccc);
            width: 320px; max-height: 350px;
            overflow-y: auto; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: none; z-index: 1000; border-radius: 8px;
        }
        .notifications-dropdown ul { list-style: none; margin: 0; padding: 0; }
        .notifications-dropdown ul li {
            padding: 12px 15px;
            border-bottom: 1px solid var(--subtle-border-color, #eee);
            font-size: 0.95em;
            cursor: pointer; transition: background-color 0.2s;
        }
        .notifications-dropdown ul li:hover { background-color: var(--bg-color-secondary-hover, #f0f0f0); }
        .notifications-dropdown ul li:last-child { border-bottom: none; }
        .notifications-button {
            width: 100%; padding: 10px;
            border: none; background-color: var(--accent-color, #007bff);
            color: white; cursor: pointer; font-size: 0.95em;
            border-top: 1px solid var(--subtle-border-color, #eee);
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }
        .notifications-button:hover { background-color: var(--accent-color-darker, #0056b3); }

        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #333;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 1001; /* Выше чем dropdown уведомлений */
            opacity: 0;
            transition: opacity 0.5s, transform 0.5s;
            transform: translateY(20px);
            font-size: 1em;
        }
        .toast-notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        .toast-notification.success {
            background-color: var(--success-color, #28a745);
        }
        .toast-notification.error {
            background-color: var(--danger-color, #dc3545);
        }

        @media (max-width: 900px) {
            .board-edit-container {
                flex-direction: column;
                gap: 25px;
            }
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                margin-left: 15px; margin-right: 15px;
            }
        }
        @media (max-width: 600px) {
            .board-form-card {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="logo">TaskJunior</div>
    <nav>
        <div class="notifications-container">
            <span id="notifications-icon" class="notifications-icon">
                <i class="fas fa-bell"></i>
                <span id="notifications-badge" class="notifications-badge" style="display:none;">0</span>
            </span>
            <div id="notifications-dropdown" class="notifications-dropdown" style="display:none;">
                <ul id="notifications-list">
                    <!-- Notifications will be populated by JavaScript -->
                </ul>
                <button id="mark-all-as-read" class="notifications-button">Отметить все как прочитанные</button>
            </div>
        </div>
        <ul>
            <li><a href="dashboard.php">Доски</a></li>
            <?php if (isset($user_role) && ($user_role === 'manager' || $user_role === 'admin')): ?>
                <li><a href="admin.php">Админка</a></li>
            <?php endif; ?>
            <li><a href="logout.php">Выйти</a></li>
            <li><button id="theme-toggle-button">Сменить тему</button></li>
            <li><span id="user-info">Привет, <?php echo htmlspecialchars($username); ?>!</span></li>
        </ul>
    </nav>
</header>

<main>
    <div class="dashboard-header">
        <div class="title-section">
            <h1>Редактирование доски</h1>
            <p class="subtitle">Измените данные доски и управляйте участниками</p>
        </div>
    </div>

    <div class="board-edit-container">
        <div class="board-edit-form-section">
            <div class="board-form-card">
                <div class="form-header">
                    <h3><i class="fas fa-cog"></i> Настройки доски</h3>
                    <p class="subtitle">Основная информация о доске</p>
                </div>
                <form id="editBoardForm">
                    <input type="hidden" id="boardId">
                    <div class="input-group">
                        <label for="boardTitle">Название доски:</label>
                        <div class="input-with-icon">
                            <i class="fas fa-clipboard-list"></i>
                            <input type="text" id="boardTitle" required placeholder="Введите название доски">
                        </div>
                    </div>
                    <div class="input-group">
                        <label for="boardDescription">Описание доски:</label>
                        <div class="input-with-icon">
                            <i class="fas fa-align-left"></i>
                            <textarea id="boardDescription" placeholder="Введите описание доски"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="primary-button">
                            <i class="fas fa-save"></i> Сохранить изменения
                        </button>
                        <a href="dashboard.php" class="cancel-button">
                            <i class="fas fa-times"></i> Отмена
                        </a>
                    </div>
                    <div id="message" class="message-box"></div>
                </form>
            </div>
        </div>

        <div class="board-members-section">
            <div class="board-form-card">
                <div class="form-header">
                    <h3><i class="fas fa-users-cog"></i> Управление участниками</h3>
                    <p class="subtitle">Добавление и удаление пользователей</p>
                </div>

                <div class="input-group">
                    <label for="memberUsername">Добавить участника по логину:</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i> <!-- Иконка пользователя для поля ввода логина -->
                        <input type="text" id="memberUsername" placeholder="Введите логин пользователя">
                    </div>
                    <button type="button" onclick="addMemberByUsername()" class="action-button" style="margin-top: 8px;">
                        <i class="fas fa-plus-circle"></i> Добавить участника
                    </button>
                </div>
                <div id="memberMessage" class="message-box"></div>

                <div class="members-container">
                    <h4><i class="fas fa-users"></i> Текущие участники</h4>
                    <ul id="boardMembersList" class="members-list">
                        <!-- Сюда будут добавляться участники через JS -->
                    </ul>
                    <div id="membersMessage" class="message-box" style="margin-top: 5px;"></div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Toast notification container -->
<div id="toast-notification" class="toast-notification"></div>

</body>
</html>
