<?php
require_once '../../includes/config.php';

// Check if user is logged in
if (!$is_logged_in) {
    redirect('login');
}

// Check if user has admin role
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    redirect('dashboard');
}

// Get current admin info
$admin_id = $current_user_id;
$admin_query = "SELECT * FROM users WHERE user_id = '$admin_id'";
$admin_result = mysqli_query($con, $admin_query);
$admin = mysqli_fetch_assoc($admin_result);
$full_name = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')) ?: 'Admin';
$avatar_url = !empty($admin['profile_image_url']) ? $base_url . $admin['profile_image_url'] : 'https://ui-avatars.com/api/?name=' . urlencode($full_name) . '&background=6366f1&color=fff';

// Platform Statistics
// Total Users by Role
$users_stats = mysqli_query($con, "SELECT role, COUNT(*) as count FROM users GROUP BY role");
$user_counts = ['student' => 0, 'instructor' => 0, 'admin' => 0, 'super_admin' => 0, 'total' => 0];
while ($row = mysqli_fetch_assoc($users_stats)) {
    $user_counts[$row['role']] = $row['count'];
    $user_counts['total'] += $row['count'];
}

// Course Statistics
$courses_stats = mysqli_query($con, "SELECT status, COUNT(*) as count FROM courses GROUP BY status");
$course_counts = ['published' => 0, 'draft' => 0, 'pending_review' => 0, 'suspended' => 0, 'archived' => 0, 'total' => 0];
while ($row = mysqli_fetch_assoc($courses_stats)) {
    $course_counts[$row['status']] = $row['count'];
    $course_counts['total'] += $row['count'];
}

// Total Enrollments
$enrollments_result = mysqli_query($con, "SELECT COUNT(*) as total FROM enrollments");
$total_enrollments = mysqli_fetch_assoc($enrollments_result)['total'];

// Total Revenue
$revenue_result = mysqli_query($con, "SELECT SUM(amount) as total FROM transactions WHERE status = 'completed'");
$total_revenue = mysqli_fetch_assoc($revenue_result)['total'] ?? 0;

// Platform Fee Revenue
$platform_fee_result = mysqli_query($con, "SELECT SUM(platform_fee) as total FROM transactions WHERE status = 'completed'");
$platform_revenue = mysqli_fetch_assoc($platform_fee_result)['total'] ?? 0;

// Recent Users (last 7 days)
$recent_users = mysqli_query($con, "SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$new_users_week = mysqli_fetch_assoc($recent_users)['count'];

// Categories
$categories_result = mysqli_query($con, "SELECT * FROM categories ORDER BY display_order ASC");
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row;
}

