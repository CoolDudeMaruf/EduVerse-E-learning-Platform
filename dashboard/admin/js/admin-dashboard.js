// Admin Dashboard JavaScript

const BASE_URL = window.location.origin + '/eduverse/';
let currentPage = { users: 1, courses: 1, transactions: 1, reviews: 1, instructors: 1 };
const ITEMS_PER_PAGE = 10;

// Initialize
document.addEventListener('DOMContentLoaded', function () {
    initNavigation();
    loadInitialData();
    setupFormHandlers();

    // Handle hash changes
    if (window.location.hash) {
        const section = window.location.hash.substring(1);
        showSection(section);
    }
});

// Navigation
function initNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    const mobileToggle = document.getElementById('mobileMenuToggle');
    const nav = document.getElementById('dashboardNav');
    const backdrop = document.getElementById('navBackdrop');

    navItems.forEach(item => {
        item.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href && href.startsWith('#')) {
                e.preventDefault();
                const section = href.substring(1);
                showSection(section);

                // Close mobile menu
                if (window.innerWidth <= 1024) {
                    nav.classList.remove('active');
                    backdrop.classList.remove('active');
                }
            }
        });
    });

    // Mobile menu toggle
    mobileToggle?.addEventListener('click', function () {
        nav.classList.toggle('active');
        backdrop.classList.toggle('active');
    });

    backdrop?.addEventListener('click', function () {
        nav.classList.remove('active');
        backdrop.classList.remove('active');
    });
}

function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.dashboard-section').forEach(section => {
        section.classList.remove('active');
    });

    // Show selected section
    const section = document.getElementById(sectionId);
    if (section) {
        section.classList.add('active');
    }

    // Update nav active state
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('href') === '#' + sectionId) {
            item.classList.add('active');
        }
    });

    // Update URL hash
    history.pushState(null, '', '#' + sectionId);

    // Load section data if needed
    loadSectionData(sectionId);
}

function loadSectionData(section) {
    switch (section) {
        case 'users':
            loadUsers();
            break;
        case 'courses':
            loadCourses();
            break;
        case 'transactions':
            loadTransactions();
            break;
        case 'reviews':
            loadReviews();
            break;
        case 'instructors':
            loadInstructors();
            break;
    }
}

function loadInitialData() {
    // Preload users data
    setTimeout(() => loadUsers(), 500);
}

// ============== Users Management ==============

async function loadUsers(page = 1) {
    currentPage.users = page;
    const role = document.getElementById('userRoleFilter')?.value || '';
    const status = document.getElementById('userStatusFilter')?.value || '';
    const search = document.getElementById('userSearch')?.value || '';

    const tbody = document.getElementById('usersTableBody');
    tbody.innerHTML = `<tr><td colspan="6" class="loading-cell"><div class="loading-spinner"></div><span>Loading users...</span></td></tr>`;

    try {
        const response = await fetch(`ajax/users.php?action=get_users&page=${page}&role=${role}&status=${status}&search=${encodeURIComponent(search)}`);
        const data = await response.json();

        if (data.success) {
            renderUsersTable(data.users);
            renderPagination('usersPagination', data.total_pages, page, 'loadUsers');
        } else {
            showToast(data.message || 'Failed to load users', 'error');
        }
    } catch (error) {
        console.error('Error loading users:', error);
        showToast('Failed to load users', 'error');
    }
}

