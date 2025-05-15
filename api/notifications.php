<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../db.php'; // db.php в родительской директории (task-manager)
require_once 'auth.php';    // auth.php в той же директории (api), содержит session_start()

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Пользователь не авторизован.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? ''; // Принимаем action из GET или POST

switch ($action) {
    case 'get_unread':
        getUnreadNotifications($pdo, $current_user_id);
        break;
    case 'mark_as_read':
        markNotificationAsRead($pdo, $current_user_id);
        break;
    case 'mark_all_as_read':
        markAllNotificationsAsRead($pdo, $current_user_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Неизвестное действие.']);
        break;
}

function getUnreadNotifications($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, message, board_id, task_id, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $notifications_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $notifications = array_map(function($notification) {
            $notification['message'] = htmlspecialchars_decode($notification['message'], ENT_QUOTES);
            return $notification;
        }, $notifications_raw);
        echo json_encode(['success' => true, 'notifications' => $notifications]);
    } catch (PDOException $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Ошибка при получении уведомлений: ' . $e->getMessage()]);
    }
}

function markNotificationAsRead($pdo, $user_id) {
    $notification_id = $_POST['notification_id'] ?? null;
    if (!$notification_id) {
        echo json_encode(['success' => false, 'message' => 'Не указан ID уведомления.']);
        return;
    }

    try {
        // Дополнительная проверка: принадлежит ли уведомление текущему пользователю
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id = ? AND user_id = ?");
        $stmt_check->execute([$notification_id, $user_id]);
        if ($stmt_check->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'message' => 'Уведомление не найдено или нет прав.']);
            return;
        }

        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $success = $stmt->execute([$notification_id, $user_id]);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Уведомление отмечено как прочитанное.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Не удалось отметить уведомление как прочитанное.']);
        }
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Ошибка при обновлении уведомления: ' . $e->getMessage()]);
    }
}

function markAllNotificationsAsRead($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $success = $stmt->execute([$user_id]);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Все уведомления отмечены как прочитанные.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Не удалось отметить все уведомления как прочитанные.']);
        }
    } catch (PDOException $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Ошибка при обновлении всех уведомлений: ' . $e->getMessage()]);
    }
}

?>
