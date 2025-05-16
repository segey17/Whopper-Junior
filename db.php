<?php
$host = getenv('DB_HOST') ?: "db";
$dbname = getenv('DB_DATABASE') ?: "task_manager";
$user = getenv('DB_USERNAME') ?: "root";
$password = getenv('DB_PASSWORD') ?: "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}
?>
