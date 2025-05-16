document.addEventListener('DOMContentLoaded', () => {
    // Элементы DOM
    const createBoardButton = document.getElementById('createBoardButton');
    const createBoardFormContainer = document.getElementById('createBoardFormContainer');
    const createBoardForm = document.getElementById('createBoardForm');
    const closeBoardForm = document.getElementById('closeBoardForm');
    const cancelCreateBoard = document.getElementById('cancelCreateBoard');
    const messageDiv = document.getElementById('message');
    const boardsContainer = document.getElementById('boards');
    const searchInput = document.getElementById('searchInput');
    const filterBoards = document.getElementById('filterBoards');

    // Статистика
    const totalBoardsElement = document.getElementById('total-boards');

    // Состояние
    let allBoards = [];
    let filteredBoards = [];

    // Выводим значения ключей Pusher для отладки
    console.log('PUSHER_APP_KEY from dashboard.js:', typeof PUSHER_APP_KEY !== 'undefined' ? PUSHER_APP_KEY : 'NOT DEFINED');
    console.log('PUSHER_APP_CLUSTER from dashboard.js:', typeof PUSHER_APP_CLUSTER !== 'undefined' ? PUSHER_APP_CLUSTER : 'NOT DEFINED');

    // Инициализация Pusher
    let pusher = null;
    if (
        typeof Pusher !== 'undefined' &&
        typeof PUSHER_APP_KEY !== 'undefined' && PUSHER_APP_KEY &&
        typeof PUSHER_APP_CLUSTER !== 'undefined' && PUSHER_APP_CLUSTER
    ) {
        pusher = new Pusher(PUSHER_APP_KEY, {
            cluster: PUSHER_APP_CLUSTER
        });

        // Подписка на канал досок
        const boardChannel = pusher.subscribe('board-events');

        boardChannel.bind('board_created', function(data) {
            console.log('Pusher: board_created', data);
            // showToast(`Новая доска создана: ${data.title}`, 'success'); // отключаем уведомление для всех
            loadBoards();
        });

        boardChannel.bind('board_updated', function(data) {
            console.log('Pusher: board_updated', data);
            showToast(`Доска обновлена: ${data.title}`, 'info');
            loadBoards(); // Перезагружаем доски
        });

        boardChannel.bind('board_deleted', function(data) {
            console.log('Pusher: board_deleted', data);
            showToast(`Доска удалена (ID: ${data.id})`, 'info');
            loadBoards(); // Перезагружаем доски
        });

        boardChannel.bind('member_added', function(data) {
            console.log('Pusher: member_added', data);
            showToast(`Пользователь ${data.username} добавлен на доску ID: ${data.board_id}`, 'info');
            loadBoards();
        });

        boardChannel.bind('member_removed', function(data) {
            console.log('Pusher: member_removed', data);
            showToast(`Пользователь (ID: ${data.user_id}) удален с доски ID: ${data.board_id}`, 'info');
            loadBoards();
        });

        // Подписка на канал задач (если на этой странице есть отображение задач или их статистики)
        // Если задачи отображаются на другой странице (tasks.php), то эта подписка должна быть там.
        // Пока оставим здесь для общей статистики.
        const taskChannel = pusher.subscribe('task-events');

        taskChannel.bind('task_created', function(data) {
            console.log('Pusher: task_created', data);
            showToast(`Новая задача создана: ${data.title}`, 'success');
            // Если бы задачи были на этой странице, мы бы обновили и их список
        });

        taskChannel.bind('task_status_updated', function(data) {
            console.log('Pusher: task_status_updated', data);
            showToast(`Статус задачи (ID: ${data.id}) обновлен`, 'info');
            //
        });

        taskChannel.bind('task_priority_updated', function(data) {
            console.log('Pusher: task_priority_updated', data);
            showToast(`Приоритет задачи (ID: ${data.id}) обновлен`, 'info');
            //
        });

        taskChannel.bind('task_deadline_updated', function(data) {
            console.log('Pusher: task_deadline_updated', data);
            showToast(`Дедлайн задачи (ID: ${data.id}) обновлен`, 'info');
            //
        });

        taskChannel.bind('task_deleted', function(data) {
            console.log('Pusher: task_deleted', data);
            showToast(`Задача (ID: ${data.id}) удалена`, 'info');
            //
        });

        pusher.connection.bind('connected', () => {
            console.log('Pusher: Successfully connected!');
        });

        pusher.connection.bind('error', (err) => {
            console.error('Pusher: Connection error:', err);
            if (err.error && err.error.data && err.error.data.code === 4004) {
                console.error('Pusher: App key not found or similar issue. Please check your Pusher App Key and Cluster in dashboard.php.');
                showToast('Ошибка подключения к системе обновлений: проверьте ключи Pusher.', 'error');
            } else {
                showToast('Ошибка подключения к системе обновлений.', 'error');
            }
        });
    } else {
        console.warn('Pusher: SDK not loaded or PUSHER_APP_KEY/PUSHER_APP_CLUSTER are not defined or are empty. Realtime updates disabled.');
        showToast('Система обновлений в реальном времени не настроена (ключи не указаны или пусты).', 'warning');
    }

    // Открытие/закрытие модального окна создания доски
    function toggleCreateBoardModal(show = true) {
        createBoardFormContainer.style.display = show ? 'flex' : 'none';
        if (show) {
            document.body.style.overflow = 'hidden'; // Блокируем скролл страницы
            document.getElementById('boardTitle').focus();
        } else {
            document.body.style.overflow = '';
            createBoardForm.reset();
            if (messageDiv) messageDiv.textContent = '';
            if (messageDiv) messageDiv.className = 'message-box';
            if (messageDiv) messageDiv.style.display = 'none';
        }
    }

    // Обработчики событий для модального окна
    if (createBoardButton) {
        createBoardButton.addEventListener('click', () => toggleCreateBoardModal(true));
    }

    if (closeBoardForm) {
        closeBoardForm.addEventListener('click', () => toggleCreateBoardModal(false));
    }

    if (cancelCreateBoard) {
        cancelCreateBoard.addEventListener('click', () => toggleCreateBoardModal(false));
    }

    // Закрыть модальное окно при клике вне его содержимого
    if (createBoardFormContainer) {
        createBoardFormContainer.addEventListener('click', (e) => {
            if (e.target === createBoardFormContainer) {
                toggleCreateBoardModal(false);
            }
        });
    }

    // Отображение сообщения в форме создания доски
    function showMessage(text, type = 'error') {
        if (!messageDiv) return;

        messageDiv.textContent = text;
        messageDiv.className = 'message-box';
        messageDiv.classList.add(type === 'error' ? 'error-message' : 'success-message');
        messageDiv.style.display = 'block';
    }

    // Обработка отправки формы создания доски
    if (createBoardForm) {
        createBoardForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const titleInput = document.getElementById('boardTitle');
            const descriptionInput = document.getElementById('boardDescription');

            const title = titleInput ? titleInput.value.trim() : '';
            const description = descriptionInput ? descriptionInput.value.trim() : '';
            // Все доски приватные по умолчанию
            const isPrivate = 1;

            if (!title) {
                showMessage('Введите название доски', 'error');
                return;
            }

            fetch('api/boards.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ title, description, is_private: isPrivate })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMessage('Доска успешно создана!', 'success');
                    setTimeout(() => {
                        toggleCreateBoardModal(false);
                        loadBoards();
                    }, 1500);
                } else {
                    showMessage('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
                }
            })
            .catch(error => {
                console.error('Ошибка создания доски:', error);
                showMessage('Произошла сетевая ошибка', 'error');
            });
        });
    }

    // Поиск и фильтрация досок
    function filterAndSearchBoards() {
        if (!allBoards.length) return;

        const searchTerm = searchInput?.value.toLowerCase() || '';
        const filterValue = filterBoards?.value || 'all';

        filteredBoards = allBoards.filter(board => {
            // Поиск по заголовку и описанию
            const matchesSearch =
                board.title.toLowerCase().includes(searchTerm) ||
                (board.description && board.description.toLowerCase().includes(searchTerm));

            // Фильтрация по типу
            let matchesFilter = true;
            if (filterValue === 'my') {
                matchesFilter = board.is_owner;
            } else if (filterValue === 'shared') {
                matchesFilter = !board.is_owner;
            }

            return matchesSearch && matchesFilter;
        });

        renderBoards();
    }

    // Подключаем события поиска и фильтрации
    if (searchInput) {
        searchInput.addEventListener('input', filterAndSearchBoards);
    }

    if (filterBoards) {
        filterBoards.addEventListener('change', filterAndSearchBoards);
    }

    // Загрузка статистики
    function loadStats() {
        if (!totalBoardsElement) return;

        // Счетчик досок - из текущих данных
        totalBoardsElement.textContent = allBoards.length;
    }

    // Отрисовка досок
    function renderBoards() {
        if (!boardsContainer) return;

        boardsContainer.innerHTML = '';

        if (!filteredBoards.length) {
            const emptyState = document.createElement('div');
            emptyState.className = 'empty-state';

            if (!allBoards.length) {
                emptyState.innerHTML = `
                    <div class="empty-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                    <h3>У вас пока нет досок</h3>
                    <p>Создайте свою первую доску, чтобы начать работу</p>
                    <button class="primary-button" onclick="document.getElementById('createBoardButton').click()">
                        <i class="fa-solid fa-plus"></i> Создать доску
                    </button>
                `;
            } else {
                emptyState.innerHTML = `
                    <div class="empty-icon"><i class="fa-solid fa-search"></i></div>
                    <h3>Ничего не найдено</h3>
                    <p>Попробуйте изменить параметры поиска или фильтрации</p>
                `;
            }

            boardsContainer.appendChild(emptyState);
        } else {
            filteredBoards.forEach(board => {
                const card = document.createElement('div');
                card.className = 'board-card';

                card.innerHTML = `
                    <h3>
                        <i class="fa-solid fa-clipboard-list"></i>
                        ${board.title}
                    </h3>
                    <p>${board.description || 'Описание отсутствует'}</p>
                    <div class="buttons-container">
                        ${board.is_owner ? `
                            <button class="delete-button" onclick="deleteBoard(${board.id})">
                                <i class="fa-solid fa-trash"></i> Удалить
                            </button>
                            <a href="edit_board.php?board_id=${board.id}" class="button-link">
                                <i class="fa-solid fa-pen"></i> Редактировать
                            </a>
                        ` : ''}
                        <button class="tasks-button" onclick="viewBoard(${board.id})">
                            <i class="fa-solid fa-tasks"></i> Задачи
                        </button>
                    </div>
                `;

                boardsContainer.appendChild(card);
            });
        }
    }

    // Загрузка списка досок
    function loadBoards() {
        if (!boardsContainer) return;

        fetch('api/boards.php?action=get')
            .then(response => {
                // Сначала получаем ответ как текст
                return response.text().then(text => {
                    console.log("RAW RESPONSE FROM SERVER:", text); // Выводим сырой ответ
                    // Теперь пытаемся распарсить текст как JSON
                    try {
                        const data = JSON.parse(text);
                        // Проверяем HTTP статус уже после успешного парсинга JSON
                        if (!response.ok) {
                            // Если !response.ok, но JSON распарсился, используем сообщение об ошибке из JSON, если есть
                            throw new Error(data.error || `Server error with status: ${response.status} and message: ${text}`);
                        }
                        return data; // Возвращаем распарсенные данные
                    } catch (e) {
                        // Если парсинг JSON не удался, значит, это не JSON (вероятно, HTML с ошибкой PHP)
                        // В этом случае response.ok МОЖЕТ быть true, если сервер отдал HTML с кодом 200 OK
                        // Поэтому дополнительно проверяем, не HTML ли это
                        if (text.trim().startsWith("<")) {
                            throw new Error(`Server returned HTML instead of JSON. Response: ${text.substring(0, 300)}...`);
                        } else {
                            // Если это не HTML и парсинг не удался, это может быть другая ошибка
                            throw new Error(`Failed to parse JSON. Original error: ${e.message}. Server response: ${text.substring(0, 200)}...`);
                        }
                    }
                });
            })
            .then(boards => {
                if (!Array.isArray(boards)) {
                    console.error('Expected an array of boards, but received:', boards);
                    // Если boards это объект ошибки из предыдущего catch, его message уже будет информативным
                    const errorMessage = boards instanceof Error ? boards.message : 'Ошибка загрузки данных: неверный формат ответа от сервера.';
                    showEmptyState(errorMessage);
                    allBoards = [];
                    filteredBoards = [];
                    loadStats();
                    return;
                }

                allBoards = boards;
                filteredBoards = [...boards];

                renderBoards();
                loadStats();
            })
            .catch(error => {
                // Этот catch теперь будет ловить ошибки как от fetch, так и от JSON.parse, и от проверок
                showEmptyState(`Не удалось загрузить доски: ${error.message}`);
                console.error('Общая ошибка в loadBoards:', error);
            });
    }

    // Отображение пустого состояния с сообщением об ошибке
    function showEmptyState(message) {
        if (!boardsContainer) return;

        boardsContainer.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-exclamation-triangle"></i></div>
                <h3>Упс! Что-то пошло не так</h3>
                <p>${message}</p>
                <button class="primary-button" onclick="location.reload()">
                    <i class="fa-solid fa-redo"></i> Попробовать снова
                </button>
            </div>
        `;
    }

    // Удаление доски
    window.deleteBoard = function(boardId) {
        if (!confirm("Вы уверены, что хотите удалить эту доску?")) return;

        fetch(`api/boards.php?action=delete&board_id=${boardId}`, {
            method: 'DELETE'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Вместо alert используем временное всплывающее уведомление
                showToast('Доска успешно удалена', 'success');
                loadBoards();
            } else {
                showToast('Ошибка при удалении: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            showToast('Сетевая ошибка при удалении доски', 'error');
            console.error('Ошибка удаления доски:', error);
        });
    };

    // Временное всплывающее уведомление
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            </div>
            <div class="toast-message">${message}</div>
        `;

        document.body.appendChild(toast);

        // Анимация появления
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        // Автоматическое скрытие через 3 секунды
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    }

    // Переход к задачам
    window.viewBoard = function(boardId) {
        window.location.href = `tasks.php?board_id=${boardId}`;
    };

    // Инициализация
    loadBoards();
});