// Coupons
$coupons_result = mysqli_query($con, "SELECT * FROM coupons ORDER BY created_at DESC");
$coupons = [];
while ($row = mysqli_fetch_assoc($coupons_result)) {
    $coupons[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo $site_name; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="<?php echo $base_url; ?>public/images/favicon.svg">
    <link rel="stylesheet" href="css/admin-dashboard.css">
</head>
<body>
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <span class="material-icons">menu</span>
    </button>

    <div class="nav-backdrop" id="navBackdrop"></div>

    <nav class="dashboard-nav" id="dashboardNav">
        <div class="nav-brand">
            <span class="material-icons">admin_panel_settings</span>
            <span class="brand-text">Admin Panel</span>
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
                </a>
                <a href="#users" class="nav-item">
                    <span class="material-icons">people</span>
                    <span>Users</span>
                    <span class="nav-count"><?php echo number_format($user_counts['total']); ?></span>
                </a>
                <a href="#courses" class="nav-item">
                    <span class="material-icons">school</span>
                    <span>Courses</span>
                    <span class="nav-count"><?php echo number_format($course_counts['total']); ?></span>
                </a>
                <a href="#categories" class="nav-item">
                    <span class="material-icons">category</span>
                    <span>Categories</span>
                    <span class="nav-count"><?php echo count($categories); ?></span>
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-section-title">
                    <span class="material-icons">trending_up</span>
                    Finance
                </div>
                <a href="#transactions" class="nav-item">
                    <span class="material-icons">receipt_long</span>
                    <span>Transactions</span>
                </a>
                <a href="#coupons" class="nav-item">
                    <span class="material-icons">local_offer</span>
                    <span>Coupons</span>
                    <span class="nav-count"><?php echo count($coupons); ?></span>
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-section-title">
                    <span class="material-icons">settings</span>
                    Management
                </div>
                <a href="#reviews" class="nav-item">
                    <span class="material-icons">rate_review</span>
                    <span>Reviews</span>
                </a>
                <a href="#instructors" class="nav-item">
                    <span class="material-icons">supervisor_account</span>
                    <span>Instructors</span>
                </a>
                <a href="#profile" class="nav-item">
                    <span class="material-icons">account_circle</span>
                    <span>My Profile</span>
                </a>
                <a href="#settings" class="nav-item">
                    <span class="material-icons">tune</span>
                    <span>Settings</span>
                </a>
            </div>

            <div class="nav-group">
                <a href="<?php echo $base_url; ?>" class="nav-item">
                    <span class="material-icons">home</span>
                    <span>Back to Site</span>
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
                    <span class="profile-role">Admin</span>
                </div>
            </div>
        </div>
    </nav>

    <main class="dashboard-main">
        <!-- Overview Section -->
        <section id="overview" class="dashboard-section active">
            <div class="section-header">
                <h1>Dashboard Overview</h1>
                <div class="header-actions">
                    <span class="last-updated">Last updated: <?php echo date('M d, Y H:i'); ?></span>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                        <span class="material-icons">people</span>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo number_format($user_counts['total']); ?></span>
                        <span class="stat-label">Total Users</span>
                        <span class="stat-change positive">+<?php echo $new_users_week; ?> this week</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                        <span class="material-icons">school</span>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo number_format($course_counts['total']); ?></span>
                        <span class="stat-label">Total Courses</span>
                        <span class="stat-detail"><?php echo $course_counts['published']; ?> published</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <span class="material-icons">how_to_reg</span>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo number_format($total_enrollments); ?></span>
                        <span class="stat-label">Total Enrollments</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                        <span class="material-icons">attach_money</span>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value">৳<?php echo number_format($total_revenue, 2); ?></span>
                        <span class="stat-label">Total Revenue</span>
                        <span class="stat-detail">৳<?php echo number_format($platform_revenue, 2); ?> platform</span>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="card user-breakdown">
                    <div class="card-header">
                        <h3>User Breakdown</h3>
                    </div>
                    <div class="card-body">
                        <div class="breakdown-item">
                            <span class="breakdown-label"><span class="material-icons">person</span> Students</span>
                            <span class="breakdown-value"><?php echo number_format($user_counts['student']); ?></span>
                        </div>
                        <div class="breakdown-item">
                            <span class="breakdown-label"><span class="material-icons">cast_for_education</span> Instructors</span>
                            <span class="breakdown-value"><?php echo number_format($user_counts['instructor']); ?></span>
                        </div>
                        <div class="breakdown-item">
                            <span class="breakdown-label"><span class="material-icons">admin_panel_settings</span> Admins</span>
                            <span class="breakdown-value"><?php echo number_format($user_counts['admin'] + $user_counts['super_admin']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="card course-breakdown">
                    <div class="card-header">
                        <h3>Course Status</h3>
                    </div>
                    <div class="card-body">
                        <div class="breakdown-item">
                            <span class="breakdown-label"><span class="status-dot published"></span> Published</span>
                            <span class="breakdown-value"><?php echo number_format($course_counts['published']); ?></span>
                        </div>
                        <div class="breakdown-item">
                            <span class="breakdown-label"><span class="status-dot pending"></span> Pending Review</span>
                            <span class="breakdown-value"><?php echo number_format($course_counts['pending_review']); ?></span>
                        </div>
                        <div class="breakdown-item">
                            <span class="breakdown-label"><span class="status-dot draft"></span> Draft</span>
                            <span class="breakdown-value"><?php echo number_format($course_counts['draft']); ?></span>
                        </div>
                        <div class="breakdown-item">
                            <span class="breakdown-label"><span class="status-dot suspended"></span> Suspended</span>
                            <span class="breakdown-value"><?php echo number_format($course_counts['suspended']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="card quick-actions">
                    <div class="card-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <button class="quick-action-btn" onclick="showSection('users')">
                            <span class="material-icons">person_add</span>
                            <span>Manage Users</span>
                        </button>
                        <button class="quick-action-btn" onclick="showSection('courses')">
                            <span class="material-icons">library_books</span>
                            <span>Manage Courses</span>
                        </button>
                        <button class="quick-action-btn" onclick="showSection('coupons'); showAddCouponModal()">
                            <span class="material-icons">add_circle</span>
                            <span>Create Coupon</span>
                        </button>
                        <button class="quick-action-btn" onclick="showSection('categories')">
                            <span class="material-icons">add_box</span>
                            <span>Add Category</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Users Section -->
        <section id="users" class="dashboard-section">
            <div class="section-header">
                <h1>User Management</h1>
                <div class="header-actions">
                    <select id="userRoleFilter" class="filter-select" onchange="filterUsers()">
                        <option value="">All Roles</option>
                        <option value="student">Students</option>
                        <option value="instructor">Instructors</option>
                        <option value="admin">Admins</option>
                        <option value="super_admin">Super Admins</option>
                    </select>
                    <select id="userStatusFilter" class="filter-select" onchange="filterUsers()">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="pending_verification">Pending</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <div class="search-box">
                        <span class="material-icons">search</span>
                        <input type="text" id="userSearch" placeholder="Search users..." onkeyup="filterUsers()">
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table class="data-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="6" class="loading-cell">
                                    <div class="loading-spinner"></div>
                                    <span>Loading users...</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="usersPagination"></div>
            </div>
        </section>

        <!-- Courses Section -->
        <section id="courses" class="dashboard-section">
            <div class="section-header">
                <h1>Course Management</h1>
                <div class="header-actions">
                    <select id="courseStatusFilter" class="filter-select" onchange="filterCourses()">
                        <option value="">All Status</option>
                        <option value="published">Published</option>
                        <option value="pending_review">Pending Review</option>
                        <option value="draft">Draft</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <div class="search-box">
                        <span class="material-icons">search</span>
                        <input type="text" id="courseSearch" placeholder="Search courses..." onkeyup="filterCourses()">
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table class="data-table" id="coursesTable">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Instructor</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Enrollments</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="coursesTableBody">
                            <tr>
                                <td colspan="7" class="loading-cell">
                                    <div class="loading-spinner"></div>
                                    <span>Loading courses...</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="coursesPagination"></div>
            </div>
        </section>

        <!-- Categories Section -->
        <section id="categories" class="dashboard-section">
            <div class="section-header">
                <h1>Category Management</h1>
                <div class="header-actions">
                    <button class="btn-primary" onclick="showAddCategoryModal()">
                        <span class="material-icons">add</span>
                        Add Category
                    </button>
                </div>
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table class="data-table" id="categoriesTable">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Icon</th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Courses</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                            <tr data-id="<?php echo $cat['category_id']; ?>">
                                <td><?php echo $cat['display_order']; ?></td>
                                <td><span class="category-icon"><?php echo $cat['icon'] ?? '📚'; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($cat['slug']); ?></code></td>
                                <td><?php echo $cat['course_count']; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $cat['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn edit" onclick="editCategory(<?php echo $cat['category_id']; ?>)" title="Edit">
                                            <span class="material-icons">edit</span>
                                        </button>
                                        <button class="action-btn delete" onclick="deleteCategory(<?php echo $cat['category_id']; ?>)" title="Delete">
                                            <span class="material-icons">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Transactions Section -->
        <section id="transactions" class="dashboard-section">
            <div class="section-header">
                <h1>Transactions</h1>
                <div class="header-actions">
                    <input type="date" id="transDateFrom" class="date-input" onchange="filterTransactions()">
                    <span>to</span>
                    <input type="date" id="transDateTo" class="date-input" onchange="filterTransactions()">
                    <button class="btn-secondary" onclick="exportTransactions()">
                        <span class="material-icons">download</span>
                        Export CSV
                    </button>
                </div>
            </div>
            <div class="stats-grid stats-small">
                <div class="stat-card mini">
                    <div class="stat-info">
                        <span class="stat-value" id="totalTransAmount">৳0</span>
                        <span class="stat-label">Total Revenue</span>
                    </div>
                </div>
                <div class="stat-card mini">
                    <div class="stat-info">
                        <span class="stat-value" id="platformFeeTotal">৳0</span>
                        <span class="stat-label">Platform Fees</span>
                    </div>
                </div>
                <div class="stat-card mini">
                    <div class="stat-info">
                        <span class="stat-value" id="transCount">0</span>
                        <span class="stat-label">Transactions</span>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table class="data-table" id="transactionsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Platform Fee</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="transactionsTableBody">
                            <tr>
                                <td colspan="7" class="loading-cell">
                                    <div class="loading-spinner"></div>
                                    <span>Loading transactions...</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="transactionsPagination"></div>
            </div>
        </section>

        <!-- Coupons Section -->
        <section id="coupons" class="dashboard-section">
            <div class="section-header">
                <h1>Coupon Management</h1>
                <div class="header-actions">
                    <button class="btn-primary" onclick="showAddCouponModal()">
                        <span class="material-icons">add</span>
                        Create Coupon
                    </button>
                </div>
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table class="data-table" id="couponsTable">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Discount</th>
                                <th>Usage</th>
                                <th>Valid Period</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coupons as $coupon): ?>
                            <tr data-id="<?php echo $coupon['coupon_id']; ?>">
                                <td><code class="coupon-code"><?php echo htmlspecialchars($coupon['code']); ?></code></td>
                                <td>
                                    <?php if ($coupon['discount_type'] === 'percentage'): ?>
                                        <span class="discount-badge percentage"><?php echo $coupon['discount_value']; ?>%</span>
                                    <?php else: ?>
                                        <span class="discount-badge fixed">৳<?php echo number_format($coupon['discount_value'], 2); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="usage-info"><?php echo $coupon['usage_count']; ?> / <?php echo $coupon['usage_limit'] ?: '∞'; ?></span>
                                </td>
                                <td>
                                    <small><?php echo date('M d, Y', strtotime($coupon['valid_from'])); ?> - <?php echo date('M d, Y', strtotime($coupon['valid_until'])); ?></small>
                                </td>
                                <td>
                                    <?php 
                                    $is_expired = strtotime($coupon['valid_until']) < time();
                                    $status = $is_expired ? 'expired' : ($coupon['is_active'] ? 'active' : 'inactive');
                                    ?>
                                    <span class="status-badge <?php echo $status; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn edit" onclick="editCoupon(<?php echo $coupon['coupon_id']; ?>)" title="Edit">
                                            <span class="material-icons">edit</span>
                                        </button>
                                        <button class="action-btn toggle" onclick="toggleCoupon(<?php echo $coupon['coupon_id']; ?>, <?php echo $coupon['is_active'] ? 0 : 1; ?>)" title="<?php echo $coupon['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                            <span class="material-icons"><?php echo $coupon['is_active'] ? 'toggle_on' : 'toggle_off'; ?></span>
                                        </button>
                                        <button class="action-btn delete" onclick="deleteCoupon(<?php echo $coupon['coupon_id']; ?>)" title="Delete">
                                            <span class="material-icons">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Reviews Section -->
        <section id="reviews" class="dashboard-section">
            <div class="section-header">
                <h1>Review Moderation</h1>
                <div class="header-actions">
                    <select id="reviewFilter" class="filter-select" onchange="filterReviews()">
                        <option value="">All Reviews</option>
                        <option value="published">Published</option>
                        <option value="unpublished">Unpublished</option>
                    </select>
                </div>
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table class="data-table" id="reviewsTable">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>User</th>
                                <th>Rating</th>
                                <th>Review</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="reviewsTableBody">
                            <tr>
                                <td colspan="7" class="loading-cell">
                                    <div class="loading-spinner"></div>
                                    <span>Loading reviews...</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="reviewsPagination"></div>
            </div>
        </section>

        <!-- Instructors Section -->
        <section id="instructors" class="dashboard-section">
            <div class="section-header">
                <h1>Instructor Management</h1>
                <div class="header-actions">
                    <select id="instructorStatusFilter" class="filter-select" onchange="filterInstructors()">
                        <option value="">All Status</option>
                        <option value="verified">Verified</option>
                        <option value="pending">Pending</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table class="data-table" id="instructorsTable">
                        <thead>
                            <tr>
                                <th>Instructor</th>
                                <th>Expertise</th>
                                <th>Courses</th>
                                <th>Students</th>
                                <th>Earnings</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="instructorsTableBody">
                            <tr>
                                <td colspan="7" class="loading-cell">
                                    <div class="loading-spinner"></div>
                                    <span>Loading instructors...</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="instructorsPagination"></div>
            </div>
        </section>

        <!-- Profile Section -->
        <section id="profile" class="dashboard-section">
            <div class="section-header">
                <h1>My Profile</h1>
            </div>
            <div class="settings-grid">
                <div class="card">
                    <div class="card-header">
                        <h3><span class="material-icons">person</span> Personal Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="profile-avatar-section">
                            <div class="profile-avatar-container">
                                <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile" class="profile-avatar-large" id="profileAvatarPreview">
                                <label for="profileImageInput" class="avatar-upload-btn">
                                    <span class="material-icons">camera_alt</span>
                                </label>
                                <input type="file" id="profileImageInput" accept="image/*" style="display: none;" onchange="uploadProfileImage(this)">
                            </div>
                            <div class="avatar-info">
                                <p>Upload a new profile picture</p>
                                <small>JPG, PNG or GIF (Max 2MB)</small>
                            </div>
                        </div>
                        <form id="profileInfoForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" id="profileFirstName" value="<?php echo htmlspecialchars($admin['first_name'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" id="profileLastName" value="<?php echo htmlspecialchars($admin['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" id="profileEmail" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="phone" id="profilePhone" value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Bio</label>
                                <textarea name="bio" id="profileBio" rows="3" placeholder="Tell something about yourself..."><?php echo htmlspecialchars($admin['bio'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" class="btn-primary">
                                <span class="material-icons">save</span>
                                Save Changes
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h3><span class="material-icons">lock</span> Change Password</h3>
                    </div>
                    <div class="card-body">
                        <form id="changePasswordForm">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" id="currentPassword" required>
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" id="newPassword" required minlength="8">
                                <small class="form-hint">Minimum 8 characters</small>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirmPassword" required>
                            </div>
                            <button type="submit" class="btn-primary">
                                <span class="material-icons">vpn_key</span>
                                Update Password
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h3><span class="material-icons">info</span> Account Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="account-info-list">
                            <div class="account-info-item">
                                <span class="info-label">User ID</span>
                                <span class="info-value"><code><?php echo htmlspecialchars($admin['user_id']); ?></code></span>
                            </div>
                            <div class="account-info-item">
                                <span class="info-label">Username</span>
                                <span class="info-value">@<?php echo htmlspecialchars($admin['username'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="account-info-item">
                                <span class="info-label">Role</span>
                                <span class="info-value">
                                    <span class="role-badge <?php echo $admin['role']; ?>"><?php echo ucfirst(str_replace('_', ' ', $admin['role'])); ?></span>
                                </span>
                            </div>
                            <div class="account-info-item">
                                <span class="info-label">Account Status</span>
                                <span class="info-value">
                                    <span class="status-badge <?php echo $admin['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $admin['status'])); ?></span>
                                </span>
                            </div>
                            <div class="account-info-item">
                                <span class="info-label">Member Since</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></span>
                            </div>
                            <div class="account-info-item">
                                <span class="info-label">Last Updated</span>
                                <span class="info-value"><?php echo $admin['updated_at'] ? date('M d, Y H:i', strtotime($admin['updated_at'])) : 'Never'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Settings Section -->
        <section id="settings" class="dashboard-section">
            <div class="section-header">
                <h1>System Settings</h1>
            </div>
            <div class="settings-grid">
                <div class="card">
                    <div class="card-header">
                        <h3><span class="material-icons">business</span> Platform Settings</h3>
                    </div>
                    <div class="card-body">
                        <form id="platformSettingsForm">
                            <div class="form-group">
                                <label>Site Name</label>
                                <input type="text" name="site_name" value="<?php echo $site_name; ?>">
                            </div>
                            <div class="form-group">
                                <label>Default Currency</label>
                                <select name="default_currency">
                                    <option value="BDT" selected>BDT (৳)</option>
                                    <option value="USD">USD ($)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Platform Fee (%)</label>
                                <input type="number" name="platform_fee" value="20" min="0" max="100" step="0.1">
                            </div>
                            <button type="submit" class="btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h3><span class="material-icons">security</span> Security Settings</h3>
                    </div>
                    <div class="card-body">
                        <form id="securitySettingsForm">
                            <div class="form-group">
                                <label>Session Timeout (minutes)</label>
                                <input type="number" name="session_timeout" value="60" min="5" max="1440">
                            </div>
                            <div class="form-group checkbox">
                                <label>
                                    <input type="checkbox" name="email_verification" checked>
                                    Require Email Verification
                                </label>
                            </div>
                            <div class="form-group checkbox">
                                <label>
                                    <input type="checkbox" name="course_approval" checked>
                                    Require Course Approval
                                </label>
                            </div>
                            <button type="submit" class="btn-primary">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Modals -->
    <!-- User Edit Modal -->
    <div class="modal-overlay" id="userModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="userModalTitle">Edit User</h3>
                <button class="modal-close" onclick="closeModal('userModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" id="editFirstName" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" id="editLastName" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="editEmail" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" id="editRole">
                                <option value="student">Student</option>
                                <option value="instructor">Instructor</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" id="editStatus">
                                <option value="active">Active</option>
                                <option value="pending_verification">Pending</option>
                                <option value="suspended">Suspended</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal('userModal')">Cancel</button>
                        <button type="submit" class="btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Category Modal -->
    <div class="modal-overlay" id="categoryModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="categoryModalTitle">Add Category</h3>
                <button class="modal-close" onclick="closeModal('categoryModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="categoryForm">
                    <input type="hidden" name="category_id" id="editCategoryId">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" id="editCategoryName" required>
                    </div>
                    <div class="form-group">
                        <label>Slug</label>
                        <input type="text" name="slug" id="editCategorySlug" required>
                    </div>
                    <div class="form-group">
                        <label>Icon (Emoji)</label>
                        <input type="text" name="icon" id="editCategoryIcon" placeholder="📚" maxlength="5">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="editCategoryDesc" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Display Order</label>
                        <input type="number" name="display_order" id="editCategoryOrder" value="0" min="0">
                    </div>
                    <div class="form-group checkbox">
                        <label>
                            <input type="checkbox" name="is_active" id="editCategoryActive" checked>
                            Active
                        </label>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal('categoryModal')">Cancel</button>
                        <button type="submit" class="btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Coupon Modal -->
    <div class="modal-overlay" id="couponModal">
        <div class="modal modal-lg">
            <div class="modal-header">
                <h3 id="couponModalTitle">Create Coupon</h3>
                <button class="modal-close" onclick="closeModal('couponModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="couponForm">
                    <input type="hidden" name="coupon_id" id="editCouponId">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Coupon Code</label>
                            <input type="text" name="code" id="editCouponCode" required style="text-transform: uppercase;">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <input type="text" name="description" id="editCouponDesc">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Discount Type</label>
                            <select name="discount_type" id="editCouponType">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (৳)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Discount Value</label>
                            <input type="number" name="discount_value" id="editCouponValue" required min="0" step="0.01">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Min Purchase Amount</label>
                            <input type="number" name="min_purchase" id="editCouponMinPurchase" value="0" min="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Max Discount Amount</label>
                            <input type="number" name="max_discount" id="editCouponMaxDiscount" min="0" step="0.01" placeholder="Leave empty for no limit">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Valid From</label>
                            <input type="date" name="valid_from" id="editCouponFrom" required>
                        </div>
                        <div class="form-group">
                            <label>Valid Until</label>
                            <input type="date" name="valid_until" id="editCouponUntil" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Usage Limit (0 = unlimited)</label>
                            <input type="number" name="usage_limit" id="editCouponLimit" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label>Per User Limit</label>
                            <input type="number" name="per_user_limit" id="editCouponPerUser" value="1" min="1">
                        </div>
                    </div>
                    <div class="form-group checkbox">
                        <label>
                            <input type="checkbox" name="is_active" id="editCouponActive" checked>
                            Active
                        </label>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal('couponModal')">Cancel</button>
                        <button type="submit" class="btn-primary">Save Coupon</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirm Modal -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal modal-sm">
            <div class="modal-header">
                <h3 id="confirmModalTitle">Confirm Action</h3>
                <button class="modal-close" onclick="closeModal('confirmModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p id="confirmModalMessage">Are you sure you want to proceed?</p>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('confirmModal')">Cancel</button>
                    <button type="button" class="btn-danger" id="confirmModalBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <script src="js/admin-dashboard.js"></script>
</body>
</html>
