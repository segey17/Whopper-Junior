document.addEventListener('DOMContentLoaded', () => {
    const themeButton = document.querySelector('button[onclick="toggleTheme()"]');
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);

    if (themeButton) {
        themeButton.textContent = savedTheme === 'dark' ? 'Светлая тема' : 'Темная тема';
    }

    window.toggleTheme = function () {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);

        if (themeButton) {
            themeButton.textContent = newTheme === 'dark' ? 'Светлая тема' : 'Темная тема';
        }
    };
});