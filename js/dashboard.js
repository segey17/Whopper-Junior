document.addEventListener('DOMContentLoaded', () => {
    const createBoardButton = document.getElementById('createBoardButton');
    const createBoardForm = document.getElementById('createBoardForm');
    const messageDiv = document.getElementById('message');
    const boardsContainer = document.getElementById('boards');

    // Переключение формы
    if (createBoardButton) { // Добавим проверку на существование элемента
        createBoardButton.addEventListener('click', () => {
            if (createBoardForm) {
                createBoardForm.style.display = createBoardForm.style.display === 'none' ? 'block' : 'none';
            }
            if (messageDiv) {
                messageDiv.textContent = '';
            }
        });
    }

    // Обработка отправки формы создания доски
    if (createBoardForm) { // Добавим проверку
        createBoardForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const titleInput = document.getElementById('boardTitle');
            const descriptionInput = document.getElementById('boardDescription');
            const isPrivateCheckbox = document.getElementById('boardIsPrivate');

            const title = titleInput ? titleInput.value.trim() : '';
            const description = descriptionInput ? descriptionInput.value.trim() : '';
            const isPrivate = isPrivateCheckbox ? (isPrivateCheckbox.checked ? 1 : 0) : 0;

            if (!title) {
                if (messageDiv) {
                    messageDiv.textContent = 'Введите название доски.';
                    messageDiv.style.color = 'red';
                }
                return;
            }

            fetch('api/boards.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ title, description, is_private: isPrivate })
            })
            .then(res => res.json())
            .then(data => {
                if (messageDiv) {
                    if (data.success) {
                        messageDiv.textContent = 'Доска успешно создана!';
                        messageDiv.style.color = 'green';
                        createBoardForm.reset();
                        setTimeout(() => {
                            createBoardForm.style.display = 'none';
                            loadBoards(); // Обновляем список досок
                        }, 1000);
                    } else {
                        messageDiv.textContent = 'Ошибка: ' + (data.error || 'Неизвестная ошибка');
                        messageDiv.style.color = 'red';
                    }
                }
            })
            .catch(error => {
                console.error('Ошибка создания доски:', error);
                if (messageDiv) {
                    messageDiv.textContent = 'Произошла сетевая ошибка.';
                    messageDiv.style.color = 'red';
                }
            });
        });
    }

    // Загрузка списка досок
    function loadBoards() {
        if (!boardsContainer) return; // Проверка на boardsContainer

        fetch('api/boards.php?action=get')
            .then(res => {
                if (!res.ok) {
                    return res.json().then(err => { throw new Error(err.error || 'Ошибка загрузки досок'); });
                }
                return res.json();
            })
            .then(boards => {
                boardsContainer.innerHTML = '';
                if (!Array.isArray(boards)) { // Добавим проверку, что boards это массив
                     boardsContainer.innerHTML = `<div class="empty-state">Ошибка загрузки данных.</div>`;
                     console.error('Ожидался массив досок, получено:', boards);
                     return;
                }
                if (!boards.length) {
                    boardsContainer.innerHTML = `<div class="empty-state">У вас пока нет досок. Создайте первую!</div>`;
                } else {
                    boards.forEach(board => {
                        const card = document.createElement('div');
                        card.className = 'board-card';
                        // Отображаем (Приватная) если доска приватная
                        const privacyLabel = board.is_private == 1 ? '(Приватная)' : '';
                        card.innerHTML = `
                            <h3>${board.title} ${privacyLabel}</h3>
                            <p>${board.description || '-'}</p>
                            <div class="buttons-container">
                                <a href="edit_board.php?board_id=${board.id}" class="button-link">Редактировать</a>
                                <button onclick="deleteBoard(${board.id})">Удалить</button>
                                <button onclick="viewBoard(${board.id})">Перейти к задачам</button>
                            </div>
                        `;
                        boardsContainer.appendChild(card);
                    });
                }
            })
            .catch(error => {
                boardsContainer.innerHTML = `<div class="empty-state">Не удалось загрузить доски: ${error.message}</div>`;
                console.error('Ошибка загрузки досок:', error);
            });
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
                alert('Доска удалена.');
                loadBoards(); // Обновляем список
            } else {
                alert('Ошибка при удалении: ' + (data.error || 'Неизвестная ошибка'));
            }
        })
        .catch(error => {
            alert('Сетевая ошибка при удалении доски.');
            console.error('Ошибка удаления доски:', error);
        });
    };

    // Переход к задачам
    window.viewBoard = function(boardId) {
        window.location.href = `tasks.php?board_id=${boardId}`;
    };

    // Запуск загрузки досок при открытии страницы
    loadBoards();
});
