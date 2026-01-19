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
    case 'get_earnings':
        // Total earnings (all time) - using enrollments table with course prices
        $total_query = "SELECT COALESCE(SUM(CASE WHEN e.price_paid > 0 THEN e.price_paid ELSE c.price END), 0) as total 
                       FROM enrollments e 
                       INNER JOIN courses c ON e.course_id = c.course_id
                       WHERE c.instructor_id = '$instructor_id' 
                       AND c.is_free = 0 AND c.price > 0";
        $total_result = mysqli_query($con, $total_query);
        $total_earnings = 0;
        if ($total_result) {
            $row = mysqli_fetch_assoc($total_result);
            $total_earnings = $row['total'] ?? 0;
        }
        
        // This month's earnings - using enrollments
        $month_start = date('Y-m-01');
        $month_query = "SELECT COALESCE(SUM(CASE WHEN e.price_paid > 0 THEN e.price_paid ELSE c.price END), 0) as total 
                       FROM enrollments e 
                       INNER JOIN courses c ON e.course_id = c.course_id
                       WHERE c.instructor_id = '$instructor_id' 
                       AND c.is_free = 0 AND c.price > 0
                       AND e.enrollment_date >= '$month_start'";
        $month_result = mysqli_query($con, $month_query);
        $month_earnings = 0;
        if ($month_result) {
            $row = mysqli_fetch_assoc($month_result);
            $month_earnings = $row['total'] ?? 0;
        }
        
        // Last month's earnings for comparison
        $last_month_start = date('Y-m-01', strtotime('-1 month'));
        $last_month_end = date('Y-m-t', strtotime('-1 month'));
        $last_month_query = "SELECT COALESCE(SUM(CASE WHEN e.price_paid > 0 THEN e.price_paid ELSE c.price END), 0) as total 
                            FROM enrollments e 
                            INNER JOIN courses c ON e.course_id = c.course_id
                            WHERE c.instructor_id = '$instructor_id' 
                            AND c.is_free = 0 AND c.price > 0
                            AND e.enrollment_date BETWEEN '$last_month_start' AND '$last_month_end 23:59:59'";
        $last_month_result = mysqli_query($con, $last_month_query);
        $last_month_earnings = 0;
        if ($last_month_result) {
            $row = mysqli_fetch_assoc($last_month_result);
            $last_month_earnings = $row['total'] ?? 0;
        }
        
        // Calculate percentage change
        $percentage_change = 0;
        if ($last_month_earnings > 0) {
            $percentage_change = (($month_earnings - $last_month_earnings) / $last_month_earnings) * 100;
        } elseif ($month_earnings > 0) {
            $percentage_change = 100;
        }
        
        // Pending payout (simplified - earnings not yet paid out)
        $pending_payout = $month_earnings * 0.7; // Assuming 70% payout after platform fee
        
        // Next payout date (15th or end of month)
        $today = date('j');
        if ($today < 15) {
            $next_payout = date('M j, Y', strtotime(date('Y-m-15')));
        } else {
            $next_payout = date('M j, Y', strtotime(date('Y-m-01', strtotime('+1 month'))));
        }
        
        echo json_encode([
            'success' => true,
            'earnings' => [
                'total' => (float)$total_earnings,
                'this_month' => (float)$month_earnings,
                'last_month' => (float)$last_month_earnings,
                'percentage_change' => round($percentage_change, 1),
                'pending_payout' => (float)$pending_payout,
                'next_payout_date' => $next_payout
            ]
        ]);
        break;
        
    case 'get_revenue_chart':
        $months = isset($_GET['months']) ? min(12, max(3, (int)$_GET['months'])) : 12;
        $data = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $month_start = date('Y-m-01', strtotime("-$i months"));
            $month_end = date('Y-m-t', strtotime("-$i months"));
            $month_label = date('M Y', strtotime("-$i months"));
            
            // Using enrollments table with course prices
            $query = "SELECT COALESCE(SUM(CASE WHEN e.price_paid > 0 THEN e.price_paid ELSE c.price END), 0) as total 
                     FROM enrollments e 
                     INNER JOIN courses c ON e.course_id = c.course_id
                     WHERE c.instructor_id = '$instructor_id' 
                     AND c.is_free = 0 AND c.price > 0
                     AND e.enrollment_date BETWEEN '$month_start' AND '$month_end 23:59:59'";
            $result = mysqli_query($con, $query);
            $total = 0;
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $total = $row['total'] ?? 0;
            }
            $data[] = ['label' => $month_label, 'value' => (float)$total];
        }
        
        echo json_encode(['success' => true, 'chart_data' => $data]);
        break;
        
    case 'get_course_revenue':
        // Revenue breakdown by course (using enrollments with course prices)
        $query = "SELECT c.course_id, c.title, c.price, c.status, c.is_free,
                        COUNT(e.enrollment_id) as total_sales,
                        COALESCE(SUM(CASE WHEN e.price_paid > 0 THEN e.price_paid ELSE c.price END), 0) as total_revenue
                 FROM courses c
                 LEFT JOIN enrollments e ON c.course_id = e.course_id
                 WHERE c.instructor_id = '$instructor_id'
                 GROUP BY c.course_id
                 ORDER BY total_revenue DESC";
        
        $result = mysqli_query($con, $query);
        $courses = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $courses[] = [
                    'course_id' => $row['course_id'],
                    'title' => $row['title'],
                    'price' => (float)$row['price'],
                    'status' => $row['status'],
                    'is_free' => (bool)$row['is_free'],
                    'total_sales' => (int)$row['total_sales'],
                    'total_revenue' => (float)$row['total_revenue']
                ];
            }
        }
        
        echo json_encode(['success' => true, 'courses' => $courses]);
        break;
        
    case 'get_transactions':
        $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = ($page - 1) * $limit;
        
        // Get recent transactions from enrollments (for paid courses)
        $query = "SELECT e.enrollment_id, 
                        CASE WHEN e.price_paid > 0 THEN e.price_paid ELSE c.price END as amount, 
                        e.enrollment_date as payment_date, 
                        'completed' as payment_status,
                        u.first_name, u.last_name, u.email,
                        c.title as course_title
                 FROM enrollments e
                 INNER JOIN courses c ON e.course_id = c.course_id
                 INNER JOIN users u ON e.student_id = u.user_id
                 WHERE c.instructor_id = '$instructor_id'
                 AND c.is_free = 0 AND c.price > 0
                 ORDER BY e.enrollment_date DESC
                 LIMIT $limit OFFSET $offset";
        
        $result = mysqli_query($con, $query);
        $transactions = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $transactions[] = [
                    'payment_id' => 'ENR_' . $row['enrollment_id'],
                    'amount' => (float)$row['amount'],
                    'date' => $row['payment_date'],
                    'status' => $row['payment_status'],
                    'student_name' => trim($row['first_name'] . ' ' . $row['last_name']),
                    'student_email' => $row['email'],
                    'course_title' => $row['course_title']
                ];
            }
        }
        
        // Get total count from enrollments
        $count_query = "SELECT COUNT(*) as total FROM enrollments e
                       INNER JOIN courses c ON e.course_id = c.course_id
                       WHERE c.instructor_id = '$instructor_id'
                       AND c.is_free = 0 AND c.price > 0";
        $count_result = mysqli_query($con, $count_query);
        $total = 0;
        if ($count_result) {
            $row = mysqli_fetch_assoc($count_result);
            $total = $row['total'];
        }
        
        echo json_encode([
            'success' => true,
            'transactions' => $transactions,
            'pagination' => [
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
        break;
        
    case 'update_price':
        $course_id = isset($_POST['course_id']) ? mysqli_real_escape_string($con, $_POST['course_id']) : '';
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
        $original_price = isset($_POST['original_price']) ? floatval($_POST['original_price']) : $price;
        $change_reason = isset($_POST['change_reason']) ? mysqli_real_escape_string($con, $_POST['change_reason']) : null;
        
        if (empty($course_id)) {
            echo json_encode(['success' => false, 'error' => 'Course ID required']);
            exit;
        }
        
        if ($price < 0) {
            echo json_encode(['success' => false, 'error' => 'Price cannot be negative']);
            exit;
        }
        
        // Verify ownership and get current prices
        $check_query = "SELECT course_id, price, original_price, is_free FROM courses WHERE course_id = '$course_id' AND instructor_id = '$instructor_id'";
        $check_result = mysqli_query($con, $check_query);
        if (!$check_result || mysqli_num_rows($check_result) == 0) {
            echo json_encode(['success' => false, 'error' => 'Course not found']);
            exit;
        }
        
        $current_course = mysqli_fetch_assoc($check_result);
        $old_price = $current_course['price'];
        $old_original_price = $current_course['original_price'];
        $old_is_free = $current_course['is_free'];
        $new_is_free = ($price == 0) ? 1 : 0;
        
        // Only record history if price actually changed
        if ($old_price != $price || $old_original_price != $original_price || $old_is_free != $new_is_free) {
            // Create price_history table if it doesn't exist
            $create_table = "CREATE TABLE IF NOT EXISTS `price_history` (
                `history_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `course_id` varchar(20) NOT NULL,
                `old_price` decimal(8,2) UNSIGNED NOT NULL DEFAULT 0.00,
                `new_price` decimal(8,2) UNSIGNED NOT NULL DEFAULT 0.00,
                `old_original_price` decimal(8,2) UNSIGNED DEFAULT NULL,
                `new_original_price` decimal(8,2) UNSIGNED DEFAULT NULL,
                `old_is_free` tinyint(1) NOT NULL DEFAULT 0,
                `new_is_free` tinyint(1) NOT NULL DEFAULT 0,
                `change_reason` varchar(255) DEFAULT NULL,
                `changed_by` varchar(255) NOT NULL,
                `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`history_id`),
                KEY `idx_course_id` (`course_id`),
                KEY `idx_changed_at` (`changed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            mysqli_query($con, $create_table);
            
            // Record price change in history
            $reason_sql = $change_reason ? "'$change_reason'" : "NULL";
            $old_orig_sql = $old_original_price !== null ? $old_original_price : "NULL";
            $new_orig_sql = $original_price > 0 ? $original_price : "NULL";
            
            $history_query = "INSERT INTO price_history 
                (course_id, old_price, new_price, old_original_price, new_original_price, old_is_free, new_is_free, change_reason, changed_by)
                VALUES ('$course_id', $old_price, $price, $old_orig_sql, $new_orig_sql, $old_is_free, $new_is_free, $reason_sql, '$instructor_id')";
            mysqli_query($con, $history_query);
        }
        
        $update_query = "UPDATE courses SET price = $price, original_price = $original_price, is_free = $new_is_free WHERE course_id = '$course_id'";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Price updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update price']);
        }
        break;
    
    case 'get_price_history':
        $course_id = isset($_GET['course_id']) ? mysqli_real_escape_string($con, $_GET['course_id']) : '';
        $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;
        
        if (empty($course_id)) {
            echo json_encode(['success' => false, 'error' => 'Course ID required']);
            exit;
        }
        
        // Verify ownership
        $check_query = "SELECT course_id, title, price, original_price, is_free FROM courses WHERE course_id = '$course_id' AND instructor_id = '$instructor_id'";
        $check_result = mysqli_query($con, $check_query);
        if (!$check_result || mysqli_num_rows($check_result) == 0) {
            echo json_encode(['success' => false, 'error' => 'Course not found']);
            exit;
        }
        
        $course = mysqli_fetch_assoc($check_result);
        
        // Check if table exists
        $table_check = mysqli_query($con, "SHOW TABLES LIKE 'price_history'");
        if (!$table_check || mysqli_num_rows($table_check) == 0) {
            echo json_encode([
                'success' => true,
                'course' => [
                    'course_id' => $course['course_id'],
                    'title' => $course['title'],
                    'current_price' => (float)$course['price'],
                    'original_price' => $course['original_price'] ? (float)$course['original_price'] : null,
                    'is_free' => (bool)$course['is_free']
                ],
                'history' => [],
                'message' => 'No price history available'
            ]);
            exit;
        }
        
        // Get price history
        $history_query = "SELECT ph.*, u.first_name, u.last_name 
                         FROM price_history ph
                         LEFT JOIN users u ON ph.changed_by = u.user_id
                         WHERE ph.course_id = '$course_id'
                         ORDER BY ph.changed_at DESC
                         LIMIT $limit";
        
        $history_result = mysqli_query($con, $history_query);
        $history = [];
        
        if ($history_result) {
            while ($row = mysqli_fetch_assoc($history_result)) {
                $changed_by_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                
                // Determine change type
                $change_type = 'price_change';
                if ($row['old_is_free'] != $row['new_is_free']) {
                    $change_type = $row['new_is_free'] ? 'made_free' : 'made_paid';
                } elseif ($row['new_price'] > $row['old_price']) {
                    $change_type = 'price_increase';
                } elseif ($row['new_price'] < $row['old_price']) {
                    $change_type = 'price_decrease';
                } elseif ($row['old_original_price'] != $row['new_original_price']) {
                    $change_type = 'discount_change';
                }
                
                $history[] = [
                    'history_id' => (int)$row['history_id'],
                    'old_price' => (float)$row['old_price'],
                    'new_price' => (float)$row['new_price'],
                    'old_original_price' => $row['old_original_price'] ? (float)$row['old_original_price'] : null,
                    'new_original_price' => $row['new_original_price'] ? (float)$row['new_original_price'] : null,
                    'old_is_free' => (bool)$row['old_is_free'],
                    'new_is_free' => (bool)$row['new_is_free'],
                    'change_type' => $change_type,
                    'change_reason' => $row['change_reason'],
                    'changed_by' => $changed_by_name ?: 'Unknown',
                    'changed_at' => $row['changed_at']
                ];
            }
        }
        
        // Calculate price statistics
        $stats_query = "SELECT 
                        MIN(new_price) as min_price,
                        MAX(new_price) as max_price,
                        AVG(new_price) as avg_price,
                        COUNT(*) as total_changes
                       FROM price_history
                       WHERE course_id = '$course_id'";
        $stats_result = mysqli_query($con, $stats_query);
        $stats = [
            'min_price' => 0,
            'max_price' => 0,
            'avg_price' => 0,
            'total_changes' => 0
        ];
        
        if ($stats_result) {
            $stats_row = mysqli_fetch_assoc($stats_result);
            $stats = [
                'min_price' => (float)($stats_row['min_price'] ?? 0),
                'max_price' => (float)($stats_row['max_price'] ?? 0),
                'avg_price' => round((float)($stats_row['avg_price'] ?? 0), 2),
                'total_changes' => (int)($stats_row['total_changes'] ?? 0)
            ];
        }
        
        echo json_encode([
            'success' => true,
            'course' => [
                'course_id' => $course['course_id'],
                'title' => $course['title'],
                'current_price' => (float)$course['price'],
                'original_price' => $course['original_price'] ? (float)$course['original_price'] : null,
                'is_free' => (bool)$course['is_free']
            ],
            'history' => $history,
            'stats' => $stats
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