function renderUsersTable(users) {
    const tbody = document.getElementById('usersTableBody');

    if (!users || users.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="loading-cell">No users found</td></tr>`;
        return;
    }

    tbody.innerHTML = users.map(user => `
        <tr data-id="${user.user_id}">
            <td>
                <div class="user-cell">
                    <img src="${user.profile_image_url ? BASE_URL + user.profile_image_url : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.first_name + ' ' + user.last_name)}" alt="">
                    <div>
                        <div class="user-name">${escapeHtml(user.first_name || '')} ${escapeHtml(user.last_name || '')}</div>
                        <div class="user-id">@${escapeHtml(user.username || user.user_id)}</div>
                    </div>
                </div>
            </td>
            <td>${escapeHtml(user.email)}</td>
            <td><span class="role-badge ${user.role}">${formatRole(user.role)}</span></td>
            <td><span class="status-badge ${user.status}">${formatStatus(user.status)}</span></td>
            <td>${formatDate(user.created_at)}</td>
            <td>
                <div class="action-buttons">
                    <button class="action-btn edit" onclick="editUser('${user.user_id}')" title="Edit">
                        <span class="material-icons">edit</span>
                    </button>
                    <button class="action-btn toggle" onclick="toggleUserStatus('${user.user_id}', '${user.status}')" title="${user.status === 'suspended' ? 'Activate' : 'Suspend'}">
                        <span class="material-icons">${user.status === 'suspended' ? 'lock_open' : 'lock'}</span>
                    </button>
                    <button class="action-btn delete" onclick="confirmDeleteUser('${user.user_id}')" title="Delete">
                        <span class="material-icons">delete</span>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function filterUsers() {
    loadUsers(1);
}

async function editUser(userId) {
    try {
        const response = await fetch(`ajax/users.php?action=get_user&user_id=${userId}`);
        const data = await response.json();

        if (data.success) {
            const user = data.user;
            document.getElementById('editUserId').value = user.user_id;
            document.getElementById('editFirstName').value = user.first_name || '';
            document.getElementById('editLastName').value = user.last_name || '';
            document.getElementById('editEmail').value = user.email || '';
            document.getElementById('editRole').value = user.role || 'student';
            document.getElementById('editStatus').value = user.status || 'active';
            document.getElementById('userModalTitle').textContent = 'Edit User';
            openModal('userModal');
        } else {
            showToast(data.message || 'Failed to load user', 'error');
        }
    } catch (error) {
        showToast('Failed to load user details', 'error');
    }
}

async function toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'suspended' ? 'active' : 'suspended';
    const action = currentStatus === 'suspended' ? 'activate' : 'suspend';

    if (!confirm(`Are you sure you want to ${action} this user?`)) return;

    try {
        const response = await fetch('ajax/users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_status&user_id=${userId}&status=${newStatus}`
        });
        const data = await response.json();

        if (data.success) {
            showToast(`User ${action}d successfully`, 'success');
            loadUsers(currentPage.users);
        } else {
            showToast(data.message || `Failed to ${action} user`, 'error');
        }
    } catch (error) {
        showToast(`Failed to ${action} user`, 'error');
    }
}

function confirmDeleteUser(userId) {
    showConfirmModal('Delete User', 'Are you sure you want to delete this user? This action cannot be undone.', async () => {
        try {
            const response = await fetch('ajax/users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_user&user_id=${userId}`
            });
            const data = await response.json();

            if (data.success) {
                showToast('User deleted successfully', 'success');
                loadUsers(currentPage.users);
            } else {
                showToast(data.message || 'Failed to delete user', 'error');
            }
        } catch (error) {
            showToast('Failed to delete user', 'error');
        }
    });
}

// ============== Courses Management ==============

async function loadCourses(page = 1) {
    currentPage.courses = page;
    const status = document.getElementById('courseStatusFilter')?.value || '';
    const search = document.getElementById('courseSearch')?.value || '';

    const tbody = document.getElementById('coursesTableBody');
    tbody.innerHTML = `<tr><td colspan="7" class="loading-cell"><div class="loading-spinner"></div><span>Loading courses...</span></td></tr>`;

    try {
        const response = await fetch(`ajax/courses.php?action=get_courses&page=${page}&status=${status}&search=${encodeURIComponent(search)}`);
        const data = await response.json();

        if (data.success) {
            renderCoursesTable(data.courses);
            renderPagination('coursesPagination', data.total_pages, page, 'loadCourses');
        } else {
            showToast(data.message || 'Failed to load courses', 'error');
        }
    } catch (error) {
        console.error('Error loading courses:', error);
        showToast('Failed to load courses', 'error');
    }
}

