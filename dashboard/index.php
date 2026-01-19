<?php
require_once '../includes/config.php';

if (!$is_logged_in) {
    redirect('login');
}

if (!isset($_SESSION['role']) || empty($_SESSION['role'])) {
    // If no role, logout and redirect to login
    session_destroy();
    redirect('login');
}

$user_role = strtolower(trim($_SESSION['role']));

switch ($user_role) {
    case 'admin':
        redirect('dashboard/admin/');
        break;
    
    case 'instructor':
        redirect('dashboard/instructor/');
        break;
    
    case 'student':
        redirect('dashboard/student/');
        break;
    
    case 'inactive':
        redirect('signup_complete');
        break;
    
    default:
        session_destroy();
        redirect('login');
        break;
}
?>
