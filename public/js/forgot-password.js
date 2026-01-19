// Forgot Password Form Functionality
let forgotPasswordEmail = '';
let forgotPasswordUsername = '';
let forgotPasswordUserId = '';

document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    setupNotificationSystem();
});

// Initialize notification system
function showErrorMessage(message) {
    let notification = document.getElementById('notification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'notification';
        notification.className = 'notification error-notification';
        document.body.appendChild(notification);
    }

    notification.textContent = message;
    notification.className = 'notification error-notification show';

    setTimeout(() => {
        notification.classList.remove('show');
    }, 5000);
}

function showSuccessMessage(message) {
    let notification = document.getElementById('notification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'notification';
        notification.className = 'notification success-notification';
        document.body.appendChild(notification);
    }

    notification.textContent = message;
    notification.className = 'notification success-notification show';

    setTimeout(() => {
        notification.classList.remove('show');
    }, 5000);
}

function setupNotificationSystem() {
    const style = document.createElement('style');
    style.textContent = `
        #notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 20px;
            border-radius: 8px;
            z-index: 9999;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            opacity: 0;
            transform: translateX(400px);
            transition: all 0.3s ease;
        }

        #notification.show {
            opacity: 1;
            transform: translateX(0);
        }

        .error-notification {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #f56565;
        }

        .success-notification {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #48bb78;
        }

        @media (max-width: 600px) {
            #notification {
                left: 10px;
                right: 10px;
                max-width: none;
            }
        }
    `;
    document.head.appendChild(style);
}

// Toggle password visibility
function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    const button = event.target.closest('.password-toggle');
    if (field.type === 'password') {
        field.type = 'text';
        button.innerHTML = '<i class="material-icons">visibility_off</i>';
    } else {
        field.type = 'password';
        button.innerHTML = '<i class="material-icons">visibility</i>';
    }
}

// Setup event listeners
function setupEventListeners() {
    const forgotForm = document.getElementById('forgotPasswordForm');
    const verifyForm = document.getElementById('verifyCodeForm');
    const resetForm = document.getElementById('resetPasswordForm');
    const resendBtn = document.getElementById('resendBtn');

    if(forgotForm) {
        forgotForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleForgotPassword();
        });
    }

    if(verifyForm) {
        verifyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleVerifyCode();
        });
    }

    if(resetForm) {
        resetForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleResetPassword();
        });
    }

    if(resendBtn) {
        resendBtn.addEventListener('click', function() {
            resendVerificationCode();
        });
    }

    // Setup code input auto-focus
    setupCodeInputAutoFocus();

    // Setup password strength indicator
    setupPasswordStrength();
}

// Step 1: Handle forgot password submission
function handleForgotPassword() {
    const emailOrUsername = document.getElementById('emailOrUsername').value.trim();

    if (!emailOrUsername) {
        showErrorMessage('Please enter your email or username');
        return;
    }

    if (!validateEmailOrUsername(emailOrUsername)) {
        showErrorMessage('Please enter a valid email or username');
        return;
    }

    submitForgotPassword(emailOrUsername);
}

// Validate email or username
function validateEmailOrUsername(value) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (emailRegex.test(value)) {
        return true;
    }
    
    const usernameRegex = /^[a-zA-Z0-9_]{3,}$/;
    return usernameRegex.test(value);
}

// Submit forgot password request
function submitForgotPassword(emailOrUsername) {
    const btn = document.querySelector('#forgotPasswordForm .btn-primary');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons">hourglass_empty</span>Sending Code...';

    $.ajax({
        type: 'POST',
        url: 'ajax/forgot-password.php',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'send_code',
            emailOrUsername: emailOrUsername
        }),
        dataType: 'json',
        success: function(data) {
            btn.disabled = false;
            btn.innerHTML = originalText;

            if(data.success) {
                forgotPasswordEmail = data.email || emailOrUsername;
                forgotPasswordUsername = data.username || emailOrUsername;
                forgotPasswordUserId = data.user_id;
                
                document.getElementById('verifyEmailDisplay').textContent = 'Verification code sent to ' + forgotPasswordEmail;
                
                showSuccessMessage('✓ Verification code sent!');
                setTimeout(() => {
                    goToStep(2);
                    startResendTimer();
                }, 1000);
            } else {
                showErrorMessage(data.message || 'Failed to send code');
            }
        },
        error: function(xhr, status, error) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            showErrorMessage('An error occurred. Please try again.');
        }
    });
}

// Step 2: Handle code verification
function handleVerifyCode() {
    const codeInputs = document.querySelectorAll('#codeInputs .code-input');
    let code = '';
    
    codeInputs.forEach(input => {
        if(input.value === '') {
            showErrorMessage('Please enter all 6 digits');
            return;
        }
        code += input.value;
    });

    if(code.length !== 6) {
        showErrorMessage('Please enter all 6 digits');
        return;
    }

    submitVerifyCode(code);
}