function renderCoursesTable(courses) {
    const tbody = document.getElementById('coursesTableBody');

    if (!courses || courses.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="loading-cell">No courses found</td></tr>`;
        return;
    }

    tbody.innerHTML = courses.map(course => `
        <tr data-id="${course.course_id}">
            <td>
                <div class="course-cell">
                    <img src="${course.thumbnail_url ? BASE_URL + course.thumbnail_url : BASE_URL + 'public/images/default-course.jpg'}" alt="">
                    <div>
                        <div class="course-title" title="${escapeHtml(course.title)}">${escapeHtml(course.title)}</div>
                    </div>
                </div>
            </td>
            <td>${escapeHtml(course.instructor_name || 'Unknown')}</td>
            <td>${escapeHtml(course.category_name || 'Uncategorized')}</td>
            <td>${course.is_free ? '<span class="status-badge active">Free</span>' : '৳' + parseFloat(course.price).toFixed(2)}</td>
            <td><span class="status-badge ${course.status}">${formatStatus(course.status)}</span></td>
            <td>${course.enrollment_count || 0}</td>
            <td>
                <div class="action-buttons">
                    <button class="action-btn view" onclick="window.open('${BASE_URL}course.php?id=${course.course_id}', '_blank')" title="View">
                        <span class="material-icons">visibility</span>
                    </button>
                    <button class="action-btn toggle" onclick="toggleCourseFeature('${course.course_id}', ${course.is_featured})" title="${course.is_featured ? 'Unfeature' : 'Feature'}">
                        <span class="material-icons">${course.is_featured ? 'star' : 'star_border'}</span>
                    </button>
                    <button class="action-btn edit" onclick="changeCourseStatus('${course.course_id}')" title="Change Status">
                        <span class="material-icons">edit</span>
                    </button>
                    <button class="action-btn delete" onclick="confirmDeleteCourse('${course.course_id}')" title="Delete">
                        <span class="material-icons">delete</span>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function filterCourses() {
    loadCourses(1);
}

async function toggleCourseFeature(courseId, isFeatured) {
    try {
        const response = await fetch('ajax/courses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle_featured&course_id=${courseId}&is_featured=${isFeatured ? 0 : 1}`
        });
        const data = await response.json();

        if (data.success) {
            showToast(isFeatured ? 'Course unfeatured' : 'Course featured', 'success');
            loadCourses(currentPage.courses);
        } else {
            showToast(data.message || 'Failed to update course', 'error');
        }
    } catch (error) {
        showToast('Failed to update course', 'error');
    }
}

function changeCourseStatus(courseId) {
    const statuses = ['published', 'draft', 'pending_review', 'suspended'];
    const statusLabels = statuses.map(s => formatStatus(s)).join(', ');
    const newStatus = prompt(`Enter new status (${statusLabels}):`, 'published');

    if (newStatus && statuses.includes(newStatus)) {
        updateCourseStatus(courseId, newStatus);
    } else if (newStatus) {
        showToast('Invalid status', 'error');
    }
}

async function updateCourseStatus(courseId, status) {
    try {
        const response = await fetch('ajax/courses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_status&course_id=${courseId}&status=${status}`
        });
        const data = await response.json();

        if (data.success) {
            showToast('Course status updated', 'success');
            loadCourses(currentPage.courses);
        } else {
            showToast(data.message || 'Failed to update status', 'error');
        }
    } catch (error) {
        showToast('Failed to update status', 'error');
    }
}

function confirmDeleteCourse(courseId) {
    showConfirmModal('Delete Course', 'Are you sure you want to delete this course? All associated data will be lost.', async () => {
        try {
            const response = await fetch('ajax/courses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_course&course_id=${courseId}`
            });
            const data = await response.json();

            if (data.success) {
                showToast('Course deleted successfully', 'success');
                loadCourses(currentPage.courses);
            } else {
                showToast(data.message || 'Failed to delete course', 'error');
            }
        } catch (error) {
            showToast('Failed to delete course', 'error');
        }
    });
}

// ============== Categories Management ==============

function showAddCategoryModal() {
    document.getElementById('categoryForm').reset();
    document.getElementById('editCategoryId').value = '';
    document.getElementById('categoryModalTitle').textContent = 'Add Category';
    openModal('categoryModal');
}

