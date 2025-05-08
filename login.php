<!DOCTYPE html>
<html>
<head>
    <title>Вход</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/theme.js" defer></script>
</head>
<body>
<header>
    <h1>Задачник</h1>
    <nav>
        <ul>
            <li><a href="login.php">Войти</a></li>
            <li><a href="register.php">Зарегистрироваться</a></li>
        </ul>
    </nav>
</header>

<main>
    <h2>Вход</h2>
    <form action="api/auth.php" method="post">
        <input type="text" name="username" placeholder="Логин" required><br><br>
        <input type="password" name="password" placeholder="Пароль" required><br><br>
        <button type="submit">Войти</button>
    </form>
    <p>Еще не зарегистрированы? <a href="register.php">Зарегистрироваться</a></p>
</main>
</body>
</html>