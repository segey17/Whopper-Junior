document.addEventListener('DOMContentLoaded', () => {
    const themeButton = document.querySelector('button[onclick="toggleTheme()"]');
    const savedTheme = localStorage.getItem('theme');

    // Устанавливаем тему из localStorage или по умолчанию светлую
    const initialTheme = savedTheme || 'light';
    document.documentElement.setAttribute('data-theme', initialTheme);
    themeButton.textContent = initialTheme === 'dark' ? 'Светлая тема' : 'Темная тема';

    window.toggleTheme = function() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        themeButton.textContent = newTheme === 'dark' ? 'Светлая тема' : 'Темная тема';
    };
});