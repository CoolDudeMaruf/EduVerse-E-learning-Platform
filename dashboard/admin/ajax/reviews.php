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
    case 'get_reviews':
        getReviews($con);
        break;
    case 'get_review':
        getReview($con);
        break;
    case 'toggle_publish':
        togglePublish($con);
        break;
    case 'delete_review':
        deleteReview($con);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getReviews($con) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $filter = mysqli_real_escape_string($con, $_GET['filter'] ?? '');
    
    $where = "1=1";
    if ($filter === 'published') $where .= " AND r.is_published = 1";
    if ($filter === 'unpublished') $where .= " AND r.is_published = 0";
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM reviews r WHERE $where";
    $count_result = mysqli_query($con, $count_query);
    $total = mysqli_fetch_assoc($count_result)['total'];
    $total_pages = ceil($total / $limit);
    
    // Get reviews
    $query = "SELECT r.*, 
              c.title as course_title,
              CONCAT(u.first_name, ' ', u.last_name) as user_name
              FROM reviews r 
              LEFT JOIN courses c ON r.course_instructor_id = c.course_id
              LEFT JOIN users u ON r.student_id = u.user_id
              WHERE $where 
              ORDER BY r.created_at DESC 
              LIMIT $offset, $limit";
    $result = mysqli_query($con, $query);
    
    $reviews = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $reviews[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'total' => $total,
        'total_pages' => $total_pages,
        'current_page' => $page
    ]);
}

function getReview($con) {
    $review_id = intval($_GET['review_id'] ?? 0);
    
    if (!$review_id) {
        echo json_encode(['success' => false, 'message' => 'Review ID required']);
        return;
    }
    
    $query = "SELECT r.*, 
              c.title as course_title,
              CONCAT(u.first_name, ' ', u.last_name) as user_name
              FROM reviews r 
              LEFT JOIN courses c ON r.course_instructor_id = c.course_id
              LEFT JOIN users u ON r.student_id = u.user_id
              WHERE r.review_id = $review_id";
    $result = mysqli_query($con, $query);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'review' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Review not found']);
    }
}

function togglePublish($con) {
    $review_id = intval($_POST['review_id'] ?? 0);
    $is_published = intval($_POST['is_published'] ?? 0);
    
    if (!$review_id) {
        echo json_encode(['success' => false, 'message' => 'Review ID required']);
        return;
    }
    
    $query = "UPDATE reviews SET is_published = $is_published, updated_at = NOW() WHERE review_id = $review_id";
    
    if (mysqli_query($con, $query)) {
        // Update course rating if needed
        updateCourseRating($con, $review_id);
        echo json_encode(['success' => true, 'message' => $is_published ? 'Review published' : 'Review unpublished']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update review']);
    }
}

function deleteReview($con) {
    $review_id = intval($_POST['review_id'] ?? 0);
    
    if (!$review_id) {
        echo json_encode(['success' => false, 'message' => 'Review ID required']);
        return;
    }
    
    // Get course ID before deleting
    $course_query = mysqli_query($con, "SELECT course_instructor_id FROM reviews WHERE review_id = $review_id");
    $course_row = mysqli_fetch_assoc($course_query);
    
    $query = "DELETE FROM reviews WHERE review_id = $review_id";
    
    if (mysqli_query($con, $query)) {
        // Update course rating
        if ($course_row) {
            updateCourseRatingByCourse($con, $course_row['course_instructor_id']);
        }
        echo json_encode(['success' => true, 'message' => 'Review deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete review']);
    }
}

function updateCourseRating($con, $review_id) {
    $query = "SELECT course_instructor_id FROM reviews WHERE review_id = $review_id";
    $result = mysqli_query($con, $query);
    if ($row = mysqli_fetch_assoc($result)) {
        updateCourseRatingByCourse($con, $row['course_instructor_id']);
    }
}

function updateCourseRatingByCourse($con, $course_id) {
    if (!$course_id) return;
    
    $course_id = mysqli_real_escape_string($con, $course_id);
    
    // Calculate new average rating
    $rating_query = "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
                     FROM reviews 
                     WHERE course_instructor_id = '$course_id' AND is_published = 1";
    $rating_result = mysqli_query($con, $rating_query);
    $rating_data = mysqli_fetch_assoc($rating_result);
    
    $avg_rating = round($rating_data['avg_rating'] ?? 0, 2);
    $review_count = $rating_data['review_count'] ?? 0;
    
    // Update course
    $update_query = "UPDATE courses SET 
                     average_rating = $avg_rating,
                     total_reviews = $review_count,
                     updated_at = NOW()
                     WHERE course_id = '$course_id'";
    mysqli_query($con, $update_query);
}
?>
