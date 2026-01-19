<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (!$is_logged_in) {
    redirect('login');
}

// Only students can access this dashboard
if (strtolower($_SESSION['role'] ?? '') !== 'student') {
    redirect('dashboard/' . strtolower($_SESSION['role'] ?? 'student'));
}

$user_data = get_user_by_id($con, $current_user_id);
$full_name = trim(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? ''));
$full_name = $full_name ?: 'Student';
$user_email = $user_data['email'] ?? '';
$user_avatar = $user_data['profile_image_url'] ?? '';
$avatar_url = $user_avatar ? $base_url . $user_avatar : 'https://ui-avatars.com/api/?name=' . urlencode($full_name) . '&background=6366f1&color=fff';

$enrollments_count = count_user_enrollments($con, $current_user_id);
$learning_hours = get_user_learning_hours($con, $current_user_id);

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | <?php echo $site_name; ?></title>
    
    <meta name="description" content="Track your learning progress, view certificates, and manage your courses on EduVerse">
    <meta name="robots" content="noindex, nofollow">
    
    <link rel="icon" type="image/svg+xml" href="<?php echo $base_url; ?>public/images/favicon.svg">
    
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="css/student-profile.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
   </head>
<body class="dashboard-page" data-theme="dark">
    <nav class="dashboard-nav">
        <div class="nav-container">
            <div class="nav-left">
                <button class="btn-icon btn-menu-toggle" id="btnMenuToggle">
                    <span class="material-icons">menu</span>
                </button>
                <a href="<?php echo $base_url; ?>" class="logo">
                    <span class="logo-icon"><span class="material-icons">school</span></span>
                    <span class="logo-text"><?php echo $site_name; ?></span>
                </a>
                <div class="search-bar">
                    <span class="material-icons">search</span>
                    <input type="text" placeholder="Search courses, lessons, or resources..." id="dashboardSearch">
                </div>
            </div>
            <div class="nav-right">
              
                <div class="notification-bell">
                    <button class="btn-icon" id="btnNotifications">
                        <span class="material-icons">notifications</span>
                        <span class="notification-badge" style="display: none;">0</span>
                    </button>
                </div>
                <div class="user-menu">
                    <button class="user-avatar" id="btnUserMenu">
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="<?php echo htmlspecialchars($full_name); ?>">
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-info">
                            <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="<?php echo htmlspecialchars($full_name); ?>">
                            <div>
                                <strong><?php echo htmlspecialchars($full_name); ?></strong>
                                <p><?php echo htmlspecialchars($user_email); ?></p>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="#profile" class="dropdown-item">
                            <span class="material-icons">person</span>
                            My Profile
                        </a>
                        <a href="#settings" class="dropdown-item">
                            <span class="material-icons">settings</span>
                            Settings
                        </a>
                        <a href="#help" class="dropdown-item">
                            <span class="material-icons">help</span>
                            Help & Support
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo $base_url; ?>logout" class="dropdown-item">
                            <span class="material-icons">logout</span>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <aside class="dashboard-sidebar" id="dashboardSidebar">
        <div class="sidebar-content">
            <div class="sidebar-section">
                <h4>Dashboard</h4>
                <a href="#overview" class="sidebar-link nav-item active" data-tab="overview">
                    <span class="material-icons">dashboard</span>
                    <span>Overview</span>
                </a>
                <a href="#my-learning" class="sidebar-link nav-item" data-tab="my-learning">
                    <span class="material-icons">school</span>
                    <span>My Learning</span>
                </a>
                <a href="#progress" class="sidebar-link nav-item" data-tab="progress">
                    <span class="material-icons">trending_up</span>
                    <span>Progress & Stats</span>
                </a>
                <a href="#certificates" class="sidebar-link nav-item" data-tab="certificates">
                    <span class="material-icons">workspace_premium</span>
                    <span>Certificates</span>
                    <span class="badge" id="certBadge">0</span>
                </a>
            </div>

            <div class="sidebar-section">
                <h4>Learning Tools</h4>
                <a href="#notes" class="sidebar-link nav-item" data-tab="notes">
                    <span class="material-icons">note</span>
                    <span>My Notes</span>
                </a>
            </div>
        </div>
    </aside>

    <main class="dashboard-main">
        <div class="dashboard-container">
            <div class="welcome-section">
                <div class="welcome-content">
                    <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?>! 👋</h1>
                    <p>Ready to continue your learning journey? You're making great progress!</p>
                </div>
                <div class="quick-stats">
                    <div class="stat-card stat-enrollments">
                        <span class="material-icons">school</span>
                        <div>
                            <strong id="statEnrollments"><?php echo $enrollments_count; ?></strong>
                            <span>Courses</span>
                        </div>
                    </div>
                    <div class="stat-card stat-hours">
                        <span class="material-icons">schedule</span>
                        <div>
                            <strong id="statHours"><?php echo $learning_hours; ?>h</strong>
                            <span>Learning Time</span>
                        </div>
                    </div>
                    <div class="stat-card stat-certificates">
                        <span class="material-icons">workspace_premium</span>
                        <div>
                            <strong id="statCertificates">0</strong>
                            <span>Certificates</span>
                        </div>
                    </div>
                </div>
            </div>

            <section class="dashboard-section">
                <div class="section-header">
                    <div class="section-title">
                        <span class="material-icons">auto_awesome</span>
                        <h2>Recommended For You</h2>
                    </div>
                    <p class="section-subtitle">Based on your learning patterns and interests</p>
                </div>
                <div class="recommendations-grid">
                    <!-- Dynamic content loaded via AJAX -->
                    <div class="loading-placeholder">
                        <div class="loading-spinner"></div>
                        <p>Loading recommendations...</p>
                    </div>
                </div>
            </section>

            <section class="dashboard-section">
                <div class="section-header">
                    <h2>Continue Learning</h2>
                </div>
                <div class="courses-continue-grid">
                    <!-- Dynamic content loaded via AJAX -->
                    <div class="loading-placeholder">
                        <div class="loading-spinner"></div>
                        <p>Loading your courses...</p>
                    </div>
                </div>
            </section>

            <section class="dashboard-section">
                <div class="section-header">
                    <h2>Your Learning Analytics</h2>
                    <div class="period-selector">
                        <button class="period-btn" data-period="week">Week</button>
                        <button class="period-btn active" data-period="month">Month</button>
                        <button class="period-btn" data-period="year">Year</button>
                    </div>
                </div>
                <div class="analytics-grid">
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3>Learning Activity</h3>
                            <span class="material-icons">show_chart</span>
                        </div>
                        <canvas id="activityChart"></canvas>
                    </div>
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3>Course Progress</h3>
                            <span class="material-icons">pie_chart</span>
                        </div>
                        <canvas id="progressChart"></canvas>
                    </div>
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3>Skills Analysis</h3>
                            <span class="material-icons">radar</span>
                        </div>
                        <canvas id="skillsChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="dashboard-section" id="streakSection">
                <div class="streak-section">
                    <!-- Dynamic streak content loaded via AJAX -->
                    <div class="loading-placeholder">
                        <div class="loading-spinner"></div>
                        <p>Loading your streak...</p>
                    </div>
                </div>
            </section>

            <section class="dashboard-section">
                <div class="activity-notifications-grid">
                    <div class="activity-card" id="activityList">
                        <h3>Recent Activity</h3>
                        <div class="activity-list">
                            <!-- Dynamic activity loaded via AJAX -->
                            <div class="loading-placeholder">
                                <div class="loading-spinner"></div>
                                <p>Loading activity...</p>
                            </div>
                        </div>
                    </div>

                    <div class="notifications-card" id="notificationsList">
                        <div class="notifications-header">
                            <h3>Notifications</h3>
                        </div>
                        <div class="notifications-list">
                            <!-- Dynamic notifications loaded via AJAX -->
                            <div class="loading-placeholder">
                                <div class="loading-spinner"></div>
                                <p>Loading notifications...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="dashboard-section tab-content" id="my-learning" style="display: none;">
                <div class="section-header">
                    <h2>My Learning</h2>
                    <div class="filter-tabs">
                        <button class="filter-tab active" data-filter="all">All Courses</button>
                        <button class="filter-tab" data-filter="in-progress">In Progress</button>
                        <button class="filter-tab" data-filter="completed">Completed</button>
                        <button class="filter-tab" data-filter="wishlist">Wishlist</button>
                    </div>
                </div>
                
                <div class="courses-grid" id="myCoursesGrid">
                    <!-- Dynamic content loaded via AJAX -->
                    <div class="loading-placeholder">
                        <div class="loading-spinner"></div>
                        <p>Loading your courses...</p>
                    </div>
                </div>
            </section>

            <section class="dashboard-section tab-content" id="progress" style="display: none;">
                <div class="section-header">
                    <h2>Progress & Statistics</h2>
                    <p class="section-subtitle">Track your learning journey and progress</p>
                </div>

                <div class="stats-overview-grid" id="progressStatsGrid">
                    <!-- Dynamic stats loaded via AJAX -->
                    <div class="loading-placeholder">
                        <div class="loading-spinner"></div>
                        <p>Loading statistics...</p>
                    </div>
                </div>

                <div class="progress-details-grid" id="progressDetailsGrid">
                    <!-- Dynamic progress details loaded via AJAX -->
                </div>
            </section>

            <section class="dashboard-section tab-content" id="certificates" style="display: none;">
                <div class="section-header">
                    <h2>My Certificates</h2>
                    <p class="section-subtitle">Download and share your earned certificates</p>
                </div>

                <div class="certificates-grid" id="certificatesGrid">
                    <!-- Dynamic certificates loaded via AJAX -->
                    <div class="loading-placeholder">
                        <div class="loading-spinner"></div>
                        <p>Loading certificates...</p>
                    </div>
                </div>
            </section>

            <section class="dashboard-section tab-content" id="notes" style="display: none;">
                <div class="section-header">
                    <h2>My Notes</h2>
                    <button class="btn btn-primary" id="btnNewNote">
                        <span class="material-icons">add</span>
                        New Note
                    </button>
                </div>

                <div class="notes-toolbar">
                    <div class="notes-search">
                        <span class="material-icons">search</span>
                        <input type="text" placeholder="Search notes..." class="notes-search-input" id="notesSearchInput">
                    </div>
                    <div class="notes-filters">
                        <button class="note-filter-btn active" data-filter="all">All Notes</button>
                        <button class="note-filter-btn" data-filter="recent">Recent</button>
                        <button class="note-filter-btn" data-filter="starred">Starred</button>
                        <button class="note-filter-btn" data-filter="archived">Archived</button>
                    </div>
                    <select class="notes-sort" id="notesSort">
                        <option value="modified">Last Modified</option>
                        <option value="created">Date Created</option>
                        <option value="title">Title</option>
                    </select>
                </div>

                <div class="notes-grid" id="notesGrid">
                    <!-- Dynamic notes loaded via AJAX -->
                    <div class="loading-placeholder">
                        <div class="loading-spinner"></div>
                        <p>Loading notes...</p>
                    </div>
                </div>
            </section>

            <section class="dashboard-section tab-content" id="notifications-tab" style="display: none;">
                <div class="section-header">
                    <h2>Notifications</h2>
                    <button class="btn btn-secondary" id="btnMarkAllRead">
                        <span class="material-icons">done_all</span>
                        Mark All as Read
                    </button>
                </div>

                <div class="notifications-container" id="notificationsFullList">
                    <!-- Dynamic notifications loaded via AJAX -->
                    <div class="loading-placeholder">
                        <div class="loading-spinner"></div>
                        <p>Loading notifications...</p>
                    </div>
                </div>
            </section>

            <section class="dashboard-section" id="profile" style="display: none;">
                <div class="section-header">
                    <h2>My Profile</h2>
                    <button class="btn btn-primary" id="btnEditProfile">
                        <span class="material-icons">edit</span>
                        Edit Profile
                    </button>
                </div>
                
                <div class="profile-container">
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="profile-avatar-section">
                                <div class="profile-avatar-large">
                                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="<?php echo htmlspecialchars($full_name); ?>" id="profileAvatar">
                                    <button class="btn-upload-avatar" id="btnUploadAvatar">
                                        <span class="material-icons">photo_camera</span>
                                    </button>
                                    <input type="file" id="avatarInput" accept="image/*" style="display: none;">
                                </div>
                                <div class="profile-header-info">
                                    <h2 id="profileDisplayName"><?php echo htmlspecialchars($full_name); ?></h2>
                                    <p id="profileDisplayEmail"><?php echo htmlspecialchars($user_email); ?></p>
                                    <p class="profile-headline" id="profileDisplayHeadline"><?php echo htmlspecialchars($user_data['headline'] ?? $user_data['occupation'] ?? 'Student'); ?></p>
                                    <p class="profile-location" id="profileDisplayLocation" style="<?php echo ($user_data['location'] || $user_data['country']) ? '' : 'display:none;'; ?>">
                                        <span class="material-icons">location_on</span>
                                        <?php echo htmlspecialchars(implode(', ', array_filter([$user_data['location'] ?? '', $user_data['country'] ?? '']))); ?>
                                    </p>
                                    <div class="profile-badges">
                                        <?php if ($user_data['email_verified']): ?>
                                        <span class="badge badge-primary">
                                            <span class="material-icons">verified</span>
                                            Verified Student
                                        </span>
                                        <?php endif; ?>
                                        <?php if ($enrollments_count >= 5): ?>
                                        <span class="badge badge-secondary">
                                            <span class="material-icons">emoji_events</span>
                                            Active Learner
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="profile-stats-grid">
                            <div class="profile-stat-card">
                                <span class="material-icons">school</span>
                                <div>
                                    <strong id="statCoursesEnrolled"><?php echo $enrollments_count; ?></strong>
                                    <span>Courses Enrolled</span>
                                </div>
                            </div>
                            <div class="profile-stat-card">
                                <span class="material-icons">check_circle</span>
                                <div>
                                    <strong id="statCoursesCompleted">0</strong>
                                    <span>Completed</span>
                                </div>
                            </div>
                            <div class="profile-stat-card">
                                <span class="material-icons">workspace_premium</span>
                                <div>
                                    <strong id="statCertificates">0</strong>
                                    <span>Certificates</span>
                                </div>
                            </div>
                            <div class="profile-stat-card">
                                <span class="material-icons">schedule</span>
                                <div>
                                    <strong id="statLearningHours"><?php echo $learning_hours; ?>h</strong>
                                    <span>Learning Time</span>
                                </div>
                            </div>
                            <div class="profile-stat-card">
                                <span class="material-icons">local_fire_department</span>
                                <div>
                                    <strong id="statStreakDays"><?php echo $user_data['streak_days'] ?? 0; ?></strong>
                                    <span>Day Streak</span>
                                </div>
                            </div>
                        </div>

                        <div class="profile-details">
                            <h3><span class="material-icons">person</span> Personal Information</h3>
                            <form id="profileForm" class="profile-form">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>First Name *</label>
                                        <input type="text" class="form-control" id="inputFirstName" value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>Last Name</label>
                                        <input type="text" class="form-control" id="inputLastName" value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Username</label>
                                        <input type="text" class="form-control" id="inputUsername" value="<?php echo htmlspecialchars($user_data['username'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>Email Address *</label>
                                        <input type="email" class="form-control" id="inputEmail" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Country Code</label>
                                        <select class="form-control" id="inputCountryCode" disabled>
                                            <option value="+880" <?php echo ($user_data['country_code'] ?? '') === '+880' ? 'selected' : ''; ?>>+880 (BD)</option>
                                            <option value="+1" <?php echo ($user_data['country_code'] ?? '') === '+1' ? 'selected' : ''; ?>>+1 (US)</option>
                                            <option value="+44" <?php echo ($user_data['country_code'] ?? '') === '+44' ? 'selected' : ''; ?>>+44 (UK)</option>
                                            <option value="+91" <?php echo ($user_data['country_code'] ?? '') === '+91' ? 'selected' : ''; ?>>+91 (IN)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Phone Number</label>
                                        <input type="tel" class="form-control" id="inputPhone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Headline</label>
                                    <input type="text" class="form-control" id="inputHeadline" value="<?php echo htmlspecialchars($user_data['headline'] ?? ''); ?>" placeholder="e.g., Web Developer | Student" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Bio</label>
                                    <textarea class="form-control" id="inputBio" rows="3" placeholder="Tell us about yourself..." readonly><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Occupation</label>
                                        <input type="text" class="form-control" id="inputOccupation" value="<?php echo htmlspecialchars($user_data['occupation'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>Company/School</label>
                                        <input type="text" class="form-control" id="inputCompany" value="<?php echo htmlspecialchars($user_data['company'] ?? ''); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Location</label>
                                        <input type="text" class="form-control" id="inputLocation" value="<?php echo htmlspecialchars($user_data['location'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>Country</label>
                                        <input type="text" class="form-control" id="inputCountry" value="<?php echo htmlspecialchars($user_data['country'] ?? 'Bangladesh'); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Date of Birth</label>
                                        <input type="date" class="form-control" id="inputDateOfBirth" value="<?php echo htmlspecialchars($user_data['date_of_birth'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>Gender</label>
                                        <select class="form-control" id="inputGender" disabled>
                                            <option value="">Select Gender</option>
                                            <option value="male" <?php echo ($user_data['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($user_data['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Blood Group</label>
                                        <select class="form-control" id="inputBloodGroup" disabled>
                                            <option value="">Select Blood Group</option>
                                            <option value="A+" <?php echo ($user_data['blood_group'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                                            <option value="A-" <?php echo ($user_data['blood_group'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                                            <option value="B+" <?php echo ($user_data['blood_group'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                                            <option value="B-" <?php echo ($user_data['blood_group'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                                            <option value="AB+" <?php echo ($user_data['blood_group'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                            <option value="AB-" <?php echo ($user_data['blood_group'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                            <option value="O+" <?php echo ($user_data['blood_group'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                                            <option value="O-" <?php echo ($user_data['blood_group'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Member Since</label>
                                        <input type="text" class="form-control" id="inputMemberSince" value="<?php echo date('F Y', strtotime($user_data['created_at'] ?? 'now')); ?>" readonly>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Timezone</label>
                                        <select class="form-control" id="inputTimezone" disabled>
                                            <option value="Asia/Dhaka" <?php echo ($user_data['timezone'] ?? '') === 'Asia/Dhaka' ? 'selected' : ''; ?>>Asia/Dhaka (GMT+6)</option>
                                            <option value="America/New_York" <?php echo ($user_data['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York (EST)</option>
                                            <option value="America/Los_Angeles" <?php echo ($user_data['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>America/Los_Angeles (PST)</option>
                                            <option value="Europe/London" <?php echo ($user_data['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London (GMT)</option>
                                            <option value="Asia/Tokyo" <?php echo ($user_data['timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : ''; ?>>Asia/Tokyo (JST)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Language</label>
                                        <select class="form-control" id="inputLanguage" disabled>
                                            <option value="en" <?php echo ($user_data['language'] ?? '') === 'en' ? 'selected' : ''; ?>>English</option>
                                            <option value="bn" <?php echo ($user_data['language'] ?? '') === 'bn' ? 'selected' : ''; ?>>Bengali</option>
                                            <option value="es" <?php echo ($user_data['language'] ?? '') === 'es' ? 'selected' : ''; ?>>Spanish</option>
                                            <option value="fr" <?php echo ($user_data['language'] ?? '') === 'fr' ? 'selected' : ''; ?>>French</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-actions" style="display: none;" id="profileFormActions">
                                    <button type="button" class="btn btn-secondary" id="btnCancelEdit">Cancel</button>
                                    <button type="submit" class="btn btn-primary" id="btnSaveProfile">Save Changes</button>
                                </div>
                            </form>
                        </div>

                        <div class="social-links-section">
                            <div class="social-links-header">
                                <h3><span class="material-icons">share</span> Social Links</h3>
                                <button class="btn btn-secondary btn-sm" id="btnEditSocialLinks">
                                    <span class="material-icons">edit</span>
                                    Edit
                                </button>
                            </div>
                            <div id="socialLinksContainer">
                                <p class="no-social-links">No social links added yet</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="profile-modal" id="passwordModal">
                <div class="modal-backdrop"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Change Password</h3>
                        <button class="modal-close"><span class="material-icons">close</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" class="form-control" id="currentPassword" placeholder="Enter current password">
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" class="form-control" id="newPassword" placeholder="Enter new password">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" class="form-control" id="confirmPassword" placeholder="Confirm new password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary modal-close">Cancel</button>
                        <button class="btn btn-primary" id="btnSavePassword">Change Password</button>
                    </div>
                </div>
            </div>

            <div class="profile-modal" id="socialLinksModal">
                <div class="modal-backdrop"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Edit Social Links</h3>
                        <button class="modal-close"><span class="material-icons">close</span></button>
                    </div>
                    <div class="modal-body">
                        <div id="socialLinksEditContainer"></div>
                        <button type="button" class="btn btn-secondary btn-sm" id="btnAddSocialLink" style="margin-top: 12px;">
                            <span class="material-icons">add</span>
                            Add Link
                        </button>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary modal-close">Cancel</button>
                        <button class="btn btn-primary" id="btnSaveSocialLinks">Save Links</button>
                    </div>
                </div>
            </div>

            <!-- Note Create/Edit Modal -->
            <div class="profile-modal" id="noteModal">
                <div class="modal-backdrop"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="noteModalTitle">New Note</h3>
                        <button class="modal-close"><span class="material-icons">close</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Title *</label>
                            <input type="text" class="form-control" id="noteTitle" placeholder="Enter note title">
                        </div>
                        <div class="form-group">
                            <label>Course *</label>
                            <select class="form-control" id="noteCourse" required>
                                <option value="">Select a course (loading...)</option>
                            </select>
                            <small class="form-text text-muted">Select the course this note is related to</small>
                        </div>
                        <div class="form-group">
                            <label>Content *</label>
                            <textarea class="form-control" id="noteContent" rows="8" placeholder="Write your note here..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary modal-close">Cancel</button>
                        <button class="btn btn-primary" id="btnSaveNote">Save Note</button>
                    </div>
                </div>
            </div>

            <!-- Note Delete Confirmation Modal -->
            <div class="profile-modal" id="noteDeleteModal">
                <div class="modal-backdrop"></div>
                <div class="modal-content modal-small">
                    <div class="modal-header">
                        <h3>Delete Note</h3>
                        <button class="modal-close"><span class="material-icons">close</span></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this note? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary modal-close">Cancel</button>
                        <button class="btn btn-danger" id="btnConfirmDeleteNote">Delete</button>
                    </div>
                </div>
            </div>

            <!-- Delete Account Confirmation Modal -->
            <div class="profile-modal" id="deleteAccountModal">
                <div class="modal-backdrop"></div>
                <div class="modal-content modal-small">
                    <div class="modal-header">
                        <h3><span class="material-icons" style="color: var(--error-color);">warning</span> Delete Account</h3>
                        <button class="modal-close"><span class="material-icons">close</span></button>
                    </div>
                    <div class="modal-body">
                        <p style="color: var(--error-color); font-weight: 500;">This action is permanent and cannot be undone!</p>
                        <p>All your data including courses, notes, certificates, and progress will be permanently deleted.</p>
                        <div class="form-group" style="margin-top: 16px;">
                            <label>Enter your password to confirm:</label>
                            <input type="password" class="form-control" id="deleteAccountPassword" placeholder="Your password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary modal-close">Cancel</button>
                        <button class="btn btn-danger" id="btnConfirmDeleteAccount">Delete My Account</button>
                    </div>
                </div>
            </div>

            <section class="dashboard-section" id="settings" style="display: none;">
                <div class="section-header">
                    <h2>Settings</h2>
                    <p class="section-subtitle">Manage your account preferences and settings</p>
                </div>

                <div class="settings-container">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <span class="material-icons">account_circle</span>
                            <h3>Account Settings</h3>
                        </div>
                        <div class="settings-card-body">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Change Password</h4>
                                    <p>Update your password regularly to keep your account secure</p>
                                </div>
                                <button class="btn btn-secondary" id="btnChangePassword">Change Password</button>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Email Verification</h4>
                                    <p><?php echo htmlspecialchars($user_email); ?> - <?php echo $user_data['email_verified'] ? 'Verified' : 'Not Verified'; ?></p>
                                </div>
                                <?php if ($user_data['email_verified']): ?>
                                <span class="badge badge-success">
                                    <span class="material-icons">check_circle</span>
                                    Verified
                                </span>
                                <?php else: ?>
                                <button class="btn btn-secondary btn-sm">Verify Email</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card settings-danger">
                        <div class="settings-card-header">
                            <span class="material-icons">warning</span>
                            <h3>Danger Zone</h3>
                        </div>
                        <div class="settings-card-body">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Delete Account</h4>
                                    <p>Permanently delete your account and all associated data</p>
                                </div>
                                <button class="btn btn-danger" id="btnDeleteAccount">Delete Account</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="dashboard-section" id="help" style="display: none;">
                <div class="section-header">
                    <h2>Help & Support</h2>
                    <p class="section-subtitle">Get answers to your questions and learn how to use EduVerse</p>
                </div>

                <div class="help-container">
                    <div class="quick-help-grid">
                        <div class="help-card">
                            <div class="help-card-icon">
                                <span class="material-icons">school</span>
                            </div>
                            <h3>Getting Started</h3>
                            <p>Learn the basics of using EduVerse platform</p>
                            <button class="btn btn-secondary btn-sm" onclick="showHelpArticle('getting-started')">Learn More</button>
                        </div>
                        <div class="help-card">
                            <div class="help-card-icon">
                                <span class="material-icons">play_circle</span>
                            </div>
                            <h3>Taking Courses</h3>
                            <p>How to enroll and complete courses effectively</p>
                            <button class="btn btn-secondary btn-sm" onclick="showHelpArticle('taking-courses')">Learn More</button>
                        </div>
                        <div class="help-card">
                            <div class="help-card-icon">
                                <span class="material-icons">workspace_premium</span>
                            </div>
                            <h3>Certificates</h3>
                            <p>Earn and download your course certificates</p>
                            <button class="btn btn-secondary btn-sm" onclick="showHelpArticle('certificates')">Learn More</button>
                        </div>
                        <div class="help-card">
                            <div class="help-card-icon">
                                <span class="material-icons">payment</span>
                            </div>
                            <h3>Payments & Billing</h3>
                            <p>Manage subscriptions and payment methods</p>
                            <button class="btn btn-secondary btn-sm" onclick="showHelpArticle('billing')">Learn More</button>
                        </div>
                    </div>

                    <div class="help-search-section">
                        <h3>Search for Help</h3>
                        <div class="help-search-bar">
                            <span class="material-icons">search</span>
                            <input type="text" placeholder="Search help articles..." id="helpSearchInput">
                            <button class="btn btn-primary" id="btnHelpSearch">Search</button>
                        </div>
                    </div>

                    <div class="faq-section">
                        <h3>Frequently Asked Questions</h3>
                        <div class="faq-list">
                            <div class="faq-item">
                                <button class="faq-question">
                                    <span>How do I enroll in a course?</span>
                                    <span class="material-icons">expand_more</span>
                                </button>
                                <div class="faq-answer">
                                    <p>To enroll in a course:</p>
                                    <ol>
                                        <li>Browse courses from the catalog or search for specific topics</li>
                                        <li>Click on a course to view its details</li>
                                        <li>Click the "Enroll Now" button</li>
                                        <li>Complete the payment process if it's a paid course</li>
                                        <li>Start learning immediately after enrollment</li>
                                    </ol>
                                </div>
                            </div>
                            <div class="faq-item">
                                <button class="faq-question">
                                    <span>How do I track my learning progress?</span>
                                    <span class="material-icons">expand_more</span>
                                </button>
                                <div class="faq-answer">
                                    <p>You can track your progress in several ways:</p>
                                    <ul>
                                        <li>View your dashboard for an overview of all courses</li>
                                        <li>Check the progress bar on each course card</li>
                                        <li>Access detailed analytics in the "My Learning" section</li>
                                        <li>Receive weekly progress reports via email</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="faq-item">
                                <button class="faq-question">
                                    <span>Can I download course videos for offline viewing?</span>
                                    <span class="material-icons">expand_more</span>
                                </button>
                                <div class="faq-answer">
                                    <p>Yes! Premium subscribers can download course videos:</p>
                                    <ol>
                                        <li>Open the course you want to download</li>
                                        <li>Click the download icon next to each video</li>
                                        <li>Videos will be available in the mobile app for offline viewing</li>
                                        <li>Downloaded content expires after 30 days</li>
                                    </ol>
                                </div>
                            </div>
                            <div class="faq-item">
                                <button class="faq-question">
                                    <span>How do I get a certificate after completing a course?</span>
                                    <span class="material-icons">expand_more</span>
                                </button>
                                <div class="faq-answer">
                                    <p>Certificates are automatically generated when you:</p>
                                    <ul>
                                        <li>Complete 100% of the course lectures</li>
                                        <li>Pass all required quizzes and assignments</li>
                                        <li>Achieve the minimum passing grade (if applicable)</li>
                                    </ul>
                                    <p>Once earned, you can download, print, or share your certificate from the "Certificates" section.</p>
                                </div>
                            </div>
                            <div class="faq-item">
                                <button class="faq-question">
                                    <span>What payment methods do you accept?</span>
                                    <span class="material-icons">expand_more</span>
                                </button>
                                <div class="faq-answer">
                                    <p>We accept the following payment methods:</p>
                                    <ul>
                                        <li>Credit and Debit Cards (Visa, Mastercard, American Express)</li>
                                        <li>PayPal</li>
                                        <li>Bank Transfer (for enterprise plans)</li>
                                        <li>Mobile Wallets (Apple Pay, Google Pay)</li>
                                    </ul>
                                    <p>All transactions are secured with 256-bit SSL encryption.</p>
                                </div>
                            </div>
                            <div class="faq-item">
                                <button class="faq-question">
                                    <span>Can I get a refund if I'm not satisfied?</span>
                                    <span class="material-icons">expand_more</span>
                                </button>
                                <div class="faq-answer">
                                    <p>Yes! We offer a 30-day money-back guarantee:</p>
                                    <ul>
                                        <li>Request a refund within 30 days of purchase</li>
                                        <li>Contact support with your order details</li>
                                        <li>Refunds are processed within 5-7 business days</li>
                                        <li>You must have completed less than 30% of the course</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="contact-support-section">
                        <h3>Still Need Help?</h3>
                        <p>Can't find what you're looking for? Our support team is here to help!</p>
                        <div class="contact-options-grid">
                            <div class="contact-option">
                                <span class="material-icons">email</span>
                                <h4>Email Support</h4>
                                <p>support@eduverse.com</p>
                                <p class="response-time">Response within 24 hours</p>
                            </div>
                            <div class="contact-option">
                                <span class="material-icons">chat</span>
                                <h4>Live Chat</h4>
                                <p>Chat with our team</p>
                                <p class="response-time">Available Mon-Fri, 9AM-6PM</p>
                                <button class="btn btn-primary btn-sm" id="btnStartChat">Start Chat</button>
                            </div>
                        </div>
                    </div>

                    <div class="video-tutorials-section">
                        <h3>Video Tutorials</h3>
                        <div class="tutorials-grid">
                            <div class="tutorial-card">
                                <div class="tutorial-thumbnail">
                                    <div class="thumbnail-placeholder gradient-1"></div>
                                    <span class="play-icon">
                                        <span class="material-icons">play_circle</span>
                                    </span>
                                    <span class="video-duration">5:32</span>
                                </div>
                                <h4>Platform Overview</h4>
                                <p>Get a quick tour of EduVerse features</p>
                            </div>
                            <div class="tutorial-card">
                                <div class="tutorial-thumbnail">
                                    <div class="thumbnail-placeholder gradient-2"></div>
                                    <span class="play-icon">
                                        <span class="material-icons">play_circle</span>
                                    </span>
                                    <span class="video-duration">8:15</span>
                                </div>
                                <h4>Course Navigation</h4>
                                <p>Learn how to navigate through courses</p>
                            </div>
                            <div class="tutorial-card">
                                <div class="tutorial-thumbnail">
                                    <div class="thumbnail-placeholder gradient-3"></div>
                                    <span class="play-icon">
                                        <span class="material-icons">play_circle</span>
                                    </span>
                                    <span class="video-duration">6:47</span>
                                </div>
                                <h4>Using AI Assistant</h4>
                                <p>Get the most out of our AI learning assistant</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Pass PHP base URL to JavaScript
        window.EDUVERSE_BASE_URL = '<?php echo $base_url; ?>';
    </script>
    <script src="<?php echo $base_url.'dashboard/student/js/profile-handler.js?v='.time() ?>"></script>
    <script src="<?php echo $base_url.'dashboard/student/js/student-profile.js?v='.time() ?>"></script>
    <script src="<?php echo $base_url.'dashboard/student/js/student-dashboard.js?v='.time() ?>"></script>
</body>
</html>