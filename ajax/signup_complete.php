<?php
session_start();
ob_start();

// Check if user is authenticated
if (!isset($_SESSION['auth'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please login first.'
    ]);
    exit(0);
}

// Include config
include('../includes/config.php');

// Get action
$action = $_POST['action'] ?? 'complete_profile';

if ($action === 'complete_profile') {
    completeProfile($con);
}

function completeProfile($con) {
    ob_clean();
    header('Content-Type: application/json');

    try {
        $user_id = $_SESSION['user_id'];

        $first_name = htmlspecialchars($_POST['first_name'] ?? '', ENT_QUOTES);
        $last_name = htmlspecialchars($_POST['last_name'] ?? '', ENT_QUOTES);
        $phone = htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES);
        $country_code = htmlspecialchars($_POST['countryCode'] ?? '+880', ENT_QUOTES);
        $country = htmlspecialchars($_POST['country'] ?? '', ENT_QUOTES);
        
        // Escape for database
        $first_name = mysqli_real_escape_string($con, $first_name);
        $last_name = mysqli_real_escape_string($con, $last_name);
        $phone = mysqli_real_escape_string($con, $phone);
        $country_code = mysqli_real_escape_string($con, $country_code);
        $country = mysqli_real_escape_string($con, $country);
        
        $date_of_birth = $_POST['date_of_birth'] ?? null;
        $gender = htmlspecialchars($_POST['gender'] ?? '', ENT_QUOTES);
        $blood_group = htmlspecialchars($_POST['blood_group'] ?? '', ENT_QUOTES);
        $bio = htmlspecialchars($_POST['bio'] ?? '', ENT_QUOTES);
        
        // Escape for database
        $gender = mysqli_real_escape_string($con, $gender);
        $blood_group = mysqli_real_escape_string($con, $blood_group);
        $bio = mysqli_real_escape_string($con, $bio);
        
        $headline = htmlspecialchars($_POST['headline'] ?? '', ENT_QUOTES);
        $occupation = htmlspecialchars($_POST['occupation'] ?? '', ENT_QUOTES);
        $company = htmlspecialchars($_POST['company'] ?? '', ENT_QUOTES);
        $location = htmlspecialchars($_POST['location'] ?? '', ENT_QUOTES);
        
        // Escape for database
        $headline = mysqli_real_escape_string($con, $headline);
        $occupation = mysqli_real_escape_string($con, $occupation);
        $company = mysqli_real_escape_string($con, $company);
        $location = mysqli_real_escape_string($con, $location);
        
        $linkedin_url = htmlspecialchars($_POST['linkedin_url'] ?? '', ENT_QUOTES);
        $twitter_handle = htmlspecialchars($_POST['twitter_handle'] ?? '', ENT_QUOTES);
        $github_url = htmlspecialchars($_POST['github_url'] ?? '', ENT_QUOTES);
        
        // Escape for database
        $linkedin_url = mysqli_real_escape_string($con, $linkedin_url);
        $twitter_handle = mysqli_real_escape_string($con, $twitter_handle);
        $github_url = mysqli_real_escape_string($con, $github_url);

        if (empty($phone) || empty($country)) {
            throw new Exception('Phone number and country are required');
        }

        if (empty($first_name) || empty($last_name)) {
            throw new Exception('First name and last name are required');
        }

        if (empty($date_of_birth) || empty($gender) || empty($blood_group)) {
            throw new Exception('Date of birth, gender, and blood group are required');
        }

        if (!preg_match('/^\d{7,15}$/', $phone)) {
            throw new Exception('Invalid phone number format');
        }

        if (!strtotime($date_of_birth)) {
            throw new Exception('Invalid date of birth');
        }

        $birthDate = new DateTime($date_of_birth);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        
        if ($age < 13) {
            throw new Exception('You must be at least 13 years old');
        }

        $profile_photo_url = null;
        if (!empty($_POST['profile_photo'])) {
            $profile_photo_url = saveProfilePhoto($user_id, $_POST['profile_photo']);
            if (!$profile_photo_url) {
                throw new Exception('Failed to save profile photo');
            }
        }

        $updateQuery = "UPDATE users SET first_name='$first_name', last_name='$last_name', phone='$phone', country_code='$country_code', country='$country', date_of_birth='$date_of_birth', gender='$gender', blood_group='$blood_group', bio='$bio', headline='$headline', occupation='$occupation', company='$company', location='$location'" . (!empty($profile_photo_url) ? ", profile_image_url='".mysqli_real_escape_string($con, $profile_photo_url)."'" : "") . ", status='active', updated_at=NOW() WHERE user_id='$user_id'";
        if (mysqli_query($con, $updateQuery)) {
            $_SESSION['status'] = 'active';
            
        $preferencesQuery = "INSERT INTO user_preferences (user_id) VALUES ('$user_id')";
        if (!mysqli_query($con, $preferencesQuery)) {
            error_log('Failed to create user preferences: ' . mysqli_error($con));
        }
        
        // Insert user social links
        $socialLinks = [];
        
        if (!empty($linkedin_url)) {
            $socialLinks[] = "('$user_id', 'linkedin', '$linkedin_url', 0, TRUE)";
        }
        
        if (!empty($twitter_handle)) {
            // Handle Twitter handle format
            $twitterUrl = (strpos($twitter_handle, 'http') === 0) ? $twitter_handle : 'https://twitter.com/' . ltrim($twitter_handle, '@');
            $twitterUrl = mysqli_real_escape_string($con, $twitterUrl);
            $socialLinks[] = "('$user_id', 'twitter', '$twitterUrl', 1, TRUE)";
        }
        
        if (!empty($github_url)) {
            $socialLinks[] = "('$user_id', 'github', '$github_url', 2, TRUE)";
        }
        
        if (!empty($socialLinks)) {
            $socialLinksQuery = "INSERT INTO user_social_links (user_id, platform, url, display_order, is_public) VALUES " . implode(',', $socialLinks);
            if (!mysqli_query($con, $socialLinksQuery)) {
                error_log('Failed to create user social links: ' . mysqli_error($con));
            }
        }

        if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'instructor') {
            $instructorProfileQuery = "INSERT INTO instructor_profiles (user_id) VALUES ('$user_id')";
            if (!mysqli_query($con, $instructorProfileQuery)) {
                error_log('Failed to create instructor profile: ' . mysqli_error($con));
            }
        }

            echo json_encode([
                'success' => true,
                'message' => 'Profile completed successfully! Redirecting...'
            ]);
            exit;
        } else {
            throw new Exception('Failed to update profile: ' . mysqli_error($con));
        }



    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Save profile photo to server
 */
