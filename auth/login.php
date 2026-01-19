<?php
$page_title = 'Login';
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
                <h2 class="brand-title">Welcome Back</h2>
                <p class="brand-subtitle">Continue your learning journey with us</p>
            </div>

            <!-- Benefits -->
            <div class="benefits-section">
                <div class="benefit-card">
                    <i class="material-icons benefit-icon">trending_up</i>
                    <h3>Track Progress</h3>
                    <p>Monitor your learning journey</p>
                </div>
                <div class="benefit-card">
                    <i class="material-icons benefit-icon">bookmark</i>
                    <h3>Saved Courses</h3>
                    <p>Access your bookmarked content</p>
                </div>
                <div class="benefit-card">
                    <i class="material-icons benefit-icon">assessment</i>
                    <h3>View Certificates</h3>
                    <p>Showcase your achievements</p>
                </div>
                <div class="benefit-card">
                    <i class="material-icons benefit-icon">group</i>
                    <h3>Community</h3>
                    <p>Connect with other learners</p>
                </div>
            </div>

            <div class="footer-text">
                <p>Join <strong>500,000+</strong> learners worldwide</p>
            </div>
        </div>

        <div class="signup-right">
            <div class="form-wrapper">
                <!-- Login Form -->
                <div class="modal-content active">
                    <div class="modal-header">
                        <h1>Welcome Back</h1>
                        <p>Sign in to your account</p>
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
                        <span>or login with email</span>
                    </div>

                    <!-- Form Fields -->
                    <form id="loginForm" class="signup-form">
                        <div class="form-field">
                            <label for="emailOrUsername">Email or Username *</label>
                            <input type="text" id="emailOrUsername" name="emailOrUsername" placeholder="mohatamimhaque7@gmail.com or username" required>
                        </div>

                        <div class="form-field">
                            <label for="password">Password *</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password')">
                                    <i class="material-icons">visibility</i>
                                </button>
                            </div>
                        </div>

                        <div class="forgot-password-section">
                            <a href="<?php echo $base_url; ?>recovery" class="forgot-link">Forgot Password?</a>
                        </div>

                        <button type="submit" class="btn-primary btn-full">
                            Sign In
                            <i class="material-icons btn-icon">arrow_forward</i>
                        </button>
                    </form>

                    <div class="modal-footer">
                        <p>Don't have an account? <a href="<?php echo $base_url; ?>signup" class="link-accent">Create Account</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS for Login -->
<script src="<?php echo $base_url; ?>public/js/login.js"></script>
<?php
include('includes/script.php');
include('includes/footer.php');
?>
