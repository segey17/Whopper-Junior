<?php session_start();
require 'db.php'; // Убран ../ чтобы путь был корректным
// 1. Проверка, вошел ли пользователь
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}
// Теперь можно безопасно использовать $_SESSION['user']['id'], $_SESSION['user']['username'], $_SESSION['user']['role']
// Например, для следующего TODO:
// $current_user_id = $_SESSION['user']['id'];
// TODO: Добавить проверку, имеет ли пользователь $current_user_id доступ к board_id, указанному в GET-параметре
?>
<!DOCTYPE html>
<html>
<head>
    <title>Задачи</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css ">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.css">
    <script src="https://cdn.jsdelivr.net/npm/frappe-gantt@0.6.1/dist/frappe-gantt.min.js"></script>
    <script src="js/theme.js" defer></script>
    <script src="js/notifications.js" defer></script>
    <script src="js/tasks.js" defer></script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <script>
        const PUSHER_APP_KEY = '<?php echo getenv('PUSHER_APP_KEY') ?: 'dbe89bd713c5f93e5e19'; ?>'; // Используем переменную окружения или значение по умолчанию
        const PUSHER_APP_CLUSTER = '<?php echo getenv('PUSHER_APP_CLUSTER') ?: 'eu'; ?>'; // Используем переменную окружения или значение по умолчанию
        const boardIdFromPHP = <?php echo json_encode($_GET['board_id'] ?? null); ?>;
        const currentUserIdFromPHP = <?php echo json_encode($_SESSION['user']['id']); ?>;
    </script>
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
            <li><a href="dashboard.php">Доски</a></li>
            <?php if (isset($_SESSION['user']['role']) && ($_SESSION['user']['role'] === 'manager' || $_SESSION['user']['role'] === 'admin')): // Проверяем роль пользователя ?>
                <li><a href="admin.php">Админка</a></li>
            <?php endif; ?>
            <li><a href="logout.php">Выйти</a></li>
            <li><button id="theme-toggle-button">Сменить тему</button></li>
        </ul>
    </nav>
