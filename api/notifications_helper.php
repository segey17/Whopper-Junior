<?php

function create_app_notification($pdo, $initiator_user_id, $receiver_user_id, $board_id, $task_id, $message) {
    if ($receiver_user_id == $initiator_user_id) {
        return; // Не отправляем уведомление самому себе
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, board_id, task_id, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$receiver_user_id, $board_id, $task_id, $message]);
    } catch (PDOException $e) {
        error_log("Failed to create notification: " . $e->getMessage());
    }
}