async function editCategory(categoryId) {
    try {
        const response = await fetch(`ajax/categories.php?action=get_category&category_id=${categoryId}`);
        const data = await response.json();

        if (data.success) {
            const cat = data.category;
            document.getElementById('editCategoryId').value = cat.category_id;
            document.getElementById('editCategoryName').value = cat.name || '';
            document.getElementById('editCategorySlug').value = cat.slug || '';
            document.getElementById('editCategoryIcon').value = cat.icon || '';
            document.getElementById('editCategoryDesc').value = cat.description || '';
            document.getElementById('editCategoryOrder').value = cat.display_order || 0;
            document.getElementById('editCategoryActive').checked = cat.is_active == 1;
            document.getElementById('categoryModalTitle').textContent = 'Edit Category';
            openModal('categoryModal');
        } else {
            showToast(data.message || 'Failed to load category', 'error');
        }
    } catch (error) {
        showToast('Failed to load category', 'error');
    }
}

function deleteCategory(categoryId) {
    showConfirmModal('Delete Category', 'Are you sure you want to delete this category?', async () => {
        try {
            const response = await fetch('ajax/categories.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_category&category_id=${categoryId}`
            });
            const data = await response.json();

            if (data.success) {
                showToast('Category deleted successfully', 'success');
                location.reload();
            } else {
                showToast(data.message || 'Failed to delete category', 'error');
            }
        } catch (error) {
            showToast('Failed to delete category', 'error');
        }
    });
}

// ============== Coupons Management ==============

function showAddCouponModal() {
    document.getElementById('couponForm').reset();
    document.getElementById('editCouponId').value = '';
    document.getElementById('couponModalTitle').textContent = 'Create Coupon';

    // Set default dates
    const today = new Date().toISOString().split('T')[0];
    const nextMonth = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    document.getElementById('editCouponFrom').value = today;
    document.getElementById('editCouponUntil').value = nextMonth;

    openModal('couponModal');
}

async function editCoupon(couponId) {
    try {
        const response = await fetch(`ajax/coupons.php?action=get_coupon&coupon_id=${couponId}`);
        const data = await response.json();

        if (data.success) {
            const coupon = data.coupon;
            document.getElementById('editCouponId').value = coupon.coupon_id;
            document.getElementById('editCouponCode').value = coupon.code || '';
            document.getElementById('editCouponDesc').value = coupon.description || '';
            document.getElementById('editCouponType').value = coupon.discount_type || 'percentage';
            document.getElementById('editCouponValue').value = coupon.discount_value || 0;
            document.getElementById('editCouponMinPurchase').value = coupon.min_purchase_amount || 0;
            document.getElementById('editCouponMaxDiscount').value = coupon.max_discount_amount || '';
            document.getElementById('editCouponFrom').value = coupon.valid_from ? coupon.valid_from.split(' ')[0] : '';
            document.getElementById('editCouponUntil').value = coupon.valid_until ? coupon.valid_until.split(' ')[0] : '';
            document.getElementById('editCouponLimit').value = coupon.usage_limit || 0;
            document.getElementById('editCouponPerUser').value = coupon.per_user_limit || 1;
            document.getElementById('editCouponActive').checked = coupon.is_active == 1;
            document.getElementById('couponModalTitle').textContent = 'Edit Coupon';
            openModal('couponModal');
        } else {
            showToast(data.message || 'Failed to load coupon', 'error');
        }
    } catch (error) {
        showToast('Failed to load coupon', 'error');
    }
}

async function toggleCoupon(couponId, newStatus) {
    try {
        const response = await fetch('ajax/coupons.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle_coupon&coupon_id=${couponId}&is_active=${newStatus}`
        });
        const data = await response.json();

        if (data.success) {
            showToast(newStatus ? 'Coupon activated' : 'Coupon deactivated', 'success');
            location.reload();
        } else {
            showToast(data.message || 'Failed to update coupon', 'error');
        }
    } catch (error) {
        showToast('Failed to update coupon', 'error');
    }
}

