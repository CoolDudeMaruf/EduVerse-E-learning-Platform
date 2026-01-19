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

$response = ['success' => false, 'message' => 'Invalid request'];

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    // ==================== ACTION 1: SEND VERIFICATION CODE ====================
    if($action === 'send_code') {
        $emailOrUsername = htmlspecialchars($input['emailOrUsername'] ?? '', ENT_QUOTES, 'UTF-8');

        if(empty($emailOrUsername)) {
            $response['message'] = 'Email or username is required';
            echo json_encode($response);
            exit;
        }

        // Check if user exists
        $emailOrUsername_escaped = mysqli_real_escape_string($con, $emailOrUsername);
        $query = mysqli_query($con, "SELECT user_id, email, username FROM users WHERE email = '$emailOrUsername_escaped' OR username = '$emailOrUsername_escaped'");

        if(mysqli_num_rows($query) === 0) {
            $response['message'] = 'User not found';
            echo json_encode($response);
            exit;
        }

        $user = mysqli_fetch_assoc($query);
        $user_id = $user['user_id'];
        $email = $user['email'];
        $username = $user['username'];
        $_SESSION['user_id'] = $user_id;

        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        $_SESSION['forgot_password_code_' . $user_id] = [
            'code' => $code,
            'expires' => time() + 900
        ];
       
        if(send_mail($email,$code)) {
            $response['success'] = true;
            $response['message'] = 'Verification code sent';
            $response['user_id'] = $user_id;
            $response['email'] = $email;
            $response['username'] = $username;
        } else {
            $response['message'] = 'Failed to send verification code';
        }
    }

    // ==================== ACTION 2: VERIFY CODE ====================
    else if($action === 'verify_code') {
        $code = htmlspecialchars($input['code'] ?? '', ENT_QUOTES, 'UTF-8');

        if(empty($code)) {
            $response['message'] = 'Invalid request';
            echo json_encode($response);
            exit;
        }

        $user_id = $_SESSION['user_id'];

        $sessionKey = 'forgot_password_code_' . $user_id;


        if(!isset($_SESSION[$sessionKey])) {
            $response['message'] = 'No code found. Please request a new code.';
            echo json_encode($response);
            exit;
        }

        $storedCode = $_SESSION[$sessionKey];
        if(time() > $storedCode['expires']) {
            unset($_SESSION[$sessionKey]);
            $response['message'] = 'Code expired. Please request a new code.';
            echo json_encode($response);
            exit;
        }

        if($storedCode['code'] !== $code) {
            $response['message'] = "Invalid Code!";
            echo json_encode($response);
            exit;
        }

        $sql = "SELECT password_reset_token FROM users WHERE user_id='$user_id'";
        $result = mysqli_query($con, $sql);
        $row = mysqli_fetch_assoc($result);

        $stored_hash = $row['password_reset_token'];
        if(verify_password($code,$stored_hash)){
            $_SESSION['forgot_password_verified_' . $user_id] = true;
            $response['success'] = true;
            $response['message'] = 'Code verified';
        }else{
            $response['message'] = "Invalid Code!";
            echo json_encode($response);
            exit;
        }


    }

    // ==================== ACTION 3: RESET PASSWORD ====================
    else if($action === 'reset_password') {
        $user_id = $_SESSION['user_id'];

        $password = $input['password'] ?? '';

        if(empty($user_id) || empty($password)) {
            $response['message'] = 'Invalid request';
            echo json_encode($response);
            exit;
        }

        $verifiedKey = 'forgot_password_verified_' . $user_id;
        if(!isset($_SESSION[$verifiedKey])) {
            $response['message'] = 'Please verify your code first';
            echo json_encode($response);
            exit;
        }

        // Validate password strength
        if(strlen($password) < 8) {
            $response['message'] = 'Password must be at least 8 characters';
            echo json_encode($response);
            exit;
        }

        if(!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || 
           !preg_match('/[0-9]/', $password) || !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
            $response['message'] = 'Password must contain uppercase, lowercase, number, and special character';
            echo json_encode($response);
            exit;
        }

        // Hash new password
        $password_hash = hash_password($password);
        $password_hash_escaped = mysqli_real_escape_string($con, $password_hash);

        $updateQuery = mysqli_query($con, "UPDATE users SET password_hash = '$password_hash_escaped' WHERE user_id = '$user_id'");

        if($updateQuery) {
            unset($_SESSION['forgot_password_code_' . $user_id]);
            unset($_SESSION['forgot_password_verified_' . $user_id]);
            $response['success'] = true;
            $response['message'] = 'Password reset successfully';
        } else {
            $response['message'] = 'Failed to reset password: ' . mysqli_error($con);
        }
    }

    echo json_encode($response);
}




function send_mail($email, $code) {
    global $myEmail, $appPassword, $yourname, $con;
 
    
    try {
        $mail = new PHPMailer(true);
        
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
        $mail->Subject = $code . ' is your ' . $yourname . ' password reset code';
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; color: #333; margin: 0; padding: 0;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #3b82f6; margin-bottom: 20px;">Password Reset Request</h2>
                <p>Hello,</p>
                <p>We received a request to reset your password for your ' . $yourname . ' account.</p>
                <p>Your password reset code is:</p>
                <div style="background-color: #f1f4f8; border: 1px solid #c8d1dc; padding: 15px; display: inline-block; margin: 10px 0; font-size: 28px; font-weight: bold; border-radius: 5px; text-align: center; letter-spacing: 3px;">
                    ' . $code . '
                </div>
                <p>This code will expire in 10 minutes.</p>
                <p style="color: #666; font-size: 12px;">If you did not request a password reset, please ignore this email.</p>
                <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
                <p style="color: #999; font-size: 12px;">Best regards,<br><strong>' . $yourname . ' Team</strong></p>
            </div>
        </body>
        </html>';

        $mail->AltBody = 'Your ' . $yourname . ' password reset code is: ' . $code . '. This code will expire in 10 minutes. If you did not request this, please ignore this email.';


        if ($mail->send()) {
            $query = "UPDATE users SET password_reset_token='".hash_password($code)."' WHERE email='$email'";
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
