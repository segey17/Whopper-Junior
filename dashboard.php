<?php
session_start();
// Обновленная проверка авторизации
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) { // Проверяем наличие массива user и id в нем
    header('Location: login.php');
    exit;
}
$username = $_SESSION['user']['username'] ?? 'Пользователь';
$user_role = $_SESSION['user']['role'] ?? 'user'; // Теперь роль можно получить из массива user
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Доски</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
    <!-- Подключаем внешний JS файл -->
    <script src="js/dashboard.js" defer></script>
</head>
<body>
<header>
    <div class="logo">Задачник</div>
    <nav>
        <ul>
            <li><a href="dashboard.php">Доски</a></li>
            <?php if ($user_role === 'manager' || $user_role === 'developer'): ?>
                <li><a href="admin.php">Админка</a></li>
            <?php endif; ?>
            <li><a href="logout.php">Выйти</a></li>
            <li><button onclick="toggleTheme()">Сменить тему</button></li>
            <li><span id="user-info">Привет, <?php echo htmlspecialchars($username); ?>!</span></li>
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
            <input type="text" id="boardDescription" name="boardDescription" />
        </div>
        <button type="submit">Создать</button>
        <div id="message" style="margin-top: 10px;"></div>
    </form>

    <!-- Список досок -->
    <div id="boards" style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 30px;"></div>
</main>

</body>
</html>
