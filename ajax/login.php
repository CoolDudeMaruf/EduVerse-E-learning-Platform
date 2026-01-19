<?php
session_start();
header('Content-Type: application/json');
include('../includes/config.php');
include('../includes/functions.php');

$response = ['success' => false, 'message' => 'Invalid request'];

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $emailOrUsername = htmlspecialchars($input['emailOrUsername'] ?? '', ENT_QUOTES, 'UTF-8');
    $password = htmlspecialchars($input['password'] ?? '', ENT_QUOTES, 'UTF-8');
    
    // Validate input
    if(empty($emailOrUsername)) {
        $response['message'] = 'Email or username is required';
        echo json_encode($response);
        exit;
    }
    
    if(empty($password)) {
        $response['message'] = 'Password is required';
        echo json_encode($response);
        exit;
    }
    
    // Check if user exists (by email or username)
    $emailOrUsername_escaped = mysqli_real_escape_string($con, $emailOrUsername);
    $query = mysqli_query($con, "SELECT user_id, username, email, password_hash, role, email_verified, status FROM users WHERE email = '$emailOrUsername_escaped' OR username = '$emailOrUsername_escaped'");
    
    if(mysqli_num_rows($query) > 0) {
        $user = mysqli_fetch_assoc($query);
        
        if($user['email_verified'] != 1) {
            $response['message'] = 'Email not found. Please sign up first';
            echo json_encode($response);
            exit;
        }
        
    if (password_verify($password, $user['password_hash'])) {
        if (in_array($user['status'], ['active', 'inactive'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['auth'] = $user['role'];
            $_SESSION['status'] = $user['status'];

            // Migrate cart and wishlist from session to database
            include('../includes/migrate_session_to_db.php');

            $response['message'] = 'Login successful';
            $response['success'] = true;
            $response['redirect'] = $user['status'] === 'active' ? '/eduverse' : 'signup/complete';

            $stmt = $con->prepare("UPDATE users SET last_login_at = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $user['user_id']);
            $stmt->execute();
            $stmt->close();
        } else {
            $response['success'] = false;
            $response['message'] = 'Your account has been suspended! Contact support.';
        }
    } else {
        $response['success'] = false;
        $response['message'] = 'Invalid credentials!';
    }
    } else {
        $response['message'] = 'Email or Username not found. Please sign up first';
    }
}

echo json_encode($response);
exit;
?>
