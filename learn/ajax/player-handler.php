<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!$is_logged_in) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $current_user_id;

switch ($action) {
    case 'get_lecture':
        $lecture_id = isset($_GET['lecture_id']) ? mysqli_real_escape_string($con, $_GET['lecture_id']) : '';
        $course_id = isset($_GET['course_id']) ? mysqli_real_escape_string($con, $_GET['course_id']) : '';
        
        if (empty($lecture_id)) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        // Verify access and get enrollment info
        $access_query = "SELECT c.instructor_id, e.enrollment_id, e.total_learning_time_seconds
                        FROM courses c
                        LEFT JOIN enrollments e ON c.course_id = e.course_id AND e.student_id = '$user_id'
                        WHERE c.course_id = '$course_id'";
        $access_result = mysqli_query($con, $access_query);
        $access = mysqli_fetch_assoc($access_result);
        
        if (!$access || ($access['instructor_id'] !== $user_id && empty($access['enrollment_id']))) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
        
        // Update last accessed time for enrollment
        if (!empty($access['enrollment_id'])) {
            mysqli_query($con, "UPDATE enrollments SET last_accessed_at = NOW() WHERE enrollment_id = '{$access['enrollment_id']}'");
        }
        
        // Get lecture with progress
        $query = "SELECT l.*, lp.is_completed, lp.watch_duration_seconds, lp.last_watched_at
                  FROM lectures l
                  LEFT JOIN lecture_progress lp ON l.lecture_id = lp.lecture_id AND lp.student_id = '$user_id'
                  WHERE l.lecture_id = '$lecture_id'";
        $result = mysqli_query($con, $query);
        
        if ($result && $row = mysqli_fetch_assoc($result)) {
            echo json_encode([
                'success' => true,
                'lecture' => [
                    'lecture_id' => $row['lecture_id'],
                    'title' => $row['title'],
                    'description' => $row['description'] ?? '',
                    'video_url' => $row['content_url'],
                    'duration' => (int)$row['duration_seconds'],
                    'is_completed' => (bool)$row['is_completed'],
                    'watch_time' => (int)($row['watch_duration_seconds'] ?? 0),
                    'thumbnail_url' => $row['thumbnail_url'] ?? '',
                    'video_source' => $row['video_source'] ?? '',
                    'learning_objectives' => json_decode($row['learning_objectives'] ?? '[]', true),
                    'subtitles' => json_decode($row['subtitles'] ?? '[]', true)
                ],
                'total_learning_time' => (int)($access['total_learning_time_seconds'] ?? 0)
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Lecture not found']);
        }
        break;
        
    case 'mark_complete':
        $lecture_id = isset($_POST['lecture_id']) ? mysqli_real_escape_string($con, $_POST['lecture_id']) : '';
        $course_id = isset($_POST['course_id']) ? mysqli_real_escape_string($con, $_POST['course_id']) : '';
        
        if (empty($lecture_id)) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        // Check if user is instructor (preview mode)
        $instructor_check = mysqli_query($con, "SELECT instructor_id FROM courses WHERE course_id = '$course_id'");
        $is_instructor = false;
        if ($instructor_check && $row = mysqli_fetch_assoc($instructor_check)) {
            $is_instructor = ($row['instructor_id'] === $user_id);
        }
        
        if ($is_instructor) {
            echo json_encode(['success' => false, 'error' => 'Preview mode - progress not tracked for instructors']);
            exit;
        }
        
        // Get enrollment_id
        $enroll_result = mysqli_query($con, "SELECT enrollment_id FROM enrollments WHERE course_id = '$course_id' AND student_id = '$user_id'");
        $enrollment_id = null;
        if ($enroll_result && $enroll = mysqli_fetch_assoc($enroll_result)) {
            $enrollment_id = $enroll['enrollment_id'];
        }
        
        // Check if progress exists
        $check = mysqli_query($con, "SELECT progress_id FROM lecture_progress WHERE lecture_id = '$lecture_id' AND student_id = '$user_id'");
        
        if ($check && mysqli_num_rows($check) > 0) {
            mysqli_query($con, "UPDATE lecture_progress SET is_completed = 1, completed_at = NOW() WHERE lecture_id = '$lecture_id' AND student_id = '$user_id'");
        } else {
            $enrollment_field = $enrollment_id ? ", enrollment_id" : "";
            $enrollment_value = $enrollment_id ? ", '$enrollment_id'" : "";
            mysqli_query($con, "INSERT INTO lecture_progress (student_id, lecture_id, is_completed, completed_at $enrollment_field) VALUES ('$user_id', '$lecture_id', 1, NOW() $enrollment_value)");
        }
        
        // Calculate progress
        $progress = calculateProgress($con, $course_id, $user_id);
        
        // Update enrollment progress and check completion
        mysqli_query($con, "UPDATE enrollments SET 
            progress_percentage = {$progress['percent']},
            last_accessed_at = NOW(),
            status = IF({$progress['percent']} = 100, 'completed', status),
            completed_at = IF({$progress['percent']} = 100 AND completed_at IS NULL, NOW(), completed_at)
            WHERE course_id = '$course_id' AND student_id = '$user_id'");
        
        // Check if course just completed
        $is_completed = ($progress['percent'] == 100);
        
        echo json_encode(['success' => true, 'progress' => $progress, 'course_completed' => $is_completed]);
        break;
    
    case 'toggle_complete':
        $lecture_id = isset($_POST['lecture_id']) ? mysqli_real_escape_string($con, $_POST['lecture_id']) : '';
        $course_id = isset($_POST['course_id']) ? mysqli_real_escape_string($con, $_POST['course_id']) : '';
        $is_complete = isset($_POST['is_complete']) ? (int)$_POST['is_complete'] : 1;
        
        if (empty($lecture_id)) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        // Check if user is instructor (preview mode)
        $instructor_check = mysqli_query($con, "SELECT instructor_id FROM courses WHERE course_id = '$course_id'");
        if ($instructor_check && $row = mysqli_fetch_assoc($instructor_check)) {
            if ($row['instructor_id'] === $user_id) {
                echo json_encode(['success' => false, 'error' => 'Preview mode']);
                exit;
            }
        }
        
        // Get enrollment_id
        $enroll_result = mysqli_query($con, "SELECT enrollment_id FROM enrollments WHERE course_id = '$course_id' AND student_id = '$user_id'");
        $enrollment_id = null;
        if ($enroll_result && $enroll = mysqli_fetch_assoc($enroll_result)) {
            $enrollment_id = $enroll['enrollment_id'];
        }
        
        $check = mysqli_query($con, "SELECT progress_id FROM lecture_progress WHERE lecture_id = '$lecture_id' AND student_id = '$user_id'");
        
        if ($check && mysqli_num_rows($check) > 0) {
            $completed_at = $is_complete ? 'NOW()' : 'NULL';
            mysqli_query($con, "UPDATE lecture_progress SET is_completed = $is_complete, completed_at = $completed_at WHERE lecture_id = '$lecture_id' AND student_id = '$user_id'");
        } else if ($is_complete) {
            $enrollment_field = $enrollment_id ? ", enrollment_id" : "";
            $enrollment_value = $enrollment_id ? ", '$enrollment_id'" : "";
            mysqli_query($con, "INSERT INTO lecture_progress (student_id, lecture_id, is_completed, completed_at $enrollment_field) VALUES ('$user_id', '$lecture_id', 1, NOW() $enrollment_value)");
        }
        
        // Calculate and update progress
        $progress = calculateProgress($con, $course_id, $user_id);
        mysqli_query($con, "UPDATE enrollments SET 
            progress_percentage = {$progress['percent']},
            status = IF({$progress['percent']} = 100, 'completed', IF(status = 'completed', 'active', status))
            WHERE course_id = '$course_id' AND student_id = '$user_id'");
        
        echo json_encode(['success' => true, 'is_completed' => (bool)$is_complete, 'progress' => $progress]);
        break;
        
    case 'save_progress':
        $lecture_id = isset($_POST['lecture_id']) ? mysqli_real_escape_string($con, $_POST['lecture_id']) : '';
        $course_id = isset($_POST['course_id']) ? mysqli_real_escape_string($con, $_POST['course_id']) : '';
        $watch_time = isset($_POST['watch_time']) ? (int)$_POST['watch_time'] : 0;
        
        if (empty($lecture_id)) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        // Check if user is instructor (preview mode)
        if (!empty($course_id)) {
            $instructor_check = mysqli_query($con, "SELECT instructor_id FROM courses WHERE course_id = '$course_id'");
            if ($instructor_check && $row = mysqli_fetch_assoc($instructor_check)) {
                if ($row['instructor_id'] === $user_id) {
                    echo json_encode(['success' => true, 'preview_mode' => true]);
                    exit;
                }
            }
        }
        
        // Get enrollment_id
        $enroll_result = mysqli_query($con, "SELECT enrollment_id FROM enrollments WHERE course_id = '$course_id' AND student_id = '$user_id'");
        $enrollment_id = null;
        if ($enroll_result && $enroll = mysqli_fetch_assoc($enroll_result)) {
            $enrollment_id = $enroll['enrollment_id'];
        }
        
        $check = mysqli_query($con, "SELECT progress_id, watch_duration_seconds FROM lecture_progress WHERE lecture_id = '$lecture_id' AND student_id = '$user_id'");
        
        if ($check && mysqli_num_rows($check) > 0) {
            mysqli_query($con, "UPDATE lecture_progress SET 
                watch_duration_seconds = $watch_time,
                last_watched_at = NOW()
                WHERE lecture_id = '$lecture_id' AND student_id = '$user_id'");
        } else {
            $enrollment_field = $enrollment_id ? ", enrollment_id" : "";
            $enrollment_value = $enrollment_id ? ", '$enrollment_id'" : "";
            mysqli_query($con, "INSERT INTO lecture_progress (student_id, lecture_id, watch_duration_seconds, last_watched_at $enrollment_field) 
                VALUES ('$user_id', '$lecture_id', $watch_time, NOW() $enrollment_value)");
        }
        
        // Update enrollment last accessed
        if ($enrollment_id) {
            mysqli_query($con, "UPDATE enrollments SET last_accessed_at = NOW() WHERE enrollment_id = '$enrollment_id'");
        }
        
        echo json_encode(['success' => true]);
        break;
    
    case 'update_learning_time':
        $course_id = isset($_POST['course_id']) ? mysqli_real_escape_string($con, $_POST['course_id']) : '';
        $session_time = isset($_POST['session_time']) ? (int)$_POST['session_time'] : 0;
        
        if (empty($course_id) || $session_time <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }
        
        // Check if instructor
        $instructor_check = mysqli_query($con, "SELECT instructor_id FROM courses WHERE course_id = '$course_id'");
        if ($instructor_check && $row = mysqli_fetch_assoc($instructor_check)) {
            if ($row['instructor_id'] === $user_id) {
                echo json_encode(['success' => true, 'preview_mode' => true]);
                exit;
            }
        }
        
        // Update total learning time in enrollment
        $update = mysqli_query($con, "UPDATE enrollments SET 
            total_learning_time_seconds = COALESCE(total_learning_time_seconds, 0) + $session_time,
            last_accessed_at = NOW()
            WHERE course_id = '$course_id' AND student_id = '$user_id'");
        
        // Get updated total
        $result = mysqli_query($con, "SELECT total_learning_time_seconds FROM enrollments WHERE course_id = '$course_id' AND student_id = '$user_id'");
        $total = 0;
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $total = (int)$row['total_learning_time_seconds'];
        }
        
        // Update user's total learning hours
        mysqli_query($con, "UPDATE users SET 
            total_learning_hours = (SELECT COALESCE(SUM(total_learning_time_seconds), 0) / 3600 FROM enrollments WHERE student_id = '$user_id'),
            last_active_at = NOW()
            WHERE user_id = '$user_id'");
        
        echo json_encode(['success' => true, 'total_time' => $total]);
        break;
    
    case 'get_stats':
        $course_id = isset($_GET['course_id']) ? mysqli_real_escape_string($con, $_GET['course_id']) : '';
        
        // Get enrollment stats
        $enrollment_query = "SELECT 
            e.enrollment_id, e.progress_percentage, e.total_learning_time_seconds, 
            e.status, e.completed_at, e.enrollment_date, e.last_accessed_at,
            c.total_lectures, c.duration_hours
            FROM enrollments e
            INNER JOIN courses c ON e.course_id = c.course_id
            WHERE e.course_id = '$course_id' AND e.student_id = '$user_id'";
        $enrollment_result = mysqli_query($con, $enrollment_query);
        
        if (!$enrollment_result || mysqli_num_rows($enrollment_result) == 0) {
            echo json_encode(['success' => false, 'error' => 'Enrollment not found']);
            exit;
        }
        
        $enrollment = mysqli_fetch_assoc($enrollment_result);
        
        // Get completed lectures count
        $progress = calculateProgress($con, $course_id, $user_id);
        
        // Check for certificate
        $cert_result = mysqli_query($con, "SELECT certificate_id, certificate_number FROM certificates WHERE course_id = '$course_id' AND student_id = '$user_id'");
        $certificate = null;
        if ($cert_result && mysqli_num_rows($cert_result) > 0) {
            $certificate = mysqli_fetch_assoc($cert_result);
        }
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'enrollment_id' => $enrollment['enrollment_id'],
                'progress_percent' => (int)$enrollment['progress_percentage'],
                'total_learning_time' => (int)($enrollment['total_learning_time_seconds'] ?? 0),
                'completed_lectures' => $progress['completed'],
                'total_lectures' => $progress['total'],
                'status' => $enrollment['status'],
                'completed_at' => $enrollment['completed_at'],
                'enrollment_date' => $enrollment['enrollment_date'],
                'last_accessed' => $enrollment['last_accessed_at'],
                'certificate' => $certificate
            ]
        ]);
        break;
        
    case 'get_notes':
        $lecture_id = isset($_GET['lecture_id']) ? mysqli_real_escape_string($con, $_GET['lecture_id']) : '';
        
        $query = "SELECT * FROM lecture_notes WHERE user_id = '$user_id' AND lecture_id = '$lecture_id' ORDER BY timestamp ASC";
        $result = mysqli_query($con, $query);
        
        $notes = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $notes[] = [
                    'note_id' => $row['note_id'],
                    'content' => $row['content'],
                    'timestamp' => (int)$row['timestamp'],
                    'created_at' => $row['created_at']
                ];
            }
        }
        
        echo json_encode(['success' => true, 'notes' => $notes]);
        break;
        
    case 'add_note':
        $lecture_id = isset($_POST['lecture_id']) ? mysqli_real_escape_string($con, $_POST['lecture_id']) : '';
        $course_id = isset($_POST['course_id']) ? mysqli_real_escape_string($con, $_POST['course_id']) : '';
        $content = isset($_POST['content']) ? mysqli_real_escape_string($con, $_POST['content']) : '';
        $timestamp = isset($_POST['timestamp']) ? (int)$_POST['timestamp'] : 0;
        
        if (empty($content)) {
            echo json_encode(['success' => false, 'error' => 'Note content required']);
            exit;
        }
        
        $query = "INSERT INTO lecture_notes (user_id, lecture_id, content, timestamp) VALUES ('$user_id', '$lecture_id', '$content', $timestamp)";
        
        if (mysqli_query($con, $query)) {
            $note_id = mysqli_insert_id($con);
            echo json_encode([
                'success' => true, 
                'note_id' => $note_id,
                'note' => [
                    'note_id' => $note_id,
                    'content' => stripslashes($content),
                    'timestamp' => $timestamp,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save note: ' . mysqli_error($con)]);
        }
        break;
    
    case 'update_note':
        $note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
        $content = isset($_POST['content']) ? mysqli_real_escape_string($con, $_POST['content']) : '';
        
        if (empty($content) || $note_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Note ID and content required']);
            exit;
        }
        
        $query = "UPDATE lecture_notes SET content = '$content' WHERE note_id = $note_id AND user_id = '$user_id'";
        
        if (mysqli_query($con, $query)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update note']);
        }
        break;
        
    case 'delete_note':
        $note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
        
        $query = "DELETE FROM lecture_notes WHERE note_id = $note_id AND user_id = '$user_id'";
        
        if (mysqli_query($con, $query)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete note']);
        }
        break;
        
    case 'get_resources':
        $lecture_id = isset($_GET['lecture_id']) ? mysqli_real_escape_string($con, $_GET['lecture_id']) : '';
        
        $table_check = mysqli_query($con, "SHOW TABLES LIKE 'lecture_resources'");
        if (!$table_check || mysqli_num_rows($table_check) == 0) {
            echo json_encode(['success' => true, 'resources' => []]);
            exit;
        }
        
        $query = "SELECT * FROM lecture_resources WHERE lecture_id = '$lecture_id' ORDER BY display_order ASC";
        $result = mysqli_query($con, $query);
        
        $resources = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $file_size = '';
                if (!empty($row['file_size_kb'])) {
                    $size_kb = (int)$row['file_size_kb'];
                    if ($size_kb >= 1024) {
                        $file_size = round($size_kb / 1024, 1) . ' MB';
                    } else {
                        $file_size = $size_kb . ' KB';
                    }
                }
                
                $resources[] = [
                    'resource_id' => $row['resource_id'],
                    'title' => $row['title'],
                    'file_url' => $row['file_url'],
                    'file_type' => $row['resource_type'] ?? 'file',
                    'file_size' => $file_size,
                    'file_name' => $row['file_name'] ?? ''
                ];
            }
        }
        
        echo json_encode(['success' => true, 'resources' => $resources]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Calculate course progress
 */
function calculateProgress($con, $course_id, $user_id) {
    $total_query = "SELECT COUNT(*) as total FROM lectures l
                    INNER JOIN course_sections cs ON l.section_id = cs.section_id
                    WHERE cs.course_id = '$course_id'";
    $total_result = mysqli_query($con, $total_query);
    $total = mysqli_fetch_assoc($total_result)['total'] ?? 0;
    
    $completed_query = "SELECT COUNT(*) as completed FROM lecture_progress lp
                        INNER JOIN lectures l ON lp.lecture_id = l.lecture_id
                        INNER JOIN course_sections cs ON l.section_id = cs.section_id
                        WHERE cs.course_id = '$course_id' AND lp.student_id = '$user_id' AND lp.is_completed = 1";
    $completed_result = mysqli_query($con, $completed_query);
    $completed = mysqli_fetch_assoc($completed_result)['completed'] ?? 0;
    
    $percent = $total > 0 ? round(($completed / $total) * 100) : 0;
    
    return [
        'total' => (int)$total,
        'completed' => (int)$completed,
        'percent' => $percent
    ];
}
?>
