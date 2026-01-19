<?php
require_once '../../../includes/config.php';

header('Content-Type: application/json');

if (!$is_logged_in) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'instructor') {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$instructor_id = $current_user_id;

switch ($action) {
    case 'list':
        $filter = isset($_GET['filter']) ? mysqli_real_escape_string($con, $_GET['filter']) : 'all';
        $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;
        
        $notifications = [];
        $unread_count = 0;
        
        // Get notifications from the notifications table
        $notif_query = "SELECT notification_id, notification_type, title, message, link_url, related_entity_type, 
                        related_entity_id, is_read, read_at, priority, created_at
                        FROM notifications
                        WHERE user_id = '$instructor_id'";
        
        // Apply filter
        if ($filter === 'unread') {
            $notif_query .= " AND is_read = 0";
        } elseif ($filter === 'enrollments') {
            $notif_query .= " AND notification_type IN ('new_enrollment', 'enrollment')";
        } elseif ($filter === 'reviews') {
            $notif_query .= " AND notification_type = 'review'";
        } elseif ($filter === 'revenue') {
            $notif_query .= " AND notification_type IN ('payment', 'revenue')";
        }
        
        $notif_query .= " ORDER BY created_at DESC LIMIT $limit";
        
        $notif_result = mysqli_query($con, $notif_query);
        
        if ($notif_result && mysqli_num_rows($notif_result) > 0) {
            // Use notifications table
            while ($row = mysqli_fetch_assoc($notif_result)) {
                $type = $row['notification_type'] ?? 'info';
                $icon = match($type) {
                    'new_enrollment', 'enrollment' => 'person_add',
                    'review' => 'star',
                    'payment', 'revenue' => 'attach_money',
                    'course_update' => 'update',
                    'announcement' => 'campaign',
                    default => 'notifications'
                };
                
                $color = match($type) {
                    'new_enrollment', 'enrollment' => '#4CAF50',
                    'review' => '#FF9800',
                    'payment', 'revenue' => '#2196F3',
                    default => '#9C27B0'
                };
                
                $notifications[] = [
                    'id' => $row['notification_id'],
                    'type' => $type,
                    'icon' => $icon,
                    'color' => $color,
                    'title' => $row['title'],
                    'message' => $row['message'],
                    'time' => $row['created_at'],
                    'time_ago' => timeAgo($row['created_at']),
                    'is_read' => (bool)$row['is_read'],
                    'link' => $row['link_url']
                ];
                
                if (!$row['is_read']) {
                    $unread_count++;
                }
            }
        }
        
        // Also get dynamic notifications from enrollments and reviews tables
        // These supplement the notifications table with real-time data
        
        // Get enrollments as notifications
        if ($filter === 'all' || $filter === 'enrollments') {
            $enroll_query = "SELECT e.enrollment_id, e.enrollment_date, e.student_id,
                                   u.first_name, u.last_name, c.title as course_title, c.course_id
                            FROM enrollments e
                            INNER JOIN users u ON e.student_id = u.user_id
                            INNER JOIN courses c ON e.course_id = c.course_id
                            WHERE c.instructor_id = '$instructor_id'
                            ORDER BY e.enrollment_date DESC
                            LIMIT $limit";
            $enroll_result = mysqli_query($con, $enroll_query);
            if ($enroll_result) {
                while ($row = mysqli_fetch_assoc($enroll_result)) {
                    $notif_id = 'enroll_' . $row['enrollment_id'];
                    
                    // Skip if deleted
                    if (isNotificationDeleted($notif_id, $instructor_id, $con)) {
                        continue;
                    }
                    
                    // Check if this enrollment notification already exists in our list
                    $exists = false;
                    foreach ($notifications as $n) {
                        if (strpos($n['message'], $row['course_title']) !== false && 
                            strpos($n['type'], 'enrollment') !== false) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $student_name = trim($row['first_name'] . ' ' . $row['last_name']);
                        $notifications[] = [
                            'id' => $notif_id,
                            'type' => 'new_enrollment',
                            'icon' => 'person_add',
                            'color' => '#4CAF50',
                            'title' => 'New Student Enrolled',
                            'message' => 'A student has enrolled in your course: ' . $row['course_title'],
                            'time' => $row['enrollment_date'],
                            'time_ago' => timeAgo($row['enrollment_date']),
                            'is_read' => isNotificationRead($notif_id, $instructor_id, $con),
                            'link' => 'course.php?id=' . $row['course_id']
                        ];
                    }
                }
            }
        }
        
        // Get reviews as notifications
        if ($filter === 'all' || $filter === 'reviews') {
            $review_query = "SELECT r.review_id, r.created_at, r.rating, r.review_text, r.review_title,
                                   u.first_name, u.last_name, r.course_instructor_id as course_id
                            FROM reviews r
                            INNER JOIN users u ON r.student_id = u.user_id
                            INNER JOIN courses c ON r.course_instructor_id = c.course_id
                            WHERE c.instructor_id = '$instructor_id'
                            AND r.is_published = 1
                            ORDER BY r.created_at DESC
                            LIMIT $limit";
            $review_result = mysqli_query($con, $review_query);
            if ($review_result) {
                while ($row = mysqli_fetch_assoc($review_result)) {
                    $notif_id = 'review_' . $row['review_id'];
                    
                    // Skip if deleted
                    if (isNotificationDeleted($notif_id, $instructor_id, $con)) {
                        continue;
                    }
                    
                    $exists = false;
                    foreach ($notifications as $n) {
                        if ($n['id'] == $notif_id || ($n['type'] == 'review' && strpos($n['message'], $row['review_title'] ?? '') !== false)) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $student_name = trim($row['first_name'] . ' ' . $row['last_name']);
                        $stars = str_repeat('★', $row['rating']) . str_repeat('☆', 5 - $row['rating']);
                        $notifications[] = [
                            'id' => $notif_id,
                            'type' => 'review',
                            'icon' => 'star',
                            'color' => '#FF9800',
                            'title' => 'New Course Review',
                            'message' => $student_name . ' left a ' . $row['rating'] . '-star review: "' . substr($row['review_title'] ?? $row['review_text'] ?? '', 0, 50) . '..."',
                            'time' => $row['created_at'],
                            'time_ago' => timeAgo($row['created_at']),
                            'is_read' => isNotificationRead($notif_id, $instructor_id, $con),
                            'link' => 'course.php?id=' . $row['course_id']
                        ];
                    }
                }
            }
        }
        
        // Get revenue notifications from enrollments (paid courses)
        if ($filter === 'all' || $filter === 'revenue') {
            $revenue_query = "SELECT e.enrollment_id, e.enrollment_date, e.price_paid,
                                    u.first_name, u.last_name, 
                                    c.title as course_title, c.price, c.course_id
                             FROM enrollments e
                             INNER JOIN courses c ON e.course_id = c.course_id
                             INNER JOIN users u ON e.student_id = u.user_id
                             WHERE c.instructor_id = '$instructor_id' 
                             AND (e.price_paid > 0 OR (c.price > 0 AND c.is_free = 0))
                             ORDER BY e.enrollment_date DESC
                             LIMIT $limit";
            $revenue_result = mysqli_query($con, $revenue_query);
            if ($revenue_result) {
                while ($row = mysqli_fetch_assoc($revenue_result)) {
                    $notif_id = 'revenue_' . $row['enrollment_id'];
                    
                    // Skip if deleted
                    if (isNotificationDeleted($notif_id, $instructor_id, $con)) {
                        continue;
                    }
                    $exists = false;
                    foreach ($notifications as $n) {
                        if ($n['id'] == $notif_id) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $student_name = trim($row['first_name'] . ' ' . $row['last_name']);
                        $amount = $row['price_paid'] > 0 ? $row['price_paid'] : $row['price'];
                        $notifications[] = [
                            'id' => $notif_id,
                            'type' => 'revenue',
                            'icon' => 'attach_money',
                            'color' => '#2196F3',
                            'title' => 'New Sale',
                            'message' => $student_name . ' purchased "' . $row['course_title'] . '" for ৳' . number_format($amount, 2),
                            'time' => $row['enrollment_date'],
                            'time_ago' => timeAgo($row['enrollment_date']),
                            'is_read' => isNotificationRead($notif_id, $instructor_id, $con),
                            'link' => 'course.php?id=' . $row['course_id']
                        ];
                    }
                }
            }
        }
        
        // Sort all notifications by time descending
        usort($notifications, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
        
        // Apply unread filter
        if ($filter === 'unread') {
            $notifications = array_filter($notifications, function($n) {
                return !$n['is_read'];
            });
            $notifications = array_values($notifications);
        }
        
        // Limit total and remove duplicates
        $seen_ids = [];
        $unique_notifications = [];
        foreach ($notifications as $n) {
            if (!in_array($n['id'], $seen_ids)) {
                $seen_ids[] = $n['id'];
                $unique_notifications[] = $n;
            }
        }
        $notifications = array_slice($unique_notifications, 0, $limit);
        
        // Count unread
        $unread_count = 0;
        foreach ($notifications as $n) {
            if (!$n['is_read']) $unread_count++;
        }
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]);
        break;
        
    case 'mark_read':
        $notification_id = isset($_POST['notification_id']) ? mysqli_real_escape_string($con, $_POST['notification_id']) : '';
        
        if (empty($notification_id)) {
            echo json_encode(['success' => false, 'error' => 'Notification ID required']);
            exit;
        }
        
        // Check if it's a numeric ID (from notifications table)
        if (is_numeric($notification_id)) {
            // Update the notifications table directly
            $update_query = "UPDATE notifications SET is_read = 1, read_at = NOW() 
                            WHERE notification_id = '$notification_id' AND user_id = '$instructor_id'";
            mysqli_query($con, $update_query);
        }
        
        // Also track in notification_reads table for dynamic notifications
        $create_table = "CREATE TABLE IF NOT EXISTS notification_reads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_id VARCHAR(100) NOT NULL,
            user_id VARCHAR(50) NOT NULL,
            read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_read (notification_id, user_id)
        )";
        mysqli_query($con, $create_table);
        
        $insert_query = "INSERT IGNORE INTO notification_reads (notification_id, user_id) 
                        VALUES ('$notification_id', '$instructor_id')";
        
        if (mysqli_query($con, $insert_query)) {
            echo json_encode(['success' => true, 'message' => 'Marked as read']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to mark as read']);
        }
        break;
        
    case 'mark_all_read':
        // Create table if not exists
        $create_table = "CREATE TABLE IF NOT EXISTS notification_reads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_id VARCHAR(100) NOT NULL,
            user_id VARCHAR(50) NOT NULL,
            read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_read (notification_id, user_id)
        )";
        mysqli_query($con, $create_table);
        
        // Mark all in notifications table as read
        mysqli_query($con, "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = '$instructor_id' AND is_read = 0");
        
        // Get all notification IDs and mark them as read
        $ids = $_POST['notification_ids'] ?? '';
        if (!empty($ids)) {
            $id_list = explode(',', $ids);
            foreach ($id_list as $nid) {
                $nid = mysqli_real_escape_string($con, trim($nid));
                mysqli_query($con, "INSERT IGNORE INTO notification_reads (notification_id, user_id) VALUES ('$nid', '$instructor_id')");
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'All marked as read']);
        break;
    
    case 'delete':
        $notification_id = isset($_POST['notification_id']) ? mysqli_real_escape_string($con, $_POST['notification_id']) : '';
        
        if (empty($notification_id)) {
            echo json_encode(['success' => false, 'error' => 'Notification ID required']);
            exit;
        }
        
        // Check if it's a numeric ID (from notifications table)
        if (is_numeric($notification_id)) {
            // Delete from notifications table
            $delete_query = "DELETE FROM notifications WHERE notification_id = '$notification_id' AND user_id = '$instructor_id'";
            mysqli_query($con, $delete_query);
        }
        
        // Also mark as deleted in notification_reads (we'll use a separate table for deletions)
        $create_deleted_table = "CREATE TABLE IF NOT EXISTS notification_deletions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_id VARCHAR(100) NOT NULL,
            user_id VARCHAR(50) NOT NULL,
            deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_deletion (notification_id, user_id)
        )";
        mysqli_query($con, $create_deleted_table);
        
        $insert_deletion = "INSERT IGNORE INTO notification_deletions (notification_id, user_id) 
                           VALUES ('$notification_id', '$instructor_id')";
        
        if (mysqli_query($con, $insert_deletion)) {
            echo json_encode(['success' => true, 'message' => 'Notification deleted']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete notification']);
        }
        break;
    
    case 'clear_all':
        // Delete all notifications for this user from notifications table
        mysqli_query($con, "DELETE FROM notifications WHERE user_id = '$instructor_id'");
        
        // Create deletions table if not exists
        $create_deleted_table = "CREATE TABLE IF NOT EXISTS notification_deletions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_id VARCHAR(100) NOT NULL,
            user_id VARCHAR(50) NOT NULL,
            deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_deletion (notification_id, user_id)
        )";
        mysqli_query($con, $create_deleted_table);
        
        // Mark all dynamic notifications as deleted
        $ids = $_POST['notification_ids'] ?? '';
        if (!empty($ids)) {
            $id_list = explode(',', $ids);
            foreach ($id_list as $nid) {
                $nid = mysqli_real_escape_string($con, trim($nid));
                mysqli_query($con, "INSERT IGNORE INTO notification_deletions (notification_id, user_id) VALUES ('$nid', '$instructor_id')");
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'All notifications cleared']);
        break;
        
    case 'get_unread_count':
        // This is a quick count for the nav badge
        $count = 0;
        
        // Count recent unread enrollments (last 7 days)
        $week_ago = date('Y-m-d', strtotime('-7 days'));
        $query = "SELECT COUNT(*) as cnt FROM enrollments e 
                  INNER JOIN courses c ON e.course_id = c.course_id 
                  WHERE c.instructor_id = '$instructor_id' 
                  AND e.enrollment_date >= '$week_ago'
                  AND NOT EXISTS (
                      SELECT 1 FROM notification_reads nr 
                      WHERE nr.notification_id = CONCAT('enroll_', e.enrollment_id) 
                      AND nr.user_id = '$instructor_id'
                  )";
        $result = mysqli_query($con, $query);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $count += $row['cnt'];
        }
        
        // Count recent unread reviews
        $query = "SELECT COUNT(*) as cnt FROM reviews r 
                  INNER JOIN courses c ON r.course_instructor_id = c.course_id 
                  WHERE c.instructor_id = '$instructor_id' 
                  AND r.created_at >= '$week_ago'
                  AND NOT EXISTS (
                      SELECT 1 FROM notification_reads nr 
                      WHERE nr.notification_id = CONCAT('review_', r.review_id) 
                      AND nr.user_id = '$instructor_id'
                  )";
        $result = mysqli_query($con, $query);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $count += $row['cnt'];
        }
        
        echo json_encode(['success' => true, 'unread_count' => $count]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

// Helper function for time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}

// Check if notification is read
function isNotificationRead($notification_id, $user_id, $con) {
    $notification_id = mysqli_real_escape_string($con, $notification_id);
    $query = "SELECT 1 FROM notification_reads WHERE notification_id = '$notification_id' AND user_id = '$user_id' LIMIT 1";
    $result = mysqli_query($con, $query);
    return $result && mysqli_num_rows($result) > 0;
}

// Check if notification is deleted
function isNotificationDeleted($notification_id, $user_id, $con) {
    $notification_id = mysqli_real_escape_string($con, $notification_id);
    $query = "SELECT 1 FROM notification_deletions WHERE notification_id = '$notification_id' AND user_id = '$user_id' LIMIT 1";
    $result = mysqli_query($con, $query);
    return $result && mysqli_num_rows($result) > 0;
}
?>
