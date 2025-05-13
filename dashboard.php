<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Доски</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
</head>
<body>
<header>
    <div class="logo">Задачник</div>
    <nav>
        <ul>
            <li><a href="dashboard.php">Доски</a></li>
            <?php if ($_SESSION['user']['role'] === 'manager' || $_SESSION['user']['role'] === 'developer'): ?>
                <li><a href="admin.php">Админка</a></li>
            <?php endif; ?>
            <li><a href="logout.php">Выйти</a></li>
            <li><button onclick="toggleTheme()">Сменить тему</button></li>
            <li><span id="user-info"></span></li>
        </ul>
    </nav>
</header>

<main style="max-width: 900px; margin: 50px auto;">
    <h1>Ваши доски</h1>
    <button id="createBoardButton">Создать новую доску</button>

    <!-- Форма создания доски -->
    <form id="createBoardForm" style="display: none; margin-top: 20px;">
        <div class="input-group">
            <label for="boardTitle">Название:</label>
            <input type="text" id="boardTitle" name="boardTitle" required />
        </div>
        <div class="input-group">
            <label for="boardDescription">Описание:</label>
            <textarea id="boardDescription" name="boardDescription"></textarea>
        </div>
        <div class="input-group">
            <label>
                <input type="checkbox" id="boardIsPrivate" name="boardIsPrivate"> Приватная
            </label>
        </div>
        <button type="submit">Создать</button>
        <div id="message" style="margin-top: 10px;"></div>
    </form>

    <!-- Список досок -->
    <div id="boards" style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 30px;"></div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const createBoardButton = document.getElementById('createBoardButton');
    const createBoardForm = document.getElementById('createBoardForm');
    const messageDiv = document.getElementById('message');
    const boardsContainer = document.getElementById('boards');

    // Переключение формы
    createBoardButton.addEventListener('click', () => {
        createBoardForm.style.display = createBoardForm.style.display === 'none' ? 'block' : 'none';
        messageDiv.textContent = '';
    });

    // Обработка отправки формы создания доски
    createBoardForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const title = document.getElementById('boardTitle').value.trim();
        const description = document.getElementById('boardDescription').value.trim();
        const isPrivate = document.getElementById('boardIsPrivate').checked;

        if (!title) {
            messageDiv.textContent = 'Введите название доски.';
            messageDiv.style.color = 'red';
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
        })
        .catch(error => {
            console.error('Ошибка создания доски:', error);
            messageDiv.textContent = 'Произошла сетевая ошибка.';
            messageDiv.style.color = 'red';
        });
    });

    // Загрузка списка досок
    function loadBoards() {
        fetch('api/boards.php?action=get')
            .then(res => res.json())
            .then(boards => {
                boardsContainer.innerHTML = '';
                if (!boards.length) {
                    boardsContainer.innerHTML = `<div class="empty-state">У вас пока нет досок. Создайте первую!</div>`;
                } else {
                    boards.forEach(board => {
                        const card = document.createElement('div');
                        card.className = 'board-card';
                        card.innerHTML = `
                            <h3>${board.title}</h3>
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
                alert('Ошибка при удалении: ' + data.error);
            }
        });
    };

    // Переход к задачам
    window.viewBoard = function(boardId) {
        window.location.href = `tasks.php?board_id=${boardId}`;
    };

    // Запуск загрузки досок при открытии страницы
    loadBoards();
});
</script>
</body>
</html>