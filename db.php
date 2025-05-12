<?php
$host = "localhost";
$dbname = "task_manager";
$user = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}
?>