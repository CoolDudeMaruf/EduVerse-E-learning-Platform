// Complete Signup Form Functionality
let currentStep = 1;
const totalSteps = 4;
let profilePhoto = null;
let photoRotation = 0;

document.addEventListener('DOMContentLoaded', function() {
    setupPhotoUpload();
    setupFormValidation();
    setupCharCounters();
    updateProgressBar();
});


function setupPhotoUpload() {
    const photoUploadArea = document.getElementById('photoUploadArea');
    const photoInput = document.getElementById('photoInput');
    const uploadPlaceholder = photoUploadArea.querySelector('.upload-placeholder');

    uploadPlaceholder.addEventListener('click', function() {
        photoInput.click();
    });

    photoInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            loadAndPreviewPhoto(this.files[0]);
        }
    });

    photoUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        photoUploadArea.classList.add('dragover');
    });

    photoUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        photoUploadArea.classList.remove('dragover');
    });

    photoUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        photoUploadArea.classList.remove('dragover');

        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            const file = e.dataTransfer.files[0];
            if (file.type.startsWith('image/')) {
                loadAndPreviewPhoto(file);
            } else {
                showErrorMessage('Please drop an image file');
            }
        }
    });

    setupPhotoActions();
}

function loadAndPreviewPhoto(file) {
    if (file.size > 5 * 1024 * 1024) {
        showErrorMessage('File size must not exceed 5MB');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        profilePhoto = {
            data: e.target.result,
            file: file,
            originalData: e.target.result
        };

        const previewImage = document.getElementById('previewImage');
        previewImage.src = profilePhoto.data;

        document.getElementById('photoUploadArea').style.display = 'none';
        document.getElementById('photoPreview').style.display = 'block';

        photoRotation = 0;
    };
    reader.readAsDataURL(file);
}

function setupPhotoActions() {
    const cropBtn = document.querySelector('.btn-action.crop');
    const rotateBtn = document.querySelector('.btn-action.rotate');
    const removeBtn = document.querySelector('.btn-action.remove');

    if (cropBtn) {
        cropBtn.addEventListener('click', function() {
            const img = document.getElementById('previewImage');
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            const image = new Image();
            image.onload = function() {
                const minDim = Math.min(image.width, image.height);
                canvas.width = minDim;
                canvas.height = minDim;
                
                const offsetX = (image.width - minDim) / 2;
                const offsetY = (image.height - minDim) / 2;
                
                ctx.drawImage(image, offsetX, offsetY, minDim, minDim, 0, 0, minDim, minDim);
                profilePhoto.data = canvas.toDataURL('image/jpeg', 0.9);
                img.src = profilePhoto.data;
            };
            image.src = profilePhoto.data;
        });
    }

    if (rotateBtn) {
        rotateBtn.addEventListener('click', function() {
            photoRotation = (photoRotation + 90) % 360;
            const img = document.getElementById('previewImage');
            
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            const image = new Image();
            image.onload = function() {
                if (photoRotation === 0) {
                    canvas.width = image.width;
                    canvas.height = image.height;
                } else if (photoRotation === 90 || photoRotation === 270) {
                    canvas.width = image.height;
                    canvas.height = image.width;
                } else {
                    canvas.width = image.width;
                    canvas.height = image.height;
                }
                
                ctx.save();
                ctx.translate(canvas.width / 2, canvas.height / 2);
                ctx.rotate((photoRotation * Math.PI) / 180);
                ctx.drawImage(image, -image.width / 2, -image.height / 2);
                ctx.restore();
                
                profilePhoto.data = canvas.toDataURL('image/jpeg', 0.9);
                img.src = profilePhoto.data;
            };
            image.src = profilePhoto.data;
        });
    }

    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            profilePhoto = null;
            photoRotation = 0;
            document.getElementById('photoInput').value = '';
            document.getElementById('photoUploadArea').style.display = 'block';
            document.getElementById('photoPreview').style.display = 'none';
        });
    }
}


function changeStep(direction) {
    const steps = document.querySelectorAll('.form-step');
    
    if (direction > 0) {
        if (!validateStep(currentStep)) {
            return;
        }
    }

    steps[currentStep - 1].classList.remove('active');

    currentStep += direction;

    if (currentStep < 1) currentStep = 1;
    if (currentStep > totalSteps) currentStep = totalSteps;

    steps[currentStep - 1].classList.add('active');

    updateProgressBar();
    updateButtons();

    document.querySelector('.form-card').scrollTop = 0;
}

function updateProgressBar() {
    const progressPercentage = (currentStep / totalSteps) * 100;
    document.getElementById('progressBar').style.width = progressPercentage + '%';
    document.getElementById('currentStep').textContent = currentStep;
}

function updateButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    // Show/hide prev button
    prevBtn.style.display = currentStep > 1 ? 'flex' : 'none';

    // Show/hide next/submit buttons
    if (currentStep === totalSteps) {
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'flex';
    } else {
        nextBtn.style.display = 'flex';
        submitBtn.style.display = 'none';
    }
}

// ===== FORM VALIDATION =====

function validateStep(step) {
    switch(step) {
        case 1:
            return validateContactInfo();
        case 2:
            return validatePersonalDetails();
        case 3:
            return validateProfessional();
        case 4:
            return validateSocialLinks();
        default:
            return true;
    }
}

