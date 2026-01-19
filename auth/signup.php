<?php
$page_title = 'Sign Up';
include('includes/header.php');
// unset($_SESSION['auth']);
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
                <h2 class="brand-title">Your Learning Destination</h2>
                <p class="brand-subtitle">Start your journey to success with thousands of courses</p>
            </div>

            <!-- Benefits -->
            <div class="benefits-section">
                <div class="benefit-card">
                    <i class="material-icons benefit-icon">library_books</i>
                    <h3>10,000+ Courses</h3>
                    <p>Learn from diverse topics across all fields</p>
                </div>
                <div class="benefit-card">
                    <i class="material-icons benefit-icon">person_outline</i>
                    <h3>Expert Instructors</h3>
                    <p>Learn from industry professionals</p>
                </div>
                <div class="benefit-card">
                    <i class="material-icons benefit-icon">card_membership</i>
                    <h3>Earn Certificates</h3>
                    <p>Get recognized credentials</p>
                </div>
                <div class="benefit-card">
                    <i class="material-icons benefit-icon">schedule</i>
                    <h3>Learn at Your Pace</h3>
                    <p>Study anytime, anywhere</p>
                </div>
            </div>

            <div class="footer-text">
                <p>Join <strong>500,000+</strong> learners worldwide</p>
            </div>
        </div>

         <div class="signup-right">
            <div class="form-wrapper">
                <!-- Modal 1: Basic Info -->
                <div id="signupModal1" class="modal-content active">
                    <div class="modal-header">
                        <h1>Create Account</h1>
                        <p>Join our learning community</p>
                    </div>

                    <!-- Role Selection -->
                    <div class="role-selection-container">
                        <label class="role-card-item">
                            <input type="radio" name="role" value="student" checked>
                            <div class="role-content">
                                <i class="material-icons role-icon">person</i>
                                <span class="role-name">Student</span>
                                <span class="role-desc">Explore courses</span>
                            </div>
                        </label>
                        <label class="role-card-item">
                            <input type="radio" name="role" value="instructor">
                            <div class="role-content">
                                <i class="material-icons role-icon">school</i>
                                <span class="role-name">Instructor</span>
                                <span class="role-desc">Teach others</span>
                            </div>
                        </label>
                    </div>

                    <!-- Social Auth -->
                    <div class="social-auth-section">
                        <button type="button" class="social-button google">
                            <svg viewBox="0 0 24 24"><path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/></svg>
                            Google
                        </button>
                        <button type="button" class="social-button facebook">
                            <svg viewBox="0 0 24 24"><path fill="currentColor" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            Facebook
                        </button>
                    </div>

                    <div class="divider-section">
                        <span>or continue with email</span>
                    </div>

                    <!-- Form Fields -->
                    <form id="form-step1" class="signup-form">
                        <div class="form-field">
                            <label for="username">Username*</label>
                            <input type="text" id="username" name="username" placeholder="mohatamim" required>
                        </div>

                        <div class="form-field">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" placeholder="mohatamimhaque7@gmail.com" required >
                        </div>

                        <div class="form-row" style="margin-top:12px">
                            <div class="form-field">
                                <label for="password">Password*</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="password" name="password" placeholder="At least 8 characters" required>
                                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password')">
                                        <i class="material-icons">visibility</i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-field">
                                <label for="confirmPassword">Confirm Password*</label>
                                <div class="password-input-wrapper">
                                    <input  type="password" id="confirmPassword" name="confirmPassword" placeholder="Re-enter password" required> 
                                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirmPassword')">
                                        <i class="material-icons">visibility</i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        

                        <button type="button" class="btn-primary btn-full continue">
                            Continue
                            <i class="material-icons btn-icon">arrow_forward</i>
                        </button>
                    </form>

                    <div class="modal-footer">
                        <p>Already have an account? <a href="<?php echo $base_url; ?>login" class="link-accent">Sign In</a></p>
                    </div>
                </div>

                <!-- Modal 2: Email Verification -->
                <div id="signupModal2" class="modal-content">
                    <div class="modal-header">
                        <h1>Verify Email</h1>
                        <p>Enter the 6-digit code we sent</p>
                    </div>

                    <div class="verification-card">
                        <i class="material-icons verification-icon">mail</i>
                        <p class="verification-text">We've sent a verification code to <strong id="emailDisplay"></strong></p>
                        
                        <div class="code-inputs">
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" placeholder="0">
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" placeholder="0">
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" placeholder="0">
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" placeholder="0">
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" placeholder="0">
                            <input type="text" class="code-input" maxlength="1" inputmode="numeric" placeholder="0">
                        </div>

                        <div class="resend-section">
                            <p>Didn't receive the code?</p>
                            <a href="javascript:void(0)" onclick="resendCode()" class="resend-link">Resend Code</a>
                            <span class="resend-timer"></span>
                        </div>
                    </div>

                    <button type="button" class="btn-primary btn-full verifyCode" onclick="verifyCode()">
                        Verify & Continue
                        <i class="material-icons btn-icon">check</i>
                    </button>

                    <button type="button" class="btn-secondary btn-full" onclick="switchModal('signupModal1')">
                        <i class="material-icons btn-icon">arrow_back</i>
                        Back to Email
                    </button>

                    <div class="modal-footer">
                        <p>Need help? <a href="#" class="link-accent">Contact Support</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS for Signup -->

<script src="<?php echo $base_url; ?>public/js/signup.js"></script>
<?php
include('includes/script.php');
include('includes/footer.php');
?>

