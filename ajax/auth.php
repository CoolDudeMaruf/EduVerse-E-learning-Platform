<?php
session_start();
header('Content-Type: application/json');
include('../includes/config.php');
include('../includes/functions.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';


$myEmail = "mohatamimhaque7@gmail.com";
$appPassword = "aflfxlheqkbnbuee";
$yourname = 'EduVerse';

function generateUserID($con) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    while(true){
        $userID = 'u';
        for ($i = 0; $i < 10; $i++) {
            $userID .= $characters[random_int(0, strlen($characters) - 1)];
        }
        $query = mysqli_query($con, "SELECT * FROM users WHERE user_id = '" . mysqli_real_escape_string($con, $userID) . "'");
        if(mysqli_num_rows($query) == 0) {
            return $userID;
        }
    }
    return false;
}

// Function to validate password strength
function validatePasswordStrength($password) {
    $requirements = [
        'length' => strlen($password) >= 8,
        'uppercase' => preg_match('/[A-Z]/', $password),
        'lowercase' => preg_match('/[a-z]/', $password),
        'number' => preg_match('/[0-9]/', $password),
        'special' => preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)
    ];
    
    // All requirements must be met
    return $requirements['length'] && $requirements['uppercase'] && $requirements['lowercase'] && $requirements['number'] && $requirements['special'];
}


$action = $_GET['action'] ?? $_POST['action'] ?? null;
$response = ['success' => false, 'message' => 'Invalid request'];

if($action === 'send_code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $username = htmlspecialchars($input['username'] ?? '', ENT_QUOTES, 'UTF-8');
    $role = htmlspecialchars($input['role'] ?? '', ENT_QUOTES, 'UTF-8');
    $password = htmlspecialchars($input['password'] ?? '', ENT_QUOTES, 'UTF-8');
    
    if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email address';
        echo json_encode($response);
        exit;
    }
    
   $username = trim($username);

    if (strlen($username) < 3) {
        $response['message'] = 'Username must be at least 3 characters long';
        echo json_encode($response);
        exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_]{5,}$/', $username)) {
        $response['message'] = 'Username must be at least 3 characters and contain only letters, numbers, or underscores';
        echo json_encode($response);
        exit;
    }

    if(empty($role) || !in_array($role, ['student', 'instructor'])) {
        $response['message'] = 'Invalid role selected';
        echo json_encode($response);
        exit;
    }
    
    if(empty($password)) {
        $response['message'] = 'Password is required';
        echo json_encode($response);
        exit;
    }
    
    if(!validatePasswordStrength($password)) {
        $response['message'] = 'Password must be 8+ characters with uppercase, lowercase, number, and special character';
        $response['pass'] = $password;
        echo json_encode($response);
        exit;
    }

    $username = mysqli_real_escape_string($con, trim($username));
    $query = mysqli_query($con, "SELECT user_id FROM users WHERE username = '$username'");
    if (mysqli_num_rows($query) > 0) {
        $response['message'] = 'Username already exists!';
        echo json_encode($response);
        exit;
    }



    $email_escaped = mysqli_real_escape_string($con, $email);
    $query = mysqli_query($con, "SELECT * FROM users WHERE email = '$email_escaped'");
    $_SESSION['username'] = $username;

    $verificationCode = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $query = mysqli_query($con, "SELECT * FROM users WHERE email = '" . mysqli_real_escape_string($con, $email) . "'");
    if(mysqli_num_rows($query) > 0) {
        $user = mysqli_fetch_assoc($query);
        if($user['email_verified'] == 1) {
            $response['message'] = 'Email already registered';
            echo json_encode($response);
        }else{
            $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['verification_expires'] = time() + 600; 
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            if(send_mail($email,$code,1)) { //1 for mail verification code,call to function.php
                $response['success'] = true;
                $response['message'] = 'Verification code sent successfully';
            } else {
                $response['message'] = 'Failed to send verification code. Please try again.';
            }
            echo json_encode($response);
        }
        exit;
    }

    $userID = generateUserID($con);
    $_SESSION['user_id'] = $userID;
    $_SESSION['role'] = $role;
    $_SESSION['verification_expires'] = time() + 600;


    $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);

    $query = "INSERT INTO users SET user_id='$userID', email='$email', role='$role', password_hash='".hash_password($password)."'";
    if(mysqli_query($con, $query)){
        $response['status'] = "ok";
        if(send_mail($email,$code,1)) { //1 for mail verification code,call to function.php
            $response['success'] = true;
            $response['message'] = 'Verification code sent successfully';
        } else {
            $response['message'] = 'Failed to send verification code. Please try again.';
        }
        echo json_encode($response);
        exit;
    }

}