// Submit code verification
function submitVerifyCode(code) {
    const btn = document.querySelector('#verifyCodeForm .btn-primary');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons">hourglass_empty</span>Verifying...';

    $.ajax({
        type: 'POST',
        url: 'ajax/forgot-password.php',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'verify_code',
            user_id: forgotPasswordUserId,
            code: code
        }),
        dataType: 'json',
        success: function(data) {
            btn.disabled = false;
            btn.innerHTML = originalText;

            if(data.success) {
                showSuccessMessage('✓ Code verified!');
                setTimeout(() => {
                    goToStep(3);
                }, 1000);
            } else {
                showErrorMessage(data.message || 'Invalid code');
            }
        },
        error: function(xhr, status, error) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            showErrorMessage('An error occurred. Please try again.');
        }
    });
}

// Step 3: Handle password reset
function handleResetPassword() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    if (!newPassword) {
        showErrorMessage('Please enter a new password');
        return;
    }

    if (!validatePassword(newPassword)) {
        showErrorMessage('Password must be at least 8 characters with uppercase, lowercase, number, and special character');
        return;
    }

    if (newPassword !== confirmPassword) {
        showErrorMessage('Passwords do not match');
        return;
    }

    submitResetPassword(newPassword);
}

// Validate password strength
function validatePassword(password) {
    if (password.length < 8) return false;
    if (!/[A-Z]/.test(password)) return false;
    if (!/[a-z]/.test(password)) return false;
    if (!/[0-9]/.test(password)) return false;
    if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) return false;
    return true;
}

// Submit password reset
function submitResetPassword(newPassword) {
    const btn = document.querySelector('#resetPasswordForm .btn-primary');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons">hourglass_empty</span>Resetting...';

    $.ajax({
        type: 'POST',
        url: 'ajax/forgot-password.php',
        contentType: 'application/json',
        data: JSON.stringify({
            action: 'reset_password',
            user_id: forgotPasswordUserId,
            password: newPassword
        }),
        dataType: 'json',
        success: function(data) {
            btn.disabled = false;
            btn.innerHTML = originalText;

            if(data.success) {
                showSuccessMessage('✓ Password reset successfully! Redirecting to login...');
                setTimeout(() => {
                    window.location.href = 'login';
                }, 2000);
            } else {
                showErrorMessage(data.message || 'Failed to reset password');
            }
        },
        error: function(xhr, status, error) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            showErrorMessage('An error occurred. Please try again.');
        }
    });
}

// Navigation between steps
function goToStep(step) {
    document.querySelectorAll('.modal-content').forEach(el => {
        el.classList.remove('active');
    });
    document.getElementById('step' + step).classList.add('active');
}

function goBackToStep1() {
    goToStep(1);
    document.getElementById('forgotPasswordForm').reset();
    document.querySelectorAll('#codeInputs .code-input').forEach(input => {
        input.value = '';
    });
}

// Resend code functionality
let resendTimer = null;
function startResendTimer() {
    let timeLeft = 60;
    const timerEl = document.getElementById('resendTimer');
    const resendBtn = document.getElementById('resendBtn');
    
    resendBtn.style.pointerEvents = 'none';
    resendBtn.style.opacity = '0.5';
    
    resendTimer = setInterval(() => {
        timeLeft--;
        timerEl.textContent = timeLeft + 's';
        
        if (timeLeft <= 0) {
            clearInterval(resendTimer);
            resendBtn.style.pointerEvents = 'auto';
            resendBtn.style.opacity = '1';
            timerEl.textContent = '';
        }
    }, 1000);
}

function resendVerificationCode() {
    const btn = document.getElementById('resendBtn');
    if (btn.style.pointerEvents === 'none') return;
    
    submitForgotPassword(forgotPasswordEmail || forgotPasswordUsername);
}

// Setup code input auto-focus
function setupCodeInputAutoFocus() {
    const codeInputs = document.querySelectorAll('#codeInputs .code-input');
    
    codeInputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            if(this.value.length > 1) {
                this.value = this.value.slice(0, 1);
            }
            
            if(this.value && index < codeInputs.length - 1) {
                codeInputs[index + 1].focus();
            }
        });
        
        input.addEventListener('keydown', function(e) {
            if(e.key === 'Backspace' && !this.value && index > 0) {
                codeInputs[index - 1].focus();
            }
        });
        
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            const pasteArray = paste.slice(0, 6).split('');
            
            pasteArray.forEach((char, pasteIndex) => {
                if (index + pasteIndex < codeInputs.length) {
                    codeInputs[index + pasteIndex].value = char;
                }
            });
            
            codeInputs[Math.min(index + pasteArray.length - 1, codeInputs.length - 1)].focus();
        });
    });
}

// Setup password strength indicator
function setupPasswordStrength() {
    const passwordField = document.getElementById('newPassword');
    const statusLine = document.getElementById('passwordStatus');
    
    if(!passwordField) return;
    
    passwordField.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        let color = '#e53e3e';
        let text = 'Weak';
        
        if(password.length >= 8) strength++;
        if(/[A-Z]/.test(password)) strength++;
        if(/[a-z]/.test(password)) strength++;
        if(/[0-9]/.test(password)) strength++;
        if(/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) strength++;
        
        if(strength <= 2) {
            color = '#e53e3e';
            text = 'Weak';
        } else if(strength <= 3) {
            color = '#ed8936';
            text = 'Fair';
        } else if(strength <= 4) {
            color = '#ecc94b';
            text = 'Good';
        } else {
            color = '#48bb78';
            text = 'Strong';
        }
        
        statusLine.style.backgroundColor = color;
        statusLine.textContent = text;
    });
}
