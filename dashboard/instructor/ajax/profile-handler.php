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
$user_id = $current_user_id;

// Debug: check if user_id is set
if (empty($user_id)) {
    echo json_encode(['success' => false, 'error' => 'User ID not found in session']);
    exit;
}

switch ($action) {
    case 'get':
        $query = "SELECT user_id, first_name, last_name, email, phone, 
                         profile_image_url, bio, location, title, 
                         specialization, experience, created_at
                  FROM users WHERE user_id = '$user_id'";
        $result = mysqli_query($con, $query);
        
        if ($result && $row = mysqli_fetch_assoc($result)) {
            echo json_encode(['success' => true, 'profile' => $row]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Profile not found']);
        }
        break;
        
    case 'update_personal':
        $first_name = isset($_POST['first_name']) ? mysqli_real_escape_string($con, trim($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? mysqli_real_escape_string($con, trim($_POST['last_name'])) : '';
        $email = isset($_POST['email']) ? mysqli_real_escape_string($con, trim($_POST['email'])) : '';
        $phone = isset($_POST['phone']) ? mysqli_real_escape_string($con, trim($_POST['phone'])) : '';
        $location = isset($_POST['location']) ? mysqli_real_escape_string($con, trim($_POST['location'])) : '';
        
        if (empty($first_name) || empty($email)) {
            echo json_encode(['success' => false, 'error' => 'Name and email are required']);
            exit;
        }
        
        // Check if email is already used by another user
        $email_check = "SELECT user_id FROM users WHERE email = '$email' AND user_id != '$user_id'";
        $email_result = mysqli_query($con, $email_check);
        if ($email_result && mysqli_num_rows($email_result) > 0) {
            echo json_encode(['success' => false, 'error' => 'Email already in use']);
            exit;
        }
        
        $update_query = "UPDATE users SET 
                         first_name = '$first_name',
                         last_name = '$last_name',
                         email = '$email',
                         phone = '$phone',
                         location = '$location'
                         WHERE user_id = '$user_id'";
        
        if (mysqli_query($con, $update_query)) {
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
            echo json_encode(['success' => true, 'message' => 'Personal info updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update personal info']);
        }
        break;
        
    case 'update_professional':
        $title = isset($_POST['title']) ? mysqli_real_escape_string($con, trim($_POST['title'])) : '';
        $specialization = isset($_POST['specialization']) ? mysqli_real_escape_string($con, trim($_POST['specialization'])) : '';
        $experience_years = isset($_POST['experience_years']) ? mysqli_real_escape_string($con, trim($_POST['experience_years'])) : '';
        $education = isset($_POST['education']) ? mysqli_real_escape_string($con, trim($_POST['education'])) : '';
        
        $update_query = "UPDATE users SET 
                         title = '$title',
                         specialization = '$specialization',
                         experience = '$experience_years',
                         education = '$education'
                         WHERE user_id = '$user_id'";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Professional info updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update professional info']);
        }
        break;
        
    case 'update_bio':
        $bio = isset($_POST['bio']) ? mysqli_real_escape_string($con, trim($_POST['bio'])) : '';
        
        $update_query = "UPDATE users SET bio = '$bio' WHERE user_id = '$user_id'";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Biography updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update biography']);
        }
        break;
        
    case 'update_social':
        $website = isset($_POST['website']) ? mysqli_real_escape_string($con, trim($_POST['website'])) : '';
        $linkedin = isset($_POST['linkedin']) ? mysqli_real_escape_string($con, trim($_POST['linkedin'])) : '';
        $github = isset($_POST['github']) ? mysqli_real_escape_string($con, trim($_POST['github'])) : '';
        $twitter = isset($_POST['twitter']) ? mysqli_real_escape_string($con, trim($_POST['twitter'])) : '';
        
        $social_data = [
            'website' => $website,
            'linkedin' => $linkedin,
            'github' => $github,
            'twitter' => $twitter
        ];
        
        $success = true;
        foreach ($social_data as $platform => $url) {
            if (!empty($url)) {
                // Check if entry exists
                $check_query = "SELECT social_link_id FROM user_social_links WHERE user_id = '$user_id' AND platform = '$platform'";
                $check_result = mysqli_query($con, $check_query);
                
                if ($check_result && mysqli_num_rows($check_result) > 0) {
                    // Update existing
                    $update_query = "UPDATE user_social_links SET url = '$url' WHERE user_id = '$user_id' AND platform = '$platform'";
                    if (!mysqli_query($con, $update_query)) {
                        $success = false;
                    }
                } else {
                    // Insert new
                    $insert_query = "INSERT INTO user_social_links (user_id, platform, url) VALUES ('$user_id', '$platform', '$url')";
                    if (!mysqli_query($con, $insert_query)) {
                        $success = false;
                    }
                }
            } else {
                // Delete if empty
                $delete_query = "DELETE FROM user_social_links WHERE user_id = '$user_id' AND platform = '$platform'";
                mysqli_query($con, $delete_query);
            }
        }
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Social links updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update social links']);
        }
        break;
        
    case 'change_password':
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            echo json_encode(['success' => false, 'error' => 'All password fields are required']);
            exit;
        }
        
        if (strlen($new_password) < 8) {
            echo json_encode(['success' => false, 'error' => 'New password must be at least 8 characters']);
            exit;
        }
        
        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'error' => 'New passwords do not match']);
            exit;
        }
        
        // Ensure user_id is properly escaped
        $user_id_escaped = mysqli_real_escape_string($con, $user_id);
        
        // Verify current password
        $user_query = "SELECT password_hash FROM users WHERE user_id = '$user_id_escaped'";
        $user_result = mysqli_query($con, $user_query);
        
        if (!$user_result) {
            echo json_encode(['success' => false, 'error' => 'Database query failed: ' . mysqli_error($con)]);
            exit;
        }
        
        if (mysqli_num_rows($user_result) == 0) {
            echo json_encode(['success' => false, 'error' => 'User not found with ID: ' . $user_id]);
            exit;
        }
        
        $user = mysqli_fetch_assoc($user_result);
        
        if (!password_verify($current_password, $user['password_hash'])) {
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
            exit;
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET password_hash = '$hashed_password' WHERE user_id = '$user_id_escaped'";
        
        if (mysqli_query($con, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to change password']);
        }
        break;
        
    case 'upload_photo':
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
            exit;
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['photo']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP allowed']);
            exit;
        }
        
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($_FILES['photo']['size'] > $max_size) {
            echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 5MB']);
            exit;
        }
        
        $upload_dir = '../../../public/uploads/profiles/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        $db_path = 'public/uploads/profiles/' . $filename;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $filepath)) {
            $update_query = "UPDATE users SET profile_image_url = '$db_path' WHERE user_id = '$user_id'";
            if (mysqli_query($con, $update_query)) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Profile photo updated successfully',
                    'photo_url' => $base_url . $db_path
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update profile']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
