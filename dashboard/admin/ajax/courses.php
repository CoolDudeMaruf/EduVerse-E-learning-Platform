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
    case 'get_courses':
        getCourses($con);
        break;
    case 'get_course':
        getCourse($con);
        break;
    case 'update_status':
        updateCourseStatus($con);
        break;
    case 'toggle_featured':
        toggleFeatured($con);
        break;
    case 'toggle_bestseller':
        toggleBestseller($con);
        break;
    case 'delete_course':
        deleteCourse($con);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getCourses($con) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $status = mysqli_real_escape_string($con, $_GET['status'] ?? '');
    $search = mysqli_real_escape_string($con, $_GET['search'] ?? '');
    
    $where = "1=1";
    if ($status) $where .= " AND c.status = '$status'";
    if ($search) {
        $where .= " AND (c.title LIKE '%$search%' OR c.subtitle LIKE '%$search%')";
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM courses c WHERE $where";
    $count_result = mysqli_query($con, $count_query);
    $total = mysqli_fetch_assoc($count_result)['total'];
    $total_pages = ceil($total / $limit);
    
    // Get courses
    $query = "SELECT c.course_id, c.title, c.thumbnail_url, c.price, c.is_free, c.status, 
              c.is_featured, c.is_bestseller, c.enrollment_count, c.created_at,
              CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
              cat.name as category_name
              FROM courses c 
              LEFT JOIN users u ON c.instructor_id = u.user_id
              LEFT JOIN categories cat ON c.category_id = cat.category_id
              WHERE $where 
              ORDER BY c.created_at DESC 
              LIMIT $offset, $limit";
    $result = mysqli_query($con, $query);
    
    $courses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $courses[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'total' => $total,
        'total_pages' => $total_pages,
        'current_page' => $page
    ]);
}

function getCourse($con) {
    $course_id = mysqli_real_escape_string($con, $_GET['course_id'] ?? '');
    
    if (!$course_id) {
        echo json_encode(['success' => false, 'message' => 'Course ID required']);
        return;
    }
    
    $query = "SELECT c.*, 
              CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
              cat.name as category_name
              FROM courses c 
              LEFT JOIN users u ON c.instructor_id = u.user_id
              LEFT JOIN categories cat ON c.category_id = cat.category_id
              WHERE c.course_id = '$course_id'";
    $result = mysqli_query($con, $query);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'course' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Course not found']);
    }
}

function updateCourseStatus($con) {
    $course_id = mysqli_real_escape_string($con, $_POST['course_id'] ?? '');
    $status = mysqli_real_escape_string($con, $_POST['status'] ?? '');
    
    $valid_statuses = ['published', 'draft', 'pending_review', 'suspended', 'archived'];
    
    if (!$course_id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Course ID and status required']);
        return;
    }
    
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    $published_at = $status === 'published' ? ", published_at = IF(published_at IS NULL, NOW(), published_at)" : "";
    
    $query = "UPDATE courses SET status = '$status', updated_at = NOW() $published_at WHERE course_id = '$course_id'";
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => 'Course status updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
}

function toggleFeatured($con) {
    $course_id = mysqli_real_escape_string($con, $_POST['course_id'] ?? '');
    $is_featured = intval($_POST['is_featured'] ?? 0);
    
    if (!$course_id) {
        echo json_encode(['success' => false, 'message' => 'Course ID required']);
        return;
    }
    
    $query = "UPDATE courses SET is_featured = $is_featured, updated_at = NOW() WHERE course_id = '$course_id'";
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => $is_featured ? 'Course featured' : 'Course unfeatured']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update course']);
    }
}

function toggleBestseller($con) {
    $course_id = mysqli_real_escape_string($con, $_POST['course_id'] ?? '');
    $is_bestseller = intval($_POST['is_bestseller'] ?? 0);
    
    if (!$course_id) {
        echo json_encode(['success' => false, 'message' => 'Course ID required']);
        return;
    }
    
    $query = "UPDATE courses SET is_bestseller = $is_bestseller, updated_at = NOW() WHERE course_id = '$course_id'";
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => $is_bestseller ? 'Marked as bestseller' : 'Removed from bestsellers']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update course']);
    }
}

function deleteCourse($con) {
    $course_id = mysqli_real_escape_string($con, $_POST['course_id'] ?? '');
    
    if (!$course_id) {
        echo json_encode(['success' => false, 'message' => 'Course ID required']);
        return;
    }
    
    // The foreign keys with CASCADE will handle related data
    $query = "DELETE FROM courses WHERE course_id = '$course_id'";
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => 'Course deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete course: ' . mysqli_error($con)]);
    }
}
?>
