<?php
session_start();
include('../includes/config.php');

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

// Get and sanitize course_id (from GET or POST)
$course_id = null;
if (isset($_GET['course_id'])) {
    $course_id = mysqli_real_escape_string($con, $_GET['course_id']);
} elseif (isset($_POST['course_id'])) {
    $course_id = mysqli_real_escape_string($con, $_POST['course_id']);
}

// Get and sanitize instructor_id (from GET or POST)
$instructor_id = null;
if (isset($_GET['instructor_id'])) {
    $instructor_id = mysqli_real_escape_string($con, $_GET['instructor_id']);
} elseif (isset($_POST['instructor_id'])) {
    $instructor_id = mysqli_real_escape_string($con, $_POST['instructor_id']);
}

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'helpful';
$reviews_per_page = 5;
$offset = ($page - 1) * $reviews_per_page;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!$course_id && !$instructor_id && $action !== 'mark_helpful' && $action !== 'report_review') { 
    echo json_encode(['error' => 'Course ID or Instructor ID is required']); 
    exit; 
}
if (($action === 'mark_helpful' || $action === 'report_review') && !$user_id) { 
    echo json_encode(['error' => 'Please login', 'need_login' => true]); 
    exit; 
}

// Handle different actions
switch ($action) {
    case 'get_reviews':
        getReviews();
        break;
    
    case 'mark_helpful':
        markReviewHelpful();
        break;
    
    case 'get_rating_breakdown':
        getRatingBreakdown();
        break;
    
    case 'respond_to_review':
        respondToReview();
        break;
    
    case 'report_review':
        reportReview();
        break;
    
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function getReviews() {
    global $con, $course_id, $instructor_id, $page, $search, $sort, $reviews_per_page, $offset, $user_id;
    
    // Build WHERE clause based on whether we have course_id or instructor_id
    if ($course_id) {
        $where = "r.course_instructor_id = '$course_id' AND r.is_published = 1";
        // For course reviews, also include course title
        $join_course = "LEFT JOIN courses c ON r.course_instructor_id = c.course_id";
        $select_extra = ", c.title as course_title";
    } elseif ($instructor_id) {
        $where = "r.is_published = 1 AND c.instructor_id = '$instructor_id'";
        $join_course = "INNER JOIN courses c ON r.course_instructor_id = c.course_id";
        $select_extra = ", c.title as course_title";
    } else {
        echo json_encode(['error' => 'Course ID or Instructor ID is required']);
        exit;
    }
    
    if (!empty($search)) {
        $search_safe = mysqli_real_escape_string($con, $search);
        $where .= " AND (r.review_text LIKE '%$search_safe%' OR r.review_title LIKE '%$search_safe%' OR u.first_name LIKE '%$search_safe%' OR u.last_name LIKE '%$search_safe%')";
    }
    
    // Determine order
    switch ($sort) {
        case 'recent':
            $order = "r.created_at DESC";
            break;
        case 'highest':
            $order = "r.rating DESC, r.created_at DESC";
            break;
        case 'lowest':
            $order = "r.rating ASC, r.created_at DESC";
            break;
        case 'helpful':
        default:
            $order = "r.helpful_count DESC, r.created_at DESC";
            break;
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM reviews r $join_course LEFT JOIN users u ON r.student_id = u.user_id WHERE $where";
    $count_result = mysqli_query($con, $count_query);
    $count_data = mysqli_fetch_assoc($count_result);
    $total_reviews = $count_data['total'];
    $total_pages = ceil($total_reviews / $reviews_per_page);
    
    // Get reviews with course title
    $query = "SELECT r.review_id, r.course_instructor_id, r.rating, r.review_title, r.review_text, r.helpful_count, r.not_helpful_count, r.instructor_response, r.instructor_responded_at, r.created_at, u.user_id, u.first_name, u.last_name, u.profile_image_url$select_extra
            FROM reviews r $join_course LEFT JOIN users u ON r.student_id = u.user_id WHERE $where ORDER BY $order LIMIT $reviews_per_page OFFSET $offset";
    
    $result = mysqli_query($con, $query);
    $reviews = [];
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Check if current user marked this review as helpful or not helpful
            $user_marked_helpful = false;
            $user_marked_not_helpful = false;
            if ($user_id) {
                $helpful_check = "SELECT is_helpful FROM review_helpfulness WHERE review_id = {$row['review_id']} AND user_id = '$user_id' LIMIT 1";
                $helpful_result = mysqli_query($con, $helpful_check);
                if (mysqli_num_rows($helpful_result) > 0) {
                    $helpful_data = mysqli_fetch_assoc($helpful_result);
                    $user_marked_helpful = ($helpful_data['is_helpful'] == 1);
                    $user_marked_not_helpful = ($helpful_data['is_helpful'] == 0);
                }
            }
            $row['user_marked_helpful'] = $user_marked_helpful;
            $row['user_marked_not_helpful'] = $user_marked_not_helpful;
            $reviews[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'total_reviews' => $total_reviews,
        'current_page' => $page,
        'total_pages' => $total_pages
    ]);
}

function markReviewHelpful() {
    global $con, $user_id;
    $review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : 0;
    $is_helpful = isset($_POST['is_helpful']) ? intval($_POST['is_helpful']) : 1;
    
    if (!$review_id) { 
        echo json_encode(['error' => 'Review ID is required']); 
        exit; 
    }
    if (!$user_id) { 
        echo json_encode(['error' => 'Please login to mark reviews', 'need_login' => true]); 
        exit; 
    }
    
    $check_query = "SELECT is_helpful FROM review_helpfulness WHERE review_id = $review_id AND user_id = '$user_id' LIMIT 1";
    $check_result = mysqli_query($con, $check_query);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $check_data = mysqli_fetch_assoc($check_result);
        if ($check_data['is_helpful'] == $is_helpful) {
            // Same vote, remove it
            mysqli_query($con, "DELETE FROM review_helpfulness WHERE review_id = $review_id AND user_id = '$user_id'");
        } else {
            // Different vote, update it
            mysqli_query($con, "UPDATE review_helpfulness SET is_helpful = $is_helpful WHERE review_id = $review_id AND user_id = '$user_id'");
        }
    } else {
        // New vote
        $insert_query = "INSERT INTO review_helpfulness (review_id, user_id, is_helpful) VALUES ($review_id, '$user_id', $is_helpful)";
        if (!mysqli_query($con, $insert_query)) {
            echo json_encode(['error' => 'Failed to mark review', 'debug' => mysqli_error($con)]);
            exit;
        }
    }
    
    // Recalculate counts from review_helpfulness table
    $helpful_query = "SELECT COUNT(*) as count FROM review_helpfulness WHERE review_id = $review_id AND is_helpful = 1";
    $helpful_result = mysqli_query($con, $helpful_query);
    $helpful_data = mysqli_fetch_assoc($helpful_result);
    $helpful_count = $helpful_data['count'];
    
    $not_helpful_query = "SELECT COUNT(*) as count FROM review_helpfulness WHERE review_id = $review_id AND is_helpful = 0";
    $not_helpful_result = mysqli_query($con, $not_helpful_query);
    $not_helpful_data = mysqli_fetch_assoc($not_helpful_result);
    $not_helpful_count = $not_helpful_data['count'];
    
    // Update reviews table with correct counts
    mysqli_query($con, "UPDATE reviews SET helpful_count = $helpful_count, not_helpful_count = $not_helpful_count WHERE review_id = $review_id");
    
    echo json_encode(['success' => true, 'helpful_count' => $helpful_count, 'not_helpful_count' => $not_helpful_count]);
}

