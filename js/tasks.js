document.addEventListener('DOMContentLoaded', () => {
    const boardId = new URLSearchParams(window.location.search).get('board_id');
    const taskForm = document.getElementById('createTaskForm');
    const taskMessage = document.getElementById('taskMessage'); // Для сообщений формы создания
    const toggleTaskButton = document.getElementById('toggleTaskFormButton');
    const taskBoardColumnsContainer = document.querySelector('.task-board-columns'); // Основной контейнер для колонок задач
    const roleDisplayElement = document.getElementById('current-user-board-role-display'); // Для отображения роли

    // Инициализация Pusher
    let pusher = null;
    // boardIdFromPHP передается из tasks.php
    const currentBoardId = typeof boardIdFromPHP !== 'undefined' ? boardIdFromPHP : new URLSearchParams(window.location.search).get('board_id');

    if (
        typeof Pusher !== 'undefined' &&
        typeof PUSHER_APP_KEY !== 'undefined' && PUSHER_APP_KEY &&
        typeof PUSHER_APP_CLUSTER !== 'undefined' && PUSHER_APP_CLUSTER
    ) {
        pusher = new Pusher(PUSHER_APP_KEY, {
            cluster: PUSHER_APP_CLUSTER
        });

        const taskChannel = pusher.subscribe('task-events');

        const handleTaskEvent = (eventName, data) => {
            console.log(`Pusher: Received event '${eventName}' on channel 'task-events'. Raw data:`, JSON.parse(JSON.stringify(data))); // Логируем сырые данные

            // Убедимся, что событие относится к текущей доске
            // currentBoardId уже должен быть строкой из URL или tasks.php
            if (data.board_id && data.board_id.toString() === currentBoardId) {
                console.log(`Pusher: Event '${eventName}' IS for the current board (${currentBoardId}). Applying update.`);

                let notificationMessage = '';
                if (eventName === 'task_created') {
                    notificationMessage = `Новая задача "${data.title}" добавлена.`;
                } else if (eventName === 'task_updated') {
                    notificationMessage = `Задача "${data.title}" (ID: ${data.id}) обновлена.`;
                } else if (eventName === 'task_deleted') {
                    // Для task_deleted data может содержать только id и board_id
                    notificationMessage = `Задача (ID: ${data.id}) удалена.`;
                } else {
                    notificationMessage = `Событие: ${eventName.replace(/_/g, ' ')} для задачи ID ${data.id || ''}`;
                }
                showToastForTask(notificationMessage, 'info');

                if (window.loadTasks) { // Убедимся, что функция loadTasks существует
                    window.loadTasks();
                }
            } else {
                console.warn(`Pusher: Event '${eventName}' IGNORED. Event board_id ('${data.board_id}', type: ${typeof data.board_id}) does not match current board_id ('${currentBoardId}', type: ${typeof currentBoardId}).`);
            }
        };

        taskChannel.bind('task_created', function(data) {
            handleTaskEvent('task_created', data);
        });

        // Заменяем старые обработчики на один для 'task_updated'
        taskChannel.bind('task_updated', function(data) {
            handleTaskEvent('task_updated', data);
        });

        taskChannel.bind('task_deleted', function(data) {
            handleTaskEvent('task_deleted', data);
        });

        // Удаляем старые специфичные обработчики, если они были
        // taskChannel.unbind('task_status_updated');
        // taskChannel.unbind('task_priority_updated');
        // taskChannel.unbind('task_deadline_updated');
        // taskChannel.unbind('task_assigned');

        pusher.connection.bind('connected', () => {
            console.log('Pusher: Successfully connected on tasks page!');
        });

        pusher.connection.bind('error', (err) => {
            console.error('Pusher: Connection error on tasks page:', err);
            if (err.error && err.error.data && err.error.data.code === 4004) {
                console.error('Pusher: App key not found or similar issue. Please check your Pusher App Key and Cluster in tasks.php.');
                showToastForTask('Ошибка риал-тайм обновлений: проверьте ключи Pusher.', 'error');
            } else {
                showToastForTask('Ошибка подключения к системе риал-тайм обновлений.', 'error');
            }
        });
    } else {
        console.warn('Pusher: SDK not loaded or PUSHER_APP_KEY/PUSHER_APP_CLUSTER are not defined or are empty. Realtime updates disabled on tasks page.');
        showToastForTask('Система риал-тайм обновлений не настроена (ключи не указаны или пусты).', 'warning');
    }

    // Вспомогательная функция для отображения уведомлений на странице задач
    function showToastForTask(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`; // Предполагается, что CSS для .toast уже есть
        toast.style.position = 'fixed';
        toast.style.bottom = '20px';
        toast.style.right = '20px';
        toast.style.padding = '15px';
        toast.style.backgroundColor = type === 'error' ? '#f44336' : type === 'success' ? '#4CAF50' : '#2196F3';
        toast.style.color = 'white';
        toast.style.borderRadius = '5px';
        toast.style.zIndex = '1000';
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s ease-in-out';
        toast.innerHTML = message;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '1';
        }, 10);

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                if (document.body.contains(toast)) {
                    document.body.removeChild(toast);
                }
            }, 500);
        }, 3000 + (type === 'error' ? 2000 : 0)); // Ошибки показываем дольше
    }

    // --- Логика для формы создания задачи ---
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

            // ВАЖНО: Убедимся, что boardId это число перед отправкой, если это значение из URL
            const boardIdInt = parseInt(boardId, 10);
            if (isNaN(boardIdInt)) {
                if(taskMessage) {
                    taskMessage.textContent = 'Ошибка: ID доски некорректен.';
                    taskMessage.style.color = 'red';
                }
                console.error("Create task: Invalid boardId:", boardId);
                return;
            }

            const taskData = {
                board_id: boardIdInt, // Используем преобразованный boardId
                title,
                description,
                status,
                priority,
                deadline: deadline === '' ? null : deadline,
                // assigned_to_user_id и progress будут установлены по умолчанию на бэкенде, если не переданы
            };

            fetch('api/tasks.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(taskData)
            })
            .then(res => {
                if (!res.ok) {
                    return res.text().then(text => { throw new Error(`Ошибка сервера: ${res.status} ${text}`); });
                }
                return res.json();
            })
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
                    taskMessage.textContent = 'Произошла ошибка: ' + error.message;
                    taskMessage.style.color = 'red';
                }
            });
        });
    }

    // --- Функция отрисовки задач в колонках ---
    function renderTasksInColumns(tasks, currentUserRoleKey) {
        console.log("renderTasksInColumns called with tasks:", tasks, "and roleKey:", currentUserRoleKey);
        const statuses = ['В ожидании', 'В работе', 'Завершено'];
        statuses.forEach(status => {
            const list = document.querySelector(`.task-list[data-status-column="${status}"]`);
            if (list) list.innerHTML = '';
        });

        if (!tasks || !tasks.length) {
            const pendingList = document.querySelector('.task-list[data-status-column="В ожидании"]');
            if (pendingList) {
                pendingList.innerHTML = `<div class="empty-state">Нет задач.</div>`;
            }
            renderGanttChart([]);
            return;
        }

        tasks.forEach(task => {
            const deadlineDate = task.deadline ? task.deadline.split(' ')[0] : '';
            const card = document.createElement('div');
            card.className = 'task-card';
            card.setAttribute('draggable', true);
            card.dataset.taskId = task.id;

            let assignedUserInfo = '';
            if (task.assigned_username) {
                assignedUserInfo = `<div class="assigned-user">Назначено: ${task.assigned_username}</div>`;
            } else if (task.assigned_to_user_id) {
                assignedUserInfo = `<div class="assigned-user">Назначено ID: ${task.assigned_to_user_id}</div>`;
            }

            let progressIndicator = '';
            if (task.progress !== undefined && task.progress !== null) {
                progressIndicator = `<div class="progress-bar-container">
                                       <div class="progress-bar" style="width: ${task.progress}%;">${task.progress}%</div>
                                   </div>`;
            }

            let actionButtons = '';
            if (currentUserRoleKey === 'owner') {
                actionButtons = `
                    <button class="delete-button" data-id="${task.id}">Удалить</button>
                    <button class="edit-button" data-id="${task.id}">Редактировать</button>
                `;
            } else if (currentUserRoleKey === 'participant_developer') {
                actionButtons = `
                    <button class="delete-button" data-id="${task.id}">Удалить</button>
                    <button class="edit-button" data-id="${task.id}">Редактировать</button>
                `;
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
                ${actionButtons}
            `;
            const listContainer = document.querySelector(`.task-list[data-status-column="${task.status}"]`);
            if (listContainer) {
                listContainer.appendChild(card);
            } else {
                const pendingList = document.querySelector('.task-list[data-status-column="В ожидании"]');
                if (pendingList) pendingList.appendChild(card);
            }
        });
        initializeDragAndDrop();
        renderGanttChart(tasks);
    }

    // --- Загрузка задач ---
    window.loadTasks = function() {
        if (!boardId) {
            if (taskBoardColumnsContainer) {
                 taskBoardColumnsContainer.innerHTML = '<h1>ID доски не указан. Невозможно загрузить задачи.</h1>';
            }
            const ganttContainer = document.getElementById('gantt-chart-container');
            if (ganttContainer) ganttContainer.innerHTML = '';
            if (roleDisplayElement) roleDisplayElement.textContent = '';
            return;
        }

        fetch(`api/tasks.php?action=get&board_id=${boardId}`)
            .then(res => {
                if (!res.ok) {
                    return res.json().then(err => { throw new Error(err.error || `HTTP error! status: ${res.status}`); })
                                   .catch(() => { throw new Error(`HTTP error! status: ${res.status}`); });
                }
                return res.json();
            })
            .then(responseData => {
                if (typeof responseData === 'object' && responseData !== null && responseData.tasks && Array.isArray(responseData.tasks)) {
                    if (roleDisplayElement && responseData.currentUserEffectiveRoleOnBoard) {
                        roleDisplayElement.textContent = `Ваша роль на этой доске: ${responseData.currentUserEffectiveRoleOnBoard}`;
                    }
                    renderTasksInColumns(responseData.tasks, responseData.currentUserEffectiveRoleKey);
                } else if (responseData && responseData.error) {
                    console.error('Ошибка от API при загрузке задач:', responseData.error);
                    renderTasksInColumns([], null);
                     const pendingList = document.querySelector('.task-list[data-status-column="В ожидании"]');
                    if (pendingList) {
                        pendingList.innerHTML = `<div class="empty-state">Не удалось загрузить задачи: ${responseData.error}</div>`;
                    }
                    if (roleDisplayElement) roleDisplayElement.textContent = 'Не удалось определить роль.';
                } else {
                    console.error('Ожидался объект с массивом задач, получено:', responseData);
                    renderTasksInColumns([], null);
                    if (roleDisplayElement) roleDisplayElement.textContent = 'Ошибка загрузки данных о роли.';
                }
            })
            .catch(error => {
                console.error('Ошибка при загрузке задач:', error);
                const pendingList = document.querySelector('.task-list[data-status-column="В ожидании"]');
                if (pendingList) {
                    pendingList.innerHTML = `<div class="empty-state">Не удалось загрузить задачи: ${error.message}</div>`;
                }
                renderTasksInColumns([], null);
                 if (roleDisplayElement) roleDisplayElement.textContent = 'Ошибка сети при загрузке роли.';
            });
    }

    // --- Делегирование событий для карточек ---
    if (taskBoardColumnsContainer) {
        taskBoardColumnsContainer.addEventListener('click', function(event) {
            const target = event.target;

            if (target.classList.contains('delete-button')) {
                const taskId = target.dataset.id;
                if (confirm("Удалить задачу?")) {
                    fetch('api/tasks.php?action=delete', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: parseInt(taskId) })
                    }).then(res => res.json()).then(data => {
                        if (data.success) {
                            // loadTasks(); // Обновление через Pusher, это можно убрать или оставить для подстраховки
                        } else {
                            alert('Ошибка удаления: ' + data.error);
                        }
                    });
                }
            }

            if (target.classList.contains('edit-button')) {
                const taskId = target.dataset.id;
                fetch(`api/tasks.php?action=get_details&task_id=${taskId}`)
                    .then(res => res.json())
                    .then(taskDetails => {
                        if (taskDetails && !taskDetails.error) {
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

        taskBoardColumnsContainer.addEventListener('change', function(event) {
            const target = event.target;
            const taskId = target.dataset.id;
            if (!taskId) return;

            if (target.classList.contains('status-select')) {
                const status = target.value;
                const progress = status === 'В работе' ? 50 : (status === 'Завершено' ? 100 : 0);
                fetch('api/tasks.php?action=update_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: parseInt(taskId), status, progress })
                }).then(res => res.json()).then(data => {
                    if (data.success) { /* loadTasks(); */ } else alert('Ошибка обновления статуса: ' + data.error);
                });
            }

            if (target.classList.contains('priority-select')) {
                const priority = target.value;
                fetch('api/tasks.php?action=update_details', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: parseInt(taskId), priority })
                }).then(res => res.json()).then(data => {
                    if (data.success) { /* loadTasks(); */ } else alert('Ошибка обновления приоритета: ' + data.error);
                });
            }

            if (target.classList.contains('deadline-input')) {
                const deadline = target.value;
                fetch('api/tasks.php?action=update_details', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: parseInt(taskId), deadline: deadline === '' ? null : deadline })
                }).then(res => res.json()).then(data => {
                    if (data.success) { /* loadTasks(); */ } else alert('Ошибка обновления дедлайна: ' + data.error);
                });
            }
        });
    }

    // --- Логика модального окна редактирования ---
    const editTaskModal = document.getElementById('editTaskModal');
    const editTaskForm = document.getElementById('editTaskForm');
    const closeButton = editTaskModal ? editTaskModal.querySelector('.close-button') : null;

    window.openEditModal = function(task) {
        if (!editTaskModal || !editTaskForm) return;
        document.getElementById('editTaskId').value = task.id;
        document.getElementById('editTaskTitle').value = task.title;
        document.getElementById('editTaskDescription').value = task.description || '';
        document.getElementById('editTaskPriority').value = task.priority;
        const deadlineDate = task.deadline ? task.deadline.split(' ')[0] : '';
        document.getElementById('editTaskDeadline').value = deadlineDate;

        const assignedUserInput = document.getElementById('editTaskAssignedUser');
        assignedUserInput.value = task.assigned_to_user_id || '';
        assignedUserInput.dataset.originalAssignedTo = task.assigned_to_user_id || '';

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
            const assignedUserSelect = document.getElementById('editTaskAssignedUser');
            const newAssignedToIdString = assignedUserSelect.value;
            const originalAssignedToIdString = assignedUserSelect.dataset.originalAssignedTo || '';

            if (!title) {
                alert('Название задачи не может быть пустым.'); return;
            }

            const taskDetailsData = {
                id: parseInt(taskId), title, description, priority,
                deadline: deadline === '' ? null : deadline,
            };

            fetch('api/tasks.php?action=update_details', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(taskDetailsData)
            })
            .then(res => res.json())
            .then(detailsData => {
                if (detailsData.success) {
                    let newAssignedToId = null;
                    if (newAssignedToIdString !== '') {
                        newAssignedToId = parseInt(newAssignedToIdString, 10);
                    }
                    const originalAssignedToId = originalAssignedToIdString === '' ? null : parseInt(originalAssignedToIdString, 10);

                    if (newAssignedToId !== originalAssignedToId) {
                        fetch('api/tasks.php?action=assign', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: parseInt(taskId), assignee_user_id: newAssignedToId })
                        })
                        .then(assignRes => assignRes.json())
                        .then(assignData => {
                            if (!assignData.success) {
                                alert('Ошибка обновления назначения: ' + (assignData.error || 'Неизвестная ошибка'));
                            }
                            closeEditModal();
                            // loadTasks(); // Обновление через Pusher
                        });
                    } else {
                        closeEditModal();
                        // loadTasks(); // Обновление через Pusher
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
        const taskLists = document.querySelectorAll('.task-list');

        taskCards.forEach(card => {
            card.addEventListener('dragstart', function(event) {
                draggedItem = this;
                event.dataTransfer.setData('text/plain', this.dataset.taskId);
                setTimeout(() => { this.style.opacity = '0.5'; }, 0);
            });
            card.addEventListener('dragend', function() {
                setTimeout(() => { if (draggedItem) draggedItem.style.opacity = '1'; draggedItem = null; }, 0);
            });
        });

        taskLists.forEach(list => {
            list.addEventListener('dragover', function(event) { event.preventDefault(); this.classList.add('drag-over'); });
            list.addEventListener('dragleave', function() { this.classList.remove('drag-over'); });
            list.addEventListener('drop', function(event) {
                event.preventDefault();
                this.classList.remove('drag-over');
                if (draggedItem && draggedItem.parentNode !== this) {
                    this.appendChild(draggedItem);
                    const taskId = draggedItem.dataset.taskId;
                    const newStatus = this.dataset.statusColumn;
                    let progress = 0;
                    if (newStatus === 'В работе') progress = 50;
                    else if (newStatus === 'Завершено') progress = 100;

                    fetch('api/tasks.php?action=update_status', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: parseInt(taskId), status: newStatus, progress })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const statusSelect = draggedItem.querySelector('.status-select');
                            if(statusSelect) statusSelect.value = newStatus;
                            const progressBar = draggedItem.querySelector('.progress-bar');
                            const progressBarContainer = draggedItem.querySelector('.progress-bar-container');

                            if (progressBar) {
                                if (progress > 0) {
                                    progressBar.style.width = progress + '%';
                                    progressBar.textContent = progress + '%';
                                } else {
                                   if(progressBarContainer) progressBarContainer.innerHTML = '';
                                }
                            } else if (progress > 0 && progressBarContainer) {
                                progressBarContainer.innerHTML = `<div class="progress-bar" style="width: ${progress}%;">${progress}%</div>`;
                            }
                        } else {
                            alert('Ошибка обновления статуса: ' + data.error); loadTasks();
                        }
                    })
                    .catch(error => { console.error('Сетевая ошибка (drag-n-drop):', error); loadTasks(); });
                } else if (draggedItem) {
                     draggedItem.style.opacity = '1';
                }
                 draggedItem = null; // Убедимся, что draggedItem сбрасывается
            });
        });
    }

    // --- Логика диаграммы Ганта ---
    function renderGanttChart(tasks) {
        const ganttChartContainer = document.getElementById('gantt-chart');
        if (!ganttChartContainer || typeof Gantt === 'undefined') return;
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

    // --- Функция загрузки пользователей для назначения ---
    function loadUsers() {
        const assignedUserSelect = document.getElementById('editTaskAssignedUser');
        if (!assignedUserSelect) {
            console.error('Элемент select для пользователей не найден');
            return;
        }

        // Настоящий board_id может быть из boardId (или currentBoardId)
        const bId = boardId || currentBoardId;
        if (!bId) {
            assignedUserSelect.innerHTML = '<option value="">-- Не назначен --</option>';
            return;
        }

        fetch(`api/boards.php?action=get_members&board_id=${bId}`)
            .then(response => {
                if (!response.ok) return response.json().then(e => { throw new Error(e.error || 'Ошибка API'); });
                return response.json();
            })
            .then(members => {
                assignedUserSelect.innerHTML = '<option value="">-- Не назначен --</option>';
                if (Array.isArray(members) && members.length > 0) {
                    // Исключаем владельца доски (user.is_owner === true || user.role === 'owner')
                    members.filter(user => !user.is_owner && user.role !== 'owner').forEach(user => {
                        const option = document.createElement('option');
                        option.value = user.id;
                        option.textContent = user.username + (user.role ? ` (Роль: ${user.role})` : '');
                        assignedUserSelect.appendChild(option);
                    });
                    // Если после фильтрации никого нет — добавим вариант "-- Нет участников --"
                    if (assignedUserSelect.options.length === 1) {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = '-- Нет участников --';
                        assignedUserSelect.appendChild(option);
                    }
                } else {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = '-- Нет участников --';
                    assignedUserSelect.appendChild(option);
                }
            })
            .catch(error => {
                console.error('Ошибка при загрузке участников доски:', error.message);
                assignedUserSelect.innerHTML = '<option value="">-- Не назначен --</option>';
            });
    }

    // Первоначальная загрузка задач, если boardId есть
    if (boardId) {
        loadTasks();
    }
    loadUsers();
});