function saveProfilePhoto($user_id, $photoData) {
    try {
        // Extract base64 data
        if (strpos($photoData, 'data:image') === 0) {
            // Extract MIME type and base64 data
            $parts = explode(';', $photoData);
            $mimeType = str_replace('data:', '', $parts[0]);
            $base64Data = explode(',', $parts[1])[1];
        } else {
            $base64Data = $photoData;
            $mimeType = 'image/jpeg';
        }

        // Validate MIME type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes)) {
            throw new Exception('Invalid image format. Only JPG, PNG, and WebP are allowed.');
        }

        // Decode base64
        $imageData = base64_decode($base64Data, true);
        if ($imageData === false) {
            throw new Exception('Invalid image data');
        }

        $uploadDir = __DIR__ . '/../public/uploads/profiles/' . $user_id;
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Failed to create upload directory');
            }
        }

        // Generate filename
        $extension = match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg'
        };

        $filename = 'profile.' . $extension;
        $filepath = $uploadDir . '/' . $filename;

        // Save image
        if (!file_put_contents($filepath, $imageData)) {
            throw new Exception('Failed to save image');
        }

        // Create thumbnail (200x200)
        createThumbnail($filepath, $uploadDir . '/profile-thumb.jpg', 200, 200);

        // Return relative path
        return 'public/uploads/profiles/' . $user_id . '/' . $filename;

    } catch (Exception $e) {
        error_log('Photo upload error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Create thumbnail image
 */
function createThumbnail($sourcePath, $destPath, $width, $height) {
    try {
        // Check if GD library is available
        if (!extension_loaded('gd')) {
            return false;
        }

        // Get image info
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];

        // Load source image
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }

        if (!$sourceImage) {
            return false;
        }

        // Create thumbnail
        $thumbnail = imagecreatetruecolor($width, $height);

        // Calculate dimensions for center crop
        $aspectRatio = $sourceWidth / $sourceHeight;
        if ($aspectRatio > 1) {
            // Width is larger
            $cropHeight = $sourceHeight;
            $cropWidth = $sourceHeight;
            $cropX = ($sourceWidth - $cropWidth) / 2;
            $cropY = 0;
        } else {
            // Height is larger or equal
            $cropWidth = $sourceWidth;
            $cropHeight = $sourceWidth;
            $cropX = 0;
            $cropY = ($sourceHeight - $cropHeight) / 2;
        }

        imagecopyresampled(
            $thumbnail,
            $sourceImage,
            0, 0,
            $cropX, $cropY,
            $width, $height,
            $cropWidth, $cropHeight
        );

        // Save thumbnail
        imagejpeg($thumbnail, $destPath, 90);

        imagedestroy($sourceImage);
        imagedestroy($thumbnail);

        return true;

    } catch (Exception $e) {
        error_log('Thumbnail creation error: ' . $e->getMessage());
        return false;
    }
}

ob_end_flush();
?>