<?php
require_once '../../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!$is_logged_in || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

switch ($action) {
    case 'update_profile':
        updateProfile($con, $user_id);
        break;
    case 'change_password':
        changePassword($con, $user_id);
        break;
    case 'upload_avatar':
        uploadAvatar($con, $user_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function updateProfile($con, $user_id) {
    $first_name = mysqli_real_escape_string($con, $_POST['first_name'] ?? '');
    $last_name = mysqli_real_escape_string($con, $_POST['last_name'] ?? '');
    $email = mysqli_real_escape_string($con, $_POST['email'] ?? '');
    $phone = mysqli_real_escape_string($con, $_POST['phone'] ?? '');
    $bio = mysqli_real_escape_string($con, $_POST['bio'] ?? '');
    
    if (!$first_name || !$email) {
        echo json_encode(['success' => false, 'message' => 'First name and email are required']);
        return;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    // Check email uniqueness
    $user_id_escaped = mysqli_real_escape_string($con, $user_id);
    $check = mysqli_query($con, "SELECT user_id FROM users WHERE email = '$email' AND user_id != '$user_id_escaped'");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already in use by another account']);
        return;
    }
    
    $query = "UPDATE users SET 
              first_name = '$first_name',
              last_name = '$last_name',
              email = '$email',
              phone = '$phone',
              bio = '$bio',
              updated_at = NOW()
              WHERE user_id = '$user_id_escaped'";
    
    if (mysqli_query($con, $query)) {
        // Update session if email changed
        $_SESSION['email'] = $email;
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
}

function changePassword($con, $user_id) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (!$current_password || !$new_password || !$confirm_password) {
        echo json_encode(['success' => false, 'message' => 'All password fields are required']);
        return;
    }
    
    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
        return;
    }
    
    if (strlen($new_password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        return;
    }
    
    // Get current password hash
    $user_id_escaped = mysqli_real_escape_string($con, $user_id);
    $query = "SELECT password_hash FROM users WHERE user_id = '$user_id_escaped'";
    $result = mysqli_query($con, $query);
    
    if (!$row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    // Verify current password
    if (!password_verify($current_password, $row['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        return;
    }
    
    // Hash new password
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    $update_query = "UPDATE users SET 
                     password_hash = '$new_hash',
                     updated_at = NOW()
                     WHERE user_id = '$user_id_escaped'";
    
    if (mysqli_query($con, $update_query)) {
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to change password']);
    }
}

function uploadAvatar($con, $user_id) {
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        return;
    }
    
    $file = $_FILES['avatar'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    // Validate size
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File size must be less than 2MB']);
        return;
    }
    
    // Validate type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG or GIF images are allowed']);
        return;
    }
    
    // Create upload directory if not exists
    $upload_dir = '../../../uploads/avatars/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $extension = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        default => 'jpg'
    };
    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update database
        $avatar_url = 'uploads/avatars/' . $filename;
        $user_id_escaped = mysqli_real_escape_string($con, $user_id);
        
        // Get old avatar to delete
        $old_query = mysqli_query($con, "SELECT profile_image_url FROM users WHERE user_id = '$user_id_escaped'");
        $old_row = mysqli_fetch_assoc($old_query);
        
        $update_query = "UPDATE users SET 
                        profile_image_url = '$avatar_url',
                        updated_at = NOW()
                        WHERE user_id = '$user_id_escaped'";
        
        if (mysqli_query($con, $update_query)) {
            // Delete old avatar if exists
            if (!empty($old_row['profile_image_url'])) {
                $old_path = '../../../' . $old_row['profile_image_url'];
                if (file_exists($old_path) && strpos($old_row['profile_image_url'], 'avatar_') !== false) {
                    unlink($old_path);
                }
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Avatar uploaded successfully',
                'avatar_url' => $avatar_url
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    }
}
?>