function getRatingBreakdown() {
    global $con, $course_id, $instructor_id;
    
    if ($course_id) {
        $where = "course_instructor_id = '$course_id' AND is_published = 1";
    } elseif ($instructor_id) {
        $where = "is_published = 1 AND course_instructor_id IN (SELECT course_id FROM courses WHERE instructor_id = '$instructor_id')";
    } else {
        echo json_encode(['error' => 'Course ID or Instructor ID is required']);
        exit;
    }
    
    $query = "SELECT rating, COUNT(*) as count FROM reviews WHERE $where GROUP BY rating ORDER BY rating DESC";
    $result = mysqli_query($con, $query);
    $breakdown = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    $total = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $breakdown[$row['rating']] = $row['count'];
        $total += $row['count'];
    }
    
    // Calculate percentages
    $percentages = [];
    foreach ($breakdown as $rating => $count) {
        $percentages[$rating] = $total > 0 ? round(($count / $total) * 100) : 0;
    }
    
    echo json_encode([
        'success' => true,
        'breakdown' => $breakdown,
        'percentages' => $percentages,
        'total' => $total
    ]);
}

function respondToReview() {
    global $con, $user_id;
    
    $review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : 0;
    $response_text = isset($_POST['response_text']) ? mysqli_real_escape_string($con, $_POST['response_text']) : '';
    
    if (!$review_id) {
        echo json_encode(['error' => 'Review ID is required']);
        exit;
    }
    if (!$user_id) {
        echo json_encode(['error' => 'Please login', 'need_login' => true]);
        exit;
    }
    if (empty($response_text)) {
        echo json_encode(['error' => 'Response cannot be empty']);
        exit;
    }
    
    // Get review and course info
    $review_query = "SELECT course_id FROM reviews WHERE review_id = $review_id LIMIT 1";
    $review_result = mysqli_query($con, $review_query);
    $review_data = mysqli_fetch_assoc($review_result);
    
    if (!$review_data) {
        echo json_encode(['error' => 'Review not found']);
        exit;
    }
    
    $course_id = $review_data['course_id'];
    
    // Check if user is the course instructor
    $course_query = "SELECT instructor_id FROM courses WHERE course_id = '$course_id' LIMIT 1";
    $course_result = mysqli_query($con, $course_query);
    $course_data = mysqli_fetch_assoc($course_result);
    
    if (!$course_data || $course_data['instructor_id'] != $user_id) {
        echo json_encode(['error' => 'Only the course instructor can respond to reviews']);
        exit;
    }
    
    // Update review with instructor response
    $update_query = "UPDATE reviews SET instructor_response = '$response_text', instructor_responded_at = NOW() WHERE review_id = $review_id";
    if (mysqli_query($con, $update_query)) {
        echo json_encode(['success' => true, 'message' => 'Response added successfully']);
    } else {
        echo json_encode(['error' => 'Failed to save response']);
    }
}

