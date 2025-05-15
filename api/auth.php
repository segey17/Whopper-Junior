<?php
session_start(); // Возвращаем, т.к. этот скрипт может быть точкой входа (login, register)
require '../db.php';

$auth_action = null;
if (isset($_POST['username']) && isset($_POST['password'])) {
    if (isset($_GET['action']) && $_GET['action'] === 'login') {
        $auth_action = 'login';
    } elseif (isset($_GET['action']) && $_GET['action'] === 'register') {
        $auth_action = 'register';
    } else if (basename($_SERVER['PHP_SELF']) === 'auth.php' && isset($_POST['username']) && isset($_POST['password']) && !isset($_GET['action'])){
        // Попытка логина, если вызван auth.php напрямую без action в GET, но есть POST данные
        $auth_action = 'login';
    }
}

if ($auth_action === 'login') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'] // Убедимся, что роль также сохраняется
        ];
        // Для обратной совместимости или если где-то используется напрямую:
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        // Удаляем сообщение об ошибке, если оно было
        unset($_SESSION['login_error']);

        header("Location: ../dashboard.php");
        exit();
    } else {
        $_SESSION['login_error'] = "Неверный логин или пароль.";
        header("Location: ../login.php");
        exit();
    }
}

if ($auth_action === 'register') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['register_error'] = "Логин и пароль не могут быть пустыми.";
        header("Location: ../register.php");
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['register_error'] = "Пароли не совпадают.";
        header("Location: ../register.php");
        exit();
    }

    if (strlen($password) < 6) {
         $_SESSION['register_error'] = "Пароль должен быть не менее 6 символов.";
         header("Location: ../register.php");
         exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt_check->execute([$username]);
    if ($stmt_check->fetchColumn() > 0) {
        $_SESSION['register_error'] = "Пользователь с таким именем уже существует.";
        header("Location: ../register.php");
        exit();
    }
    try {
        $stmt_insert = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')"); // Указываем роль по умолчанию
        $stmt_insert->execute([$username, $hashed_password]);
        $user_id = $pdo->lastInsertId();

        // Автологин после успешной регистрации
        $_SESSION['user'] = [
            'id' => $user_id,
            'username' => $username,
            'role' => 'user' // Явно указываем роль
        ];
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;

        // Удаляем сообщение об ошибке, если оно было
        unset($_SESSION['register_error']);

        header("Location: ../dashboard.php");
        exit();
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage()); // Логируем ошибку
        $_SESSION['register_error'] = "Ошибка регистрации. Пожалуйста, попробуйте позже.";
        header("Location: ../register.php");
        exit();
    }
}

// Логика get_user остается как есть, если она нужна
if (isset($_GET['action']) && $_GET['action'] === 'get_user') {
    $user = [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        // 'role' => $_SESSION['role'] ?? null, // если нужно
    ];
    echo json_encode($user);
    exit();
}
?>
