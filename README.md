Задачник с командной работой
Для разработки используем:
Бекенд - Node.js
Фронтенд - Vue.js
База данных - PostgreSQL

Архитектура сайта:
1. Лицевая страница
Эта страница представляет собой первую точку контакта с сервисом и дает посетителю общее представление о нашем продукте и преимуществах его использования. Элементы лицевой страницы:
· заголовок сайта.
· Краткое описание функционала платформы.
· Примеры возможных сценариев использования (создание досок, назначение задач, отслеживание прогресса).
· Кнопки регистрации и авторизации, а также входа в личный кабинет.
· Контакты создателей.

2. Авторизация и регистрация
Эта страница нужна для регистрации новых пользователей и доступа к личному кабинету уже зарегистрированных. Понадобится две формы:
Форма регистрации нового пользователя:
• Имя пользователя.
• Email.
• Пароль.
• Подтверждение пароля.
Форма входа (авторизации):
Вход будет через email и пароль.
3. Личный кабинет
Здесь каждый пользователь увидит списки своих досок и свою роль в каждой из них.
Элементы личного кабинета:
• Список досок, в которых участвует пользователь.
• Разделение списков по статусам участия (Пользователь, Менеджер, Разработчик).
• Быстрая навигация по основным действиям (создать новую доску, пригласить коллег).
4. Доска задач
Основная страница сервиса, на которой будут располагаться карточки задач пользователей. Элементы данной страницы:
• Заголовок доски и ее описание.
• Панель инструментов для управления доской (редактирование, удаление, изменение настроек).
• Колонки (статусные этапы задачи: "Ожидание", "В работе", "Завершено"), каждая из которых состоит из карточек задач.
• Каждую карточку задачи можно перетаскивать между столбцами, менять её свойства (описание, сроки, ответственный исполнитель).
• Мгновенное обновление данных (реактивность).

Пошаговый план реализации проекта
Этап 1: Определение структуры проекта
Начнем с проектирования общей структуры нашего приложения:
project-task-manager/
project-task-manager/
│
├── backend/ # Серверная часть (Node.js)
│ ├── src/
│ │ ├── controllers/ # Контроллеры для бизнес-логики
│ │ ├── models/ # Модели для базы данных (ORM)
│ │ └── routes/ # Маршруты API
│ ├── package.json # Конфигурационный файл npm
│ └── server.js # Основной запускаемый файл
│
├── frontend/ # Клиентская часть (Vue.js)
│ ├── public/ # Статичные файлы (HTML, иконки)
│ ├── src/
│ │ ├── components/ # Компоненты Vue.js
│ │ ├── views/ # Экранные представления
│ │ ├── store/ # State management (Vuetify или Vuex)
│ │ └── App.vue # Главный контейнер приложения
│ ├── index.html # Главная точка входа фронта
│ └── main.js # Запускающий скрипт фронта
│
├── .env # Окружение для переменных среды
├── docker-compose.yml # Docker Compose конфиг
├── README.md # Документация проекта

Этап 2: Реализация серверной части (Backend)
Шаги на данном этапе:
Настройка маршрутов API
Создаем маршруты для основных сущностей (users, boards, tasks).
Этап 3: Реализация клиентской части (Frontend)
Шаги на данном этапе:
Структура страниц и представлений
1. Главная страница
2. Формы авторизации и регистрации
3. Личный кабинет пользователя
4. Рабочая доска задач
Компоненты и логика работы
Разрабатываем базовые компоненты для задач, карточек, панелей.
Используем Vue для упрощения стилизации.
Этап 4: Деплоймент и инфраструктура
Для контейнеризации и развертывания будем использовать Docker.

~ваш Воппер джуниор
