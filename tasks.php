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
    <!-- <script src="js/tasks.js" defer></script> -->
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
                    <label for="editTaskAssignedUser">Назначить пользователя (ID):</label>
                    <input type="number" id="editTaskAssignedUser" name="editTaskAssignedUser" placeholder="ID пользователя">
                </div>
                <button type="submit">Сохранить изменения</button>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const boardId = new URLSearchParams(window.location.search).get('board_id');
    const taskForm = document.getElementById('createTaskForm');
    const taskMessage = document.getElementById('taskMessage');
    const toggleTaskButton = document.getElementById('toggleTaskFormButton');
    const taskBoardColumnsContainer = document.querySelector('.task-board-columns'); // Контейнер для всех колонок

    // --- Логика для формы создания задачи (остается без изменений) ---
    if (toggleTaskButton) {
        toggleTaskButton.addEventListener('click', () => {
            taskForm.style.display = taskForm.style.display === 'none' ? 'block' : 'none';
            if(taskMessage) taskMessage.textContent = '';
        });
    }
    if (taskForm) {
        taskForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const title = document.getElementById('taskTitle').value.trim();
            const description = document.getElementById('taskDescription').value.trim();
            const status = document.getElementById('taskStatus').value;
            const priority = document.getElementById('taskPriority').value;
            const deadline = document.getElementById('taskDeadline').value;

            if (!title) {
                if(taskMessage) {
                    taskMessage.textContent = 'Введите название задачи.';
                    taskMessage.style.color = 'red';
                }
                return;
            }

            fetch('api/tasks.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    board_id: parseInt(boardId), title, description, status, priority,
                    deadline: deadline === '' ? null : deadline
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if(taskMessage) {
                        taskMessage.textContent = 'Задача успешно создана!';
                        taskMessage.style.color = 'green';
                    }
                    taskForm.reset();
                    setTimeout(() => {
                        if (taskForm) taskForm.style.display = 'none';
                        loadTasks();
                    }, 1000);
                } else {
                    if(taskMessage) {
                        taskMessage.textContent = 'Ошибка: ' + (data.error || 'Неизвестная ошибка');
                        taskMessage.style.color = 'red';
                    }
                }
            })
            .catch(error => {
                console.error('Ошибка создания задачи:', error);
                if(taskMessage) {
                    taskMessage.textContent = 'Произошла сетевая ошибка.';
                    taskMessage.style.color = 'red';
                }
            });
        });
    }

    // --- Функция отрисовки задач в колонках ---
    function renderTasksInColumns(tasks) {
        console.log("renderTasksInColumns called with tasks:", tasks);
        const statuses = ['В ожидании', 'В работе', 'Завершено'];
        statuses.forEach(status => {
            const list = document.querySelector(`.task-list[data-status-column="${status}"]`);
            if (list) list.innerHTML = ''; // Очищаем колонки
        });

        if (!tasks || !tasks.length) {
            const pendingList = document.querySelector('.task-list[data-status-column="В ожидании"]');
            if (pendingList) {
                pendingList.innerHTML = `<div class="empty-state">Нет задач. Создайте первую!</div>`;
            }
            renderGanttChart([]);
            return;
        }

        tasks.forEach(task => {
            const deadlineDate = task.deadline ? task.deadline.split(' ')[0] : '';
            const card = document.createElement('div');
            card.className = 'task-card';
            card.setAttribute('draggable', true);
            card.dataset.taskId = task.id; // Сохраняем ID задачи

            let assignedUserInfo = '';
            if (task.assigned_username) {
                assignedUserInfo = `<div class="assigned-user">Назначено: ${task.assigned_username}</div>`;
            } else if (task.assigned_to) {
                assignedUserInfo = `<div class="assigned-user">Назначено ID: ${task.assigned_to}</div>`;
            }

            let progressIndicator = '';
            if (task.progress !== undefined && task.progress !== null) {
                progressIndicator = `<div class="progress-bar-container">
                                       <div class="progress-bar" style="width: ${task.progress}%;">${task.progress}%</div>
                                   </div>`;
            }

            card.innerHTML = `
                <div class="title">${task.title}</div>
                <div class="description">${task.description || '-'}</div>
                ${assignedUserInfo}
                ${progressIndicator}
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
                <input type="date" class="deadline-input" data-id="${task.id}" value="${deadlineDate}">
                <button class="delete-button" data-id="${task.id}">Удалить</button>
                <button class="edit-button" data-id="${task.id}">Редактировать</button>
            `;
            const listContainer = document.querySelector(`.task-list[data-status-column="${task.status}"]`);
            if (listContainer) {
                listContainer.appendChild(card);
            } else {
                // Если колонка для статуса не найдена, добавляем в "В ожидании"
                const pendingList = document.querySelector('.task-list[data-status-column="В ожидании"]');
                if (pendingList) pendingList.appendChild(card);
            }
        });
        initializeDragAndDrop(); // Инициализация drag-n-drop после отрисовки
        renderGanttChart(tasks);
    }

    // --- Загрузка задач ---
    window.loadTasks = function() {
        fetch(`api/tasks.php?action=get&board_id=${boardId}`)
            .then(res => {
                if (!res.ok) {
                    return res.json().then(err => { throw new Error(err.error || `HTTP error! status: ${res.status}`); })
                                   .catch(() => { throw new Error(`HTTP error! status: ${res.status}`); });
                }
                return res.json();
            })
            .then(tasks => {
                if (typeof tasks === 'object' && tasks !== null && !Array.isArray(tasks) && tasks.error) {
                    console.error('Ошибка от API при загрузке задач:', tasks.error);
                    renderTasksInColumns([]);
                    const pendingList = document.querySelector('.task-list[data-status-column="В ожидании"]');
                    if (pendingList) {
                        pendingList.innerHTML = `<div class="empty-state">Не удалось загрузить задачи: ${tasks.error}</div>`;
                    }
                } else if (Array.isArray(tasks)) {
                    renderTasksInColumns(tasks);
                } else {
                    console.error('Ожидался массив задач, получено:', tasks);
                    renderTasksInColumns([]); // Показываем пустые колонки
                }
            })
            .catch(error => {
                console.error('Ошибка при загрузке задач:', error);
                const pendingList = document.querySelector('.task-list[data-status-column="В ожидании"]');
                if (pendingList) {
                    pendingList.innerHTML = `<div class="empty-state">Не удалось загрузить задачи: ${error.message}</div>`;
                }
                renderTasksInColumns([]);
            });
    }

    // --- Делегирование событий для карточек ---
    if (taskBoardColumnsContainer) {
        taskBoardColumnsContainer.addEventListener('click', function(event) {
            const target = event.target;

            // Кнопка "Удалить"
            if (target.classList.contains('delete-button')) {
                console.log("Delegated: Delete button clicked for task ID:", target.dataset.id);
                const taskId = target.dataset.id;
                if (confirm("Удалить задачу?")) {
                    console.log("Delegated: Confirmed delete for task ID:", taskId);
                    fetch('api/tasks.php?action=delete', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: parseInt(taskId) })
                    }).then(res => res.json()).then(data => {
                        if (data.success) {
                            loadTasks();
                        } else {
                            alert('Ошибка удаления: ' + data.error);
                        }
                    });
                }
            }

            // Кнопка "Редактировать"
            if (target.classList.contains('edit-button')) {
                console.log("Delegated: Edit button clicked for task ID:", target.dataset.id);
                const taskId = target.dataset.id;
                fetch(`api/tasks.php?action=get_details&task_id=${taskId}`)
                    .then(res => res.json())
                    .then(taskDetails => {
                        if (taskDetails && !taskDetails.error) {
                            console.log("Delegated: Task details fetched for edit:", taskDetails);
                            openEditModal(taskDetails);
                        } else {
                            alert('Не удалось загрузить детали задачи: ' + (taskDetails.error || 'Неизвестная ошибка'));
                        }
                    })
                    .catch(err => {
                        console.error('Ошибка загрузки деталей задачи для редактирования:', err);
                        alert('Ошибка загрузки деталей задачи.');
                    });
            }
        });

        // Обработка изменения select'ов (статус, приоритет) и input (дедлайн)
        taskBoardColumnsContainer.addEventListener('change', function(event) {
            const target = event.target;
            const taskId = target.dataset.id;

            if (!taskId) return; // Если data-id нет, ничего не делаем

            if (target.classList.contains('status-select')) {
                console.log("Delegated: Status changed to", target.value, "for task ID:", taskId);
                const status = target.value;
                const progress = status === 'В работе' ? 50 : (status === 'Завершено' ? 100 : 0);
                fetch('api/tasks.php?action=update_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: parseInt(taskId), status, progress })
                }).then(res => res.json()).then(data => {
                    if (data.success) loadTasks(); else alert('Ошибка обновления статуса: ' + data.error);
                });
            }

            if (target.classList.contains('priority-select')) {
                console.log("Delegated: Priority changed to", target.value, "for task ID:", taskId);
                const priority = target.value;
                fetch('api/tasks.php?action=update_details', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: parseInt(taskId), priority })
                }).then(res => res.json()).then(data => {
                    if (data.success) loadTasks(); else alert('Ошибка обновления приоритета: ' + data.error);
                });
            }

            if (target.classList.contains('deadline-input')) {
                console.log("Delegated: Deadline changed to", target.value, "for task ID:", taskId);
                const deadline = target.value;
                fetch('api/tasks.php?action=update_details', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: parseInt(taskId), deadline: deadline === '' ? null : deadline })
                }).then(res => res.json()).then(data => {
                    if (data.success) loadTasks(); else alert('Ошибка обновления дедлайна: ' + data.error);
                });
            }
        });
    }


    // --- Логика модального окна редактирования (остается почти без изменений) ---
    const editTaskModal = document.getElementById('editTaskModal');
    const editTaskForm = document.getElementById('editTaskForm');
    const closeButton = editTaskModal ? editTaskModal.querySelector('.close-button') : null;

    window.openEditModal = function(task) { // Сделаем глобальной для доступа
        if (!editTaskModal || !editTaskForm) return;
        document.getElementById('editTaskId').value = task.id;
        document.getElementById('editTaskTitle').value = task.title;
        document.getElementById('editTaskDescription').value = task.description || '';
        document.getElementById('editTaskPriority').value = task.priority;
        const deadlineDate = task.deadline ? task.deadline.split(' ')[0] : '';
        document.getElementById('editTaskDeadline').value = deadlineDate;
        // Важно: при открытии модалки, нужно сохранить текущего назначенного пользователя, чтобы потом сравнить
        const assignedUserInput = document.getElementById('editTaskAssignedUser');
        assignedUserInput.value = task.assigned_to || '';
        assignedUserInput.dataset.originalAssignedTo = task.assigned_to || '';

        editTaskModal.style.display = 'flex';
    }

    function closeEditModal() {
        if (editTaskModal) editTaskModal.style.display = 'none';
    }

    if (closeButton) {
        closeButton.addEventListener('click', closeEditModal);
    }
    window.addEventListener('click', (event) => {
        if (event.target == editTaskModal) {
            closeEditModal();
        }
    });

    if (editTaskForm) {
        editTaskForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const taskId = document.getElementById('editTaskId').value;
            const title = document.getElementById('editTaskTitle').value.trim();
            const description = document.getElementById('editTaskDescription').value.trim();
            const priority = document.getElementById('editTaskPriority').value;
            const deadline = document.getElementById('editTaskDeadline').value;
            const assignedUserInput = document.getElementById('editTaskAssignedUser');
            const newAssignedToString = assignedUserInput.value.trim();
            const originalAssignedToString = assignedUserInput.dataset.originalAssignedTo || '';


            if (!title) {
                alert('Название задачи не может быть пустым.'); return;
            }

            const taskDetailsData = {
                id: parseInt(taskId), title, description, priority,
                deadline: deadline === '' ? null : deadline,
            };

            // 1. Обновляем основные детали задачи
            fetch('api/tasks.php?action=update_details', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(taskDetailsData)
            })
            .then(res => res.json())
            .then(detailsData => {
                if (detailsData.success) {
                    // 2. Если детали обновлены успешно, проверяем, нужно ли обновить назначение
                    let newAssignedToId = null;
                    if (newAssignedToString !== '') {
                        newAssignedToId = parseInt(newAssignedToString);
                        if (isNaN(newAssignedToId)) {
                            alert('ID пользователя для назначения должен быть числом.');
                            // Можно не закрывать модалку и не перезагружать задачи, если ошибка только в ID
                            return;
                        }
                    }

                    // Обновляем назначение только если оно изменилось
                    const originalAssignedToId = originalAssignedToString === '' ? null : parseInt(originalAssignedToString);

                    if (newAssignedToId !== originalAssignedToId) {
                        fetch('api/tasks.php?action=assign', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: parseInt(taskId), assignee_user_id: newAssignedToId })
                        })
                        .then(assignRes => assignRes.json())
                        .then(assignData => {
                            if (assignData.success) {
                                console.log('Назначение обновлено успешно.');
                            } else {
                                alert('Ошибка обновления назначения: ' + (assignData.error || 'Неизвестная ошибка'));
                            }
                            closeEditModal();
                            loadTasks(); // Перезагружаем задачи в любом случае после попытки назначения
                        });
                    } else {
                        // Назначение не изменилось, просто закрываем и перезагружаем
                        closeEditModal();
                        loadTasks();
                    }
                } else {
                    alert('Ошибка обновления задачи: ' + (detailsData.error || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                console.error('Сетевая ошибка при обновлении задачи:', error);
                alert('Сетевая ошибка при обновлении задачи.');
            });
        });
    }

    // --- Drag and Drop Logic ---
    let draggedItem = null;

    function initializeDragAndDrop() {
        const taskCards = document.querySelectorAll('.task-card');
        const taskLists = document.querySelectorAll('.task-list'); // Колонки-контейнеры

        taskCards.forEach(card => {
            card.addEventListener('dragstart', function(event) {
                draggedItem = this;
                event.dataTransfer.setData('text/plain', this.dataset.taskId); // Для Firefox
                setTimeout(() => {
                    this.style.opacity = '0.5';
                }, 0);
            });

            card.addEventListener('dragend', function() {
                setTimeout(() => {
                    if (draggedItem) draggedItem.style.opacity = '1';
                    draggedItem = null;
                }, 0);
            });
        });

        taskLists.forEach(list => {
            list.addEventListener('dragover', function(event) {
                event.preventDefault();
                this.classList.add('drag-over');
            });

            list.addEventListener('dragleave', function() {
                this.classList.remove('drag-over');
            });

            list.addEventListener('drop', function(event) {
                event.preventDefault();
                this.classList.remove('drag-over');
                if (draggedItem && draggedItem.parentNode !== this) { // Только если перетаскиваем в другую колонку
                    this.appendChild(draggedItem); // Перемещаем карточку в DOM
                    const taskId = draggedItem.dataset.taskId;
                    const newStatus = this.dataset.statusColumn; // Статус из data-атрибута колонки
                    let progress = 0;
                    if (newStatus === 'В работе') progress = 50;
                    else if (newStatus === 'Завершено') progress = 100;

                    // Обновляем статус на сервере
                    fetch('api/tasks.php?action=update_status', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: parseInt(taskId), status: newStatus, progress })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            console.log('Статус задачи обновлен через drag-n-drop');
                            // Обновляем select и прогресс-бар внутри карточки
                            const statusSelect = draggedItem.querySelector('.status-select');
                            if(statusSelect) statusSelect.value = newStatus;
                            const progressBar = draggedItem.querySelector('.progress-bar');
                            if(progressBar) {
                                progressBar.style.width = progress + '%';
                                progressBar.textContent = progress + '%';
                            } else if (progress > 0) { // Если прогресс-бара не было, а он должен быть
                                const indicatorContainer = draggedItem.querySelector('.progress-bar-container');
                                if(indicatorContainer) { // Если есть контейнер, но нет бара
                                   indicatorContainer.innerHTML = `<div class="progress-bar" style="width: ${progress}%;">${progress}%</div>`;
                                } else { // Если нет даже контейнера (маловероятно, но для полноты)
                                   // Можно добавить логику создания контейнера и бара
                                }
                            } else if (progress === 0) { // Если прогресс 0, убрать бар
                                const indicatorContainer = draggedItem.querySelector('.progress-bar-container');
                                if(indicatorContainer) indicatorContainer.innerHTML = '';
                            }
                        } else {
                            alert('Ошибка обновления статуса: ' + data.error);
                            loadTasks(); // Перезагрузка для восстановления консистентности
                        }
                    })
                    .catch(error => {
                        console.error('Сетевая ошибка при обновлении статуса (drag-n-drop):', error);
                        loadTasks(); // Перезагрузка для восстановления консистентности
                    });
                } else if (draggedItem) { // Карточка брошена в ту же колонку
                     draggedItem.style.opacity = '1'; // Восстановить видимость
                }
                 draggedItem = null; // Всегда сбрасывать draggedItem
            });
        });
    }

    // --- Логика диаграммы Ганта (без изменений) ---
    function renderGanttChart(tasks) {
        const ganttChartContainer = document.getElementById('gantt-chart');
        if (!ganttChartContainer || typeof Gantt === 'undefined') {
            return;
        }
        ganttChartContainer.innerHTML = '';

        const ganttTasks = tasks.filter(task => task.created_at && task.deadline)
            .map(task => {
                const startDate = new Date(task.created_at.split(' ')[0]);
                const endDate = new Date(task.deadline.split(' ')[0]);
                if (isNaN(startDate.getTime()) || isNaN(endDate.getTime()) || startDate > endDate) return null;
                const formatDate = (dateObj) => dateObj.toISOString().split('T')[0];
                return {
                    id: String(task.id), name: task.title, start: formatDate(startDate), end: formatDate(endDate),
                    progress: task.progress !== undefined ? task.progress : (task.status === 'Завершено' ? 100 : (task.status === 'В работе' ? 50 : 0)),
                };
            }).filter(task => task !== null);

        if (ganttTasks.length > 0) {
            try {
                new Gantt("#gantt-chart", ganttTasks, {
                    header_height: 50, column_width: 30, step: 24,
                    view_modes: ['Day', 'Week', 'Month'], bar_height: 20, bar_corner_radius: 3,
                    arrow_curve: 5, padding: 18, view_mode: 'Week', date_format: 'YYYY-MM-DD',
                    custom_popup_html: task => `
                        <div class="gantt-popup-content" style="padding:5px;">
                          <strong>${task.name}</strong><br>
                          Период: ${task.start} - ${task.end}<br>
                          Прогресс: ${task.progress}%
                        </div>`
                });
            } catch (e) { console.error("Ошибка Ганта:", e); ganttChartContainer.innerHTML = "<p>Ошибка диаграммы.</p>"; }
        } else {
            ganttChartContainer.innerHTML = "<p>Нет задач для диаграммы Ганта.</p>";
        }
    }

    // Первоначальная загрузка задач
    if (boardId) {
        loadTasks();
    } else {
        if (taskBoardColumnsContainer) {
             taskBoardColumnsContainer.innerHTML = '<h1>ID доски не указан. Невозможно загрузить задачи.</h1>';
        }
        const ganttContainer = document.getElementById('gantt-chart-container');
        if (ganttContainer) ganttContainer.innerHTML = '';
    }
});
</script>
</body>
</html>
