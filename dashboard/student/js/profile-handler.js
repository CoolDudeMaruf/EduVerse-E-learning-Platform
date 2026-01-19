/**
 * Profile Handler - Handles all profile-related functionality
 */
(function($) {
    'use strict';

    const ProfileHandler = {
        /**
         * Initialize profile functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            const self = this;

            // Edit profile button
            $(document).on('click', '#btnEditProfile', function(e) {
                e.preventDefault();
                console.log('Edit profile clicked');
                self.enableProfileEditing(true);
            });

            // Cancel edit
            $(document).on('click', '#btnCancelEdit', function(e) {
                e.preventDefault();
                self.enableProfileEditing(false);
            });

            // Save profile form
            $(document).on('click', '#btnSaveProfile', function(e) {
                e.preventDefault();
                self.saveProfile();
            });

            // Avatar upload button
            $(document).on('click', '#btnUploadAvatar', function(e) {
                e.preventDefault();
                $('#avatarInput').trigger('click');
            });

            // Avatar file change
            $(document).on('change', '#avatarInput', function() {
                self.handleAvatarUpload(this);
            });

            // Change password button
            $(document).on('click', '#btnChangePassword', function(e) {
                e.preventDefault();
                self.openModal('#passwordModal');
            });

            // Save password
            $(document).on('click', '#btnSavePassword', function(e) {
                e.preventDefault();
                self.savePassword();
            });

            // Edit social links
            $(document).on('click', '#btnEditSocialLinks', function(e) {
                e.preventDefault();
                self.loadSocialLinks();
                self.openModal('#socialLinksModal');
            });

            // Add social link
            $(document).on('click', '#btnAddSocialLink', function(e) {
                e.preventDefault();
                self.addSocialLinkRow();
            });

            // Save social links
            $(document).on('click', '#btnSaveSocialLinks', function(e) {
                e.preventDefault();
                self.saveSocialLinks();
            });

            // Remove social link
            $(document).on('click', '.btn-remove-social', function() {
                $(this).closest('.social-link-row').remove();
            });

            // Delete account button
            $(document).on('click', '#btnDeleteAccount', function(e) {
                e.preventDefault();
                $('#deleteAccountPassword').val('');
                self.openModal('#deleteAccountModal');
            });

            // Confirm delete account
            $(document).on('click', '#btnConfirmDeleteAccount', function(e) {
                e.preventDefault();
                self.deleteAccount();
            });

            // Modal close buttons
            $(document).on('click', '.profile-modal .modal-close, .profile-modal .modal-backdrop', function() {
                self.closeAllModals();
            });
        },

        /**
         * Enable/disable profile editing
         */
        enableProfileEditing: function(enable) {
            const fields = [
                '#inputFirstName', '#inputLastName', '#inputUsername', '#inputEmail',
                '#inputPhone', '#inputCountryCode', '#inputBio', '#inputHeadline',
                '#inputOccupation', '#inputCompany', '#inputLocation', '#inputCountry',
                '#inputDateOfBirth', '#inputGender', '#inputBloodGroup', '#inputTimezone', '#inputLanguage'
            ];

            fields.forEach(function(field) {
                const $el = $(field);
                if ($el.is('select')) {
                    $el.prop('disabled', !enable);
                } else {
                    $el.prop('readonly', !enable);
                }
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
        },

        /**
         * Save profile with AJAX
         */
        saveProfile: function() {
            const self = this;
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

            if (!data.first_name || !data.first_name.trim()) {
                this.showToast('First name is required', 'error');
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
                    self.showToast('Profile updated successfully', 'success');
                    self.enableProfileEditing(false);
                    
                    // Update display name
                    const fullName = data.first_name + ' ' + (data.last_name || '');
                    $('#profileDisplayName').text(fullName.trim());
                    if (data.headline) $('#profileDisplayHeadline').text(data.headline);
                } else {
                    self.showToast(response.error || 'Failed to update profile', 'error');
                }
            }).fail(function() {
                self.showToast('Failed to update profile', 'error');
            }).always(function() {
                $('#btnSaveProfile').prop('disabled', false).html('Save Changes');
            });
        },

        /**
         * Handle avatar upload
         */
        handleAvatarUpload: function(input) {
            const self = this;
            const file = input.files[0];
            if (!file) return;

            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                this.showToast('Please select a valid image file (JPG, PNG, GIF, WebP)', 'error');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                this.showToast('File size must be less than 5MB', 'error');
                return;
            }

            // Preview image immediately
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#profileAvatar').attr('src', e.target.result);
                $('.user-avatar img').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);

            // Upload to server
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
                    self.showToast('Avatar updated successfully', 'success');
                    const baseUrl = window.EDUVERSE_BASE_URL || '/';
                    const avatarUrl = baseUrl + response.avatar_url;
                    $('#profileAvatar').attr('src', avatarUrl);
                    $('.user-avatar img').attr('src', avatarUrl);
                } else {
                    self.showToast(response.error || 'Failed to upload avatar', 'error');
                }
            }).fail(function() {
                self.showToast('Failed to upload avatar', 'error');
            });
        },

        /**
         * Save password
         */
        savePassword: function() {
            const self = this;
            const current = $('#currentPassword').val();
            const newPass = $('#newPassword').val();
            const confirm = $('#confirmPassword').val();

            if (!current || !newPass || !confirm) {
                this.showToast('All password fields are required', 'error');
                return;
            }

            if (newPass !== confirm) {
                this.showToast('New passwords do not match', 'error');
                return;
            }

            if (newPass.length < 8) {
                this.showToast('Password must be at least 8 characters', 'error');
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
                    self.showToast('Password changed successfully', 'success');
                    self.closeAllModals();
                    $('#currentPassword, #newPassword, #confirmPassword').val('');
                } else {
                    self.showToast(response.error || 'Failed to change password', 'error');
                }
            }).fail(function() {
                self.showToast('Failed to change password', 'error');
            }).always(function() {
                $('#btnSavePassword').prop('disabled', false).html('Change Password');
            });
        },

        /**
         * Add social link row
         */
        addSocialLinkRow: function(platform, url) {
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
                options += '<option value="' + p.value + '"' + selected + '>' + p.label + '</option>';
            });

            const html = '<div class="social-link-row">' +
                '<select class="form-control social-platform-select">' + options + '</select>' +
                '<input type="url" class="form-control social-url-input" placeholder="https://..." value="' + url + '">' +
                '<button type="button" class="btn-icon btn-remove-social">' +
                '<span class="material-icons">delete</span>' +
                '</button></div>';

            $('#socialLinksEditContainer').append(html);
        },

        /**
         * Save social links
         */
        saveSocialLinks: function() {
            const self = this;
            const links = [];

            $('.social-link-row').each(function() {
                const platform = $(this).find('.social-platform-select').val();
                const url = $(this).find('.social-url-input').val().trim();
                if (platform && url) {
                    links.push({ platform: platform, url: url });
                }
            });

            $('#btnSaveSocialLinks').prop('disabled', true).html('<span class="material-icons spin">sync</span> Saving...');

            $.ajax({
                url: 'ajax/dashboard_handler.php',
                method: 'POST',
                data: {
                    action: 'save_social_links',
                    links: JSON.stringify(links)
                },
                dataType: 'json'
            }).done(function(response) {
                if (response.success) {
                    self.showToast('Social links saved successfully', 'success');
                    self.closeAllModals();
                    self.updateSocialLinksDisplay(links);
                } else {
                    self.showToast(response.error || 'Failed to save social links', 'error');
                }
            }).fail(function() {
                self.showToast('Failed to save social links', 'error');
            }).always(function() {
                $('#btnSaveSocialLinks').prop('disabled', false).html('Save Links');
            });
        },

        /**
         * Load existing social links
         */
        loadSocialLinks: function() {
            const self = this;
            $('#socialLinksEditContainer').html('<p class="text-muted text-center">Loading...</p>');

            $.ajax({
                url: 'ajax/dashboard_handler.php',
                method: 'POST',
                data: { action: 'get_social_links' },
                dataType: 'json'
            }).done(function(response) {
                $('#socialLinksEditContainer').empty();
                if (response.success && response.data && response.data.length > 0) {
                    response.data.forEach(function(link) {
                        self.addSocialLinkRow(link.platform, link.url);
                    });
                } else {
                    // Add one empty row if no links
                    self.addSocialLinkRow();
                }
            }).fail(function() {
                $('#socialLinksEditContainer').empty();
                self.addSocialLinkRow();
            });
        },

        /**
         * Update social links display after saving
         */
        updateSocialLinksDisplay: function(links) {
            const container = $('#socialLinksContainer, .social-links');
            if (container.length === 0) return;

            const iconMap = {
                website: 'language',
                linkedin: 'link',
                twitter: 'tag',
                github: 'code',
                facebook: 'facebook',
                instagram: 'photo_camera',
                youtube: 'play_circle',
                portfolio: 'work',
                other: 'link'
            };

            let html = '';
            links.forEach(function(link) {
                const icon = iconMap[link.platform] || 'link';
                html += '<a href="' + link.url + '" target="_blank" class="social-link-item" title="' + link.platform + '">' +
                        '<span class="material-icons">' + icon + '</span></a>';
            });

            container.html(html || '<p class="text-muted">No social links added</p>');
        },

        /**
         * Delete account
         */
        deleteAccount: function() {
            const self = this;
            const password = $('#deleteAccountPassword').val();

            if (!password) {
                this.showToast('Please enter your password', 'error');
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
                    self.showToast('Account deleted successfully', 'success');
                    setTimeout(function() {
                        window.location.href = window.EDUVERSE_BASE_URL || '/';
                    }, 1500);
                } else {
                    self.showToast(response.error || 'Failed to delete account', 'error');
                }
            }).fail(function() {
                self.showToast('Failed to delete account', 'error');
            }).always(function() {
                $('#btnConfirmDeleteAccount').prop('disabled', false).html('Delete My Account');
            });
        },

        /**
         * Open modal
         */
        openModal: function(modalId) {
            $(modalId).addClass('active');
            $('body').addClass('modal-open');
        },

        /**
         * Close all modals
         */
        closeAllModals: function() {
            $('.profile-modal').removeClass('active');
            $('body').removeClass('modal-open');
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            type = type || 'info';
            
            // Remove existing toasts
            $('.toast-notification').remove();
            
            const iconMap = {
                success: 'check_circle',
                error: 'error',
                warning: 'warning',
                info: 'info'
            };
            
            const $toast = $('<div class="toast-notification toast-' + type + '">' +
                '<span class="material-icons">' + iconMap[type] + '</span>' +
                '<span class="toast-message">' + message + '</span>' +
                '</div>');
            
            $('body').append($toast);
            
            setTimeout(function() {
                $toast.addClass('show');
            }, 10);
            
            setTimeout(function() {
                $toast.removeClass('show');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            }, 3000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ProfileHandler.init();
        console.log('ProfileHandler initialized');
    });

    // Expose globally
    window.ProfileHandler = ProfileHandler;

})(jQuery);
