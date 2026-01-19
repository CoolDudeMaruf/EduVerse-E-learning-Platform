<?php
require_once '../../includes/config.php';

if (!$is_logged_in) {
    redirect('login');
}

if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'instructor') {
    redirect('dashboard');
}

$user_data = get_user_by_id($con, $current_user_id);
$full_name = trim(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? ''));
$full_name = $full_name ?: 'Mohatamim';
$user_email = $user_data['email'] ?? 'mohatamimhaque7@gmail.com';
$user_phone = $user_data['phone'] ?? '01518749114';
$user_avatar = $user_data['profile_image_url'] ?? '';
$avatar_url = $user_avatar ? $base_url . $user_avatar : 'https://ui-avatars.com/api/?name=' . urlencode($full_name) . '&background=6366f1&color=fff';

$courses_query = "SELECT c.*, cat.name as category_name,
                  (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id) as student_count,
                  (SELECT COUNT(*) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) as review_count
                  FROM courses c 
                  LEFT JOIN categories cat ON c.category_id = cat.category_id 
                  WHERE c.instructor_id = '$current_user_id'
                  ORDER BY c.created_at DESC";
$courses_result = mysqli_query($con, $courses_query);
$courses = [];
$total_students = 0;
$total_courses = 0;
$total_published = 0;
if ($courses_result) {
    while ($row = mysqli_fetch_assoc($courses_result)) {
        $courses[] = $row;
        $total_courses++;
        $total_students += $row['student_count'];
        if ($row['status'] == 'published') {
            $total_published++;
        }
    }
}

// Calculate total earnings from enrollments (using course price instead of payments table)
$earnings_query = "SELECT COALESCE(SUM(c.price), 0) as total_earnings 
                   FROM enrollments e 
                   INNER JOIN courses c ON e.course_id = c.course_id
                   WHERE c.instructor_id = '$current_user_id' 
                   AND c.is_free = 0 
                   AND c.price > 0";
$earnings_result = mysqli_query($con, $earnings_query);
$total_earnings = 0;
if ($earnings_result) {
    $earnings_row = mysqli_fetch_assoc($earnings_result);
    $total_earnings = $earnings_row['total_earnings'] ?? 0;
}

$rating_query = "SELECT AVG(r.rating) as avg_rating 
                 FROM reviews r 
                 INNER JOIN courses c ON r.course_id = c.course_id 
                 WHERE c.instructor_id = '$current_user_id'";
$rating_result = mysqli_query($con, $rating_query);
$avg_rating = '0.0';
if ($rating_result) {
    $rating_row = mysqli_fetch_assoc($rating_result);
    $avg_rating = $rating_row['avg_rating'] ? number_format($rating_row['avg_rating'], 1) : '0.0';
}

$categories_query = "SELECT category_id, name FROM categories ORDER BY name";
$categories_result = mysqli_query($con, $categories_query);
$categories = [];
if ($categories_result) {
    while ($row = mysqli_fetch_assoc($categories_result)) {
        $categories[] = $row;
    }
}

// Fetch social links
$social_links_query = "SELECT platform, url FROM user_social_links WHERE user_id = '$current_user_id' AND is_public = 1";
$social_links_result = mysqli_query($con, $social_links_query);
$social_links = [];
if ($social_links_result) {
    while ($row = mysqli_fetch_assoc($social_links_result)) {
        $social_links[$row['platform']] = $row['url'];
    }
}

// Get unread notification count
$unread_notif_count = 0;

// Count from notifications table
$unread_query = "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = '$current_user_id' AND is_read = 0";
$unread_result = mysqli_query($con, $unread_query);
if ($unread_result) {
    $row = mysqli_fetch_assoc($unread_result);
    $unread_notif_count += $row['cnt'] ?? 0;
}

// Also count recent enrollments not marked as read (last 30 days)
$month_ago = date('Y-m-d', strtotime('-30 days'));
$enroll_unread_query = "SELECT COUNT(*) as cnt FROM enrollments e 
                        INNER JOIN courses c ON e.course_id = c.course_id 
                        WHERE c.instructor_id = '$current_user_id' 
                        AND e.enrollment_date >= '$month_ago'
                        AND NOT EXISTS (
                            SELECT 1 FROM notification_reads nr 
                            WHERE nr.notification_id = CONCAT('enroll_', e.enrollment_id) 
                            AND nr.user_id = '$current_user_id'
                        )
                        AND NOT EXISTS (
                            SELECT 1 FROM notification_deletions nd 
                            WHERE nd.notification_id = CONCAT('enroll_', e.enrollment_id) 
                            AND nd.user_id = '$current_user_id'
                        )";
$enroll_unread_result = mysqli_query($con, $enroll_unread_query);
if ($enroll_unread_result) {
    $row = mysqli_fetch_assoc($enroll_unread_result);
    $unread_notif_count += $row['cnt'] ?? 0;
}

// Count recent reviews not marked as read
$review_unread_query = "SELECT COUNT(*) as cnt FROM reviews r 
                        INNER JOIN courses c ON r.course_instructor_id = c.course_id 
                        WHERE c.instructor_id = '$current_user_id' 
                        AND r.created_at >= '$month_ago'
                        AND r.is_published = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM notification_reads nr 
                            WHERE nr.notification_id = CONCAT('review_', r.review_id) 
                            AND nr.user_id = '$current_user_id'
                        )
                        AND NOT EXISTS (
                            SELECT 1 FROM notification_deletions nd 
                            WHERE nd.notification_id = CONCAT('review_', r.review_id) 
                            AND nd.user_id = '$current_user_id'
                        )";
