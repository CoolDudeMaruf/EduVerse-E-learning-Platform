// Login Form Functionality
document.addEventListener('DOMContentLoaded', function () {
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
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            handleLogin();
        });
    }
}

// Handle login form submission
function handleLogin() {
    const emailOrUsername = document.getElementById('emailOrUsername').value.trim();
    const password = document.getElementById('password').value;

    // Validation
    if (!emailOrUsername) {
        showErrorMessage('Please enter your email or username');
        return;
    }

    if (!validateEmailOrUsername(emailOrUsername)) {
        showErrorMessage('Please enter a valid email or username');
        return;
    }

    if (!password) {
        showErrorMessage('Please enter your password');
        return;
    }

    submitLogin(emailOrUsername, password);
}

// Validate email or username
function validateEmailOrUsername(value) {
    // Check if it's an email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (emailRegex.test(value)) {
        return true;
    }

    // Check if it's a username (3+ alphanumeric characters and underscores)
    const usernameRegex = /^[a-zA-Z0-9_]{3,}$/;
    return usernameRegex.test(value);
}

// Submit login via AJAX
function submitLogin(emailOrUsername, password) {
    const btn = document.querySelector('#loginForm .btn-primary');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons">hourglass_empty</span>Signing In...';

    $.ajax({
        type: 'POST',
        url: 'ajax/login.php',
        contentType: 'application/json',
        data: JSON.stringify({
            emailOrUsername: emailOrUsername,
            password: password
        }),
        dataType: 'json',
        success: function (data) {
            btn.disabled = false;
            btn.innerHTML = originalText;

            if (data.success) {
                showSuccessMessage('✓ Login successful! Redirecting...');

                setTimeout(() => {
                    window.location.href = data.redirect || '/eduverse';
                }, 1500);
            } else {
                showErrorMessage(data.message || 'Login failed');
            }
        },
        error: function (xhr, status, error) {
            btn.disabled = false;
            btn.innerHTML = originalText;

            let errorMsg = 'An error occurred. Please try again.';
            try {
                const response = JSON.parse(xhr.responseText);
                errorMsg = response.message || errorMsg;
            } catch (e) {
                if (xhr.responseText) {
                    errorMsg = xhr.responseText;
                }
            }

            showErrorMessage(errorMsg);
        }
    });
}
