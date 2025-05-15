<?php session_start();
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
</head>
<body>
<header>
    <div class="logo">
        <svg viewBox="0 0 24 24">
            <path d="M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3M17,7H7V5H17M17,9H7V11H17V9M7,13H13V15H7V13Z"/>
        </svg>
        Задачник
    </div>
    <nav>
        <div class="notifications-container">
            <span id="notifications-icon" class="notifications-icon">🔔
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
            <?php if ($_SESSION['user']['role'] === 'manager' || $_SESSION['user']['role'] === 'developer'): ?>
                <li><a href="admin.php">Админка</a></li>
            <?php endif; ?>
            <li><a href="logout.php">Выйти</a></li>
            <li><button id="theme-toggle-button">Сменить тему</button></li>
        </ul>
    </nav>
</header>
<main>
    <h1>Задачи на доске</h1>
    <p id="current-user-board-role-display" style="margin-bottom: 15px; font-style: italic;"></p>

    <!-- Кнопка -->
    <button id="toggleTaskFormButton">Добавить задачу</button>

    <!-- Форма создания задачи -->
    <form id="createTaskForm" style="display: none; margin-top: 20px;">
        <div class="input-group">
            <label for="taskTitle">Название задачи:</label>
            <input type="text" id="taskTitle" name="taskTitle" required />
        </div>
        <div class="input-group">
            <label for="taskDescription">Описание задачи:</label>
            <textarea id="taskDescription" name="taskDescription"></textarea>
        </div>
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
        <div class="input-group">
            <label for="taskDeadline">Дедлайн:</label>
            <input type="date" id="taskDeadline" name="taskDeadline">
        </div>
        <button type="submit">Создать задачу</button>
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
                <button type="submit">Сохранить изменения</button>
            </form>
        </div>
    </div>
</main>

</body>
</html>
