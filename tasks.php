<!DOCTYPE html>
<html>
<head>
    <title>Задачи</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
    <script src="js/tasks.js" defer></script>
    
</head>
<body>
<header>
    <h1>Задачник</h1>
    <nav>
        <ul>
            <li><a href="dashboard.php">Доски</a></li>
            <li><a href="logout.php">Выйти</a></li>
            <li><button onclick="toggleTheme()">Сменить тему</button></li>
        </ul>
    </nav>
</header>

<main>
    <h1>Задачи на доске</h1>
    <button onclick="createTask()">Добавить задачу</button>
    <div id="tasks"></div>
</main>
</body>
</html>