function deleteCoupon(couponId) {
    showConfirmModal('Delete Coupon', 'Are you sure you want to delete this coupon?', async () => {
        try {
            const response = await fetch('ajax/coupons.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_coupon&coupon_id=${couponId}`
            });
            const data = await response.json();

            if (data.success) {
                showToast('Coupon deleted successfully', 'success');
                location.reload();
            } else {
                showToast(data.message || 'Failed to delete coupon', 'error');
            }
        } catch (error) {
            showToast('Failed to delete coupon', 'error');
        }
    });
}

// ============== Transactions ==============

async function loadTransactions(page = 1) {
    currentPage.transactions = page;
    const dateFrom = document.getElementById('transDateFrom')?.value || '';
    const dateTo = document.getElementById('transDateTo')?.value || '';

    const tbody = document.getElementById('transactionsTableBody');
    tbody.innerHTML = `<tr><td colspan="7" class="loading-cell"><div class="loading-spinner"></div><span>Loading transactions...</span></td></tr>`;

    try {
        const response = await fetch(`ajax/transactions.php?action=get_transactions&page=${page}&date_from=${dateFrom}&date_to=${dateTo}`);
        const data = await response.json();

        if (data.success) {
            renderTransactionsTable(data.transactions);
            renderPagination('transactionsPagination', data.total_pages, page, 'loadTransactions');

            // Update stats
            document.getElementById('totalTransAmount').textContent = '৳' + parseFloat(data.total_amount || 0).toLocaleString();
            document.getElementById('platformFeeTotal').textContent = '৳' + parseFloat(data.total_fees || 0).toLocaleString();
            document.getElementById('transCount').textContent = data.total_count || 0;
        } else {
            showToast(data.message || 'Failed to load transactions', 'error');
        }
    } catch (error) {
        console.error('Error loading transactions:', error);
        showToast('Failed to load transactions', 'error');
    }
}

function renderTransactionsTable(transactions) {
    const tbody = document.getElementById('transactionsTableBody');

    if (!transactions || transactions.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="loading-cell">No transactions found</td></tr>`;
        return;
    }

    tbody.innerHTML = transactions.map(trans => `
        <tr>
            <td>#${trans.transaction_id}</td>
            <td>${escapeHtml(trans.user_name || trans.user_id)}</td>
            <td><span class="status-badge info">${formatStatus(trans.transaction_type)}</span></td>
            <td>৳${parseFloat(trans.amount).toFixed(2)}</td>
            <td>৳${parseFloat(trans.platform_fee || 0).toFixed(2)}</td>
            <td><span class="status-badge ${trans.status}">${formatStatus(trans.status)}</span></td>
            <td>${formatDate(trans.created_at)}</td>
        </tr>
    `).join('');
}

function filterTransactions() {
    loadTransactions(1);
}

async function exportTransactions() {
    const dateFrom = document.getElementById('transDateFrom')?.value || '';
    const dateTo = document.getElementById('transDateTo')?.value || '';
    window.location.href = `ajax/transactions.php?action=export&date_from=${dateFrom}&date_to=${dateTo}`;
}

// ============== Reviews ==============

async function loadReviews(page = 1) {
    currentPage.reviews = page;
    const filter = document.getElementById('reviewFilter')?.value || '';

    const tbody = document.getElementById('reviewsTableBody');
    tbody.innerHTML = `<tr><td colspan="7" class="loading-cell"><div class="loading-spinner"></div><span>Loading reviews...</span></td></tr>`;

    try {
        const response = await fetch(`ajax/reviews.php?action=get_reviews&page=${page}&filter=${filter}`);
        const data = await response.json();

        if (data.success) {
            renderReviewsTable(data.reviews);
            renderPagination('reviewsPagination', data.total_pages, page, 'loadReviews');
        } else {
            showToast(data.message || 'Failed to load reviews', 'error');
        }
    } catch (error) {
        console.error('Error loading reviews:', error);
        showToast('Failed to load reviews', 'error');
    }
}

function renderReviewsTable(reviews) {
    const tbody = document.getElementById('reviewsTableBody');

    if (!reviews || reviews.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="loading-cell">No reviews found</td></tr>`;
        return;
    }

    tbody.innerHTML = reviews.map(review => `
        <tr data-id="${review.review_id}">
            <td>${escapeHtml(review.course_title || 'Unknown')}</td>
            <td>${escapeHtml(review.user_name || 'Anonymous')}</td>
            <td><span class="rating-stars">${'★'.repeat(review.rating)}${'☆'.repeat(5 - review.rating)}</span></td>
            <td>${escapeHtml((review.review_text || '').substring(0, 50))}${review.review_text?.length > 50 ? '...' : ''}</td>
            <td>${formatDate(review.created_at)}</td>
            <td><span class="status-badge ${review.is_published ? 'published' : 'unpublished'}">${review.is_published ? 'Published' : 'Unpublished'}</span></td>
            <td>
                <div class="action-buttons">
                    <button class="action-btn toggle" onclick="toggleReviewPublish(${review.review_id}, ${review.is_published})" title="${review.is_published ? 'Unpublish' : 'Publish'}">
                        <span class="material-icons">${review.is_published ? 'visibility_off' : 'visibility'}</span>
                    </button>
                    <button class="action-btn delete" onclick="confirmDeleteReview(${review.review_id})" title="Delete">
                        <span class="material-icons">delete</span>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function filterReviews() {
    loadReviews(1);
}

async function toggleReviewPublish(reviewId, isPublished) {
    try {
        const response = await fetch('ajax/reviews.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle_publish&review_id=${reviewId}&is_published=${isPublished ? 0 : 1}`
        });
        const data = await response.json();

        if (data.success) {
            showToast(isPublished ? 'Review unpublished' : 'Review published', 'success');
            loadReviews(currentPage.reviews);
        } else {
            showToast(data.message || 'Failed to update review', 'error');
        }
    } catch (error) {
        showToast('Failed to update review', 'error');
    }
}

