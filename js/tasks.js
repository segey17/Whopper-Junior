document.addEventListener('DOMContentLoaded', () => {
    const getPriorityClass = (priority) => {
        switch (priority) {
            case 'Высокий': return 'priority-high';
            case 'Средний': return 'priority-medium';
            case 'Низкий': return 'priority-low';
            default: return 'priority-medium';
        }
    };

    const getPriorityText = (priority) => {
        return priority || 'Средний';
    };

    function truncateDescription(description, maxLength = 60) {
        if (!description) return '';
        if (description.length <= maxLength) return description;
        return description.substring(0, maxLength) + '...';
    }

    const boardId = new URLSearchParams(window.location.search).get('board_id');
    const taskForm = document.getElementById('createTaskForm');
    const taskMessage = document.getElementById('taskMessage'); // Для сообщений формы создания
    const toggleTaskButton = document.getElementById('toggleTaskFormButton');
    const taskBoardColumnsContainer = document.querySelector('.task-board-columns'); // Основной контейнер для колонок задач
    const roleDisplayElement = document.getElementById('current-user-board-role-display'); // Для отображения роли
    const boardNameTitleElement = document.getElementById('boardNameTitle'); // Для имени доски

    // Новые элементы для фильтров
    const taskSearchInput = document.getElementById('taskSearchInput');
    const priorityFilter = document.getElementById('priorityFilter');
    const assigneeFilter = document.getElementById('assigneeFilter');
    const cancelCreateTaskButton = document.getElementById('cancelCreateTask');

    // Для модального окна редактирования (пока только объявление, заполнение в openEditModal)
    const editTaskModal = document.getElementById('editTaskModal');
    const editTaskForm = document.getElementById('editTaskForm');
    const closeButton = editTaskModal ? editTaskModal.querySelector('.close-button') : null;
    const editTaskProgressSlider = document.getElementById('editTaskProgressSlider');
    const editTaskProgressInput = document.getElementById('editTaskProgress'); // input type number
    const editProgressBarDisplay = document.getElementById('editProgressBar'); // div для отображения прогресса

    let allTasks = []; // Массив для хранения всех задач с сервера
    let currentUserRoleKey = ''; // Ключ роли текущего пользователя на доске
    let boardMembers = []; // Массив для хранения участников доски

    // Инициализация Pusher
    let pusher = null;
    // boardIdFromPHP передается из tasks.php
    const currentBoardId = typeof boardIdFromPHP !== 'undefined' ? boardIdFromPHP : new URLSearchParams(window.location.search).get('board_id');
    // currentUserIdFromPHP передается из tasks.php
    const currentUserId = typeof currentUserIdFromPHP !== 'undefined' ? currentUserIdFromPHP : null;

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

    // --- Новая функция для применения фильтров и рендеринга ---
    function applyFiltersAndRender() {
        if (!allTasks) return;

        let filteredTasks = [...allTasks];
        const searchTerm = taskSearchInput ? taskSearchInput.value.toLowerCase() : '';
        const selectedPriority = priorityFilter ? priorityFilter.value : 'all';
        const selectedAssignee = assigneeFilter ? assigneeFilter.value : 'all';

        // Фильтр по поисковому запросу (название и описание)
        if (searchTerm) {
            filteredTasks = filteredTasks.filter(task =>
                task.title.toLowerCase().includes(searchTerm) ||
                (task.description && task.description.toLowerCase().includes(searchTerm))
            );
        }

        // Фильтр по приоритету
        if (selectedPriority !== 'all') {
            filteredTasks = filteredTasks.filter(task => task.priority === selectedPriority);
        }

        // Фильтр по исполнителю
        if (selectedAssignee === 'me' && currentUserId) {
            filteredTasks = filteredTasks.filter(task => task.assigned_to_user_id && task.assigned_to_user_id.toString() === currentUserId.toString());
        } else if (selectedAssignee === 'unassigned') {
            filteredTasks = filteredTasks.filter(task => !task.assigned_to_user_id);
        } else if (selectedAssignee !== 'all') { // конкретный пользователь
            filteredTasks = filteredTasks.filter(task => task.assigned_to_user_id && task.assigned_to_user_id.toString() === selectedAssignee);
        }

        renderTasksInColumns(filteredTasks, currentUserRoleKey);
        renderGanttChart(filteredTasks); // Диаграмма Ганта также должна использовать отфильтрованные задачи
    }

    // --- Слушатели событий для фильтров ---
    if (taskSearchInput) {
        taskSearchInput.addEventListener('input', applyFiltersAndRender);
    }
    if (priorityFilter) {
        priorityFilter.addEventListener('change', applyFiltersAndRender);
    }
    if (assigneeFilter) {
        assigneeFilter.addEventListener('change', applyFiltersAndRender);
    }

    // --- Кнопка отмены в форме создания задачи ---
    if (cancelCreateTaskButton && taskForm) {
        cancelCreateTaskButton.addEventListener('click', () => {
            taskForm.style.display = 'none';
            taskForm.reset();
            if(taskMessage) taskMessage.textContent = '';
        });
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
                    // Не скрываем форму сразу, если пользователь захочет добавить еще
                    // taskForm.style.display = 'none'; // Убрано, кнопка "Отмена" теперь для этого
                    loadTasks(); // Обновит список и применит фильтры
                    setTimeout(() => { // Очистка сообщения через некоторое время
                        if(taskMessage) taskMessage.textContent = '';
                         if (taskForm.style.display !== 'none') { // Если форма видима, скрываем
                            // Можно оставить открытой для быстрого добавления новой или скрывать
                            // taskForm.style.display = 'none';
                         }
                    }, 2000);
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
    function renderTasksInColumns(tasks, roleKey) {
        console.log("renderTasksInColumns called with tasks:", tasks, "and roleKey:", roleKey);
        const statuses = ['В ожидании', 'В работе', 'Завершено'];
        statuses.forEach(status => {
            const list = document.querySelector(`.task-list[data-status-column="${status}"]`);
            if (list) list.innerHTML = '';
        });

        if (!tasks || !tasks.length) {
            const pendingList = document.querySelector('.task-list[data-status-column="В ожидании"]');
            const inProgressList = document.querySelector('.task-list[data-status-column="В работе"]');
            const completedList = document.querySelector('.task-list[data-status-column="Завершено"]');

            let message = "Нет задач.";
            if ((taskSearchInput && taskSearchInput.value) || (priorityFilter && priorityFilter.value !== 'all') || (assigneeFilter && assigneeFilter.value !== 'all')) {
                message = "Нет задач, соответствующих вашим фильтрам.";
            }

            if (pendingList) pendingList.innerHTML = `<div class="empty-state">${message}</div>`;
            if (inProgressList && !inProgressList.hasChildNodes()) inProgressList.innerHTML = `<div class="empty-state"></div>`; // Для средних колонок можно просто пустоту
            if (completedList && !completedList.hasChildNodes()) completedList.innerHTML = `<div class="empty-state"></div>`;

            renderGanttChart([]);
            return;
        }

        tasks.forEach(task => {
            const card = document.createElement('div');
            card.className = 'task-card-modern'; // Новый класс для карточки
            card.setAttribute('draggable', true);
            card.dataset.taskId = task.id;

            const priorityClass = getPriorityClass(task.priority);
            const priorityText = getPriorityText(task.priority);

            let assignedUserHtml = '';
            if (task.assigned_username) {
                assignedUserHtml = `<div class="task-assignee"><i class="fas fa-user-circle"></i> ${task.assigned_username}</div>`;
            } else if (task.assigned_to_user_id) {
                assignedUserHtml = `<div class="task-assignee"><i class="fas fa-user-circle"></i> ID: ${task.assigned_to_user_id}</div>`;
            }

            let progressHtml = '';
            if (task.progress !== undefined && task.progress !== null) {
                progressHtml = `
                    <div class="task-progress-bar-container">
                        <div class="task-progress-bar" style="width: ${task.progress}%;"></div>
                    </div>
                    <div class="task-progress-text">${task.progress}%</div>
                `;
            }

            let deadlineHtml = '';
            if (task.deadline) {
                const deadlineDate = new Date(task.deadline.split(' ')[0]);
                const options = { day: 'numeric', month: 'short' };
                const formattedDeadline = deadlineDate.toLocaleDateString('ru-RU', options);
                deadlineHtml = `<div class="task-deadline"><i class="fas fa-calendar-alt"></i> ${formattedDeadline}</div>`;
            }


            let actionButtons = '';
            if (roleKey === 'owner') {
                actionButtons = `
                    <button class="task-action-btn edit-button" data-id="${task.id}" title="Редактировать"><i class="fas fa-edit"></i></button>
                    <button class="task-action-btn delete-button" data-id="${task.id}" title="Удалить"><i class="fas fa-trash-alt"></i></button>
                `;
            } else if (roleKey === 'participant_developer') {
                 actionButtons = `
                    <button class="task-action-btn edit-button" data-id="${task.id}" title="Редактировать"><i class="fas fa-edit"></i></button>
                 `;
            }

            card.innerHTML = `
                <div class="task-card-modern-header">
                    <h3 class="task-title">${task.title}</h3>
                    <span class="task-priority-tag ${priorityClass}">${priorityText}</span>
                </div>
                <p class="task-description">${truncateDescription(task.description)}</p>
                <div class="task-card-modern-meta">
                    ${assignedUserHtml}
                    ${deadlineHtml}
                </div>
                <div class="task-progress-section">
                    ${progressHtml}
                </div>
                <div class="task-card-modern-footer">
                    <select class="status-select-modern" data-id="${task.id}" ${roleKey === 'participant_developer' && task.assigned_to_user_id && task.assigned_to_user_id.toString() !== (currentUserId ? currentUserId.toString() : '') ? 'disabled' : ''}>
                        <option value="В ожидании" ${task.status === 'В ожидании' ? 'selected' : ''}>В ожидании</option>
                        <option value="В работе" ${task.status === 'В работе' ? 'selected' : ''}>В работе</option>
                        <option value="Завершено" ${task.status === 'Завершено' ? 'selected' : ''}>Завершено</option>
                    </select>
                    <div class="task-actions">
                        ${actionButtons}
                    </div>
                </div>
            `;

            const listContainer = document.querySelector(`.task-list[data-status-column="${task.status}"]`);
            if (listContainer) {
                listContainer.appendChild(card);
            } else {
                // Fallback to 'В ожидании' if somehow the column is not found (should not happen)
                const pendingList = document.querySelector('.task-list[data-status-column="В ожидании"]');
                if (pendingList) pendingList.appendChild(card);
            }
        });
        initializeDragAndDrop();
    }

    // --- Модифицированная функция загрузки задач ---
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
                    allTasks = responseData.tasks; // Сохраняем все задачи
                    currentUserRoleKey = responseData.currentUserEffectiveRoleKey; // Сохраняем роль

                    if (roleDisplayElement && responseData.currentUserEffectiveRoleOnBoard) {
                        roleDisplayElement.textContent = `Ваша роль на этой доске: ${responseData.currentUserEffectiveRoleOnBoard}`;
                    }

                    applyFiltersAndRender(); // Применяем фильтры и рендерим

                    // Управляем видимостью кнопки "Добавить задачу" и формы
                    if (toggleTaskButton && taskForm) {
                        if (responseData.currentUserEffectiveRoleKey === 'owner') {
                            toggleTaskButton.style.display = ''; // Показываем кнопку
                        } else {
                            toggleTaskButton.style.display = 'none'; // Скрываем кнопку
                            taskForm.style.display = 'none';     // Также скрываем форму, если она была открыта
                        }
                    }

                } else if (responseData && responseData.error) {
                    console.error('Ошибка от API при загрузке задач:', responseData.error);
                    allTasks = [];
                    currentUserRoleKey = '';
                    applyFiltersAndRender(); // Отобразит "Нет задач" или ошибку
                    const pendingList = document.querySelector('.task-list[data-status-column="В ожидании"]');
                    if (pendingList) {
                        pendingList.innerHTML = `<div class="empty-state">Не удалось загрузить задачи: ${responseData.error}</div>`;
                    }
                    if (roleDisplayElement) roleDisplayElement.textContent = 'Не удалось определить роль.';
                } else {
                    console.error('Ожидался объект с массивом задач, получено:', responseData);
                    allTasks = [];
                    currentUserRoleKey = '';
                    applyFiltersAndRender();
                    if (roleDisplayElement) roleDisplayElement.textContent = 'Ошибка загрузки данных о роли.';
                }
            })
            .catch(error => {
                console.error('Ошибка при загрузке задач:', error);
                allTasks = [];
                currentUserRoleKey = '';
                applyFiltersAndRender(); // Отобразит "Нет задач" или ошибку
                const pendingList = document.querySelector('.task-list[data-status-column="В ожидании"]');
                if (pendingList) {
                    pendingList.innerHTML = `<div class="empty-state">Не удалось загрузить задачи: ${error.message}</div>`;
                }
                if (roleDisplayElement) roleDisplayElement.textContent = 'Ошибка сети при загрузке роли.';
            });
    }

    // Изначально скрываем кнопку и форму, пока не загрузится роль
    if (toggleTaskButton) toggleTaskButton.style.display = 'none';
    if (taskForm) taskForm.style.display = 'none';

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

            if (target.classList.contains('edit-button') || target.closest('.edit-button')) {
                const button = target.closest('.edit-button');
                const taskId = button.dataset.id;
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

            // Обновление статуса из селекта на карточке
            if (target.classList.contains('status-select') || target.classList.contains('status-select-modern')) {
                const status = target.value;
                // Предполагаем, что API handleProgress если не передан
                // let progress = status === 'В работе' ? 50 : (status === 'Завершено' ? 100 : 0); // Прогресс лучше обновлять в модалке
                fetch('api/tasks.php?action=update_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: parseInt(taskId), status: status /*, progress: progress */ })
                }).then(res => res.json()).then(data => {
                    if (data.success) {
                        // loadTasks(); // Обновление через Pusher, или если нет Pusher - раскомментировать
                    } else {
                        alert('Ошибка обновления статуса: ' + data.error);
                        loadTasks(); // Перезагрузить, если ошибка, чтобы откатить UI
                    }
                });
            }
            // Удалены обработчики для priority-select и deadline-input с карточки, т.к. они в модалке
        });
    }

    // --- Логика модального окна редактирования ---
    window.openEditModal = function(task) {
        if (!editTaskModal || !editTaskForm) return;
        document.getElementById('editTaskId').value = task.id;
        document.getElementById('editTaskTitle').value = task.title;
        document.getElementById('editTaskDescription').value = task.description || '';
        document.getElementById('editTaskPriority').value = task.priority;
        const deadlineDate = task.deadline ? task.deadline.split(' ')[0] : '';
        document.getElementById('editTaskDeadline').value = deadlineDate;

        const assignedUserInput = document.getElementById('editTaskAssignedUser');
        // loadUsers() должен был уже заполнить этот select. Здесь мы просто устанавливаем значение.
        assignedUserInput.value = task.assigned_to_user_id || '';
        assignedUserInput.dataset.originalAssignedTo = task.assigned_to_user_id || '';

        // --- Инициализация поля прогресса ---
        const progress = task.progress !== null && task.progress !== undefined ? parseInt(task.progress, 10) : 0;
        if (editTaskProgressSlider) editTaskProgressSlider.value = progress;
        if (editTaskProgressInput) editTaskProgressInput.value = progress;
        if (editProgressBarDisplay) {
            editProgressBarDisplay.style.width = progress + '%';
            editProgressBarDisplay.textContent = progress + '%';
        }

        // Управляем доступностью полей прогресса в зависимости от статуса
        const canEditProgress = task.status === 'В работе';
        if (editTaskProgressSlider) editTaskProgressSlider.disabled = !canEditProgress;
        if (editTaskProgressInput) editTaskProgressInput.disabled = !canEditProgress;

        editTaskModal.style.display = 'flex'; // Используем flex для лучшего центрирования, если CSS это поддерживает
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

            // Получаем значение прогресса
            const progress = editTaskProgressInput ? parseInt(editTaskProgressInput.value, 10) : null;

            if (!title) {
                alert('Название задачи не может быть пустым.'); return;
            }

            const taskDetailsData = {
                id: parseInt(taskId), title, description, priority,
                deadline: deadline === '' ? null : deadline,
                progress: (progress !== null && !isNaN(progress)) ? progress : undefined // Отправляем, если корректно
            };

            console.log('Updating task details. Task ID:', taskId, 'Data to send:', taskDetailsData);

            // Сначала обновляем основные детали (включая прогресс)
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

    // --- Синхронизация слайдера и инпута прогресса в модалке ---
    if (editTaskProgressSlider && editTaskProgressInput && editProgressBarDisplay) {
        editTaskProgressSlider.addEventListener('input', () => {
            const val = editTaskProgressSlider.value;
            editTaskProgressInput.value = val;
            editProgressBarDisplay.style.width = val + '%';
            editProgressBarDisplay.textContent = val + '%';
        });
        editTaskProgressInput.addEventListener('input', () => {
            let val = parseInt(editTaskProgressInput.value, 10);
            if (isNaN(val)) val = 0;
            if (val < 0) val = 0;
            if (val > 100) val = 100;
            editTaskProgressInput.value = val; // Корректируем значение в инпуте
            editTaskProgressSlider.value = val;
            editProgressBarDisplay.style.width = val + '%';
            editProgressBarDisplay.textContent = val + '%';
        });
    }

    // --- Drag and Drop Logic ---
    let draggedItem = null;
    function initializeDragAndDrop() {
        const taskCards = document.querySelectorAll('.task-card, .task-card-modern');
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

                    // Определяем прогресс в зависимости от нового статуса
                    let progressForStatusUpdate = 0;
                    // Ищем текущий прогресс задачи, чтобы не сбрасывать его, если он уже есть и не 0 или 100
                    const currentTask = allTasks.find(t => t.id.toString() === taskId);
                    let newProgress = currentTask ? currentTask.progress : 0;

                    if (newStatus === 'В ожидании') {
                        newProgress = 0;
                    } else if (newStatus === 'Завершено') {
                        newProgress = 100;
                    } else if (newStatus === 'В работе') {
                        // Если задача перетаскивается в "В работе" и прогресс 0, ставим 10 (или 50).
                        // Если уже есть прогресс (например, 25) и не 100, оставляем его.
                        if (newProgress === 0 || newProgress === 100) {
                           newProgress = 10; // или 50, если это стандарт
                        }
                    }

                    fetch('api/tasks.php?action=update_status', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: parseInt(taskId), status: newStatus, progress: newProgress })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const statusSelect = draggedItem.querySelector('.status-select, .status-select-modern');
                            if(statusSelect) statusSelect.value = newStatus;
                            const progressBar = draggedItem.querySelector('.progress-bar, .task-progress-bar');
                            const progressBarContainer = draggedItem.querySelector('.progress-bar-container, .task-progress-bar-container');

                            if (progressBar) {
                                if (newProgress > 0) {
                                    progressBar.style.width = newProgress + '%';
                                    progressBar.textContent = newProgress + '%';
                                } else {
                                   if(progressBarContainer) progressBarContainer.innerHTML = '';
                                }
                            } else if (newProgress > 0 && progressBarContainer) {
                                progressBarContainer.innerHTML = `<div class="task-progress-bar" style="width: ${newProgress}%;">${newProgress}%</div>`;
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
    function renderGanttChart(tasksToRender) { // Принимает отфильтрованные задачи
        const ganttChartContainer = document.getElementById('gantt-chart');
        if (!ganttChartContainer || typeof Gantt === 'undefined') return;
        ganttChartContainer.innerHTML = '';

        const ganttTasks = tasksToRender.filter(task => task.created_at && task.deadline)
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

    // --- Функция загрузки пользователей для селектов назначения и фильтра ---
    // (loadUsers была переименована/дополнена для ясности)
    function loadBoardMembersAndPopulateSelects() {
        const editAssignedUserSelect = document.getElementById('editTaskAssignedUser');
        // assigneeFilter (для фильтра на странице) уже объявлен выше

        if (!currentBoardId) {
            if (editAssignedUserSelect) editAssignedUserSelect.innerHTML = '<option value="">-- Не назначен --</option>';
            if (assigneeFilter) { // Очищаем и ставим дефолтные для фильтра
                assigneeFilter.innerHTML = `
                    <option value="all">Все пользователи</option>
                    <option value="me">Мои задачи</option>
                    <option value="unassigned">Неназначенные</option>
                    <option value="" disabled>-- Нет участников --</option>`;
            }
            return;
        }

        fetch(`api/boards.php?action=get_members&board_id=${currentBoardId}`)
            .then(response => {
                if (!response.ok) return response.json().then(e => { throw new Error(e.error || 'Ошибка API при загрузке участников'); });
                return response.json();
            })
            .then(membersData => {
                boardMembers = Array.isArray(membersData) ? membersData : [];

                // Заполнение селекта в модальном окне редактирования
                if (editAssignedUserSelect) {
                    editAssignedUserSelect.innerHTML = '<option value="">-- Не назначен --</option>';
                    boardMembers.forEach(user => {
                        // Возможно, здесь не нужно фильтровать владельца, т.к. задача может быть назначена и ему
                        const option = document.createElement('option');
                        option.value = user.id;
                        option.textContent = user.username + (user.role ? ` (${user.role})` : '');
                        editAssignedUserSelect.appendChild(option);
                    });
                    if (boardMembers.length === 0 && editAssignedUserSelect.options.length === 1) {
                         editAssignedUserSelect.innerHTML += '<option value="" disabled>-- Нет участников --</option>';
                    }
                }

                // Заполнение селекта фильтра исполнителей
                if (assigneeFilter) {
                    const currentAssigneeFilterValue = assigneeFilter.value; // Сохраняем текущее значение фильтра
                    assigneeFilter.innerHTML = `
                        <option value="all">Все пользователи</option>
                        <option value="me">Мои задачи</option>
                        <option value="unassigned">Неназначенные</option>`;
                    if (boardMembers.length > 0) {
                        assigneeFilter.innerHTML += '<option disabled>──────────</option>'; // Разделитель
                        boardMembers.forEach(user => {
                            const option = document.createElement('option');
                            option.value = user.id;
                            option.textContent = user.username;
                            assigneeFilter.appendChild(option);
                        });
                    } else {
                         assigneeFilter.innerHTML += '<option value="" disabled>-- Нет участников --</option>';
                    }
                    assigneeFilter.value = currentAssigneeFilterValue; // Восстанавливаем значение фильтра
                }
            })
            .catch(error => {
                console.error('Ошибка при загрузке участников доски:', error.message);
                if (editAssignedUserSelect) editAssignedUserSelect.innerHTML = '<option value="">-- Ошибка загрузки --</option>';
                if (assigneeFilter) {
                    assigneeFilter.innerHTML = `
                        <option value="all">Все пользователи</option>
                        <option value="me">Мои задачи</option>
                        <option value="unassigned">Неназначенные</option>
                        <option value="" disabled>-- Ошибка загрузки --</option>`;
                }
            });
    }

    // --- Функция загрузки деталей доски (имя) ---
    function loadBoardDetails() {
        if (!currentBoardId || !boardNameTitleElement) return;

        fetch(`api/boards.php?action=get_single&board_id=${currentBoardId}`) // Предполагаемый эндпоинт
            .then(response => {
                if (!response.ok) return response.json().then(e => { throw new Error(e.error || 'Ошибка API при загрузке деталей доски'); });
                return response.json();
            })
            .then(data => {
                if (data && data.name) {
                    boardNameTitleElement.textContent = data.name;
                    document.title = data.name + " - Задачи"; // Обновляем и title страницы
                } else if (data && data.error) {
                    console.warn("Не удалось загрузить имя доски:", data.error);
                     boardNameTitleElement.textContent = "Задачи на доске"; // Фоллбэк
                } else {
                     boardNameTitleElement.textContent = "Задачи на доске"; // Фоллбэк
                }
            })
            .catch(error => {
                console.error('Ошибка при загрузке деталей доски:', error.message);
                boardNameTitleElement.textContent = "Задачи на доске"; // Фоллбэк при ошибке сети
            });
    }

    // Первоначальная загрузка
    if (boardId) {
        loadBoardDetails(); // Загружаем имя доски
        loadBoardMembersAndPopulateSelects(); // Загружаем участников для фильтров и модалки
        loadTasks(); // Загружаем задачи (это вызовет applyFiltersAndRender)
    } else {
        // Обработка случая, если boardId отсутствует
        if (boardNameTitleElement) boardNameTitleElement.textContent = "Ошибка: ID доски не указан";
        if (taskBoardColumnsContainer) taskBoardColumnsContainer.innerHTML = '<h1>ID доски не указан.</h1>';
        // Скрываем фильтры и кнопку добавления, если нет ID доски
        const filtersDiv = document.querySelector('.task-filters');
        if(filtersDiv) filtersDiv.style.display = 'none';
        if(toggleTaskButton) toggleTaskButton.style.display = 'none';
    }
    // loadUsers(); // Старый вызов, заменен на loadBoardMembersAndPopulateSelects()
});
