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
$course_id = isset($_REQUEST['course_id']) ? mysqli_real_escape_string($con, $_REQUEST['course_id']) : '';

if (empty($course_id)) {
    echo json_encode(['success' => false, 'error' => 'Course ID required']);
    exit;
}

$verify_query = "SELECT course_id FROM courses WHERE course_id = '$course_id' AND instructor_id = '$current_user_id'";
$verify_result = mysqli_query($con, $verify_query);
if (!$verify_result || mysqli_num_rows($verify_result) === 0) {
    echo json_encode(['success' => false, 'error' => 'Access denied to this course']);
    exit;
}

switch ($action) {
    case 'add':
        $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
        $title = isset($_POST['title']) ? mysqli_real_escape_string($con, $_POST['title']) : '';
        $lecture_type = isset($_POST['lecture_type']) ? mysqli_real_escape_string($con, $_POST['lecture_type']) : 'video';
        
        if ($section_id <= 0 || empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Section ID and title required']);
            exit;
        }
        
        $verify_section = "SELECT section_id FROM course_sections WHERE section_id = $section_id AND course_id = '$course_id'";
        $verify_section_result = mysqli_query($con, $verify_section);
        if (!$verify_section_result || mysqli_num_rows($verify_section_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Section not found']);
            exit;
        }
        
        $order_query = "SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM lectures WHERE section_id = $section_id";
        $order_result = mysqli_query($con, $order_query);
        $order_row = mysqli_fetch_assoc($order_result);
        $next_order = $order_row['next_order'];
        
        $insert_query = "INSERT INTO lectures (section_id, course_id, title, lecture_type, display_order, is_published) 
                         VALUES ($section_id, '$course_id', '$title', '$lecture_type', $next_order, 1)";
        
        if (mysqli_query($con, $insert_query)) {
            $lecture_id = mysqli_insert_id($con);
            
            $update_section = "UPDATE course_sections SET total_lectures = total_lectures + 1 WHERE section_id = $section_id";
            mysqli_query($con, $update_section);
            
            $update_course = "UPDATE courses SET total_lectures = total_lectures + 1 WHERE course_id = '$course_id'";
            mysqli_query($con, $update_course);
            
            echo json_encode([
                'success' => true, 
                'lecture_id' => $lecture_id,
                'display_order' => $next_order,
                'message' => 'Lecture added successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to add lecture']);
        }
        break;
        
    case 'update':
        $lecture_id = isset($_POST['lecture_id']) ? intval($_POST['lecture_id']) : 0;
        
        if ($lecture_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        // Find the lecture first
        $verify_lecture = "SELECT lecture_id, section_id, course_id FROM lectures WHERE lecture_id = $lecture_id";
        $verify_lecture_result = mysqli_query($con, $verify_lecture);
        if (!$verify_lecture_result || mysqli_num_rows($verify_lecture_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture not found']);
            exit;
        }
        
        $lecture_data = mysqli_fetch_assoc($verify_lecture_result);
        if ($lecture_data['course_id'] !== $course_id) {
            echo json_encode(['success' => false, 'error' => 'Lecture does not belong to this course']);
            exit;
        }
        
        $updates = [];
        
        if (isset($_POST['title'])) {
            $title = mysqli_real_escape_string($con, $_POST['title']);
            $updates[] = "title = '$title'";
        }
        if (isset($_POST['description'])) {
            $description = mysqli_real_escape_string($con, $_POST['description']);
            $updates[] = "description = '$description'";
        }
        if (isset($_POST['lecture_type'])) {
            $lecture_type = mysqli_real_escape_string($con, $_POST['lecture_type']);
            $updates[] = "lecture_type = '$lecture_type'";
        }
        if (isset($_POST['content_url'])) {
            $content_url = mysqli_real_escape_string($con, $_POST['content_url']);
            $updates[] = "content_url = '$content_url'";
        }
        if (isset($_POST['duration_minutes'])) {
            $duration_minutes = intval($_POST['duration_minutes']);
            $updates[] = "duration_minutes = $duration_minutes";
        }
        if (isset($_POST['is_preview'])) {
            $is_preview = $_POST['is_preview'] ? 1 : 0;
            $updates[] = "is_preview = $is_preview";
        }
        if (isset($_POST['is_downloadable'])) {
            $is_downloadable = $_POST['is_downloadable'] ? 1 : 0;
            $updates[] = "is_downloadable = $is_downloadable";
        }
        if (isset($_POST['learning_objectives'])) {
            // Ensure column exists
            $check_column = mysqli_query($con, "SHOW COLUMNS FROM lectures LIKE 'learning_objectives'");
            if (!$check_column || mysqli_num_rows($check_column) == 0) {
                mysqli_query($con, "ALTER TABLE lectures ADD COLUMN learning_objectives TEXT DEFAULT NULL");
            }
            $learning_objectives = mysqli_real_escape_string($con, $_POST['learning_objectives']);
            $updates[] = "learning_objectives = '$learning_objectives'";
        }
        
        if (empty($updates)) {
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            exit;
        }
        
        $update_query = "UPDATE lectures SET " . implode(', ', $updates) . " WHERE lecture_id = $lecture_id";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Lecture updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update lecture']);
        }
        break;
        
    case 'delete':
        $lecture_id = isset($_POST['lecture_id']) ? intval($_POST['lecture_id']) : 0;
        
        if ($lecture_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        // Find the lecture first
        $verify_lecture = "SELECT lecture_id, section_id, content_url, course_id FROM lectures WHERE lecture_id = $lecture_id";
        $verify_lecture_result = mysqli_query($con, $verify_lecture);
        if (!$verify_lecture_result || mysqli_num_rows($verify_lecture_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture not found']);
            exit;
        }
        
        $lecture_row = mysqli_fetch_assoc($verify_lecture_result);
        if ($lecture_row['course_id'] !== $course_id) {
            echo json_encode(['success' => false, 'error' => 'Lecture does not belong to this course']);
            exit;
        }
        $section_id = $lecture_row['section_id'];
        $content_url = $lecture_row['content_url'];
        
        // Delete video file if it exists on server
        if (!empty($content_url) && strpos($content_url, 'uploads/videos/') !== false) {
            $video_path = '../../../' . $content_url;
            if (file_exists($video_path)) {
                @unlink($video_path);
            }
        }
        
        // Delete all resources associated with this lecture
        $resources_query = "SELECT resource_id, resource_type, file_url FROM lecture_resources WHERE lecture_id = $lecture_id";
        $resources_result = mysqli_query($con, $resources_query);
        if ($resources_result) {
            while ($resource = mysqli_fetch_assoc($resources_result)) {
                // Delete resource file if it's an uploaded file
                if ($resource['resource_type'] !== 'link' && !empty($resource['file_url'])) {
                    $resource_path = '../../../' . $resource['file_url'];
                    if (file_exists($resource_path)) {
                        @unlink($resource_path);
                    }
                }
            }
        }
        // Delete resources from database
        mysqli_query($con, "DELETE FROM lecture_resources WHERE lecture_id = $lecture_id");
        
        // Delete the lecture
        $delete_query = "DELETE FROM lectures WHERE lecture_id = $lecture_id";
        
        if (mysqli_query($con, $delete_query)) {
            $update_section = "UPDATE course_sections SET total_lectures = GREATEST(0, total_lectures - 1) WHERE section_id = $section_id";
            mysqli_query($con, $update_section);
            
            $update_course = "UPDATE courses SET total_lectures = GREATEST(0, total_lectures - 1) WHERE course_id = '$course_id'";
            mysqli_query($con, $update_course);
            
            // Recalculate course duration
            $recalc_query = "UPDATE courses SET total_duration_minutes = (
                SELECT COALESCE(SUM(l.duration_minutes), 0) FROM lectures l 
                INNER JOIN course_sections cs ON l.section_id = cs.section_id 
                WHERE cs.course_id = '$course_id'
            ) WHERE course_id = '$course_id'";
            @mysqli_query($con, $recalc_query);
            
            echo json_encode(['success' => true, 'message' => 'Lecture and all related files deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete lecture']);
        }
        break;
        
    case 'get':
        $lecture_id = isset($_GET['lecture_id']) ? intval($_GET['lecture_id']) : 0;
        
        if ($lecture_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        // Find the lecture first
        $lecture_query = "SELECT * FROM lectures WHERE lecture_id = $lecture_id";
        $lecture_result = mysqli_query($con, $lecture_query);
        
        if ($lecture_result && mysqli_num_rows($lecture_result) > 0) {
            $lecture = mysqli_fetch_assoc($lecture_result);
            if ($lecture['course_id'] !== $course_id) {
                echo json_encode(['success' => false, 'error' => 'Lecture does not belong to this course']);
            } else {
                echo json_encode(['success' => true, 'lecture' => $lecture]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Lecture not found']);
        }
        break;
        
    case 'add_subtitle':
        $lecture_id = isset($_POST['lecture_id']) ? intval($_POST['lecture_id']) : 0;
        $subtitle_lang = isset($_POST['subtitle_lang']) ? mysqli_real_escape_string($con, $_POST['subtitle_lang']) : 'en';
        
        if ($lecture_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        // Verify lecture belongs to course
        $verify_lecture = "SELECT lecture_id, subtitles FROM lectures WHERE lecture_id = $lecture_id AND course_id = '$course_id'";
        $verify_result = mysqli_query($con, $verify_lecture);
        if (!$verify_result || mysqli_num_rows($verify_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture not found']);
            exit;
        }
        
        $lecture_row = mysqli_fetch_assoc($verify_result);
        $existing_subtitles = $lecture_row['subtitles'] ? json_decode($lecture_row['subtitles'], true) : [];
        
        // Handle file upload
        if (!isset($_FILES['subtitle_file']) || $_FILES['subtitle_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Subtitle file required']);
            exit;
        }
        
        $file = $_FILES['subtitle_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, ['srt', 'vtt'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid file format. Use .srt or .vtt']);
            exit;
        }
        
        $subtitle_dir = '../../../uploads/subtitles/' . $course_id . '/';
        if (!file_exists($subtitle_dir)) {
            mkdir($subtitle_dir, 0777, true);
        }
        
        $new_filename = uniqid() . '_' . $subtitle_lang . '.' . $ext;
        $destination = $subtitle_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $existing_subtitles[] = [
                'lang' => $subtitle_lang,
                'file' => 'uploads/subtitles/' . $course_id . '/' . $new_filename,
                'original_name' => $file['name']
            ];
            
            $subtitles_json = mysqli_real_escape_string($con, json_encode($existing_subtitles));
            $update_query = "UPDATE lectures SET subtitles = '$subtitles_json' WHERE lecture_id = $lecture_id";
            
            if (mysqli_query($con, $update_query)) {
                echo json_encode(['success' => true, 'message' => 'Subtitle added successfully']);
            } else {
                @unlink($destination);
                echo json_encode(['success' => false, 'error' => 'Failed to save subtitle']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
        }
        break;
        
    case 'get_subtitles':
        $lecture_id = isset($_GET['lecture_id']) ? intval($_GET['lecture_id']) : 0;
        
        if ($lecture_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        $lecture_query = "SELECT subtitles FROM lectures WHERE lecture_id = $lecture_id AND course_id = '$course_id'";
        $lecture_result = mysqli_query($con, $lecture_query);
        
        if ($lecture_result && mysqli_num_rows($lecture_result) > 0) {
            $lecture = mysqli_fetch_assoc($lecture_result);
            $subtitles = $lecture['subtitles'] ? json_decode($lecture['subtitles'], true) : [];
            echo json_encode(['success' => true, 'subtitles' => $subtitles]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Lecture not found']);
        }
        break;
        
    case 'delete_subtitle':
        $lecture_id = isset($_POST['lecture_id']) ? intval($_POST['lecture_id']) : 0;
        $subtitle_index = isset($_POST['subtitle_index']) ? intval($_POST['subtitle_index']) : -1;
        
        if ($lecture_id <= 0 || $subtitle_index < 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }
        
        $lecture_query = "SELECT subtitles FROM lectures WHERE lecture_id = $lecture_id AND course_id = '$course_id'";
        $lecture_result = mysqli_query($con, $lecture_query);
        
        if ($lecture_result && mysqli_num_rows($lecture_result) > 0) {
            $lecture = mysqli_fetch_assoc($lecture_result);
            $subtitles = $lecture['subtitles'] ? json_decode($lecture['subtitles'], true) : [];
            
            if (isset($subtitles[$subtitle_index])) {
                // Delete the file
                $file_path = '../../../' . $subtitles[$subtitle_index]['file'];
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
                
                // Remove from array
                array_splice($subtitles, $subtitle_index, 1);
                
                $subtitles_json = mysqli_real_escape_string($con, json_encode($subtitles));
                $update_query = "UPDATE lectures SET subtitles = '$subtitles_json' WHERE lecture_id = $lecture_id";
                
                if (mysqli_query($con, $update_query)) {
                    echo json_encode(['success' => true, 'message' => 'Subtitle deleted']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to update']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Subtitle not found']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Lecture not found']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
