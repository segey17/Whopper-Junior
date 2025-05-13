document.addEventListener('DOMContentLoaded', () => {
    const boardsContainer = document.getElementById('boards');
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

    function escapeHtml(unsafe) {
        return unsafe ? String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "<")
            .replace(/>/g, ">")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;") : '';
    }

    function loadBoards() {
        fetch('api/boards.php?action=get')
            .then(res => {
                if (!res.ok) throw new Error('Сетевая ошибка');
                return res.json();
            })
            .then(boards => {
                boardsContainer.innerHTML = '';
                if (!boards || boards.length === 0) {
                    boardsContainer.innerHTML = `
                        <div class="empty-state">
                            У вас пока нет досок. Создайте первую!
                        </div>
                    `;
                    return;
                }

                boards.forEach(board => {
                    const safeTitle = escapeHtml(board.title || 'Без названия');
                    const safeDescription = escapeHtml(board.description || '');
                    const card = document.createElement('div');
                    card.className = 'board-card';
                    card.innerHTML = `
                        <h3>${safeTitle}</h3>
                        <p>${safeDescription}</p>
                        <div class="buttons-container">
                            <button 
                                data-id="${board.id}" 
                                data-title="${JSON.stringify(safeTitle)}" 
                                data-description="${JSON.stringify(safeDescription)}"
                                onclick="window.editBoard(this)"
                            >
                                Редактировать
                            </button>
                            <button onclick="window.deleteBoard(${board.id})">Удалить</button>
                            <button onclick="window.viewBoard(${board.id})">Перейти к задачам</button>
                        </div>
                    `;
                    boardsContainer.appendChild(card);
                });
            })
            .catch(error => {
                console.error('Ошибка загрузки досок:', error);
                showMessage('error', 'Ошибка загрузки досок. Проверьте подключение к интернету.');
            });
    }

    window.editBoard = function(button) {
        const boardId = button.getAttribute('data-id');
        const currentTitle = JSON.parse(button.getAttribute('data-title'));
        const currentDescription = JSON.parse(button.getAttribute('data-description'));

        const newTitle = prompt("Введите новое название доски:", currentTitle);
        if (!newTitle) return;

        const newDescription = prompt("Введите новое описание доски:", currentDescription || '');

        if (isNaN(boardId)) {
            showMessage('error', "Ошибка: Неверный идентификатор доски");
            return;
        }

        fetch('api/boards.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: parseInt(boardId),
                title: newTitle.trim(),
                description: newDescription?.trim() || ''
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showMessage('success', "Доска обновлена!");
                loadBoards();
            } else {
                showMessage('error', `Ошибка: ${data.error || 'Неизвестная ошибка'}`);
            }
        })
        .catch(error => {
            console.error('Ошибка обновления доски:', error);
            showMessage('error', 'Произошла ошибка сети при обновлении доски');
        });
    };

    window.deleteBoard = function(boardId) {
        if (!confirm("Удалить эту доску?")) return;
        fetch(`api/boards.php?action=delete&board_id=${boardId}`, {
            method: 'POST'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showMessage('success', "Доска удалена!");
                loadBoards();
            } else {
                showMessage('error', `Ошибка: ${data.error || 'Неизвестная ошибка'}`);
            }
        })
        .catch(error => {
            console.error('Ошибка удаления доски:', error);
            showMessage('error', 'Произошла ошибка сети при удалении доски');
        });
    };

    window.viewBoard = function(boardId) {
        window.location.href = `tasks.php?board_id=${boardId}`;
    };

    window.createBoard = function() {
        const title = prompt("Введите название доски:");
        if (!title) return;
        const description = prompt("Введите описание доски (опционально):");
        const isPrivate = confirm("Сделать доску приватной?");
        fetch('api/boards.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                title: title.trim(),
                description: description?.trim() || '',
                is_private: !!isPrivate
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showMessage('success', "Доска создана успешно!");
                loadBoards();
            } else {
                showMessage('error', `Ошибка создания доски: ${data.message || 'Неизвестная ошибка'}`);
            }
        })
        .catch(error => {
            console.error('Ошибка создания доски:', error);
            showMessage('error', 'Произошла ошибка сети при создании доски');
        });
    };

    loadBoards();
});