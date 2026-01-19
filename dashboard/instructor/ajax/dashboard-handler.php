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
    case 'get_stats':
        // Get total students enrolled in instructor's courses
        $students_query = "SELECT COUNT(DISTINCT e.student_id) as total_students 
                          FROM enrollments e 
                          INNER JOIN courses c ON e.course_id = c.course_id 
                          WHERE c.instructor_id = '$instructor_id'";
        $students_result = mysqli_query($con, $students_query);
        $total_students = 0;
        if ($students_result) {
            $row = mysqli_fetch_assoc($students_result);
            $total_students = $row['total_students'] ?? 0;
        }
        
        // Get course counts
        $courses_query = "SELECT 
                            COUNT(*) as total_courses,
                            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_courses,
                            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_courses
                          FROM courses WHERE instructor_id = '$instructor_id'";
        $courses_result = mysqli_query($con, $courses_query);
        $total_courses = 0;
        $published_courses = 0;
        $draft_courses = 0;
        if ($courses_result) {
            $row = mysqli_fetch_assoc($courses_result);
            $total_courses = $row['total_courses'] ?? 0;
            $published_courses = $row['published_courses'] ?? 0;
            $draft_courses = $row['draft_courses'] ?? 0;
        }
        
        // Get total earnings from enrollments (using price_paid or course price)
        $earnings_query = "SELECT COALESCE(SUM(CASE WHEN e.price_paid > 0 THEN e.price_paid ELSE c.price END), 0) as total_earnings 
                          FROM enrollments e 
                          INNER JOIN courses c ON e.course_id = c.course_id
                          WHERE c.instructor_id = '$instructor_id' 
                          AND c.is_free = 0 AND c.price > 0";
        $earnings_result = mysqli_query($con, $earnings_query);
        $total_earnings = 0;
        if ($earnings_result) {
            $row = mysqli_fetch_assoc($earnings_result);
            $total_earnings = $row['total_earnings'] ?? 0;
        }
        
        // Get average rating from reviews table
        $rating_query = "SELECT AVG(r.rating) as avg_rating, COUNT(r.review_id) as total_reviews
                        FROM reviews r 
                        INNER JOIN courses c ON r.course_instructor_id = c.course_id 
                        WHERE c.instructor_id = '$instructor_id'
                        AND r.is_published = 1";
        $rating_result = mysqli_query($con, $rating_query);
        $avg_rating = 0;
        $total_reviews = 0;
        if ($rating_result) {
            $row = mysqli_fetch_assoc($rating_result);
            $avg_rating = $row['avg_rating'] ? round($row['avg_rating'], 1) : 0;
            $total_reviews = $row['total_reviews'] ?? 0;
        }
        
        // Get this month's stats
        $this_month = date('Y-m-01');
        $month_students_query = "SELECT COUNT(DISTINCT e.student_id) as month_students 
                                FROM enrollments e 
                                INNER JOIN courses c ON e.course_id = c.course_id 
                                WHERE c.instructor_id = '$instructor_id' 
                                AND e.enrollment_date >= '$this_month'";
        $month_result = mysqli_query($con, $month_students_query);
        $month_students = 0;
        if ($month_result) {
            $row = mysqli_fetch_assoc($month_result);
            $month_students = $row['month_students'] ?? 0;
        }
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_students' => (int)$total_students,
                'total_courses' => (int)$total_courses,
                'published_courses' => (int)$published_courses,
                'draft_courses' => (int)$draft_courses,
                'total_earnings' => (float)$total_earnings,
                'avg_rating' => (float)$avg_rating,
                'total_reviews' => (int)$total_reviews,
                'month_students' => (int)$month_students
            ]
        ]);
        break;
        
    case 'get_chart_data':
        $period = $_GET['period'] ?? 'monthly';
        
        // Enrollment trends for last 6 months
        $enrollment_data = [];
        for ($i = 5; $i >= 0; $i--) {
            $month_start = date('Y-m-01', strtotime("-$i months"));
            $month_end = date('Y-m-t', strtotime("-$i months"));
            $month_label = date('M Y', strtotime("-$i months"));
            
            $query = "SELECT COUNT(e.enrollment_id) as count 
                     FROM enrollments e 
                     INNER JOIN courses c ON e.course_id = c.course_id 
                     WHERE c.instructor_id = '$instructor_id' 
                     AND e.enrollment_date BETWEEN '$month_start' AND '$month_end 23:59:59'";
            $result = mysqli_query($con, $query);
            $count = 0;
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $count = $row['count'] ?? 0;
            }
            $enrollment_data[] = ['label' => $month_label, 'value' => (int)$count];
        }
        
        // Revenue trends for last 6 months (using enrollments with price)
        $revenue_data = [];
        for ($i = 5; $i >= 0; $i--) {
            $month_start = date('Y-m-01', strtotime("-$i months"));
            $month_end = date('Y-m-t', strtotime("-$i months"));
            $month_label = date('M Y', strtotime("-$i months"));
            
            $query = "SELECT COALESCE(SUM(CASE WHEN e.price_paid > 0 THEN e.price_paid ELSE c.price END), 0) as total 
                     FROM enrollments e 
                     INNER JOIN courses c ON e.course_id = c.course_id 
                     WHERE c.instructor_id = '$instructor_id' 
                     AND c.is_free = 0 AND c.price > 0
                     AND e.enrollment_date BETWEEN '$month_start' AND '$month_end 23:59:59'";
            $result = mysqli_query($con, $query);
            $total = 0;
            if ($result) {
                $row = mysqli_fetch_assoc($result);
                $total = $row['total'] ?? 0;
            }
            $revenue_data[] = ['label' => $month_label, 'value' => (float)$total];
        }
        
        // Course performance
        $course_performance = [];
        $perf_query = "SELECT c.title, 
                              COUNT(e.enrollment_id) as enrollments,
                              COALESCE(AVG(r.rating), 0) as avg_rating
                       FROM courses c 
                       LEFT JOIN enrollments e ON c.course_id = e.course_id
                       LEFT JOIN reviews r ON c.course_id = r.course_instructor_id AND r.is_published = 1
                       WHERE c.instructor_id = '$instructor_id'
                       GROUP BY c.course_id
                       ORDER BY enrollments DESC
                       LIMIT 5";
        $perf_result = mysqli_query($con, $perf_query);
        if ($perf_result) {
            while ($row = mysqli_fetch_assoc($perf_result)) {
                $course_performance[] = [
                    'title' => $row['title'],
                    'enrollments' => (int)$row['enrollments'],
                    'rating' => round($row['avg_rating'], 1)
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'charts' => [
                'enrollment' => $enrollment_data,
                'revenue' => $revenue_data,
                'course_performance' => $course_performance
            ]
        ]);
        break;
        
    case 'get_activity':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $activities = [];
        
        // Get recent enrollments
        $enroll_query = "SELECT e.enrollment_date, u.first_name, u.last_name, c.title as course_title
                        FROM enrollments e
                        INNER JOIN users u ON e.student_id = u.user_id
                        INNER JOIN courses c ON e.course_id = c.course_id
                        WHERE c.instructor_id = '$instructor_id'
                        ORDER BY e.enrollment_date DESC
                        LIMIT $limit";
        $enroll_result = mysqli_query($con, $enroll_query);
        if ($enroll_result) {
            while ($row = mysqli_fetch_assoc($enroll_result)) {
                $activities[] = [
                    'type' => 'enrollment',
                    'icon' => 'person_add',
                    'message' => trim($row['first_name'] . ' ' . $row['last_name']) . ' enrolled in ' . $row['course_title'],
                    'time' => $row['enrollment_date'],
                    'time_ago' => timeAgo($row['enrollment_date'])
                ];
            }
        }
        
        // Get recent reviews
        $review_query = "SELECT r.created_at, r.rating, r.review_text, u.first_name, u.last_name, c.title as course_title
                        FROM reviews r
                        INNER JOIN users u ON r.student_id = u.user_id
                        INNER JOIN courses c ON r.course_instructor_id = c.course_id
                        WHERE c.instructor_id = '$instructor_id'
                        AND r.is_published = 1
                        ORDER BY r.created_at DESC
                        LIMIT $limit";
        $review_result = mysqli_query($con, $review_query);
        if ($review_result) {
            while ($row = mysqli_fetch_assoc($review_result)) {
                $activities[] = [
                    'type' => 'review',
                    'icon' => 'star',
                    'message' => trim($row['first_name'] . ' ' . $row['last_name']) . ' rated ' . $row['course_title'] . ' ' . $row['rating'] . ' stars',
                    'time' => $row['created_at'],
                    'time_ago' => timeAgo($row['created_at'])
                ];
            }
        }
        
        // Sort by time descending
        usort($activities, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
        
        // Limit total activities
        $activities = array_slice($activities, 0, $limit);
        
        echo json_encode(['success' => true, 'activities' => $activities]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

// Helper function for time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}
?>
