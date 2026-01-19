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

if ($action === 'toggle_wishlist') {
    if ($is_logged_in) {
        // User is logged in - use database
        $user_id = $_SESSION['user_id'];
        $course_id_escaped = mysqli_real_escape_string($con, $course_id);
        
        // Check if user is admin or instructor - they cannot buy courses
        $user_role = strtolower($_SESSION['role'] ?? '');
        if (in_array($user_role, ['admin', 'instructor'])) {
            echo json_encode(['success' => false, 'error' => 'Admins and instructors cannot add to wishlist']);
            exit;
        }
        
        // Check if user is already enrolled in this course
        $enrollment_check = "SELECT enrollment_id FROM enrollments WHERE user_id = '$user_id' AND course_id = '$course_id_escaped' LIMIT 1";
        $enrollment_result = mysqli_query($con, $enrollment_check);
        if ($enrollment_result && mysqli_num_rows($enrollment_result) > 0) {
            echo json_encode(['success' => false, 'error' => 'You already own this course']);
            exit;
        }
        
        $check_query = "SELECT wishlist_id FROM wishlists WHERE user_id = '$user_id' AND course_id = '$course_id_escaped'";
        $check_result = mysqli_query($con, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Remove from wishlist
            $delete_query = "DELETE FROM wishlists WHERE user_id = '$user_id' AND course_id = '$course_id_escaped'";
            if (mysqli_query($con, $delete_query)) {
                echo json_encode(['success' => true, 'action' => 'removed', 'in_wishlist' => false]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to remove from wishlist']);
            }
        } else {
            // Add to wishlist
            $insert_query = "INSERT INTO wishlists (user_id, course_id) VALUES ('$user_id', '$course_id_escaped')";
            if (mysqli_query($con, $insert_query)) {
                echo json_encode(['success' => true, 'action' => 'added', 'in_wishlist' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to add to wishlist']);
            }
        }
    } else {
        // Guest user - use session
        if (!isset($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }
        
        $wishlist = &$_SESSION['wishlist'];
        $inWishlist = in_array($course_id, $wishlist);
        
        if ($inWishlist) {
            // Remove from wishlist
            $wishlist = array_values(array_filter($wishlist, function($id) use ($course_id) {
                return $id != $course_id;
            }));
            $_SESSION['wishlist'] = $wishlist;
            echo json_encode(['success' => true, 'action' => 'removed', 'in_wishlist' => false]);
        } else {
            // Add to wishlist
            $wishlist[] = $course_id;
            $_SESSION['wishlist'] = $wishlist;
            echo json_encode(['success' => true, 'action' => 'added', 'in_wishlist' => true]);
        }
    }
} elseif ($action === 'check_wishlist') {
    if ($is_logged_in) {
        // User is logged in - check database
        $user_id = $_SESSION['user_id'];
        $course_id_escaped = mysqli_real_escape_string($con, $course_id);
        
        $check_query = "SELECT wishlist_id FROM wishlists WHERE user_id = '$user_id' AND course_id = '$course_id_escaped'";
        $check_result = mysqli_query($con, $check_query);
        
        $inWishlist = mysqli_num_rows($check_result) > 0;
        echo json_encode(['success' => true, 'in_wishlist' => $inWishlist]);
    } else {
        // Guest user - check session
        if (!isset($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }
        
        $inWishlist = in_array($course_id, $_SESSION['wishlist']);
        echo json_encode(['success' => true, 'in_wishlist' => $inWishlist]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
