<?php
/**
 * Create a new notification for a user
 * 
 * @param mysqli $con Database connection
 * @param string $user_id The user who will receive the notification
 * @param string $notification_type Notification type (course_update, new_enrollment, review, message, achievement, certificate, assignment, discussion, announcement, system, payment)
 * @param string $title Notification title (max 120 chars)
 * @param string $message Notification message (max 500 chars)
 * @param string|null $link_url Optional URL to navigate to when clicked
 * @param string|null $related_entity_type Optional entity type (e.g., 'course', 'user', 'enrollment')
 * @param int|null $related_entity_id Optional entity ID
 * @param string $priority Priority level (low, normal, high, urgent) - default 'normal'
 * @return bool True on success, false on failure
 */
function create_notification($con, $user_id, $notification_type, $title, $message, $link_url = null, $related_entity_type = null, $related_entity_id = null, $priority = 'normal') {
    $user_id = mysqli_real_escape_string($con, $user_id);
    $notification_type = mysqli_real_escape_string($con, $notification_type);
    $title = substr(mysqli_real_escape_string($con, $title), 0, 120);
    $message = substr(mysqli_real_escape_string($con, $message), 0, 500);
    $link_url = $link_url ? mysqli_real_escape_string($con, $link_url) : null;
    $related_entity_type = $related_entity_type ? mysqli_real_escape_string($con, $related_entity_type) : null;
    $related_entity_id = $related_entity_id ? (int)$related_entity_id : null;
    $priority = mysqli_real_escape_string($con, $priority);
    
    $link_url_value = $link_url ? "'$link_url'" : "NULL";
    $related_entity_type_value = $related_entity_type ? "'$related_entity_type'" : "NULL";
    $related_entity_id_value = $related_entity_id ? "'$related_entity_id'" : "NULL";
    
    $query = "INSERT INTO notifications (user_id, notification_type, title, message, link_url, related_entity_type, related_entity_id, is_read, priority, created_at) 
              VALUES ('$user_id', '$notification_type', '$title', '$message', $link_url_value, $related_entity_type_value, $related_entity_id_value, 0, '$priority', NOW())";
    
    return mysqli_query($con, $query);
}

/**
 * Create notifications for multiple users at once
 * 
 * @param mysqli $con Database connection
 * @param array $user_ids Array of user IDs
 * @param string $notification_type Notification type
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string|null $link_url Optional URL
 * @param string|null $related_entity_type Optional entity type
 * @param int|null $related_entity_id Optional entity ID
 * @param string $priority Priority level
 * @return bool True on success
 */
function create_bulk_notifications($con, $user_ids, $notification_type, $title, $message, $link_url = null, $related_entity_type = null, $related_entity_id = null, $priority = 'normal') {
    $success = true;
    foreach ($user_ids as $user_id) {
        if (!create_notification($con, $user_id, $notification_type, $title, $message, $link_url, $related_entity_type, $related_entity_id, $priority)) {
            $success = false;
        }
    }
    return $success;
}

/**
 * Delete old read notifications (cleanup function)
 * 
 * @param mysqli $con Database connection
 * @param int $days_old Delete notifications older than this many days
 * @return bool True on success
 */
function delete_old_notifications($con, $days_old = 30) {
    $days_old = (int)$days_old;
    $query = "DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL $days_old DAY)";
    return mysqli_query($con, $query);
}

/**
 * Get unread notification count for a user
 * 
 * @param mysqli $con Database connection
 * @param int $user_id User ID
 * @return int Count of unread notifications
 */
function get_unread_notification_count($con, $user_id) {
    $user_id = mysqli_real_escape_string($con, $user_id);
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = '$user_id' AND is_read = 0";
    $result = mysqli_query($con, $query);
    
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return (int)$row['count'];
    }
    return 0;
}
?>
