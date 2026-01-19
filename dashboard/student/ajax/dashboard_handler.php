<?php
session_start();
header('Content-Type: application/json');
require_once '../../../includes/config.php';
require_once '../../../includes/functions.php';
if (!$is_logged_in) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$student_id = $current_user_id;
$action = $_GET['action'] ?? $_POST['action'] ?? '';
switch ($action) {
    case 'get_dashboard_stats':
        getDashboardStats($con, $student_id);
        break;
    case 'get_continue_learning':
        getContinueLearning($con, $student_id);
        break;
    case 'get_recommendations':
        getRecommendations($con, $student_id);
        break;
    case 'get_certificates':
        getCertificates($con, $student_id);
        break;
    case 'get_notes':
        getNotes($con, $student_id);
        break;
    case 'get_recent_activity':
        getRecentActivity($con, $student_id);
        break;
    case 'get_learning_analytics':
        getLearningAnalytics($con, $student_id);
        break;
    case 'get_notifications':
        getNotifications($con, $student_id);
        break;
    case 'get_all_courses':
        getAllCourses($con, $student_id);
        break;
    case 'get_streak_data':
        getStreakData($con, $student_id);
        break;
    case 'save_note':
        saveNote($con, $student_id);
        break;
    case 'get_note':
        getNote($con, $student_id);
        break;
    case 'toggle_note_star':
        toggleNoteStar($con, $student_id);
        break;
    case 'delete_note':
        deleteNote($con, $student_id);
        break;
    case 'get_enrolled_courses':
        getEnrolledCourses($con, $student_id);
        break;
    case 'mark_notification_read':
        markNotificationRead($con, $student_id);
        break;
    case 'mark_all_notifications_read':
        markAllNotificationsRead($con, $student_id);
        break;
    case 'get_profile':
        getProfile($con, $student_id);
        break;
    case 'update_profile':
        updateProfile($con, $student_id);
        break;
    case 'upload_avatar':
        uploadAvatar($con, $student_id);
        break;
    case 'change_password':
        changePassword($con, $student_id);
        break;
    case 'delete_account':
        deleteAccount($con, $student_id);
        break;
    case 'get_social_links':
        getSocialLinks($con, $student_id);
        break;
    case 'save_social_links':
        saveSocialLinks($con, $student_id);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
function getDashboardStats($con, $student_id) {
    try {
        $sql = "SELECT COUNT(*) as total FROM enrollments WHERE student_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $total_courses = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        
        $sql = "SELECT COUNT(*) as completed FROM enrollments WHERE student_id = ? AND status = 'completed'";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $completed_courses = $stmt->get_result()->fetch_assoc()['completed'] ?? 0;
        
        $sql = "SELECT COALESCE(SUM(total_learning_time_seconds), 0) as total_time FROM enrollments WHERE student_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $total_seconds = $stmt->get_result()->fetch_assoc()['total_time'] ?? 0;
        $learning_hours = round($total_seconds / 3600, 1);
        
        // Count completed lectures from lecture_progress table
        $sql = "SELECT COUNT(*) as lectures FROM lecture_progress WHERE student_id = ? AND is_completed = 1";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $lectures_completed = $stmt->get_result()->fetch_assoc()['lectures'] ?? 0;
        
        $sql = "SELECT COUNT(*) as certs FROM certificates WHERE student_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $certificates_count = $stmt->get_result()->fetch_assoc()['certs'] ?? 0;
        
        $sql = "SELECT COALESCE(AVG(progress_percentage), 0) as avg_progress FROM enrollments WHERE student_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $avg_progress = round($stmt->get_result()->fetch_assoc()['avg_progress'] ?? 0);
        
        // Calculate current streak
        $streak = 0;
        $check_date = date('Y-m-d');
        for ($i = 0; $i < 365; $i++) {
            $sql = "SELECT COUNT(*) as count FROM enrollments 
                    WHERE student_id = ? AND DATE(last_accessed_at) = ?";
            $stmt = $con->prepare($sql);
            $stmt->bind_param("ss", $student_id, $check_date);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result['count'] > 0) {
                $streak++;
                $check_date = date('Y-m-d', strtotime("$check_date -1 day"));
            } else {
                break;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_courses' => (int)$total_courses,
                'completed_courses' => (int)$completed_courses,
                'in_progress' => (int)$total_courses - (int)$completed_courses,
                'learning_hours' => $learning_hours,
                'lectures_completed' => (int)$lectures_completed,
                'certificates_count' => (int)$certificates_count,
                'average_progress' => $avg_progress,
                'current_streak' => $streak
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function getContinueLearning($con, $student_id) {
    try {
        $sql = "SELECT 
                    e.enrollment_id,
                    e.course_id,
                    e.progress_percentage,
                    e.lectures_completed,
                    e.last_accessed_at,
                    c.title as course_title,
                    c.thumbnail_url,
                    c.description as short_description,
                    cat.name as category_name,
                    CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
                    (SELECT COUNT(*) FROM lectures l JOIN course_sections cs ON l.section_id = cs.section_id WHERE cs.course_id = c.course_id) as total_lectures,
                    (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) as average_rating,
                    (SELECT COALESCE(SUM(l.duration_seconds), 0) FROM lectures l JOIN course_sections cs ON l.section_id = cs.section_id WHERE cs.course_id = c.course_id) as total_duration_seconds
                FROM enrollments e
                JOIN courses c ON e.course_id = c.course_id
                LEFT JOIN categories cat ON c.category_id = cat.category_id
                LEFT JOIN users u ON c.instructor_id = u.user_id
                WHERE e.student_id = ? 
                AND e.status = 'active'
                AND e.progress_percentage < 100
                ORDER BY e.last_accessed_at DESC
                LIMIT 6";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $courses = [];
        while ($row = $result->fetch_assoc()) {
            $total_seconds = (int)$row['total_duration_seconds'];
            $total_minutes = round($total_seconds / 60);
            
            $courses[] = [
                'enrollment_id' => $row['enrollment_id'],
                'course_id' => $row['course_id'],
                'title' => $row['course_title'],
                'thumbnail' => $row['thumbnail_url'],
                'description' => $row['short_description'],
                'category' => $row['category_name'] ?? 'General',
                'instructor' => $row['instructor_name'] ?? 'Instructor',
                'progress' => (int)$row['progress_percentage'],
                'lectures_completed' => (int)$row['lectures_completed'],
                'total_lectures' => (int)$row['total_lectures'],
                'last_accessed' => $row['last_accessed_at'],
                'rating' => round((float)$row['average_rating'], 1),
                'duration_minutes' => $total_minutes
            ];
        }
        echo json_encode(['success' => true, 'data' => $courses]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function getRecommendations($con, $student_id) {
    try {
        $sql = "SELECT DISTINCT c.category_id 
                FROM enrollments e 
                JOIN courses c ON e.course_id = c.course_id 
                WHERE e.student_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $enrolled_categories = [];
        while ($row = $result->fetch_assoc()) {
            $enrolled_categories[] = $row['category_id'];
        }
        $recommendations = [];
        if (!empty($enrolled_categories)) {
            $placeholders = str_repeat('?,', count($enrolled_categories) - 1) . '?';
            $sql = "SELECT 
                        c.course_id,
                        c.title,
                        c.thumbnail_url,
                        c.description as short_description,
                        c.price,
                        c.enrollment_count,
                        cat.name as category_name,
                        CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
                        (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) as average_rating,
                        (SELECT COUNT(*) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) as total_reviews
                    FROM courses c
                    LEFT JOIN categories cat ON c.category_id = cat.category_id
                    LEFT JOIN users u ON c.instructor_id = u.user_id
                    WHERE c.category_id IN ($placeholders)
                    AND c.course_id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ?)
                    AND c.status = 'published'
                    ORDER BY c.enrollment_count DESC
                    LIMIT 4";
            $stmt = $con->prepare($sql);
            $params = array_merge($enrolled_categories, [$student_id]);
            $types = str_repeat('i', count($enrolled_categories)) . 's';
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $recommendations[] = [
                    'course_id' => $row['course_id'],
                    'title' => $row['title'],
                    'thumbnail' => $row['thumbnail_url'],
                    'description' => $row['short_description'],
                    'category' => $row['category_name'] ?? 'General',
                    'instructor' => $row['instructor_name'] ?? 'Instructor',
                    'price' => $row['price'],
                    'rating' => round((float)$row['average_rating'], 1),
                    'reviews' => (int)$row['total_reviews'],
                    'students' => (int)$row['enrollment_count']
                ];
            }
        }
        if (count($recommendations) < 4) {
            $limit = 4 - count($recommendations);
            $existing_ids = array_column($recommendations, 'course_id');
            $existing_ids_str = empty($existing_ids) ? "''" : "'" . implode("','", $existing_ids) . "'";
            $sql = "SELECT 
                        c.course_id,
                        c.title,
                        c.thumbnail_url,
                        c.description as short_description,
                        c.price,
                        c.enrollment_count,
                        cat.name as category_name,
                        CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
                        (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) as average_rating,
                        (SELECT COUNT(*) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) as total_reviews
                    FROM courses c
                    LEFT JOIN categories cat ON c.category_id = cat.category_id
                    LEFT JOIN users u ON c.instructor_id = u.user_id
                    WHERE c.course_id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ?)
                    AND c.course_id NOT IN ($existing_ids_str)
                    AND c.status = 'published'
                    ORDER BY c.enrollment_count DESC
                    LIMIT ?";
            $stmt = $con->prepare($sql);
            $stmt->bind_param("si", $student_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $recommendations[] = [
                    'course_id' => $row['course_id'],
                    'title' => $row['title'],
                    'thumbnail' => $row['thumbnail_url'],
                    'description' => $row['short_description'],
                    'category' => $row['category_name'] ?? 'General',
                    'instructor' => $row['instructor_name'] ?? 'Instructor',
                    'price' => $row['price'],
                    'rating' => round((float)$row['average_rating'], 1),
                    'reviews' => (int)$row['total_reviews'],
                    'students' => (int)($row['enrollment_count'] ?? 0)
                ];
            }
        }
        echo json_encode(['success' => true, 'data' => $recommendations]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function getCertificates($con, $student_id) {
    try {
        $sql = "SELECT 
                    cert.certificate_id,
                    cert.certificate_number,
                    cert.issued_date,
                    cert.final_grade,
                    c.title as course_title,
                    c.thumbnail_url,
                    cat.name as category_name,
                    CONCAT(u.first_name, ' ', u.last_name) as instructor_name
                FROM certificates cert
                JOIN courses c ON cert.course_id = c.course_id
                LEFT JOIN categories cat ON c.category_id = cat.category_id
                LEFT JOIN users u ON c.instructor_id = u.user_id
                WHERE cert.student_id = ?
                ORDER BY cert.issued_date DESC";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $certificates = [];
        while ($row = $result->fetch_assoc()) {
            $certificates[] = [
                'certificate_id' => $row['certificate_id'],
                'certificate_number' => $row['certificate_number'],
                'course_title' => $row['course_title'],
                'thumbnail' => $row['thumbnail_url'],
                'category' => $row['category_name'] ?? 'General',
                'instructor' => $row['instructor_name'] ?? 'Instructor',
                'issued_date' => date('F j, Y', strtotime($row['issued_date'])),
                'grade' => $row['final_grade']
            ];
        }
        echo json_encode(['success' => true, 'data' => $certificates]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function getNotes($con, $student_id) {
    try {
        $filter = $_GET['filter'] ?? 'all';
        $search = $_GET['search'] ?? '';
        $sort = $_GET['sort'] ?? 'modified';
        $where = "WHERE n.student_id = ?";
        $params = [$student_id];
        $types = "s";
        if (!empty($search)) {
            $where .= " AND (n.note_title LIKE ? OR n.note_content LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= "ss";
        }
        $order = match($sort) {
            'created' => 'n.created_at DESC',
            'title' => 'n.note_title ASC',
            default => 'n.updated_at DESC'
        };
        $sql = "SELECT 
                    n.note_id,
                    n.course_id,
                    n.note_title,
                    n.note_content,
                    n.is_public,
                    n.created_at,
                    n.updated_at,
                    c.title as course_title,
                    l.title as lecture_title
                FROM course_notes n
                LEFT JOIN courses c ON n.course_id = c.course_id
                LEFT JOIN lectures l ON n.lecture_id = l.lecture_id
                $where
                ORDER BY $order
                LIMIT 50";
        $stmt = $con->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $notes = [];
        while ($row = $result->fetch_assoc()) {
            $notes[] = [
                'note_id' => $row['note_id'],
                'course_id' => $row['course_id'],
                'title' => $row['note_title'],
                'content' => $row['note_content'],
                'is_starred' => false,
                'is_archived' => false,
                'course' => $row['course_title'],
                'lecture' => $row['lecture_title'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'time_ago' => timeAgo($row['updated_at'])
            ];
        }
        echo json_encode(['success' => true, 'data' => $notes]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function getRecentActivity($con, $student_id) {
    try {
        $activities = [];
        $sql = "SELECT 
                    e.last_accessed_at,
                    c.title as course_title,
                    e.progress_percentage
                FROM enrollments e
                JOIN courses c ON e.course_id = c.course_id
                WHERE e.student_id = ?
                ORDER BY e.last_accessed_at DESC
                LIMIT 5";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $activities[] = [
                'type' => 'progress',
                'icon' => 'school',
                'message' => "Studied <strong>{$row['course_title']}</strong>",
                'detail' => "{$row['progress_percentage']}% complete",
                'time' => timeAgo($row['last_accessed_at']),
                'timestamp' => $row['last_accessed_at']
            ];
        }
        $sql = "SELECT 
                    n.created_at,
                    n.note_title,
                    c.title as course_title
                FROM course_notes n
                LEFT JOIN courses c ON n.course_id = c.course_id
                WHERE n.student_id = ?
                ORDER BY n.created_at DESC
                LIMIT 3";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $activities[] = [
                'type' => 'note',
                'icon' => 'note',
                'message' => "<strong>Created note:</strong> {$row['note_title']}",
                'detail' => $row['course_title'],
                'time' => timeAgo($row['created_at']),
                'timestamp' => $row['created_at']
            ];
        }
        $sql = "SELECT 
                    cert.issued_date,
                    c.title as course_title
                FROM certificates cert
                JOIN courses c ON cert.course_id = c.course_id
                WHERE cert.student_id = ?
                ORDER BY cert.issued_date DESC
                LIMIT 2";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $activities[] = [
                'type' => 'certificate',
                'icon' => 'workspace_premium',
                'message' => "<strong>Earned certificate:</strong> {$row['course_title']}",
                'detail' => 'Congratulations!',
                'time' => timeAgo($row['issued_date']),
                'timestamp' => $row['issued_date']
            ];
        }
        usort($activities, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        $activities = array_slice($activities, 0, 10);
        echo json_encode(['success' => true, 'data' => $activities]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function getLearningAnalytics($con, $student_id) {
    try {
        $period = $_GET['period'] ?? 'month';
        $days = match($period) {
            'week' => 7,
            'year' => 365,
            default => 30
        };
        $activity_data = [];
        $labels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M j', strtotime($date));
            $sql = "SELECT COUNT(*) * 30 as minutes FROM enrollments 
                    WHERE student_id = ? AND DATE(last_accessed_at) = ?";
            $stmt = $con->prepare($sql);
            $stmt->bind_param("ss", $student_id, $date);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $activity_data[] = (int)($result['minutes'] ?? 0);
        }
        $sql = "SELECT 
                    SUM(CASE WHEN progress_percentage = 100 THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN progress_percentage BETWEEN 50 AND 99 THEN 1 ELSE 0 END) as advanced,
                    SUM(CASE WHEN progress_percentage BETWEEN 1 AND 49 THEN 1 ELSE 0 END) as started,
                    SUM(CASE WHEN progress_percentage = 0 THEN 1 ELSE 0 END) as not_started
                FROM enrollments WHERE student_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $progress = $stmt->get_result()->fetch_assoc();
        $sql = "SELECT 
                    COALESCE(cat.name, 'General') as category,
                    AVG(e.progress_percentage) as proficiency
                FROM enrollments e
                JOIN courses c ON e.course_id = c.course_id
                LEFT JOIN categories cat ON c.category_id = cat.category_id
                WHERE e.student_id = ?
                GROUP BY cat.category_id, cat.name
                LIMIT 6";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $skills_labels = [];
        $skills_data = [];
        while ($row = $result->fetch_assoc()) {
            $skills_labels[] = $row['category'];
            $skills_data[] = round($row['proficiency']);
        }
        echo json_encode([
            'success' => true,
            'data' => [
                'activity' => [
                    'labels' => $labels,
                    'data' => $activity_data
                ],
                'progress' => [
                    'completed' => (int)($progress['completed'] ?? 0),
                    'advanced' => (int)($progress['advanced'] ?? 0),
                    'started' => (int)($progress['started'] ?? 0),
                    'not_started' => (int)($progress['not_started'] ?? 0)
                ],
                'skills' => [
                    'labels' => $skills_labels,
                    'data' => $skills_data
                ]
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function getNotifications($con, $user_id) {
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        
        $query = "SELECT notification_id, notification_type, title, message, link_url, related_entity_type, 
                  related_entity_id, is_read, read_at, priority, created_at
                  FROM notifications
                  WHERE user_id = '$user_id'
                  ORDER BY priority DESC, created_at DESC
                  LIMIT $limit";
        
        $result = mysqli_query($con, $query);
        
        $notifications = [];
        $unread_count = 0;
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $icon = match($row['notification_type'] ?? 'info') {
                    'course_update' => 'school',
                    'new_lecture' => 'video_library',
                    'certificate' => 'workspace_premium',
                    'enrollment' => 'how_to_reg',
                    'payment' => 'payment',
                    'reminder' => 'notifications_active',
                    'announcement' => 'campaign',
                    'review' => 'rate_review',
                    'achievement' => 'emoji_events',
                    default => 'notifications'
                };
                
                $notifications[] = [
                    'id' => $row['notification_id'],
                    'type' => $row['notification_type'],
                    'icon' => $icon,
                    'title' => $row['title'],
                    'message' => $row['message'],
                    'link' => $row['link_url'],
                    'time' => timeAgo($row['created_at']),
                    'read' => (bool)$row['is_read'],
                    'priority' => $row['priority'],
                    'created_at' => $row['created_at']
                ];
                
                if (!$row['is_read']) {
                    $unread_count++;
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $notifications,
            'unread_count' => $unread_count
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function getAllCourses($con, $student_id) {
    try {
        $filter = $_GET['filter'] ?? 'all';
        $where_conditions = [];
        if ($filter === 'in-progress') {
            $where_conditions[] = "e.enrollment_id IS NOT NULL AND e.status = 'active' AND e.progress_percentage < 100";
        } elseif ($filter === 'completed') {
            $where_conditions[] = "e.enrollment_id IS NOT NULL AND e.progress_percentage = 100";
        } elseif ($filter === 'wishlist') {
            $where_conditions[] = "w.wishlist_id IS NOT NULL AND e.enrollment_id IS NULL";
        }
        $where = "WHERE (e.student_id = ? OR w.user_id = ?)";
        if (!empty($where_conditions)) {
            $where .= " AND (" . implode(' OR ', $where_conditions) . ")";
        }
        $sql = "SELECT 
                    c.course_id,
                    c.title,
                    c.thumbnail_url,
                    c.description as short_description,
                    ROUND((SELECT COALESCE(SUM(l.duration_seconds), 0) FROM lectures l JOIN course_sections cs ON l.section_id = cs.section_id WHERE cs.course_id = c.course_id) / 3600, 1) as duration_hours,
                    cat.name as category_name,
                    CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
                    e.enrollment_id,
                    e.progress_percentage,
                    e.status as enrollment_status,
                    e.enrollment_date,
                    e.last_accessed_at,
                    w.wishlist_id,
                    w.added_at as wishlist_date,
                    (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) as average_rating,
                    (SELECT COUNT(*) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) as total_reviews,
                    CASE 
                        WHEN e.enrollment_id IS NOT NULL THEN 'enrolled'
                        WHEN w.wishlist_id IS NOT NULL THEN 'wishlist'
                        ELSE 'other'
                    END as course_status
                FROM courses c
                LEFT JOIN enrollments e ON c.course_id = e.course_id AND e.student_id = ?
                LEFT JOIN wishlists w ON c.course_id = w.course_id AND w.user_id = ?
                LEFT JOIN categories cat ON c.category_id = cat.category_id
                LEFT JOIN users u ON c.instructor_id = u.user_id
                $where
                ORDER BY 
                    CASE WHEN e.enrollment_id IS NOT NULL THEN e.last_accessed_at ELSE w.added_at END DESC";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("ssss", $student_id, $student_id, $student_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $courses = [];
        while ($row = $result->fetch_assoc()) {
            $is_enrolled = !empty($row['enrollment_id']);
            $is_wishlisted = !empty($row['wishlist_id']);
            if ($is_enrolled) {
                $status = 'in-progress';
                if ($row['progress_percentage'] == 100) {
                    $status = 'completed';
                } elseif ($row['progress_percentage'] == 0) {
                    $status = 'not-started';
                }
                $progress = (int)$row['progress_percentage'];
                $enrolled_at = date('M j, Y', strtotime($row['enrollment_date']));
            } else {
                $status = 'wishlist';
                $progress = 0;
                $enrolled_at = null;
            }
            // Calculate duration display
            $duration_hours = (float)($row['duration_hours'] ?? 0);
            if ($duration_hours >= 1) {
                $duration_display = round($duration_hours, 1) . ' hours';
            } else {
                $duration_minutes = round($duration_hours * 60);
                $duration_display = $duration_minutes . ' mins';
            }
            
            $courses[] = [
                'enrollment_id' => $row['enrollment_id'],
                'course_id' => $row['course_id'],
                'title' => $row['title'],
                'thumbnail' => $row['thumbnail_url'],
                'description' => $row['short_description'],
                'category' => $row['category_name'] ?? 'General',
                'instructor' => $row['instructor_name'] ?? 'Instructor',
                'rating' => round((float)($row['average_rating'] ?? 0), 1),
                'total_reviews' => (int)($row['total_reviews'] ?? 0),
                'duration' => $duration_display,
                'progress' => $progress,
                'status' => $status,
                'enrolled_at' => $enrolled_at,
                'is_enrolled' => $is_enrolled,
                'is_wishlisted' => $is_wishlisted
            ];
        }
        echo json_encode(['success' => true, 'data' => $courses]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function getStreakData($con, $student_id) {
    try {
        $streak = 0;
        $check_date = date('Y-m-d');
        for ($i = 0; $i < 365; $i++) {
            $sql = "SELECT COUNT(*) as count FROM enrollments 
                    WHERE student_id = ? AND DATE(last_accessed_at) = ?";
            $stmt = $con->prepare($sql);
            $stmt->bind_param("ss", $student_id, $check_date);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            if ($result['count'] > 0) {
                $streak++;
                $check_date = date('Y-m-d', strtotime("$check_date -1 day"));
            } else {
                break;
            }
        }
        $weekly = [];
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $day_name = date('D', strtotime($date));
            $sql = "SELECT COUNT(*) as count FROM enrollments 
                    WHERE student_id = ? AND DATE(last_accessed_at) = ?";
            $stmt = $con->prepare($sql);
            $stmt->bind_param("ss", $student_id, $date);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $weekly[] = [
                'day' => substr($day_name, 0, 1),
                'completed' => $result['count'] > 0,
                'is_today' => $date === date('Y-m-d')
            ];
        }
        
        // Get total learning time from enrollments
        $sql = "SELECT COALESCE(SUM(total_learning_time_seconds), 0) as total_time FROM enrollments WHERE student_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $time_result = $stmt->get_result()->fetch_assoc();
        $total_time = $time_result['total_time'] ?? 0;
        
        // Get total completed lectures from lecture_progress
        $sql = "SELECT COUNT(*) as total_lessons FROM lecture_progress WHERE student_id = ? AND is_completed = 1";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $lessons_result = $stmt->get_result()->fetch_assoc();
        $total_lessons = $lessons_result['total_lessons'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'current_streak' => $streak,
                'longest_streak' => max($streak, 14),
                'weekly' => $weekly,
                'total_time' => round($total_time / 3600) . 'h',
                'total_lessons' => (int)$total_lessons
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function saveNote($con, $student_id) {
    try {
        $note_id = $_POST['note_id'] ?? null;
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $course_id = $_POST['course_id'] ?? '';
        $lecture_id = $_POST['lecture_id'] ?? null;
        if (empty($note_id) || $note_id === 'null' || $note_id === 'undefined') {
            $note_id = null;
        }
        if (empty($course_id) || $course_id === 'null' || $course_id === 'undefined') {
            $course_id = '';
        }
        if (empty($lecture_id) || $lecture_id === 'null' || $lecture_id === 'undefined') {
            $lecture_id = null;
        }
        if (empty($content)) {
            $content = ' ';
        }
        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Title is required']);
            return;
        }
        if (empty($course_id)) {
            echo json_encode(['success' => false, 'error' => 'Please select a course for this note']);
            return;
        }
        if ($note_id !== null && $note_id !== '') {
            if ($lecture_id === null) {
                $sql = "UPDATE course_notes SET note_title = ?, note_content = ?, course_id = ?, lecture_id = NULL, updated_at = NOW() 
                        WHERE note_id = ? AND student_id = ?";
                $stmt = $con->prepare($sql);
                $stmt->bind_param("sssis", $title, $content, $course_id, $note_id, $student_id);
            } else {
                $sql = "UPDATE course_notes SET note_title = ?, note_content = ?, course_id = ?, lecture_id = ?, updated_at = NOW() 
                        WHERE note_id = ? AND student_id = ?";
                $stmt = $con->prepare($sql);
                $stmt->bind_param("sssiis", $title, $content, $course_id, $lecture_id, $note_id, $student_id);
            }
        } else {
            if ($lecture_id === null) {
                $sql = "INSERT INTO course_notes (student_id, course_id, note_title, note_content, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, NOW(), NOW())";
                $stmt = $con->prepare($sql);
                $stmt->bind_param("ssss", $student_id, $course_id, $title, $content);
            } else {
                $sql = "INSERT INTO course_notes (student_id, course_id, lecture_id, note_title, note_content, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $con->prepare($sql);
                $stmt->bind_param("ssiss", $student_id, $course_id, $lecture_id, $title, $content);
            }
        }
        $stmt->execute();
        if ($stmt->error) {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
            return;
        }
        echo json_encode([
            'success' => true,
            'note_id' => $note_id ?? $con->insert_id
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function getEnrolledCourses($con, $student_id) {
    try {
        $sql = "SELECT 
                    c.course_id,
                    c.title as course_title
                FROM enrollments e
                JOIN courses c ON e.course_id = c.course_id
                WHERE e.student_id = ? 
                AND e.status IN ('active', 'completed')
                ORDER BY c.title ASC";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $courses = [];
        while ($row = $result->fetch_assoc()) {
            $courses[] = [
                'course_id' => $row['course_id'],
                'title' => $row['course_title']
            ];
        }
        echo json_encode(['success' => true, 'data' => $courses]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function getNote($con, $student_id) {
    try {
        $note_id = $_GET['note_id'] ?? null;
        if (!$note_id) {
            echo json_encode(['success' => false, 'error' => 'Note ID is required']);
            return;
        }
        $sql = "SELECT n.*, c.title as course_title 
                FROM course_notes n
                LEFT JOIN courses c ON n.course_id = c.course_id
                WHERE n.note_id = ? AND n.student_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("is", $note_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($note = $result->fetch_assoc()) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'note_id' => $note['note_id'],
                    'title' => $note['note_title'],
                    'content' => $note['note_content'],
                    'course_id' => $note['course_id'],
                    'lecture_id' => $note['lecture_id'],
                    'course' => $note['course_title']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Note not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function toggleNoteStar($con, $student_id) {
    try {
        $note_id = $_POST['note_id'] ?? null;
        if (!$note_id) {
            echo json_encode(['success' => false, 'error' => 'Note ID is required']);
            return;
        }
        $sql = "SHOW COLUMNS FROM course_notes LIKE 'is_starred'";
        $result = $con->query($sql);
        if ($result->num_rows > 0) {
            $sql = "UPDATE course_notes SET is_starred = NOT COALESCE(is_starred, 0) 
                    WHERE note_id = ? AND student_id = ?";
            $stmt = $con->prepare($sql);
            $stmt->bind_param("is", $note_id, $student_id);
            $stmt->execute();
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function deleteNote($con, $student_id) {
    try {
        $note_id = $_POST['note_id'] ?? null;
        if (!$note_id) {
            echo json_encode(['success' => false, 'error' => 'Note ID is required']);
            return;
        }
        $sql = "DELETE FROM course_notes WHERE note_id = ? AND student_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("is", $note_id, $student_id);
        $stmt->execute();
        echo json_encode(['success' => $stmt->affected_rows > 0]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function markNotificationRead($con, $user_id) {
    try {
        $notification_id = $_POST['notification_id'] ?? null;
        
        if (!$notification_id) {
            echo json_encode(['success' => false, 'error' => 'Notification ID required']);
            return;
        }
        
        $notification_id = mysqli_real_escape_string($con, $notification_id);
        $user_id_escaped = mysqli_real_escape_string($con, $user_id);
        
        $query = "UPDATE notifications 
                  SET is_read = 1, read_at = NOW() 
                  WHERE notification_id = '$notification_id' AND user_id = '$user_id_escaped'";
        
        mysqli_query($con, $query);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function markAllNotificationsRead($con, $user_id) {
    try {
        $user_id_escaped = mysqli_real_escape_string($con, $user_id);
        
        $query = "UPDATE notifications 
                  SET is_read = 1, read_at = NOW() 
                  WHERE user_id = '$user_id_escaped' AND is_read = 0";
        
        mysqli_query($con, $query);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) {
        if ($diff->d == 1) return 'Yesterday';
        if ($diff->d < 7) return $diff->d . ' days ago';
        return floor($diff->d / 7) . ' week' . (floor($diff->d / 7) > 1 ? 's' : '') . ' ago';
    }
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}
function formatDuration($minutes) {
    if ($minutes < 60) return $minutes . ' mins';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours . 'h' . ($mins > 0 ? ' ' . $mins . 'm' : '');
}
function getProfile($con, $student_id) {
    try {
        $sql = "SELECT user_id, email, username, first_name, last_name, phone, country_code, 
                       blood_group, date_of_birth, gender, profile_image_url, bio, headline,
                       occupation, company, location, country, timezone, language,
                       email_verified, created_at
                FROM users WHERE user_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function updateProfile($con, $student_id) {
    try {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $country_code = trim($_POST['country_code'] ?? '+880');
        $bio = trim($_POST['bio'] ?? '');
        $headline = trim($_POST['headline'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $country = trim($_POST['country'] ?? 'Bangladesh');
        $date_of_birth = $_POST['date_of_birth'] ?? null;
        $gender = $_POST['gender'] ?? null;
        $blood_group = $_POST['blood_group'] ?? null;
        $timezone = $_POST['timezone'] ?? 'Asia/Dhaka';
        $language = $_POST['language'] ?? 'en';
        if (empty($first_name)) {
            echo json_encode(['success' => false, 'error' => 'First name is required']);
            return;
        }
        if (empty($date_of_birth)) {
            $date_of_birth = null;
        }
        if (empty($gender)) {
            $gender = null;
        }
        if (empty($blood_group)) {
            $blood_group = null;
        }
        $sql = "UPDATE users SET 
                    first_name = ?, last_name = ?, phone = ?, country_code = ?,
                    bio = ?, headline = ?, occupation = ?, company = ?,
                    location = ?, country = ?, date_of_birth = ?, gender = ?,
                    blood_group = ?, timezone = ?, language = ?, updated_at = NOW()
                WHERE user_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("ssssssssssssssss", 
            $first_name, $last_name, $phone, $country_code,
            $bio, $headline, $occupation, $company,
            $location, $country, $date_of_birth, $gender,
            $blood_group, $timezone, $language, $student_id
        );
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update profile']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function uploadAvatar($con, $student_id) {
    try {
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
            return;
        }
        $file = $_FILES['avatar'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP']);
            return;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'File size must be less than 5MB']);
            return;
        }
        $upload_dir = '../../../public/uploads/profiles/' . $student_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile.' . $extension;
        $filepath = $upload_dir . $filename;
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $relative_path = 'public/uploads/profiles/' . $student_id . '/' . $filename;
            $sql = "UPDATE users SET profile_image_url = ?, updated_at = NOW() WHERE user_id = ?";
            $stmt = $con->prepare($sql);
            $stmt->bind_param("ss", $relative_path, $student_id);
            $stmt->execute();
            echo json_encode(['success' => true, 'avatar_url' => $relative_path . '?t=' . time()]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save file']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function changePassword($con, $student_id) {
    try {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            echo json_encode(['success' => false, 'error' => 'All password fields are required']);
            return;
        }
        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'error' => 'New passwords do not match']);
            return;
        }
        if (strlen($new_password) < 8) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']);
            return;
        }
        $sql = "SELECT password_hash FROM users WHERE user_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (!password_verify($current_password, $row['password_hash'])) {
                echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
                return;
            }
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?";
            $stmt = $con->prepare($sql);
            $stmt->bind_param("ss", $new_hash, $student_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update password']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function deleteAccount($con, $student_id) {
    try {
        $password = $_POST['password'] ?? '';
        if (empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Password is required to delete account']);
            return;
        }
        $sql = "SELECT password_hash FROM users WHERE user_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (!password_verify($password, $row['password_hash'])) {
                echo json_encode(['success' => false, 'error' => 'Password is incorrect']);
                return;
            }
            $sql = "UPDATE users SET deleted_at = NOW(), status = 'inactive' WHERE user_id = ?";
            $stmt = $con->prepare($sql);
            $stmt->bind_param("s", $student_id);
            if ($stmt->execute()) {
                session_destroy();
                echo json_encode(['success' => true, 'message' => 'Account deleted successfully', 'redirect' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete account']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function getSocialLinks($con, $student_id) {
    try {
        $sql = "SELECT social_link_id, platform, url, display_order, is_public 
                FROM user_social_links 
                WHERE user_id = ? 
                ORDER BY display_order ASC";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $links = [];
        while ($row = $result->fetch_assoc()) {
            $links[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $links]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
function saveSocialLinks($con, $student_id) {
    try {
        $links = isset($_POST['links']) ? json_decode($_POST['links'], true) : [];
        $con->begin_transaction();
        $sql = "DELETE FROM user_social_links WHERE user_id = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        if (!empty($links)) {
            $sql = "INSERT INTO user_social_links (user_id, platform, url, display_order, is_public) VALUES (?, ?, ?, ?, 1)";
            $stmt = $con->prepare($sql);
            $order = 0;
            foreach ($links as $link) {
                if (!empty($link['platform']) && !empty($link['url'])) {
                    $stmt->bind_param("sssi", $student_id, $link['platform'], $link['url'], $order);
                    $stmt->execute();
                    $order++;
                }
            }
        }
        $con->commit();
        echo json_encode(['success' => true, 'message' => 'Social links saved successfully']);
    } catch (Exception $e) {
        $con->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