function confirmDeleteReview(reviewId) {
    showConfirmModal('Delete Review', 'Are you sure you want to delete this review?', async () => {
        try {
            const response = await fetch('ajax/reviews.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_review&review_id=${reviewId}`
            });
            const data = await response.json();

            if (data.success) {
                showToast('Review deleted successfully', 'success');
                loadReviews(currentPage.reviews);
            } else {
                showToast(data.message || 'Failed to delete review', 'error');
            }
        } catch (error) {
            showToast('Failed to delete review', 'error');
        }
    });
}

// ============== Instructors ==============

async function loadInstructors(page = 1) {
    currentPage.instructors = page;
    const status = document.getElementById('instructorStatusFilter')?.value || '';

    const tbody = document.getElementById('instructorsTableBody');
    tbody.innerHTML = `<tr><td colspan="7" class="loading-cell"><div class="loading-spinner"></div><span>Loading instructors...</span></td></tr>`;

    try {
        const response = await fetch(`ajax/users.php?action=get_instructors&page=${page}&status=${status}`);
        const data = await response.json();

        if (data.success) {
            renderInstructorsTable(data.instructors);
            renderPagination('instructorsPagination', data.total_pages, page, 'loadInstructors');
        } else {
            showToast(data.message || 'Failed to load instructors', 'error');
        }
    } catch (error) {
        console.error('Error loading instructors:', error);
        showToast('Failed to load instructors', 'error');
    }
}

