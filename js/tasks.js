document.addEventListener('DOMContentLoaded', () => {
    const tasksContainer = document.getElementById('tasks');
    const boardId = new URLSearchParams(window.location.search).get('board_id');

    function loadTasks() {
        fetch(`api/tasks.php?action=get&board_id=${boardId}`)
            .then(res => res.json())
            .then(tasks => {
                tasksContainer.innerHTML = '';
                if (tasks.length === 0) {
                    tasksContainer.innerHTML = '<div class="empty-state">У вас пока нет задач. Создайте первую!</div>';
                } else {
                    tasks.forEach(task => {
                        const card = document.createElement('div');
                        card.className = 'task-card';
                        card.innerHTML = `
                            <div class="title">${task.title}</div>
                            <div class="description">${task.description}</div>
                            <select class="status-select" data-id="${task.id}" data-progress="${task.progress}">
                                <option value="В ожидании" ${task.status === 'В ожидании' ? 'selected' : ''}>В ожидании</option>
                                <option value="В работе" ${task.status === 'В работе' ? 'selected' : ''}>В работе</option>
                                <option value="Завершено" ${task.status === 'Завершено' ? 'selected' : ''}>Завершено</option>
                            </select>
                            <select class="priority-select" data-id="${task.id}">
                                <option value="низкий" ${task.priority === 'низкий' ? 'selected' : ''}>Низкий</option>
                                <option value="средний" ${task.priority === 'средний' ? 'selected' : ''}>Средний</option>
                                <option value="высокий" ${task.priority === 'высокий' ? 'selected' : ''}>Высокий</option>
                            </select>
                            <input type="date" class="deadline-input" data-id="${task.id}" value="${task.deadline || ''}">
                            <button class="delete-button" onclick="deleteTask(${task.id})">Удалить задачу</button>
                        `;
                        tasksContainer.appendChild(card);
                    });

                    // Обработчики событий для обновления статуса
                    document.querySelectorAll('.status-select').forEach(select => {
                        select.addEventListener('change', e => {
                            const taskId = e.target.dataset.id;
                            const progress = e.target.value === 'В работе' ? 50 :
                                             e.target.value === 'Завершено' ? 100 : 0;

                            fetch('api/tasks.php?action=update_status', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({id: taskId, status: e.target.value, progress})
                            }).then(() => location.reload());
                        });
                    });

                    // Обработчики событий для обновления приоритета
                    document.querySelectorAll('.priority-select').forEach(select => {
                        select.addEventListener('change', e => {
                            const taskId = e.target.dataset.id;
                            const priority = e.target.value;

                            fetch('api/tasks.php?action=update_priority', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({id: taskId, priority})
                            }).then(() => location.reload());
                        });
                    });

                    // Обработчики событий для обновления дедлайна
                    document.querySelectorAll('.deadline-input').forEach(input => {
                        input.addEventListener('change', e => {
                            const taskId = e.target.dataset.id;
                            const deadline = e.target.value;

                            fetch('api/tasks.php?action=update_deadline', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({id: taskId, deadline})
                            }).then(() => location.reload());
                        });
                    });

                    // Обработчики событий для удаления задачи
                    document.querySelectorAll('.delete-button').forEach(button => {
                        button.addEventListener('click', e => {
                            const taskId = e.target.dataset.id;
                            if (confirm("Вы уверены, что хотите удалить эту задачу?")) {
                                fetch(`api/tasks.php?action=delete&id=${taskId}`, {
                                    method: 'DELETE'
                                }).then(() => {
                                    alert("Задача удалена успешно!");
                                    loadTasks();
                                });
                            }
                        });
                    });
                }
            })
            .catch(error => {
                console.error('Ошибка при загрузке задач:', error);
                tasksContainer.innerHTML = '<div class="error-message">Ошибка при загрузке задач. Попробуйте еще раз позже.</div>';
            });
    }

    function createTask() {
        const title = prompt("Введите название задачи:");
        if (!title) return;

        const description = prompt("Введите описание задачи (опционально):");
        const priority = prompt("Введите приоритет задачи (низкий, средний, высокий):");
        const deadline = prompt("Введите дедлайн задачи (опционально, формат YYYY-MM-DD):");

        fetch('api/tasks.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                board_id: boardId,
                title: title,
                description: description,
                status: 'В ожидании',
                priority: priority,
                deadline: deadline
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Задача создана успешно!");
                loadTasks();
            } else {
                alert('Ошибка при создании задачи: ' + (data.message || 'Неизвестная ошибка.'));
            }
        })
        .catch(error => {
            console.error('Ошибка при создании задачи:', error);
            alert('Ошибка сети при создании задачи.');
        });
    }

    // Объявляем функцию createTask в глобальной области, иначе она не будет доступна из кнопки
    window.createTask = createTask;

    loadTasks();
});