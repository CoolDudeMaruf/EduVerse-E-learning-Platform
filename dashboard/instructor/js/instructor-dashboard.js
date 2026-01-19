// Instructor Dashboard JavaScript - Dynamic AJAX Implementation

(function () {
    'use strict';

    // Configuration
    const AJAX_BASE = typeof window.AJAX_BASE !== 'undefined' ? window.AJAX_BASE : 'ajax/';
    const INSTRUCTOR_ID = typeof window.INSTRUCTOR_ID !== 'undefined' ? window.INSTRUCTOR_ID : '';

    // Cache DOM elements
    const DOM = {
        mobileMenuToggle: document.getElementById('mobileMenuToggle'),
        navBackdrop: document.getElementById('navBackdrop'),
        dashboardNav: document.getElementById('dashboardNav'),
        navItems: document.querySelectorAll('.nav-item'),
        dashboardSections: document.querySelectorAll('.dashboard-section'),
        settingsNavItems: document.querySelectorAll('.settings-nav-item'),
        settingsPanels: document.querySelectorAll('.settings-panel'),
        filterBtns: document.querySelectorAll('.filter-btn'),
        notificationItems: document.querySelectorAll('.notification-item')
    };

    // State management
    const STATE = {
        navOpen: false,
        currentSection: 'overview',
        currentSettingsPanel: 'general',
        charts: {},
        students: [],
        coupons: [],
        notifications: [],
        currentNotificationFilter: 'all'
    };

    /**
     * Initialize application
     */
    function init() {
        initMobileNavigation();
        initSidebarNavigation();
        initSettingsTabs();
        initNotificationFilters();
        initEventListeners();
        initCourseFilters();
        initFormHandlers();
        showInitialSection();

        // Load dynamic data
        loadDashboardStats();
        loadChartData();
        loadActivityFeed();
        loadStudents();
        loadCoupons();
        loadNotifications();
    }

    /**
     * Initialize form handlers - Brand New Implementation
     */
    function initFormHandlers() {
        // Create Course Form
        const newCourseForm = document.getElementById('newCourseForm');
        if (newCourseForm) {
            newCourseForm.addEventListener('submit', createCourseHandler);
        }

        // Edit Course Form
        const editCourseForm = document.getElementById('editCourseForm');
        if (editCourseForm) {
            editCourseForm.addEventListener('submit', editCourseHandler);
        }

        // Update Pricing Form
        const updatePricingForm = document.getElementById('updatePricingForm');
        if (updatePricingForm) {
            updatePricingForm.addEventListener('submit', updatePricingHandler);
        }

        // Character counter for new course description
        const newDescInput = document.getElementById('newCourseDescription');
        if (newDescInput) {
            newDescInput.addEventListener('input', function () {
                const counter = document.getElementById('newCourseDescCount');
                if (counter) counter.textContent = this.value.length;
            });
        }

        // Thumbnail preview for new course
        const newThumbnail = document.getElementById('newCourseThumbnail');
        if (newThumbnail) {
            newThumbnail.addEventListener('change', function () {
                previewThumbnail(this, 'newThumbnailPreview', 'newThumbnailImage');
            });
        }

        // Thumbnail preview for edit course
        const editThumbnail = document.getElementById('editCourseThumbnail');
        if (editThumbnail) {
            editThumbnail.addEventListener('change', function () {
                previewThumbnail(this, 'editThumbnailPreview', 'editThumbnailImage');
            });
        }

        // Pricing type toggle for new course
        const newCoursePaid = document.getElementById('newCoursePaid');
        const newCourseFree = document.getElementById('newCourseFree');
        if (newCoursePaid) newCoursePaid.addEventListener('change', toggleNewCoursePricing);
        if (newCourseFree) newCourseFree.addEventListener('change', toggleNewCoursePricing);

        // Price calculation for new course
        const newPrice = document.getElementById('newCoursePrice');
        const newOriginal = document.getElementById('newCourseOriginalPrice');
        if (newPrice) newPrice.addEventListener('input', calculateNewCourseDiscount);
        if (newOriginal) newOriginal.addEventListener('input', calculateNewCourseDiscount);

        // Pricing toggle for update form
        const updateFreeToggle = document.getElementById('updatePricingFree');
        if (updateFreeToggle) updateFreeToggle.addEventListener('change', toggleUpdatePricingFields);

        // Price calculation for update form
        const updatePrice = document.getElementById('updatePricingPrice');
        const updateOriginal = document.getElementById('updatePricingOriginal');
        if (updatePrice) updatePrice.addEventListener('input', calculateUpdateDiscount);
        if (updateOriginal) updateOriginal.addEventListener('input', calculateUpdateDiscount);
    }

    /**
     * Preview thumbnail image
     */
    function previewThumbnail(input, previewContainerId, previewImageId) {
        const file = input.files[0];
        if (file) {
            // Validate file type
            const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                showNotification('Please upload a valid image file (JPG, PNG, or WebP)', 'error');
                input.value = '';
                return;
            }

            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                showNotification('Image size must be less than 5MB', 'error');
                input.value = '';
                return;
            }

            // Show preview
            const reader = new FileReader();
            reader.onload = function (e) {
                const previewContainer = document.getElementById(previewContainerId);
                const previewImage = document.getElementById(previewImageId);

                if (previewImage && previewContainer) {
                    previewImage.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
            };
            reader.readAsDataURL(file);
        }
    }

    /**
     * AJAX helper function
     */
    function ajax(url, options = {}) {
        const method = options.method || 'GET';
        const data = options.data || null;

        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open(method, url, true);

            if (method === 'POST' && !(data instanceof FormData)) {
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            }

            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        resolve(JSON.parse(xhr.responseText));
                    } catch (e) {
                        reject(new Error('Invalid JSON response'));
                    }
                } else {
                    reject(new Error(xhr.statusText));
                }
            };

            xhr.onerror = function () {
                reject(new Error('Network error'));
            };

            if (data instanceof FormData) {
                xhr.send(data);
            } else if (data) {
                xhr.send(new URLSearchParams(data).toString());
            } else {
                xhr.send();
            }
        });
    }

    /**
     * Load dashboard statistics
     */
    function loadDashboardStats() {
        ajax(AJAX_BASE + 'dashboard-handler.php?action=get_stats')
            .then(response => {
                if (response.success) {
                    updateStatsDisplay(response.stats);
                }
            })
            .catch(err => console.error('Failed to load stats:', err));
    }

    /**
     * Update stats display
     */
    function updateStatsDisplay(stats) {
        const elements = {
            totalStudents: stats.total_students.toLocaleString(),
            totalCourses: stats.published_courses,
            totalEarnings: '৳' + stats.total_earnings.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
            avgRating: stats.avg_rating.toFixed(1)
        };

        Object.keys(elements).forEach(key => {
            const element = document.getElementById(key);
            if (element) {
                element.textContent = elements[key];
            }
        });
    }

    /**
     * Load chart data from backend
     */
    function loadChartData() {
        ajax(AJAX_BASE + 'dashboard-handler.php?action=get_chart_data')
            .then(response => {
                if (response.success) {
                    initChartsWithData(response.charts);
                }
            })
            .catch(err => {
                console.error('Failed to load chart data:', err);
                initChartsWithDefaults();
            });
    }

    /**
     * Initialize charts with real data
     */
    function initChartsWithData(data) {
        if (typeof Chart === 'undefined') return;

        Chart.defaults.color = '#E4E4E4';
        Chart.defaults.borderColor = 'rgba(51, 51, 51, 0.6)';

        // Enrollment Chart
        const enrollmentCtx = document.getElementById('enrollmentChart');
        if (enrollmentCtx) {
            STATE.charts.enrollment = new Chart(enrollmentCtx, {
                type: 'line',
                data: {
                    labels: data.enrollment.map(d => d.label),
                    datasets: [{
                        label: 'Enrollments',
                        data: data.enrollment.map(d => d.value),
                        borderColor: '#4A90E2',
                        backgroundColor: 'rgba(74, 144, 226, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            STATE.charts.revenue = new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: data.revenue.map(d => d.label),
                    datasets: [{
                        label: 'Revenue ($)',
                        data: data.revenue.map(d => d.value),
                        backgroundColor: '#50E3C2',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        // Revenue Trend Chart
        const revenueTrendCtx = document.getElementById('revenueTrendChart');
        if (revenueTrendCtx) {
            STATE.charts.revenueTrend = new Chart(revenueTrendCtx, {
                type: 'line',
                data: {
                    labels: data.revenue.map(d => d.label),
                    datasets: [{
                        label: 'Revenue',
                        data: data.revenue.map(d => d.value),
                        borderColor: '#4A90E2',
                        backgroundColor: 'rgba(74, 144, 226, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } }
                }
            });
        }
    }

    /**
     * Initialize charts with default data (fallback)
     */
    function initChartsWithDefaults() {
        if (typeof Chart === 'undefined') return;

        Chart.defaults.color = '#E4E4E4';
        Chart.defaults.borderColor = 'rgba(51, 51, 51, 0.6)';

        // Enrollment Chart - destroy existing if any
        const enrollmentCtx = document.getElementById('enrollmentChart');
        if (enrollmentCtx) {
            if (STATE.charts.enrollment) {
                STATE.charts.enrollment.destroy();
            }
            STATE.charts.enrollment = new Chart(enrollmentCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Enrollments',
                        data: [0, 0, 0, 0, 0, 0],
                        borderColor: '#4A90E2',
                        backgroundColor: 'rgba(74, 144, 226, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        // Revenue Chart - destroy existing if any
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            if (STATE.charts.revenue) {
                STATE.charts.revenue.destroy();
            }
            STATE.charts.revenue = new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Revenue ($)',
                        data: [0, 0, 0, 0, 0, 0],
                        backgroundColor: '#50E3C2',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    }

    /**
     * Load activity feed
     */
    function loadActivityFeed() {
        ajax(AJAX_BASE + 'dashboard-handler.php?action=get_activity&limit=10')
            .then(response => {
                if (response.success) {
                    renderActivityFeed(response.activities);
                }
            })
            .catch(err => {
                console.error('Failed to load activity:', err);
                renderEmptyActivity();
            });
    }

    /**
     * Render activity feed
     */
    function renderActivityFeed(activities) {
        const activityFeed = document.getElementById('activityFeed');
        if (!activityFeed) return;

        if (activities.length === 0) {
            renderEmptyActivity();
            return;
        }

        const iconColors = {
            'enrollment': '#4CAF50',
            'review': '#FF9800',
            'sale': '#2196F3',
            'question': '#9C27B0'
        };

        activityFeed.innerHTML = activities.map(a => `
            <div class="activity-item">
                <div class="activity-icon" style="background: ${iconColors[a.type] || '#666'};">
                    <span class="material-icons">${a.icon}</span>
                </div>
                <div class="activity-content">
                    <p>${a.message}</p>
                </div>
                <span class="activity-time">${a.time_ago}</span>
            </div>
        `).join('');
    }

    function renderEmptyActivity() {
        const activityFeed = document.getElementById('activityFeed');
        if (activityFeed) {
            activityFeed.innerHTML = '<p style="color: var(--text-color); text-align: center; padding: 40px;">No recent activity</p>';
        }
    }

    /**
     * Load students list
     */
    /**
     * Load students list with pagination
     */
    let currentStudentPage = 1;
    let studentsPerPage = 20;

    function loadStudents(search = '', page = 1) {
        currentStudentPage = page;
        const url = AJAX_BASE + 'student-handler.php?action=list&page=' + page +
            '&limit=' + studentsPerPage +
            (search ? '&search=' + encodeURIComponent(search) : '');
        ajax(url)
            .then(response => {
                if (response.success) {
                    STATE.students = response.students;
                    renderStudentsList(response.students);
                    renderStudentsPagination(response.pagination);
                }
            })
            .catch(err => console.error('Failed to load students:', err));
    }

    /**
     * Render students list
     */
    function renderStudentsList(students) {
        const studentsList = document.getElementById('studentsList');
        if (!studentsList) return;

        if (students.length === 0) {
            studentsList.innerHTML = `
                <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                    <span class="material-icons" style="font-size: 64px; color: #666;">people</span>
                    <h3 style="margin: 16px 0 8px;">No students yet</h3>
                    <p style="color: #888;">Students will appear here once they enroll in your courses</p>
                </div>
            `;
            return;
        }

        studentsList.innerHTML = students.map(s => `
            <div class="student-card" data-user-id="${s.user_id}">
                <img src="${s.avatar}" alt="${s.name}" class="student-avatar">
                <div class="student-info">
                    <h4>${s.name}</h4>
                    <p>${s.email}</p>
                </div>
                <div class="student-stats">
                    <div class="student-stat">
                        <span class="value">${s.courses_count}</span>
                        <span class="label">Courses</span>
                    </div>
                    <div class="student-stat">
                        <span class="value">${s.progress}%</span>
                        <span class="label">Progress</span>
                    </div>
                </div>
                <button class="btn-icon" onclick="viewStudentDetails('${s.user_id}')">
                    <span class="material-icons">visibility</span>
                </button>
            </div>
        `).join('');
    }

    /**
     * Render students pagination
     */
    function renderStudentsPagination(pagination) {
        const paginationContainer = document.getElementById('studentsPagination');
        if (!paginationContainer) return;

        if (pagination.total_pages <= 1) {
            paginationContainer.style.display = 'none';
            return;
        }

        paginationContainer.style.display = 'flex';

        const currentPage = pagination.page;
        const totalPages = pagination.total_pages;
        let buttons = [];

        // Previous button
        buttons.push(`
            <button class="btn-secondary" 
                    ${currentPage === 1 ? 'disabled' : ''} 
                    onclick="loadStudents(document.getElementById('studentSearch').value, ${currentPage - 1})"
                    style="padding: 8px 12px;">
                <span class="material-icons" style="font-size: 18px;">chevron_left</span>
            </button>
        `);

        // Page numbers
        const maxVisible = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);

        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        if (startPage > 1) {
            buttons.push(`<button class="btn-secondary" onclick="loadStudents(document.getElementById('studentSearch').value, 1)" style="padding: 8px 16px;">1</button>`);
            if (startPage > 2) {
                buttons.push(`<span style="padding: 8px;">...</span>`);
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            buttons.push(`
                <button class="btn-${i === currentPage ? 'primary' : 'secondary'}" 
                        onclick="loadStudents(document.getElementById('studentSearch').value, ${i})"
                        style="padding: 8px 16px;">
                    ${i}
                </button>
            `);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                buttons.push(`<span style="padding: 8px;">...</span>`);
            }
            buttons.push(`<button class="btn-secondary" onclick="loadStudents(document.getElementById('studentSearch').value, ${totalPages})" style="padding: 8px 16px;">${totalPages}</button>`);
        }

        // Next button
        buttons.push(`
            <button class="btn-secondary" 
                    ${currentPage === totalPages ? 'disabled' : ''} 
                    onclick="loadStudents(document.getElementById('studentSearch').value, ${currentPage + 1})"
                    style="padding: 8px 12px;">
                <span class="material-icons" style="font-size: 18px;">chevron_right</span>
            </button>
        `);

        paginationContainer.innerHTML = buttons.join('');
    }

    /**
     * Load coupons list
     */
    function loadCoupons(filters = {}) {
        let url = AJAX_BASE + 'coupon-handler.php?action=list';
        if (filters.type) url += '&type=' + filters.type;
        if (filters.status) url += '&status=' + filters.status;
        if (filters.search) url += '&search=' + encodeURIComponent(filters.search);

        ajax(url)
            .then(response => {
                if (response.success) {
                    STATE.coupons = response.coupons;
                    renderCouponsList(response.coupons);
                    updateCouponStats(response.stats);
                }
            })
            .catch(err => console.error('Failed to load coupons:', err));
    }

    /**
     * Render coupons list
     */
    function renderCouponsList(coupons) {
        const couponList = document.getElementById('couponList');
        if (!couponList) return;

        if (coupons.length === 0) {
            couponList.innerHTML = `
                <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                    <span class="material-icons" style="font-size: 64px; color: #666;">local_offer</span>
                    <h3 style="margin: 16px 0 8px;">No coupons yet</h3>
                    <p style="color: #888;">Create your first coupon to offer discounts</p>
                </div>
            `;
            return;
        }

        couponList.innerHTML = coupons.map(c => `
            <div class="coupon-item" data-coupon-id="${c.coupon_id}">
                <div class="coupon-info">
                    <h4>${c.code}</h4>
                    <span class="coupon-type">${c.course_title}</span>
                </div>
                <div class="coupon-value">
                    <span class="discount">${c.discount_type === 'percentage' ? c.discount_value + '%' : '$' + c.discount_value}</span>
                    <span class="uses">${c.times_used}${c.max_uses > 0 ? '/' + c.max_uses : ''} used</span>
                </div>
                <div class="coupon-status">
                    <span class="badge ${c.status}">${c.status}</span>
                    ${c.expires_at ? '<small>Expires: ' + new Date(c.expires_at).toLocaleDateString() + '</small>' : ''}
                </div>
                <div class="coupon-actions">
                    <button class="btn-icon" onclick="toggleCoupon('${c.coupon_id}')" title="Toggle active">
                        <span class="material-icons">${c.is_active ? 'pause' : 'play_arrow'}</span>
                    </button>
                    <button class="btn-icon" onclick="deleteCoupon('${c.coupon_id}')" title="Delete">
                        <span class="material-icons">delete</span>
                    </button>
                </div>
            </div>
        `).join('');
    }

    /**
     * Update coupon stats
     */
    function updateCouponStats(stats) {
        const elements = {
            totalCoupons: stats.total,
            activeCoupons: stats.active,
            totalRedemptions: stats.total_uses,
            totalDiscount: '$' + stats.total_discount
        };

        Object.keys(elements).forEach(key => {
            const el = document.getElementById(key);
            if (el) el.textContent = elements[key];
        });
    }

    /**
     * Initialize course filters
     */
    function initCourseFilters() {
        const courseSearch = document.getElementById('courseSearch');
        const courseFilter = document.getElementById('courseFilter');

        if (courseSearch) {
            courseSearch.addEventListener('input', debounce(function () {
                filterCourses();
            }, 300));
        }

        if (courseFilter) {
            courseFilter.addEventListener('change', function () {
                filterCourses();
            });
        }

        const studentSearch = document.getElementById('studentSearch');
        if (studentSearch) {
            studentSearch.addEventListener('input', debounce(function () {
                currentStudentPage = 1; // Reset to first page on search
                loadStudents(this.value, 1);
            }, 300));
        }
    }

    /**
     * Filter courses display
     */
    function filterCourses() {
        const searchTerm = document.getElementById('courseSearch')?.value.toLowerCase() || '';
        const filterValue = document.getElementById('courseFilter')?.value || 'all';

        const courseCards = document.querySelectorAll('.course-card[data-course-id]');
        courseCards.forEach(card => {
            const title = card.querySelector('h3')?.textContent.toLowerCase() || '';
            const status = card.getAttribute('data-status');

            const matchesSearch = !searchTerm || title.includes(searchTerm);
            const matchesFilter = filterValue === 'all' || status === filterValue;

            card.style.display = matchesSearch && matchesFilter ? '' : 'none';
        });
    }

    /**
     * Debounce helper
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Mobile Navigation Functions
    function initMobileNavigation() {
        if (DOM.mobileMenuToggle) {
            DOM.mobileMenuToggle.addEventListener('click', toggleMobileNav);
        }
        if (DOM.navBackdrop) {
            DOM.navBackdrop.addEventListener('click', closeMobileNav);
        }
        window.addEventListener('resize', handleResize);
    }

    function toggleMobileNav() {
        STATE.navOpen = !STATE.navOpen;
        DOM.dashboardNav.classList.toggle('active', STATE.navOpen);
        DOM.navBackdrop.classList.toggle('active', STATE.navOpen);
        document.body.style.overflow = STATE.navOpen ? 'hidden' : '';
    }

    function closeMobileNav() {
        STATE.navOpen = false;
        DOM.dashboardNav.classList.remove('active');
        DOM.navBackdrop.classList.remove('active');
        document.body.style.overflow = '';
    }

    function handleResize() {
        if (window.innerWidth > 1024 && STATE.navOpen) {
            closeMobileNav();
        }
    }

    function initSidebarNavigation() {
        DOM.navItems.forEach(item => {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                showSection(targetId);
                DOM.navItems.forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
                if (window.innerWidth <= 1024) {
                    closeMobileNav();
                }
            });
        });
    }

    function showSection(sectionId) {
        DOM.dashboardSections.forEach(section => {
            section.classList.remove('active');
        });
        const targetSection = document.getElementById(sectionId);
        if (targetSection) {
            targetSection.classList.add('active');
            STATE.currentSection = sectionId;
        }
    }

    function initSettingsTabs() {
        DOM.settingsNavItems.forEach(item => {
            item.addEventListener('click', function () {
                const panelId = this.getAttribute('data-tab');
                DOM.settingsNavItems.forEach(nav => nav.classList.remove('active'));
                DOM.settingsPanels.forEach(panel => panel.classList.remove('active'));
                this.classList.add('active');
                const targetPanel = document.querySelector('.settings-panel[data-panel="' + panelId + '"]');
                if (targetPanel) {
                    targetPanel.classList.add('active');
                    STATE.currentSettingsPanel = panelId;
                }
            });
        });
    }

    /**
     * Initialize TinyMCE for course detail fields
     */
    function initTinyMCE() {
        // Only init if TinyMCE is available and editors exist
        if (typeof tinymce === 'undefined') return;

        const editorSelectors = [
            '#editCourseLongDescription',
            '#editLearningObjectives',
            '#editCourseRequirements',
            '#editTargetAudience'
        ];

        // Remove any existing instances first
        editorSelectors.forEach(selector => {
            const existingEditor = tinymce.get(selector.replace('#', ''));
            if (existingEditor) {
                existingEditor.remove();
            }
        });

        // Initialize TinyMCE
        tinymce.init({
            selector: editorSelectors.join(','),
            height: 200,
            menubar: false,
            promotion: false,
            branding: false,
            skin: 'oxide-dark',
            content_css: 'dark',
            plugins: [
                'lists', 'link', 'autolink', 'autoresize'
            ],
            toolbar: 'undo redo | bold italic underline | bullist numlist | link | removeformat',
            toolbar_mode: 'sliding',
            autoresize_bottom_margin: 10,
            // Only allow these HTML elements - prevents unwanted hr, data attributes, etc.
            valid_elements: 'p,br,strong/b,em/i,u,ul,ol,li,a[href|target],span[style]',
            // Disable paste formatting that adds unwanted attributes
            paste_as_text: false,
            paste_remove_styles_if_webkit: true,
            paste_strip_class_attributes: 'all',
            content_style: `
                body { 
                    font-family: Inter, -apple-system, BlinkMacSystemFont, sans-serif; 
                    font-size: 14px; 
                    color: #e4e4e4;
                    background-color: #1a1a2e;
                    margin: 8px;
                }
                ul, ol { margin-left: 20px; padding-left: 10px; }
                li { margin-bottom: 4px; }
                a { color: #6366f1; }
            `,
            setup: function (editor) {
                // Style the container
                editor.on('init', function () {
                    const container = editor.getContainer();
                    if (container) {
                        container.style.borderRadius = '8px';
                        container.style.overflow = 'hidden';
                    }
                });
            }
        });
    }

    /**
     * Get content from TinyMCE editor or textarea
     */
    function getEditorContent(elementId) {
        if (typeof tinymce !== 'undefined') {
            const editor = tinymce.get(elementId);
            if (editor) {
                return editor.getContent();
            }
        }
        const el = document.getElementById(elementId);
        return el ? el.value : '';
    }

    /**
     * Set content to TinyMCE editor or textarea
     */
    function setEditorContent(elementId, content) {
        if (typeof tinymce !== 'undefined') {
            const editor = tinymce.get(elementId);
            if (editor) {
                editor.setContent(content || '');
                return;
            }
        }
        const el = document.getElementById(elementId);
        if (el) el.value = content || '';
    }

    function initNotificationFilters() {
        DOM.filterBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                DOM.filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });
    }

    function initEventListeners() {
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (STATE.navOpen) closeMobileNav();
                document.querySelectorAll('.modal').forEach(modal => {
                    if (modal.style.display === 'flex') {
                        modal.style.display = 'none';
                    }
                });
            }
        });

        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });
    }

    function showInitialSection() {
        const overviewSection = document.getElementById('overview');
        if (overviewSection) overviewSection.classList.add('active');
        const overviewNavItem = document.querySelector('.nav-item[href="#overview"]');
        if (overviewNavItem) overviewNavItem.classList.add('active');
        const firstSettingsPanel = document.querySelector('.settings-panel[data-panel="general"]');
        if (firstSettingsPanel) firstSettingsPanel.classList.add('active');
        const firstSettingsNavItem = document.querySelector('.settings-nav-item[data-tab="general"]');
        if (firstSettingsNavItem) firstSettingsNavItem.classList.add('active');
    }

    // Helper function to show notifications
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = 'toast-notification ' + type;
        notification.innerHTML = `
            <span class="material-icons">${type === 'success' ? 'check_circle' : 'error'}</span>
            <span>${message}</span>
        `;
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: ${type === 'success' ? '#28A745' : '#D9534F'};
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 3000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        `;
        document.body.appendChild(notification);
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Public functions
    window.markAsRead = function (element) {
        const notificationItem = element.closest('.notification-item');
        if (notificationItem) notificationItem.classList.remove('unread');
    };

    window.markAllAsRead = function () {
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.classList.remove('unread');
        });
    };

    window.clearAllNotifications = function () {
        if (confirm('Are you sure you want to clear all notifications?')) {
            const notificationsList = document.querySelector('.notifications-list');
            if (notificationsList) {
                notificationsList.innerHTML = '<p style="color: var(--text-color); text-align: center; padding: 40px;">No notifications</p>';
            }
        }
    };

    window.editProfile = function () {
        const modal = document.getElementById('editPersonalModal');
        if (modal) modal.style.display = 'flex';
    };

    window.changeProfilePhoto = function () {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = function (e) {
            const file = e.target.files[0];
            if (file) {
                const formData = new FormData();
                formData.append('action', 'upload_photo');
                formData.append('photo', file);

                ajax(AJAX_BASE + 'profile-handler.php', { method: 'POST', data: formData })
                    .then(response => {
                        if (response.success) {
                            const avatar = document.querySelector('.profile-avatar-large');
                            if (avatar) avatar.src = response.photo_url;
                            const navAvatar = document.querySelector('.profile-img');
                            if (navAvatar) navAvatar.src = response.photo_url;
                            showNotification('Profile photo updated!', 'success');
                        } else {
                            showNotification(response.error || 'Failed to upload photo', 'error');
                        }
                    })
                    .catch(() => showNotification('Failed to upload photo', 'error'));
            }
        };
        input.click();
    };

    window.editSection = function (section) {
        const modalMap = {
            'personal': 'editPersonalModal',
            'professional': 'editProfessionalModal',
            'bio': 'editBioModal',
            'social': 'editSocialModal'
        };
        const modalId = modalMap[section];
        if (modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.style.display = 'flex';
        }
    };

    window.changePassword = function () {
        const modal = document.getElementById('changePasswordModal');
        if (modal) modal.style.display = 'flex';
    };

    window.enable2FA = function () {
        showNotification('Two-Factor Authentication will be available soon.', 'info');
    };

    window.savePersonalInfo = function (event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'update_personal');

        // Parse full name into first/last
        const fullName = formData.get('fullName') || '';
        const nameParts = fullName.trim().split(' ');
        formData.append('first_name', nameParts[0] || '');
        formData.append('last_name', nameParts.slice(1).join(' ') || '');

        ajax(AJAX_BASE + 'profile-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    // Update UI
                    const nameElement = document.querySelector('.profile-details h2');
                    const emailElement = document.querySelector('.profile-email');
                    if (nameElement) nameElement.textContent = fullName;
                    if (emailElement) emailElement.textContent = formData.get('email');

                    closeModal('editPersonalModal');
                    showNotification('Personal information updated!', 'success');
                } else {
                    showNotification(response.error || 'Failed to update', 'error');
                }
            })
            .catch(() => showNotification('Failed to update personal info', 'error'));
    };

    window.saveProfessionalInfo = function (event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'update_professional');

        ajax(AJAX_BASE + 'profile-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    closeModal('editProfessionalModal');
                    showNotification('Professional info updated!', 'success');
                    location.reload();
                } else {
                    showNotification(response.error || 'Failed to update', 'error');
                }
            })
            .catch(() => showNotification('Failed to update', 'error'));
    };

    window.saveBioInfo = function (event) {
        event.preventDefault();
        const bio = document.getElementById('editBio')?.value || '';
        const formData = new FormData();
        formData.append('action', 'update_bio');
        formData.append('bio', bio);

        ajax(AJAX_BASE + 'profile-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    const bioText = document.querySelector('.bio-text');
                    if (bioText) bioText.textContent = bio;
                    closeModal('editBioModal');
                    showNotification('Biography updated!', 'success');
                } else {
                    showNotification(response.error || 'Failed to update', 'error');
                }
            })
            .catch(() => showNotification('Failed to update biography', 'error'));
    };

    window.saveSocialInfo = function (event) {
        event.preventDefault();
        closeModal('editSocialModal');
        showNotification('Social links updated!', 'success');
    };

    window.savePassword = function (event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'change_password');

        ajax(AJAX_BASE + 'profile-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    closeModal('changePasswordModal');
                    form.reset();
                    showNotification('Password changed successfully!', 'success');
                } else {
                    showNotification(response.error || 'Failed to change password', 'error');
                }
            })
            .catch(() => showNotification('Failed to change password', 'error'));
    };

    /**
     * Toggle password visibility
     */
    window.togglePassword = function (fieldId) {
        const input = document.getElementById(fieldId);
        const button = input.parentElement.querySelector('.password-toggle');
        const icon = button.querySelector('.material-icons');

        if (input.type === 'password') {
            input.type = 'text';
            icon.textContent = 'visibility_off';
        } else {
            input.type = 'password';
            icon.textContent = 'visibility';
        }
    };

    window.showCreateCourseModal = function () {
        const modal = document.getElementById('createCourseModal');
        const form = document.getElementById('newCourseForm');

        if (modal && form) {
            // Reset form
            form.reset();

            // Reset UI elements
            const descCount = document.getElementById('newCourseDescCount');
            if (descCount) descCount.textContent = '0';

            const discountInfo = document.getElementById('newCourseDiscountInfo');
            if (discountInfo) discountInfo.style.display = 'none';

            const priceFields = document.getElementById('newCoursePriceFields');
            if (priceFields) priceFields.style.display = 'block';

            // Reset thumbnail preview
            const thumbnailPreview = document.getElementById('newThumbnailPreview');
            if (thumbnailPreview) thumbnailPreview.style.display = 'none';

            // Show modal
            modal.style.display = 'flex';
        }
    };
    // ========================================
    // COURSE CREATION HANDLER - NEW
    // ========================================

    /**
     * Create new course handler
     */
    function createCourseHandler(event) {
        event.preventDefault();
        event.stopPropagation();

        const form = event.target;
        const submitBtn = document.getElementById('newCourseSubmitBtn');

        // Disable submit
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Creating...';

        // Gather form data
        const formData = new FormData();
        formData.append('action', 'create_course');
        formData.append('title', document.getElementById('newCourseTitle').value.trim());
        formData.append('category_id', document.getElementById('newCourseCategory').value);
        formData.append('level', document.getElementById('newCourseLevel').value);
        formData.append('language', document.getElementById('newCourseLanguage').value);
        formData.append('duration_hours', document.getElementById('newCourseDuration').value || '0');
        formData.append('description', document.getElementById('newCourseDescription').value.trim());

        // Thumbnail
        const thumbnailInput = document.getElementById('newCourseThumbnail');
        if (thumbnailInput && thumbnailInput.files[0]) {
            formData.append('thumbnail', thumbnailInput.files[0]);
        }

        // Pricing
        const pricingType = document.querySelector('input[name=\"pricing_type\"]:checked').value;
        if (pricingType === 'free') {
            formData.append('is_free', '1');
            formData.append('price', '0');
            formData.append('original_price', '0');
        } else {
            formData.append('is_free', '0');
            const price = parseFloat(document.getElementById('newCoursePrice').value) || 0;
            const origPrice = parseFloat(document.getElementById('newCourseOriginalPrice').value) || 0;

            formData.append('price', price);
            formData.append('original_price', origPrice > 0 ? origPrice : price);
        }

        // Send AJAX request
        ajax(AJAX_BASE + 'course-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    showNotification('Course created successfully!', 'success');
                    closeModal('createCourseModal');
                    form.reset();

                    // Reload to show new course
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification(response.error || 'Failed to create course', 'error');
                }
            })
            .catch(error => {
                console.error('Create course error:', error);
                showNotification('Failed to create course', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span class=\"material-icons\">add</span> Create Course';
            });
    }

    /**
     * Edit course handler
     */
    function editCourseHandler(event) {
        event.preventDefault();
        event.stopPropagation();

        const form = event.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const courseId = document.getElementById('editCourseId').value;

        // Disable submit
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Updating...';

        // Gather form data
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('course_id', courseId);
        formData.append('title', document.getElementById('editCourseTitle').value.trim());
        formData.append('category_id', document.getElementById('editCourseCategory').value);
        formData.append('level', document.getElementById('editCourseLevel').value);
        formData.append('price', document.getElementById('editCoursePrice').value);
        formData.append('status', document.getElementById('editCourseStatus').value);
        formData.append('description', document.getElementById('editCourseDescription').value.trim());

        // New detail fields (from TinyMCE editors)
        formData.append('long_description', getEditorContent('editCourseLongDescription'));
        formData.append('learning_objectives', getEditorContent('editLearningObjectives'));
        formData.append('requirements', getEditorContent('editCourseRequirements'));
        formData.append('target_audience', getEditorContent('editTargetAudience'));

        // Thumbnail (if new file selected)
        const thumbnailInput = document.getElementById('editCourseThumbnail');
        if (thumbnailInput && thumbnailInput.files[0]) {
            formData.append('thumbnail', thumbnailInput.files[0]);
        }

        // Send AJAX request
        ajax(AJAX_BASE + 'course-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    showNotification('Course updated successfully!', 'success');
                    closeModal('editCourseModal');

                    // Reload to show updated course
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification(response.error || 'Failed to update course', 'error');
                }
            })
            .catch(error => {
                console.error('Update course error:', error);
                showNotification('Failed to update course', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span class="material-icons">save</span> Update Course';
            });
    }

    /**
     * Toggle pricing fields for new course
     */
    function toggleNewCoursePricing() {
        const pricingType = document.querySelector('input[name=\"pricing_type\"]:checked').value;
        const priceFields = document.getElementById('newCoursePriceFields');
        const priceInput = document.getElementById('newCoursePrice');

        if (pricingType === 'free') {
            priceFields.style.display = 'none';
            if (priceInput) priceInput.required = false;
        } else {
            priceFields.style.display = 'block';
            if (priceInput) priceInput.required = true;
        }

        calculateNewCourseDiscount();
    }

    /**
     * Calculate discount for new course
     */
    function calculateNewCourseDiscount() {
        const price = parseFloat(document.getElementById('newCoursePrice')?.value) || 0;
        const originalPrice = parseFloat(document.getElementById('newCourseOriginalPrice')?.value) || 0;
        const discountInfo = document.getElementById('newCourseDiscountInfo');
        const discountText = document.getElementById('newCourseDiscountText');

        if (originalPrice > price && price > 0) {
            const discountPercent = Math.round(((originalPrice - price) / originalPrice) * 100);
            const savings = (originalPrice - price).toFixed(2);

            if (discountText) {
                discountText.textContent = `${discountPercent}% OFF - Students save $${savings}`;
            }
            if (discountInfo) {
                discountInfo.style.display = 'flex';
            }
        } else {
            if (discountInfo) {
                discountInfo.style.display = 'none';
            }
        }
    }

    // ========================================
    // PRICING UPDATE HANDLER - NEW
    // ========================================

    /**
     * Edit course pricing
     */
    window.editPricing = function (courseId) {
        // Fetch course details
        ajax(AJAX_BASE + 'course-handler.php?action=get_course&course_id=' + courseId)
            .then(response => {
                if (response.success && response.course) {
                    const course = response.course;

                    // Populate form
                    document.getElementById('updatePricingCourseId').value = course.course_id;
                    document.getElementById('updatePricingCourseName').textContent = course.title;
                    document.getElementById('updatePricingFree').checked = course.is_free == 1 || course.price == 0;
                    document.getElementById('updatePricingPrice').value = course.price || '0';
                    document.getElementById('updatePricingOriginal').value = course.original_price || '';

                    toggleUpdatePricingFields();
                    calculateUpdateDiscount();

                    // Show modal
                    document.getElementById('editPricingModal').style.display = 'flex';
                } else {
                    showNotification('Failed to load course details', 'error');
                }
            })
            .catch(error => {
                console.error('Load course error:', error);
                showNotification('Failed to load course details', 'error');
            });
    };

    /**
     * Update pricing form handler
     */
    function updatePricingHandler(event) {
        event.preventDefault();
        event.stopPropagation();

        const form = event.target;
        const submitBtn = document.getElementById('updatePricingSubmitBtn');
        const courseId = document.getElementById('updatePricingCourseId').value;
        const isFree = document.getElementById('updatePricingFree').checked;

        // Disable submit
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class=\"material-icons\">hourglass_empty</span> Updating...';

        // Build form data
        const formData = new FormData();
        formData.append('action', 'update_pricing');
        formData.append('course_id', courseId);
        formData.append('is_free', isFree ? '1' : '0');

        if (!isFree) {
            const price = parseFloat(document.getElementById('updatePricingPrice').value) || 0;
            const originalPrice = parseFloat(document.getElementById('updatePricingOriginal').value) || 0;

            // Validation
            if (price < 0) {
                showNotification('Price cannot be negative', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span class=\"material-icons\">save</span> Update Pricing';
                return;
            }

            if (originalPrice > 0 && originalPrice < price) {
                showNotification('Original price must be higher than current price', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span class=\"material-icons\">save</span> Update Pricing';
                return;
            }

            formData.append('price', price);
            formData.append('original_price', originalPrice > 0 ? originalPrice : price);
        } else {
            formData.append('price', '0');
            formData.append('original_price', '0');
        }

        // Send AJAX
        ajax(AJAX_BASE + 'course-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    showNotification('Pricing updated successfully', 'success');
                    closeModal('editPricingModal');

                    // Reload to show updated pricing
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification(response.error || 'Failed to update pricing', 'error');
                }
            })
            .catch(error => {
                console.error('Update pricing error:', error);
                showNotification('Failed to update pricing', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span class=\"material-icons\">save</span> Update Pricing';
            });
    }

    // ========================================
    // PRICING HISTORY HANDLER - NEW
    // ========================================

    /**
     * View pricing history for a course
     */
    window.viewPricingHistory = function (courseId) {
        const modal = document.getElementById('pricingHistoryModal');
        const timeline = document.getElementById('historyTimeline');

        if (!modal || !timeline) {
            showNotification('Price history modal not found', 'error');
            return;
        }

        // Show modal with loading state
        modal.style.display = 'flex';
        timeline.innerHTML = '<div class="history-loading"><span class="material-icons rotating">sync</span><span>Loading history...</span></div>';

        // Reset stats
        document.getElementById('historyMinPrice').textContent = '৳--';
        document.getElementById('historyMaxPrice').textContent = '৳--';
        document.getElementById('historyAvgPrice').textContent = '৳--';
        document.getElementById('historyTotalChanges').textContent = '--';
        document.getElementById('historyCourseName').textContent = 'Loading...';
        document.getElementById('historyCurrentPrice').textContent = '--';

        // Fetch price history
        ajax(AJAX_BASE + 'revenue-handler.php?action=get_price_history&course_id=' + courseId)
            .then(response => {
                if (response.success) {
                    // Update course info
                    document.getElementById('historyCourseName').textContent = response.course.title;

                    if (response.course.is_free) {
                        document.getElementById('historyCurrentPrice').innerHTML = '<span class="free-badge">FREE</span>';
                    } else {
                        let priceHtml = '৳' + response.course.current_price.toFixed(2);
                        if (response.course.original_price && response.course.original_price > response.course.current_price) {
                            const discount = Math.round(((response.course.original_price - response.course.current_price) / response.course.original_price) * 100);
                            priceHtml += ' <span class="original-strike">৳' + response.course.original_price.toFixed(2) + '</span>';
                            priceHtml += ' <span class="discount-badge">' + discount + '% OFF</span>';
                        }
                        document.getElementById('historyCurrentPrice').innerHTML = priceHtml;
                    }

                    // Update stats
                    if (response.stats) {
                        document.getElementById('historyMinPrice').textContent = '৳' + response.stats.min_price.toFixed(2);
                        document.getElementById('historyMaxPrice').textContent = '৳' + response.stats.max_price.toFixed(2);
                        document.getElementById('historyAvgPrice').textContent = '৳' + response.stats.avg_price.toFixed(2);
                        document.getElementById('historyTotalChanges').textContent = response.stats.total_changes;
                    }

                    // Render timeline
                    renderPricingTimeline(response.history, response.course);
                } else {
                    timeline.innerHTML = '<div class="history-empty"><span class="material-icons">info</span><span>' + (response.error || 'Failed to load price history') + '</span></div>';
                }
            })
            .catch(error => {
                console.error('Load price history error:', error);
                timeline.innerHTML = '<div class="history-empty"><span class="material-icons">error</span><span>Failed to load price history</span></div>';
            });
    };

    /**
     * Render pricing timeline
     */
    function renderPricingTimeline(history, course) {
        const timeline = document.getElementById('historyTimeline');

        if (!history || history.length === 0) {
            timeline.innerHTML = `
                <div class="history-empty">
                    <span class="material-icons">history</span>
                    <div>
                        <strong>No price changes recorded yet</strong>
                        <p>Price history will appear here when you update the course pricing.</p>
                    </div>
                </div>
            `;
            return;
        }

        let html = '';

        history.forEach((item, index) => {
            const date = new Date(item.changed_at);
            const formattedDate = date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            // Determine icon and color based on change type
            let icon, colorClass, changeDescription;

            switch (item.change_type) {
                case 'made_free':
                    icon = 'card_giftcard';
                    colorClass = 'success';
                    changeDescription = 'Made course free';
                    break;
                case 'made_paid':
                    icon = 'monetization_on';
                    colorClass = 'primary';
                    changeDescription = 'Changed to paid course';
                    break;
                case 'price_increase':
                    icon = 'trending_up';
                    colorClass = 'warning';
                    changeDescription = 'Price increased';
                    break;
                case 'price_decrease':
                    icon = 'trending_down';
                    colorClass = 'success';
                    changeDescription = 'Price decreased';
                    break;
                case 'discount_change':
                    icon = 'local_offer';
                    colorClass = 'info';
                    changeDescription = 'Discount updated';
                    break;
                default:
                    icon = 'edit';
                    colorClass = 'default';
                    changeDescription = 'Price modified';
            }

            // Format price change
            let priceChange = '';
            if (item.old_is_free && !item.new_is_free) {
                priceChange = '<span class="old-price">FREE</span> → <span class="new-price">৳' + item.new_price.toFixed(2) + '</span>';
            } else if (!item.old_is_free && item.new_is_free) {
                priceChange = '<span class="old-price">৳' + item.old_price.toFixed(2) + '</span> → <span class="new-price free">FREE</span>';
            } else {
                priceChange = '<span class="old-price">৳' + item.old_price.toFixed(2) + '</span> → <span class="new-price">৳' + item.new_price.toFixed(2) + '</span>';
            }

            // Calculate difference
            let diffHtml = '';
            if (!item.old_is_free && !item.new_is_free) {
                const diff = item.new_price - item.old_price;
                const diffPercent = item.old_price > 0 ? Math.round((diff / item.old_price) * 100) : 0;
                if (diff > 0) {
                    diffHtml = '<span class="price-diff increase">+৳' + diff.toFixed(2) + ' (+' + diffPercent + '%)</span>';
                } else if (diff < 0) {
                    diffHtml = '<span class="price-diff decrease">-৳' + Math.abs(diff).toFixed(2) + ' (' + diffPercent + '%)</span>';
                }
            }

            html += `
                <div class="timeline-item ${colorClass}">
                    <div class="timeline-marker">
                        <span class="material-icons">${icon}</span>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <span class="timeline-title">${changeDescription}</span>
                            <span class="timeline-date">${formattedDate}</span>
                        </div>
                        <div class="timeline-price-change">
                            ${priceChange}
                            ${diffHtml}
                        </div>
                        ${item.change_reason ? '<div class="timeline-reason"><span class="material-icons">notes</span> ' + escapeHtml(item.change_reason) + '</div>' : ''}
                        <div class="timeline-footer">
                            <span class="material-icons">person</span>
                            Changed by ${escapeHtml(item.changed_by)}
                        </div>
                    </div>
                </div>
            `;
        });

        timeline.innerHTML = html;
    }

    /**
     * Escape HTML for safe rendering
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Toggle pricing fields for update form
     */
    function toggleUpdatePricingFields() {
        const isFree = document.getElementById('updatePricingFree')?.checked;
        const pricingFields = document.getElementById('updatePricingFields');
        const priceInput = document.getElementById('updatePricingPrice');

        if (isFree) {
            if (pricingFields) pricingFields.style.display = 'none';
            if (priceInput) priceInput.required = false;
        } else {
            if (pricingFields) pricingFields.style.display = 'block';
            if (priceInput) priceInput.required = true;
        }

        calculateUpdateDiscount();
    }

    /**
     * Calculate discount for update form
     */
    function calculateUpdateDiscount() {
        const price = parseFloat(document.getElementById('updatePricingPrice')?.value) || 0;
        const originalPrice = parseFloat(document.getElementById('updatePricingOriginal')?.value) || 0;
        const discountInfo = document.getElementById('updatePricingDiscountInfo');
        const discountText = document.getElementById('updatePricingDiscountText');

        if (originalPrice > price && price > 0) {
            const discountPercent = Math.round(((originalPrice - price) / originalPrice) * 100);
            const savings = (originalPrice - price).toFixed(2);

            if (discountText) {
                discountText.textContent = `${discountPercent}% OFF - Students save $${savings}`;
            }
            if (discountInfo) {
                discountInfo.style.display = 'flex';
            }
        } else {
            if (discountInfo) {
                discountInfo.style.display = 'none';
            }
        }
    }

    /**
     * Close modal
     */
    window.closeModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'none';
    };

    /**
     * Edit course - Opens edit course modal
     */
    window.editCourse = function (courseId) {
        // Fetch course details
        ajax(AJAX_BASE + 'course-handler.php?action=get_course&course_id=' + courseId)
            .then(response => {
                if (response.success && response.course) {
                    const course = response.course;

                    // Populate form
                    document.getElementById('editCourseId').value = course.course_id;
                    document.getElementById('editCourseTitle').value = course.title;
                    document.getElementById('editCourseCategory').value = course.category_id;
                    document.getElementById('editCourseLevel').value = course.level;
                    document.getElementById('editCoursePrice').value = course.price || '0';
                    document.getElementById('editCourseStatus').value = course.status;
                    document.getElementById('editCourseDescription').value = course.description || '';

                    // Show existing thumbnail if available
                    const editThumbnailPreview = document.getElementById('editThumbnailPreview');
                    const editThumbnailImage = document.getElementById('editThumbnailImage');
                    if (course.thumbnail_url && editThumbnailImage && editThumbnailPreview) {
                        editThumbnailImage.src = '../../' + course.thumbnail_url;
                        editThumbnailPreview.style.display = 'block';
                    } else if (editThumbnailPreview) {
                        editThumbnailPreview.style.display = 'none';
                    }

                    // Show modal first
                    document.getElementById('editCourseModal').style.display = 'flex';

                    // Initialize TinyMCE then set content after a small delay
                    setTimeout(() => {
                        initTinyMCE();

                        // Wait for editors to initialize before setting content
                        setTimeout(() => {
                            setEditorContent('editCourseLongDescription', course.long_description || '');
                            setEditorContent('editLearningObjectives', course.learning_objectives || '');
                            setEditorContent('editCourseRequirements', course.requirements || '');
                            setEditorContent('editTargetAudience', course.target_audience || '');
                        }, 500);
                    }, 100);
                } else {
                    showNotification('Failed to load course details', 'error');
                }
            })
            .catch(error => {
                console.error('Load course error:', error);
                showNotification('Failed to load course details', 'error');
            });
    };

    window.manageCourse = function (courseId) {
        window.location.href = 'course-manage.php?id=' + courseId;
    };

    window.deleteCourse = function (courseId, courseTitle) {
        // Store the course ID to be deleted
        window.courseToDelete = courseId;

        // Update the modal with course title
        const titleElement = document.getElementById('deleteCourseTitle');
        if (titleElement) {
            titleElement.textContent = courseTitle;
        }

        // Show the confirmation modal
        const modal = document.getElementById('deleteCourseModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    };

    window.confirmDeleteCourse = function () {
        const courseId = window.courseToDelete;
        if (!courseId) return;

        const confirmBtn = document.getElementById('confirmDeleteBtn');
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Deleting...';
        }

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('course_id', courseId);

        ajax(AJAX_BASE + 'course-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    showNotification('Course deleted successfully', 'success');
                    closeModal('deleteCourseModal');

                    // Remove the course card from the DOM
                    const courseCard = document.querySelector(`.course-card[data-course-id="${courseId}"]`);
                    if (courseCard) {
                        courseCard.style.transition = 'opacity 0.3s, transform 0.3s';
                        courseCard.style.opacity = '0';
                        courseCard.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            courseCard.remove();

                            // Check if there are no more courses and show empty state
                            const coursesGrid = document.getElementById('coursesGrid');
                            if (coursesGrid && coursesGrid.querySelectorAll('.course-card').length === 0) {
                                coursesGrid.innerHTML = `
                                    <div class="empty-state">
                                        <span class="material-icons">video_library</span>
                                        <h3>No courses yet</h3>
                                        <p>Create your first course to get started</p>
                                        <button class="btn-primary" onclick="showCreateCourseModal()">
                                            <span class="material-icons">add</span>
                                            Create Course
                                        </button>
                                    </div>
                                `;
                            }
                        }, 300);
                    }

                    // Update course count in navigation
                    const countElement = document.querySelector('.nav-item[href="#courses"] .nav-count');
                    if (countElement) {
                        const currentCount = parseInt(countElement.textContent) || 0;
                        if (currentCount > 0) {
                            countElement.textContent = currentCount - 1;
                        }
                    }
                } else {
                    showNotification(response.error || 'Failed to delete course', 'error');
                }
            })
            .catch(() => {
                showNotification('Failed to delete course', 'error');
            })
            .finally(() => {
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<span class="material-icons">delete</span> Delete Course';
                }
            });
    };

    window.viewStudentDetails = function (userId) {
        ajax(AJAX_BASE + 'student-handler.php?action=get&user_id=' + userId)
            .then(response => {
                if (response.success) {
                    const s = response.student;

                    // Populate modal with student data
                    document.getElementById('studentDetailAvatar').src = s.avatar;
                    document.getElementById('studentDetailName').textContent = s.name;
                    document.getElementById('studentDetailEmail').textContent = s.email;
                    document.getElementById('studentDetailEnrolled').textContent = 'Enrolled: ' + s.enrolled_at;

                    // Stats
                    document.getElementById('studentDetailCoursesCount').textContent = s.courses.length;
                    document.getElementById('studentDetailProgress').textContent = s.avg_progress + '%';
                    document.getElementById('studentDetailLearningTime').textContent = s.total_learning_time + 'h';

                    // Courses list
                    const coursesList = document.getElementById('studentDetailCoursesList');
                    if (s.courses.length === 0) {
                        coursesList.innerHTML = '<p style="color: rgba(228, 228, 228, 0.6); text-align: center; padding: 20px;">No courses enrolled</p>';
                    } else {
                        coursesList.innerHTML = s.courses.map(course => `
                            <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(228, 228, 228, 0.05); border-radius: 8px;">
                                <img src="${course.thumbnail}" alt="${course.title}" style="width: 60px; height: 40px; border-radius: 4px; object-fit: cover;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 500; color: var(--text-color); margin-bottom: 4px;">${course.title}</div>
                                    <div style="font-size: 12px; color: rgba(228, 228, 228, 0.6);">
                                        Enrolled: ${course.enrolled_date} • Progress: ${course.progress}%
                                    </div>
                                </div>
                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                                    <div style="width: 100px; height: 6px; background: rgba(228, 228, 228, 0.1); border-radius: 3px; overflow: hidden;">
                                        <div style="width: ${course.progress}%; height: 100%; background: linear-gradient(90deg, #6366f1, #8b5cf6);"></div>
                                    </div>
                                    <span style="font-size: 12px; color: rgba(228, 228, 228, 0.6);">${course.lectures_completed}/${course.total_lectures} lectures</span>
                                </div>
                            </div>
                        `).join('');
                    }

                    // Show modal
                    const modal = document.getElementById('studentDetailsModal');
                    if (modal) {
                        modal.style.display = 'flex';
                        document.body.style.overflow = 'hidden';
                    }
                } else {
                    showNotification(response.error || 'Failed to load student details', 'error');
                }
            })
            .catch(() => showNotification('Failed to load student details', 'error'));
    };

    window.createCoupon = function () {
        const modal = document.getElementById('createCouponModal');
        if (modal) {
            modal.style.display = 'flex';

            // Reset form
            document.getElementById('createCouponForm').reset();
            document.getElementById('couponCourseGroup').style.display = 'none';

            // Add event listener for coupon type change
            const couponTypeSelect = document.getElementById('couponType');
            const couponCourseGroup = document.getElementById('couponCourseGroup');
            const couponCourseSelect = document.getElementById('couponCourse');

            if (couponTypeSelect) {
                couponTypeSelect.addEventListener('change', function () {
                    if (this.value === 'specific') {
                        couponCourseGroup.style.display = 'block';
                        couponCourseSelect.required = true;
                    } else {
                        couponCourseGroup.style.display = 'none';
                        couponCourseSelect.required = false;
                        couponCourseSelect.value = '';
                    }
                });
            }
        }
    };

    window.submitCouponForm = function (event) {
        event.preventDefault();

        const form = document.getElementById('createCouponForm');
        const formData = new FormData(form);
        formData.append('action', 'create');

        // Get the submit button
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Creating...';

        ajax(AJAX_BASE + 'coupon-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    showNotification('Coupon created successfully!', 'success');
                    closeModal('createCouponModal');
                    form.reset();
                    loadCoupons(); // Reload the coupon list
                } else {
                    showNotification(response.error || 'Failed to create coupon', 'error');
                }
            })
            .catch(error => {
                console.error('Coupon creation error:', error);
                showNotification('Failed to create coupon. Please try again.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
    };

    window.toggleCoupon = function (couponId) {
        const formData = new FormData();
        formData.append('action', 'toggle');
        formData.append('coupon_id', couponId);

        ajax(AJAX_BASE + 'coupon-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    loadCoupons();
                    showNotification('Coupon status updated', 'success');
                } else {
                    showNotification(response.error || 'Failed to toggle', 'error');
                }
            })
            .catch(() => showNotification('Failed to toggle coupon', 'error'));
    };

    window.deleteCoupon = function (couponId) {
        if (!confirm('Are you sure you want to delete this coupon?')) return;

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('coupon_id', couponId);

        ajax(AJAX_BASE + 'coupon-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    loadCoupons();
                    showNotification('Coupon deleted', 'success');
                } else {
                    showNotification(response.error || 'Failed to delete', 'error');
                }
            })
            .catch(() => showNotification('Failed to delete coupon', 'error'));
    };

    window.filterCoupons = function () {
        const type = document.getElementById('couponTypeFilter')?.value || 'all';
        const status = document.getElementById('couponStatusFilter')?.value || 'all';
        const search = document.getElementById('couponSearchInput')?.value || '';
        loadCoupons({ type, status, search });
    };

    window.filterAnalytics = function () {
        loadAnalyticsData();
    };

    /**
     * Load complete analytics data from backend
     */
    window.loadAnalyticsData = function () {
        const startDate = document.getElementById('startDate')?.value || '';
        const endDate = document.getElementById('endDate')?.value || '';

        // Show loading states
        const statsGrid = document.getElementById('analyticsStatsGrid');
        if (statsGrid) {
            document.getElementById('analyticsStudents').textContent = '--';
            document.getElementById('analyticsEarnings').textContent = '৳--';
            document.getElementById('analyticsRating').textContent = '--';
            document.getElementById('analyticsCompletion').textContent = '--%';
        }



        // Load overview stats
        ajax(AJAX_BASE + 'analytics-handler.php?action=get_overview&start_date=' + startDate + '&end_date=' + endDate)
            .then(response => {
                if (response.success) {
                    updateAnalyticsStats(response.overview);
                }
            })
            .catch(err => console.error('Failed to load analytics overview:', err));

        // Load engagement chart data
        ajax(AJAX_BASE + 'analytics-handler.php?action=get_engagement&start_date=' + startDate + '&end_date=' + endDate)
            .then(response => {
                if (response.success) {
                    renderEngagementChart(response.engagement);
                }
            })
            .catch(err => console.error('Failed to load engagement data:', err));

        // Load completion rates
        ajax(AJAX_BASE + 'analytics-handler.php?action=get_completion')
            .then(response => {
                if (response.success) {
                    renderCompletionChart(response.completion);
                }
            })
            .catch(err => console.error('Failed to load completion data:', err));



        // Load forum/weekly activity
        ajax(AJAX_BASE + 'analytics-handler.php?action=get_forum')
            .then(response => {
                if (response.success) {
                    renderForumChart(response.forum);
                }
            })
            .catch(err => console.error('Failed to load forum data:', err));
    };

    /**
     * Update analytics stats display
     */
    function updateAnalyticsStats(overview) {
        const elements = {
            analyticsStudents: overview.total_students?.toLocaleString() || '0',
            analyticsEarnings: '৳' + (overview.total_earnings?.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) || '0.00'),
            analyticsRating: overview.avg_rating?.toFixed(1) || '0.0',
            analyticsCompletion: (overview.completion_rate || 0) + '%'
        };

        Object.keys(elements).forEach(key => {
            const el = document.getElementById(key);
            if (el) el.textContent = elements[key];
        });
    }

    /**
     * Render engagement chart with real data (doughnut chart)
     */
    function renderEngagementChart(data) {
        const ctx = document.getElementById('engagementChart');
        if (!ctx || typeof Chart === 'undefined') return;

        // Destroy existing chart if exists
        if (STATE.charts.analyticsEngagement) {
            STATE.charts.analyticsEngagement.destroy();
        }

        // Data should have: active, moderate, inactive counts
        const activeCount = data.active || 0;
        const moderateCount = data.moderate || 0;
        const inactiveCount = data.inactive || 0;

        STATE.charts.analyticsEngagement = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Moderate', 'Inactive'],
                datasets: [{
                    data: [activeCount, moderateCount, inactiveCount],
                    backgroundColor: ['#4A90E2', '#50E3C2', '#F39C12']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const total = activeCount + moderateCount + inactiveCount;
                                const percentage = total > 0 ? Math.round((context.raw / total) * 100) : 0;
                                return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Render completion chart with real data
     */
    function renderCompletionChart(data) {
        const ctx = document.getElementById('completionChart');
        if (!ctx || typeof Chart === 'undefined') return;

        // Destroy existing chart if exists
        if (STATE.charts.analyticsCompletion) {
            STATE.charts.analyticsCompletion.destroy();
        }

        if (!data || data.length === 0) {
            STATE.charts.analyticsCompletion = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['No courses yet'],
                    datasets: [{
                        label: 'Completion Rate',
                        data: [0],
                        backgroundColor: '#4A90E2',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } }
                }
            });
            return;
        }

        const labels = data.map(c => c.title.substring(0, 25) + (c.title.length > 25 ? '...' : ''));
        const values = data.map(c => c.rate || 0);

        STATE.charts.analyticsCompletion = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Completion Rate %',
                    data: values,
                    backgroundColor: values.map(v => v >= 70 ? '#4CAF50' : v >= 40 ? '#FF9800' : '#F44336'),
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, max: 100 } }
            }
        });
    }



    /**
     * Render forum/weekly activity chart
     */
    function renderForumChart(data) {
        const ctx = document.getElementById('forumChart');
        if (!ctx || typeof Chart === 'undefined') return;

        // Destroy existing chart if exists
        if (STATE.charts.analyticsForum) {
            STATE.charts.analyticsForum.destroy();
        }

        const labels = data.map(d => d.label);
        const values = data.map(d => d.activity || 0);

        STATE.charts.analyticsForum = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Activity',
                    data: values,
                    backgroundColor: '#50E3C2',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    /**
     * Export analytics report
     */
    window.exportReport = function (format) {
        if (format === 'csv') {
            // Generate CSV from current analytics data
            ajax(AJAX_BASE + 'analytics-handler.php?action=get_all')
                .then(response => {
                    if (response.success) {
                        generateCSVReport(response);
                    }
                })
                .catch(err => {
                    console.error('Failed to export report:', err);
                    showNotification('Failed to export report', 'error');
                });
        } else if (format === 'pdf') {
            showNotification('PDF export coming soon. Use CSV for now.', 'info');
        }
    };

    /**
     * Generate and download CSV report
     */
    function generateCSVReport(data) {
        let csv = 'EduVerse Analytics Report\n';
        csv += 'Generated: ' + new Date().toLocaleString() + '\n\n';

        // Engagement data
        if (data.engagement && data.engagement.length > 0) {
            csv += 'Daily Enrollments\n';
            csv += 'Date,Enrollments\n';
            data.engagement.forEach(row => {
                csv += row.label + ',' + (row.value || row.enrollments || 0) + '\n';
            });
            csv += '\n';
        }

        // Completion data
        if (data.completion && data.completion.length > 0) {
            csv += 'Course Completion Rates\n';
            csv += 'Course,Enrolled,Completed,Rate%\n';
            data.completion.forEach(row => {
                csv += '"' + row.title + '",' + row.total_enrolled + ',' + row.completed + ',' + row.rate + '\n';
            });
            csv += '\n';
        }

        // Download
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'analytics_report_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        showNotification('Report downloaded successfully', 'success');
    }

    // Auto-load analytics when section becomes visible
    (function () {
        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.target.id === 'analytics' && mutation.target.classList.contains('active')) {
                    loadAnalyticsData();
                }
            });
        });

        const analyticsSection = document.getElementById('analytics');
        if (analyticsSection) {
            observer.observe(analyticsSection, { attributes: true, attributeFilter: ['class'] });
        }
    })();

    window.createStudentGroup = function () {
        showNotification('Student groups feature coming soon', 'info');
    };

    window.editPaymentMethod = function (method) {
        showNotification('Payment method editing coming soon', 'info');
    };

    window.addPaymentMethod = function () {
        showNotification('Add payment method coming soon', 'info');
    };

    window.filterPricingCourses = function () {
        const filterValue = document.getElementById('pricingCourseFilter')?.value || 'all';
        const searchTerm = document.getElementById('pricingSearchInput')?.value.toLowerCase() || '';
        const pricingItems = document.querySelectorAll('.pricing-item');

        pricingItems.forEach(item => {
            const title = item.querySelector('h4')?.textContent.toLowerCase() || '';
            const isFree = item.getAttribute('data-is-free') === '1';
            const hasDiscount = item.getAttribute('data-has-discount') === '1';

            const matchesSearch = !searchTerm || title.includes(searchTerm);
            let matchesFilter = true;

            if (filterValue === 'free') {
                matchesFilter = isFree;
            } else if (filterValue === 'paid') {
                matchesFilter = !isFree;
            } else if (filterValue === 'discounted') {
                matchesFilter = hasDiscount;
            }

            item.style.display = matchesSearch && matchesFilter ? '' : 'none';
        });
    };

    window.bulkPricingUpdate = function () {
        showNotification('Bulk pricing update feature coming soon', 'info');
    };

    window.viewPricingHistory = function (courseId) {
        showNotification('Pricing history feature coming soon', 'info');
    };

    /**
     * Load notifications from backend
     */
    function loadNotifications(filter) {
        filter = filter || STATE.currentNotificationFilter || 'all';
        STATE.currentNotificationFilter = filter;

        ajax(AJAX_BASE + 'notification-handler.php?action=list&filter=' + filter + '&limit=30')
            .then(response => {
                if (response.success) {
                    STATE.notifications = response.notifications;
                    renderNotifications(response.notifications);
                    updateNotificationBadge(response.unread_count);
                }
            })
            .catch(err => {
                console.error('Failed to load notifications:', err);
                renderEmptyNotifications();
            });
    }

    /**
     * Render notifications list
     */
    function renderNotifications(notifications) {
        const container = document.getElementById('notificationsList');
        if (!container) return;

        if (notifications.length === 0) {
            renderEmptyNotifications();
            return;
        }

        container.innerHTML = notifications.map(n => `
            <div class="notification-item ${n.is_read ? '' : 'unread'}" data-notification-id="${n.id}" data-type="${n.type}">
                <div class="notification-icon" style="background: ${n.color};">
                    <span class="material-icons">${n.icon}</span>
                </div>
                <div class="notification-content">
                    <h4>${n.title}</h4>
                    <p>${n.message}</p>
                    <span class="notification-time">${n.time_ago}</span>
                </div>
                <div class="notification-actions">
                    <button class="btn-icon" onclick="markNotificationRead('${n.id}', this)" title="${n.is_read ? 'Already read' : 'Mark as read'}">
                        <span class="material-icons">${n.is_read ? 'check' : 'mark_email_read'}</span>
                    </button>
                    <button class="btn-icon btn-delete" onclick="deleteNotification('${n.id}', this)" title="Delete notification">
                        <span class="material-icons">close</span>
                    </button>
                </div>
            </div>
        `).join('');
    }

    function renderEmptyNotifications() {
        const container = document.getElementById('notificationsList');
        if (container) {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="material-icons">notifications_none</span>
                    <h3>No notifications</h3>
                    <p>You're all caught up!</p>
                </div>
            `;
        }
    }

    /**
     * Update notification badge in nav
     */
    function updateNotificationBadge(count) {
        const badge = document.querySelector('.nav-item[href="#notifications"] .nav-notification');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? '' : 'none';
        }
    }

    /**
     * Mark single notification as read
     */
    window.markNotificationRead = function (notificationId, btn) {
        const formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('notification_id', notificationId);

        ajax(AJAX_BASE + 'notification-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    const item = btn.closest('.notification-item');
                    if (item) {
                        item.classList.remove('unread');
                        btn.querySelector('.material-icons').textContent = 'check';
                    }
                    // Update badge
                    const badge = document.querySelector('.nav-item[href="#notifications"] .nav-notification');
                    if (badge) {
                        const current = parseInt(badge.textContent) || 0;
                        if (current > 0) badge.textContent = current - 1;
                        if (current - 1 <= 0) badge.style.display = 'none';
                    }
                }
            });
    };

    /**
     * Mark all notifications as read - override global function
     */
    window.markAllAsRead = function () {
        const ids = STATE.notifications.filter(n => !n.is_read).map(n => n.id).join(',');
        if (!ids) {
            showNotification('All notifications already read', 'info');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'mark_all_read');
        formData.append('notification_ids', ids);

        ajax(AJAX_BASE + 'notification-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        const icon = item.querySelector('.btn-icon .material-icons');
                        if (icon) icon.textContent = 'check';
                    });
                    updateNotificationBadge(0);
                    showNotification('All notifications marked as read', 'success');
                }
            });
    };

    /**
     * Clear all notifications - override global function
     */
    window.clearAllNotifications = function () {
        if (!confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
            return;
        }

        const ids = STATE.notifications.map(n => n.id).join(',');

        const formData = new FormData();
        formData.append('action', 'clear_all');
        formData.append('notification_ids', ids);

        ajax(AJAX_BASE + 'notification-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    STATE.notifications = [];
                    renderEmptyNotifications();
                    updateNotificationBadge(0);
                    showNotification('All notifications cleared', 'success');
                } else {
                    showNotification('Failed to clear notifications', 'error');
                }
            })
            .catch(err => {
                console.error('Error clearing notifications:', err);
                showNotification('Failed to clear notifications', 'error');
            });
    };

    /**
     * Delete single notification
     */
    window.deleteNotification = function (notificationId, btn) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('notification_id', notificationId);

        ajax(AJAX_BASE + 'notification-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    const item = btn.closest('.notification-item');
                    if (item) {
                        // Animate removal
                        item.style.transition = 'all 0.3s ease';
                        item.style.opacity = '0';
                        item.style.transform = 'translateX(20px)';
                        setTimeout(() => {
                            item.remove();
                            // Update state
                            STATE.notifications = STATE.notifications.filter(n => n.id != notificationId);
                            // Check if list is empty
                            if (STATE.notifications.length === 0) {
                                renderEmptyNotifications();
                            }
                            // Update unread count
                            const unreadCount = STATE.notifications.filter(n => !n.is_read).length;
                            updateNotificationBadge(unreadCount);
                        }, 300);
                    }
                    showNotification('Notification deleted', 'success');
                } else {
                    showNotification('Failed to delete notification', 'error');
                }
            })
            .catch(err => {
                console.error('Error deleting notification:', err);
                showNotification('Failed to delete notification', 'error');
            });
    };

    /**
     * Filter notifications - called by filter buttons
     */
    window.filterNotificationsList = function (filter) {
        STATE.currentNotificationFilter = filter;
        loadNotifications(filter);

        // Update URL hash
        if (filter !== 'all') {
            history.replaceState(null, '', '#notifications/' + filter);
        } else {
            history.replaceState(null, '', '#notifications');
        }
    };

    /**
     * Initialize notification filter buttons with AJAX
     */
    function initNotificationFilters() {
        const filterBtns = document.querySelectorAll('.notification-filters .filter-btn');
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const filter = this.getAttribute('data-filter');
                filterNotificationsList(filter);
            });
        });
    }

    /**
     * Handle URL hash changes for navigation
     */
    function handleHashChange() {
        const hash = window.location.hash.substring(1);
        if (hash) {
            const parts = hash.split('/');
            const sectionId = parts[0];

            // Show the section
            showSection(sectionId);

            // Update nav active state
            DOM.navItems.forEach(nav => nav.classList.remove('active'));
            const activeNav = document.querySelector('.nav-item[href="#' + sectionId + '"]');
            if (activeNav) activeNav.classList.add('active');

            // Handle sub-filters (like notifications/unread)
            if (parts[1] && sectionId === 'notifications') {
                const filterBtn = document.querySelector('.filter-btn[data-filter="' + parts[1] + '"]');
                if (filterBtn) filterBtn.click();
            }
        }
    }

    /**
     * Update showSection to also update URL hash
     */
    const originalShowSection = showSection;
    function showSection(sectionId) {
        DOM.dashboardSections.forEach(section => {
            section.classList.remove('active');
        });
        const targetSection = document.getElementById(sectionId);
        if (targetSection) {
            targetSection.classList.add('active');
            STATE.currentSection = sectionId;
            // Update URL hash
            history.pushState(null, '', '#' + sectionId);
        }
    }

    // Listen for hash changes
    window.addEventListener('hashchange', handleHashChange);

    // Check hash on initial load
    if (window.location.hash) {
        setTimeout(handleHashChange, 100);
    }

    // Initialize on DOM load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
