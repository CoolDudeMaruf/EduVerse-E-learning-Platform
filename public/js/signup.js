
document.addEventListener('DOMContentLoaded', function () {
    setupEventListeners();
    setupCodeInputAutoFocus();
    setupPasswordValidation();
});

const baseUrl = document.querySelector('[data-base-url]')?.getAttribute('data-base-url') || '/eduverse/';
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

// Password validation function
function setupPasswordValidation() {
    const passwordField = document.getElementById('password');
    const confirmField = document.getElementById('confirmPassword');

    if (!passwordField) return;


}


function validatePassword(password) {
    const requirements = {
        length: password.length >= 8,
        upper: /[A-Z]/.test(password),
        lower: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
    };

    return Object.values(requirements).every(val => val === true);
}



function setupEventListeners() {
    const form1 = document.querySelector('#form-step1 button.continue');
    if (form1) {
        form1.addEventListener('click', function (e) {
            e.preventDefault();
            validateAndProceed();
        });
    }
}

function validateAndProceed() {
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;


    const role = document.querySelector('input[name="role"]:checked').value;

    if (!username) {
        showErrorMessage('Please enter username');
        return;
    }



    if (!validateEmail(email)) {
        showErrorMessage('Please enter a valid email address');
        return;
    }

    if (!validatePassword(password)) {
        showErrorMessage('Password must contain at least 8 characters, one uppercase, one lowercase, one number, and one special character');
        return;
    }

    if (password !== confirmPassword) {
        showErrorMessage('Passwords do not match');
        return;
    }

    sendVerificationCode(email, username, role, password);
}


function sendVerificationCode(email, username, role, password) {
    const btn = document.querySelector('#form-step1 .btn-primary');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons">hourglass_empty</span>Sending...';

    $.ajax({
        type: 'POST',
        url: baseUrl + 'ajax/auth.php?action=send_code',
        contentType: 'application/json',
        data: JSON.stringify({
            email: email,
            username: username,
            role: role,
            password: password
        }),
        dataType: 'json',
        success: function (data) {
            btn.disabled = false;
            btn.innerHTML = originalText;


            if (data.success) {
                showSuccessMessage('✓ Code sent to ' + email);
                localStorage.setItem('signup_email', email);

                setTimeout(() => {
                    switchModal('signupModal2');
                    setupResendTimer();
                }, 1000);
            } else {
                showErrorMessage(data.message || 'Failed to send verification code');
            }
        },
        error: function (xhr, status, error) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            showErrorMessage('An error occurred. Please try again.');
        }
    });
}

/**
 * Switch between modals
 */
function switchModal(modalId) {
    // Hide all modals
    document.querySelectorAll('.modal-content').forEach(modal => {
        modal.classList.remove('active');
    });

    // Show target modal
    const targetModal = document.getElementById(modalId);
    if (targetModal) {
        targetModal.classList.add('active');
    }

}




function verifyCode() {
    const codeInputs = document.querySelectorAll('.code-input');
    let code = '';

    codeInputs.forEach(input => {
        code += input.value;
    });

    if (code.length !== 6) {
        showErrorMessage('Please enter all 6 digits');
        return;
    }

    const email = localStorage.getItem('signup_email');
    const btn = document.querySelector('#signupModal2 .btn-primary.verifyCode');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons">hourglass_empty</span>Verifying...';

    $.ajax({
        type: 'POST',
        url: baseUrl + 'ajax/auth.php?action=verify_code',
        contentType: 'application/json',
        data: JSON.stringify({
            email: email,
            code: code
        }),
        dataType: 'json',
        success: function (data) {
            btn.disabled = false;
            btn.innerHTML = originalText;

            if (data.success) {
                showSuccessMessage('✓ Email verified successfully!');
                setTimeout(() => {
                    window.location.href = data.redirect || window.location.href + '?step=completed';
                }, 1500);
            } else {
                showErrorMessage(data.message || 'Invalid verification code');
                codeInputs.forEach(input => input.value = '');
                codeInputs[0].focus();
            }
        },
        error: function (xhr, status, error) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            showErrorMessage('An error occurred. Please try again.');
        }
    });
}


function resendCode() {
    const email = localStorage.getItem('signup_email');
    const resendLink = document.querySelector('.resend-link');
    const originalText = resendLink.textContent;

    resendLink.style.pointerEvents = 'none';
    resendLink.style.opacity = '0.5';
    resendLink.textContent = 'Sending...';

    $.ajax({
        type: 'POST',
        url: baseUrl + 'ajax/auth.php?action=resend_code',
        contentType: 'application/json',
        data: JSON.stringify({
            email: email
        }),
        dataType: 'json',
        success: function (data) {
            if (data.success) {
                showSuccessMessage('✓ Code resent to your email');
                setupResendTimer();

                // Clear code inputs
                document.querySelectorAll('.code-input').forEach(input => {
                    input.value = '';
                });
                document.querySelector('.code-input').focus();
            } else {
                showErrorMessage(data.message || 'Failed to resend code');
                resendLink.style.pointerEvents = 'auto';
                resendLink.style.opacity = '1';
                resendLink.textContent = originalText;
            }
        },
        error: function (xhr, status, error) {
            showErrorMessage('An error occurred. Please try again.');
            resendLink.style.pointerEvents = 'auto';
            resendLink.style.opacity = '1';
            resendLink.textContent = originalText;
        }
    });
}

/**
 * Setup code input auto-focus
 */
function setupCodeInputAutoFocus() {
    const codeInputs = document.querySelectorAll('.code-input');

    codeInputs.forEach((input, index) => {
        input.addEventListener('input', function (e) {
            // Only allow numbers
            this.value = this.value.replace(/[^0-9]/g, '');

            // Move to next input
            if (this.value.length === 1 && index < codeInputs.length - 1) {
                codeInputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', function (e) {
            // Handle backspace
            if (e.key === 'Backspace' && this.value === '' && index > 0) {
                codeInputs[index - 1].focus();
            }

            // Handle paste
            if (e.ctrlKey && e.key === 'v') {
                e.preventDefault();
                navigator.clipboard.readText().then(text => {
                    const digits = text.replace(/[^0-9]/g, '').split('');
                    digits.forEach((digit, i) => {
                        if (index + i < codeInputs.length) {
                            codeInputs[index + i].value = digit;
                        }
                    });
                    if (digits.length > 0) {
                        codeInputs[Math.min(index + digits.length - 1, codeInputs.length - 1)].focus();
                    }
                });
            }
        });
    });
}

/**
 * Setup resend timer (60 seconds)
 */
function setupResendTimer() {
    const resendLink = document.querySelector('.resend-link');
    const timerSpan = document.querySelector('.resend-timer');
    let timeLeft = 60;

    if (!resendLink) return;

    resendLink.style.pointerEvents = 'none';
    resendLink.style.opacity = '0.5';

    const interval = setInterval(() => {
        timeLeft--;

        if (timeLeft <= 0) {
            clearInterval(interval);
            resendLink.style.pointerEvents = 'auto';
            resendLink.style.opacity = '1';
            timerSpan.textContent = '';
        } else {
            timerSpan.textContent = `(${timeLeft}s)`;
        }
    }, 1000);
}

/**
 * Validate email format
 */
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}




