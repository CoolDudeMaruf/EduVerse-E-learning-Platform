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
        $title = isset($_POST['title']) ? mysqli_real_escape_string($con, $_POST['title']) : '';
        $description = isset($_POST['description']) ? mysqli_real_escape_string($con, $_POST['description']) : '';
        
        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Section title required']);
            exit;
        }
        
        $order_query = "SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM course_sections WHERE course_id = '$course_id'";
        $order_result = mysqli_query($con, $order_query);
        $order_row = mysqli_fetch_assoc($order_result);
        $next_order = $order_row['next_order'];
        
        $insert_query = "INSERT INTO course_sections (course_id, title, description, display_order, is_published) 
                         VALUES ('$course_id', '$title', '$description', $next_order, 1)";
        
        if (mysqli_query($con, $insert_query)) {
            $section_id = mysqli_insert_id($con);
            $update_course = "UPDATE courses SET total_sections = total_sections + 1 WHERE course_id = '$course_id'";
            mysqli_query($con, $update_course);
            
            echo json_encode([
                'success' => true, 
                'section_id' => $section_id,
                'display_order' => $next_order,
                'message' => 'Section added successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to add section']);
        }
        break;
        
    case 'update':
        $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
        $title = isset($_POST['title']) ? mysqli_real_escape_string($con, $_POST['title']) : '';
        $description = isset($_POST['description']) ? mysqli_real_escape_string($con, $_POST['description']) : '';
        
        if ($section_id <= 0 || empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Section ID and title required']);
            exit;
        }
        
        // First find the section
        $verify_section = "SELECT section_id, course_id FROM course_sections WHERE section_id = $section_id";
        $verify_section_result = mysqli_query($con, $verify_section);
        if (!$verify_section_result || mysqli_num_rows($verify_section_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Section not found']);
            exit;
        }
        
        $section_data = mysqli_fetch_assoc($verify_section_result);
        if ($section_data['course_id'] !== $course_id) {
            echo json_encode(['success' => false, 'error' => 'Section does not belong to this course']);
            exit;
        }
        
        $update_query = "UPDATE course_sections SET title = '$title', description = '$description' WHERE section_id = $section_id";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Section updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update section']);
        }
        break;
        
    case 'delete':
        $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
        
        if ($section_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Section ID required']);
            exit;
        }
        
        // First find the section
        $verify_section = "SELECT section_id, course_id FROM course_sections WHERE section_id = $section_id";
        $verify_section_result = mysqli_query($con, $verify_section);
        if (!$verify_section_result || mysqli_num_rows($verify_section_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Section not found']);
            exit;
        }
        
        $section_data = mysqli_fetch_assoc($verify_section_result);
        
        // Verify section belongs to the course we have access to
        if ($section_data['course_id'] !== $course_id) {
            echo json_encode(['success' => false, 'error' => 'Section does not belong to this course']);
            exit;
        }
        
        // Get all lectures in this section
        $lectures_query = "SELECT lecture_id, content_url FROM lectures WHERE section_id = $section_id";
        $lectures_result = mysqli_query($con, $lectures_query);
        $lecture_count = mysqli_num_rows($lectures_result);
        
        // Delete all files related to each lecture
        if ($lectures_result) {
            while ($lecture = mysqli_fetch_assoc($lectures_result)) {
                $lecture_id = $lecture['lecture_id'];
                
                // Delete video file if exists
                if (!empty($lecture['content_url']) && strpos($lecture['content_url'], 'uploads/videos/') !== false) {
                    $video_path = '../../../' . $lecture['content_url'];
                    if (file_exists($video_path)) {
                        @unlink($video_path);
                    }
                }
                
                // Delete all resource files for this lecture
                $resources_query = "SELECT resource_type, file_url FROM lecture_resources WHERE lecture_id = $lecture_id";
                $resources_result = mysqli_query($con, $resources_query);
                if ($resources_result) {
                    while ($resource = mysqli_fetch_assoc($resources_result)) {
                        if ($resource['resource_type'] !== 'link' && !empty($resource['file_url'])) {
                            $resource_path = '../../../' . $resource['file_url'];
                            if (file_exists($resource_path)) {
                                @unlink($resource_path);
                            }
                        }
                    }
                }
                
                // Delete resources from database for this lecture
                mysqli_query($con, "DELETE FROM lecture_resources WHERE lecture_id = $lecture_id");
            }
        }
        
        // Delete all lectures in this section
        mysqli_query($con, "DELETE FROM lectures WHERE section_id = $section_id");
        
        // Delete the section
        $delete_section = "DELETE FROM course_sections WHERE section_id = $section_id";
        
        if (mysqli_query($con, $delete_section)) {
            $update_course = "UPDATE courses SET total_sections = GREATEST(0, total_sections - 1), 
                              total_lectures = GREATEST(0, total_lectures - $lecture_count) WHERE course_id = '$course_id'";
            mysqli_query($con, $update_course);
            
            // Recalculate course total duration
            $recalc_query = "UPDATE courses SET total_duration_minutes = (
                SELECT COALESCE(SUM(l.duration_minutes), 0) FROM lectures l 
                INNER JOIN course_sections cs ON l.section_id = cs.section_id 
                WHERE cs.course_id = '$course_id'
            ) WHERE course_id = '$course_id'";
            @mysqli_query($con, $recalc_query);
            
            echo json_encode(['success' => true, 'message' => 'Section and all related content deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete section']);
        }
        break;
        
    case 'get':
        $section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
        
        if ($section_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Section ID required']);
            exit;
        }
        
        // First find the section
        $section_query = "SELECT * FROM course_sections WHERE section_id = $section_id";
        $section_result = mysqli_query($con, $section_query);
        
        if ($section_result && mysqli_num_rows($section_result) > 0) {
            $section = mysqli_fetch_assoc($section_result);
            if ($section['course_id'] !== $course_id) {
                echo json_encode(['success' => false, 'error' => 'Section does not belong to this course']);
            } else {
                echo json_encode(['success' => true, 'section' => $section]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Section not found']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
