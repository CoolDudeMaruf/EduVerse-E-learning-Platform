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
    case 'create':
        $code = isset($_POST['code']) ? strtoupper(mysqli_real_escape_string($con, trim($_POST['code']))) : '';
        $description = isset($_POST['description']) ? mysqli_real_escape_string($con, $_POST['description']) : '';
        $discount_type = isset($_POST['discount_type']) ? mysqli_real_escape_string($con, $_POST['discount_type']) : 'percentage';
        $discount_value = isset($_POST['discount_value']) ? floatval($_POST['discount_value']) : 0;
        $min_purchase_amount = isset($_POST['min_purchase_amount']) ? floatval($_POST['min_purchase_amount']) : 0;
        
        // Date handling - accept both start_date/end_date (new form) and valid_from/expires_at (old form)
        $valid_from = isset($_POST['start_date']) && !empty($_POST['start_date']) 
            ? mysqli_real_escape_string($con, $_POST['start_date']) 
            : (isset($_POST['valid_from']) && !empty($_POST['valid_from']) 
                ? mysqli_real_escape_string($con, $_POST['valid_from']) 
                : date('Y-m-d'));
                
        $valid_until = isset($_POST['end_date']) && !empty($_POST['end_date']) 
            ? mysqli_real_escape_string($con, $_POST['end_date']) 
            : (isset($_POST['expires_at']) && !empty($_POST['expires_at']) 
                ? mysqli_real_escape_string($con, $_POST['expires_at']) 
                : date('Y-m-d', strtotime('+1 year')));
        
        // Usage limit - accept both usage_limit (new form) and max_uses (old form)
        $usage_limit = isset($_POST['usage_limit']) && $_POST['usage_limit'] !== '' 
            ? (int)$_POST['usage_limit'] 
            : (isset($_POST['max_uses']) && $_POST['max_uses'] !== '' 
                ? (int)$_POST['max_uses'] 
                : 0);
                
        $per_user_limit = isset($_POST['per_user_limit']) ? (int)$_POST['per_user_limit'] : 1;
        
        // Course/applicable handling - check coupon_type field
        $coupon_type = isset($_POST['coupon_type']) ? mysqli_real_escape_string($con, $_POST['coupon_type']) : 'overall';
        $course_id = isset($_POST['course_id']) && !empty($_POST['course_id']) ? $_POST['course_id'] : null;
        
        // Determine applicable_to based on coupon_type
        if ($coupon_type === 'specific' && $course_id) {
            $applicable_to = 'specific_courses';
            $applicable_ids = json_encode([$course_id]);
        } else {
            $applicable_to = 'all_courses';
            $applicable_ids = null;
        }
        
        $is_active = 1;
        
        if (empty($code)) {
            echo json_encode(['success' => false, 'error' => 'Coupon code is required']);
            exit;
        }
        
        if ($discount_value <= 0) {
            echo json_encode(['success' => false, 'error' => 'Discount value must be greater than 0']);
            exit;
        }
        
        if ($discount_type === 'percentage' && $discount_value > 100) {
            echo json_encode(['success' => false, 'error' => 'Percentage discount cannot exceed 100%']);
            exit;
        }
        
        // Validate dates
        if (strtotime($valid_from) > strtotime($valid_until)) {
            echo json_encode(['success' => false, 'error' => 'Start date must be before end date']);
            exit;
        }
        
        // Check if code already exists
        $check_query = "SELECT coupon_id FROM coupons WHERE code = '$code'";
        $check_result = mysqli_query($con, $check_query);
        if ($check_result && mysqli_num_rows($check_result) > 0) {
            echo json_encode(['success' => false, 'error' => 'Coupon code already exists']);
            exit;
        }
        
        $applicable_ids_sql = $applicable_ids ? "'" . mysqli_real_escape_string($con, $applicable_ids) . "'" : "NULL";
        
        $insert_query = "INSERT INTO coupons (code, description, discount_type, discount_value, 
                                              min_purchase_amount, valid_from, valid_until,
                                              usage_limit, per_user_limit, applicable_to, applicable_ids, 
                                              is_active, created_by, created_at)
                        VALUES ('$code', '$description', '$discount_type', $discount_value, 
                                $min_purchase_amount, '$valid_from', '$valid_until',
                                $usage_limit, $per_user_limit, '$applicable_to', $applicable_ids_sql,
                                $is_active, '$instructor_id', NOW())";
        
        if (mysqli_query($con, $insert_query)) {
            $new_id = mysqli_insert_id($con);
            echo json_encode([
                'success' => true, 
                'message' => 'Coupon created successfully',
                'coupon_id' => $new_id
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create coupon: ' . mysqli_error($con)]);
        }
        break;
        
    case 'list':
        $type_filter = isset($_GET['type']) ? mysqli_real_escape_string($con, $_GET['type']) : 'all';
        $status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : 'all';
        $search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
        
        // Filter by instructor's coupons
        $where_conditions = ["(created_by = '$instructor_id' OR created_by IS NULL)"];
        
        if ($type_filter === 'overall') {
            $where_conditions[] = "applicable_to = 'all_courses'";
        } elseif ($type_filter === 'specific') {
            $where_conditions[] = "applicable_to = 'specific_courses'";
        }
        
        if ($status_filter === 'active') {
            $where_conditions[] = "is_active = 1 AND valid_until >= CURDATE()";
        } elseif ($status_filter === 'expired') {
            $where_conditions[] = "valid_until < CURDATE()";
        } elseif ($status_filter === 'disabled') {
            $where_conditions[] = "is_active = 0";
        }
        
        if (!empty($search)) {
            $where_conditions[] = "code LIKE '%$search%'";
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT * FROM coupons WHERE $where_clause ORDER BY created_at DESC";
        
        $result = mysqli_query($con, $query);
        $coupons = [];
        $stats = [
            'total' => 0,
            'active' => 0,
            'total_uses' => 0,
            'total_discount' => 0
        ];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $is_expired = strtotime($row['valid_until']) < time();
                $status = $is_expired ? 'expired' : ($row['is_active'] ? 'active' : 'disabled');
                
                // Get course title if specific course
                $course_title = 'All Courses';
                if ($row['applicable_to'] === 'specific_courses' && $row['applicable_ids']) {
                    $applicable = json_decode($row['applicable_ids'], true);
                    if (!empty($applicable)) {
                        $course_id = mysqli_real_escape_string($con, $applicable[0]);
                        $course_query = mysqli_query($con, "SELECT title FROM courses WHERE course_id = '$course_id'");
                        if ($course_query && $course_row = mysqli_fetch_assoc($course_query)) {
                            $course_title = $course_row['title'];
                        }
                    }
                }
                
                $coupons[] = [
                    'coupon_id' => $row['coupon_id'],
                    'code' => $row['code'],
                    'description' => $row['description'],
                    'discount_type' => $row['discount_type'],
                    'discount_value' => (float)$row['discount_value'],
                    'max_discount_amount' => $row['max_discount_amount'] ? (float)$row['max_discount_amount'] : null,
                    'min_purchase_amount' => (float)$row['min_purchase_amount'],
                    'course_title' => $course_title,
                    'applicable_to' => $row['applicable_to'],
                    'max_uses' => (int)$row['usage_limit'],
                    'times_used' => (int)$row['usage_count'],
                    'expires_at' => $row['valid_until'],
                    'valid_from' => $row['valid_from'],
                    'valid_until' => $row['valid_until'],
                    'is_active' => (bool)$row['is_active'],
                    'status' => $status,
                    'created_at' => $row['created_at']
                ];
                
                $stats['total']++;
                if ($status === 'active') {
                    $stats['active']++;
                }
                $stats['total_uses'] += (int)$row['usage_count'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'coupons' => $coupons,
            'stats' => $stats
        ]);
        break;
        
    case 'update':
        $coupon_id = isset($_POST['coupon_id']) ? (int)$_POST['coupon_id'] : 0;
        
        if ($coupon_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Coupon ID is required']);
            exit;
        }
        
        $check_query = "SELECT coupon_id FROM coupons WHERE coupon_id = $coupon_id";
        $check_result = mysqli_query($con, $check_query);
        if (!$check_result || mysqli_num_rows($check_result) == 0) {
            echo json_encode(['success' => false, 'error' => 'Coupon not found']);
            exit;
        }
        
        $updates = [];
        
        if (isset($_POST['discount_type'])) {
            $discount_type = mysqli_real_escape_string($con, $_POST['discount_type']);
            $updates[] = "discount_type = '$discount_type'";
        }
        
        if (isset($_POST['discount_value'])) {
            $discount_value = floatval($_POST['discount_value']);
            $updates[] = "discount_value = $discount_value";
        }
        
        if (isset($_POST['usage_limit'])) {
            $usage_limit = (int)$_POST['usage_limit'];
            $updates[] = "usage_limit = $usage_limit";
        }
        
        if (isset($_POST['valid_until'])) {
            $valid_until = mysqli_real_escape_string($con, $_POST['valid_until']);
            $updates[] = "valid_until = '$valid_until'";
        }
        
        if (isset($_POST['is_active'])) {
            $is_active = (int)$_POST['is_active'];
            $updates[] = "is_active = $is_active";
        }
        
        if (empty($updates)) {
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            exit;
        }
        
        $update_query = "UPDATE coupons SET " . implode(', ', $updates) . " WHERE coupon_id = $coupon_id";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Coupon updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update coupon']);
        }
        break;
        
    case 'delete':
        $coupon_id = isset($_POST['coupon_id']) ? (int)$_POST['coupon_id'] : 0;
        
        if ($coupon_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Coupon ID is required']);
            exit;
        }
        
        $delete_query = "DELETE FROM coupons WHERE coupon_id = $coupon_id";
        
        if (mysqli_query($con, $delete_query)) {
            echo json_encode(['success' => true, 'message' => 'Coupon deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete coupon']);
        }
        break;
        
    case 'toggle':
        $coupon_id = isset($_POST['coupon_id']) ? (int)$_POST['coupon_id'] : 0;
        
        if ($coupon_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Coupon ID is required']);
            exit;
        }
        
        $toggle_query = "UPDATE coupons SET is_active = NOT is_active WHERE coupon_id = $coupon_id";
        
        if (mysqli_query($con, $toggle_query) && mysqli_affected_rows($con) > 0) {
            echo json_encode(['success' => true, 'message' => 'Coupon status toggled']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to toggle coupon status']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
