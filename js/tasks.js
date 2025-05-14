document.addEventListener('DOMContentLoaded', () => {
    const tasksContainer = document.getElementById('tasks');
    const boardId = new URLSearchParams(window.location.search).get('board_id');
    const messageContainer = document.querySelector('.message');

    function showMessage(type, text) {
        const successEl = messageContainer.querySelector('.success');
        const errorEl = messageContainer.querySelector('.error');
        successEl.textContent = '';
        errorEl.textContent = '';
        if (type === 'success') {
            successEl.textContent = text;
        } else if (type === 'error') {
            errorEl.textContent = text;
        }
        messageContainer.style.display = 'block';
        setTimeout(() => messageContainer.style.display = 'none', 3000);
    }

    if (!boardId) {
        showMessage('error', 'ID доски не указан');
        setTimeout(() => window.location.href = "dashboard.php", 3000);
        return;
    }

    let isOwner = false;

    // Получаем данные пользователя через API
    fetch('api/auth.php?action=get_user')
        .then(res => res.json())
        .then(user => {
            // Загрузка информации о доске и проверка прав
            fetch(`api/boards.php?action=get`)
                .then(res => res.json())
                .then(boards => {
                    const board = boards.find(b => b.id == boardId);
                    if (!board) {
                        showMessage('error', 'Доска не найдена.');
                        return;
                    }

                    isOwner = board.owner_id === user.id;

                    // Если не владелец — скрыть элементы управления
                    if (!isOwner) {
                        const addMemberDiv = document.querySelector('.add-member');
                        if (addMemberDiv) {
                            addMemberDiv.style.display = 'none';
                        }
                        document.querySelectorAll('[data-owner-only]').forEach(el => el.remove());
                    }
                });

            loadUsers();
            loadTasks();
        })
        .catch(error => console.error('Ошибка получения данных пользователя:', error));

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

    function loadTasks() {
        fetch(`api/tasks.php?action=get&board_id=${boardId}`)
            .then(res => res.json())
            .then(tasks => {
                tasksContainer.innerHTML = '';
                if (!tasks.length) {
                    tasksContainer.innerHTML = '<div class="empty-state">Нет задач. Создайте первую!</div>';
                } else {
                    tasks.forEach(task => {
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
                            <input type="date" class="deadline-input" data-id="${task.id}" value="${(task.deadline && task.deadline !== '0000-00-00 00:00:00') ? task.deadline.split(' ')[0] : ''}">
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

                // --- Начало кода для Диаграммы Ганта ---
                const ganttTasks = tasks.filter(task => task.created_at && task.deadline).map(task => {
                    const startDate = new Date(task.created_at.split(' ')[0]); // Убираем время, если есть
                    const endDate = new Date(task.deadline.split(' ')[0]);   // Убираем время, если есть

                    // Проверка на валидность дат
                    if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
                        console.warn(`Некорректные даты для задачи ID ${task.id}: начало ${task.created_at}, конец ${task.deadline}`);
                        return null; // Пропускаем эту задачу
                    }
                    // Убедимся, что дата начала не позже даты окончания
                    if (startDate > endDate) {
                         console.warn(`Дата начала (${startDate}) позже даты окончания (${endDate}) для задачи ID ${task.id}. Задача будет пропущена.`);
                         return null;
                    }

                    const formatDate = (dateObj) => {
                        const year = dateObj.getFullYear();
                        const month = (dateObj.getMonth() + 1).toString().padStart(2, '0');
                        const day = dateObj.getDate().toString().padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    };

                    let progress = 0;
                    if (task.status === 'Завершено') {
                        progress = 100;
                    } else if (task.status === 'В работе') {
                        progress = task.progress > 0 ? task.progress : 50; // Используем существующий прогресс или 50%
                    } else {
                        progress = task.progress > 0 ? task.progress : 0; // Используем существующий прогресс или 0%
                    }

                    return {
                        id: String(task.id), // ID должен быть строкой
                        name: task.title,
                        start: formatDate(startDate),
                        end: formatDate(endDate),
                        progress: progress,
                        // dependencies: 'task_id_previous_task' // Если бы были зависимости
                    };
                }).filter(task => task !== null); // Удаляем задачи, которые были пропущены (null)

                const ganttChartContainer = document.getElementById('gantt-chart');
                if (ganttChartContainer && typeof Gantt !== 'undefined') {
                    // Очищаем предыдущую диаграмму, если она была
                    ganttChartContainer.innerHTML = '';
                    if (ganttTasks.length > 0) {
                         try {
                            new Gantt("#gantt-chart", ganttTasks, {
                                header_height: 50,
                                column_width: 30,
                                step: 24,
                                view_modes: ['Quarter Day', 'Half Day', 'Day', 'Week', 'Month'],
                                bar_height: 20,
                                bar_corner_radius: 3,
                                arrow_curve: 5,
                                padding: 18,
                                view_mode: 'Week',
                                date_format: 'YYYY-MM-DD',
                                custom_popup_html: function(task) {
                                  return `
                                    <div class="gantt-popup-content">
                                      <strong>${task.name}</strong><br>
                                      Начало: ${task.start}<br>
                                      Конец: ${task.end}<br>
                                      Прогресс: ${task.progress}%
                                    </div>
                                  `;
                                }
                                // Для просмотра, обработчики on_click, on_date_change, on_progress_change не нужны для изменения данных
                                // on_click: function (task) {
                                //   console.log(task);
                                // }
                            });
                        } catch (e) {
                            console.error("Ошибка при создании диаграммы Ганта:", e);
                            ganttChartContainer.innerHTML = "<p>Не удалось построить диаграмму Ганта.</p>";
                        }
                    } else {
                        ganttChartContainer.innerHTML = "<p>Нет задач с корректными датами для отображения на диаграмме Ганта.</p>";
                    }
                } else {
                    if (!ganttChartContainer) console.error("Контейнер #gantt-chart не найден.");
                    if (typeof Gantt === 'undefined') console.error("Библиотека Frappe Gantt (Gantt) не загружена.");
                }
                // --- Конец кода для Диаграммы Ганта ---
            });
    }

    window.addMember = function () {
        const userId = document.getElementById('member-select').value;
        fetch('api/boards.php?action=add_member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ board_id: boardId, user_id: userId })
        }).then(res => res.json())
          .then(data => {
              if (data.success) {
                  showMessage('success', 'Участник успешно добавлен!');
              } else {
                  showMessage('error', 'Ошибка добавления: ' + data.error);
              }
          });
    };

    window.createTask = function () {
        const title = prompt("Название задачи:");
        if (!title) return;
        const description = prompt("Описание задачи:") || '';
        const priority = prompt("Приоритет (низкий/средний/высокий):")?.toLowerCase() || 'низкий';
        const deadline = prompt("Дедлайн (YYYY-MM-DD):") || null;
        fetch('api/tasks.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ board_id: boardId, title, description, priority, deadline })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showMessage('success', 'Задача создана!');
                loadTasks();
            } else {
                showMessage('error', 'Ошибка создания: ' + data.error);
            }
        });
    };

    loadUsers();
    loadTasks();
});
