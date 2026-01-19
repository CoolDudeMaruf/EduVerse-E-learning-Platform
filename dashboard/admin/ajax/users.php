<?php
require_once '../../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!$is_logged_in || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$is_super_admin = strtolower($_SESSION['role']) === 'super_admin';

switch ($action) {
    case 'get_users':
        getUsers($con);
        break;
    case 'get_user':
        getUser($con);
        break;
    case 'update_user':
        updateUser($con, $is_super_admin);
        break;
    case 'update_status':
        updateUserStatus($con);
        break;
    case 'delete_user':
        deleteUser($con, $is_super_admin);
        break;
    case 'get_instructors':
        getInstructors($con);
        break;
    case 'verify_instructor':
        verifyInstructor($con);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getUsers($con) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $role = mysqli_real_escape_string($con, $_GET['role'] ?? '');
    $status = mysqli_real_escape_string($con, $_GET['status'] ?? '');
    $search = mysqli_real_escape_string($con, $_GET['search'] ?? '');
    
    $where = "1=1";
    if ($role) $where .= " AND role = '$role'";
    if ($status) $where .= " AND status = '$status'";
    if ($search) {
        $where .= " AND (first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR email LIKE '%$search%' OR username LIKE '%$search%')";
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM users WHERE $where";
    $count_result = mysqli_query($con, $count_query);
    $total = mysqli_fetch_assoc($count_result)['total'];
    $total_pages = ceil($total / $limit);
    
    // Get users
    $query = "SELECT user_id, email, username, first_name, last_name, profile_image_url, role, status, created_at 
              FROM users 
              WHERE $where 
              ORDER BY created_at DESC 
              LIMIT $offset, $limit";
    $result = mysqli_query($con, $query);
    
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => $total,
        'total_pages' => $total_pages,
        'current_page' => $page
    ]);
}

function getUser($con) {
    $user_id = mysqli_real_escape_string($con, $_GET['user_id'] ?? '');
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    $query = "SELECT user_id, email, username, first_name, last_name, profile_image_url, role, status, phone, bio, created_at 
              FROM users WHERE user_id = '$user_id'";
    $result = mysqli_query($con, $query);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'user' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
}

function updateUser($con, $is_super_admin) {
    $user_id = mysqli_real_escape_string($con, $_POST['user_id'] ?? '');
    $first_name = mysqli_real_escape_string($con, $_POST['first_name'] ?? '');
    $last_name = mysqli_real_escape_string($con, $_POST['last_name'] ?? '');
    $email = mysqli_real_escape_string($con, $_POST['email'] ?? '');
    $role = mysqli_real_escape_string($con, $_POST['role'] ?? '');
    $status = mysqli_real_escape_string($con, $_POST['status'] ?? '');
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    // Can't make someone super_admin unless you're super_admin
    if ($role === 'super_admin' && !$is_super_admin) {
        echo json_encode(['success' => false, 'message' => 'Cannot assign super_admin role']);
        return;
    }
    
    // Validate email uniqueness
    $check = mysqli_query($con, "SELECT user_id FROM users WHERE email = '$email' AND user_id != '$user_id'");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already in use']);
        return;
    }
    
    $query = "UPDATE users SET 
              first_name = '$first_name',
              last_name = '$last_name',
              email = '$email',
              role = '$role',
              status = '$status',
              updated_at = NOW()
              WHERE user_id = '$user_id'";
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user']);
    }
}

function updateUserStatus($con) {
    $user_id = mysqli_real_escape_string($con, $_POST['user_id'] ?? '');
    $status = mysqli_real_escape_string($con, $_POST['status'] ?? '');
    
    if (!$user_id || !$status) {
        echo json_encode(['success' => false, 'message' => 'User ID and status required']);
        return;
    }
    
    // Prevent self-suspension
    if ($user_id === $_SESSION['user_id'] && $status === 'suspended') {
        echo json_encode(['success' => false, 'message' => 'Cannot suspend yourself']);
        return;
    }
    
    $query = "UPDATE users SET status = '$status', updated_at = NOW() WHERE user_id = '$user_id'";
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => 'User status updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
}

function deleteUser($con, $is_super_admin) {
    $user_id = mysqli_real_escape_string($con, $_POST['user_id'] ?? '');
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    // Prevent self-deletion
    if ($user_id === $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete yourself']);
        return;
    }
    
    // Check if target is super_admin
    $check = mysqli_query($con, "SELECT role FROM users WHERE user_id = '$user_id'");
    $target = mysqli_fetch_assoc($check);
    if ($target && $target['role'] === 'super_admin' && !$is_super_admin) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete super admin']);
        return;
    }
    
    $query = "DELETE FROM users WHERE user_id = '$user_id'";
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    }
}

function getInstructors($con) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $status = mysqli_real_escape_string($con, $_GET['status'] ?? '');
    
    $where = "u.role = 'instructor'";
    if ($status) {
        $where .= " AND ip.verification_status = '$status'";
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM users u 
                    LEFT JOIN instructor_profiles ip ON u.user_id = ip.user_id 
                    WHERE $where";
    $count_result = mysqli_query($con, $count_query);
    $total = mysqli_fetch_assoc($count_result)['total'];
    $total_pages = ceil($total / $limit);
    
    // Get instructors
    $query = "SELECT u.user_id, u.email, u.first_name, u.last_name, u.profile_image_url,
              ip.expertise_areas, ip.verification_status,
              (SELECT COUNT(*) FROM courses c WHERE c.instructor_id = u.user_id) as course_count,
              (SELECT COUNT(*) FROM enrollments e JOIN courses c ON e.course_id = c.course_id WHERE c.instructor_id = u.user_id) as student_count,
              (SELECT COALESCE(SUM(t.instructor_revenue), 0) FROM transactions t 
               JOIN courses c ON t.course_id = c.course_id 
               WHERE c.instructor_id = u.user_id AND t.status = 'completed') as total_earnings
              FROM users u 
              LEFT JOIN instructor_profiles ip ON u.user_id = ip.user_id 
              WHERE $where 
              ORDER BY u.created_at DESC 
              LIMIT $offset, $limit";
    $result = mysqli_query($con, $query);
    
    $instructors = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['verification_status'] = $row['verification_status'] ?? 'pending';
        $instructors[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'instructors' => $instructors,
        'total' => $total,
        'total_pages' => $total_pages,
        'current_page' => $page
    ]);
}

function verifyInstructor($con) {
    $user_id = mysqli_real_escape_string($con, $_POST['user_id'] ?? '');
    $admin_id = $_SESSION['user_id'];
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    // Check if instructor profile exists
    $check = mysqli_query($con, "SELECT instructor_profile_id FROM instructor_profiles WHERE user_id = '$user_id'");
    
    if (mysqli_num_rows($check) > 0) {
        // Update existing profile
        $query = "UPDATE instructor_profiles SET 
                  verification_status = 'verified',
                  verified_by = '$admin_id',
                  verified_at = NOW()
                  WHERE user_id = '$user_id'";
    } else {
        // Create new profile
        $query = "INSERT INTO instructor_profiles (user_id, verification_status, verified_by, verified_at, created_at)
                  VALUES ('$user_id', 'verified', '$admin_id', NOW(), NOW())";
    }
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => 'Instructor verified']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to verify instructor']);
    }
}
?>
