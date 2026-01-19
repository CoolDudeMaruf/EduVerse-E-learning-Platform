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
$lecture_id = isset($_REQUEST['lecture_id']) ? intval($_REQUEST['lecture_id']) : 0;

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

$upload_dir = '../../../uploads/resources/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

switch ($action) {
    case 'add':
        if ($lecture_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        $verify_lecture = "SELECT l.lecture_id FROM lectures l 
                          INNER JOIN course_sections cs ON l.section_id = cs.section_id 
                          WHERE l.lecture_id = $lecture_id AND cs.course_id = '$course_id'";
        $verify_lecture_result = mysqli_query($con, $verify_lecture);
        if (!$verify_lecture_result || mysqli_num_rows($verify_lecture_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture not found']);
            exit;
        }
        
        $resource_type = isset($_POST['resource_type']) ? mysqli_real_escape_string($con, $_POST['resource_type']) : 'other';
        $title = isset($_POST['resource_name']) ? mysqli_real_escape_string($con, $_POST['resource_name']) : '';
        
        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Resource name required']);
            exit;
        }
        
        $file_url = '';
        $file_name = '';
        $file_size_kb = 0;
        
        if ($resource_type === 'link') {
            $file_url = isset($_POST['resource_url']) ? mysqli_real_escape_string($con, $_POST['resource_url']) : '';
            if (empty($file_url)) {
                echo json_encode(['success' => false, 'error' => 'URL required for link resources']);
                exit;
            }
            $resource_type = 'link';
        } else {
            if (!isset($_FILES['resource_file']) || $_FILES['resource_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'File upload failed']);
                exit;
            }
            
            $file = $_FILES['resource_file'];
            $allowed_extensions = ['pdf', 'zip', 'doc', 'docx', 'txt', 'ppt', 'pptx', 'xls', 'xlsx'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_ext, $allowed_extensions)) {
                echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_extensions)]);
                exit;
            }
            
            $max_size = 50 * 1024 * 1024; // 50MB
            if ($file['size'] > $max_size) {
                echo json_encode(['success' => false, 'error' => 'File too large. Maximum 50MB allowed']);
                exit;
            }
            
            $unique_name = 'resource_' . time() . '_' . uniqid() . '.' . $file_ext;
            $target_path = $upload_dir . $unique_name;
            
            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                echo json_encode(['success' => false, 'error' => 'Failed to save file']);
                exit;
            }
            
            $file_url = 'uploads/resources/' . $unique_name;
            $file_name = $file['name'];
            $file_size_kb = round($file['size'] / 1024);
            $resource_type = $file_ext;
        }
        
        $order_query = "SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM lecture_resources WHERE lecture_id = $lecture_id";
        $order_result = mysqli_query($con, $order_query);
        $order_row = mysqli_fetch_assoc($order_result);
        $next_order = $order_row['next_order'];
        
        $insert_query = "INSERT INTO lecture_resources (lecture_id, title, resource_type, file_url, file_name, file_size_kb, display_order) 
                         VALUES ($lecture_id, '$title', '$resource_type', '$file_url', '$file_name', $file_size_kb, $next_order)";
        
        if (mysqli_query($con, $insert_query)) {
            $resource_id = mysqli_insert_id($con);
            echo json_encode([
                'success' => true, 
                'resource_id' => $resource_id,
                'resource_name' => stripslashes($title),
                'resource_type' => $resource_type,
                'file_url' => $file_url,
                'message' => 'Resource added successfully'
            ]);
        } else {
            if ($resource_type !== 'link' && file_exists($target_path)) {
                unlink($target_path);
            }
            echo json_encode(['success' => false, 'error' => 'Failed to add resource: ' . mysqli_error($con)]);
        }
        break;
        
    case 'list':
        if ($lecture_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        $resources_query = "SELECT resource_id, title as resource_name, resource_type, file_url, file_name, file_size_kb, display_order FROM lecture_resources WHERE lecture_id = $lecture_id ORDER BY display_order ASC";
        $resources_result = mysqli_query($con, $resources_query);
        
        $resources = [];
        if ($resources_result) {
            while ($row = mysqli_fetch_assoc($resources_result)) {
                $resources[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'resources' => $resources]);
        break;
        
    case 'update':
        $resource_id = isset($_POST['resource_id']) ? intval($_POST['resource_id']) : 0;
        $title = isset($_POST['resource_name']) ? mysqli_real_escape_string($con, $_POST['resource_name']) : '';
        
        if ($resource_id <= 0 || empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Resource ID and name required']);
            exit;
        }
        
        $verify_resource = "SELECT lr.resource_id FROM lecture_resources lr
                            INNER JOIN lectures l ON lr.lecture_id = l.lecture_id
                            INNER JOIN course_sections cs ON l.section_id = cs.section_id
                            WHERE lr.resource_id = $resource_id AND cs.course_id = '$course_id'";
        $verify_result = mysqli_query($con, $verify_resource);
        if (!$verify_result || mysqli_num_rows($verify_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Resource not found']);
            exit;
        }
        
        $update_query = "UPDATE lecture_resources SET title = '$title' WHERE resource_id = $resource_id";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Resource updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update resource']);
        }
        break;
        
    case 'delete':
        $resource_id = isset($_POST['resource_id']) ? intval($_POST['resource_id']) : 0;
        
        if ($resource_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Resource ID required']);
            exit;
        }
        
        $resource_query = "SELECT lr.* FROM lecture_resources lr
                          INNER JOIN lectures l ON lr.lecture_id = l.lecture_id
                          INNER JOIN course_sections cs ON l.section_id = cs.section_id
                          WHERE lr.resource_id = $resource_id AND cs.course_id = '$course_id'";
        $resource_result = mysqli_query($con, $resource_query);
        
        if (!$resource_result || mysqli_num_rows($resource_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Resource not found']);
            exit;
        }
        
        $resource = mysqli_fetch_assoc($resource_result);
        
        if ($resource['resource_type'] !== 'link' && !empty($resource['file_url'])) {
            $file_path = '../../../' . $resource['file_url'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $delete_query = "DELETE FROM lecture_resources WHERE resource_id = $resource_id";
        
        if (mysqli_query($con, $delete_query)) {
            echo json_encode(['success' => true, 'message' => 'Resource deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete resource']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