</header>
<main>
    <div class="dashboard-header">
        <div class="title-section">
            <h1 id="boardNameTitle">Задачи на доске</h1>
            <p id="current-user-board-role-display" class="subtitle"></p>
        </div>
        <div class="actions-section">
            <button id="toggleTaskFormButton" class="primary-button">
                <i class="fas fa-plus"></i> Добавить задачу
            </button>
        </div>
    </div>

    <div class="task-filters">
        <div class="search-container">
            <input type="text" id="taskSearchInput" placeholder="Поиск задач..." class="search-input">
            <i class="fas fa-search search-icon"></i>
        </div>
        <div class="filter-container">
            <select id="priorityFilter" class="filter-select">
                <option value="all">Все приоритеты</option>
                <option value="низкий">Низкий</option>
                <option value="средний">Средний</option>
                <option value="высокий">Высокий</option>
            </select>
            <select id="assigneeFilter" class="filter-select">
                <option value="all">Все пользователи</option>
                <option value="me">Мои задачи</option>
                <option value="unassigned">Неназначенные</option>
                <!-- Участники доски будут добавлены через JS -->
            </select>
        </div>
    </div>

    <!-- Форма создания задачи -->
    <form id="createTaskForm" style="display: none; margin-top: 20px;">
        <div class="form-header">
            <h3>Создание новой задачи</h3>
            <p class="subtitle">Заполните информацию о задаче</p>
        </div>
        <div class="input-group">
            <label for="taskTitle">Название задачи:</label>
            <input type="text" id="taskTitle" name="taskTitle" required placeholder="Введите название задачи" />
        </div>
        <div class="input-group">
            <label for="taskDescription">Описание задачи:</label>
            <textarea id="taskDescription" name="taskDescription" placeholder="Опишите задачу подробнее..."></textarea>
        </div>
        <div class="form-row">
            <div class="input-group">
                <label for="taskStatus">Статус:</label>
                <select id="taskStatus" name="taskStatus">
                    <option value="В ожидании">В ожидании</option>
                    <option value="В работе">В работе</option>
                    <option value="Завершено">Завершено</option>
                </select>
            </div>
            <div class="input-group">
                <label for="taskPriority">Приоритет:</label>
                <select id="taskPriority" name="taskPriority">
                    <option value="низкий">Низкий</option>
                    <option value="средний">Средний</option>
                    <option value="высокий">Высокий</option>
                </select>
            </div>
        </div>
        <div class="input-group">
            <label for="taskDeadline">Дедлайн:</label>
            <input type="date" id="taskDeadline" name="taskDeadline">
        </div>
        <div class="form-actions">
            <button type="submit">Создать задачу</button>
            <button type="button" id="cancelCreateTask" class="cancel-button">Отмена</button>
        </div>
    </form>

    <!-- Сообщение -->
    <div id="taskMessage" style="margin-top: 10px;"></div>

    <!-- Список задач -->
    <div class="task-board-columns">
        <div class="task-column" id="column-pending" data-status="В ожидании">
            <h2>В ожидании</h2>
            <div class="task-list" data-status-column="В ожидании"></div>
        </div>
        <div class="task-column" id="column-in-progress" data-status="В работе">
            <h2>В работе</h2>
            <div class="task-list" data-status-column="В работе"></div>
        </div>
        <div class="task-column" id="column-completed" data-status="Завершено">
            <h2>Завершено</h2>
            <div class="task-list" data-status-column="Завершено"></div>
        </div>
    </div>

    <h2>Диаграмма Ганта</h2>
    <div id="gantt-chart-container" style="margin-top: 20px;">
        <svg id="gantt-chart"></svg>
    </div>

    <!-- Модальное окно для редактирования задачи -->
    <div id="editTaskModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Редактировать задачу</h2>
            <form id="editTaskForm">
                <input type="hidden" id="editTaskId" name="editTaskId">
                <div class="input-group">
                    <label for="editTaskTitle">Название:</label>
                    <input type="text" id="editTaskTitle" name="editTaskTitle" required>
                </div>
                <div class="input-group">
                    <label for="editTaskDescription">Описание:</label>
                    <textarea id="editTaskDescription" name="editTaskDescription"></textarea>
                </div>
                <div class="input-group">
                    <label for="editTaskPriority">Приоритет:</label>
                    <select id="editTaskPriority" name="editTaskPriority">
                        <option value="низкий">Низкий</option>
                        <option value="средний">Средний</option>
                        <option value="высокий">Высокий</option>
                    </select>
                </div>
                <div class="input-group">
                    <label for="editTaskDeadline">Дедлайн:</label>
                    <input type="date" id="editTaskDeadline" name="editTaskDeadline">
                </div>
                <div class="input-group">
                    <label for="editTaskAssignedUser">Назначить пользователя:</label>
                    <select id="editTaskAssignedUser" name="editTaskAssignedUser">
                        <option value="">-- Не назначен --</option>
                        <!-- Пользователи будут подставлены через JS -->
                    </select>
                </div>
                <div class="input-group">
                    <label for="editTaskProgress">Прогресс выполнения (%):</label>
                    <div class="progress-edit-container">
                         <input type="range" id="editTaskProgressSlider" name="editTaskProgressSlider" min="0" max="100" value="0" class="progress-slider">
                         <input type="number" id="editTaskProgress" name="editTaskProgress" min="0" max="100" value="0" class="progress-input" style="width: 60px;">
                    </div>
                    <div class="progress-bar-container" style="margin-top: 5px;">
                        <div id="editProgressBar" class="progress-bar" style="width: 0%; background-color: #4CAF50; height: 20px; text-align: center; line-height: 20px; color: white;">0%</div>
                    </div>
                </div>
                <button type="submit">Сохранить изменения</button>
            </form>
        </div>
    </div>
</main>

</body>
</html>
