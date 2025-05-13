<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать доску</title>
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
        </ul>
    </nav>
</header>

<main style="max-width: 600px; margin: 50px auto;">
    <h2>Редактировать доску</h2>
    <form id="editBoardForm">
        <input type="hidden" id="boardId">
        <div class="input-group">
            <label for="boardTitle">Название:</label>
            <input type="text" id="boardTitle" required>
        </div>
        <div class="input-group">
            <label for="boardDescription">Описание:</label>
            <textarea id="boardDescription"></textarea>
        </div>
        <div class="input-group">
            <label>
                <input type="checkbox" id="isPrivate"> Приватная
            </label>
        </div>
        <!-- Новое поле для добавления участника -->
        <div class="input-group" style="margin-top: 20px;">
            <label for="memberUsername">Добавить участника по логину:</label>
            <input type="text" id="memberUsername" placeholder="Введите логин">
            <button onclick="addMemberByUsername()">Добавить</button>
        </div>
        <div id="memberMessage"></div>
        <button type="submit">Сохранить изменения</button>
        <div id="message" style="margin-top: 10px;"></div>
    </form>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const boardId = new URLSearchParams(window.location.search).get('board_id');
    const messageDiv = document.getElementById('message');

    if (!boardId) {
        messageDiv.textContent = 'ID доски не указан.';
        messageDiv.style.color = 'red';
        return;
    }

    function addMemberByUsername() {
    const username = document.getElementById('memberUsername').value.trim();
    const boardId = document.getElementById('boardId').value;
    const messageDiv = document.getElementById('memberMessage');

    if (!username || !boardId) {
        messageDiv.textContent = 'Укажите логин и ID доски.';
        messageDiv.style.color = 'red';
        return;
    }

    // Сначала ищем пользователя по логину
    fetch(`api/users.php?action=get`)
        .then(res => res.json())
        .then(users => {
            const user = users.find(u => u.username === username);
            if (!user) {
                messageDiv.textContent = 'Пользователь не найден.';
                messageDiv.style.color = 'red';
                return;
            }

            // Добавляем найденного пользователя как участника доски
            fetch('api/boards.php?action=add_member', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ board_id: boardId, user_id: user.id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    messageDiv.textContent = 'Участник успешно добавлен!';
                    messageDiv.style.color = 'green';
                    setTimeout(() => messageDiv.textContent = '', 3000);
                } else {
                    messageDiv.textContent = 'Ошибка при добавлении: ' + data.error;
                    messageDiv.style.color = 'red';
                }
            });
        })
        .catch(err => {
            console.error("Ошибка поиска пользователя:", err);
            messageDiv.textContent = "Произошла сетевая ошибка.";
            messageDiv.style.color = "red";
        });
}

    // Загрузка данных доски
    fetch(`api/boards.php?action=get`)
        .then(res => res.json())
        .then(boards => {
            const board = boards.find(b => b.id == boardId);
            if (!board) {
                messageDiv.textContent = 'Доска не найдена.';
                messageDiv.style.color = 'red';
                return;
            }
            document.getElementById('boardId').value = board.id;
            document.getElementById('boardTitle').value = board.title;
            document.getElementById('boardDescription').value = board.description;
            document.getElementById('isPrivate').checked = board.is_private == 1;
        });

    // Обработка формы
    document.getElementById('editBoardForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const data = {
            id: document.getElementById('boardId').value,
            title: document.getElementById('boardTitle').value.trim(),
            description: document.getElementById('boardDescription').value.trim(),
            is_private: document.getElementById('isPrivate').checked ? 1 : 0
        };

        fetch('api/boards.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                messageDiv.textContent = 'Доска успешно обновлена!';
                messageDiv.style.color = 'green';
                setTimeout(() => window.location.href = 'dashboard.php', 1000);
            } else {
                messageDiv.textContent = 'Ошибка: ' + response.error;
                messageDiv.style.color = 'red';
            }
        })
        .catch(err => {
            console.error('Ошибка:', err);
            messageDiv.textContent = 'Произошла ошибка сети.';
            messageDiv.style.color = 'red';
        });
    });
});
</script>
</body>
</html>