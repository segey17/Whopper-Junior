<?php session_start();
// 1. Проверка, вошел ли пользователь
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
// 2. Проверка, имеет ли пользователь право доступа (роль manager или developer)
if ($_SESSION['user']['role'] !== 'manager' && $_SESSION['user']['role'] !== 'developer') {
    // Можно перенаправить на dashboard или показать страницу с ошибкой доступа
    header('Location: dashboard.php?error=access_denied');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админка — Управление пользователями</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <script src="js/theme.js" defer></script>
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
