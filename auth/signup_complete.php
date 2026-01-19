<?php
$page_title = 'Complete Your Profile';
include('includes/header.php');

// Check if user is authenticated
if(!isset($_SESSION['auth'])) {
    header("Location: " . $base_url . "signup");
    exit(0);
}
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>public/css/signup.css">
<link rel="stylesheet" href="<?php echo $base_url; ?>public/css/complete-signup.css">

<div class="complete-signup-wrapper">
    <div class="complete-signup-container">
        <!-- Left Column - Profile Photo -->
        <div class="profile-column">
            <div class="profile-card">
                <div class="profile-header">
                    <h2>Profile Photo</h2>
                    <p>Add a professional photo</p>
                </div>

                <!-- Photo Upload Area -->
                <div class="photo-upload-area" id="photoUploadArea">
                    <div class="upload-placeholder">
                        <i class="material-icons">cloud_upload</i>
                        <p>Drag & drop your photo here</p>
                        <span>or</span>
                        <label for="photoInput" class="upload-link">browse files</label>
                        <input type="file" id="photoInput" accept="image/*" style="display: none;">
                    </div>
                </div>

                <!-- Photo Preview -->
                <div id="photoPreview" class="photo-preview" style="display: none;">
                    <img id="previewImage" src="" alt="Profile Preview">
                    <div class="photo-actions">
                        <button type="button" class="btn-action crop" title="Crop">
                            <i class="material-icons">crop</i>
                        </button>
                        <button type="button" class="btn-action rotate" title="Rotate">
                            <i class="material-icons">rotate_right</i>
                        </button>
                        <button type="button" class="btn-action remove" title="Remove">
                            <i class="material-icons">delete</i>
                        </button>
                    </div>
                </div>

                <!-- Photo Info -->
                <div class="photo-info">
                    <p><strong>Recommended:</strong> Square image, 400x400px minimum</p>
                    <p><strong>Formats:</strong> JPG, PNG (Max 5MB)</p>
                </div>
            </div>
        </div>

        <!-- Right Column - Form -->
        <div class="form-column">
            <div class="form-card">
                <div class="form-header">
                    <h1>Complete Your Profile</h1>
                    <p>Help us know more about you</p>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressBar" style="width: 25%;"></div>
                    </div>
                    <p class="progress-text"><span id="currentStep">1</span> of <span id="totalSteps">4</span></p>
                </div>

                <form id="completeSignupForm" class="form-container">
                    <!-- Step 1: Contact Information -->
                    <div class="form-step active" id="step1">
                        <h3>Contact Information</h3>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number <span class="required">*</span></label>
                            <div class="phone-input-group">
                                <select id="countryCode" name="countryCode" class="country-code">
                                    <option value="+880" selected>🇧🇩 +880</option>
                                    <option value="+1">🇺🇸 +1</option>
                                    <option value="+44">🇬🇧 +44</option>
                                    <option value="+91">🇮🇳 +91</option>
                                    <option value="+86">🇨🇳 +86</option>
                                    <option value="+81">🇯🇵 +81</option>
                                </select>
                                <input type="tel" id="phone" name="phone" placeholder="01518749114">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName">First Name<span class="required">*</span></label>
                                <input type="text" id="firstName" name="first_name" placeholder="Mohatamim">
                            </div>
                            <div class="form-group">
                                <label for="lastName">Last Name<span class="required">*</span></label>
                                <input type="text" id="lastName" name="last_name" placeholder="Haque">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="country">Country <span class="required">*</span></label>
                            <input type="text" id="country" name="country" placeholder="Bangladesh">
                        </div>
                    </div>

                    <!-- Step 2: Personal Details -->
                    <div class="form-step" id="step2">
                        <h3>Personal Details</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="dob">Date of Birth <span class="required">*</span></label>
                                <input type="date" id="dob" name="date_of_birth">
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender <span class="required">*</span></label>
                                <select id="gender" name="gender">
                                    <option value="">Select gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="bloodGroup">Blood Group <span class="required">*</span></label>
                                <select id="bloodGroup" name="blood_group">
                                    <option value="">Select blood group</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="bio">Bio <span class="optional">(Optional)</span></label>
                            <textarea id="bio" name="bio" placeholder="Tell us about yourself" rows="4" maxlength="1000"></textarea>
                            <small class="char-count"><span id="charCount">0</span>/1000</small>
                        </div>
                    </div>

                    <!-- Step 3: Professional Information -->
                    <div class="form-step" id="step3">
                        <h3>Professional Information</h3>
                        
                        <div class="form-group">
                            <label for="headline">Professional Headline <span class="optional">(Optional)</span></label>
                            <input type="text" id="headline" name="headline" placeholder="e.g., Full Stack Developer" maxlength="120">
                            <small class="char-count"><span id="headlineCount">0</span>/120</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="occupation">Occupation <span class="optional">(Optional)</span></label>
                                <input type="text" id="occupation" name="occupation" placeholder="e.g., Full Stack Developer">
                            </div>
                            <div class="form-group">
                                <label for="company">Company <span class="optional">(Optional)</span></label>
                                <input type="text" id="company" name="company" placeholder="e.g., EduVerse">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="location">Location <span class="optional">(Optional)</span></label>
                            <input type="text" id="location" name="location" placeholder="e.g., Dhaka, Bangladesh">
                        </div>
                    </div>

                    <!-- Step 4: Social Links -->
                    <div class="form-step" id="step4">
                        <h3>Social Links</h3>
                        
                        <div class="form-group">
                            <label for="linkedin">LinkedIn URL <span class="optional">(Optional)</span></label>
                            <div class="input-with-icon">
                                <i class="fab fa-linkedin"></i>
                                <input type="url" id="linkedin" name="linkedin_url" placeholder="https://www.linkedin.com/in/mohatamim/">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="twitter">Twitter Handle <span class="optional">(Optional)</span></label>
                            <div class="input-with-icon">
                                <i class="fab fa-twitter"></i>
                                <input type="text" id="twitter" name="twitter_handle" placeholder="@username" maxlength="50">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="github">GitHub URL <span class="optional">(Optional)</span></label>
                            <div class="input-with-icon">
                                <i class="fab fa-github"></i>
                                <input type="url" id="github" name="github_url" placeholder="https://github.com/mohatamimhaque/">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="facebook">Facebook URL <span class="optional">(Optional)</span></label>
                            <div class="input-with-icon">
                                <i class="fab fa-facebook"></i>
                                <input type="url" id="facebook" name="facebook_url" placeholder="https://www.facebook.com/mohatamim44">
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" id="prevBtn" onclick="changeStep(-1)" style="display: none;">
                            <i class="material-icons">arrow_back</i>
                            Previous
                        </button>
                        <button type="button" class="btn-primary" id="nextBtn" onclick="changeStep(1)">
                            Next
                            <i class="material-icons">arrow_forward</i>
                        </button>
                        <button type="submit" class="btn-success" id="submitBtn" style="display: none;">
                            <i class="material-icons">check</i>
                            Complete Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo $base_url; ?>public/js/complete-signup.js"></script>
<?php
include('includes/script.php');
include('includes/footer.php');
?>
