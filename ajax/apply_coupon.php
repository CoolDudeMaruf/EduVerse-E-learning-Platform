<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to apply coupon']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$code = strtoupper(trim($_POST['code'] ?? ''));
$total = floatval($_POST['total'] ?? 0);

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a coupon code']);
    exit;
}

if ($total <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart total']);
    exit;
}

// Get coupon details
$code_escaped = mysqli_real_escape_string($con, $code);
$query = "SELECT * FROM coupons 
          WHERE code = '$code_escaped' 
          AND is_active = 1 
          AND valid_from <= CURDATE() 
          AND valid_until >= CURDATE()
          LIMIT 1";

$result = mysqli_query($con, $query);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon code']);
    exit;
}

$coupon = mysqli_fetch_assoc($result);

// Check usage limit
if ($coupon['usage_limit'] > 0 && $coupon['usage_count'] >= $coupon['usage_limit']) {
    echo json_encode(['success' => false, 'message' => 'This coupon has reached its usage limit']);
    exit;
}

// Check per user limit
$user_usage_query = "SELECT COUNT(*) as usage_count 
                     FROM coupon_usage 
                     WHERE coupon_id = '{$coupon['coupon_id']}' 
                     AND user_id = '$user_id'";
$user_usage_result = mysqli_query($con, $user_usage_query);
$user_usage = mysqli_fetch_assoc($user_usage_result);

if ($user_usage['usage_count'] >= $coupon['per_user_limit']) {
    echo json_encode(['success' => false, 'message' => 'You have already used this coupon the maximum number of times']);
    exit;
}

// Check minimum purchase amount
if ($total < $coupon['min_purchase_amount']) {
    echo json_encode([
        'success' => false, 
        'message' => 'Minimum purchase amount of $' . number_format($coupon['min_purchase_amount'], 2) . ' required'
    ]);
    exit;
}

// Check if coupon is applicable to cart items
if ($coupon['applicable_to'] !== 'all_courses') {
    $cart_query = "SELECT c.course_id, c.category_id, c.instructor_id 
                   FROM shopping_carts sc
                   JOIN courses c ON sc.course_id = c.course_id
                   WHERE sc.user_id = '$user_id' AND c.status = 'published'";
    
    $cart_result = mysqli_query($con, $cart_query);
    $cart_items = [];
    
    while ($item = mysqli_fetch_assoc($cart_result)) {
        $cart_items[] = $item;
    }
    
    if (empty($cart_items)) {
        echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
        exit;
    }
    
    $applicable_ids = json_decode($coupon['applicable_ids'], true) ?? [];
    $is_applicable = false;
    
    foreach ($cart_items as $item) {
        if ($coupon['applicable_to'] === 'specific_courses' && in_array($item['course_id'], $applicable_ids)) {
            $is_applicable = true;
            break;
        } elseif ($coupon['applicable_to'] === 'categories' && in_array($item['category_id'], $applicable_ids)) {
            $is_applicable = true;
            break;
        } elseif ($coupon['applicable_to'] === 'instructors' && in_array($item['instructor_id'], $applicable_ids)) {
            $is_applicable = true;
            break;
        }
    }
    
    if (!$is_applicable) {
        echo json_encode(['success' => false, 'message' => 'This coupon is not applicable to the items in your cart']);
        exit;
    }
}

// Calculate discount
$discount = 0;
if ($coupon['discount_type'] === 'percentage') {
    $discount = ($total * $coupon['discount_value']) / 100;
    
    // Apply max discount limit if set
    if ($coupon['max_discount_amount'] > 0 && $discount > $coupon['max_discount_amount']) {
        $discount = $coupon['max_discount_amount'];
    }
} else {
    // Fixed discount
    $discount = $coupon['discount_value'];
}

// Ensure discount doesn't exceed total
if ($discount > $total) {
    $discount = $total;
}

// Store coupon in session for checkout
$_SESSION['applied_coupon'] = [
    'coupon_id' => $coupon['coupon_id'],
    'code' => $coupon['code'],
    'discount' => $discount,
    'applied_at' => time()
];

echo json_encode([
    'success' => true,
    'message' => 'Coupon applied successfully!',
    'discount' => $discount,
    'new_total' => $total - $discount,
    'coupon_code' => $coupon['code']
]);
