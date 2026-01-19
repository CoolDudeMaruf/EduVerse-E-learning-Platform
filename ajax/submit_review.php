<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/config.php';

$action = isset($_POST['action']) ? $_POST['action'] : null;
$course_id = isset($_POST['course_id']) ? mysqli_real_escape_string($con, $_POST['course_id']) : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!$action) {
    echo json_encode(['success' => false, 'error' => 'Missing action parameter']);
    exit;
}

// Only require course_id for submit_review action
if ($action === 'submit_review' && !$course_id) {
    echo json_encode(['success' => false, 'error' => 'Missing course_id parameter']);
    exit;
}

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Please login to add a review', 'need_login' => true]);
    exit;
}

// Only students can submit reviews - instructors and admins can only view
$user_role = strtolower($_SESSION['role'] ?? 'student');
if ($action === 'submit_review' && $user_role !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Only students can submit reviews']);
    exit;
}

if ($action === 'submit_review') {
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : null;
    $review_text = isset($_POST['review_text']) ? mysqli_real_escape_string($con, $_POST['review_text']) : null;
    $review_title = isset($_POST['review_title']) ? mysqli_real_escape_string($con, $_POST['review_title']) : null;
    
    if (!$rating || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5']);
        exit;
    }
    
    if (empty($review_text)) {
        echo json_encode(['success' => false, 'error' => 'Review text is required']);
        exit;
    }
    
    if (strlen($review_text) < 10) {
        echo json_encode(['success' => false, 'error' => 'Review must be at least 10 characters']);
        exit;
    }
    
    // Check if user already reviewed this course
    $check_query = "SELECT review_id FROM reviews WHERE course_instructor_id = '$course_id' AND student_id = '$user_id' LIMIT 1";
    $check_result = mysqli_query($con, $check_query);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'error' => 'You have already reviewed this course']);
        exit;
    }
    
    // Check if user is enrolled for verified purchase
    $enrollment_query = "SELECT enrollment_id FROM enrollments WHERE student_id = '$user_id' AND course_id = '$course_id' LIMIT 1";
    $enrollment_result = mysqli_query($con, $enrollment_query);
    $is_verified_purchase = (mysqli_num_rows($enrollment_result) > 0) ? 1 : 0;
    
    // Insert review
    $insert_query = "INSERT INTO reviews (course_instructor_id, student_id, rating, review_title, review_text, is_verified_purchase, is_published) VALUES ('$course_id', '$user_id', '$rating', '$review_title', '$review_text', '$is_verified_purchase', 1)";
    
    if (mysqli_query($con, $insert_query)) {
        $review_id = mysqli_insert_id($con);
        
        // Reviews table is now the source of truth for ratings
        // No need to update courses table
        
        echo json_encode(['success' => true, 'message' => 'Review submitted successfully', 'review_id' => $review_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error submitting review. Please try again later.']);
    }
} elseif ($action === 'check_user_review') {
    $check_query = "SELECT review_id FROM reviews WHERE course_instructor_id = '$course_id' AND student_id = '$user_id' LIMIT 1";
    $check_result = mysqli_query($con, $check_query);
    
    $has_review = mysqli_num_rows($check_result) > 0;
    echo json_encode(['success' => true, 'has_review' => $has_review]);
} elseif ($action === 'edit_review') {
    $review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : 0;
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : null;
    $review_text = isset($_POST['review_text']) ? mysqli_real_escape_string($con, $_POST['review_text']) : null;
    $review_title = isset($_POST['review_title']) ? mysqli_real_escape_string($con, $_POST['review_title']) : null;
    
    if (!$review_id) {
        echo json_encode(['success' => false, 'error' => 'Review ID is required']);
        exit;
    }
    
    if (!$rating || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5']);
        exit;
    }
    
    if (empty($review_text)) {
        echo json_encode(['success' => false, 'error' => 'Review text is required']);
        exit;
    }
    
    if (strlen($review_text) < 10) {
        echo json_encode(['success' => false, 'error' => 'Review must be at least 10 characters']);
        exit;
    }
    
    // Verify ownership and get course_id
    $verify_query = "SELECT review_id, course_instructor_id FROM reviews WHERE review_id = $review_id AND student_id = '$user_id' LIMIT 1";
    $verify_result = mysqli_query($con, $verify_query);
    
    if (!$verify_result || mysqli_num_rows($verify_result) === 0) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $review_data = mysqli_fetch_assoc($verify_result);
    $review_course_id = $review_data['course_instructor_id'];
    
    // Update review
    $update_query = "UPDATE reviews SET rating = '$rating', review_title = '$review_title', review_text = '$review_text', is_edited = 1, edited_at = NOW() WHERE review_id = $review_id";
    
    if (mysqli_query($con, $update_query)) {
        // Reviews table is now the source of truth for ratings
        // No need to update courses table
        
        echo json_encode(['success' => true, 'message' => 'Review updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error updating review. Please try again later.']);
    }
} elseif ($action === 'delete_review') {
    $review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : null;
    
    if (!$review_id) {
        echo json_encode(['success' => false, 'error' => 'Review ID is required']);
        exit;
    }
    
    // Check if review belongs to current user
    $check_query = "SELECT review_id, course_instructor_id FROM reviews WHERE review_id = '$review_id' AND student_id = '$user_id' LIMIT 1";
    $check_result = mysqli_query($con, $check_query);
    
    if (!$check_result || mysqli_num_rows($check_result) === 0) {
        echo json_encode(['success' => false, 'error' => 'You can only delete your own reviews']);
        exit;
    }
    
    $review = mysqli_fetch_assoc($check_result);
    $review_course_id = $review['course_instructor_id'];
    
    // Delete the review
    $delete_query = "DELETE FROM reviews WHERE review_id = '$review_id'";
    
    if (mysqli_query($con, $delete_query)) {
        // Reviews table is now the source of truth for ratings
        // No need to update courses table
        
        echo json_encode(['success' => true, 'message' => 'Review deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error deleting review']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
