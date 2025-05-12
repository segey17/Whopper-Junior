<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админка — Управление пользователями</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
    <style>
        .auth-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--card-border);
        }
        select {
            padding: 6px;
            border-radius: 5px;
            border: 1px solid var(--card-border);
            background-color: var(--input-bg);
            color: var(--text-color);
        }
        button.update-role-btn {
            padding: 6px 12px;
            background-color: var(--button-bg);
            color: var(--button-text);
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button.update-role-btn:hover {
            background-color: var(--button-hover);
        }
    </style>
</head>
<body>
<header>
    <div class="logo">
        <svg viewBox="0 0 24 24">
            <path d="M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3M17,7H7V5H17M17,9H7V11H17V9M7,13H13V15H7V13Z"/>
        </svg>
        Задачник
    </div>
    <nav>
        <ul>
            <li><a href="dashboard.php">Доски</a></li>
            <li><a href="logout.php">Выйти</a></li>
            <li><button onclick="toggleTheme()">Сменить тему</button></li>
        </ul>
    </nav>
</header>

<main class="auth-container">
    <h2>Управление пользователями</h2>
    <table id="users-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Имя</th>
                <th>Роль</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</main>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const usersTable = document.querySelector('#users-table tbody');

        // Функция загрузки пользователей
        function loadUsers() {
            fetch('api/users.php?action=get')
                .then(res => res.json())
                .then(users => {
                    usersTable.innerHTML = '';
                    users.forEach(user => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${user.id}</td>
                            <td>${user.username}</td>
                            <td>
                                <select class="role-select" data-id="${user.id}">
                                    <option value="user" ${user.role === 'user' ? 'selected' : ''}>Пользователь</option>
                                    <option value="manager" ${user.role === 'manager' ? 'selected' : ''}>Менеджер</option>
                                    <option value="developer" ${user.role === 'developer' ? 'selected' : ''}>Разработчик</option>
                                </select>
                            </td>
                            <td>
                                <button class="update-role-btn" onclick="updateUserRole(${user.id})">Обновить</button>
                            </td>
                        `;
                        usersTable.appendChild(row);
                    });
                })
                .catch(err => {
                    console.error("Ошибка загрузки пользователей:", err);
                    usersTable.innerHTML = '<tr><td colspan="4">Не удалось загрузить пользователей</td></tr>';
                });
        }

        // Функция обновления роли пользователя
        window.updateUserRole = function(userId) {
            const roleSelect = document.querySelector(`select[data-id="${userId}"]`);
            const newRole = roleSelect.value;

            fetch('api/users.php?action=update_role', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: userId, role: newRole })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("Роль успешно обновлена");
                } else {
                    alert("Ошибка при обновлении роли");
                }
            })
            .catch(err => {
                console.error("Ошибка обновления роли:", err);
                alert("Произошла ошибка сети");
            });
        };

        loadUsers();
    });
</script>
</body>
</html>