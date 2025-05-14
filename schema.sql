-- Пользователи
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'manager', 'developer') DEFAULT 'user'
);

-- Доски
CREATE TABLE boards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100),
    description TEXT NOT NULL DEFAULT '',
    is_private BOOLEAN DEFAULT TRUE, -- Изменено: по умолчанию доска приватная
    owner_id INT,
    FOREIGN KEY (owner_id) REFERENCES users(id)
);

-- Задачи
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    board_id INT,
    title VARCHAR(100),
    description TEXT,
    status VARCHAR(50), -- Например: "В ожидании", "В работе", "Завершено"
    priority ENUM('низкий', 'средний', 'высокий'),
    deadline DATETIME NULL, -- Изменено на DATETIME для возможности указания времени
    assigned_to INT NULL,
    progress INT DEFAULT 0, -- Прогресс в процентах
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, -- Дата создания задачи
    FOREIGN KEY (board_id) REFERENCES boards(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Участники досок
CREATE TABLE board_members (
    board_id INT,
    user_id INT,
    FOREIGN KEY (board_id) REFERENCES boards(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    PRIMARY KEY (board_id, user_id) -- Гарантирует уникальность пары пользователь-доска
);

-- Обновления для таблицы boards для обеспечения консистентности поля description
ALTER TABLE boards MODIFY COLUMN description TEXT NOT NULL DEFAULT '';
UPDATE boards SET description = '' WHERE description IS NULL;

-- Комментарий: Для поля 'status' в таблице 'tasks' также можно рассмотреть использование ENUM,
-- например: ENUM('В ожидании', 'В работе', 'Завершено') DEFAULT 'В ожидании'.
-- Это может повысить целостность данных. Оставляем VARCHAR(50) согласно текущей структуре,
-- но это возможное улучшение на будущее.
