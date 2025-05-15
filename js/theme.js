(function() {
    // 1. Получаем тему из localStorage или по умолчанию ('light')
    const storedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', storedTheme);

    // 2. Ищем кнопку смены темы (по id)
    const themeToggleButton = document.getElementById('theme-toggle-button');

    if (themeToggleButton) {
        themeToggleButton.textContent = storedTheme === 'dark' ? 'Светлая тема' : 'Темная тема';

        themeToggleButton.addEventListener('click', function() {
            const nextTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', nextTheme);
            localStorage.setItem('theme', nextTheme);
            themeToggleButton.textContent = nextTheme === 'dark' ? 'Светлая тема' : 'Темная тема';
        });
    }
    // Если кнопки нет, тихо выходим
})();
