document.addEventListener('DOMContentLoaded', () => {
    const notificationsIcon = document.getElementById('notifications-icon');
    const notificationsBadge = document.getElementById('notifications-badge');
    const notificationsDropdown = document.getElementById('notifications-dropdown');
    const notificationsList = document.getElementById('notifications-list');
    const markAllAsReadButton = document.getElementById('mark-all-as-read');

    let areNotificationsVisible = false;
    const API_URL = 'api/notifications.php'; // Убедитесь, что путь к API правильный

    // 1. Получение непрочитанных уведомлений
    async function fetchUnreadNotifications() {
        try {
            const response = await fetch(`${API_URL}?action=get_unread`);
            if (!response.ok) {
                console.error('Network response was not ok for notifications');
                return;
            }
            const data = await response.json();
            if (data.success && data.notifications) {
                displayNotifications(data.notifications);
            } else if (data.success && data.notifications.length === 0) {
                displayNotifications([]); // Показать пустой список
            }
        } catch (error) {
            console.error('Error fetching notifications:', error);
        }
    }

    // 2. Отображение уведомлений
    function displayNotifications(notifications) {
        notificationsList.innerHTML = ''; // Очищаем старые уведомления

        if (notifications.length === 0) {
            notificationsList.innerHTML = '<li class="notification-item-empty">Нет новых уведомлений</li>';
            notificationsBadge.style.display = 'none';
            notificationsBadge.textContent = '0';
        } else {
            notifications.forEach(notification => {
                const listItem = document.createElement('li');
                listItem.classList.add('notification-item');
                listItem.dataset.id = notification.id;

                const messageSpan = document.createElement('span');
                messageSpan.classList.add('notification-message');
                messageSpan.textContent = notification.message; // Убираем escapeHtml, т.к. textContent безопасен

                const markReadButton = document.createElement('button');
                markReadButton.classList.add('mark-as-read', 'notifications-button');
                markReadButton.textContent = 'Прочитано';
                markReadButton.addEventListener('click', (e) => {
                    e.stopPropagation(); // Предотвращаем закрытие дропдауна
                    markNotificationAsRead(notification.id);
                });

                listItem.appendChild(messageSpan);
                listItem.appendChild(markReadButton);
                notificationsList.appendChild(listItem);
            });
            notificationsBadge.textContent = notifications.length;
            notificationsBadge.style.display = 'block';
        }
    }

    // 3. Отметка одного уведомления как прочитанного
    async function markNotificationAsRead(notificationId) {
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=mark_as_read&notification_id=${notificationId}`
            });
            const data = await response.json();
            if (data.success) {
                // Обновляем счетчик и весь список, он больше не будет содержать прочитанное уведомление
                fetchUnreadNotifications();
            } else {
                console.error('Failed to mark notification as read:', data.message);
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    // 4. Отметка всех уведомлений как прочитанных
    async function markAllNotificationsAsRead() {
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=mark_all_as_read`
            });
            const data = await response.json();
            if (data.success) {
                notificationsList.innerHTML = '<li class="notification-item-empty">Нет новых уведомлений</li>';
                notificationsBadge.style.display = 'none';
                notificationsBadge.textContent = '0';
            } else {
                console.error('Failed to mark all notifications as read:', data.message);
            }
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
        }
    }

    // Показать/скрыть выпадающий список уведомлений
    function toggleNotificationsDropdown() {
        areNotificationsVisible = !areNotificationsVisible;
        notificationsDropdown.style.display = areNotificationsVisible ? 'block' : 'none';
        if (areNotificationsVisible) {
            fetchUnreadNotifications(); // Загружаем при открытии
        }
    }

    // Инициализация
    if (notificationsIcon) {
        notificationsIcon.addEventListener('click', toggleNotificationsDropdown);
    }

    if (markAllAsReadButton) {
        markAllAsReadButton.addEventListener('click', () => {
            markAllNotificationsAsRead();
        });
    }

    // Закрытие дропдауна при клике вне его
    document.addEventListener('click', (event) => {
        if (notificationsIcon && notificationsDropdown) {
            const isClickInsideIcon = notificationsIcon.contains(event.target);
            const isClickInsideDropdown = notificationsDropdown.contains(event.target);
            if (!isClickInsideIcon && !isClickInsideDropdown && areNotificationsVisible) {
                toggleNotificationsDropdown();
            }
        }
    });

    // Периодический опрос каждые 30 секунд
    setInterval(fetchUnreadNotifications, 30000);

    // Первоначальная загрузка уведомлений
    fetchUnreadNotifications();
});