$review_unread_result = mysqli_query($con, $review_unread_query);
if ($review_unread_result) {
    $row = mysqli_fetch_assoc($review_unread_result);
    $unread_notif_count += $row['cnt'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard - EduVerse</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="<?php echo $base_url; ?>public/images/favicon.svg">
    <link rel="stylesheet" href="css/instructor-dashboard.css">
    <!-- TinyMCE Rich Text Editor -->
    <script src="https://cdn.tiny.cloud/1/p96i0nowt4gkiybhevy4k7kb1b27bflnn5wy7o8bkkber932/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>

<body>
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <span class="material-icons">menu</span>
    </button>

    <div class="nav-backdrop" id="navBackdrop"></div>

    <nav class="dashboard-nav" id="dashboardNav">
        <div class="nav-brand">
            <span class="material-icons">school</span>
            <span class="brand-text">EduVerse</span>
        </div>
        <div class="nav-menu">
            <div class="nav-group">
                <div class="nav-section-title">
                    <span class="material-icons">layers</span>
                    Main
                </div>
                <a href="#overview" class="nav-item active">
                    <span class="material-icons">dashboard</span>
                    <span>Overview</span>
                    <span class="nav-badge">New</span>
                </a>
                <a href="#courses" class="nav-item">
                    <span class="material-icons">video_library</span>
                    <span>My Courses</span>
                    <span class="nav-count"><?php echo $total_courses; ?></span>
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-section-title">
                    <span class="material-icons">tune</span>
                    Manage
                </div>
                <a href="#students" class="nav-item">
                    <span class="material-icons">people</span>
                    <span>Students</span>
                    <span class="nav-count"><?php echo number_format($total_students); ?></span>
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-section-title">
                    <span class="material-icons">trending_up</span>
                    Growth
                </div>
                <a href="#revenue" class="nav-item">
                    <span class="material-icons">attach_money</span>
                    <span>Revenue</span>
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-section-title">
                    <span class="material-icons">account_circle</span>
                    Account
                </div>
                <a href="#profile" class="nav-item">
                    <span class="material-icons">person</span>
                    <span>Profile</span>
                </a>
                <a href="#settings" class="nav-item">
                    <span class="material-icons">settings</span>
                    <span>Settings</span>
                </a>
                <a href="#notifications" class="nav-item">
                    <span class="material-icons">notifications</span>
                    <span>Notifications</span>
                    <span class="nav-notification" <?php echo $unread_notif_count == 0 ? 'style="display:none;"' : ''; ?>><?php echo $unread_notif_count; ?></span>
                </a>
            </div>
        </div>
        <div class="nav-footer">
            <div class="user-profile">
                <div style="position: relative;">
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile" class="profile-img">
                    <span class="online-indicator"></span>
                </div>
                <div class="profile-info">
                    <span class="profile-name"><?php echo htmlspecialchars($full_name); ?></span>
                    <span class="profile-role">Instructor</span>
                </div>
            </div>
        </div>
    </nav>

    <main class="dashboard-main">
        <section id="overview" class="dashboard-section active">
            <div class="section-header">
                <h1>Dashboard Overview</h1>
                <button class="btn-primary" onclick="showCreateCourseModal()">
                    <span class="material-icons">add</span>
                    Create New Course
                </button>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #4CAF50;">
                        <span class="material-icons">school</span>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value" id="totalStudents"><?php echo number_format($total_students); ?></span>
                        <span class="stat-label">Total Students</span>
                        <span class="stat-change positive">+12% this month</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #2196F3;">
                        <span class="material-icons">video_library</span>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value" id="totalCourses"><?php echo $total_published; ?></span>
                        <span class="stat-label">Active Courses</span>
                        <span class="stat-change positive">+2 this month</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #FF9800;">
                        <span class="material-icons">attach_money</span>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value" id="totalEarnings">৳<?php echo number_format($total_earnings, 2); ?></span>
                        <span class="stat-label">Total Earnings</span>
                        <span class="stat-change positive">+18% this month</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #9C27B0;">
                        <span class="material-icons">star</span>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value" id="avgRating"><?php echo $avg_rating; ?></span>
                        <span class="stat-label">Average Rating</span>
                        <span class="stat-change positive">+0.3 this month</span>
                    </div>
                </div>
            </div>

            <div class="charts-row">
                <div class="chart-card">
                    <h3>Student Enrollment Trends</h3>
                    <canvas id="enrollmentChart"></canvas>
                </div>
                <div class="chart-card">
                    <h3>Revenue Overview</h3>
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <div class="activity-section">
                <h2>Recent Activity</h2>
                <div class="activity-feed" id="activityFeed">
                </div>
            </div>
        </section>

        <section id="courses" class="dashboard-section">
            <div class="section-header">
                <h1>My Courses</h1>
                <div class="header-actions">
                    <div class="search-box">
                        <span class="material-icons">search</span>
                        <input type="text" placeholder="Search courses..." id="courseSearch">
                    </div>
                    <select id="courseFilter" class="filter-select">
                        <option value="all">All Courses</option>
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
            </div>

            <div class="courses-grid" id="coursesGrid">
                <?php foreach ($courses as $course): 
                    $status_class = strtolower($course['status']);
                    $thumb = $course['thumbnail_url'] ? $base_url . $course['thumbnail_url'] : 'https://via.placeholder.com/300x200?text=' . urlencode($course['title']);
                ?>
                <div class="course-card" data-status="<?php echo $status_class; ?>" data-course-id="<?php echo $course['course_id']; ?>">
                    <div class="course-thumbnail">
                        <img src="<?php echo htmlspecialchars($thumb); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>">
                        <span class="course-status <?php echo $status_class; ?>"><?php echo ucfirst($course['status']); ?></span>
                    </div>
                    <div class="course-content">
                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                        <div class="course-meta">
                            <span><span class="material-icons">people</span> <?php echo $course['student_count'] ?? 0; ?> students</span>
                            <span><span class="material-icons">rate_review</span> <?php echo $course['review_count'] ?? 0; ?> reviews</span>
                        </div>
                        <div class="course-stats">
                            <span><?php echo $course['total_sections'] ?? 0; ?> sections</span>
                            <span>•</span>
                            <span><?php echo $course['total_lectures'] ?? 0; ?> lectures</span>
                        </div>
                        <div class="course-actions">
                            <button class="btn-secondary" onclick="editCourse('<?php echo $course['course_id']; ?>')">
                                <span class="material-icons">edit</span> Edit
                            </button>
                            <button class="btn-primary" onclick="manageCourse('<?php echo $course['course_id']; ?>')">
                                <span class="material-icons">settings</span> Manage
                            </button>
                            <button class="btn-danger" onclick="deleteCourse('<?php echo $course['course_id']; ?>', '<?php echo htmlspecialchars($course['title']); ?>')">
                                <span class="material-icons">delete</span> Delete
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($courses)): ?>
                <div class="empty-state">
                    <span class="material-icons">video_library</span>
                    <h3>No courses yet</h3>
                    <p>Create your first course to get started</p>
                    <button class="btn-primary" onclick="showCreateCourseModal()">
                        <span class="material-icons">add</span>
                        Create Course
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </section>



        <section id="students" class="dashboard-section">
            <div class="section-header">
                <h1>Student Management</h1>
                <div class="header-actions">
                    <div class="search-box">
                        <span class="material-icons">search</span>
                        <input type="text" placeholder="Search students..." id="studentSearch">
                    </div>
                </div>
            </div>

            <div class="students-container">
                <div class="students-list" id="studentsList">
                    <!-- Students loaded dynamically -->
                </div>
                <div class="pagination-container" id="studentsPagination" style="display: none; margin-top: 24px; display: flex; justify-content: center; gap: 8px;">
                    <!-- Pagination loaded dynamically -->
                </div>
            </div>
        </section>

        <section id="revenue" class="dashboard-section">
            <div class="section-header">
                <h1>Revenue & Monetization</h1>
            </div>

            <div class="earnings-cards">
                <div class="earnings-card">
                    <h3>Total Earnings</h3>
                    <p class="earnings-amount" id="totalEarningsAmount">৳<?php echo number_format($total_earnings, 2); ?></p>
                    <span class="earnings-period">All time</span>
                </div>
                <div class="earnings-card">
                    <h3>This Month</h3>
                    <p class="earnings-amount" id="monthEarningsAmount">৳0.00</p>
                    <span class="earnings-change positive" id="monthEarningsChange">+0%</span>
                </div>
                <div class="earnings-card">
                    <h3>Pending Payout</h3>
                    <p class="earnings-amount" id="pendingPayoutAmount">৳0.00</p>
                    <span class="earnings-date" id="nextPayoutDate">Next: <?php echo date('M j, Y', strtotime('+15 days')); ?></span>
                </div>
            </div>

            <div class="revenue-chart-container">
                <h3>Revenue Trends</h3>
                <canvas id="revenueTrendChart"></canvas>
            </div>

            <div class="pricing-section">
                <div class="section-header">
                    <h2>Course Pricing Management</h2>
                </div>
                                
                <div class="pricing-filters">
                    <div class="filter-group">
                        <label for="pricingCourseFilter">Filter:</label>
                        <select id="pricingCourseFilter" onchange="filterPricingCourses()">
                            <option value="all">All Courses</option>
                            <option value="free">Free Courses</option>
                            <option value="paid">Paid Courses</option>
                            <option value="discounted">Discounted</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <input type="text" id="pricingSearchInput" placeholder="Search courses..." onkeyup="filterPricingCourses()">
                    </div>
                </div>

                <div class="pricing-list" id="pricingList">
                    <?php foreach ($courses as $course): 
                        $is_free = ($course['price'] == 0 || $course['is_free']);
                        $has_discount = !$is_free && $course['original_price'] && $course['original_price'] > $course['price'];
                        $discount_percent = $has_discount ? round((($course['original_price'] - $course['price']) / $course['original_price']) * 100) : 0;
                    ?>
                    <div class="pricing-item" data-course-id="<?php echo $course['course_id']; ?>" data-is-free="<?php echo $is_free ? '1' : '0'; ?>" data-has-discount="<?php echo $has_discount ? '1' : '0'; ?>">
                        <div class="pricing-info">
                            <h4><?php echo htmlspecialchars($course['title']); ?></h4>
                            <div class="pricing-meta">
                                <span class="pricing-status <?php echo strtolower($course['status']); ?>"><?php echo ucfirst($course['status']); ?></span>
                                <?php if ($is_free): ?>
                                    <span class="pricing-badge free">FREE</span>
                                <?php elseif ($has_discount): ?>
                                    <span class="pricing-badge discount"><?php echo $discount_percent; ?>% OFF</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="pricing-value">
                            <?php if ($is_free): ?>
                                <span class="current-price free-label">FREE</span>
                            <?php else: ?>
                                <div class="price-display">
                                    <span class="current-price">৳<?php echo number_format($course['price'], 2); ?></span>
                                    <?php if ($has_discount): ?>
                                    <span class="original-price">৳<?php echo number_format($course['original_price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="pricing-actions">
                            <button class="btn-icon" onclick="viewPricingHistory('<?php echo $course['course_id']; ?>')" title="Price History">
                                <span class="material-icons">history</span>
                            </button>
                            <button class="btn-secondary" onclick="editPricing('<?php echo $course['course_id']; ?>')">
                                <span class="material-icons">edit</span>
                                Edit Price
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="coupon-section">
                <div class="section-header">
                    <h2>Coupon Management</h2>
                    <button class="btn-primary" onclick="createCoupon()">
                        <span class="material-icons">local_offer</span>
                        Create Coupon
                    </button>
                </div>

                <div class="coupon-stats">
                    <div class="stat-card">
                        <span class="material-icons">confirmation_number</span>
                        <div>
                            <h3 id="totalCoupons">0</h3>
                            <p>Total Coupons</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="material-icons">check_circle</span>
                        <div>
                            <h3 id="activeCoupons">0</h3>
                            <p>Active Coupons</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="material-icons">redeem</span>
                        <div>
                            <h3 id="totalRedemptions">0</h3>
                            <p>Total Redemptions</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <span class="material-icons">savings</span>
                        <div>
                            <h3 id="totalDiscount">৳0</h3>
                            <p>Total Discount Given</p>
                        </div>
                    </div>
                </div>

                <div class="coupon-filters">
                    <div class="filter-group">
                        <label for="couponTypeFilter">Type:</label>
                        <select id="couponTypeFilter" onchange="filterCoupons()">
                            <option value="all">All Coupons</option>
                            <option value="overall">Overall (All Courses)</option>
                            <option value="specific">Specific Course</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="couponStatusFilter">Status:</label>
                        <select id="couponStatusFilter" onchange="filterCoupons()">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                            <option value="disabled">Disabled</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <input type="text" id="couponSearchInput" placeholder="Search coupon code..." onkeyup="filterCoupons()">
                    </div>
                </div>

                <div class="coupon-list" id="couponList">
                </div>
            </div>
        </section>

        <section id="profile" class="dashboard-section">
            <div class="section-header">
                <h1>My Profile</h1>
            </div>

            <div class="profile-container">
                <div class="profile-header-card">
                    <div class="profile-banner"></div>
                    <div class="profile-main-info">
                        <div class="profile-avatar-section">
                            <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile" class="profile-avatar-large">
                            <button class="btn-change-photo" onclick="changeProfilePhoto()">
                                <span class="material-icons">camera_alt</span>
                            </button>
                        </div>
                        <div class="profile-details">
                            <h2><?php echo htmlspecialchars($full_name); ?></h2>
                            <p class="profile-role-badge">Instructor</p>
                            <p class="profile-email"><?php echo htmlspecialchars($user_email); ?></p>
                            <div class="profile-stats">
                                <div class="stat-item">
                                    <span class="material-icons">school</span>
                                    <span><strong><?php echo number_format($total_students); ?></strong> Students</span>
                                </div>
                                <div class="stat-item">
                                    <span class="material-icons">video_library</span>
                                    <span><strong><?php echo $total_courses; ?></strong> Courses</span>
                                </div>
                                <div class="stat-item">
                                    <span class="material-icons">star</span>
                                    <span><strong><?php echo $avg_rating; ?></strong> Rating</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="profile-info-grid">
                    <div class="info-card">
                        <div class="info-card-header">
                            <h3><span class="material-icons">person</span> Personal Information</h3>
                            <button class="btn-icon" onclick="editSection('personal')">
                                <span class="material-icons">edit</span>
                            </button>
                        </div>
                        <div class="info-card-body">
                            <div class="info-row">
                                <span class="info-label">Full Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($full_name); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_email); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Phone</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_data['phone'] ?? 'Not set'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Location</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_data['location'] ?? 'Not set'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-card-header">
                            <h3><span class="material-icons">work</span> Professional Information</h3>
                            <button class="btn-icon" onclick="editSection('professional')">
                                <span class="material-icons">edit</span>
                            </button>
                        </div>
                        <div class="info-card-body">
                            <div class="info-row">
                                <span class="info-label">Title</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_data['title'] ?? 'Instructor'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Specialization</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_data['specialization'] ?? 'Not set'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Experience</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_data['experience'] ?? 'Not set'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Member Since</span>
                                <span class="info-value"><?php echo isset($user_data['created_at']) ? date('F Y', strtotime($user_data['created_at'])) : 'N/A'; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="info-card full-width">
                        <div class="info-card-header">
                            <h3><span class="material-icons">description</span> Biography</h3>
                            <button class="btn-icon" onclick="editSection('bio')">
                                <span class="material-icons">edit</span>
                            </button>
                        </div>
                        <div class="info-card-body">
                            <p class="bio-text"><?php echo htmlspecialchars($user_data['bio'] ?? 'No biography added yet.'); ?></p>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-card-header">
                            <h3><span class="material-icons">link</span> Social Links</h3>
                            <button class="btn-icon" onclick="editSection('social')">
                                <span class="material-icons">edit</span>
                            </button>
                        </div>
                        <div class="info-card-body">
                            <div class="social-links-list">
                                <?php if (!empty($social_links)): ?>
                                    <?php if (isset($social_links['website']) && !empty($social_links['website'])): ?>
                                    <a href="<?php echo htmlspecialchars($social_links['website']); ?>" class="social-link-item" target="_blank" rel="noopener noreferrer">
                                        <span class="material-icons">language</span>
                                        <span><?php echo htmlspecialchars(parse_url($social_links['website'], PHP_URL_HOST) ?: $social_links['website']); ?></span>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($social_links['linkedin']) && !empty($social_links['linkedin'])): ?>
                                    <a href="<?php echo htmlspecialchars($social_links['linkedin']); ?>" class="social-link-item" target="_blank" rel="noopener noreferrer">
                                        <span class="material-icons">link</span>
                                        <span>LinkedIn</span>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($social_links['github']) && !empty($social_links['github'])): ?>
                                    <a href="<?php echo htmlspecialchars($social_links['github']); ?>" class="social-link-item" target="_blank" rel="noopener noreferrer">
                                        <span class="material-icons">code</span>
                                        <span>GitHub</span>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($social_links['twitter']) && !empty($social_links['twitter'])): ?>
                                    <a href="<?php echo htmlspecialchars($social_links['twitter']); ?>" class="social-link-item" target="_blank" rel="noopener noreferrer">
                                        <span class="material-icons">tag</span>
                                        <span>Twitter</span>
                                    </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p style="color: rgba(228, 228, 228, 0.6); font-style: italic;">No social links added yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-card-header">
                            <h3><span class="material-icons">security</span> Account Security</h3>
                        </div>
                        <div class="info-card-body">
                            <button class="btn-secondary full-width" onclick="changePassword()">
                                <span class="material-icons">lock</span>
                                Change Password
                            </button>
                            <button class="btn-secondary full-width" onclick="enable2FA()" style="margin-top: 12px;">
                                <span class="material-icons">verified_user</span>
                                Enable Two-Factor Authentication
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="settings" class="dashboard-section">
            <div class="section-header">
                <h1>Settings</h1>
            </div>

            <div class="settings-container">
                <div class="settings-sidebar">
                    <div class="settings-nav-item active" data-tab="general">
                        <span class="material-icons">tune</span>
                        <span>General</span>
                    </div>
                    <div class="settings-nav-item" data-tab="preferences">
                        <span class="material-icons">palette</span>
                        <span>Preferences</span>
                    </div>
                    <div class="settings-nav-item" data-tab="notifications">
                        <span class="material-icons">notifications</span>
                        <span>Notifications</span>
                    </div>
                    <div class="settings-nav-item" data-tab="privacy">
                        <span class="material-icons">privacy_tip</span>
                        <span>Privacy & Security</span>
                    </div>
                    <div class="settings-nav-item" data-tab="payment">
                        <span class="material-icons">payment</span>
                        <span>Payment Methods</span>
                    </div>
                </div>

                <div class="settings-content">
                    <div class="settings-panel active" data-panel="general">
                        <h2>General Settings</h2>
                        <div class="settings-section">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Language</h4>
                                    <p>Choose your preferred language</p>
                                </div>
                                <select class="setting-control">
                                    <option value="bn" selected>বাংলা (Bangla)</option>
                                    <option value="en">English</option>
                                    <option value="es">Spanish</option>
                                    <option value="fr">French</option>
                                    <option value="de">German</option>
                                </select>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Time Zone</h4>
                                    <p>Set your local time zone</p>
                                </div>
                                <select class="setting-control">
                                    <option value="Asia/Dhaka" selected>Bangladesh Time (BST +06:00)</option>
                                    <option value="PST">Pacific Time (PST)</option>
                                    <option value="EST">Eastern Time (EST)</option>
                                    <option value="CST">Central Time (CST)</option>
                                    <option value="MST">Mountain Time (MST)</option>
                                </select>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Date Format</h4>
                                    <p>Choose how dates are displayed</p>
                                </div>
                                <select class="setting-control">
                                    <option value="MM/DD/YYYY">MM/DD/YYYY</option>
                                    <option value="DD/MM/YYYY">DD/MM/YYYY</option>
                                    <option value="YYYY-MM-DD">YYYY-MM-DD</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="settings-panel" data-panel="preferences">
                        <h2>Preferences</h2>
                        <div class="settings-section">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Dark Mode</h4>
                                    <p>Enable dark theme for better viewing</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Auto-Save Drafts</h4>
                                    <p>Automatically save course drafts</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Show Student Progress</h4>
                                    <p>Display student progress in dashboard</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="settings-panel" data-panel="notifications">
                        <h2>Notification Preferences</h2>
                        <div class="settings-section">
                            <h3>Email Notifications</h3>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>New Student Enrollments</h4>
                                    <p>Get notified when students enroll</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Course Reviews</h4>
                                    <p>Notifications for new course reviews</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Revenue Updates</h4>
                                    <p>Monthly revenue reports</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="settings-section">
                            <h3>Push Notifications</h3>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Student Questions</h4>
                                    <p>Get alerts for student questions</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Course Milestones</h4>
                                    <p>Alerts for course achievements</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="settings-panel" data-panel="privacy">
                        <h2>Privacy & Security</h2>
                        <div class="settings-section">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Profile Visibility</h4>
                                    <p>Make your profile visible to students</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Show Email Address</h4>
                                    <p>Display email on your public profile</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Two-Factor Authentication</h4>
                                    <p>Add an extra layer of security</p>
                                </div>
                                <button class="btn-secondary">Enable</button>
                            </div>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Active Sessions</h4>
                                    <p>Manage your logged-in devices</p>
                                </div>
                                <button class="btn-secondary">View Sessions</button>
                            </div>
                        </div>
                    </div>

                    <div class="settings-panel" data-panel="payment">
                        <h2>Payment Methods</h2>
                        <div class="settings-section">
                            <div class="payment-methods-list">
                                <div class="payment-method-item">
                                    <div class="payment-icon">
                                        <span class="material-icons">credit_card</span>
                                    </div>
                                    <div class="payment-info">
                                        <h4>PayPal</h4>
                                        <p><?php echo htmlspecialchars($user_email); ?></p>
                                    </div>
                                    <button class="btn-icon" onclick="editPaymentMethod('paypal')">
                                        <span class="material-icons">edit</span>
                                    </button>
                                </div>
                                <div class="payment-method-item">
                                    <div class="payment-icon">
                                        <span class="material-icons">account_balance</span>
                                    </div>
                                    <div class="payment-info">
                                        <h4>Bank Transfer</h4>
                                        <p>••••1234</p>
                                    </div>
                                    <button class="btn-icon" onclick="editPaymentMethod('bank')">
                                        <span class="material-icons">edit</span>
                                    </button>
                                </div>
                            </div>
                            <button class="btn-primary" onclick="addPaymentMethod()" style="margin-top: 20px;">
                                <span class="material-icons">add</span>
                                Add Payment Method
                            </button>
                        </div>
                        <div class="settings-section">
                            <h3>Payout Schedule</h3>
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>Automatic Payouts</h4>
                                    <p>Receive payouts automatically on the 1st of each month</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="info-box">
                                <span class="material-icons">info</span>
                                <p>Minimum payout threshold: <strong>$50.00</strong></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="notifications" class="dashboard-section">
            <div class="section-header">
                <h1>Notifications</h1>
                <div class="header-actions">
                    <button class="btn-secondary" onclick="markAllAsRead()">
                        <span class="material-icons">done_all</span>
                        Mark All as Read
                    </button>
                    <button class="btn-secondary" onclick="clearAllNotifications()">
                        <span class="material-icons">clear_all</span>
                        Clear All
                    </button>
                </div>
            </div>

            <div class="notifications-container">
                <div class="notification-filters">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="unread">Unread</button>
                    <button class="filter-btn" data-filter="enrollments">Enrollments</button>
                    <button class="filter-btn" data-filter="reviews">Reviews</button>
                    <button class="filter-btn" data-filter="revenue">Revenue</button>
                </div>

                <div class="notifications-list" id="notificationsList">
                    <div class="notifications-loading" style="text-align: center; padding: 40px;">
                        <span class="material-icons" style="font-size: 40px; color: #666; animation: spin 1s linear infinite;">refresh</span>
                        <p style="color: #888; margin-top: 12px;">Loading notifications...</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Create Course Modal -->
    <div id="createCourseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><span class="material-icons">add_circle</span> Create New Course</h2>
                <button class="close-btn" type="button" onclick="closeModal('createCourseModal')">
                    <span class="material-icons">close</span>
                </button>
            </div>
            
            <form id="newCourseForm">
                <div class="form-section">
                    <h3 class="section-title">Basic Information</h3>
                    
                    <div class="form-group full-width">
                        <label for="newCourseTitle">Course Title *</label>
                        <input type="text" id="newCourseTitle" name="title" required 
                               maxlength="150" placeholder="e.g., Complete Web Development Bootcamp">
                        <small class="help-text">Enter a clear and attractive course title</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="newCourseCategory">Category *</label>
                            <select id="newCourseCategory" name="category_id" required>
                                <option value="">Choose category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="newCourseLevel">Level *</label>
                            <select id="newCourseLevel" name="level" required>
                                <option value="">Choose level</option>
                                <option value="beginner">Beginner</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                                <option value="all_levels">All Levels</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="newCourseLanguage">Language *</label>
                            <select id="newCourseLanguage" name="language" required>
                                <option value="Bangla" selected>বাংলা (Bangla)</option>
                                <option value="English">English</option>
                                <option value="Spanish">Spanish</option>
                                <option value="French">French</option>
                                <option value="German">German</option>
                                <option value="Chinese">Chinese</option>
                                <option value="Japanese">Japanese</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="newCourseDuration">Duration (hours)</label>
                            <input type="number" id="newCourseDuration" name="duration_hours" 
                                   min="0" step="0.5" placeholder="10">
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="newCourseDescription">Description *</label>
                        <textarea id="newCourseDescription" name="description" required 
                                  rows="4" maxlength="300" 
                                  placeholder="Write a brief description of your course..."></textarea>
                        <small class="char-counter"><span id="newCourseDescCount">0</span>/300</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="newCourseThumbnail">
                            <span class="material-icons">image</span>
                            Course Thumbnail
                        </label>
                        <input type="file" id="newCourseThumbnail" name="thumbnail" 
                               accept="image/jpeg,image/png,image/jpg,image/webp">
                        <small class="help-text">Upload course thumbnail image (JPG, PNG, or WebP, max 5MB)</small>
                        <div id="newThumbnailPreview" class="thumbnail-preview" style="display: none;">
                            <img id="newThumbnailImage" src="" alt="Thumbnail preview">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Pricing</h3>
                    
                    <div class="pricing-type-selector">
                        <label class="radio-card">
                            <input type="radio" name="pricing_type" value="paid" id="newCoursePaid" checked>
                            <div class="card-content">
                                <span class="material-icons">payments</span>
                                <div>
                                    <strong>Paid Course</strong>
                                    <small>Set a price for your course</small>
                                </div>
                            </div>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="pricing_type" value="free" id="newCourseFree">
                            <div class="card-content">
                                <span class="material-icons">card_giftcard</span>
                                <div>
                                    <strong>Free Course</strong>
                                    <small>Free for everyone</small>
                                </div>
                            </div>
                        </label>
                    </div>

                    <div id="newCoursePriceFields">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="newCoursePrice">Price (BDT) *</label>
                                <input type="number" id="newCoursePrice" name="price" 
                                       min="0" step="0.01" placeholder="49.99">
                            </div>

                            <div class="form-group">
                                <label for="newCourseOriginalPrice">Original Price (BDT)</label>
                                <input type="number" id="newCourseOriginalPrice" name="original_price" 
                                       min="0" step="0.01" placeholder="99.99">
                                <small class="help-text">For showing discount</small>
                            </div>
                        </div>
                        
                        <div id="newCourseDiscountInfo" class="discount-info" style="display: none;">
                            <span class="material-icons">sell</span>
                            <span id="newCourseDiscountText"></span>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('createCourseModal')">
                        <span class="material-icons">close</span>
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary" id="newCourseSubmitBtn">
                        <span class="material-icons">add</span>
                        Create Course
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div id="editCourseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><span class="material-icons">edit</span> Edit Course</h2>
                <button class="close-btn" type="button" onclick="closeModal('editCourseModal')">
                    <span class="material-icons">close</span>
                </button>
            </div>
            
            <form id="editCourseForm">
                <input type="hidden" id="editCourseId" name="courseId">
                
                <div class="form-section">
                    <h3 class="section-title">Basic Information</h3>
                    
                    <div class="form-group full-width">
                        <label for="editCourseTitle">Course Title *</label>
                        <input type="text" id="editCourseTitle" name="title" required 
                               placeholder="Enter course title" maxlength="150">
                        <small class="help-text">Update your course title</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="editCourseCategory">Category *</label>
                            <select id="editCourseCategory" name="category" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="editCourseLevel">Level *</label>
                            <select id="editCourseLevel" name="level" required>
                                <option value="">Select level</option>
                                <option value="beginner">Beginner</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                                <option value="all_levels">All Levels</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="editCourseDescription">Short Description *</label>
                        <textarea id="editCourseDescription" name="description" required 
                                  rows="3" maxlength="300" 
                                  placeholder="Brief course overview (shown in cards)..."></textarea>
                        <small class="help-text">Short description shown in course cards (max 300 characters)</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="editCourseLongDescription">Full Description</label>
                        <textarea id="editCourseLongDescription" name="long_description" 
                                  rows="6" 
                                  placeholder="Detailed course description (supports HTML)..."></textarea>
                        <small class="help-text">Detailed description shown on the course page</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="editLearningObjectives">
                            <span class="material-icons">check_circle</span>
                            Learning Objectives
                        </label>
                        <textarea id="editLearningObjectives" name="learning_objectives" 
                                  rows="5"></textarea>
                        <small class="help-text">Use the editor toolbar to format objectives. Tip: Use bullet lists for each learning outcome.</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="editCourseRequirements">
                            <span class="material-icons">rule</span>
                            Requirements
                        </label>
                        <textarea id="editCourseRequirements" name="requirements" 
                                  rows="4"></textarea>
                        <small class="help-text">List prerequisites using the editor. Tip: Use bullet lists for requirements.</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="editTargetAudience">
                            <span class="material-icons">people</span>
                            Target Audience
                        </label>
                        <textarea id="editTargetAudience" name="target_audience" 
                                  rows="4"></textarea>
                        <small class="help-text">Describe who should take this course. Tip: Use bullet lists for audience types.</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="editCourseThumbnail">
                            <span class="material-icons">image</span>
                            Course Thumbnail
                        </label>
                        <input type="file" id="editCourseThumbnail" name="thumbnail" 
                               accept="image/jpeg,image/png,image/jpg,image/webp">
                        <small class="help-text">Upload new thumbnail to replace current (JPG, PNG, or WebP, max 5MB)</small>
                        <div id="editThumbnailPreview" class="thumbnail-preview" style="display: none;">
                            <img id="editThumbnailImage" src="" alt="Current thumbnail">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Course Settings</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editCoursePrice">Price (BDT) *</label>
                            <input type="number" id="editCoursePrice" name="price" required 
                                   min="0" step="0.01" placeholder="৳499">
                            <small class="help-text">Course price in Bangladeshi Taka</small>
                        </div>

                        <div class="form-group">
                            <label for="editCourseStatus">Course Status *</label>
                            <select id="editCourseStatus" name="status" required>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                            <small class="help-text">Change course visibility</small>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('editCourseModal')">
                        <span class="material-icons">close</span>
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary">
                        <span class="material-icons">save</span>
                        Update Course
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Course Confirmation Modal -->
    <div id="deleteCourseModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));">
                <h2><span class="material-icons" style="color: #dc3545;">warning</span> Delete Course</h2>
                <button class="close-btn" type="button" onclick="closeModal('deleteCourseModal')">
                    <span class="material-icons">close</span>
                </button>
            </div>
            
            <div class="form-section" style="padding: 24px;">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                    <span class="material-icons" style="font-size: 48px; color: #dc3545;">warning</span>
                    <div>
                        <p style="margin: 0 0 10px 0; font-weight: 500; color: var(--text-color);">Are you sure you want to delete this course?</p>
                        <p id="deleteCourseTitle" style="margin: 0; color: rgba(228, 228, 228, 0.6); font-style: italic;"></p>
                    </div>
                </div>
                <div style="background: rgba(255, 193, 7, 0.1); border: 1px solid #ffc107; border-radius: 8px; padding: 15px;">
                    <p style="margin: 0; color: #ffc107; font-size: 14px;">
                        <strong>Warning:</strong> This action cannot be undone. All course content, sections, lectures, videos, images, resources, and enrollments will be permanently deleted.
                    </p>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('deleteCourseModal')">
                    <span class="material-icons">close</span>
                    Cancel
                </button>
                <button type="button" class="btn-danger" id="confirmDeleteBtn" onclick="confirmDeleteCourse()">
                    <span class="material-icons">delete_forever</span>
                    Delete Course
                </button>
            </div>
        </div>
    </div>

    <div id="editPersonalModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><span class="material-icons">person</span> Edit Personal Information</h2>
                <button class="close-btn" type="button" onclick="closeModal('editPersonalModal')">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form id="editPersonalForm" onsubmit="savePersonalInfo(event)">
                <div class="form-section">
                    <div class="form-group full-width">
                        <label for="editFullName">Full Name *</label>
                        <input type="text" id="editFullName" name="fullName" required value="<?php echo htmlspecialchars($full_name); ?>">
                    </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editEmail">Email *</label>
                        <input type="email" id="editEmail" name="email" required value="<?php echo htmlspecialchars($user_email); ?>">
                    </div>
                    <div class="form-group">
                        <label for="editPhone">Phone</label>
                        <input type="tel" id="editPhone" name="phone" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="editLocation">Location</label>
                        <input type="text" id="editLocation" name="location" value="<?php echo htmlspecialchars($user_data['location'] ?? ''); ?>">
                    </div>
                </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('editPersonalModal')">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <span class="material-icons">save</span>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><span class="material-icons">lock</span> Change Password</h2>
                <button class="close-btn" type="button" onclick="closeModal('changePasswordModal')">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form id="changePasswordForm" onsubmit="savePassword(event)" autocomplete="off">
                <div class="form-section">
                    <div class="form-group full-width">
                        <label for="currentPassword">Current Password *</label>
                        <div style="position: relative;">
                            <input type="password" id="currentPassword" name="current_password" required autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('currentPassword')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: rgba(228, 228, 228, 0.6); cursor: pointer; padding: 4px; display: flex; align-items: center;">
                                <span class="material-icons" style="font-size: 20px;">visibility</span>
                            </button>
                        </div>
                    </div>
                
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="newPassword">New Password *</label>
                        <div style="position: relative;">
                            <input type="password" id="newPassword" name="new_password" required minlength="8" autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('newPassword')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: rgba(228, 228, 228, 0.6); cursor: pointer; padding: 4px; display: flex; align-items: center;">
                                <span class="material-icons" style="font-size: 20px;">visibility</span>
                            </button>
                        </div>
                        <small>Minimum 8 characters</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="confirmPassword">Confirm New Password *</label>
                        <div style="position: relative;">
                            <input type="password" id="confirmPassword" name="confirm_password" required autocomplete="new-password">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: rgba(228, 228, 228, 0.6); cursor: pointer; padding: 4px; display: flex; align-items: center;">
                                <span class="material-icons" style="font-size: 20px;">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('changePasswordModal')">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <span class="material-icons">lock</span>
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="editProfessionalModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><span class="material-icons">work</span> Edit Professional Information</h2>
                <button class="close-btn" type="button" onclick="closeModal('editProfessionalModal')">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form id="editProfessionalForm" onsubmit="saveProfessionalInfo(event)">
                <div class="form-section">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editTitle">Professional Title</label>
                            <input type="text" id="editTitle" name="title" value="<?php echo htmlspecialchars($user_data['title'] ?? ''); ?>" placeholder="e.g., Senior Web Developer">
                        </div>
                        <div class="form-group">
                            <label for="editExperience">Years of Experience</label>
                            <input type="number" id="editExperience" name="experience_years" value="<?php echo htmlspecialchars($user_data['experience_years'] ?? ''); ?>" min="0" placeholder="5">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editSpecialization">Specialization</label>
                            <input type="text" id="editSpecialization" name="specialization" value="<?php echo htmlspecialchars($user_data['specialization'] ?? ''); ?>" placeholder="e.g., Web Development">
                        </div>
                        <div class="form-group">
                            <label for="editEducation">Education</label>
                            <input type="text" id="editEducation" name="education" value="<?php echo htmlspecialchars($user_data['education'] ?? ''); ?>" placeholder="e.g., Bachelor's in Computer Science">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('editProfessionalModal')">
                        <span class="material-icons">close</span>
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary">
                        <span class="material-icons">save</span>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="editBioModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><span class="material-icons">description</span> Edit Biography</h2>
                <button class="close-btn" type="button" onclick="closeModal('editBioModal')">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form id="editBioForm" onsubmit="saveBioInfo(event)">
                <div class="form-section">
                    <div class="form-group full-width">
                        <label for="editBio">Biography</label>
                        <textarea id="editBio" name="bio" rows="8" maxlength="1000" placeholder="Tell students about yourself, your experience, and what makes you a great instructor..." oninput="document.getElementById('bioCharCount').textContent = this.value.length"><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
                        <small class="char-counter"><span id="bioCharCount"><?php echo strlen($user_data['bio'] ?? ''); ?></span>/1000</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('editBioModal')">
                        <span class="material-icons">close</span>
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary">
                        <span class="material-icons">save</span>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="editSocialModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><span class="material-icons">link</span> Edit Social Links</h2>
                <button class="close-btn" type="button" onclick="closeModal('editSocialModal')">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form id="editSocialForm" onsubmit="saveSocialInfo(event)">
                <div class="form-section">
                    <div class="form-group full-width">
                        <label for="editWebsite">
                            <span class="material-icons" style="font-size: 18px; vertical-align: middle;">language</span>
                            Website
                        </label>
                        <input type="url" id="editWebsite" name="website" value="<?php echo htmlspecialchars($social_links['website'] ?? ''); ?>" placeholder="https://yourwebsite.com">
                    </div>
                    <div class="form-group full-width">
                        <label for="editLinkedin">
                            <span class="material-icons" style="font-size: 18px; vertical-align: middle;">work</span>
                            LinkedIn
                        </label>
                        <input type="url" id="editLinkedin" name="linkedin" value="<?php echo htmlspecialchars($social_links['linkedin'] ?? ''); ?>" placeholder="https://linkedin.com/in/yourprofile">
                    </div>
                    <div class="form-group full-width">
                        <label for="editGithub">
                            <span class="material-icons" style="font-size: 18px; vertical-align: middle;">code</span>
                            GitHub
                        </label>
                        <input type="url" id="editGithub" name="github" value="<?php echo htmlspecialchars($social_links['github'] ?? ''); ?>" placeholder="https://github.com/yourusername">
                    </div>
                    <div class="form-group full-width">
                        <label for="editTwitter">
                            <span class="material-icons" style="font-size: 18px; vertical-align: middle;">tag</span>
                            Twitter
                        </label>
                        <input type="url" id="editTwitter" name="twitter" value="<?php echo htmlspecialchars($social_links['twitter'] ?? ''); ?>" placeholder="https://twitter.com/yourusername">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('editSocialModal')">
                        <span class="material-icons">close</span>
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary">
                        <span class="material-icons">save</span>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Student Details Modal -->
    <div id="studentDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2><span class="material-icons">person</span> Student Details</h2>
                <button class="close-btn" type="button" onclick="closeModal('studentDetailsModal')">
                    <span class="material-icons">close</span>
                </button>
            </div>
            
            <div class="form-section" style="padding: 24px;">
                <!-- Student Info -->
                <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid rgba(228, 228, 228, 0.1);">
                    <img id="studentDetailAvatar" src="" alt="Student" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;">
                    <div style="flex: 1;">
                        <h3 id="studentDetailName" style="margin: 0 0 8px 0; color: var(--text-color);"></h3>
                        <p id="studentDetailEmail" style="margin: 0; color: rgba(228, 228, 228, 0.6);"></p>
                        <p id="studentDetailEnrolled" style="margin: 8px 0 0 0; font-size: 14px; color: rgba(228, 228, 228, 0.6);"></p>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
                    <div style="background: rgba(99, 102, 241, 0.1); padding: 16px; border-radius: 8px; text-align: center;">
                        <div id="studentDetailCoursesCount" style="font-size: 32px; font-weight: 700; color: #6366f1; margin-bottom: 4px;">0</div>
                        <div style="font-size: 14px; color: rgba(228, 228, 228, 0.6);">Courses Enrolled</div>
                    </div>
                    <div style="background: rgba(34, 197, 94, 0.1); padding: 16px; border-radius: 8px; text-align: center;">
                        <div id="studentDetailProgress" style="font-size: 32px; font-weight: 700; color: #22c55e; margin-bottom: 4px;">0%</div>
                        <div style="font-size: 14px; color: rgba(228, 228, 228, 0.6);">Avg. Progress</div>
                    </div>
                    <div style="background: rgba(249, 115, 22, 0.1); padding: 16px; border-radius: 8px; text-align: center;">
                        <div id="studentDetailLearningTime" style="font-size: 32px; font-weight: 700; color: #f97316; margin-bottom: 4px;">0h</div>
                        <div style="font-size: 14px; color: rgba(228, 228, 228, 0.6);">Learning Time</div>
                    </div>
                </div>

                <!-- Enrolled Courses -->
                <div>
                    <h4 style="margin: 0 0 16px 0; color: var(--text-color); font-size: 16px;">Enrolled Courses</h4>
                    <div id="studentDetailCoursesList" style="display: flex; flex-direction: column; gap: 12px; max-height: 300px; overflow-y: auto;">
                        <!-- Courses loaded dynamically -->
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('studentDetailsModal')">
                    <span class="material-icons">close</span>
                    Close
                </button>
            </div>
        </div>
    </div>

    <div id="createCouponModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><span class="material-icons">local_offer</span> Create New Coupon</h2>
                <button class="close-btn" type="button" onclick="closeModal('createCouponModal')">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form id="createCouponForm" onsubmit="submitCouponForm(event); return false;">
                <div class="form-section">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="couponCode">Coupon Code *</label>
                            <input type="text" id="couponCode" name="code" required placeholder="E.G., SUMMER2025" maxlength="20" style="text-transform: uppercase;">
                            <small class="help-text">Use uppercase letters, numbers, and hyphens only</small>
                        </div>
                        <div class="form-group">
                            <label for="couponType">Coupon Type *</label>
                            <select id="couponType" name="coupon_type" required>
                                <option value="">Select type</option>
                                <option value="overall">Overall (All Courses)</option>
                                <option value="specific">Specific Course</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="couponDiscountType">Discount Type *</label>
                            <select id="couponDiscountType" name="discount_type" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (৳)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="couponDiscountValue">Discount Value *</label>
                            <input type="number" id="couponDiscountValue" name="discount_value" required min="1" max="100" step="0.01" placeholder="e.g., 20">
                            <small class="help-text">Enter percentage (1-100)</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="couponStartDate">Start Date *</label>
                            <input type="date" id="couponStartDate" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="couponEndDate">End Date *</label>
                            <input type="date" id="couponEndDate" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="couponUsageLimit">Usage Limit</label>
                            <input type="number" id="couponUsageLimit" name="usage_limit" min="0" placeholder="Unlimited">
                            <small class="help-text">Leave empty for unlimited uses</small>
                        </div>
                        <div class="form-group">
                            <label for="couponMinAmount">Minimum Purchase Amount (৳)</label>
                            <input type="number" id="couponMinAmount" name="min_purchase_amount" min="0" step="0.01" placeholder="0.00">
                            <small class="help-text">Minimum cart value to apply coupon</small>
                        </div>
                    </div>

                    <div class="form-group full-width" id="couponCourseGroup" style="display: none;">
                        <label for="couponCourse">Select Course *</label>
                        <select id="couponCourse" name="course_id">
                            <option value="">Choose a course</option>
                            <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['course_id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group full-width">
                        <label for="couponDescription">Description (Optional)</label>
                        <textarea id="couponDescription" name="description" rows="3" maxlength="500" placeholder="Internal notes about this coupon..."></textarea>
                        <small class="help-text">Internal notes for your reference</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('createCouponModal')">
                        <span class="material-icons">close</span>
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary">
                        <span class="material-icons">local_offer</span>
                        Create Coupon
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Pricing Modal -->
    <div id="editPricingModal" class="modal">
        <div class="modal-content pricing-modal">
            <div class="modal-header">
                <h2><span class="material-icons">monetization_on</span> Update Course Pricing</h2>
                <button class="close-btn" type="button" onclick="closeModal('editPricingModal')">
                    <span class="material-icons">close</span>
                </button>
            </div>
            
            <form id="updatePricingForm">
                <input type="hidden" id="updatePricingCourseId" name="course_id">
                
                <div class="course-info-box">
                    <div class="course-icon">
                        <span class="material-icons">school</span>
                    </div>
                    <div class="course-details">
                        <label>Course</label>
                        <h3 id="updatePricingCourseName">...</h3>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Pricing Model</h3>
                    
                    <div class="toggle-option">
                        <label class="switch-label">
                            <input type="checkbox" id="updatePricingFree" name="is_free">
                            <span class="switch-slider"></span>
                            <div class="label-content">
                                <span class="material-icons">card_giftcard</span>
                                <div>
                                    <strong>Free Course</strong>
                                    <small>Make this course free</small>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div id="updatePricingFields" class="form-section">
                    <h3 class="section-title">Set Your Price</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="updatePricingPrice">Current Price (BDT) *</label>
                            <input type="number" id="updatePricingPrice" name="price" 
                                   min="0" step="0.01" placeholder="49.99">
                            <small class="help-text">Price students pay</small>
                        </div>

                        <div class="form-group">
                            <label for="updatePricingOriginal">Original Price (BDT)</label>
                            <input type="number" id="updatePricingOriginal" name="original_price" 
                                   min="0" step="0.01" placeholder="99.99">
                            <small class="help-text">For discount display</small>
                        </div>
                    </div>
                    
                    <div id="updatePricingDiscountInfo" class="discount-preview-box" style="display: none;">
                        <div class="discount-icon">
                            <span class="material-icons">local_offer</span>
                        </div>
                        <div class="discount-details">
                            <span id="updatePricingDiscountText"></span>
                        </div>
                    </div>

                    <div class="info-box">
                        <span class="material-icons">lightbulb</span>
                        <div>
                            <strong>Pricing Tips</strong>
                            <ul>
                                <li>Research similar courses in your category</li>
                                <li>Original price shows value and discount</li>
                                <li>Consider seasonal pricing strategies</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('editPricingModal')">
                        <span class="material-icons">close</span>
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary" id="updatePricingSubmitBtn">
                        <span class="material-icons">save</span>
                        Update Pricing
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pricing History Modal -->
    <div id="pricingHistoryModal" class="modal">
        <div class="modal-content pricing-history-modal">
            <div class="modal-header">
                <h2><span class="material-icons">history</span> Price History</h2>
                <button class="close-btn" type="button" onclick="closeModal('pricingHistoryModal')">
                    <span class="material-icons">close</span>
                </button>
            </div>
            
            <div class="pricing-history-content">
                <!-- Course Info Header -->
                <div class="history-course-info">
                    <div class="course-icon">
                        <span class="material-icons">school</span>
                    </div>
                    <div class="course-details">
                        <h3 id="historyCourseName">Loading...</h3>
                        <div class="current-price-display">
                            <span class="label">Current Price:</span>
                            <span class="price" id="historyCurrentPrice">--</span>
                        </div>
                    </div>
                </div>

                <!-- Price Statistics -->
                <div class="history-stats-grid" id="historyStatsGrid">
                    <div class="history-stat-card">
                        <span class="material-icons">trending_down</span>
                        <div class="stat-info">
                            <span class="stat-value" id="historyMinPrice">৳0</span>
                            <span class="stat-label">Lowest Price</span>
                        </div>
                    </div>
                    <div class="history-stat-card">
                        <span class="material-icons">trending_up</span>
                        <div class="stat-info">
                            <span class="stat-value" id="historyMaxPrice">৳0</span>
                            <span class="stat-label">Highest Price</span>
                        </div>
                    </div>
                    <div class="history-stat-card">
                        <span class="material-icons">show_chart</span>
                        <div class="stat-info">
                            <span class="stat-value" id="historyAvgPrice">৳0</span>
                            <span class="stat-label">Average Price</span>
                        </div>
                    </div>
                    <div class="history-stat-card">
                        <span class="material-icons">swap_vert</span>
                        <div class="stat-info">
                            <span class="stat-value" id="historyTotalChanges">0</span>
                            <span class="stat-label">Total Changes</span>
                        </div>
                    </div>
                </div>

                <!-- History Timeline -->
                <div class="history-timeline-section">
                    <h4><span class="material-icons">timeline</span> Price Change Timeline</h4>
                    <div class="history-timeline" id="historyTimeline">
                        <div class="history-loading">
                            <span class="material-icons rotating">sync</span>
                            <span>Loading history...</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('pricingHistoryModal')">
                    <span class="material-icons">close</span>
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        var INSTRUCTOR_ID = '<?php echo $current_user_id; ?>';
        var AJAX_BASE = 'ajax/';
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/instructor-dashboard.js"></script>
</body>

</html>
