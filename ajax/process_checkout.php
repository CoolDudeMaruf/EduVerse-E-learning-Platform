<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/notification_functions.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Catch any PHP errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
    exit;
});

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to complete purchase']);
    exit;
}

// Check if user is admin or instructor - they cannot buy courses
$user_role = strtolower($_SESSION['role'] ?? '');
if (in_array($user_role, ['admin', 'super_admin', 'instructor'])) {
    echo json_encode(['success' => false, 'message' => 'Admins and instructors cannot purchase courses']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$payment_method = mysqli_real_escape_string($con, $_POST['payment_method'] ?? 'free');
$total_amount = floatval($_POST['total_amount'] ?? 0);
$discount_amount = floatval($_POST['discount_amount'] ?? 0);

// Get billing information
$first_name = mysqli_real_escape_string($con, $_POST['first_name'] ?? '');
$last_name = mysqli_real_escape_string($con, $_POST['last_name'] ?? '');
$email = mysqli_real_escape_string($con, $_POST['email'] ?? '');
$address = mysqli_real_escape_string($con, $_POST['address'] ?? '');
$city = mysqli_real_escape_string($con, $_POST['city'] ?? '');
$zip_code = mysqli_real_escape_string($con, $_POST['zip_code'] ?? '');
$country = mysqli_real_escape_string($con, $_POST['country'] ?? '');

// Get cart items
$cart_query = "SELECT c.course_id, c.title, c.price, c.is_free, c.currency, c.instructor_id
               FROM shopping_carts sc
               JOIN courses c ON sc.course_id = c.course_id
               WHERE sc.user_id = '$user_id' AND c.status = 'published'";

$cart_result = mysqli_query($con, $cart_query);

if (!$cart_result || mysqli_num_rows($cart_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
    exit;
}

$cart_items = [];
$original_total = 0;

while ($row = mysqli_fetch_assoc($cart_result)) {
    $is_free = (bool)$row['is_free'];
    $price = $is_free ? 0 : (float)$row['price'];
    $row['price_to_pay'] = $price;
    $cart_items[] = $row;
    $original_total += $price;
}

// Start transaction
mysqli_begin_transaction($con);

try {
    // Get coupon information if applied
    $coupon_id_value = null;
    $coupon_code = null;
    if (isset($_SESSION['applied_coupon'])) {
        $coupon_id_value = $_SESSION['applied_coupon']['coupon_id'];
        $coupon_code = mysqli_real_escape_string($con, $_SESSION['applied_coupon']['code']);
    }
    
    // Determine payment gateway
    $payment_gateway = 'NULL';
    if ($total_amount > 0 && $payment_method) {
        if ($payment_method === 'card') {
            $payment_gateway = "'stripe'"; // or your card processor
        } elseif ($payment_method === 'paypal') {
            $payment_gateway = "'paypal'";
        } elseif ($payment_method === 'bank_transfer') {
            $payment_gateway = "'bank_transfer'";
        }
    }
    
    // Get IP and User Agent
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = mysqli_real_escape_string($con, $_SERVER['HTTP_USER_AGENT'] ?? '');
    
    // Enroll user in courses and create transactions
    $enrollment_ids = [];
    $transaction_ids = [];
    foreach ($cart_items as $item) {
        $course_id = $item['course_id'];
        $instructor_id = $item['instructor_id'];
        
        // Check if already enrolled
        $check_enrollment = "SELECT enrollment_id FROM enrollments 
                            WHERE student_id = '$user_id' AND course_id = '$course_id'";
        $check_result = mysqli_query($con, $check_enrollment);
        
        if (mysqli_num_rows($check_result) > 0) {
            continue; // Already enrolled, skip
        }
        
        // Determine enrollment source
        $enrollment_source = $item['price_to_pay'] == 0 ? 'free' : ($coupon_code ? 'promotion' : 'direct');
        $course_currency = $item['currency'] ?? 'BDT';
        $price_paid = $item['price_to_pay'];
        $payment_method_value = $payment_method === 'free' || $price_paid == 0 ? 'NULL' : "'$payment_method'";
        $coupon_code_value = $coupon_code ? "'$coupon_code'" : 'NULL';
        $discount_per_course = $discount_amount > 0 ? round($discount_amount / count($cart_items), 2) : 0;
        
        // Create enrollment without transaction_id first
        $insert_enrollment = "INSERT INTO enrollments 
                             (student_id, course_id, enrollment_date, enrollment_source, 
                              price_paid, currency, payment_method, 
                              coupon_code, discount_amount, status, progress_percentage)
                             VALUES 
                             ('$user_id', '$course_id', NOW(), '$enrollment_source', 
                              '$price_paid', '$course_currency', $payment_method_value, 
                              $coupon_code_value, '$discount_per_course', 'active', 0)";
        
        if (!mysqli_query($con, $insert_enrollment)) {
            $error = mysqli_error($con);
            throw new Exception('Failed to create enrollment for course: ' . $item['title'] . '. Error: ' . $error);
        }
        
        $enrollment_id = mysqli_insert_id($con);
        $enrollment_ids[] = $enrollment_id;
        
        // Create transaction record for this enrollment
        if ($price_paid > 0) {
            $platform_fee_pct = 20.00;
            $platform_fee = round(($price_paid * $platform_fee_pct) / 100, 2);
            $instructor_revenue = round($price_paid - $platform_fee, 2);
            $coupon_id_sql = $coupon_id_value ? "'$coupon_id_value'" : 'NULL';
            
            $insert_transaction = "INSERT INTO transactions 
                                  (user_id, transaction_type, amount, currency, payment_method, 
                                   payment_gateway, status, course_id, enrollment_id, coupon_id, 
                                   discount_amount, platform_fee, platform_fee_percentage, 
                                   instructor_revenue, ip_address, user_agent, processed_at)
                                  VALUES 
                                  ('$user_id', 'enrollment', '$price_paid', '$course_currency', '$payment_method', 
                                   $payment_gateway, 'completed', '$course_id', '$enrollment_id', $coupon_id_sql, 
                                   '$discount_per_course', '$platform_fee', '$platform_fee_pct', 
                                   '$instructor_revenue', '$ip_address', '$user_agent', NOW())";
            
            if (!mysqli_query($con, $insert_transaction)) {
                $error = mysqli_error($con);
                throw new Exception('Failed to create transaction record. Error: ' . $error);
            }
            
            $transaction_id = mysqli_insert_id($con);
            $transaction_ids[] = $transaction_id;
            
            // Update enrollment with transaction_id
            mysqli_query($con, "UPDATE enrollments SET transaction_id = '$transaction_id' WHERE enrollment_id = '$enrollment_id'");
        }
        
        // Update course enrollment count
        mysqli_query($con, "UPDATE courses SET enrolled_count = enrolled_count + 1 WHERE course_id = '$course_id'");
        
        // Create notification for instructor
        try {
            if (function_exists('create_notification')) {
                create_notification(
                    $con,
                    $instructor_id,
                    'new_enrollment',
                    'New Student Enrolled',
                    "A student has enrolled in your course: {$item['title']}",
                    "course.php?id={$course_id}",
                    'course',
                    $course_id,
                    'medium'
                );
            }
        } catch (Exception $e) {
            // Continue even if notification fails
        }
    }
    
    // Record coupon usage if coupon was applied
    if ($coupon_id_value && $discount_amount > 0) {
        $first_enrollment_id = !empty($enrollment_ids) ? $enrollment_ids[0] : null;
        $first_transaction_id = !empty($transaction_ids) ? $transaction_ids[0] : null;
        $enrollment_id_value = $first_enrollment_id ? "'$first_enrollment_id'" : 'NULL';
        $transaction_id_sql = $first_transaction_id ? "'$first_transaction_id'" : 'NULL';
        
        $insert_coupon_usage = "INSERT INTO coupon_usage 
                               (coupon_id, user_id, enrollment_id, transaction_id, discount_amount)
                               VALUES 
                               ('$coupon_id_value', '$user_id', $enrollment_id_value, $transaction_id_sql, '$discount_amount')";
        
        if (!mysqli_query($con, $insert_coupon_usage)) {
            throw new Exception('Failed to record coupon usage');
        }
        
        // Update coupon usage count
        mysqli_query($con, "UPDATE coupons SET usage_count = usage_count + 1 WHERE coupon_id = '$coupon_id_value'");
        
        // Clear applied coupon from session
        unset($_SESSION['applied_coupon']);
    }
    
    // Clear shopping cart
    $delete_cart = "DELETE FROM shopping_carts WHERE user_id = '$user_id'";
    if (!mysqli_query($con, $delete_cart)) {
        throw new Exception('Failed to clear shopping cart');
    }
    
    // Commit transaction
    mysqli_commit($con);
    
    // Create success notification
    try {
        if (function_exists('create_notification')) {
            $course_count = count($cart_items);
            $course_text = $course_count > 1 ? "$course_count courses" : "1 course";
            create_notification(
                $con,
                $user_id,
                'payment',
                'Purchase Successful!',
                "You have successfully enrolled in $course_text. Start learning now!",
                'dashboard',
                'enrollment',
                null,
                'high'
            );
        }
    } catch (Exception $e) {
        // Continue even if notification fails
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Purchase completed successfully!',
        'enrollment_count' => count($enrollment_ids),
        'transaction_count' => count($transaction_ids),
        'transaction_ids' => $transaction_ids
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($con);
    
    echo json_encode([
        'success' => false,
        'message' => 'Payment processing failed: ' . $e->getMessage()
    ]);
}
