<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in and is an instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'update_profile':
            updateProfile($con, $user_id);
            break;
        case 'update_instructor_profile':
            updateInstructorProfile($con, $user_id);
            break;
        case 'update_social_links':
            updateSocialLinks($con, $user_id);
            break;
        case 'update_preferences':
            updatePreferences($con, $user_id);
            break;
        case 'load_settings':
            loadSettings($con, $user_id);
            break;
        case 'upload_avatar':
            uploadAvatar($con, $user_id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Update basic user profile
function updateProfile($con, $user_id) {
    $first_name = mysqli_real_escape_string($con, $_POST['first_name'] ?? '');
    $last_name = mysqli_real_escape_string($con, $_POST['last_name'] ?? '');
    $email = mysqli_real_escape_string($con, $_POST['email'] ?? '');
    $phone = mysqli_real_escape_string($con, $_POST['phone'] ?? '');
    $bio = mysqli_real_escape_string($con, $_POST['bio'] ?? '');
    $headline = mysqli_real_escape_string($con, $_POST['headline'] ?? '');
    $occupation = mysqli_real_escape_string($con, $_POST['occupation'] ?? '');
    $company = mysqli_real_escape_string($con, $_POST['company'] ?? '');
    $location = mysqli_real_escape_string($con, $_POST['location'] ?? '');
    $country = mysqli_real_escape_string($con, $_POST['country'] ?? 'Bangladesh');
    $date_of_birth = mysqli_real_escape_string($con, $_POST['date_of_birth'] ?? '');
    $gender = mysqli_real_escape_string($con, $_POST['gender'] ?? '');
    $blood_group = mysqli_real_escape_string($con, $_POST['blood_group'] ?? '');
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    // Check if email already exists for another user
    $check_email = mysqli_query($con, "SELECT user_id FROM users WHERE email = '$email' AND user_id != '$user_id'");
    if (mysqli_num_rows($check_email) > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already in use']);
        return;
    }
    
    $update_query = "UPDATE users SET 
        first_name = '$first_name',
        last_name = '$last_name',
        email = '$email',
        phone = '$phone',
        bio = '$bio',
        headline = '$headline',
        occupation = '$occupation',
        company = '$company',
        location = '$location',
        country = '$country',
        date_of_birth = " . ($date_of_birth ? "'$date_of_birth'" : "NULL") . ",
        gender = " . ($gender ? "'$gender'" : "NULL") . ",
        blood_group = " . ($blood_group ? "'$blood_group'" : "NULL") . ",
        updated_at = CURRENT_TIMESTAMP
        WHERE user_id = '$user_id'";
    
    if (mysqli_query($con, $update_query)) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
    }
}

// Update instructor profile (INSERT or UPDATE)
function updateInstructorProfile($con, $user_id) {
    $instructor_headline = mysqli_real_escape_string($con, $_POST['instructor_headline'] ?? '');
    $instructor_bio = mysqli_real_escape_string($con, $_POST['instructor_bio'] ?? '');
    $teaching_experience = mysqli_real_escape_string($con, $_POST['teaching_experience'] ?? '');
    $expertise_areas = mysqli_real_escape_string($con, $_POST['expertise_areas'] ?? '');
    $years_of_experience = (int)($_POST['years_of_experience'] ?? 0);
    $education = mysqli_real_escape_string($con, $_POST['education'] ?? '');
    $certifications = mysqli_real_escape_string($con, $_POST['certifications'] ?? '');
    $languages = mysqli_real_escape_string($con, $_POST['languages'] ?? '');
    $teaching_style = mysqli_real_escape_string($con, $_POST['teaching_style'] ?? '');
    $payout_email = mysqli_real_escape_string($con, $_POST['payout_email'] ?? '');
    $tax_id = mysqli_real_escape_string($con, $_POST['tax_id'] ?? '');
    $tax_information = mysqli_real_escape_string($con, $_POST['tax_information'] ?? '');
    
    // Check if instructor profile exists
    $check_query = "SELECT instructor_profile_id FROM instructor_profiles WHERE user_id = '$user_id'";
    $result = mysqli_query($con, $check_query);
    
    if (mysqli_num_rows($result) > 0) {
        // UPDATE existing profile
        $update_query = "UPDATE instructor_profiles SET 
            instructor_headline = " . ($instructor_headline ? "'$instructor_headline'" : "NULL") . ",
            instructor_bio = " . ($instructor_bio ? "'$instructor_bio'" : "NULL") . ",
            teaching_experience = " . ($teaching_experience ? "'$teaching_experience'" : "NULL") . ",
            expertise_areas = " . ($expertise_areas ? "'$expertise_areas'" : "NULL") . ",
            years_of_experience = $years_of_experience,
            education = " . ($education ? "'$education'" : "NULL") . ",
            certifications = '$certifications',
            languages = '$languages',
            teaching_style = " . ($teaching_style ? "'$teaching_style'" : "NULL") . ",
            payout_email = " . ($payout_email ? "'$payout_email'" : "NULL") . ",
            tax_id = " . ($tax_id ? "'$tax_id'" : "NULL") . ",
            tax_information = " . ($tax_information ? "'$tax_information'" : "NULL") . ",
            updated_at = CURRENT_TIMESTAMP
            WHERE user_id = '$user_id'";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Instructor profile updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
        }
    } else {
        // INSERT new profile
        $insert_query = "INSERT INTO instructor_profiles (
            user_id, instructor_headline, instructor_bio, teaching_experience,
            expertise_areas, years_of_experience, education, certifications,
            languages, teaching_style, payout_email, tax_id, tax_information
        ) VALUES (
            '$user_id',
            " . ($instructor_headline ? "'$instructor_headline'" : "NULL") . ",
            " . ($instructor_bio ? "'$instructor_bio'" : "NULL") . ",
            " . ($teaching_experience ? "'$teaching_experience'" : "NULL") . ",
            " . ($expertise_areas ? "'$expertise_areas'" : "NULL") . ",
            $years_of_experience,
            " . ($education ? "'$education'" : "NULL") . ",
            '$certifications',
            '$languages',
            " . ($teaching_style ? "'$teaching_style'" : "NULL") . ",
            " . ($payout_email ? "'$payout_email'" : "NULL") . ",
            " . ($tax_id ? "'$tax_id'" : "NULL") . ",
            " . ($tax_information ? "'$tax_information'" : "NULL") . "
        )";
        
        if (mysqli_query($con, $insert_query)) {
            echo json_encode(['success' => true, 'message' => 'Instructor profile created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
        }
    }
}

