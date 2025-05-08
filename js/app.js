document.addEventListener('DOMContentLoaded', () => {
    const boardsContainer = document.getElementById('boards');

    function loadBoards() {
        fetch('api/boards.php?action=get')
            .then(res => res.json())
            .then(boards => {
                boardsContainer.innerHTML = '';
                if (boards.length === 0) {
                    boardsContainer.innerHTML = '<div class="empty-state">У вас пока нет досок. Создайте первую!</div>';
                } else {
                    boards.forEach(board => {
                        const card = document.createElement('div');
                        card.className = 'board-card';
                        card.innerHTML = `
                            <h3>${board.title}</h3>
                            <p>${board.description}</p>
                            <button onclick="viewBoard(${board.id})">Перейти к доске</button>
                        `;
                        boardsContainer.appendChild(card);
                    });
                }
            })
            .catch(error => {
                console.error('Ошибка при загрузке досок:', error);
                boardsContainer.innerHTML = '<div class="error-message">Ошибка при загрузке досок. Попробуйте еще раз позже.</div>';
            });
    }

    function viewBoard(boardId) {
        window.location.href = `tasks.php?board_id=${boardId}`;
    }

    // Объявляем функцию viewBoard в глобальной области, иначе она не будет доступна из кнопки
    window.viewBoard = viewBoard;

    loadBoards();
});

// Выносим функцию createBoard за пределы обработчика DOMContentLoaded
function createBoard() {
    const title = prompt("Введите название доски:");
    if (!title) return;

    const description = prompt("Введите описание доски (опционально):");

    fetch('api/boards.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            title: title,
            description: description,
            is_private: false
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("Доска создана успешно!");
            location.reload();
        } else {
            alert('Ошибка при создании доски: ' + (data.message || 'Неизвестная ошибка.'));
        }
    })
    .catch(error => {
        console.error('Ошибка при создании доски:', error);
        alert('Ошибка сети при создании доски.');
    });
}

// Объявляем функцию toggleTheme в глобальной области, иначе она не будет доступна из кнопки
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    const themeButton = document.querySelector('button[onclick="toggleTheme()"]');
    themeButton.textContent = newTheme === 'dark' ? 'Светлая тема' : 'Темная тема';
}

window.toggleTheme = toggleTheme;