(function($) {
    'use strict';
    const DEFAULT_USER = {
        name: 'Mohatamim Haque',
        email: 'mohatamimhaque7@gmail.com',
        phone: '01518749114',
        country: 'Bangladesh',
        countryCode: '+880',
        timezone: 'Asia/Dhaka'
    };
    const DOM = {
        sidebar: null,
        menuToggle: null,
        userDropdown: null,
        themeToggle: null,
        notificationBtn: null,
        searchInput: null,
        modals: null
    };
    const STATE = {
        sidebarOpen: true,
        currentSection: 'overview',
        isEditing: false,
        theme: 'dark'
    };
    function init() {
        cacheDOM();
        bindEvents();
        initSidebar();
        initNavigation();
        initTheme();
        initUserMenu();
        initNotifications();
        initSearch();
        initCharts();
        initProfile();
        initModals();
    }
    function cacheDOM() {
        DOM.sidebar = $('.dashboard-sidebar');
        DOM.menuToggle = $('#btnMenuToggle');
        DOM.userDropdown = $('#userDropdown');
        DOM.themeToggle = $('#btnThemeToggle');
        DOM.notificationBtn = $('#btnNotifications');
        DOM.searchInput = $('#dashboardSearch');
        DOM.modals = $('.profile-modal');
    }
    function bindEvents() {
        $(window).on('resize', debounce(handleResize, 250));
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.user-menu').length) {
                DOM.userDropdown.removeClass('active');
            }
            if (!$(e.target).closest('.notification-bell').length) {
                $('.notification-dropdown').removeClass('active');
            }
        });
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllModals();
                DOM.userDropdown.removeClass('active');
            }
        });
    }
    function initSidebar() {
        DOM.menuToggle.on('click', toggleSidebar);
        if ($(window).width() <= 1024) {
            STATE.sidebarOpen = false;
            DOM.sidebar.removeClass('active');
        }
    }
    function toggleSidebar() {
        STATE.sidebarOpen = !STATE.sidebarOpen;
        DOM.sidebar.toggleClass('active', STATE.sidebarOpen);
        if ($(window).width() <= 1024) {
            $('body').toggleClass('sidebar-open', STATE.sidebarOpen);
        }
    }
    function initNavigation() {
        $('.sidebar-link').on('click', function(e) {
            e.preventDefault();
            const href = $(this).attr('href');
            if (href && href.startsWith('#')) {
                const sectionId = href.substring(1);
                showSection(sectionId);
                window.location.hash = sectionId;
                $('.sidebar-link').removeClass('active');
                $(this).addClass('active');
                if ($(window).width() <= 1024) {
                    toggleSidebar();
                }
            }
        });
        function handleHashChange() {
            const hash = window.location.hash;
            if (hash) {
                const sectionId = hash.substring(1);
                showSection(sectionId);
                $('.sidebar-link').removeClass('active');
                $(`.sidebar-link[href="${hash}"]`).addClass('active');
            } else {
                showSection('overview');
                $('.sidebar-link[href="#overview"]').addClass('active');
            }
        }
        $(window).on('hashchange', handleHashChange);
        handleHashChange();
    }
    function showSection(sectionId) {
        if (sectionId === 'overview') {
            $('.tab-content').hide();
            $('.dashboard-section').not('.tab-content').show();
        } else {
            $('.dashboard-section').not('.tab-content').hide();
            $('.tab-content').hide();
            const targetSection = $('#' + sectionId);
            if (targetSection.length) {
                targetSection.show();
            }
        }
        $('.sidebar-link').removeClass('active');
        $(`.sidebar-link[href="#${sectionId}"]`).addClass('active');
        STATE.currentSection = sectionId;
    }
    function initTheme() {
        const savedTheme = localStorage.getItem('eduverse-theme') || 'dark';
        setTheme(savedTheme);
        DOM.themeToggle.on('click', function() {
            const newTheme = STATE.theme === 'dark' ? 'light' : 'dark';
            setTheme(newTheme);
            localStorage.setItem('eduverse-theme', newTheme);
        });
    }
    function setTheme(theme) {
        STATE.theme = theme;
        $('html').attr('data-theme', theme);
        $('body').attr('data-theme', theme);
        const icon = theme === 'dark' ? 'light_mode' : 'dark_mode';
        DOM.themeToggle.find('.material-icons').text(icon);
    }
    function initUserMenu() {
        $('#btnUserMenu').on('click', function(e) {
            e.stopPropagation();
            DOM.userDropdown.toggleClass('active');
        });
        $('.dropdown-item').on('click', function(e) {
            const href = $(this).attr('href');
            if (href && href.startsWith('#')) {
                e.preventDefault();
                showSection(href.substring(1));
                DOM.userDropdown.removeClass('active');
            }
        });
    }
    function initNotifications() {
        DOM.notificationBtn.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            showSection('notifications-tab');
            window.location.hash = 'notifications-tab';
            $('.sidebar-link').removeClass('active');
            $('.sidebar-link[href="#notifications-tab"]').addClass('active');
            if ($(window).width() <= 1024) {
                toggleSidebar();
            }
        });
        $('#btnMarkAllRead').on('click', function() {
            $('.notification-badge').text('0').hide();
            showToast('success', 'All notifications marked as read');
        });
        $('.filter-btn').on('click', function() {
            const filter = $(this).data('filter');
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            filterNotifications(filter);
        });
        $('.mark-read').on('click', function(e) {
            e.stopPropagation();
            const $notification = $(this).closest('.notification-full-item');
            $notification.removeClass('unread');
            $(this).fadeOut();
        });
    }
    function filterNotifications(type) {
        const $notifications = $('.notification-full-item');
        if (type === 'all') {
            $notifications.show();
        } else {
            $notifications.each(function() {
                const $item = $(this);
                const itemType = $item.data('type');
                if (itemType === type) {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
        }
    }
    function initSearch() {
        let searchTimeout;
        DOM.searchInput.on('input', function() {
            const query = $(this).val().trim();
            clearTimeout(searchTimeout);
            if (query.length >= 2) {
                searchTimeout = setTimeout(function() {
                    performSearch(query);
                }, 300);
            }
        });
        DOM.searchInput.on('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = $(this).val().trim();
                if (query.length >= 2) {
                    performSearch(query);
                }
            }
        });
    }
    function performSearch(query) {
        console.log('Searching for:', query);
    }
    const CHARTS = {
        activity: null,
        progress: null,
        skills: null
    };
    function initCharts() {
        Object.keys(CHARTS).forEach(key => {
            if (CHARTS[key]) {
                CHARTS[key].destroy();
                CHARTS[key] = null;
            }
        });
        const activityCtx = document.getElementById('activityChart');
        if (activityCtx) {
            CHARTS.activity = new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Hours',
                        data: [2, 3.5, 2.5, 4, 3, 5, 4.5],
                        borderColor: '#4A90E2',
                        backgroundColor: 'rgba(74, 144, 226, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            max: 8,
                            grid: { color: 'rgba(255,255,255,0.1)' },
                            ticks: { color: '#E4E4E4' }
                        },
                        x: {
                            grid: { color: 'rgba(255,255,255,0.1)' },
                            ticks: { color: '#E4E4E4' }
                        }
                    }
                }
            });
        }
        const progressCtx = document.getElementById('progressChart');
        if (progressCtx) {
            CHARTS.progress = new Chart(progressCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Completed', 'In Progress', 'Not Started'],
                    datasets: [{
                        data: [35, 45, 20],
                        backgroundColor: ['#28A745', '#4A90E2', '#34495E']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    cutout: '70%',
                    plugins: {
                        legend: { 
                            position: 'bottom',
                            labels: { color: '#E4E4E4' }
                        }
                    }
                }
            });
        }
        const skillsCtx = document.getElementById('skillsChart');
        if (skillsCtx) {
            CHARTS.skills = new Chart(skillsCtx, {
                type: 'radar',
                data: {
                    labels: ['JavaScript', 'Python', 'React', 'Node.js', 'CSS', 'SQL'],
                    datasets: [{
                        label: 'Skill Level',
                        data: [85, 70, 80, 65, 90, 60],
                        backgroundColor: 'rgba(74, 144, 226, 0.2)',
                        borderColor: '#4A90E2',
                        pointBackgroundColor: '#4A90E2'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100,
                            grid: { color: 'rgba(255,255,255,0.1)' },
                            angleLines: { color: 'rgba(255,255,255,0.1)' },
                            pointLabels: { color: '#E4E4E4' },
                            ticks: { display: false }
                        }
                    }
                }
            });
        }
    }
    function initProfile() {
        $(document).on('click', '#btnEditProfile', function() {
            enableProfileEditing(true);
        });
        $(document).on('click', '#btnCancelEdit', function() {
            enableProfileEditing(false);
        });
        $(document).on('click', '#btnSaveProfile', saveProfile);
        $(document).on('click', '#btnUploadAvatar', function() {
            $('#avatarInput').click();
        });
        $(document).on('change', '#avatarInput', handleAvatarUpload);
        $(document).on('click', '#btnChangePassword', function() {
            openModal('#passwordModal');
        });
        $(document).on('click', '#btnSavePassword', savePassword);
        $(document).on('click', '#btnEditSocialLinks', function() {
            openModal('#socialLinksModal');
        });
        $(document).on('click', '#btnAddSocialLink', function() {
            addSocialLinkRow();
        });
        $(document).on('click', '#btnSaveSocialLinks', saveSocialLinks);
        $(document).on('click', '.btn-remove-social', function() {
            $(this).closest('.social-link-row').remove();
        });
        $(document).on('click', '#btnDeleteAccount', function() {
            $('#deleteAccountPassword').val('');
            openModal('#deleteAccountModal');
        });
        $(document).on('click', '#btnConfirmDeleteAccount', deleteAccount);
    }
    function deleteAccount() {
        const password = $('#deleteAccountPassword').val();
        if (!password) {
            showToast('error', 'Please enter your password');
            return;
        }
        $('#btnConfirmDeleteAccount').prop('disabled', true).html('<span class="material-icons spin">sync</span> Deleting...');
        $.ajax({
            url: 'ajax/dashboard_handler.php',
            method: 'POST',
            data: {
                action: 'delete_account',
                password: password
            },
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                showToast('success', 'Account deleted successfully');
                setTimeout(function() {
                    window.location.href = window.EDUVERSE_BASE_URL || '/';
                }, 1500);
            } else {
                showToast('error', response.error || 'Failed to delete account');
            }
        }).fail(function() {
            showToast('error', 'Failed to delete account');
        }).always(function() {
            $('#btnConfirmDeleteAccount').prop('disabled', false).html('Delete My Account');
        });
    }
    function enableProfileEditing(enable) {
        STATE.isEditing = enable;
        const fields = [
            '#inputFirstName', '#inputLastName', '#inputUsername', '#inputEmail',
            '#inputPhone', '#inputCountryCode', '#inputBio', '#inputHeadline',
            '#inputOccupation', '#inputCompany', '#inputLocation', '#inputCountry',
            '#inputDateOfBirth', '#inputGender', '#inputBloodGroup', '#inputTimezone', '#inputLanguage'
        ];
        fields.forEach(function(field) {
            $(field).prop('readonly', !enable).prop('disabled', !enable);
        });
        if (enable) {
            $('#profileFormActions').slideDown();
            $('#btnEditProfile').hide();
            $('.profile-form').addClass('editing');
        } else {
            $('#profileFormActions').slideUp();
            $('#btnEditProfile').show();
            $('.profile-form').removeClass('editing');
        }
    }
    function saveProfile() {
        const data = {
            action: 'update_profile',
            first_name: $('#inputFirstName').val(),
            last_name: $('#inputLastName').val(),
            phone: $('#inputPhone').val(),
            country_code: $('#inputCountryCode').val(),
            bio: $('#inputBio').val(),
            headline: $('#inputHeadline').val(),
            occupation: $('#inputOccupation').val(),
            company: $('#inputCompany').val(),
            location: $('#inputLocation').val(),
            country: $('#inputCountry').val(),
            date_of_birth: $('#inputDateOfBirth').val(),
            gender: $('#inputGender').val(),
            blood_group: $('#inputBloodGroup').val(),
            timezone: $('#inputTimezone').val(),
            language: $('#inputLanguage').val()
        };
        if (!data.first_name.trim()) {
            showToast('error', 'First name is required');
            return;
        }
        $('#btnSaveProfile').prop('disabled', true).html('<span class="material-icons spin">sync</span> Saving...');
        $.ajax({
            url: 'ajax/dashboard_handler.php',
            method: 'POST',
            data: data,
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                showToast('success', 'Profile updated successfully');
                enableProfileEditing(false);
                const fullName = data.first_name + ' ' + data.last_name;
                $('#profileDisplayName').text(fullName);
                if (data.headline) $('#profileDisplayHeadline').text(data.headline);
            } else {
                showToast('error', response.error || 'Failed to update profile');
            }
        }).fail(function() {
            showToast('error', 'Failed to update profile');
        }).always(function() {
            $('#btnSaveProfile').prop('disabled', false).html('Save Changes');
        });
    }
    function handleAvatarUpload() {
        const file = this.files[0];
        if (!file) return;
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            showToast('error', 'Please select a valid image file (JPG, PNG, GIF, WebP)');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            showToast('error', 'File size must be less than 5MB');
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#profileAvatar').attr('src', e.target.result);
            $('.user-avatar img').attr('src', e.target.result);
        };
        reader.readAsDataURL(file);
        const formData = new FormData();
        formData.append('action', 'upload_avatar');
        formData.append('avatar', file);
        $.ajax({
            url: 'ajax/dashboard_handler.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                showToast('success', 'Avatar updated successfully');
                const baseUrl = window.EDUVERSE_BASE_URL || '/';
                const avatarUrl = baseUrl + response.avatar_url;
                $('#profileAvatar').attr('src', avatarUrl);
                $('.user-avatar img').attr('src', avatarUrl);
            } else {
                showToast('error', response.error || 'Failed to upload avatar');
            }
        }).fail(function() {
            showToast('error', 'Failed to upload avatar');
        });
    }
    function savePassword() {
        const current = $('#currentPassword').val();
        const newPass = $('#newPassword').val();
        const confirm = $('#confirmPassword').val();
        if (!current || !newPass || !confirm) {
            showToast('error', 'All password fields are required');
            return;
        }
        if (newPass !== confirm) {
            showToast('error', 'New passwords do not match');
            return;
        }
        if (newPass.length < 8) {
            showToast('error', 'Password must be at least 8 characters');
            return;
        }
        $('#btnSavePassword').prop('disabled', true).html('<span class="material-icons spin">sync</span> Saving...');
        $.ajax({
            url: 'ajax/dashboard_handler.php',
            method: 'POST',
            data: {
                action: 'change_password',
                current_password: current,
                new_password: newPass,
                confirm_password: confirm
            },
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                showToast('success', 'Password changed successfully');
                closeAllModals();
                $('#currentPassword, #newPassword, #confirmPassword').val('');
            } else {
                showToast('error', response.error || 'Failed to change password');
            }
        }).fail(function() {
            showToast('error', 'Failed to change password');
        }).always(function() {
            $('#btnSavePassword').prop('disabled', false).html('Change Password');
        });
    }
    function addSocialLinkRow(platform, url) {
        platform = platform || '';
        url = url || '';
        const platforms = [
            { value: 'website', label: 'Website' },
            { value: 'linkedin', label: 'LinkedIn' },
            { value: 'twitter', label: 'Twitter/X' },
            { value: 'github', label: 'GitHub' },
            { value: 'facebook', label: 'Facebook' },
            { value: 'instagram', label: 'Instagram' },
            { value: 'youtube', label: 'YouTube' },
            { value: 'portfolio', label: 'Portfolio' },
            { value: 'other', label: 'Other' }
        ];
        let options = '<option value="">Select Platform</option>';
        platforms.forEach(function(p) {
            const selected = p.value === platform ? ' selected' : '';
            options += `<option value="${p.value}"${selected}>${p.label}</option>`;
        });
        const html = `
            <div class="social-link-row">
                <select class="form-control social-platform-select">${options}</select>
                <input type="url" class="form-control social-url-input" placeholder="https:
                <button type="button" class="btn-icon btn-remove-social">
                    <span class="material-icons">delete</span>
                </button>
            </div>
        `;
        $('#socialLinksEditContainer').append(html);
    }
    function saveSocialLinks() {
        const links = [];
        $('.social-link-row').each(function() {
            const platform = $(this).find('.social-platform-select').val();
            const url = $(this).find('.social-url-input').val().trim();
            if (platform && url) {
                links.push({ platform, url });
            }
        });
        $('#btnSaveSocialLinks').prop('disabled', true).html('<span class="material-icons spin">sync</span> Saving...');
        setTimeout(function() {
            showToast('success', 'Social links saved');
            closeAllModals();
            $('#btnSaveSocialLinks').prop('disabled', false).html('Save Links');
        }, 1000);
    }
    function initModals() {
        $('.modal-backdrop, .modal-close').on('click', closeAllModals);
        $('.modal-content').on('click', function(e) {
            e.stopPropagation();
        });
    }
    function openModal(selector) {
        $(selector).addClass('active');
        $('body').addClass('modal-open');
    }
    function closeAllModals() {
        DOM.modals.removeClass('active');
        $('body').removeClass('modal-open');
    }
    function showToast(type, message) {
        const existing = $('.profile-notification');
        if (existing.length) {
            existing.remove();
        }
        const icons = {
            success: 'check_circle',
            error: 'error',
            warning: 'warning',
            info: 'info'
        };
        const icon = icons[type] || 'info';
        const toast = $(`
            <div class="profile-notification ${type}">
                <span class="material-icons">${icon}</span>
                <span class="notification-message">${escapeHtml(message)}</span>
                <button class="notification-close"><span class="material-icons">close</span></button>
            </div>
        `);
        $('body').append(toast);
        setTimeout(function() {
            toast.addClass('show');
        }, 10);
        toast.find('.notification-close').on('click', function() {
            toast.removeClass('show');
            setTimeout(function() { toast.remove(); }, 300);
        });
        setTimeout(function() {
            toast.removeClass('show');
            setTimeout(function() { toast.remove(); }, 300);
        }, 5000);
    }
    function handleResize() {
        if ($(window).width() <= 1024) {
            if (STATE.sidebarOpen) {
                STATE.sidebarOpen = false;
                DOM.sidebar.removeClass('active');
            }
        }
    }
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    function getSocialIcon(platform) {
        const icons = {
            website: 'language',
            linkedin: 'work',
            twitter: 'alternate_email',
            github: 'code',
            facebook: 'facebook',
            instagram: 'camera_alt',
            youtube: 'play_circle',
            portfolio: 'folder_special',
            other: 'link'
        };
        return icons[platform] || 'link';
    }
    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    }
    function capitalizeFirst(str) {
        return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
    }
    function initNotes() {
        let currentNoteId = null;
        $('.btn-primary').filter(function() {
            return $(this).text().trim().includes('New Note');
        }).on('click', function() {
            currentNoteId = null;
            $('#noteModalTitle').text('New Note');
            $('#noteTitle').val('');
            $('#noteCourse').val('');
            $('#noteContent').val('');
            openModal('#noteModal');
        });
        $('.note-filter-btn').on('click', function() {
            $('.note-filter-btn').removeClass('active');
            $(this).addClass('active');
            const filter = $(this).data('filter');
            filterNotes(filter);
        });
        $('.notes-search-input').on('input', debounce(function() {
            const query = $(this).val().toLowerCase();
            searchNotes(query);
        }, 300));
        $('.notes-sort').on('change', function() {
            const sortBy = $(this).val();
            sortNotes(sortBy);
        });
        $(document).on('click', '.btn-icon-note:has(.material-icons:contains("star"))', function(e) {
            e.stopPropagation();
            const $icon = $(this).find('.material-icons');
            if ($icon.text() === 'star_border') {
                $icon.text('star');
                $(this).addClass('starred');
                showToast('success', 'Note starred');
            } else {
                $icon.text('star_border');
                $(this).removeClass('starred');
                showToast('info', 'Note unstarred');
            }
        });
        $(document).on('click', '.note-card', function(e) {
            if (!$(e.target).closest('.btn-icon-note').length) {
                const $card = $(this);
                currentNoteId = $card.index();
                $('#noteModalTitle').text('Edit Note');
                $('#noteTitle').val($card.find('h4').text());
                $('#noteCourse').val($card.find('.note-course').text());
                $('#noteContent').val($card.find('.note-content').html().replace(/<br>/g, '\n'));
                openModal('#noteModal');
            }
        });
        $(document).on('click', '.btn-icon-note:has(.material-icons:contains("more_vert"))', function(e) {
            e.stopPropagation();
            currentNoteId = $(this).closest('.note-card').index();
            openModal('#noteDeleteModal');
        });
        $('#btnSaveNote').on('click', function() {
            const title = $('#noteTitle').val().trim();
            const course = $('#noteCourse').val();
            const content = $('#noteContent').val().trim();
            if (!title || !content) {
                showToast('error', 'Please fill in all required fields');
                return;
            }
            if (currentNoteId !== null) {
                showToast('success', 'Note updated successfully');
            } else {
                showToast('success', 'Note created successfully');
            }
            closeAllModals();
        });
        $('#btnConfirmDeleteNote').on('click', function() {
            if (currentNoteId !== null) {
                $(`.notes-grid .note-card:eq(${currentNoteId})`).fadeOut(300, function() {
                    $(this).remove();
                });
                showToast('success', 'Note deleted successfully');
            }
            closeAllModals();
        });
    }
    function filterNotes(filter) {
        const $notes = $('.note-card');
        switch(filter) {
            case 'all':
                $notes.show();
                break;
            case 'recent':
                $notes.each(function() {
                    const dateText = $(this).find('.note-date').text();
                    if (dateText.includes('hour') || dateText.includes('day') && !dateText.includes('week')) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
                break;
            case 'starred':
                $notes.hide();
                $notes.filter(function() {
                    return $(this).find('.btn-icon-note.starred').length > 0;
                }).show();
                break;
            case 'archived':
                $notes.hide();
                break;
        }
    }
    function searchNotes(query) {
        const $notes = $('.note-card');
        if (!query) {
            $notes.show();
            return;
        }
        $notes.each(function() {
            const title = $(this).find('h4').text().toLowerCase();
            const content = $(this).find('.note-content').text().toLowerCase();
            const course = $(this).find('.note-course').text().toLowerCase();
            if (title.includes(query) || content.includes(query) || course.includes(query)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }
    function sortNotes(sortBy) {
        const $container = $('.notes-grid');
        const $notes = $container.find('.note-card').toArray();
        $notes.sort(function(a, b) {
            switch(sortBy) {
                case 'title':
                    return $(a).find('h4').text().localeCompare($(b).find('h4').text());
                case 'created':
                case 'modified':
                default:
                    return $(b).index() - $(a).index();
            }
        });
        $container.empty().append($notes);
    }
    $(document).ready(function() {
        init();
        initNotes();
    });
})(jQuery);
