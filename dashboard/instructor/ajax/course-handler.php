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

switch ($action) {
    case 'update_pricing':
        $course_id = isset($_POST['course_id']) ? mysqli_real_escape_string($con, $_POST['course_id']) : '';
        
        if (empty($course_id)) {
            echo json_encode(['success' => false, 'error' => 'Course ID required']);
            exit;
        }
        
        // Verify ownership and get current prices
        $verify_query = "SELECT course_id, price, original_price, is_free FROM courses WHERE course_id = '$course_id' AND instructor_id = '$current_user_id'";
        $verify_result = mysqli_query($con, $verify_query);
        if (!$verify_result || mysqli_num_rows($verify_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Access denied to this course']);
            exit;
        }
        
        $current_course = mysqli_fetch_assoc($verify_result);
        $old_price = $current_course['price'];
        $old_original_price = $current_course['original_price'];
        $old_is_free = $current_course['is_free'];
        
        // Get pricing data
        $is_free = isset($_POST['is_free']) && $_POST['is_free'] == '1';
        $price = floatval($_POST['price'] ?? 0);
        $original_price = floatval($_POST['original_price'] ?? 0);
        $currency = mysqli_real_escape_string($con, $_POST['currency'] ?? 'BDT');
        $change_reason = isset($_POST['change_reason']) ? mysqli_real_escape_string($con, $_POST['change_reason']) : null;
        
        // If marked as free, set prices to 0
        if ($is_free) {
            $price = 0;
            $original_price = 0;
        }
        
        // Validate prices
        if (!$is_free && $price < 0) {
            echo json_encode(['success' => false, 'error' => 'Price cannot be negative']);
            exit;
        }
        
        if ($original_price > 0 && $original_price < $price) {
            echo json_encode(['success' => false, 'error' => 'Original price must be higher than current price']);
            exit;
        }
        
        // If no original price set, use current price
        if ($original_price == 0 && $price > 0) {
            $original_price = $price;
        }
        
        $new_is_free = $is_free ? 1 : 0;
        
        // Update pricing
        $update_query = "UPDATE courses SET 
                        price = $price, 
                        original_price = $original_price, 
                        currency = '$currency',
                        is_free = $new_is_free,
                        last_updated_at = NOW()
                        WHERE course_id = '$course_id' AND instructor_id = '$current_user_id'";
        
        if (mysqli_query($con, $update_query)) {
            // Record price change in history if price actually changed
            if ($old_price != $price || $old_original_price != $original_price || $old_is_free != $new_is_free) {
                // Create price_history table if it doesn't exist
                $create_table = "CREATE TABLE IF NOT EXISTS `price_history` (
                    `history_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `course_id` varchar(20) NOT NULL,
                    `old_price` decimal(8,2) UNSIGNED NOT NULL DEFAULT 0.00,
                    `new_price` decimal(8,2) UNSIGNED NOT NULL DEFAULT 0.00,
                    `old_original_price` decimal(8,2) UNSIGNED DEFAULT NULL,
                    `new_original_price` decimal(8,2) UNSIGNED DEFAULT NULL,
                    `old_is_free` tinyint(1) NOT NULL DEFAULT 0,
                    `new_is_free` tinyint(1) NOT NULL DEFAULT 0,
                    `change_reason` varchar(255) DEFAULT NULL,
                    `changed_by` varchar(255) NOT NULL,
                    `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`history_id`),
                    KEY `idx_course_id` (`course_id`),
                    KEY `idx_changed_at` (`changed_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                mysqli_query($con, $create_table);
                
                // Record price change in history
                $reason_sql = $change_reason ? "'$change_reason'" : "NULL";
                $old_orig_sql = $old_original_price !== null ? $old_original_price : "NULL";
                $new_orig_sql = $original_price > 0 ? $original_price : "NULL";
                
                $history_query = "INSERT INTO price_history 
                    (course_id, old_price, new_price, old_original_price, new_original_price, old_is_free, new_is_free, change_reason, changed_by)
                    VALUES ('$course_id', $old_price, $price, $old_orig_sql, $new_orig_sql, $old_is_free, $new_is_free, $reason_sql, '$current_user_id')";
                mysqli_query($con, $history_query);
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Pricing updated successfully',
                'pricing' => [
                    'price' => $price,
                    'original_price' => $original_price,
                    'currency' => $currency,
                    'is_free' => $is_free
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update pricing: ' . mysqli_error($con)]);
        }
        break;
        
    // ========================================
    // CREATE COURSE - NEW IMPLEMENTATION
    // ========================================
    case 'create_course':
        $title = isset($_POST['title']) ? trim(mysqli_real_escape_string($con, $_POST['title'])) : '';
        $category_id = isset($_POST['category_id']) ? mysqli_real_escape_string($con, $_POST['category_id']) : '';
        $level = isset($_POST['level']) ? mysqli_real_escape_string($con, $_POST['level']) : 'beginner';
        $language = isset($_POST['language']) ? mysqli_real_escape_string($con, $_POST['language']) : 'English';
        $description = isset($_POST['description']) ? trim(mysqli_real_escape_string($con, $_POST['description'])) : '';
        $duration_hours = isset($_POST['duration_hours']) ? floatval($_POST['duration_hours']) : 0;
        $is_free = isset($_POST['is_free']) && $_POST['is_free'] == '1';
        $price = floatval($_POST['price'] ?? 0);
        $original_price = floatval($_POST['original_price'] ?? 0);
        
        // Validation
        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Course title is required']);
            exit;
        }
        
        if (strlen($title) > 150) {
            echo json_encode(['success' => false, 'error' => 'Course title cannot exceed 150 characters']);
            exit;
        }
        
        if (empty($category_id)) {
            echo json_encode(['success' => false, 'error' => 'Category is required']);
            exit;
        }
        
        if (empty($description)) {
            echo json_encode(['success' => false, 'error' => 'Description is required']);
            exit;
        }
        
        // Generate unique course ID
        $course_id = 'CRS_' . strtoupper(substr(uniqid(), -8)) . rand(10, 99);
        
        // Generate slug from title
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        
        // Handle thumbnail upload
        $thumbnail_url = NULL;
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../../public/uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'course_' . $course_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $upload_path)) {
                    $thumbnail_url = 'public/uploads/' . $new_filename;
                }
            }
        }
        
        // Handle pricing
        if ($is_free) {
            $price = 0;
            $original_price = 0;
        } else {
            if ($original_price == 0) {
                $original_price = $price;
            }
        }
        
        // Insert course
        $insert_query = "INSERT INTO courses (
            course_id, 
            instructor_id, 
            category_id, 
            title, 
            slug, 
            description, 
            level, 
            language, 
            duration_hours,
            price, 
            original_price, 
            currency,
            is_free,
            thumbnail_url,
            status,
            created_at,
            updated_at
        ) VALUES (
            '$course_id', 
            '$current_user_id', 
            '$category_id', 
            '$title', 
            '$slug', 
            '$description', 
            '$level', 
            '$language',
            $duration_hours, 
            $price, 
            $original_price, 
            'BDT',
            " . ($is_free ? '1' : '0') . ",
            " . ($thumbnail_url ? "'$thumbnail_url'" : "NULL") . ",
            'draft',
            NOW(),
            NOW()
        )";
        
        if (mysqli_query($con, $insert_query)) {
            echo json_encode([
                'success' => true, 
                'course_id' => $course_id,
                'message' => 'Course created successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create course: ' . mysqli_error($con)]);
        }
        break;

    // ========================================
    // GET COURSE DETAILS
    // ========================================
    case 'get_course':
        $course_id = isset($_GET['course_id']) ? mysqli_real_escape_string($con, $_GET['course_id']) : '';
        
        if (empty($course_id)) {
            echo json_encode(['success' => false, 'error' => 'Course ID required']);
            exit;
        }
        
        // Verify ownership
        $query = "SELECT * FROM courses WHERE course_id = '$course_id' AND instructor_id = '$current_user_id'";
        $result = mysqli_query($con, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $course = mysqli_fetch_assoc($result);
            echo json_encode([
                'success' => true, 
                'course' => $course
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Course not found or access denied']);
        }
        break;

    case 'create':
        $title = isset($_POST['title']) ? mysqli_real_escape_string($con, $_POST['title']) : '';
        $category_id = isset($_POST['category_id']) ? mysqli_real_escape_string($con, $_POST['category_id']) : '';
        $level = isset($_POST['level']) ? mysqli_real_escape_string($con, $_POST['level']) : 'beginner';
        $language = isset($_POST['language']) ? mysqli_real_escape_string($con, $_POST['language']) : 'english';
        $description = isset($_POST['description']) ? mysqli_real_escape_string($con, $_POST['description']) : '';
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
        $original_price = isset($_POST['original_price']) ? floatval($_POST['original_price']) : $price;
        $course_type = isset($_POST['course_type']) ? $_POST['course_type'] : 'premium';
        
        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Course title required']);
            exit;
        }
        
        $course_id = 'COURSE_' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        if ($course_type === 'free') {
            $price = 0;
            $original_price = 0;
        }
        
        $insert_query = "INSERT INTO courses (course_id, instructor_id, title, category_id, level, language, description, price, original_price, status) 
                         VALUES ('$course_id', '$current_user_id', '$title', '$category_id', '$level', '$language', '$description', $price, $original_price, 'draft')";
        
        if (mysqli_query($con, $insert_query)) {
            echo json_encode([
                'success' => true, 
                'course_id' => $course_id,
                'message' => 'Course created successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create course: ' . mysqli_error($con)]);
        }
        break;
        
    case 'update':
        $course_id = isset($_POST['course_id']) ? mysqli_real_escape_string($con, $_POST['course_id']) : '';
        
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
        
        $updates = [];
        
        if (isset($_POST['title'])) {
            $title = mysqli_real_escape_string($con, $_POST['title']);
            $updates[] = "title = '$title'";
        }
        if (isset($_POST['category_id'])) {
            $category_id = mysqli_real_escape_string($con, $_POST['category_id']);
            $updates[] = "category_id = '$category_id'";
        }
        if (isset($_POST['level'])) {
            $level = mysqli_real_escape_string($con, $_POST['level']);
            $updates[] = "level = '$level'";
        }
        if (isset($_POST['description'])) {
            $description = mysqli_real_escape_string($con, $_POST['description']);
            $updates[] = "description = '$description'";
        }
        if (isset($_POST['long_description'])) {
            $long_description = mysqli_real_escape_string($con, $_POST['long_description']);
            $updates[] = "long_description = '$long_description'";
        }
        if (isset($_POST['price'])) {
            $price = floatval($_POST['price']);
            $updates[] = "price = $price";
        }
        if (isset($_POST['original_price'])) {
            $original_price = floatval($_POST['original_price']);
            $updates[] = "original_price = $original_price";
        }
        if (isset($_POST['status'])) {
            $status = mysqli_real_escape_string($con, $_POST['status']);
            $updates[] = "status = '$status'";
        }
        if (isset($_POST['learning_objectives'])) {
            $learning_objectives = mysqli_real_escape_string($con, $_POST['learning_objectives']);
            $updates[] = "learning_objectives = '$learning_objectives'";
        }
        if (isset($_POST['requirements'])) {
            $requirements = mysqli_real_escape_string($con, $_POST['requirements']);
            $updates[] = "requirements = '$requirements'";
        }
        if (isset($_POST['target_audience'])) {
            $target_audience = mysqli_real_escape_string($con, $_POST['target_audience']);
            $updates[] = "target_audience = '$target_audience'";
        }
        
        // Handle thumbnail upload if new file provided
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../../public/uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                // Get old thumbnail to delete
                $old_thumb_query = "SELECT thumbnail_url FROM courses WHERE course_id = '$course_id'";
                $old_thumb_result = mysqli_query($con, $old_thumb_query);
                if ($old_thumb_result && $row = mysqli_fetch_assoc($old_thumb_result)) {
                    $old_thumbnail = $row['thumbnail_url'];
                    if ($old_thumbnail && file_exists('../../../' . $old_thumbnail)) {
                        @unlink('../../../' . $old_thumbnail);
                    }
                }
                
                $new_filename = 'course_' . $course_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $upload_path)) {
                    $thumbnail_url = 'public/uploads/' . $new_filename;
                    $updates[] = "thumbnail_url = '$thumbnail_url'";
                }
            }
        }
        
        if (empty($updates)) {
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            exit;
        }
        
        $updates[] = "updated_at = NOW()";
        
        $update_query = "UPDATE courses SET " . implode(', ', $updates) . " WHERE course_id = '$course_id'";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Course updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update course']);
        }
        break;
        
    case 'delete':
        $course_id = isset($_POST['course_id']) ? mysqli_real_escape_string($con, $_POST['course_id']) : '';
        
        if (empty($course_id)) {
            echo json_encode(['success' => false, 'error' => 'Course ID required']);
            exit;
        }
        
        // Verify ownership
        $verify_query = "SELECT course_id, thumbnail_url FROM courses WHERE course_id = '$course_id' AND instructor_id = '$current_user_id'";
        $verify_result = mysqli_query($con, $verify_query);
        if (!$verify_result || mysqli_num_rows($verify_result) === 0) {
            echo json_encode(['success' => false, 'error' => 'Access denied to this course']);
            exit;
        }
        
        $course_data = mysqli_fetch_assoc($verify_result);
        $thumbnail_url = $course_data['thumbnail_url'];
        
        // ==========================================
        // STEP 1: Delete all physical files
        // ==========================================
        
        // Get all sections for this course
        $sections_query = "SELECT section_id FROM course_sections WHERE course_id = '$course_id'";
        $sections_result = mysqli_query($con, $sections_query);
        $section_ids = [];
        $lecture_ids = [];
        
        while ($row = mysqli_fetch_assoc($sections_result)) {
            $section_ids[] = "'" . $row['section_id'] . "'";
        }
        
        // Get all lectures for these sections and course
        if (!empty($section_ids)) {
            $section_list = implode(',', $section_ids);
            $lectures_query = "SELECT lecture_id, content_url, video_provider FROM lectures WHERE section_id IN ($section_list) OR course_id = '$course_id'";
        } else {
            $lectures_query = "SELECT lecture_id, content_url, video_provider FROM lectures WHERE course_id = '$course_id'";
        }
        
        $lectures_result = mysqli_query($con, $lectures_query);
        
        // Delete lecture video files
        while ($lecture = mysqli_fetch_assoc($lectures_result)) {
            $lecture_ids[] = $lecture['lecture_id'];
            
            // Delete self-hosted video files
            if ($lecture['content_url'] && $lecture['video_provider'] === 'self_hosted') {
                $video_path = '../../../' . $lecture['content_url'];
                if (file_exists($video_path)) {
                    @unlink($video_path);
                }
            }
        }
        
        // Delete lecture resource files
        if (!empty($lecture_ids)) {
            $lecture_id_list = implode(',', $lecture_ids);
            $resources_query = "SELECT resource_id, file_url, resource_type FROM lecture_resources WHERE lecture_id IN ($lecture_id_list)";
            $resources_result = mysqli_query($con, $resources_query);
            
            while ($resource = mysqli_fetch_assoc($resources_result)) {
                // Delete physical file if it's not a link
                if ($resource['resource_type'] !== 'link' && $resource['file_url']) {
                    $resource_path = '../../../' . $resource['file_url'];
                    if (file_exists($resource_path)) {
                        @unlink($resource_path);
                    }
                }
            }
        }
        
        // Delete assignment submission files
        $submissions_query = "SELECT submission_id, file_url FROM assignment_submissions 
                             WHERE assignment_id IN (SELECT assignment_id FROM assignments WHERE course_id = '$course_id')";
        $submissions_result = mysqli_query($con, $submissions_query);
        
        while ($submission = mysqli_fetch_assoc($submissions_result)) {
            if ($submission['file_url']) {
                $submission_path = '../../../' . $submission['file_url'];
                if (file_exists($submission_path)) {
                    @unlink($submission_path);
                }
            }
        }
        
        // Delete course thumbnail
        if ($thumbnail_url) {
            $thumbnail_path = '../../../' . $thumbnail_url;
            if (file_exists($thumbnail_path)) {
                @unlink($thumbnail_path);
            }
        }
        
        // ==========================================
        // STEP 2: Delete all database records
        // ==========================================
        
        // Delete lecture resources
        if (!empty($lecture_ids)) {
            $lecture_id_list = implode(',', $lecture_ids);
            mysqli_query($con, "DELETE FROM lecture_resources WHERE lecture_id IN ($lecture_id_list)");
        }
        
        // Delete lectures
        if (!empty($section_ids)) {
            $section_list = implode(',', $section_ids);
            mysqli_query($con, "DELETE FROM lectures WHERE section_id IN ($section_list)");
        }
        mysqli_query($con, "DELETE FROM lectures WHERE course_id = '$course_id'");
        
        // Delete course sections
        mysqli_query($con, "DELETE FROM course_sections WHERE course_id = '$course_id'");
        
        // Delete assignment submissions
        mysqli_query($con, "DELETE FROM assignment_submissions 
                           WHERE assignment_id IN (SELECT assignment_id FROM assignments WHERE course_id = '$course_id')");
        
        // Delete assignments
        mysqli_query($con, "DELETE FROM assignments WHERE course_id = '$course_id'");
        
        // Delete quizzes and quiz questions
        mysqli_query($con, "DELETE FROM quiz_questions 
                           WHERE quiz_id IN (SELECT quiz_id FROM quizzes WHERE course_id = '$course_id')");
        mysqli_query($con, "DELETE FROM quizzes WHERE course_id = '$course_id'");
        
        // Delete student progress
        mysqli_query($con, "DELETE FROM student_progress WHERE course_id = '$course_id'");
        
        // Delete course notes
        mysqli_query($con, "DELETE FROM course_notes WHERE course_id = '$course_id'");
        
        // Delete bookmarks
        mysqli_query($con, "DELETE FROM bookmarks WHERE course_id = '$course_id'");
        
        // Delete discussions and replies
        mysqli_query($con, "DELETE FROM discussion_replies 
                           WHERE discussion_id IN (SELECT discussion_id FROM discussions WHERE course_id = '$course_id')");
        mysqli_query($con, "DELETE FROM discussions WHERE course_id = '$course_id'");
        
        // Delete enrollments
        mysqli_query($con, "DELETE FROM enrollments WHERE course_id = '$course_id'");
        
        // Delete reviews
        mysqli_query($con, "DELETE FROM reviews WHERE course_id = '$course_id'");
        
        // Delete certificates
        mysqli_query($con, "DELETE FROM certificates WHERE course_id = '$course_id'");
        
        // Delete course announcements
        mysqli_query($con, "DELETE FROM course_announcements WHERE course_id = '$course_id'");
        
        // Delete course analytics
        mysqli_query($con, "DELETE FROM course_analytics WHERE course_id = '$course_id'");
        
        // Delete wishlist entries
        mysqli_query($con, "DELETE FROM wishlists WHERE course_id = '$course_id'");
        
        // Delete cart items
        mysqli_query($con, "DELETE FROM cart WHERE course_id = '$course_id'");
        
        // Delete course tags
        mysqli_query($con, "DELETE FROM course_tag_mappings WHERE course_id = '$course_id'");
        
        // Finally, delete the course itself
        $delete_query = "DELETE FROM courses WHERE course_id = '$course_id'";
        
        if (mysqli_query($con, $delete_query)) {
            echo json_encode([
                'success' => true, 
                'message' => 'Course and all related content deleted successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete course: ' . mysqli_error($con)]);
        }
        break;
        
    case 'get':
        $course_id = isset($_POST['course_id']) ? mysqli_real_escape_string($con, $_POST['course_id']) : (isset($_GET['course_id']) ? mysqli_real_escape_string($con, $_GET['course_id']) : '');
        
        if (empty($course_id)) {
            echo json_encode(['success' => false, 'error' => 'Course ID required']);
            exit;
        }
        
        $course_query = "SELECT c.*, cat.name as category_name 
                         FROM courses c 
                         LEFT JOIN categories cat ON c.category_id = cat.category_id 
                         WHERE c.course_id = '$course_id' AND c.instructor_id = '$current_user_id'";
        $course_result = mysqli_query($con, $course_query);
        
        if ($course_result && mysqli_num_rows($course_result) > 0) {
            $course = mysqli_fetch_assoc($course_result);
            echo json_encode(['success' => true, 'course' => $course]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Course not found']);
        }
        break;
        
    case 'list':
        $status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : 'all';
        $search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
        
        $where = "c.instructor_id = '$current_user_id'";
        
        if ($status_filter !== 'all') {
            $where .= " AND c.status = '$status_filter'";
        }
        
        if (!empty($search)) {
            $where .= " AND c.title LIKE '%$search%'";
        }
        
        $courses_query = "SELECT c.*, cat.name as category_name,
                          (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id) as student_count
                          FROM courses c 
                          LEFT JOIN categories cat ON c.category_id = cat.category_id 
                          WHERE $where
                          ORDER BY c.created_at DESC";
        $courses_result = mysqli_query($con, $courses_query);
        
        $courses = [];
        while ($row = mysqli_fetch_assoc($courses_result)) {
            $courses[] = $row;
        }
        
        echo json_encode(['success' => true, 'courses' => $courses]);
        break;
        
    case 'create_section':
        $course_id = isset($_POST['course_id']) ? mysqli_real_escape_string($con, $_POST['course_id']) : '';
        $title = isset($_POST['title']) ? mysqli_real_escape_string($con, $_POST['title']) : '';
        
        if (empty($course_id) || empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Course ID and title required']);
            exit;
        }
        
        // Verify ownership
        $check = mysqli_query($con, "SELECT course_id FROM courses WHERE course_id = '$course_id' AND instructor_id = '$instructor_id'");
        if (!$check || mysqli_num_rows($check) == 0) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
        
        // Get max sort order
        $max_order = mysqli_query($con, "SELECT MAX(sort_order) as max_order FROM course_sections WHERE course_id = '$course_id'");
        $sort = mysqli_fetch_assoc($max_order)['max_order'] ?? 0;
        $sort++;
        
        $section_id = uniqid('sec_');
        $query = "INSERT INTO course_sections (section_id, course_id, title, sort_order) VALUES ('$section_id', '$course_id', '$title', $sort)";
        
        if (mysqli_query($con, $query)) {
            echo json_encode(['success' => true, 'section_id' => $section_id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create section']);
        }
        break;
        
    case 'update_section':
        $section_id = isset($_POST['section_id']) ? mysqli_real_escape_string($con, $_POST['section_id']) : '';
        $title = isset($_POST['title']) ? mysqli_real_escape_string($con, $_POST['title']) : '';
        
        // Verify ownership via join
        $check = mysqli_query($con, "SELECT cs.section_id FROM course_sections cs INNER JOIN courses c ON cs.course_id = c.course_id WHERE cs.section_id = '$section_id' AND c.instructor_id = '$instructor_id'");
        if (!$check || mysqli_num_rows($check) == 0) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
        
        mysqli_query($con, "UPDATE course_sections SET title = '$title' WHERE section_id = '$section_id'");
        echo json_encode(['success' => true]);
        break;
        
    case 'delete_section':
        $section_id = isset($_POST['section_id']) ? mysqli_real_escape_string($con, $_POST['section_id']) : '';
        
        // Verify ownership
        $check = mysqli_query($con, "SELECT cs.section_id FROM course_sections cs INNER JOIN courses c ON cs.course_id = c.course_id WHERE cs.section_id = '$section_id' AND c.instructor_id = '$instructor_id'");
        if (!$check || mysqli_num_rows($check) == 0) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
        
        // Delete lectures first
        mysqli_query($con, "DELETE FROM lectures WHERE section_id = '$section_id'");
        mysqli_query($con, "DELETE FROM course_sections WHERE section_id = '$section_id'");
        echo json_encode(['success' => true]);
        break;
        
    case 'create_lecture':
        $section_id = isset($_POST['section_id']) ? mysqli_real_escape_string($con, $_POST['section_id']) : '';
        $title = isset($_POST['title']) ? mysqli_real_escape_string($con, $_POST['title']) : '';
        $video_url = isset($_POST['video_url']) ? mysqli_real_escape_string($con, $_POST['video_url']) : '';
        $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
        $is_preview = isset($_POST['is_preview']) ? 1 : 0;
        
        if (empty($section_id) || empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Section ID and title required']);
            exit;
        }
        
        // Verify ownership
        $check = mysqli_query($con, "SELECT cs.section_id FROM course_sections cs INNER JOIN courses c ON cs.course_id = c.course_id WHERE cs.section_id = '$section_id' AND c.instructor_id = '$instructor_id'");
        if (!$check || mysqli_num_rows($check) == 0) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
        
        // Get max sort order
        $max_order = mysqli_query($con, "SELECT MAX(sort_order) as max_order FROM lectures WHERE section_id = '$section_id'");
        $sort = mysqli_fetch_assoc($max_order)['max_order'] ?? 0;
        $sort++;
        
        $lecture_id = uniqid('lec_');
        $query = "INSERT INTO lectures (lecture_id, section_id, title, video_url, duration, is_preview, sort_order) VALUES ('$lecture_id', '$section_id', '$title', '$video_url', $duration, $is_preview, $sort)";
        
        if (mysqli_query($con, $query)) {
            echo json_encode(['success' => true, 'lecture_id' => $lecture_id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create lecture']);
        }
        break;
        
    case 'delete_lecture':
        $lecture_id = isset($_POST['lecture_id']) ? mysqli_real_escape_string($con, $_POST['lecture_id']) : '';
        
        // Verify ownership
        $check = mysqli_query($con, "SELECT l.lecture_id FROM lectures l INNER JOIN course_sections cs ON l.section_id = cs.section_id INNER JOIN courses c ON cs.course_id = c.course_id WHERE l.lecture_id = '$lecture_id' AND c.instructor_id = '$instructor_id'");
        if (!$check || mysqli_num_rows($check) == 0) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
        
        mysqli_query($con, "DELETE FROM lectures WHERE lecture_id = '$lecture_id'");
        echo json_encode(['success' => true]);
        break;
        
    case 'toggle_publish':
        $course_id = isset($_POST['course_id']) ? mysqli_real_escape_string($con, $_POST['course_id']) : '';
        
        // Verify ownership
        $check = mysqli_query($con, "SELECT status FROM courses WHERE course_id = '$course_id' AND instructor_id = '$instructor_id'");
        if (!$check || mysqli_num_rows($check) == 0) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }
        
        $current = mysqli_fetch_assoc($check)['status'];
        $new_status = ($current === 'published') ? 'draft' : 'published';
        
        mysqli_query($con, "UPDATE courses SET status = '$new_status' WHERE course_id = '$course_id'");
        echo json_encode(['success' => true, 'status' => $new_status]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