// Update social links (INSERT or UPDATE)
function updateSocialLinks($con, $user_id) {
    $social_links = json_decode($_POST['social_links'] ?? '[]', true);
    
    if (!is_array($social_links)) {
        echo json_encode(['success' => false, 'message' => 'Invalid social links data']);
        return;
    }
    
    // Start transaction
    mysqli_begin_transaction($con);
    
    try {
        // Delete existing social links
        mysqli_query($con, "DELETE FROM user_social_links WHERE user_id = '$user_id'");
        
        // Insert new social links
        $display_order = 0;
        foreach ($social_links as $link) {
            $platform = mysqli_real_escape_string($con, $link['platform'] ?? '');
            $url = mysqli_real_escape_string($con, $link['url'] ?? '');
            $is_public = isset($link['is_public']) ? (int)$link['is_public'] : 1;
            
            if (!empty($platform) && !empty($url)) {
                // Validate URL
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new Exception("Invalid URL for $platform");
                }
                
                $insert_query = "INSERT INTO user_social_links (
                    user_id, platform, url, display_order, is_public
                ) VALUES (
                    '$user_id', '$platform', '$url', $display_order, $is_public
                )";
                
                if (!mysqli_query($con, $insert_query)) {
                    throw new Exception(mysqli_error($con));
                }
                
                $display_order++;
            }
        }
        
        mysqli_commit($con);
        echo json_encode(['success' => true, 'message' => 'Social links updated successfully']);
        
    } catch (Exception $e) {
        mysqli_rollback($con);
        echo json_encode(['success' => false, 'message' => 'Error updating social links: ' . $e->getMessage()]);
    }
}

