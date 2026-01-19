<?php
require_once '../../../includes/config.php';

header('Content-Type: application/json');

// Check if user is admin for settings
if (!$is_logged_in || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_settings':
        getSettings($con);
        break;
    case 'update_settings':
        updateSettings($con);
        break;
    case 'get_dashboard_stats':
        getDashboardStats($con);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getSettings($con) {
    $query = "SELECT * FROM system_settings ORDER BY setting_key";
    $result = mysqli_query($con, $query);
    
    $settings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = [
            'value' => $row['setting_value'],
            'type' => $row['value_type'],
            'description' => $row['description']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);
}

function updateSettings($con) {
    $settings = json_decode($_POST['settings'] ?? '{}', true);
    $admin_id = mysqli_real_escape_string($con, $_SESSION['user_id']);
    
    if (empty($settings)) {
        echo json_encode(['success' => false, 'message' => 'No settings provided']);
        return;
    }
    
    mysqli_begin_transaction($con);
    
    try {
        foreach ($settings as $key => $value) {
            $key = mysqli_real_escape_string($con, $key);
            $value = mysqli_real_escape_string($con, $value);
            
            // Update or insert setting
            $query = "INSERT INTO system_settings (setting_key, setting_value, updated_by, updated_at)
                      VALUES ('$key', '$value', '$admin_id', NOW())
                      ON DUPLICATE KEY UPDATE setting_value = '$value', updated_by = '$admin_id', updated_at = NOW()";
            
            if (!mysqli_query($con, $query)) {
                throw new Exception('Failed to update setting: ' . $key);
            }
        }
        
        mysqli_commit($con);
        echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
    } catch (Exception $e) {
        mysqli_rollback($con);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getDashboardStats($con) {
    // Users stats
    $users_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
                    SUM(CASE WHEN role = 'instructor' THEN 1 ELSE 0 END) as instructors,
                    SUM(CASE WHEN role IN ('admin', 'super_admin') THEN 1 ELSE 0 END) as admins,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_week,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_month
                    FROM users";
    $users = mysqli_fetch_assoc(mysqli_query($con, $users_query));
    
    // Courses stats
    $courses_query = "SELECT 
                      COUNT(*) as total,
                      SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
                      SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending,
                      SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft
                      FROM courses";
    $courses = mysqli_fetch_assoc(mysqli_query($con, $courses_query));
    
    // Enrollments stats
    $enrollments_query = "SELECT 
                          COUNT(*) as total,
                          SUM(CASE WHEN enrollment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_week,
                          SUM(CASE WHEN enrollment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_month
                          FROM enrollments";
    $enrollments = mysqli_fetch_assoc(mysqli_query($con, $enrollments_query));
    
    // Revenue stats
    $revenue_query = "SELECT 
                      SUM(amount) as total,
                      SUM(platform_fee) as platform_fees,
                      SUM(instructor_revenue) as instructor_earnings,
                      SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN amount ELSE 0 END) as revenue_week,
                      SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount ELSE 0 END) as revenue_month
                      FROM transactions WHERE status = 'completed'";
    $revenue = mysqli_fetch_assoc(mysqli_query($con, $revenue_query));
    
    // Monthly revenue for chart (last 6 months)
    $monthly_revenue_query = "SELECT 
                              DATE_FORMAT(created_at, '%Y-%m') as month,
                              SUM(amount) as revenue,
                              COUNT(*) as transactions
                              FROM transactions 
                              WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                              ORDER BY month ASC";
    $monthly_result = mysqli_query($con, $monthly_revenue_query);
    $monthly_revenue = [];
    while ($row = mysqli_fetch_assoc($monthly_result)) {
        $monthly_revenue[] = $row;
    }
    
    // Monthly enrollments for chart
    $monthly_enrollments_query = "SELECT 
                                  DATE_FORMAT(enrollment_date, '%Y-%m') as month,
                                  COUNT(*) as enrollments
                                  FROM enrollments 
                                  WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                                  GROUP BY DATE_FORMAT(enrollment_date, '%Y-%m')
                                  ORDER BY month ASC";
    $monthly_enroll_result = mysqli_query($con, $monthly_enrollments_query);
    $monthly_enrollments = [];
    while ($row = mysqli_fetch_assoc($monthly_enroll_result)) {
        $monthly_enrollments[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'courses' => $courses,
        'enrollments' => $enrollments,
        'revenue' => $revenue,
        'monthly_revenue' => $monthly_revenue,
        'monthly_enrollments' => $monthly_enrollments
    ]);
}
?>
