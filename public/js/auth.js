// ===================================
// Authentication JavaScript
// ===================================

$(document).ready(function() {
    // Toggle password visibility
    $('.toggle-password').on('click', function() {
        const input = $(this).siblings('input');
        const icon = $(this).find('.material-icons');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.text('visibility_off');
        } else {
            input.attr('type', 'password');
            icon.text('visibility');
        }
    });

    // Password strength checker
    $('#password').on('input', function() {
        const password = $(this).val();
        const strength = calculatePasswordStrength(password);
        updatePasswordStrength(strength);
    });

    function calculatePasswordStrength(password) {
        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;
        
        if (strength <= 2) return 'weak';
        if (strength <= 4) return 'medium';
        return 'strong';
    }

    function updatePasswordStrength(strength) {
        const strengthFill = $('.strength-fill');
        const strengthText = $('.strength-text');
        
        strengthFill.removeClass('weak medium strong');
        
        switch(strength) {
            case 'weak':
                strengthFill.addClass('weak');
                strengthText.text('Weak password').css('color', 'var(--error)');
                break;
            case 'medium':
                strengthFill.addClass('medium');
                strengthText.text('Medium password').css('color', 'var(--warning)');
                break;
            case 'strong':
                strengthFill.addClass('strong');
                strengthText.text('Strong password').css('color', 'var(--success)');
                break;
        }
    }

    // Login form submission
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        
        const email = $('#email').val();
        const password = $('#password').val();
        const remember = $('input[name="remember"]').is(':checked');
        
        // Validate form
        if (!validateEmail(email)) {
            showError('email', 'Please enter a valid email address');
            return;
        }
        
        if (password.length < 6) {
            showError('password', 'Password must be at least 6 characters');
            return;
        }
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        setLoadingState(submitBtn, true);
        
        // Simulate API call
        setTimeout(() => {
            setLoadingState(submitBtn, false);
            
            // Save to localStorage (in real app, this would be handled by backend)
            saveToLocalStorage('user', {
                email: email,
                isLoggedIn: true,
                loginDate: new Date().toISOString()
            });
            
            showToast('Login successful! Redirecting...', 'success');
            
            setTimeout(() => {
                window.location.href = '../index.html';
            }, 1500);
        }, 2000);
    });

    // Signup form submission
    $('#signupForm').on('submit', function(e) {
        e.preventDefault();
        
        const firstName = $('#firstName').val();
        const lastName = $('#lastName').val();
        const email = $('#email').val();
        const password = $('#password').val();
        const confirmPassword = $('#confirmPassword').val();
        const terms = $('input[name="terms"]').is(':checked');
        const role = $('input[name="role"]:checked').val();
        
        // Clear previous errors
        clearErrors();
        
        // Validate form
        let hasErrors = false;
        
        if (firstName.trim().length < 2) {
            showError('firstName', 'First name must be at least 2 characters');
            hasErrors = true;
        }
        
        if (lastName.trim().length < 2) {
            showError('lastName', 'Last name must be at least 2 characters');
            hasErrors = true;
        }
        
        if (!validateEmail(email)) {
            showError('email', 'Please enter a valid email address');
            hasErrors = true;
        }
        
        if (password.length < 8) {
            showError('password', 'Password must be at least 8 characters');
            hasErrors = true;
        }
        
        if (password !== confirmPassword) {
            showError('confirmPassword', 'Passwords do not match');
            hasErrors = true;
        }
        
        if (!terms) {
            showToast('Please accept the terms and conditions', 'error');
            hasErrors = true;
        }
        
        if (hasErrors) return;
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        setLoadingState(submitBtn, true);
        
        // Simulate API call
        setTimeout(() => {
            setLoadingState(submitBtn, false);
            
            // Save to localStorage
            saveToLocalStorage('user', {
                firstName: firstName,
                lastName: lastName,
                email: email,
                role: role,
                isLoggedIn: true,
                signupDate: new Date().toISOString()
            });
            
            showToast('Account created successfully! Redirecting...', 'success');
            
            setTimeout(() => {
                window.location.href = '../index.html';
            }, 1500);
        }, 2000);
    });

    // Social login buttons
    $('.btn-google, .btn-facebook').on('click', function() {
        const platform = $(this).hasClass('btn-google') ? 'Google' : 'Facebook';
        showToast(`${platform} login will be implemented with OAuth`, 'info');
    });

    // Helper functions
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function showError(fieldId, message) {
        const field = $(`#${fieldId}`);
        const inputGroup = field.closest('.input-group');
        
        inputGroup.addClass('error');
        
        // Remove existing error message
        inputGroup.siblings('.error-message').remove();
        
        // Add error message
        inputGroup.after(`<span class="error-message">${message}</span>`);
        
        // Focus on first error field
        if ($('.error').length === 1) {
            field.focus();
        }
    }

    function clearErrors() {
        $('.input-group').removeClass('error success');
        $('.error-message').remove();
    }

    // Real-time email validation
    $('input[type="email"]').on('blur', function() {
        const email = $(this).val();
        const inputGroup = $(this).closest('.input-group');
        
        if (email) {
            if (validateEmail(email)) {
                inputGroup.removeClass('error').addClass('success');
                inputGroup.siblings('.error-message').remove();
            } else {
                inputGroup.removeClass('success').addClass('error');
                if (inputGroup.siblings('.error-message').length === 0) {
                    inputGroup.after('<span class="error-message">Please enter a valid email</span>');
                }
            }
        }
    });

    // Real-time password confirmation
    $('#confirmPassword').on('input', function() {
        const password = $('#password').val();
        const confirmPassword = $(this).val();
        const inputGroup = $(this).closest('.input-group');
        
        if (confirmPassword) {
            if (password === confirmPassword) {
                inputGroup.removeClass('error').addClass('success');
                inputGroup.siblings('.error-message').remove();
            } else {
                inputGroup.removeClass('success').addClass('error');
                if (inputGroup.siblings('.error-message').length === 0) {
                    inputGroup.after('<span class="error-message">Passwords do not match</span>');
                }
            }
        }
    });

    // Clear input on focus (remove error state)
    $('input').on('focus', function() {
        $(this).closest('.input-group').removeClass('error');
        $(this).closest('.input-group').siblings('.error-message').remove();
    });

    // Enter key handling
    $('input').on('keypress', function(e) {
        if (e.which === 13) {
            $(this).closest('form').submit();
        }
    });

    // Auto-focus first input
    $('.auth-form input:first').focus();
});
