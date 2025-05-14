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
                            <input type="date" class="deadline-input" data-id="${task.id}" value="${task.deadline ? task.deadline.split(' ')[0] : ''}">
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
