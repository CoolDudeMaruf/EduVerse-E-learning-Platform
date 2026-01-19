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
    case 'list':
        $search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
        $course_filter = isset($_GET['course_id']) ? mysqli_real_escape_string($con, $_GET['course_id']) : '';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;
        $offset = ($page - 1) * $limit;
        
        $where_conditions = ["c.instructor_id = '$instructor_id'"];
        
        if (!empty($search)) {
            $where_conditions[] = "(u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR u.email LIKE '%$search%')";
        }
        
        if (!empty($course_filter)) {
            $where_conditions[] = "e.course_id = '$course_filter'";
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $count_query = "SELECT COUNT(DISTINCT e.student_id) as total 
                       FROM enrollments e 
                       INNER JOIN users u ON e.student_id = u.user_id 
                       INNER JOIN courses c ON e.course_id = c.course_id 
                       WHERE $where_clause";
        $count_result = mysqli_query($con, $count_query);
        $total = 0;
        if ($count_result) {
            $row = mysqli_fetch_assoc($count_result);
            $total = $row['total'];
        }
        
        // Get students with their enrollment details
        $query = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.email, u.profile_image_url,
                         MAX(e.enrollment_date) as last_enrolled,
                         COUNT(DISTINCT e.course_id) as courses_enrolled,
                         ROUND(AVG(e.progress_percentage), 1) as avg_progress
                  FROM enrollments e 
                  INNER JOIN users u ON e.student_id = u.user_id 
                  INNER JOIN courses c ON e.course_id = c.course_id 
                  WHERE $where_clause
                  GROUP BY u.user_id, u.first_name, u.last_name, u.email, u.profile_image_url
                  ORDER BY last_enrolled DESC
                  LIMIT $limit OFFSET $offset";
        
        $result = mysqli_query($con, $query);
        $students = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $full_name = trim($row['first_name'] . ' ' . $row['last_name']);
                $students[] = [
                    'user_id' => $row['user_id'],
                    'name' => $full_name ?: 'Unknown',
                    'email' => $row['email'],
                    'avatar' => $row['profile_image_url'] ? $base_url . $row['profile_image_url'] : 
                               'https://ui-avatars.com/api/?name=' . urlencode($full_name) . '&background=6366f1&color=fff',
                    'enrolled_at' => $row['last_enrolled'],
                    'courses_count' => (int)$row['courses_enrolled'],
                    'progress' => round($row['avg_progress'] ?? 0, 1)
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'students' => $students,
            'pagination' => [
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
        break;
        
    case 'get':
        $user_id = isset($_GET['user_id']) ? mysqli_real_escape_string($con, $_GET['user_id']) : '';
        
        if (empty($user_id)) {
            echo json_encode(['success' => false, 'error' => 'User ID required']);
            exit;
        }
        
        // Get student basic info
        $user_query = "SELECT user_id, first_name, last_name, email, profile_image_url FROM users WHERE user_id = '$user_id'";
        $user_result = mysqli_query($con, $user_query);
        
        if (!$user_result || mysqli_num_rows($user_result) == 0) {
            echo json_encode(['success' => false, 'error' => 'Student not found']);
            exit;
        }
        
        $user = mysqli_fetch_assoc($user_result);
        $full_name = trim($user['first_name'] . ' ' . $user['last_name']);
        
        // Get enrolled courses from this instructor with detailed info
        $courses_query = "SELECT c.course_id, c.title, c.thumbnail_url,
                                e.enrollment_date, e.status, e.progress_percentage,
                                e.total_learning_time_seconds,
                                (SELECT COUNT(*) FROM course_sections cs 
                                 INNER JOIN lectures l ON cs.section_id = l.section_id 
                                 WHERE cs.course_id = c.course_id) as total_lectures,
                                (SELECT COUNT(*) FROM lecture_progress lp 
                                 INNER JOIN lectures l ON lp.lecture_id = l.lecture_id
                                 INNER JOIN course_sections cs ON l.section_id = cs.section_id
                                 WHERE cs.course_id = c.course_id AND lp.student_id = '$user_id' AND lp.is_completed = 1) as lectures_completed
                         FROM enrollments e
                         INNER JOIN courses c ON e.course_id = c.course_id
                         WHERE e.student_id = '$user_id' AND c.instructor_id = '$instructor_id'
                         ORDER BY e.enrollment_date DESC";
        $courses_result = mysqli_query($con, $courses_query);
        $courses = [];
        $total_learning_time_seconds = 0;
        $avg_progress = 0;
        $first_enrollment = null;
        
        if ($courses_result) {
            while ($row = mysqli_fetch_assoc($courses_result)) {
                $total_learning_time_seconds += (int)($row['total_learning_time_seconds'] ?? 0);
                $avg_progress += $row['progress_percentage'];
                if (!$first_enrollment) {
                    $first_enrollment = $row['enrollment_date'];
                }
                
                $courses[] = [
                    'course_id' => $row['course_id'],
                    'title' => $row['title'],
                    'thumbnail' => $row['thumbnail_url'] ? $base_url . $row['thumbnail_url'] : 'https://via.placeholder.com/300x200?text=' . urlencode($row['title']),
                    'enrolled_date' => date('M d, Y', strtotime($row['enrollment_date'])),
                    'status' => $row['status'],
                    'progress' => round($row['progress_percentage'], 1),
                    'lectures_completed' => (int)$row['lectures_completed'],
                    'total_lectures' => (int)$row['total_lectures']
                ];
            }
        }
        
        $courses_count = count($courses);
        $avg_progress = $courses_count > 0 ? round($avg_progress / $courses_count, 1) : 0;
        
        // Convert seconds to hours for display
        $total_learning_hours = round($total_learning_time_seconds / 3600, 1);
        
        echo json_encode([
            'success' => true,
            'student' => [
                'user_id' => $user['user_id'],
                'name' => $full_name ?: 'Unknown',
                'email' => $user['email'],
                'avatar' => $user['profile_image_url'] ? $base_url . $user['profile_image_url'] : 
                           'https://ui-avatars.com/api/?name=' . urlencode($full_name) . '&background=6366f1&color=fff',
                'enrolled_at' => $first_enrollment ? date('M d, Y', strtotime($first_enrollment)) : 'N/A',
                'total_learning_time' => $total_learning_hours,
                'avg_progress' => $avg_progress,
                'courses' => $courses
            ]
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
