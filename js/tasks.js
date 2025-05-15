document.addEventListener('DOMContentLoaded', () => {
    const boardId = new URLSearchParams(window.location.search).get('board_id');
    const taskForm = document.getElementById('createTaskForm');
    const taskMessage = document.getElementById('taskMessage'); // Для сообщений формы создания
    const toggleTaskButton = document.getElementById('toggleTaskFormButton');
    const taskBoardColumnsContainer = document.querySelector('.task-board-columns'); // Основной контейнер для колонок задач
    const roleDisplayElement = document.getElementById('current-user-board-role-display'); // Для отображения роли

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

            fetch('api/tasks.php?action=create', { // Проверить путь к API, если 404
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    board_id: parseInt(boardId), title, description, status, priority,
                    deadline: deadline === '' ? null : deadline
                    // assigned_to_user_id по умолчанию null или можно добавить поле в форму
                })
            })
            .then(res => {
                if (!res.ok) { // Обработка HTTP ошибок типа 404
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
    function renderTasksInColumns(tasks, currentUserRoleKey) { // Добавим currentUserRoleKey для управления UI
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
            } else if (task.assigned_to_user_id) { // Используем правильное поле
                assignedUserInfo = `<div class="assigned-user">Назначено ID: ${task.assigned_to_user_id}</div>`;
            }

            let progressIndicator = '';
            if (task.progress !== undefined && task.progress !== null) {
                progressIndicator = `<div class="progress-bar-container">
                                       <div class="progress-bar" style="width: ${task.progress}%;">${task.progress}%</div>
                                   </div>`;
            }

            // Логика отображения кнопок в зависимости от роли
            // currentUserRoleKey может быть 'owner', 'participant_developer', 'viewer'
            let actionButtons = '';
            if (currentUserRoleKey === 'owner') { // Владелец может все
                actionButtons = `
                    <button class="delete-button" data-id="${task.id}">Удалить</button>
                    <button class="edit-button" data-id="${task.id}">Редактировать</button>
                `;
            } else if (currentUserRoleKey === 'participant_developer') {
                 // Участник-разработчик может редактировать (если это его задача или разрешено), но не удалять чужие.
                 // Пока оставим возможность редактирования (для изменения статуса/приоритета)
                 // и удаления (если это его задача - это проверяется на сервере)
                actionButtons = `
                    <button class="delete-button" data-id="${task.id}">Удалить</button>
                    <button class="edit-button" data-id="${task.id}">Редактировать</button>
                `;
                 // Более строгая логика:
                 // if (task.assigned_to_user_id == YOUR_CURRENT_USER_ID_FROM_SESSION_OR_GLOBAL_VAR) {
                 //    actionButtons += `<button class="edit-button" data-id="${task.id}">Редактировать</button>`;
                 // }
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
    // Оборачиваем в window.loadTasks, чтобы быть уверенным, что это та функция, которая вызывается
    window.loadTasks = function() {
        if (!boardId) {
            if (taskBoardColumnsContainer) { // Используем taskBoardColumnsContainer
                 taskBoardColumnsContainer.innerHTML = '<h1>ID доски не указан. Невозможно загрузить задачи.</h1>';
            }
            const ganttContainer = document.getElementById('gantt-chart-container');
            if (ganttContainer) ganttContainer.innerHTML = '';
            if (roleDisplayElement) roleDisplayElement.textContent = '';
            return;
        }

        fetch(`api/tasks.php?action=get&board_id=${boardId}`) // Проверить путь к API, если 404
            .then(res => {
                if (!res.ok) {
                    return res.json().then(err => { throw new Error(err.error || `HTTP error! status: ${res.status}`); })
                                   .catch(() => { throw new Error(`HTTP error! status: ${res.status}`); });
                }
                return res.json();
            })
            .then(responseData => { // Ожидаем объект { tasks: [], currentUserEffectiveRoleOnBoard: "...", currentUserEffectiveRoleKey: "..." }
                if (typeof responseData === 'object' && responseData !== null && responseData.tasks && Array.isArray(responseData.tasks)) {
                    if (roleDisplayElement && responseData.currentUserEffectiveRoleOnBoard) {
                        roleDisplayElement.textContent = `Ваша роль на этой доске: ${responseData.currentUserEffectiveRoleOnBoard}`;
                    }
                    renderTasksInColumns(responseData.tasks, responseData.currentUserEffectiveRoleKey);
                } else if (responseData && responseData.error) {
                    console.error('Ошибка от API при загрузке задач:', responseData.error);
                    renderTasksInColumns([], null); // Передаем пустой массив и null для роли
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
                    fetch('api/tasks.php?action=delete', { // Проверить путь к API, если 404
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

            if (target.classList.contains('edit-button')) {
                const taskId = target.dataset.id;
                fetch(`api/tasks.php?action=get_details&task_id=${taskId}`) // Проверить путь к API
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
                fetch('api/tasks.php?action=update_status', { // Проверить путь к API
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: parseInt(taskId), status, progress })
                }).then(res => res.json()).then(data => {
                    if (data.success) loadTasks(); else alert('Ошибка обновления статуса: ' + data.error);
                });
            }

            if (target.classList.contains('priority-select')) {
                const priority = target.value;
                fetch('api/tasks.php?action=update_details', { // Проверить путь к API (update_priority нет, используем update_details)
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: parseInt(taskId), priority })
                }).then(res => res.json()).then(data => {
                    if (data.success) loadTasks(); else alert('Ошибка обновления приоритета: ' + data.error);
                });
            }

            if (target.classList.contains('deadline-input')) {
                const deadline = target.value;
                fetch('api/tasks.php?action=update_details', { // Проверить путь к API
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: parseInt(taskId), deadline: deadline === '' ? null : deadline })
                }).then(res => res.json()).then(data => {
                    if (data.success) loadTasks(); else alert('Ошибка обновления дедлайна: ' + data.error);
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
        assignedUserInput.value = task.assigned_to_user_id || ''; // Используем assigned_to_user_id
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
            const newAssignedToIdString = assignedUserSelect.value; // Value from select
            const originalAssignedToIdString = assignedUserSelect.dataset.originalAssignedTo || '';

            if (!title) {
                alert('Название задачи не может быть пустым.'); return;
            }

            const taskDetailsData = {
                id: parseInt(taskId), title, description, priority,
                deadline: deadline === '' ? null : deadline,
            };

            fetch('api/tasks.php?action=update_details', { // Проверить путь
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
                        // No NaN check needed here if values are controlled, but good for safety if needed elsewhere
                    }
                    const originalAssignedToId = originalAssignedToIdString === '' ? null : parseInt(originalAssignedToIdString, 10);

                    if (newAssignedToId !== originalAssignedToId) {
                        fetch('api/tasks.php?action=assign', { // Проверить путь
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: parseInt(taskId), assignee_user_id: newAssignedToId })
                        })
                        .then(assignRes => assignRes.json())
                        .then(assignData => {
                            if (!assignData.success) {
                                alert('Ошибка обновления назначения: ' + (assignData.error || 'Неизвестная ошибка'));
                            }
                            // Перезагрузка и закрытие модалки в любом случае после попытки назначения
                            closeEditModal();
                            loadTasks();
                        });
                    } else {
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

                    fetch('api/tasks.php?action=update_status', { // Проверить путь
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
                                } else { // progress is 0
                                   if(progressBarContainer) progressBarContainer.innerHTML = ''; // Remove progress bar
                                }
                            } else if (progress > 0 && progressBarContainer) { // Progress bar didn't exist but should
                                progressBarContainer.innerHTML = `<div class="progress-bar" style="width: ${progress}%;">${progress}%</div>`;
                            }
                            // Если нет progressBarContainer, значит его и не было, и прогресс 0 - ничего не делаем

                        } else {
                            alert('Ошибка обновления статуса: ' + data.error); loadTasks();
                        }
                    })
                    .catch(error => { console.error('Сетевая ошибка (drag-n-drop):', error); loadTasks(); });
                } else if (draggedItem) {
                     draggedItem.style.opacity = '1';
                }
                 draggedItem = null;
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

        fetch('api/users.php?action=get')
            .then(response => {
                if (!response.ok) {
                    // Попытка прочитать тело ошибки как JSON, если не удается - как текст
                    return response.json().catch(() => response.text()).then(errorBody => {
                        throw new Error(`Ошибка сети: ${response.status}. ${typeof errorBody === 'string' ? errorBody : (errorBody.error || 'Неизвестная ошибка API')}`);
                    });
                }
                return response.json();
            })
            .then(users => {
                // Очищаем предыдущие опции, кроме первой (если она "-- Не назначен --")
                while (assignedUserSelect.options.length > 1) {
                    assignedUserSelect.remove(1);
                }
                // Если первая опция не "-- Не назначен --" или ее нет, добавляем
                if (assignedUserSelect.options.length === 0 || assignedUserSelect.options[0].value !== "") {
                     // Удаляем все и добавляем "-- Не назначен --" как первую
                    assignedUserSelect.innerHTML = '<option value="">-- Не назначен --</option>';
                }

                if (Array.isArray(users)) {
                    users.forEach(user => {
                        const option = document.createElement('option');
                        option.value = user.id;
                        option.textContent = `${user.username} (Роль: ${user.role})`;
                        assignedUserSelect.appendChild(option);
                    });
                } else if (users && users.error) {
                    console.warn('Не удалось загрузить пользователей для назначения (возможно, нет прав):', users.error);
                    // Можно здесь добавить disabled для select или информационное сообщение
                    // assignedUserSelect.disabled = true;
                } else {
                    console.warn('Ответ API пользователей не является массивом:', users);
                }
            })
            .catch(error => {
                console.error('Ошибка при загрузке пользователей:', error.message);
                // assignedUserSelect.disabled = true; // Можно заблокировать селект при ошибке
                // Можно также отобразить сообщение пользователю рядом с селектом
            });
    }

    // Первоначальная загрузка задач, если boardId есть
    if (boardId) {
        loadTasks();
    }
    loadUsers();
});