function renderInstructorsTable(instructors) {
    const tbody = document.getElementById('instructorsTableBody');

    if (!instructors || instructors.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="loading-cell">No instructors found</td></tr>`;
        return;
    }

    tbody.innerHTML = instructors.map(inst => `
        <tr data-id="${inst.user_id}">
            <td>
                <div class="user-cell">
                    <img src="${inst.profile_image_url ? BASE_URL + inst.profile_image_url : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(inst.first_name + ' ' + inst.last_name)}" alt="">
                    <div>
                        <div class="user-name">${escapeHtml(inst.first_name || '')} ${escapeHtml(inst.last_name || '')}</div>
                        <div class="user-id">${escapeHtml(inst.email)}</div>
                    </div>
                </div>
            </td>
            <td>${escapeHtml(inst.expertise_areas || 'Not specified')}</td>
            <td>${inst.course_count || 0}</td>
            <td>${inst.student_count || 0}</td>
            <td>৳${parseFloat(inst.total_earnings || 0).toFixed(2)}</td>
            <td><span class="status-badge ${inst.verification_status}">${formatStatus(inst.verification_status)}</span></td>
            <td>
                <div class="action-buttons">
                    <button class="action-btn view" onclick="window.open('${BASE_URL}instructor-profile.php?id=${inst.user_id}', '_blank')" title="View Profile">
                        <span class="material-icons">visibility</span>
                    </button>
                    ${inst.verification_status === 'pending' ? `
                    <button class="action-btn edit" onclick="verifyInstructor('${inst.user_id}')" title="Verify">
                        <span class="material-icons">verified</span>
                    </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function filterInstructors() {
    loadInstructors(1);
}

async function verifyInstructor(userId) {
    if (!confirm('Verify this instructor?')) return;

    try {
        const response = await fetch('ajax/users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=verify_instructor&user_id=${userId}`
        });
        const data = await response.json();

        if (data.success) {
            showToast('Instructor verified successfully', 'success');
            loadInstructors(currentPage.instructors);
        } else {
            showToast(data.message || 'Failed to verify instructor', 'error');
        }
    } catch (error) {
        showToast('Failed to verify instructor', 'error');
    }
}

// ============== Form Handlers ==============

function setupFormHandlers() {
    // User Form
    document.getElementById('userForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'update_user');

        try {
            const response = await fetch('ajax/users.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            });
            const data = await response.json();

            if (data.success) {
                showToast('User updated successfully', 'success');
                closeModal('userModal');
                loadUsers(currentPage.users);
            } else {
                showToast(data.message || 'Failed to update user', 'error');
            }
        } catch (error) {
            showToast('Failed to update user', 'error');
        }
    });

    // Category Form
    document.getElementById('categoryForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        const categoryId = formData.get('category_id');
        formData.append('action', categoryId ? 'update_category' : 'add_category');
        formData.set('is_active', document.getElementById('editCategoryActive').checked ? 1 : 0);

        try {
            const response = await fetch('ajax/categories.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            });
            const data = await response.json();

            if (data.success) {
                showToast(categoryId ? 'Category updated' : 'Category added', 'success');
                closeModal('categoryModal');
                location.reload();
            } else {
                showToast(data.message || 'Failed to save category', 'error');
            }
        } catch (error) {
            showToast('Failed to save category', 'error');
        }
    });

    // Coupon Form
    document.getElementById('couponForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        const couponId = formData.get('coupon_id');
        formData.append('action', couponId ? 'update_coupon' : 'add_coupon');
        formData.set('is_active', document.getElementById('editCouponActive').checked ? 1 : 0);

        try {
            const response = await fetch('ajax/coupons.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            });
            const data = await response.json();

            if (data.success) {
                showToast(couponId ? 'Coupon updated' : 'Coupon created', 'success');
                closeModal('couponModal');
                location.reload();
            } else {
                showToast(data.message || 'Failed to save coupon', 'error');
            }
        } catch (error) {
            showToast('Failed to save coupon', 'error');
        }
    });

    // Settings Forms
    document.getElementById('platformSettingsForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        showToast('Settings saved successfully', 'success');
    });

    document.getElementById('securitySettingsForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        showToast('Security settings saved', 'success');
    });

    // Auto-generate slug from name
    document.getElementById('editCategoryName')?.addEventListener('input', function () {
        const slug = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
        const slugInput = document.getElementById('editCategorySlug');
        if (!slugInput.dataset.manual) {
            slugInput.value = slug;
        }
    });

    document.getElementById('editCategorySlug')?.addEventListener('input', function () {
        this.dataset.manual = 'true';
    });

    // Profile Info Form
    document.getElementById('profileInfoForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'update_profile');

        try {
            const response = await fetch('ajax/profile.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            });
            const data = await response.json();

            if (data.success) {
                showToast('Profile updated successfully', 'success');
                // Update sidebar name
                const nameEl = document.querySelector('.profile-name');
                if (nameEl) {
                    nameEl.textContent = formData.get('first_name') + ' ' + formData.get('last_name');
                }
            } else {
                showToast(data.message || 'Failed to update profile', 'error');
            }
        } catch (error) {
            showToast('Failed to update profile', 'error');
        }
    });

    // Change Password Form
    document.getElementById('changePasswordForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;

        if (newPassword !== confirmPassword) {
            showToast('Passwords do not match', 'error');
            return;
        }

        if (newPassword.length < 8) {
            showToast('Password must be at least 8 characters', 'error');
            return;
        }

        const formData = new FormData(this);
        formData.append('action', 'change_password');

        try {
            const response = await fetch('ajax/profile.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            });
            const data = await response.json();

            if (data.success) {
                showToast('Password changed successfully', 'success');
                this.reset();
            } else {
                showToast(data.message || 'Failed to change password', 'error');
            }
        } catch (error) {
            showToast('Failed to change password', 'error');
        }
    });
}

