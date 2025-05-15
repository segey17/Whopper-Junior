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
</head>
<body>
    <script src="js/theme.js"></script>
<body>
    <header>
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <div class="logo">
                <svg viewBox="0 0 24 24">
                    <path d="M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3M17,7H7V5H17M17,9H7V11H17V9M7,13H13V15H7V13Z"/>
                </svg>
                Задачник
            </div>
            <nav>
                <ul>
                    <li><a href="login.php">Войти</a></li>
                    <li><a href="register.php">Зарегистрироваться</a></li>
                    <li><button id="theme-toggle-button">Сменить тему</button></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="container">
                <h1>Управление задачами, которое работает</h1>
                <p>Платформа для командной работы и контроля задач в реальном времени. Просто, эффективно, для вас.</p>
                <div class="cta">
                    <button onclick="window.location.href='register.php'">Начать бесплатно</button>
                </div>
            </div>
        </section>

        <section class="features">
            <div class="container">
                <h2>Ключевые возможности</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-tasks"></i></div>
                        <div class="feature-title">Гибкое управление</div>
                        <div class="feature-desc">Добавляйте задачи, статусы и дедлайны. Перетаскивайте карточки как в Trello.</div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-users"></i></div>
                        <div class="feature-title">Работа в команде</div>
                        <div class="feature-desc">Создавайте публичные или приватные доски. Назначайте задачи коллегам.</div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fa-solid fa-chart-line"></i></div>
                        <div class="feature-title">Отслеживание прогресса</div>
                        <div class="feature-desc">Следите за задачами в режиме реального времени. Все обновляется мгновенно.</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="example-board-section">
            <div class="container">
                <h2>Визуализируйте свой рабочий процесс</h2>
                <p class="section-subtitle">Управляйте задачами наглядно с помощью гибких канбан-досок. Легко перемещайте задачи между этапами.</p>
                <div class="example-board-container">
                    <div class="example-column">
                        <h3 class="example-column-title">К выполнению</h3>
                        <div class="example-task-card">
                            <div class="example-task-title">Проанализировать конкурентов</div>
                            <div class="example-task-meta">Срок: 20 мая</div>
                        </div>
                        <div class="example-task-card">
                            <div class="example-task-title">Составить ТЗ для дизайнера</div>
                            <div class="example-task-meta">Приоритет: Высокий</div>
                        </div>
                        <div class="example-task-card">
                            <div class="example-task-title">Обсудить новые фичи</div>
                        </div>
                    </div>
                    <div class="example-column">
                        <h3 class="example-column-title">В процессе</h3>
                        <div class="example-task-card">
                            <div class="example-task-title">Разработка MVP</div>
                            <div class="example-task-tags">
                                <span class="tag tag-dev">Dev</span>
                                <span class="tag tag-ux">UX</span>
                            </div>
                            <div class="example-task-progress" style="width: 60%;"></div>
                        </div>
                        <div class="example-task-card">
                            <div class="example-task-title">Дизайн мобильного приложения</div>
                            <div class="example-task-meta">Исполнитель: Анна В.</div>
                        </div>
                    </div>
                    <div class="example-column">
                        <h3 class="example-column-title">Готово</h3>
                        <div class="example-task-card">
                            <div class="example-task-title">Настроить CI/CD</div>
                            <div class="example-task-meta">Завершено: 15 мая</div>
                        </div>
                        <div class="example-task-card">
                            <div class="example-task-title">Провести A/B тестирование лендинга</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta">
            <div class="container">
                <h2>Готовы начать работу?</h2>
                <p>Присоединяйтесь к тысячам пользователей, которые уже повысили свою продуктивность.</p>
                <button onclick="window.location.href='register.php'">Зарегистрироваться бесплатно</button>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Задачник. Все права защищены.</p>
            <!-- Можно добавить еще ссылок: <a href="#">Политика</a> | <a href="#">Условия</a> -->
        </div>
    </footer>
    <script src="js/theme.js" defer></script>
</body>
</html>
