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
    description TEXT,
    is_private BOOLEAN DEFAULT FALSE,
    owner_id INT,
    FOREIGN KEY (owner_id) REFERENCES users(id)
);

-- Задачи
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    board_id INT,
    title VARCHAR(100),
    description TEXT,
    status VARCHAR(50),
    priority ENUM('низкий', 'средний', 'высокий'),
    deadline DATE,
    assigned_to INT NULL,
    progress INT DEFAULT 0,
    FOREIGN KEY (board_id) REFERENCES boards(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Участники досок
CREATE TABLE board_members (
    board_id INT,
    user_id INT,
    FOREIGN KEY (board_id) REFERENCES boards(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

ALTER TABLE users ADD COLUMN role ENUM('user', 'manager', 'developer') DEFAULT 'user';
ALTER TABLE tasks ADD COLUMN assigned_to INT NULL;
-- Убедитесь, что таблица tasks содержит все необходимые поля
ALTER TABLE tasks ADD COLUMN assigned_to INT NULL AFTER deadline;
ALTER TABLE tasks ADD COLUMN progress INT DEFAULT 0;
ALTER TABLE tasks MODIFY COLUMN deadline DATETIME NULL;
-- Убедитесь, что is_private — это BOOLEAN
ALTER TABLE boards MODIFY COLUMN is_private BOOLEAN DEFAULT FALSE;
ALTER TABLE boards MODIFY COLUMN description TEXT NOT NULL DEFAULT '';
UPDATE boards SET description = '' WHERE description IS NULL;
