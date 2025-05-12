<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <title>Задачи</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css ">
    <script src="js/tasks.js" defer></script>
    <script src="js/theme.js" defer></script>
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
        <ul>
            <li><a href="dashboard.php">Доски</a></li>
            <?php if ($_SESSION['user']['role'] === 'manager' || $_SESSION['user']['role'] === 'developer'): ?>
                <li><a href="admin.php">Админка</a></li>
            <?php endif; ?>
            <li><a href="logout.php">Выйти</a></li>
            <li><button onclick="toggleTheme()">Сменить тему</button></li>
        </ul>
    </nav>
</header>
<main>
    <h1>Задачи на доске</h1>

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
    <div id="tasks"></div>

    <h2>Участники доски</h2>
    <div class="add-member">
        <select id="member-select"></select>
        <button onclick="addMember()">Добавить участника</button>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const boardId = new URLSearchParams(window.location.search).get('board_id');
    const taskForm = document.getElementById('createTaskForm');
    const taskMessage = document.getElementById('taskMessage');
    const toggleTaskButton = document.getElementById('toggleTaskFormButton');

    // Переключение формы
    toggleTaskButton.addEventListener('click', () => {
        taskForm.style.display = taskForm.style.display === 'none' ? 'block' : 'none';
        taskMessage.textContent = '';
    });

    // Отправка формы
    taskForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const title = document.getElementById('taskTitle').value.trim();
        const description = document.getElementById('taskDescription').value.trim();
        const status = document.getElementById('taskStatus').value;
        const priority = document.getElementById('taskPriority').value;
        const deadline = document.getElementById('taskDeadline').value;

        if (!title) {
            taskMessage.textContent = 'Введите название задачи.';
            taskMessage.style.color = 'red';
            return;
        }

        fetch('api/tasks.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                board_id: parseInt(boardId),
                title,
                description,
                status,
                priority,
                deadline
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                taskMessage.textContent = 'Задача успешно создана!';
                taskMessage.style.color = 'green';
                taskForm.reset();
                setTimeout(() => {
                    taskForm.style.display = 'none';
                    loadTasks(); // Обновление списка задач
                }, 1000);
            } else {
                taskMessage.textContent = 'Ошибка: ' + (data.error || 'Неизвестная ошибка');
                taskMessage.style.color = 'red';
            }
        })
        .catch(error => {
            console.error('Ошибка создания задачи:', error);
            taskMessage.textContent = 'Произошла сетевая ошибка.';
            taskMessage.style.color = 'red';
        });
    });

    function loadUsers() {
        fetch('api/users.php?action=get')
            .then(res => res.json())
            .then(users => {
                const select = document.getElementById('member-select');
                users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = user.username;
                    select.appendChild(option);
                });
            });
    }

    function addMember() {
        const userId = document.getElementById('member-select').value;
        fetch('api/boards.php?action=add_member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ board_id: parseInt(boardId), user_id: parseInt(userId) })
        }).then(res => res.json()).then(data => {
            if (data.success) alert('Участник добавлен!');
        });
    }

    function loadTasks() {
        fetch(`api/tasks.php?action=get&board_id=${boardId}`)
            .then(res => res.json())
            .then(tasks => {
                const tasksContainer = document.getElementById('tasks');
                tasksContainer.innerHTML = '';
                if (!tasks.length) {
                    tasksContainer.innerHTML = `<div class="empty-state">Нет задач. Создайте первую!</div>`;
                } else {
                    tasks.forEach(task => {
                        const deadline = task.deadline || '';
                        const card = document.createElement('div');
                        card.className = 'task-card';
                        card.innerHTML = `
                            <div class="title">${task.title}</div>
                            <div class="description">${task.description || '-'}</div>
                            <select class="status-select" data-id="${task.id}">
                                <option value="В ожидании" ${task.status === 'В ожидании' ? 'selected' : ''}>В ожидании</option>
                                <option value="В работе" ${task.status === 'В работе' ? 'selected' : ''}>В работе</option>
                                <option value="Завершено" ${task.status === 'Завершено' ? 'selected' : ''}>Завершено</option>
                            </select>
                            <select class="priority-select" data-id="${task.id}">
                                <option value="низкий" ${task.priority === 'низкий' ? 'selected' : ''}>Низкий</option>
                                <option value="средний" ${task.priority === 'средний' ? 'selected' : ''}>Средний</option>
                                <option value="высокий" ${task.priority === 'высокий' ? 'selected' : ''}>Высокий</option>
                            </select>
                            <input type="date" class="deadline-input" data-id="${task.id}" value="${deadline}">
                            <button class="delete-button" data-id="${task.id}">Удалить</button>
                        `;
                        tasksContainer.appendChild(card);
                    });

                    // Обработчики событий
                    document.querySelectorAll('.status-select').forEach(select => {
                        select.addEventListener('change', e => {
                            const taskId = e.target.dataset.id;
                            const status = e.target.value;
                            const progress = status === 'В работе' ? 50 : status === 'Завершено' ? 100 : 0;
                            fetch('api/tasks.php?action=update_status', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: taskId, status, progress })
                            }).then(() => loadTasks());
                        });
                    });

                    document.querySelectorAll('.priority-select').forEach(select => {
                        select.addEventListener('change', e => {
                            const taskId = e.target.dataset.id;
                            const priority = e.target.value;
                            fetch('api/tasks.php?action=update_priority', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: taskId, priority })
                            }).then(() => loadTasks());
                        });
                    });

                    document.querySelectorAll('.deadline-input').forEach(input => {
                        input.addEventListener('change', e => {
                            const taskId = e.target.dataset.id;
                            const deadline = e.target.value;
                            fetch('api/tasks.php?action=update_deadline', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: taskId, deadline })
                            }).then(() => loadTasks());
                        });
                    });

                    document.querySelectorAll('.delete-button').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const taskId = btn.dataset.id;
                            if (confirm("Удалить задачу?")) {
                                fetch('api/tasks.php?action=delete', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ id: taskId })
                                }).then(() => loadTasks());
                            }
                        });
                    });
                }
            });
    }

    loadUsers();
    loadTasks();
});
</script>
</body>
</html>