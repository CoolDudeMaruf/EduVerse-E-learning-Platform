<?php
$page_title = 'Forgot Password';
include('includes/header.php');

// Redirect if already logged in
if(isset($_SESSION['auth'])){
    header("Location: " . $base_url . "dashboard");
    exit(0);
}
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>public/css/signup.css">
<div class="signup-wrapper">
    <div class="signup-container">
        <!-- Left Panel - Branding -->
        <div class="signup-left">
            <div class="brand-section">
                <a href="<?php echo $base_url; ?>" class="brand-logo">
                    <i class="material-icons logo-icon">school</i>
                    <span class="logo-text">EduVerse</span>
                </a>
                <h2 class="brand-title">Forgot Password?</h2>
                <p class="brand-subtitle">No worries, we'll help you reset it</p>
            </div>

            <!-- Benefits -->
            <div class="benefits-section">
                <div class="benefit-card">
                    <i class="material-icons benefit-icon">security</i>
                    <h3>Secure Reset</h3>
                    <p>Your password is securely reset</p>
                </div>
                <div class="benefit-card">
                    <i class="material-icons benefit-icon">verified_user</i>
                    <h3>Email Verified</h3>
                    <p>Verify via email code</p>
                </div>
                <div class="benefit-card">
                    <i class="material-icons benefit-icon">lock</i>
                    <h3>Strong Password</h3>
                    <p>Create a new secure password</p>
                </div>
                <div class="benefit-card">
                    <i class="material-icons benefit-icon">check_circle</i>
                    <h3>Quick Process</h3>
                    <p>Regain access in minutes</p>
                </div>
            </div>

            <div class="footer-text">
                <p>Join <strong>500,000+</strong> learners worldwide</p>
            </div>
        </div>

        <div class="signup-right">
            <div class="form-wrapper">
                <!-- Step 1: Enter Email/Username -->
                <div class="modal-content active" id="step1">
                    <div class="modal-header">
                        <h1>Reset Your Password</h1>
                        <p>Enter your email or username to get started</p>
                    </div>

                    <form id="forgotPasswordForm" class="signup-form">
                        <div class="form-field">
                            <label for="emailOrUsername">Email or Username *</label>
                            <input type="text" id="emailOrUsername" name="emailOrUsername" placeholder="mohatamimhaque7@gmail.com or username" required>
                        </div>

                        <button type="submit" class="btn-primary btn-full">
                            Send Verification Code
                            <i class="material-icons btn-icon">arrow_forward</i>
                        </button>
                    </form>

                    <div class="modal-footer">
                        <p>Remember your password? <a href="<?php echo $base_url; ?>signin" class="link-accent">Sign In</a></p>
                    </div>
                </div>

                <!-- Step 2: Verify Code -->
                <div class="modal-content" id="step2">
                    <div class="modal-header">
                        <h1>Verify Your Code</h1>
                        <p id="verifyEmailDisplay">Verification code sent to your email</p>
                    </div>

                    <div class="verification-card">
                        <div class="verification-icon">
                            <i class="material-icons">mail</i>
                        </div>
                        <div class="verification-text">
                            Enter the <strong>6-digit code</strong> sent to your email
                        </div>
                    </div>

                    <form id="verifyCodeForm" class="signup-form">
                        <div class="code-inputs" id="codeInputs">
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" required>
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" required>
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" required>
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" required>
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" required>
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" required>
                        </div>

                        <button type="submit" class="btn-primary btn-full">
                            Verify Code
                            <i class="material-icons btn-icon">arrow_forward</i>
                        </button>
                    </form>

                    <div class="resend-section">
                        <p>Didn't receive the code? <span class="resend-link" id="resendBtn">Resend</span> <span class="resend-timer" id="resendTimer"></span></p>
                    </div>

                    <div class="modal-footer">
                        <p><a href="javascript:void(0)" onclick="goBackToStep1()" class="link-accent">Back to Login</a></p>
                    </div>
                </div>

                <!-- Step 3: Reset Password -->
                <div class="modal-content" id="step3">
                    <div class="modal-header">
                        <h1>Create New Password</h1>
                        <p>Enter a strong password for your account</p>
                    </div>

                    <form id="resetPasswordForm" class="signup-form">
                        <div class="form-field">
                            <label for="newPassword">New Password *</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password" required>
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('newPassword')">
                                    <i class="material-icons">visibility</i>
                                </button>
                            </div>
                            <div class="password-status-line" id="passwordStatus"></div>
                        </div>

                        <div class="form-field">
                            <label for="confirmPassword">Confirm Password *</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required>
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirmPassword')">
                                    <i class="material-icons">visibility</i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn-primary btn-full">
                            Reset Password
                            <i class="material-icons btn-icon">check</i>
                        </button>
                    </form>

                    <div class="modal-footer">
                        <p><a href="<?php echo $base_url; ?>login" class="link-accent">Back to Sign In</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS for Forgot Password -->
<script src="<?php echo $base_url; ?>public/js/forgot-password.js"></script>
<?php
include('includes/script.php');
include('includes/footer.php');
?>
