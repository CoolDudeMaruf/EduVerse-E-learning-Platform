<?php
require_once '../../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!$is_logged_in || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_coupons':
        getCoupons($con);
        break;
    case 'get_coupon':
        getCoupon($con);
        break;
    case 'add_coupon':
        addCoupon($con);
        break;
    case 'update_coupon':
        updateCoupon($con);
        break;
    case 'toggle_coupon':
        toggleCoupon($con);
        break;
    case 'delete_coupon':
        deleteCoupon($con);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getCoupons($con) {
    $query = "SELECT c.*, 
              (SELECT COUNT(*) FROM coupon_usage cu WHERE cu.coupon_id = c.coupon_id) as actual_usage
              FROM coupons c 
              ORDER BY c.created_at DESC";
    $result = mysqli_query($con, $query);
    
    $coupons = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $coupons[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'coupons' => $coupons
    ]);
}

function getCoupon($con) {
    $coupon_id = intval($_GET['coupon_id'] ?? 0);
    
    if (!$coupon_id) {
        echo json_encode(['success' => false, 'message' => 'Coupon ID required']);
        return;
    }
    
    $query = "SELECT * FROM coupons WHERE coupon_id = $coupon_id";
    $result = mysqli_query($con, $query);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'coupon' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Coupon not found']);
    }
}

function addCoupon($con) {
    $code = strtoupper(mysqli_real_escape_string($con, $_POST['code'] ?? ''));
    $description = mysqli_real_escape_string($con, $_POST['description'] ?? '');
    $discount_type = mysqli_real_escape_string($con, $_POST['discount_type'] ?? 'percentage');
    $discount_value = floatval($_POST['discount_value'] ?? 0);
    $min_purchase = floatval($_POST['min_purchase'] ?? 0);
    $max_discount = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : 'NULL';
    $valid_from = mysqli_real_escape_string($con, $_POST['valid_from'] ?? date('Y-m-d'));
    $valid_until = mysqli_real_escape_string($con, $_POST['valid_until'] ?? date('Y-m-d', strtotime('+30 days')));
    $usage_limit = intval($_POST['usage_limit'] ?? 0);
    $per_user_limit = intval($_POST['per_user_limit'] ?? 1);
    $is_active = intval($_POST['is_active'] ?? 1);
    $created_by = $_SESSION['user_id'];
    
    if (!$code || $discount_value <= 0) {
        echo json_encode(['success' => false, 'message' => 'Code and discount value are required']);
        return;
    }
    
    // Check code uniqueness
    $check = mysqli_query($con, "SELECT coupon_id FROM coupons WHERE code = '$code'");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(['success' => false, 'message' => 'Coupon code already exists']);
        return;
    }
    
    // Validate percentage
    if ($discount_type === 'percentage' && $discount_value > 100) {
        echo json_encode(['success' => false, 'message' => 'Percentage cannot exceed 100']);
        return;
    }
    
    $max_discount_sql = $max_discount === 'NULL' ? 'NULL' : $max_discount;
    $usage_limit_sql = $usage_limit === 0 ? 'NULL' : $usage_limit;
    
    $query = "INSERT INTO coupons (code, description, discount_type, discount_value, min_purchase_amount, 
              max_discount_amount, valid_from, valid_until, usage_limit, per_user_limit, is_active, created_by, created_at)
              VALUES ('$code', '$description', '$discount_type', $discount_value, $min_purchase, 
              $max_discount_sql, '$valid_from', '$valid_until', $usage_limit_sql, $per_user_limit, $is_active, '$created_by', NOW())";
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => 'Coupon created successfully', 'coupon_id' => mysqli_insert_id($con)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create coupon: ' . mysqli_error($con)]);
    }
}

function updateCoupon($con) {
    $coupon_id = intval($_POST['coupon_id'] ?? 0);
    $code = strtoupper(mysqli_real_escape_string($con, $_POST['code'] ?? ''));
    $description = mysqli_real_escape_string($con, $_POST['description'] ?? '');
    $discount_type = mysqli_real_escape_string($con, $_POST['discount_type'] ?? 'percentage');
    $discount_value = floatval($_POST['discount_value'] ?? 0);
    $min_purchase = floatval($_POST['min_purchase'] ?? 0);
    $max_discount = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : 'NULL';
    $valid_from = mysqli_real_escape_string($con, $_POST['valid_from'] ?? '');
    $valid_until = mysqli_real_escape_string($con, $_POST['valid_until'] ?? '');
    $usage_limit = intval($_POST['usage_limit'] ?? 0);
    $per_user_limit = intval($_POST['per_user_limit'] ?? 1);
    $is_active = intval($_POST['is_active'] ?? 1);
    
    if (!$coupon_id || !$code || $discount_value <= 0) {
        echo json_encode(['success' => false, 'message' => 'Coupon ID, code and discount value are required']);
        return;
    }
    
    // Check code uniqueness
    $check = mysqli_query($con, "SELECT coupon_id FROM coupons WHERE code = '$code' AND coupon_id != $coupon_id");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(['success' => false, 'message' => 'Coupon code already exists']);
        return;
    }
    
    $max_discount_sql = $max_discount === 'NULL' ? 'NULL' : $max_discount;
    $usage_limit_sql = $usage_limit === 0 ? 'NULL' : $usage_limit;
    
    $query = "UPDATE coupons SET 
              code = '$code',
              description = '$description',
              discount_type = '$discount_type',
              discount_value = $discount_value,
              min_purchase_amount = $min_purchase,
              max_discount_amount = $max_discount_sql,
              valid_from = '$valid_from',
              valid_until = '$valid_until',
              usage_limit = $usage_limit_sql,
              per_user_limit = $per_user_limit,
              is_active = $is_active,
              updated_at = NOW()
              WHERE coupon_id = $coupon_id";
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => 'Coupon updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update coupon: ' . mysqli_error($con)]);
    }
}

function toggleCoupon($con) {
    $coupon_id = intval($_POST['coupon_id'] ?? 0);
    $is_active = intval($_POST['is_active'] ?? 0);
    
    if (!$coupon_id) {
        echo json_encode(['success' => false, 'message' => 'Coupon ID required']);
        return;
    }
    
    $query = "UPDATE coupons SET is_active = $is_active, updated_at = NOW() WHERE coupon_id = $coupon_id";
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => $is_active ? 'Coupon activated' : 'Coupon deactivated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update coupon']);
    }
}

function deleteCoupon($con) {
    $coupon_id = intval($_POST['coupon_id'] ?? 0);
    
    if (!$coupon_id) {
        echo json_encode(['success' => false, 'message' => 'Coupon ID required']);
        return;
    }
    
    $query = "DELETE FROM coupons WHERE coupon_id = $coupon_id";
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => 'Coupon deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete coupon']);
    }
}
?>
