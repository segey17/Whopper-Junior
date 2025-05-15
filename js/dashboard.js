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
    const totalTasksElement = document.getElementById('total-tasks');
    const completedTasksElement = document.getElementById('completed-tasks');

    // Состояние
    let allBoards = [];
    let filteredBoards = [];

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
        if (!totalBoardsElement || !totalTasksElement || !completedTasksElement) return;

        // Счетчик досок - из текущих данных
        totalBoardsElement.textContent = allBoards.length;

        // Загрузка статистики по задачам
        fetch('api/tasks.php?action=stats')
            .then(res => res.json())
            .then(stats => {
                if (stats && typeof stats === 'object') {
                    totalTasksElement.textContent = stats.total || '0';
                    completedTasksElement.textContent = stats.completed || '0';
                }
            })
            .catch(error => {
                console.error('Ошибка загрузки статистики:', error);
                totalTasksElement.textContent = allBoards.length;
                totalTasksElement.textContent = '-';
                completedTasksElement.textContent = '-';
            });
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
            .then(res => {
                if (!res.ok) {
                    return res.json().then(err => { throw new Error(err.error || 'Ошибка загрузки досок'); });
                }
                return res.json();
            })
            .then(boards => {
                if (!Array.isArray(boards)) {
                    showEmptyState('Ошибка загрузки данных');
                    console.error('Ожидался массив досок, получено:', boards);
                    return;
                }

                allBoards = boards;
                filteredBoards = [...boards];

                renderBoards();
                loadStats();
            })
            .catch(error => {
                showEmptyState(`Не удалось загрузить доски: ${error.message}`);
                console.error('Ошибка загрузки досок:', error);
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
