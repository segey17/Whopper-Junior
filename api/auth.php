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
        exit();
    } else {
        echo "Неверный логин или пароль.";
    }
}
if ($action === 'register') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        die("Пользователь с таким именем уже существует.");
    }
    try {
        $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)")
           ->execute([$username, $password]);
        // После успешной регистрации перенаправляем на dashboard
        header("Location: ../dashboard.php");
        exit();
    } catch (PDOException $e) {
        echo "Ошибка регистрации: " . $e->getMessage();
    }
}
$_SESSION['user'] = $user;
header("Location: ../dashboard.php");
exit();
if ($action === 'get_user') {
    echo json_encode($_SESSION['user']);
    exit();
}
?>
