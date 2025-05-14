<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Задачник - Управление задачами</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css ">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/landing-page.css">
    <script src="js/theme.js" defer></script>
</head>
<body>
    <header>
    <div class="logo">
        <svg viewBox="0 0 24 24">
            <path d="M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3M17,7H7V5H17M17,9H7V11H17V9M7,13H13V15H7V13Z"/>
        </svg>
        Воппер Джуниор
    </div>
    <nav>
        <ul>
            <li><a href="login.php">Войти</a></li>
            <li><a href="register.php">Зарегистрироваться</a></li>
            <li><button onclick="toggleTheme()">Сменить тему</button></li>
        </ul>
    </nav>
</header>

<main>
    <section class="hero">
        <h1>Управление задачами, которое работает</h1>
        <p>Платформа для командной работы и контроля задач в реальном времени. Просто, эффективно, бесплатно.</p>
        <div class="cta">
            <button onclick="window.location.href='register.php'">Начать бесплатно</button>
        </div>
    </section>

    <section class="features container">
        <div class="feature-card">
            <div class="feature-icon"><i class="fa-solid fa-tasks"></i></div>
            <div class="feature-title">Гибкое управление задачами</div>
            <div class="feature-desc">Добавляйте задачи, статусы и дедлайны. Работайте с карточками как в Trello.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fa-solid fa-users"></i></div>
            <div class="feature-title">Работа в команде</div>
            <div class="feature-desc">Создавайте публичные или приватные доски. Назначайте задачи коллегам.</div>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fa-solid fa-chart-line"></i></div>
            <div class="feature-title">Отслеживание прогресса</div>
            <div class="feature-desc">Следите за прогрессом задач в режиме реального времени. Всё обновляется автоматически.</div>
        </div>
    </section>

    <section class="example-board container">
        <h2>Пример доски задач</h2>
        <div class="example-task">
            <div class="example-title">Создать MVP</div>
            <div class="example-status">В работе</div>
        </div>
        <div class="example-task">
            <div class="example-title">Дизайн интерфейса</div>
            <div class="example-status">Завершено</div>
        </div>
        <div class="example-task">
            <div class="example-title">Подготовка презентации</div>
            <div class="example-status">В ожидании</div>
        </div>
    </section>

    <section class="cta">
        <h2>Готовы  протестировать нашу хуйню?</h2>
        <p>Присоединяйтесь к платформе уже сегодня!</p>
        <button onclick="window.location.href='register.php'">Зарегистрироваться</button>
    </section>
</main>

    <footer style="text-align:center; padding: 20px; color: var(--text-color);">
        &copy; 2025 Задачник?. Все правы блять.
    </footer>
</body>
</html>
