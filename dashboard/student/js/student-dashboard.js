
const StudentDashboard = {
    baseUrl: window.EDUVERSE_BASE_URL || '/',
    ajaxUrl: 'ajax/dashboard_handler.php',
    charts: {},
    enrolledCourses: [], // Cache enrolled courses

    getThumbnailUrl: function (thumbnail) {
        if (!thumbnail) return '';
        // If already a full URL, return as is
        if (thumbnail.startsWith('http://') || thumbnail.startsWith('https://')) {
            return thumbnail;
        }
        // Otherwise prepend base URL
        return this.baseUrl + thumbnail;
    },

    /**
     * Show toast notification
     */
    showToast: function (type, message) {
        // Remove existing toasts
        $('.dashboard-toast').remove();

        const icon = type === 'success' ? 'check_circle' : (type === 'error' ? 'error' : 'info');
        const toast = $(`
            <div class="dashboard-toast toast-${type}">
                <span class="material-icons">${icon}</span>
                <span class="toast-message">${message}</span>
            </div>
        `);

        $('body').append(toast);

        // Animate in
        setTimeout(() => toast.addClass('show'), 10);

        // Auto remove after 3 seconds
        setTimeout(() => {
            toast.removeClass('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },


    init: function () {
        this.loadDashboardStats();
        this.loadContinueLearning();
        this.loadRecommendations();
        this.loadRecentActivity();
        this.loadNotifications();
        this.loadStreakData();
        this.loadLearningAnalytics('month');

        this.bindEvents();
        this.initCharts();
    },


    bindEvents: function () {
        const self = this;

        // Tab navigation
        $(document).on('click', '.nav-item[data-tab]', function (e) {
            e.preventDefault();
            const tab = $(this).data('tab');
            self.switchTab(tab);
        });

        // Period selector for analytics
        $(document).on('click', '.period-btn', function () {
            $('.period-btn').removeClass('active');
            $(this).addClass('active');
            const period = $(this).data('period');
            self.loadLearningAnalytics(period);
        });

        // Course filter tabs
        $(document).on('click', '.filter-tab', function () {
            $('.filter-tab').removeClass('active');
            $(this).addClass('active');
            const filter = $(this).data('filter');
            self.loadAllCourses(filter);
        });

        // Notes filter
        $(document).on('click', '.note-filter-btn', function () {
            $('.note-filter-btn').removeClass('active');
            $(this).addClass('active');
            const filter = $(this).data('filter');
            self.loadNotes(filter);
        });

        // Notes sort
        $(document).on('change', '.notes-sort', function () {
            self.loadNotes($('.note-filter-btn.active').data('filter'), $(this).val());
        });

        // Notes search
        let searchTimeout;
        $(document).on('input', '.notes-search-input', function () {
            clearTimeout(searchTimeout);
            const query = $(this).val();
            searchTimeout = setTimeout(function () {
                self.loadNotes($('.note-filter-btn.active').data('filter'), $('.notes-sort').val(), query);
            }, 300);
        });

        // Continue learning button


        // Download certificate
        $(document).on('click', '.btn-download-cert', function () {
            const certId = $(this).data('cert-id');
            window.open(`../certificate/download.php?id=${certId}`, '_blank');
        });

        // Mark notification as read on click
        $(document).on('click', '.notification-item, .notification-full-item', function (e) {
            if ($(e.target).closest('.mark-read').length) return;
            const notifId = $(this).data('id');
            const $item = $(this);
            if (notifId && $item.hasClass('unread')) {
                self.markNotificationRead(notifId, $item);
            }
            // Navigate if has link
            const link = $(this).data('link');
            if (link) {
                window.location.href = link;
            }
        });

        // Mark all notifications as read
        $(document).on('click', '#btnMarkAllRead', function () {
            self.markAllNotificationsRead();
        });

        // Note modal handlers - works with existing profile-modal structure
        $(document).on('click', '#btnNewNote', function () {
            self.showNoteModal();
        });

        // Close modal using existing modal-close class or specific IDs
        $(document).on('click', '#noteModal .modal-close, #noteModal .modal-backdrop', function () {
            self.hideNoteModal();
        });

        // Save note button - support both btnSaveNote (existing) and saveNote (new)
        $(document).on('click', '#btnSaveNote, #saveNote', function () {
            console.log('Save note button clicked');
            self.saveNote();
        });

        // Note card click to edit
        $(document).on('click', '.note-card', function (e) {
            if ($(e.target).closest('.note-actions').length) return;
            const noteId = $(this).data('note-id');
            self.editNote(noteId);
        });

        // Star note
        $(document).on('click', '.btn-star-note', function (e) {
            e.stopPropagation();
            const $card = $(this).closest('.note-card');
            const noteId = $card.data('note-id');
            self.toggleNoteStar(noteId, $card);
        });

        // Delete note
        $(document).on('click', '.btn-delete-note', function (e) {
            e.stopPropagation();
            const noteId = $(this).closest('.note-card').data('note-id');
            if (confirm('Are you sure you want to delete this note?')) {
                self.deleteNote(noteId);
            }
        });
    },

    /**
     * Switch between dashboard tabs
     */
    switchTab: function (tab) {
        // Update navigation
        $('.nav-item').removeClass('active');
        $(`.nav-item[data-tab="${tab}"]`).addClass('active');

        // Show/hide content
        $('.tab-content, .dashboard-section').hide();

        if (tab === 'overview') {
            // Show overview sections
            $('.dashboard-section').not('.tab-content').show();
        } else {
            $(`#${tab}`).show();
        }

        // Load tab-specific data
        switch (tab) {
            case 'my-learning':
                this.loadAllCourses('all');
                break;
            case 'progress':
                this.loadProgressData();
                break;
            case 'certificates':
                this.loadCertificates();
                break;
            case 'notes':
                this.loadNotes('all');
                break;
            case 'calendar':
                this.loadCalendarEvents();
                break;
            case 'notifications-tab':
                this.loadNotifications();
                break;
        }
    },

    /**
     * Make AJAX request
     */
    ajax: function (action, data = {}, method = 'GET') {
        return $.ajax({
            url: this.ajaxUrl,
            method: method,
            data: { action, ...data },
            dataType: 'json'
        });
    },

    /**
     * Load dashboard statistics
     */
    loadDashboardStats: function () {
        const self = this;

        this.ajax('get_dashboard_stats').done(function (response) {
            if (response.success) {
                const data = response.data;

                // Update stat cards
                self.updateStatCard('.stat-enrollments', data.total_courses, 'Enrolled Courses');
                self.updateStatCard('.stat-completed', data.completed_courses, 'Completed');
                self.updateStatCard('.stat-hours', data.learning_hours + 'h', 'Learning Hours');
                self.updateStatCard('.stat-certificates', data.certificates_count, 'Certificates');

                // Update quick stats in welcome section
                $('#statEnrollments').text(data.total_courses);
                $('#statHours').text(data.learning_hours);
                $('#statCertificates').text(data.certificates_count);
            }
        });
    },

    /**
     * Update a stat card
     */
    updateStatCard: function (selector, value, label) {
        const $card = $(selector);
        if ($card.length) {
            $card.find('.stat-value, h3').text(value);
            if (label) $card.find('.stat-label, p').text(label);
        }
    },

    /**
     * Load continue learning courses
     */
    loadContinueLearning: function () {
        const self = this;
        const $container = $('.courses-continue-grid');

        if (!$container.length) return;

        $container.html(self.getLoadingHTML());

        this.ajax('get_continue_learning').done(function (response) {
            if (response.success && response.data.length > 0) {
                let html = '';

                response.data.forEach(function (course, index) {
                    const gradient = `gradient-${(index % 4) + 1}`;
                    html += self.renderCourseProgressCard(course, gradient);
                });

                $container.html(html);
                self.initProgressCircles();
            } else {
                $container.html(self.getEmptyStateHTML('No courses in progress', 'Start learning to see your courses here'));
            }
        }).fail(function () {
            $container.html(self.getErrorHTML('Failed to load courses'));
        });
    },

    /**
     * Render a course progress card
     */
    renderCourseProgressCard: function (course, gradient) {
        const self = this;
        const thumbnailUrl = this.getThumbnailUrl(course.thumbnail);
        const thumbnailStyle = thumbnailUrl
            ? `background-image: url('${thumbnailUrl}'); background-size: cover; background-position: center;`
            : '';
        const progressStatus = course.progress === 0 ? 'Not Started' : (course.progress === 100 ? 'Completed' : 'In Progress');
        const rating = course.rating || 0;
        const duration = course.duration_minutes || 0;

        return `
            <div class="course-progress-card">
                <div class="course-thumbnail">
                    <div class="thumbnail-placeholder" style="${thumbnailStyle}"></div>
                    <span class="course-status-badge ${course.progress === 0 ? 'not-started' : ''}">${progressStatus}</span>
                </div>
                <div class="course-info">
                    <span class="course-category">${course.category}</span>
                    <h4>${course.title}</h4>
                    <p class="course-instructor">by ${course.instructor}</p>
                    <div class="course-meta-info">
                        <span class="meta-item"><span class="material-icons">star</span> ${rating}</span>
                        <span class="meta-item"><span class="material-icons">schedule</span> ${duration} mins</span>
                    </div>
                    <div class="progress-info">
                        <div class="progress-bar">
                            <div class="progress-fill ${course.progress >= 100 ? 'progress-complete' : course.progress >= 50 ? 'progress-mid' : 'progress-low'}" style="width: ${course.progress}%"></div>
                        </div>
                        <span class="progress-text">${course.progress}% Complete</span>
                    </div>
                    <button class="btn btn-primary btn-block btn-continue-learning" onclick="window.location.href='${self.baseUrl}learn?id=${course.course_id}'">Continue Learning</button>
                </div>
            </div>
        `;
    },

    /**
     * Load recommendations
     */
    loadRecommendations: function () {
        const self = this;
        const $container = $('.recommendations-grid');

        if (!$container.length) return;

        this.ajax('get_recommendations').done(function (response) {
            if (response.success && response.data.length > 0) {
                let html = '';

                response.data.forEach(function (course) {
                    const thumbnailUrl = self.getThumbnailUrl(course.thumbnail);
                    html += `
                        <div class="recommendation-card">
                            <div class="rec-thumbnail">
                                ${thumbnailUrl
                            ? `<img src="${thumbnailUrl}" alt="${course.title}" onerror="this.parentElement.innerHTML='<div class=\\'rec-icon\\'>🚀</div>'">`
                            : `<div class="rec-icon">🚀</div>`}
                            </div>
                            <h4>${course.title}</h4>
                            <p>${course.description || 'Expand your skills with this recommended course'}</p>
                            <div class="rec-meta">
                                <span><span class="material-icons">star</span> ${course.rating}</span>
                                <span><span class="material-icons">people</span> ${course.students}</span>
                            </div>
                            <button class="btn btn-secondary btn-sm" onclick="window.location.href='${self.baseUrl}course/${course.course_id}'">Explore Course</button>
                        </div>
                    `;
                });

                $container.html(html);
            }
        });
    },

    /**
     * Load recent activity
     */
    loadRecentActivity: function () {
        const self = this;
        const $container = $('.activity-list');

        if (!$container.length) return;

        this.ajax('get_recent_activity').done(function (response) {
            if (response.success && response.data.length > 0) {
                let html = '';

                response.data.forEach(function (activity) {
                    html += `
                        <div class="activity-item">
                            <div class="activity-icon ${activity.type}">
                                <span class="material-icons">${activity.icon}</span>
                            </div>
                            <div class="activity-content">
                                <p>${activity.message}</p>
                                <span class="activity-time">${activity.time}</span>
                            </div>
                        </div>
                    `;
                });

                $container.html(html);
            } else {
                $container.html('<p class="empty-message">No recent activity</p>');
            }
        });
    },

    /**
     * Load notifications
     */
    loadNotifications: function () {
        const self = this;
        const $sidebarContainer = $('.notifications-list');
        const $fullContainer = $('#notificationsFullList');

        this.ajax('get_notifications').done(function (response) {
            if (response.success) {
                let sidebarHtml = '';
                let fullHtml = '';

                if (response.data.length > 0) {
                    response.data.forEach(function (notif) {
                        const unreadClass = notif.read ? '' : 'unread';
                        const linkAttr = notif.link ? `data-link="${notif.link}"` : '';

                        // Sidebar compact view
                        sidebarHtml += `
                            <div class="notification-item ${unreadClass}" data-id="${notif.id}" ${linkAttr}>
                                <div class="notification-icon">
                                    <span class="material-icons">${notif.icon}</span>
                                </div>
                                <div class="notification-content">
                                    <p><strong>${notif.title}:</strong> ${notif.message}</p>
                                    <span class="notification-time">${notif.time}</span>
                                </div>
                            </div>
                        `;

                        // Full list view with mark as read indicator
                        fullHtml += `
                            <div class="notification-full-item ${unreadClass}" data-id="${notif.id}" ${linkAttr}>
                                <div class="notification-icon ${notif.type}">
                                    <span class="material-icons">${notif.icon}</span>
                                </div>
                                <div class="notification-body">
                                    <h4>${notif.title}</h4>
                                    <p>${notif.message}</p>
                                    <span class="notification-time">${notif.time}</span>
                                </div>
                                ${!notif.read ? '<span class="unread-indicator"></span>' : ''}
                            </div>
                        `;
                    });

                    // Update badge with unread count
                    const unreadCount = response.unread_count || response.data.filter(n => !n.read).length;
                    $('.notification-badge').text(unreadCount).toggle(unreadCount > 0);
                } else {
                    sidebarHtml = '<div class="empty-state"><span class="material-icons">notifications_off</span><p>No notifications yet</p></div>';
                    fullHtml = '<div class="empty-state"><span class="material-icons">notifications_off</span><p>No notifications yet</p></div>';
                    $('.notification-badge').hide();
                }

                if ($sidebarContainer.length) $sidebarContainer.html(sidebarHtml);
                if ($fullContainer.length) $fullContainer.html(fullHtml);
            }
        }).fail(function () {
            const errorHtml = '<div class="empty-state"><span class="material-icons">error</span><p>Failed to load notifications</p></div>';
            if ($sidebarContainer.length) $sidebarContainer.html(errorHtml);
            if ($fullContainer.length) $fullContainer.html(errorHtml);
        });
    },

    /**
     * Mark a single notification as read
     */
    markNotificationRead: function (notifId, $item) {
        const self = this;

        $.ajax({
            url: this.ajaxUrl,
            method: 'POST',
            data: { action: 'mark_notification_read', notification_id: notifId },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                // Update UI
                if ($item) {
                    $item.removeClass('unread');
                    $item.find('.unread-indicator').remove();
                }
                // Also update sidebar item with same ID
                $(`.notification-item[data-id="${notifId}"]`).removeClass('unread');

                // Update badge count
                const currentCount = parseInt($('.notification-badge').text()) || 0;
                if (currentCount > 0) {
                    const newCount = currentCount - 1;
                    $('.notification-badge').text(newCount).toggle(newCount > 0);
                }
            }
        });
    },

    /**
     * Mark all notifications as read
     */
    markAllNotificationsRead: function () {
        const self = this;

        $.ajax({
            url: this.ajaxUrl,
            method: 'POST',
            data: { action: 'mark_all_notifications_read' },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                // Update all notification items
                $('.notification-item, .notification-full-item').removeClass('unread');
                $('.unread-indicator').remove();
                $('.notification-badge').text('0').hide();

                self.showToast('success', 'All notifications marked as read');
            }
        }).fail(function () {
            self.showToast('error', 'Failed to mark notifications as read');
        });
    },

    /**
     * Load streak data
     */
    loadStreakData: function () {
        const self = this;
        const $container = $('.streak-section');

        if (!$container.length) return;

        this.ajax('get_streak_data').done(function (response) {
            if (response.success) {
                const data = response.data;

                // Render the streak card
                let weeklyHtml = '';
                const days = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
                data.weekly.forEach(function (day, i) {
                    const classes = ['calendar-day'];
                    if (day.completed) classes.push('completed');
                    if (day.is_today) classes.push('active');
                    weeklyHtml += `<div class="${classes.join(' ')}">${days[i]}</div>`;
                });

                const html = `
                    <div class="streak-stats-card">
                        <h3>Your Learning Streak</h3>
                        <div class="streak-display">
                            <div class="streak-flame-big"><span class="material-icons">local_fire_department</span></div>
                            <div class="streak-number">${data.current_streak}</div>
                            <p>Day Streak</p>
                        </div>
                        <div class="streak-calendar">
                            ${weeklyHtml}
                        </div>
                        <div class="streak-stats">
                            <div class="streak-stat">
                                <strong>${data.longest_streak}</strong>
                                <span>Longest Streak</span>
                            </div>
                            <div class="streak-stat">
                                <strong>${data.total_time}</strong>
                                <span>Total Time</span>
                            </div>
                            <div class="streak-stat">
                                <strong>${data.total_lessons}</strong>
                                <span>Lessons Done</span>
                            </div>
                        </div>
                    </div>
                `;

                $container.html(html);
            } else {
                // Show default/empty state
                $container.html(`
                    <div class="streak-stats-card">
                        <h3>Your Learning Streak</h3>
                        <div class="streak-display">
                            <div class="streak-flame-big"><span class="material-icons">local_fire_department</span></div>
                            <div class="streak-number">0</div>
                            <p>Day Streak</p>
                        </div>
                        <p style="color: rgba(255,255,255,0.6); text-align: center; margin-top: 15px;">Start learning to build your streak!</p>
                    </div>
                `);
            }
        });
    },

    /**
     * Load certificates
     */
    loadCertificates: function () {
        const self = this;
        const $container = $('.certificates-grid');

        if (!$container.length) return;

        $container.html(self.getLoadingHTML());

        this.ajax('get_certificates').done(function (response) {
            if (response.success && response.data.length > 0) {
                let html = '';

                response.data.forEach(function (cert) {
                    html += `
                        <div class="certificate-card">
                            <div class="certificate-icon">🎓</div>
                            <div class="certificate-info">
                                <h4>${cert.course_title}</h4>
                                <p>Completed on ${cert.issued_date}</p>
                                ${cert.grade ? `<p class="cert-grade">Grade: ${cert.grade}%</p>` : ''}
                                <div class="certificate-actions">
                                    <button class="btn btn-sm btn-primary btn-download-cert" data-cert-id="${cert.certificate_id}">
                                        <span class="material-icons">download</span>
                                        Download
                                    </button>
                                    <button class="btn btn-sm btn-secondary">
                                        <span class="material-icons">share</span>
                                        Share
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });

                $container.html(html);
            } else {
                $container.html(self.getEmptyStateHTML('No certificates yet', 'Complete courses to earn certificates'));
            }
        });
    },

    /**
     * Load notes
     */
    loadNotes: function (filter = 'all', sort = 'modified', search = '') {
        const self = this;
        const $container = $('.notes-grid');

        if (!$container.length) return;

        $container.html(self.getLoadingHTML());

        this.ajax('get_notes', { filter, sort, search }).done(function (response) {
            if (response.success && response.data && response.data.length > 0) {
                let html = '';

                response.data.forEach(function (note) {
                    const courseLabel = note.course || 'General';
                    const contentPreview = self.truncateText(note.content || '', 150);
                    html += `
                        <div class="note-card" data-note-id="${note.note_id}" data-course-id="${note.course_id || ''}">
                            <div class="note-actions">
                                <button class="btn-icon-note btn-star-note" data-starred="${note.is_starred}" title="Star note">
                                    <span class="material-icons">${note.is_starred ? 'star' : 'star_border'}</span>
                                </button>
                                <button class="btn-icon-note btn-delete-note" title="Delete note">
                                    <span class="material-icons">delete_outline</span>
                                </button>
                            </div>
                            <div class="note-header">
                                <h4>${note.title}</h4>
                            </div>
                            <p class="note-content">${contentPreview}</p>
                            <div class="note-footer">
                                <span class="note-course" title="${courseLabel}">
                                    <span class="material-icons">school</span>
                                    ${courseLabel}
                                </span>
                                <span class="note-date">${note.time_ago}</span>
                            </div>
                        </div>
                    `;
                });

                $container.html(html);
            } else {
                $container.html(self.getEmptyStateHTML('No notes found', 'Create notes while learning to save them here'));
            }
        }).fail(function (xhr, status, error) {
            $container.html(self.getEmptyStateHTML('Error loading notes', 'Please try again later'));
        });
    },

    /**
     * Load all courses for My Learning tab
     */
    loadAllCourses: function (filter = 'all') {
        const self = this;
        const $container = $('#my-learning .courses-grid');

        if (!$container.length) return;

        $container.html(self.getLoadingHTML());

        this.ajax('get_all_courses', { filter }).done(function (response) {
            if (response.success && response.data.length > 0) {
                let html = '';

                response.data.forEach(function (course, index) {
                    const gradient = `gradient-${(index % 4) + 1}`;
                    const statusClass = course.status;
                    const statusText = course.status.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase());

                    const thumbnailUrl = self.getThumbnailUrl(course.thumbnail);
                    html += `
                        <div class="course-card" data-status="${course.status}">
                            <div class="course-thumbnail">
                                <div class="thumbnail-placeholder" ${thumbnailUrl ? `style="background-image: url('${thumbnailUrl}')"` : ''}></div>
                                <span class="course-badge ${statusClass}">${statusText}</span>
                            </div>
                            <div class="course-content">
                                <span class="course-category">${course.category}</span>
                                <h4>${course.title}</h4>
                                <p class="course-instructor">by ${course.instructor}</p>
                                <div class="course-stats">
                                    <span><span class="material-icons">star</span> ${course.rating || 0}${course.total_reviews ? ` (${course.total_reviews})` : ''}</span>
                                    <span><span class="material-icons">schedule</span> ${course.duration || '0 mins'}</span>
                                </div>
                                ${course.status === 'wishlist' ? `
                                    <button class="btn btn-primary btn-sm btn-block btn-enroll-course" data-course-id="${course.course_id}" onclick="window.location.href='${self.baseUrl}course/${course.course_id}'">
                                        <span class="material-icons">shopping_cart</span>
                                        Add to Cart
                                    </button>
                                ` : `
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: ${course.progress}%"></div>
                                    </div>
                                    <span class="progress-text">${course.progress}% Complete</span>
                                    <button class="btn btn-primary btn-sm btn-block btn-continue-learning" onclick="window.location.href='${self.baseUrl}learn?id=${course.course_id}'">
                                        <span class="material-icons">play_arrow</span>
                                        Continue Learning
                                    </button>
                                `}
                            </div>
                        </div>
                    `;
                });

                $container.html(html);
            } else {
                $container.html(self.getEmptyStateHTML('No courses found', 'Enroll in courses to see them here'));
            }
        });
    },

    /**
     * Load progress data
     */
    loadProgressData: function () {
        const self = this;
        const $statsGrid = $('#progressStatsGrid');
        const $detailsGrid = $('#progressDetailsGrid');

        // Load stats
        this.ajax('get_dashboard_stats').done(function (response) {
            if (response.success) {
                const data = response.data;

                const statsHtml = `
                    <div class="stat-overview-card">
                        <div class="stat-icon"><span class="material-icons">school</span></div>
                        <div class="stat-info">
                            <h3>${data.total_courses || 0}</h3>
                            <p>Total Courses</p>
                        </div>
                    </div>
                    <div class="stat-overview-card">
                        <div class="stat-icon"><span class="material-icons">check_circle</span></div>
                        <div class="stat-info">
                            <h3>${data.completed_courses || 0}</h3>
                            <p>Completed</p>
                        </div>
                    </div>
                    <div class="stat-overview-card">
                        <div class="stat-icon"><span class="material-icons">schedule</span></div>
                        <div class="stat-info">
                            <h3>${data.learning_hours || 0}h</h3>
                            <p>Learning Hours</p>
                        </div>
                    </div>
                    <div class="stat-overview-card">
                        <div class="stat-icon"><span class="material-icons">play_lesson</span></div>
                        <div class="stat-info">
                            <h3>${data.lectures_completed || 0}</h3>
                            <p>Lectures Completed</p>
                        </div>
                    </div>
                    <div class="stat-overview-card">
                        <div class="stat-icon"><span class="material-icons">workspace_premium</span></div>
                        <div class="stat-info">
                            <h3>${data.certificates_count || 0}</h3>
                            <p>Certificates</p>
                        </div>
                    </div>
                    <div class="stat-overview-card">
                        <div class="stat-icon"><span class="material-icons">local_fire_department</span></div>
                        <div class="stat-info">
                            <h3>${data.current_streak || 0}</h3>
                            <p>Day Streak</p>
                        </div>
                    </div>
                `;

                $statsGrid.html(statsHtml);
            }
        });

        // Load course progress list
        this.ajax('get_continue_learning').done(function (response) {
            if (response.success && response.data.length > 0) {
                let html = '<div class="progress-details-card"><h3>Course Progress</h3><div class="course-progress-list">';

                response.data.forEach(function (course) {
                    const thumbnailUrl = self.getThumbnailUrl(course.thumbnail);
                    html += `
                        <div class="course-progress-item">
                            <div class="course-progress-thumb">
                                ${thumbnailUrl ? `<img src="${thumbnailUrl}" alt="${course.title}">` : '<span class="material-icons">school</span>'}
                            </div>
                            <div class="course-progress-info">
                                <strong>${course.title}</strong>
                                <span>${course.lectures_completed || 0} / ${course.total_lectures || 0} lectures</span>
                            </div>
                            <div class="course-progress-bar">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: ${course.progress || 0}%"></div>
                                </div>
                                <span>${course.progress || 0}%</span>
                            </div>
                            <button class="btn btn-primary btn-sm btn-continue-learning" onclick="window.location.href='${self.baseUrl}learn?id=${course.course_id}'">
                                <span class="material-icons">play_arrow</span>
                            </button>
                        </div>
                    `;
                });

                html += '</div></div>';
                $detailsGrid.html(html);
            } else {
                $detailsGrid.html(self.getEmptyStateHTML('No courses in progress', 'Start a course to track your progress'));
            }
        });
    },

    /**
     * Load learning analytics
     */
    loadLearningAnalytics: function (period = 'month') {
        const self = this;

        this.ajax('get_learning_analytics', { period }).done(function (response) {
            if (response.success) {
                self.updateCharts(response.data);
            }
        });
    },

    /**
     * Load calendar events
     */
    loadCalendarEvents: function () {
        const self = this;
        const month = new Date().getMonth() + 1;
        const year = new Date().getFullYear();

        this.ajax('get_calendar_events', { month, year }).done(function (response) {
            if (response.success) {
                const events = response.data;

                $('.calendar-day').removeClass('has-event');
                events.forEach(function (dateStr) {
                    const day = new Date(dateStr).getDate();
                    $(`.calendar-day:contains(${day})`).filter(function () {
                        return $(this).text().trim() == day;
                    }).addClass('has-event');
                });
            }
        });
    },

    /**
     * Initialize charts
     */
    initCharts: function () {
        const self = this;

        // Destroy existing charts if they exist
        Object.keys(this.charts).forEach(key => {
            if (this.charts[key]) {
                this.charts[key].destroy();
                this.charts[key] = null;
            }
        });

        // Activity Chart (Line)
        const activityCtx = document.getElementById('activityChart');
        if (activityCtx) {
            this.charts.activity = new Chart(activityCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Learning Time (mins)',
                        data: [],
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Progress Chart (Doughnut)
        const progressCtx = document.getElementById('progressChart');
        if (progressCtx) {
            this.charts.progress = new Chart(progressCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'Advanced', 'Started', 'Not Started'],
                    datasets: [{
                        data: [0, 0, 0, 0],
                        backgroundColor: ['#10b981', '#6366f1', '#f59e0b', '#ef4444']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Skills Chart (Radar)
        const skillsCtx = document.getElementById('skillsChart');
        if (skillsCtx) {
            this.charts.skills = new Chart(skillsCtx.getContext('2d'), {
                type: 'radar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Proficiency',
                        data: [],
                        backgroundColor: 'rgba(99, 102, 241, 0.2)',
                        borderColor: '#6366f1',
                        pointBackgroundColor: '#6366f1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
    },

    /**
     * Update charts with data
     */
    updateCharts: function (data) {
        if (this.charts.activity && data.activity) {
            this.charts.activity.data.labels = data.activity.labels;
            this.charts.activity.data.datasets[0].data = data.activity.data;
            this.charts.activity.update();
        }

        if (this.charts.progress && data.progress) {
            this.charts.progress.data.datasets[0].data = [
                data.progress.completed,
                data.progress.advanced,
                data.progress.started,
                data.progress.not_started
            ];
            this.charts.progress.update();
        }

        if (this.charts.skills && data.skills) {
            this.charts.skills.data.labels = data.skills.labels;
            this.charts.skills.data.datasets[0].data = data.skills.data;
            this.charts.skills.update();
        }
    },

    /**
     * Initialize progress circles
     */
    initProgressCircles: function () {
        $('.progress-circle').each(function () {
            const progress = $(this).data('progress');
            $(this).find('.circle').css('stroke-dasharray', `${progress}, 100`);
        });
    },

    /**
     * Mark notification as read
     */
    markNotificationRead: function (notificationId) {
        this.ajax('mark_notification_read', { notification_id: notificationId }, 'POST');
    },

    /**
     * Show note modal
     */
    showNoteModal: function (note = null) {
        const self = this;
        const $modal = $('#noteModal');

        // Load enrolled courses first
        this.loadEnrolledCourses(function () {
            if (note) {
                $modal.find('#noteModalTitle, .modal-header h3').first().text('Edit Note');
                $modal.data('note-id', note.note_id);
                $modal.find('#noteTitle').val(note.title);
                $modal.find('#noteContent').val(note.content);
                if ($modal.find('#noteCourse').length && note.course_id) {
                    $modal.find('#noteCourse').val(note.course_id);
                }
            } else {
                $modal.find('#noteModalTitle, .modal-header h3').first().text('New Note');
                $modal.data('note-id', '');
                $modal.find('#noteTitle').val('');
                $modal.find('#noteContent').val('');
                if ($modal.find('#noteCourse').length) {
                    $modal.find('#noteCourse').val('');
                }
            }

            $modal.addClass('active');
            $modal.find('#noteTitle').focus();
        });
    },

    /**
     * Load enrolled courses for dropdown
     */
    loadEnrolledCourses: function (callback) {
        const self = this;
        const $select = $('#noteCourse');

        // If already loaded, use cache
        if (this.enrolledCourses.length > 0) {
            this.populateCourseDropdown($select, this.enrolledCourses);
            if (callback) callback();
            return;
        }

        $select.html('<option value="">Loading courses...</option>');

        this.ajax('get_enrolled_courses').done(function (response) {
            if (response.success && response.data) {
                self.enrolledCourses = response.data;
                self.populateCourseDropdown($select, response.data);
            } else {
                $select.html('<option value="">No courses found</option>');
            }
            if (callback) callback();
        }).fail(function () {
            $select.html('<option value="">Error loading courses</option>');
            if (callback) callback();
        });
    },

    /**
     * Populate course dropdown
     */
    populateCourseDropdown: function ($select, courses) {
        let html = '<option value="">-- Select a course --</option>';
        courses.forEach(function (course) {
            html += `<option value="${course.course_id}">${course.title}</option>`;
        });
        $select.html(html);
    },

    /**
     * Hide note modal
     */
    hideNoteModal: function () {
        $('#noteModal').removeClass('active');
    },

    /**
     * Save note
     */
    saveNote: function () {
        console.log('saveNote function called');
        const self = this;
        const $modal = $('#noteModal');
        console.log('Modal found:', $modal.length > 0);
        const noteId = $modal.data('note-id');
        const courseId = $modal.find('#noteCourse').val();
        console.log('Course ID:', courseId, 'Note ID:', noteId);
        const data = {
            note_id: noteId || '',
            course_id: courseId || '',
            lecture_id: '',
            title: $modal.find('#noteTitle').val(),
            content: $modal.find('#noteContent').val()
        };

        console.log('Saving note with data:', data);

        if (!data.title.trim()) {
            self.showToast('Please enter a note title', 'error');
            return;
        }

        if (!data.course_id) {
            self.showToast('Please select a course for this note', 'error');
            return;
        }

        this.ajax('save_note', data, 'POST').done(function (response) {
            console.log('Save note response:', response);
            if (response.success) {
                self.hideNoteModal();
                self.loadNotes($('.note-filter-btn.active').data('filter') || 'all');
                self.showToast(noteId ? 'Note updated successfully' : 'Note created successfully', 'success');
            } else {
                self.showToast(response.error || 'Failed to save note', 'error');
            }
        }).fail(function (xhr, status, error) {
            console.error('Save note failed:', status, error, xhr.responseText);
            self.showToast('Failed to save note', 'error');
        });
    },

    /**
     * Edit note
     */
    editNote: function (noteId) {
        const self = this;

        this.ajax('get_note', { note_id: noteId }).done(function (response) {
            if (response.success && response.data) {
                self.showNoteModal(response.data);
            }
        });
    },

    /**
     * Toggle note star
     */
    toggleNoteStar: function (noteId, $card) {
        const self = this;
        const isStarred = $card.find('.btn-star-note').data('starred');

        this.ajax('toggle_note_star', { note_id: noteId }, 'POST').done(function (response) {
            if (response.success) {
                const $btn = $card.find('.btn-star-note');
                $btn.data('starred', !isStarred);
                $btn.find('.material-icons').text(isStarred ? 'star_border' : 'star');
                $card.toggleClass('starred', !isStarred);
            }
        });
    },

    /**
     * Delete note
     */
    deleteNote: function (noteId) {
        const self = this;

        this.ajax('delete_note', { note_id: noteId }, 'POST').done(function (response) {
            if (response.success) {
                self.loadNotes($('.note-filter-btn.active').data('filter') || 'all');
                self.showToast('Note deleted successfully', 'success');
            } else {
                self.showToast('Failed to delete note', 'error');
            }
        });
    },

    /**
     * Show toast notification
     */
    showToast: function (message, type = 'info') {
        // Create toast container if not exists
        if (!$('.toast-container').length) {
            $('body').append('<div class="toast-container"></div>');
        }

        const icons = {
            success: 'check_circle',
            error: 'error',
            warning: 'warning',
            info: 'info'
        };

        const $toast = $(`
            <div class="toast ${type}">
                <span class="material-icons">${icons[type]}</span>
                <p>${message}</p>
                <button class="toast-close">
                    <span class="material-icons">close</span>
                </button>
            </div>
        `);

        $('.toast-container').append($toast);

        // Auto remove after 5 seconds
        setTimeout(function () {
            $toast.fadeOut(300, function () {
                $(this).remove();
            });
        }, 5000);

        // Manual close
        $toast.find('.toast-close').on('click', function () {
            $toast.fadeOut(300, function () {
                $(this).remove();
            });
        });
    },

    /**
     * Helper: Get loading HTML
     */
    getLoadingHTML: function () {
        return `
            <div class="loading-container">
                <div class="loading-spinner"></div>
                <p>Loading...</p>
            </div>
        `;
    },

    /**
     * Helper: Get empty state HTML
     */
    getEmptyStateHTML: function (title, message) {
        return `
            <div class="empty-state">
                <span class="material-icons">inbox</span>
                <h4>${title}</h4>
                <p>${message}</p>
            </div>
        `;
    },

    /**
     * Helper: Get error HTML
     */
    getErrorHTML: function (message) {
        return `
            <div class="error-state">
                <span class="material-icons">error</span>
                <p>${message}</p>
                <button class="btn btn-secondary btn-sm" onclick="location.reload()">Retry</button>
            </div>
        `;
    },

    /**
     * Helper: Truncate text
     */
    truncateText: function (text, maxLength) {
        if (!text) return '';
        if (text.length <= maxLength) return text;
        return text.substr(0, maxLength) + '...';
    }
};

// Initialize on document ready
$(document).ready(function () {
    // StudentDashboard.init();
});
StudentDashboard.init();