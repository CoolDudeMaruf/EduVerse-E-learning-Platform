<?php
// Suppress all error output for AJAX responses
error_reporting(0);
ini_set('display_errors', '0');

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

$upload_dir = '../../../uploads/videos/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

switch ($action) {
    case 'upload_video':
        if ($lecture_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        // First try direct course_id match
        $verify_lecture = "SELECT l.lecture_id, l.content_url FROM lectures l 
                          WHERE l.lecture_id = $lecture_id AND l.course_id = '$course_id'";
        $verify_lecture_result = mysqli_query($con, $verify_lecture);
        
        // Fallback to section join if not found
        if (!$verify_lecture_result || mysqli_num_rows($verify_lecture_result) === 0) {
            $verify_lecture = "SELECT l.lecture_id, l.content_url FROM lectures l 
                              INNER JOIN course_sections cs ON l.section_id = cs.section_id 
                              WHERE l.lecture_id = $lecture_id AND cs.course_id = '$course_id'";
            $verify_lecture_result = mysqli_query($con, $verify_lecture);
        }
        
        if (!$verify_lecture_result || mysqli_num_rows($verify_lecture_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture not found']);
            exit;
        }
        $lecture_data = mysqli_fetch_assoc($verify_lecture_result);
        
        if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            $error_msg = 'File upload failed';
            if (isset($_FILES['video'])) {
                switch ($_FILES['video']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error_msg = 'File too large';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error_msg = 'File only partially uploaded';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $error_msg = 'No file uploaded';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                    case UPLOAD_ERR_CANT_WRITE:
                        $error_msg = 'Server configuration error';
                        break;
                }
            }
            echo json_encode(['success' => false, 'error' => $error_msg]);
            exit;
        }
        
        $file = $_FILES['video'];
        $allowed_types = ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo'];
        $allowed_extensions = ['mp4', 'webm', 'mov', 'avi'];
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_mime = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_ext, $allowed_extensions) && !in_array($file_mime, $allowed_types)) {
            echo json_encode(['success' => false, 'error' => 'Invalid video format. Allowed: MP4, WebM, MOV, AVI']);
            exit;
        }
        
        $max_size = 2 * 1024 * 1024 * 1024; // 2GB
        if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'error' => 'Video too large. Maximum 2GB allowed']);
            exit;
        }
        
        if (!empty($lecture_data['content_url']) && strpos($lecture_data['content_url'], 'uploads/videos/') !== false) {
            $old_file = '../../../' . $lecture_data['content_url'];
            if (file_exists($old_file)) {
                @unlink($old_file);
            }
        }
        
        $unique_name = 'video_' . $course_id . '_' . $lecture_id . '_' . time() . '.' . $file_ext;
        $target_path = $upload_dir . $unique_name;
        
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save video file']);
            exit;
        }
        
        $video_url = 'uploads/videos/' . $unique_name;
        $file_size_bytes = $file['size'];
        $file_size_mb = round($file_size_bytes / (1024 * 1024), 2);
        
        // Get duration from client-side JavaScript in seconds
        $duration_seconds = isset($_POST['duration_seconds']) ? intval($_POST['duration_seconds']) : 0;
        
        // Fallback to old duration_minutes if provided (for backward compatibility)
        if ($duration_seconds <= 0 && isset($_POST['duration_minutes'])) {
            $duration_seconds = intval($_POST['duration_minutes']) * 60;
        }
        
        // Fallback: estimate duration based on file size if JS didn't provide it
        if ($duration_seconds <= 0 && $file_size_mb > 0) {
            $duration_seconds = max(60, round($file_size_mb * 6)); // roughly 6 seconds per MB
        }
        
        // Calculate minutes for backward compatibility
        $duration_minutes = ceil($duration_seconds / 60);
        
        // Ensure video_file_size column exists, add it if not
        $check_column = mysqli_query($con, "SHOW COLUMNS FROM lectures LIKE 'video_file_size'");
        if (!$check_column || mysqli_num_rows($check_column) == 0) {
            mysqli_query($con, "ALTER TABLE lectures ADD COLUMN video_file_size BIGINT UNSIGNED DEFAULT 0 AFTER content_url");
        }
        
        // Ensure duration_seconds column exists, add it if not
        $check_column2 = mysqli_query($con, "SHOW COLUMNS FROM lectures LIKE 'duration_seconds'");
        if (!$check_column2 || mysqli_num_rows($check_column2) == 0) {
            mysqli_query($con, "ALTER TABLE lectures ADD COLUMN duration_seconds INT UNSIGNED DEFAULT 0 AFTER duration_minutes");
        }
        
        // Update lecture with video info
        $update_query = "UPDATE lectures SET 
                         content_url = '$video_url', 
                         duration_minutes = $duration_minutes,
                         duration_seconds = $duration_seconds,
                         video_source = 'upload',
                         video_file_size = $file_size_bytes
                         WHERE lecture_id = $lecture_id";
        
        if (mysqli_query($con, $update_query)) {
            // Recalculate course total duration
            $recalc_query = "UPDATE courses c SET 
                             total_duration_minutes = (
                                 SELECT COALESCE(SUM(l.duration_minutes), 0) 
                                 FROM lectures l 
                                 INNER JOIN course_sections cs ON l.section_id = cs.section_id 
                                 WHERE cs.course_id = c.course_id
                             )
                             WHERE c.course_id = '$course_id'";
            @mysqli_query($con, $recalc_query);
            
            echo json_encode([
                'success' => true,
                'video_url' => $video_url,
                'file_name' => $file['name'],
                'file_size' => $file_size_mb . ' MB',
                'duration_seconds' => $duration_seconds,
                'duration_minutes' => $duration_minutes,
                'message' => 'Video uploaded successfully'
            ]);
        } else {
            @unlink($target_path);
            echo json_encode(['success' => false, 'error' => 'Failed to update lecture: ' . mysqli_error($con)]);
        }
        break;
        
    case 'embed_video':
        if ($lecture_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        $embed_url = isset($_POST['embed_url']) ? mysqli_real_escape_string($con, $_POST['embed_url']) : '';
        
        if (empty($embed_url)) {
            echo json_encode(['success' => false, 'error' => 'Video URL required']);
            exit;
        }
        
        // First try direct course_id match
        $verify_lecture = "SELECT l.lecture_id FROM lectures l 
                          WHERE l.lecture_id = $lecture_id AND l.course_id = '$course_id'";
        $verify_lecture_result = mysqli_query($con, $verify_lecture);
        
        // Fallback to section join
        if (!$verify_lecture_result || mysqli_num_rows($verify_lecture_result) === 0) {
            $verify_lecture = "SELECT l.lecture_id FROM lectures l 
                              INNER JOIN course_sections cs ON l.section_id = cs.section_id 
                              WHERE l.lecture_id = $lecture_id AND cs.course_id = '$course_id'";
            $verify_lecture_result = mysqli_query($con, $verify_lecture);
        }
        
        if (!$verify_lecture_result || mysqli_num_rows($verify_lecture_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture not found']);
            exit;
        }
        
        $video_id = '';
        $embed_formatted = $embed_url;
        
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $embed_url, $matches)) {
            $video_id = $matches[1];
            $embed_formatted = 'https://www.youtube.com/embed/' . $video_id;
        } elseif (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $embed_url, $matches)) {
            $video_id = $matches[1];
            $embed_formatted = 'https://player.vimeo.com/video/' . $video_id;
        }
        
        $update_query = "UPDATE lectures SET content_url = '$embed_formatted', video_source = 'embed',
                         duration_minutes = 0, duration_seconds = 0,video_file_size = 0 WHERE lecture_id = $lecture_id";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode([
                'success' => true,
                'embed_url' => $embed_formatted,
                'message' => 'Video embedded successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save embedded video']);
        }
        break;
        
    case 'delete_video':
        if ($lecture_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        // First try direct course_id match
        $lecture_query = "SELECT l.content_url FROM lectures l 
                          WHERE l.lecture_id = $lecture_id AND l.course_id = '$course_id'";
        $lecture_result = mysqli_query($con, $lecture_query);
        
        // Fallback to section join
        if (!$lecture_result || mysqli_num_rows($lecture_result) === 0) {
            $lecture_query = "SELECT l.content_url FROM lectures l 
                              INNER JOIN course_sections cs ON l.section_id = cs.section_id 
                              WHERE l.lecture_id = $lecture_id AND cs.course_id = '$course_id'";
            $lecture_result = mysqli_query($con, $lecture_query);
        }
        
        if (!$lecture_result || mysqli_num_rows($lecture_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture not found']);
            exit;
        }
        
        $lecture = mysqli_fetch_assoc($lecture_result);
        
        if (!empty($lecture['content_url']) && strpos($lecture['content_url'], 'uploads/videos/') !== false) {
            $file_path = '../../../' . $lecture['content_url'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        
        $update_query = "UPDATE lectures SET content_url = '', duration_minutes = 0 WHERE lecture_id = $lecture_id";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Video removed successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to remove video']);
        }
        break;
        
    case 'upload_thumbnail':
        if ($lecture_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        // Simply check if lecture exists and get its data
        // Note: Course ownership is already verified at line 25-30
        $verify_lecture = "SELECT lecture_id, thumbnail_url, course_id FROM lectures WHERE lecture_id = $lecture_id";
        $verify_lecture_result = mysqli_query($con, $verify_lecture);
        
        if (!$verify_lecture_result || mysqli_num_rows($verify_lecture_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture not found']);
            exit;
        }
        
        $lecture_data = mysqli_fetch_assoc($verify_lecture_result);
        
        // Verify this lecture belongs to the course we verified ownership for
        if ($lecture_data['course_id'] !== $course_id) {
            echo json_encode(['success' => false, 'error' => 'Lecture does not belong to this course']);
            exit;
        }
        
        if (!isset($_FILES['thumbnail']) || $_FILES['thumbnail']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No image file uploaded']);
            exit;
        }
        
        $file = $_FILES['thumbnail'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp','image/jpg','image/avif'];
        $file_mime = @mime_content_type($file['tmp_name']);
        
        if (!$file_mime || !in_array($file_mime, $allowed_types)) {
            echo json_encode(['success' => false, 'error' => 'Invalid image format. Allowed: JPG, PNG, GIF, WebP']);
            exit;
        }
        
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'error' => 'Image too large. Maximum 5MB allowed']);
            exit;
        }
        
        // Create thumbnails directory if not exists
        $thumb_dir = '../../../uploads/thumbnails/';
        if (!file_exists($thumb_dir)) {
            if (!@mkdir($thumb_dir, 0755, true)) {
                echo json_encode(['success' => false, 'error' => 'Failed to create thumbnails directory']);
                exit;
            }
        }
        
        // Delete old thumbnail if exists
        if (!empty($lecture_data['thumbnail_url']) && file_exists('../../../' . $lecture_data['thumbnail_url'])) {
            @unlink('../../../' . $lecture_data['thumbnail_url']);
        }
        
        // Get file extension
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (empty($file_ext)) {
            // Determine extension from mime type
            $mime_to_ext = [
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/avif' => 'avif'
            ];
            $file_ext = $mime_to_ext[$file_mime] ?? 'jpg';
        }
        
        // Save with original format (no conversion needed)
        $unique_name = 'thumb_' . $course_id . '_' . $lecture_id . '_' . time() . '.' . $file_ext;
        $target_path = $thumb_dir . $unique_name;
        
        // Simply move the uploaded file (no image processing)
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save thumbnail']);
            exit;
        }
        
        $thumbnail_url = 'uploads/thumbnails/' . $unique_name;
        
        // Ensure thumbnail_url column exists
        $check_column = mysqli_query($con, "SHOW COLUMNS FROM lectures LIKE 'thumbnail_url'");
        if (!$check_column || mysqli_num_rows($check_column) == 0) {
            mysqli_query($con, "ALTER TABLE lectures ADD COLUMN thumbnail_url VARCHAR(255) DEFAULT NULL AFTER content_url");
        }
        
        $update_query = "UPDATE lectures SET thumbnail_url = '$thumbnail_url' WHERE lecture_id = $lecture_id";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode([
                'success' => true,
                'thumbnail_url' => $thumbnail_url,
                'message' => 'Thumbnail uploaded successfully'
            ]);
        } else {
            @unlink($target_path);
            echo json_encode(['success' => false, 'error' => 'Failed to update database: ' . mysqli_error($con)]);
        }
        break;
        
    case 'delete_thumbnail':
        if ($lecture_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture ID required']);
            exit;
        }
        
        // First try direct course_id match
        $lecture_query = "SELECT l.thumbnail_url FROM lectures l 
                          WHERE l.lecture_id = $lecture_id AND l.course_id = '$course_id'";
        $lecture_result = mysqli_query($con, $lecture_query);
        
        // Fallback to section join
        if (!$lecture_result || mysqli_num_rows($lecture_result) === 0) {
            $lecture_query = "SELECT l.thumbnail_url FROM lectures l 
                              INNER JOIN course_sections cs ON l.section_id = cs.section_id 
                              WHERE l.lecture_id = $lecture_id AND cs.course_id = '$course_id'";
            $lecture_result = mysqli_query($con, $lecture_query);
        }
        
        if (!$lecture_result || mysqli_num_rows($lecture_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Lecture not found']);
            exit;
        }
        
        $lecture = mysqli_fetch_assoc($lecture_result);
        
        if (!empty($lecture['thumbnail_url'])) {
            $file_path = '../../../' . $lecture['thumbnail_url'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        
        $update_query = "UPDATE lectures SET thumbnail_url = NULL WHERE lecture_id = $lecture_id";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Thumbnail removed successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to remove thumbnail']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
