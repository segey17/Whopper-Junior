<?php
session_start();
// Обновленная проверка авторизации
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) { // Проверяем наличие массива user и id в нем
    header('Location: login.php');
    exit;
}
$username = $_SESSION['user']['username'] ?? 'Пользователь';
$user_role = $_SESSION['user']['role'] ?? 'user'; // Теперь роль можно получить из массива user
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Доски</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
    <script src="js/notifications.js" defer></script>
    <!-- Подключаем внешний JS файл -->
    <script src="js/dashboard.js" defer></script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        // Вам нужно будет заменить PUSHER_APP_KEY и PUSHER_APP_CLUSTER вашими реальными значениями
        const PUSHER_APP_KEY = 'dbe89bd713c5f93e5e19';
        const PUSHER_APP_CLUSTER = 'eu';
    </script>
    <!-- Добавим Font Awesome для иконок -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<header>
    <div class="logo">
        <svg viewBox="0 0 24 24">
            <path d="M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3M17,7H7V5H17M17,9H7V11H17V9M7,13H13V15H7V13Z"/>
        </svg>
        TaskJunior
    </div>
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
            <li><a href="dashboard.php" class="active">Доски</a></li>
            <?php if ($user_role === 'manager' || $user_role === 'developer'): ?>
                <li><a href="admin.php">Админка</a></li>
            <?php endif; ?>
            <li><a href="logout.php">Выйти</a></li>
            <li><button id="theme-toggle-button">Сменить тему</button></li>
            <li><span id="user-info">Привет, <?php echo htmlspecialchars($username); ?>!</span></li>
        </ul>
    </nav>
</header>

<main>
    <!-- Заголовок и статистика -->
    <div class="dashboard-header">
        <div class="title-section">
            <h1>Ваши доски</h1>
            <p class="subtitle">Управляйте своими проектами и задачами</p>
        </div>
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-value" id="total-boards">--</div>
                <div class="stat-label">Всего досок</div>
            </div>
        </div>
    </div>

    <!-- Фильтры и поиск -->
    <div class="dashboard-actions">
        <div class="search-filter">
            <div class="search-box">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="searchInput" placeholder="Поиск досок...">
            </div>
            <div class="filter-box">
                <select id="filterBoards">
                    <option value="all">Все доски</option>
                    <option value="my">Мои доски</option>
                    <option value="shared">Общие доски</option>
                </select>
            </div>
        </div>
        <button id="createBoardButton" class="primary-button">
            <i class="fa-solid fa-plus"></i> Создать доску
        </button>
    </div>

    <!-- Форма создания доски -->
    <div id="createBoardFormContainer" class="modal-container" style="display: none;">
        <div class="modal-content">
            <span class="close-button" id="closeBoardForm">&times;</span>
            <h2>Создать новую доску</h2>
            <form id="createBoardForm">
                <div class="input-group">
                    <label for="boardTitle">Название доски</label>
                    <input type="text" id="boardTitle" name="boardTitle" placeholder="Введите название доски" required>
                </div>
                <div class="input-group">
                    <label for="boardDescription">Описание</label>
                    <textarea id="boardDescription" name="boardDescription" placeholder="Опишите назначение доски" rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="secondary-button" id="cancelCreateBoard">Отмена</button>
                    <button type="submit" class="primary-button">Создать доску</button>
                </div>
                <div id="message" class="message-box"></div>
            </form>
        </div>
    </div>

    <!-- Список досок -->
    <div class="boards-container">
        <div class="boards-wrapper" style="max-width: 1200px; margin: 0 auto;">
            <div id="boards" class="boards-grid"></div>
        </div>
    </div>
</main>

</body>
</html>
