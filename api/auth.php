<?php
session_start();
require '../db.php';

$action = $_GET['action'] ?? 'login';

if ($action === 'login') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;
        header("Location: ../dashboard.php");
    } else {
        echo "Неверный логин или пароль.";
    }
}

if ($action === 'register') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)")
           ->execute([$username, $password, 'user']);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Ошибка регистрации: ' . $e->getMessage()]);
    }
}
?>