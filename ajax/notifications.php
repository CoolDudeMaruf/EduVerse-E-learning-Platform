<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/config.php';

$is_logged_in = isset($_SESSION['user_id']);

if (!$is_logged_in) {
    echo json_encode(['success' => false, 'notifications' => [], 'unread_count' => 0]);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : 'get';

if ($action === 'mark_read') {
    $notification_id = isset($_POST['notification_id']) ? mysqli_real_escape_string($con, $_POST['notification_id']) : null;
    
    if ($notification_id) {
        $update_query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = '$notification_id' AND user_id = '$user_id'";
        mysqli_query($con, $update_query);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    }
    exit;
}

if ($action === 'mark_all_read') {
    $update_query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = '$user_id' AND is_read = 0";
    mysqli_query($con, $update_query);
    echo json_encode(['success' => true]);
    exit;
}

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

$query = "SELECT notification_id, notification_type, title, message, link_url, related_entity_type, 
          related_entity_id, is_read, read_at, priority, created_at
          FROM notifications
          WHERE user_id = '$user_id'
          ORDER BY priority DESC, created_at DESC
          LIMIT $limit";

$result = mysqli_query($con, $query);
$notifications = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = [
            'notification_id' => $row['notification_id'],
            'type' => $row['notification_type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'link_url' => $row['link_url'],
            'related_entity_type' => $row['related_entity_type'],
            'related_entity_id' => $row['related_entity_id'],
            'is_read' => (bool)$row['is_read'],
            'read_at' => $row['read_at'],
            'priority' => $row['priority'],
            'created_at' => $row['created_at']
        ];
    }
}

$unread_query = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = '$user_id' AND is_read = 0";
$unread_result = mysqli_query($con, $unread_query);
$unread_count = 0;

if ($unread_result) {
    $unread_row = mysqli_fetch_assoc($unread_result);
    $unread_count = (int)$unread_row['unread_count'];
}

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unread_count
]);
?>