function reportReview() {
    global $con, $user_id;
    
    $review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : 0;
    $reason = isset($_POST['reason']) ? mysqli_real_escape_string($con, $_POST['reason']) : '';
    
    if (!$review_id) {
        echo json_encode(['error' => 'Review ID is required']);
        exit;
    }
    if (!$user_id) {
        echo json_encode(['error' => 'Please login', 'need_login' => true]);
        exit;
    }
    
    // Check if review exists
    $check_query = "SELECT review_id FROM reviews WHERE review_id = $review_id LIMIT 1";
    $check_result = mysqli_query($con, $check_query);
    
    if (!$check_result || mysqli_num_rows($check_result) == 0) {
        echo json_encode(['error' => 'Review not found']);
        exit;
    }
    
    // Check if user already reported this review
    $report_check = "SELECT report_id FROM review_reports WHERE review_id = $review_id AND reporter_user_id = '$user_id' LIMIT 1";
    $report_result = mysqli_query($con, $report_check);
    
    if ($report_result && mysqli_num_rows($report_result) > 0) {
        echo json_encode(['success' => true, 'message' => 'You have already reported this review']);
        exit;
    }
    
    // Insert report
    $insert_query = "INSERT INTO review_reports (review_id, reporter_user_id, reason, status) VALUES ($review_id, '$user_id', '$reason', 'pending')";
    if (mysqli_query($con, $insert_query)) {
        echo json_encode(['success' => true, 'message' => 'Review reported successfully. We will investigate this matter.']);
    } else {
        echo json_encode(['error' => 'Failed to report review']);
    }
}
?>
