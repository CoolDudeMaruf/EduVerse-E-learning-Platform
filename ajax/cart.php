<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/config.php';

$action = isset($_POST['action']) ? $_POST['action'] : null;
$course_id = isset($_POST['course_id']) ? $_POST['course_id'] : null;

if (!$action || !$course_id) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

if ($action === 'toggle_cart') {
    if ($is_logged_in) {
        // User is logged in - use database
        $user_id = $_SESSION['user_id'];
        $course_id_escaped = mysqli_real_escape_string($con, $course_id);
        
        // Check if user is admin or instructor - they cannot buy courses
        $user_role = strtolower($_SESSION['role'] ?? '');
        if (in_array($user_role, ['admin', 'instructor'])) {
            echo json_encode(['success' => false, 'error' => 'Admins and instructors cannot purchase courses']);
            exit;
        }
        
        // Check if user is already enrolled in this course
        $enrollment_check = "SELECT enrollment_id FROM enrollments WHERE user_id = '$user_id' AND course_id = '$course_id_escaped' LIMIT 1";
        $enrollment_result = mysqli_query($con, $enrollment_check);
        if ($enrollment_result && mysqli_num_rows($enrollment_result) > 0) {
            echo json_encode(['success' => false, 'error' => 'You already own this course']);
            exit;
        }
        
        $check_query = "SELECT cart_id FROM shopping_carts WHERE user_id = '$user_id' AND course_id = '$course_id_escaped'";
        $check_result = mysqli_query($con, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Remove from cart
            $delete_query = "DELETE FROM shopping_carts WHERE user_id = '$user_id' AND course_id = '$course_id_escaped'";
            if (mysqli_query($con, $delete_query)) {
                echo json_encode(['success' => true, 'action' => 'removed', 'in_cart' => false]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to remove from cart']);
            }
        } else {
            // Add to cart
            $insert_query = "INSERT INTO shopping_carts (user_id, course_id) VALUES ('$user_id', '$course_id_escaped')";
            if (mysqli_query($con, $insert_query)) {
                echo json_encode(['success' => true, 'action' => 'added', 'in_cart' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to add to cart']);
            }
        }
    } else {
        // Guest user - use session
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        $cart = &$_SESSION['cart'];
        $inCart = in_array($course_id, $cart);
        
        if ($inCart) {
            // Remove from cart
            $cart = array_values(array_filter($cart, function($id) use ($course_id) {
                return $id != $course_id;
            }));
            $_SESSION['cart'] = $cart;
            echo json_encode(['success' => true, 'action' => 'removed', 'in_cart' => false]);
        } else {
            // Add to cart
            $cart[] = $course_id;
            $_SESSION['cart'] = $cart;
            echo json_encode(['success' => true, 'action' => 'added', 'in_cart' => true]);
        }
    }
} elseif ($action === 'check_cart') {
    if ($is_logged_in) {
        // User is logged in - check database
        $user_id = $_SESSION['user_id'];
        $course_id_escaped = mysqli_real_escape_string($con, $course_id);
        
        $check_query = "SELECT cart_id FROM shopping_carts WHERE user_id = '$user_id' AND course_id = '$course_id_escaped'";
        $check_result = mysqli_query($con, $check_query);
        
        $inCart = mysqli_num_rows($check_result) > 0;
        echo json_encode(['success' => true, 'in_cart' => $inCart]);
    } else {
        // Guest user - check session
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        $inCart = in_array($course_id, $_SESSION['cart']);
        echo json_encode(['success' => true, 'in_cart' => $inCart]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