function validateContactInfo() {
    const firstName = document.getElementById('firstName').value.trim();
    const lastName = document.getElementById('lastName').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const country = document.getElementById('country').value.trim();
    
    if (!firstName) {
        showFieldError('firstName', 'First name is required');
        return false;
    }

    if (!lastName) {
        showFieldError('lastName', 'Last name is required');
        return false;
    }

    if (!phone) {
        showFieldError('phone', 'Phone number is required');
        return false;
    }

    if (!/^\d{7,15}$/.test(phone)) {
        showFieldError('phone', 'Please enter a valid phone number (7-15 digits)');
        return false;
    }

    if (!country) {
        showFieldError('country', 'Country is required');
        return false;
    }

    clearFieldError('firstName');
    clearFieldError('lastName');
    clearFieldError('phone');
    clearFieldError('country');
    return true;
}

function validatePersonalDetails() {
    const dob = document.getElementById('dob').value;
    const gender = document.getElementById('gender').value;
    const bloodGroup = document.getElementById('bloodGroup').value;

    if (!dob) {
        showFieldError('dob', 'Date of birth is required');
        return false;
    }

    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }

    if (age < 13) {
        showFieldError('dob', 'You must be at least 13 years old');
        return false;
    }

    if (!gender) {
        showFieldError('gender', 'Gender is required');
        return false;
    }

    if (!bloodGroup) {
        showFieldError('bloodGroup', 'Blood group is required');
        return false;
    }

    clearFieldError('dob');
    clearFieldError('gender');
    clearFieldError('bloodGroup');
    return true;
}

function validateProfessional() {
    clearFieldError('headline');
    clearFieldError('occupation');
    clearFieldError('company');
    clearFieldError('location');
    return true;
}

function validateSocialLinks() {
    const linkedin = document.getElementById('linkedin').value.trim();
    const twitter = document.getElementById('twitter').value.trim();
    const github = document.getElementById('github').value.trim();

    if (linkedin && !isValidUrl(linkedin)) {
        showFieldError('linkedin', 'Please enter a valid LinkedIn URL');
        return false;
    }

    if (github && !isValidUrl(github)) {
        showFieldError('github', 'Please enter a valid GitHub URL');
        return false;
    }

    // Twitter handle validation
    if (twitter && !/^@?[\w]{1,15}$/.test(twitter.replace(/^@/, ''))) {
        showFieldError('twitter', 'Please enter a valid Twitter handle');
        return false;
    }

    clearFieldError('linkedin');
    clearFieldError('github');
    clearFieldError('twitter');
    return true;
}

function isValidUrl(string) {
    try {
        new URL(string);
        return true;
    } catch (_) {
        return false;
    }
}

// ===== FIELD ERROR HANDLING =====

function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const group = field.closest('.form-group');
    
    group.classList.add('error');
    
    let errorMsg = group.querySelector('.error-message');
    if (!errorMsg) {
        errorMsg = document.createElement('div');
        errorMsg.className = 'error-message';
        group.appendChild(errorMsg);
    }
    errorMsg.textContent = message;
    errorMsg.classList.add('show');
}

function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    const group = field.closest('.form-group');
    
    group.classList.remove('error');
    
    const errorMsg = group.querySelector('.error-message');
    if (errorMsg) {
        errorMsg.classList.remove('show');
    }
}

// ===== FORM VALIDATION SETUP =====

function setupFormValidation() {
    const form = document.getElementById('completeSignupForm');

    // Clear errors on input
    form.addEventListener('input', function(e) {
        const field = e.target;
        if (field.closest('.form-group')) {
            clearFieldError(field.id);
        }
    });

    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        submitProfileForm();
    });
}

// ===== CHARACTER COUNTERS =====

function setupCharCounters() {
    const bioField = document.getElementById('bio');
    const headlineField = document.getElementById('headline');

    if (bioField) {
        bioField.addEventListener('input', function() {
            const count = this.value.length;
            document.getElementById('charCount').textContent = count;
            
            if (count >= 1000) {
                document.querySelector('small .char-count').classList.add('limit-reached');
            } else {
                document.querySelector('small .char-count').classList.remove('limit-reached');
            }
        });
    }

    if (headlineField) {
        headlineField.addEventListener('input', function() {
            const count = this.value.length;
            document.getElementById('headlineCount').textContent = count;
        });
    }
}

// ===== FORM SUBMISSION =====

function submitProfileForm() {
    if (!validateStep(currentStep)) {
        return;
    }

    const formData = new FormData(document.getElementById('completeSignupForm'));

    if (profilePhoto) {
        formData.append('profile_photo', profilePhoto.data);
    }

    const baseUrl = document.querySelector('[data-base-url]')?.getAttribute('data-base-url') || '/eduverse/';

    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="material-icons">hourglass_empty</i>Submitting...';
    submitBtn.disabled = true;

    // Send via AJAX
    $.ajax({
        url: baseUrl + 'ajax/signup_complete.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showSuccessMessage(response.message || 'Profile completed successfully!');
                
                setTimeout(function() {
                    window.location.href = baseUrl + 'dashboard';
                }, 2000);
            } else {
                showErrorMessage(response.message || 'An error occurred');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        },
        error: function(xhr, status, error) {
            
            let errorMsg = 'An error occurred while submitting your profile';
            
            try {
                const response = JSON.parse(xhr.responseText);
                errorMsg = response.message || errorMsg;
            } catch(e) {
                // Handle non-JSON response
                if (xhr.responseText) {
                    errorMsg = xhr.responseText;
                }
            }
            
            showErrorMessage(errorMsg);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
}


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


