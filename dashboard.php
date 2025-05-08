<!DOCTYPE html>
<html>
<head>
    <title>Мои доски</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
    <script src="js/app.js" defer></script>
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
    <h1>Мои доски</h1>
    <div id="boards"></div>
    <button onclick="createBoard()">Создать новую доску</button>
</main>
</body>
</html>