<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'eduverse';
$base_url = 'http://localhost/eduverse/';

$temp_con = @mysqli_connect($host, $user, $pass);
if ($temp_con) {
    mysqli_query($temp_con, "CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    mysqli_close($temp_con);
}

$con = mysqli_connect($host, $user, $pass, $db);
$connect = new PDO("mysql:host=$host;dbname=$db", $user, $pass);

if (!$con) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]));
}

require_once __DIR__ . '/database.php';



$site_name = 'EduVerse';
$site_title = 'EduVerse - Online Learning Platform';

$max_upload_size = 10737418240;
$upload_dir = 'public/uploads/';
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'zip'];

$session_timeout = 3600;

$debug_mode = true;
if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $session_timeout,
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$current_user_id = $_SESSION['user_id'] ?? null;

$current_user = null;
if ($is_logged_in) {
    $current_user = [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'status' => $_SESSION['status'] ?? null,
        'auth' => $_SESSION['auth'] ?? null
    ];
}

if ($is_logged_in) {
    if($_SESSION['role'] == "inactive"){
        header("Location: " . $base_url . "signup/complete");
    }
    $update_query = "UPDATE users SET last_active_at = NOW(), last_ip_address = '" . mysqli_real_escape_string($con, $_SERVER['REMOTE_ADDR']) . "' WHERE user_id = '" . mysqli_real_escape_string($con, $current_user_id) . "'";
    mysqli_query($con, $update_query);
}

if ($is_logged_in && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > $session_timeout) {
        session_destroy();
        $is_logged_in = false;
        $current_user = null;
        header("Location: " . $base_url . "login?expired=true");
        exit();
    }
}
$_SESSION['last_activity'] = time();

function redirect($slug){
    global $base_url;
    header("Location: " . $base_url . $slug);
    exit();
}

function get_user_by_id($con, $user_id) {
    $user_id = mysqli_real_escape_string($con, $user_id);
    $query = "SELECT * FROM users WHERE user_id = '$user_id'";
    $result = mysqli_query($con, $query);
    return mysqli_num_rows($result) > 0 ? mysqli_fetch_assoc($result) : null;
}

function get_all_categories($con) {
    $query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order ASC";
    $result = mysqli_query($con, $query);    if (!$result) {
        return [];
    }    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    return $categories;
}

function get_featured_courses($con, $limit = 10) {
    $limit = (int)$limit;
    $query = "SELECT c.*, 
              u.first_name, u.last_name, u.profile_image_url as instructor_image,
              cat.name as category,
              (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.course_id = c.course_id AND r.is_published = 1) as average_rating,
              (SELECT COUNT(*) FROM reviews r WHERE r.course_id = c.course_id AND r.is_published = 1) as total_reviews
              FROM courses c
              LEFT JOIN users u ON c.instructor_id = u.user_id
              LEFT JOIN categories cat ON c.category_id = cat.category_id
              WHERE c.status = 'published' AND c.is_featured = 1
              ORDER BY average_rating DESC, c.enrollment_count DESC
              LIMIT $limit";
    $result = mysqli_query($con, $query);
    if (!$result) {
        return [];
    }
    $courses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $courses[] = $row;
    }
    return $courses;
}

function get_bestseller_courses($con, $limit = 10) {
    $limit = (int)$limit;
    $query = "SELECT c.*,
              u.first_name, u.last_name, u.profile_image_url as instructor_image,
              cat.name as category,
              (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) as average_rating,
              (SELECT COUNT(*) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) as total_reviews
              FROM courses c
              LEFT JOIN users u ON c.instructor_id = u.user_id
              LEFT JOIN categories cat ON c.category_id = cat.category_id
              WHERE c.status = 'published' AND c.is_bestseller = 1
              ORDER BY c.enrollment_count DESC, average_rating DESC
              LIMIT $limit";
    $result = mysqli_query($con, $query);
    if (!$result) {
        return [];
    }
    $courses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $courses[] = $row;
    }
    return $courses;
}

function count_user_enrollments($con, $student_id) {
    $student_id = mysqli_real_escape_string($con, $student_id);
    $query = "SELECT COUNT(*) as total FROM enrollments WHERE student_id = '$student_id'";
    $result = mysqli_query($con, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

function get_user_learning_hours($con, $student_id) {
    $student_id = mysqli_real_escape_string($con, $student_id);
    $query = "SELECT SUM(total_learning_time_seconds) / 3600 as total_hours 
              FROM enrollments WHERE student_id = '$student_id'";
    $result = mysqli_query($con, $query);
    $row = mysqli_fetch_assoc($result);
    return round($row['total_hours'] ?? 0, 2);
}

?>