// Handle verify code
else if($action === 'verify_code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $code = htmlspecialchars($input['code'] ?? '', ENT_QUOTES, 'UTF-8');
    
    // Validate code
    if(empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
        $response['message'] = 'Invalid verification code format';
        echo json_encode($response);
        exit;
    }
    
    if( time() < $_SESSION['verification_expires']) {
        $user_id = $_SESSION['user_id'];
        $username = $_SESSION['username'];

        $sql = "SELECT email_verification_token FROM users WHERE user_id='$user_id'";
        $result = mysqli_query($con, $sql);
        $row = mysqli_fetch_assoc($result);

        $stored_hash = $row['email_verification_token'];
        if(verify_password($code,$stored_hash)){
            mysqli_query($con, "UPDATE users SET username = '$username',email_verified='1',status='inactive' WHERE user_id='$user_id'");
            $_SESSION['auth'] = $_SESSION['role'];
            $_SESSION['status'] = 'inactive';
            $response['success'] = true;
            $response['message'] = 'Email verified successfully';
            $response['redirect'] = 'signup/complete'; // Redirect to profile completion page
        }
        
    } else {
        $response['message'] = 'Invalid or expired verification code';
    }
}

// Handle resend code
else if($action === 'resend_code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['verification_expires'] = time() + 600;

    if(send_mail($email,$code,1)) {
        $response['success'] = true;
        $response['message'] = 'Verification code resent successfully';
    } else {
        $response['message'] = 'Failed to resend code. Please try again.';
    }
}

ob_end_clean();
header('Content-Type: application/json');
echo json_encode($response);
exit;



function send_mail($email, $code, $type) {
    global $myEmail, $appPassword, $yourname, $con;
    $user_id = $_SESSION['user_id'];
    // Type 1 = Email Verification
    if ($type !== 1) {
        return false;
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $myEmail;
        $mail->Password = $appPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 10;
        $mail->SMTPKeepAlive = true;
        
        // Set From and To
        $mail->setFrom($myEmail, $yourname);
        $mail->addAddress($email);
        
        // Email Content
        $mail->isHTML(true);
        $mail->Subject = $code . ' is your ' . $yourname . ' email verification code';
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #3b82f6; margin-bottom: 20px;">Email Verification</h2>
                <p>Welcome to ' . $yourname . '!</p>
                <p>Your verification code is:</p>
                <div style="background-color: #f1f4f8; border: 1px solid #c8d1dc; padding: 15px; display: inline-block; margin: 10px 0; font-size: 28px; font-weight: bold; border-radius: 5px; text-align: center; letter-spacing: 3px;">
                    ' . $code . '
                </div>
                <p>This code will expire in 10 minutes.</p>
                <p style="color: #666; font-size: 12px;">If you did not request this code, please ignore this email.</p>
                <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
                <p style="color: #999; font-size: 12px;">Best regards,<br><strong>' . $yourname . ' Team</strong></p>
            </div>
        </body>
        </html>';
        
        $mail->AltBody = 'Your ' . $yourname . ' verification code is: ' . $code . '. This code will expire in 10 minutes.';
        
        // Send email
        if ($mail->send()) {
            $query = "UPDATE users SET email_verification_token='".hash_password($code)."' WHERE user_id='$user_id'";
            if (mysqli_query($con, $query)) {
                return true;
            }
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log('PHPMailer Error: ' . $e->getMessage());
        return false;
    }
}



?>