// Update user preferences (INSERT or UPDATE)
function updatePreferences($con, $user_id) {
    $theme = mysqli_real_escape_string($con, $_POST['theme'] ?? 'light');
    $notification_email = isset($_POST['notification_email']) ? (int)$_POST['notification_email'] : 1;
    $notification_push = isset($_POST['notification_push']) ? (int)$_POST['notification_push'] : 1;
    $notification_sms = isset($_POST['notification_sms']) ? (int)$_POST['notification_sms'] : 0;
    $notification_course_updates = isset($_POST['notification_course_updates']) ? (int)$_POST['notification_course_updates'] : 1;
    $notification_new_messages = isset($_POST['notification_new_messages']) ? (int)$_POST['notification_new_messages'] : 1;
    $notification_achievements = isset($_POST['notification_achievements']) ? (int)$_POST['notification_achievements'] : 1;
    $notification_certificates = isset($_POST['notification_certificates']) ? (int)$_POST['notification_certificates'] : 1;
    $notification_promotions = isset($_POST['notification_promotions']) ? (int)$_POST['notification_promotions'] : 1;
    $privacy_profile_visible = isset($_POST['privacy_profile_visible']) ? (int)$_POST['privacy_profile_visible'] : 1;
    $privacy_courses_visible = isset($_POST['privacy_courses_visible']) ? (int)$_POST['privacy_courses_visible'] : 1;
    $privacy_achievements_visible = isset($_POST['privacy_achievements_visible']) ? (int)$_POST['privacy_achievements_visible'] : 1;
    $auto_play_videos = isset($_POST['auto_play_videos']) ? (int)$_POST['auto_play_videos'] : 1;
    $video_quality = mysqli_real_escape_string($con, $_POST['video_quality'] ?? 'auto');
    $subtitle_language = mysqli_real_escape_string($con, $_POST['subtitle_language'] ?? 'en');
    
    // Check if preferences exist
    $check_query = "SELECT preference_id FROM user_preferences WHERE user_id = '$user_id'";
    $result = mysqli_query($con, $check_query);
    
    if (mysqli_num_rows($result) > 0) {
        // UPDATE existing preferences
        $update_query = "UPDATE user_preferences SET 
            theme = '$theme',
            notification_email = $notification_email,
            notification_push = $notification_push,
            notification_sms = $notification_sms,
            notification_course_updates = $notification_course_updates,
            notification_new_messages = $notification_new_messages,
            notification_achievements = $notification_achievements,
            notification_certificates = $notification_certificates,
            notification_promotions = $notification_promotions,
            privacy_profile_visible = $privacy_profile_visible,
            privacy_courses_visible = $privacy_courses_visible,
            privacy_achievements_visible = $privacy_achievements_visible,
            auto_play_videos = $auto_play_videos,
            video_quality = '$video_quality',
            subtitle_language = '$subtitle_language',
            updated_at = CURRENT_TIMESTAMP
            WHERE user_id = '$user_id'";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Preferences updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
        }
    } else {
        // INSERT new preferences
        $insert_query = "INSERT INTO user_preferences (
            user_id, theme, notification_email, notification_push, notification_sms,
            notification_course_updates, notification_new_messages, notification_achievements,
            notification_certificates, notification_promotions, privacy_profile_visible,
            privacy_courses_visible, privacy_achievements_visible, auto_play_videos,
            video_quality, subtitle_language
        ) VALUES (
            '$user_id', '$theme', $notification_email, $notification_push, $notification_sms,
            $notification_course_updates, $notification_new_messages, $notification_achievements,
            $notification_certificates, $notification_promotions, $privacy_profile_visible,
            $privacy_courses_visible, $privacy_achievements_visible, $auto_play_videos,
            '$video_quality', '$subtitle_language'
        )";
        
        if (mysqli_query($con, $insert_query)) {
            echo json_encode(['success' => true, 'message' => 'Preferences created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
        }
    }
}

// Load all settings data
function loadSettings($con, $user_id) {
    $settings = [];
    
    // Load user profile
    $user_query = "SELECT * FROM users WHERE user_id = '$user_id'";
    $user_result = mysqli_query($con, $user_query);
    $settings['user'] = mysqli_fetch_assoc($user_result);
    
    // Load instructor profile
    $instructor_query = "SELECT * FROM instructor_profiles WHERE user_id = '$user_id'";
    $instructor_result = mysqli_query($con, $instructor_query);
    $settings['instructor_profile'] = mysqli_fetch_assoc($instructor_result);
    
    // Load social links
    $social_query = "SELECT * FROM user_social_links WHERE user_id = '$user_id' ORDER BY display_order";
    $social_result = mysqli_query($con, $social_query);
    $settings['social_links'] = [];
    while ($row = mysqli_fetch_assoc($social_result)) {
        $settings['social_links'][] = $row;
    }
    
    // Load preferences
    $prefs_query = "SELECT * FROM user_preferences WHERE user_id = '$user_id'";
    $prefs_result = mysqli_query($con, $prefs_query);
    $settings['preferences'] = mysqli_fetch_assoc($prefs_result);
    
    echo json_encode(['success' => true, 'data' => $settings]);
}

// Upload avatar/profile image
function uploadAvatar($con, $user_id) {
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        return;
    }
    
    $file = $_FILES['avatar'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP allowed']);
        return;
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB']);
        return;
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = '../public/uploads/avatars/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    $db_path = 'public/uploads/avatars/' . $filename;
    
    // Get old image path to delete it
    $result = mysqli_query($con, "SELECT profile_image_url FROM users WHERE user_id = '$user_id'");
    $user = mysqli_fetch_assoc($result);
    $old_image = $user['profile_image_url'];
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update database
        $update_query = "UPDATE users SET profile_image_url = '$db_path', updated_at = CURRENT_TIMESTAMP WHERE user_id = '$user_id'";
        
        if (mysqli_query($con, $update_query)) {
            // Delete old image if it exists and is not a default avatar
            if ($old_image && file_exists('../' . $old_image) && strpos($old_image, 'avatars/') !== false) {
                @unlink('../' . $old_image);
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Avatar uploaded successfully',
                'image_url' => $db_path
            ]);
        } else {
            // Delete uploaded file if database update fails
            @unlink($filepath);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    }
}