// ============== Profile Management ==============

async function uploadProfileImage(input) {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];

    // Validate file size (max 2MB)
    if (file.size > 2 * 1024 * 1024) {
        showToast('File size must be less than 2MB', 'error');
        return;
    }

    // Validate file type
    if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
        showToast('Only JPG, PNG or GIF images are allowed', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'upload_avatar');
    formData.append('avatar', file);

    try {
        const response = await fetch('ajax/profile.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            showToast('Profile picture updated', 'success');
            // Update preview image
            const preview = document.getElementById('profileAvatarPreview');
            if (preview && data.avatar_url) {
                preview.src = BASE_URL + data.avatar_url;
            }
            // Update sidebar avatar
            const sidebarAvatar = document.querySelector('.profile-img');
            if (sidebarAvatar && data.avatar_url) {
                sidebarAvatar.src = BASE_URL + data.avatar_url;
            }
        } else {
            showToast(data.message || 'Failed to upload image', 'error');
        }
    } catch (error) {
        showToast('Failed to upload image', 'error');
    }
}

// ============== Utility Functions ==============

function renderPagination(containerId, totalPages, currentPage, loadFunction) {
    const container = document.getElementById(containerId);
    if (!container || totalPages <= 1) {
        if (container) container.innerHTML = '';
        return;
    }

    let html = '';

    // Previous button
    html += `<button class="page-btn" onclick="${loadFunction}(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''}>&laquo;</button>`;

    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);

    if (startPage > 1) {
        html += `<button class="page-btn" onclick="${loadFunction}(1)">1</button>`;
        if (startPage > 2) html += `<span style="color: var(--text-muted)">...</span>`;
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="${loadFunction}(${i})">${i}</button>`;
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<span style="color: var(--text-muted)">...</span>`;
        html += `<button class="page-btn" onclick="${loadFunction}(${totalPages})">${totalPages}</button>`;
    }

    // Next button
    html += `<button class="page-btn" onclick="${loadFunction}(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''}>&raquo;</button>`;

    container.innerHTML = html;
}

function openModal(modalId) {
    document.getElementById(modalId)?.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId)?.classList.remove('active');
    document.body.style.overflow = '';
}

function showConfirmModal(title, message, onConfirm) {
    document.getElementById('confirmModalTitle').textContent = title;
    document.getElementById('confirmModalMessage').textContent = message;

    const confirmBtn = document.getElementById('confirmModalBtn');
    confirmBtn.onclick = () => {
        closeModal('confirmModal');
        onConfirm();
    };

    openModal('confirmModal');
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const icons = {
        success: 'check_circle',
        error: 'error',
        warning: 'warning',
        info: 'info'
    };

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span class="material-icons">${icons[type]}</span>
        <span class="toast-message">${escapeHtml(message)}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;

    container.appendChild(toast);

    setTimeout(() => toast.remove(), 5000);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatRole(role) {
    const roles = {
        'student': 'Student',
        'instructor': 'Instructor',
        'admin': 'Admin',
        'super_admin': 'Super Admin'
    };
    return roles[role] || role;
}

function formatStatus(status) {
    if (!status) return '';
    return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

// Close modals on backdrop click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function (e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

// Handle escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.style.overflow = '';
    }
});
