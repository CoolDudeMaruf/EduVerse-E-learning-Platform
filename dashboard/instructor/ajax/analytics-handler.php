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

// Date range
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($con, $_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($con, $_GET['end_date']) : date('Y-m-d');

switch ($action) {
    case 'get_overview':
        // Total students
        $students_query = "SELECT COUNT(DISTINCT e.student_id) as total
                          FROM enrollments e
                          INNER JOIN courses c ON e.course_id = c.course_id
                          WHERE c.instructor_id = '$instructor_id'";
        $students_result = mysqli_query($con, $students_query);
        $total_students = 0;
        if ($students_result) {
            $row = mysqli_fetch_assoc($students_result);
            $total_students = $row['total'] ?? 0;
        }
        
        // Total earnings from enrollments
        $earnings_query = "SELECT COALESCE(SUM(CASE WHEN e.price_paid > 0 THEN e.price_paid ELSE c.price END), 0) as total 
                          FROM enrollments e 
                          INNER JOIN courses c ON e.course_id = c.course_id
                          WHERE c.instructor_id = '$instructor_id' 
                          AND c.is_free = 0 AND c.price > 0";
        $earnings_result = mysqli_query($con, $earnings_query);
        $total_earnings = 0;
        if ($earnings_result) {
            $row = mysqli_fetch_assoc($earnings_result);
            $total_earnings = $row['total'] ?? 0;
        }
        
        // Total reviews and avg rating from reviews table
        $reviews_query = "SELECT COUNT(*) as total, AVG(rating) as avg_rating
                         FROM reviews r
                         INNER JOIN courses c ON r.course_instructor_id = c.course_id
                         WHERE c.instructor_id = '$instructor_id'
                         AND r.is_published = 1";
        $reviews_result = mysqli_query($con, $reviews_query);
        $total_reviews = 0;
        $avg_rating = 0;
        if ($reviews_result) {
            $row = mysqli_fetch_assoc($reviews_result);
            $total_reviews = $row['total'] ?? 0;
            $avg_rating = round($row['avg_rating'] ?? 0, 1);
        }
        
        // Completion rate from enrollments
        $completion_rate = 0;
        $completion_query = "SELECT 
                              COUNT(CASE WHEN e.progress_percentage >= 100 THEN 1 END) as completed,
                              COUNT(*) as total
                            FROM enrollments e
                            INNER JOIN courses c ON e.course_id = c.course_id
                            WHERE c.instructor_id = '$instructor_id'";
        $completion_result = mysqli_query($con, $completion_query);
        if ($completion_result) {
            $row = mysqli_fetch_assoc($completion_result);
            if ($row['total'] > 0) {
                $completion_rate = round(($row['completed'] / $row['total']) * 100, 1);
            }
        }
        
        echo json_encode([
            'success' => true,
            'overview' => [
                'total_students' => (int)$total_students,
                'total_earnings' => (float)$total_earnings,
                'total_reviews' => (int)$total_reviews,
                'avg_rating' => $avg_rating,
                'completion_rate' => $completion_rate
            ]
        ]);
        break;
        
    case 'get_engagement':
        // Student engagement data - categorize students by activity level
        // Active: activity in last 7 days
        // Moderate: activity in last 7-30 days
        // Inactive: no activity in 30+ days or never
        
        $active = 0;
        $moderate = 0;
        $inactive = 0;
        
        // Get all enrolled students for this instructor's courses with their last activity
        $query = "SELECT e.student_id, 
                        COALESCE(
                            (SELECT MAX(lp.updated_at) FROM lecture_progress lp 
                             INNER JOIN lectures l ON lp.lecture_id = l.lecture_id
                             INNER JOIN course_sections cs ON l.section_id = cs.section_id
                             WHERE cs.course_id = e.course_id AND lp.user_id = e.student_id),
                            e.enrollment_date
                        ) as last_activity
                  FROM enrollments e
                  INNER JOIN courses c ON e.course_id = c.course_id
                  WHERE c.instructor_id = '$instructor_id'
                  GROUP BY e.student_id";
        
        $result = mysqli_query($con, $query);
        if ($result) {
            $now = new DateTime();
            while ($row = mysqli_fetch_assoc($result)) {
                $lastActivity = new DateTime($row['last_activity']);
                $daysDiff = $now->diff($lastActivity)->days;
                
                if ($daysDiff <= 7) {
                    $active++;
                } elseif ($daysDiff <= 30) {
                    $moderate++;
                } else {
                    $inactive++;
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'engagement' => [
                'active' => $active,
                'moderate' => $moderate,
                'inactive' => $inactive
            ]
        ]);
        break;
        
    case 'get_completion':
        // Completion rates by course
        $query = "SELECT c.course_id, c.title,
                        COUNT(e.enrollment_id) as total_enrolled,
                        COUNT(CASE WHEN e.progress_percentage >= 100 THEN 1 END) as completed
                  FROM courses c
                  LEFT JOIN enrollments e ON c.course_id = e.course_id
                  WHERE c.instructor_id = '$instructor_id'
                  GROUP BY c.course_id
                  ORDER BY c.title";
        
        $result = mysqli_query($con, $query);
        $courses = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rate = $row['total_enrolled'] > 0 ? round(($row['completed'] / $row['total_enrolled']) * 100, 1) : 0;
                $courses[] = [
                    'title' => $row['title'],
                    'total' => (int)$row['total_enrolled'],
                    'completed' => (int)$row['completed'],
                    'rate' => $rate
                ];
            }
        }
        
        echo json_encode(['success' => true, 'completion' => $courses]);
        break;
        
    case 'get_heatmap':
        // Content performance - which lectures are most viewed
        $query = "SELECT l.lecture_id, l.title, cs.title as section_title, c.title as course_title,
                        COUNT(lp.progress_id) as views
                  FROM lectures l
                  INNER JOIN course_sections cs ON l.section_id = cs.section_id
                  INNER JOIN courses c ON cs.course_id = c.course_id
                  LEFT JOIN lecture_progress lp ON l.lecture_id = lp.lecture_id
                  WHERE c.instructor_id = '$instructor_id'
                  GROUP BY l.lecture_id
                  ORDER BY views DESC
                  LIMIT 20";
        
        $result = mysqli_query($con, $query);
        $lectures = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $lectures[] = [
                    'title' => $row['title'],
                    'section' => $row['section_title'],
                    'course' => $row['course_title'],
                    'views' => (int)$row['views']
                ];
            }
        }
        
        echo json_encode(['success' => true, 'heatmap' => $lectures]);
        break;
        
    case 'get_forum':
        // Forum/discussion activity (simplified)
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $label = date('D', strtotime("-$i days"));
            
            // Count reviews as forum activity proxy
            $query = "SELECT COUNT(*) as cnt FROM reviews r
                     INNER JOIN courses c ON r.course_id = c.course_id
                     WHERE c.instructor_id = '$instructor_id'
                     AND DATE(r.created_at) = '$date'";
            $result = mysqli_query($con, $query);
            $activity = 0;
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $activity = $row['cnt'] ?? 0;
            }
            
            $data[] = ['label' => $label, 'activity' => (int)$activity];
        }
        
        echo json_encode(['success' => true, 'forum' => $data]);
        break;
        
    case 'get_all':
        // Get all analytics data in one call
        $overview = [];
        $engagement = [];
        $completion = [];
        $heatmap = [];
        
        // Overview stats
        $students_query = "SELECT COUNT(DISTINCT e.user_id) as total
                          FROM enrollments e
                          INNER JOIN courses c ON e.course_id = c.course_id
                          WHERE c.instructor_id = '$instructor_id'";
        $students_result = mysqli_query($con, $students_query);
        $overview['total_students'] = 0;
        if ($students_result) {
            $row = mysqli_fetch_assoc($students_result);
            $overview['total_students'] = (int)($row['total'] ?? 0);
        }
        
        // Engagement data (last 30 days)
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $label = date('M j', strtotime("-$i days"));
            $query = "SELECT COUNT(*) as cnt FROM enrollments e
                     INNER JOIN courses c ON e.course_id = c.course_id
                     WHERE c.instructor_id = '$instructor_id'
                     AND DATE(e.enrollment_date) = '$date'";
            $result = mysqli_query($con, $query);
            $cnt = 0;
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $cnt = $row['cnt'] ?? 0;
            }
            $engagement[] = ['label' => $label, 'value' => (int)$cnt];
        }
        
        // Completion by course
        $query = "SELECT c.title,
                        COUNT(e.enrollment_id) as total,
                        COUNT(CASE WHEN e.progress_percentage >= 100 THEN 1 END) as completed
                  FROM courses c
                  LEFT JOIN enrollments e ON c.course_id = e.course_id
                  WHERE c.instructor_id = '$instructor_id'
                  GROUP BY c.course_id
                  LIMIT 10";
        $result = mysqli_query($con, $query);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rate = $row['total'] > 0 ? round(($row['completed'] / $row['total']) * 100, 1) : 0;
                $completion[] = ['title' => $row['title'], 'rate' => $rate];
            }
        }
        
        echo json_encode([
            'success' => true,
            'engagement' => $engagement,
            'completion' => $completion
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
