// Notification System
const NotificationSystem = {
    baseUrl: window.baseUrl || '/eduverse/',
    pollInterval: 30000, // 30 seconds
    pollTimer: null,
    isOpen: false,
    lastCount: 0,

    init() {
        this.createNotificationUI();
        this.attachEventListeners();
        this.startPolling();
        this.loadNotifications();
    },

    createNotificationUI() {
        // Check if notification icon already exists
        if (document.querySelector('.notification-icon-wrapper')) {
            return;
        }

        const navbar = document.querySelector('.navbar-icons') || document.querySelector('.navbar');
        if (!navbar) return;

        const notificationHTML = `
            <div class="notification-icon-wrapper" style="position: relative; display: inline-block; margin-left: 15px;">
                <i class="fas fa-bell notification-icon" style="font-size: 20px; cursor: pointer; position: relative;">
                    <span class="notification-badge" style="position: absolute; top: -8px; right: -10px; background: #ff4444; color: white; border-radius: 50%; width: 18px; height: 18px; display: none; align-items: center; justify-content: center; font-size: 10px; font-weight: bold;">0</span>
                </i>
                <div class="notification-dropdown" style="display: none; position: absolute; top: 35px; right: 0; width: 380px; max-height: 500px; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); z-index: 1000; overflow: hidden;">
                    <div class="notification-header" style="padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa;">
                        <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #333;">Notifications</h3>
                        <button class="mark-all-read-btn" style="background: none; border: none; color: #4a90e2; cursor: pointer; font-size: 13px; padding: 5px 10px; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='#e3f2fd'" onmouseout="this.style.background='transparent'">Mark all read</button>
                    </div>
                    <div class="notification-list" style="max-height: 400px; overflow-y: auto;">
                        <div class="notification-loading" style="padding: 40px; text-align: center; color: #999;">
                            <i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i>
                            <p style="margin-top: 10px;">Loading notifications...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        navbar.insertAdjacentHTML('beforeend', notificationHTML);
    },

    attachEventListeners() {
        const notificationIcon = document.querySelector('.notification-icon');
        const markAllReadBtn = document.querySelector('.mark-all-read-btn');

        if (notificationIcon) {
            notificationIcon.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleDropdown();
            });
        }

        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.markAllAsRead();
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const dropdown = document.querySelector('.notification-dropdown');
            const wrapper = document.querySelector('.notification-icon-wrapper');

            if (dropdown && !wrapper.contains(e.target)) {
                this.closeDropdown();
            }
        });
    },

    toggleDropdown() {
        const dropdown = document.querySelector('.notification-dropdown');
        if (!dropdown) return;

        if (this.isOpen) {
            this.closeDropdown();
        } else {
            dropdown.style.display = 'block';
            dropdown.style.animation = 'fadeIn 0.2s ease';
            this.isOpen = true;
            this.loadNotifications();
        }
    },

    closeDropdown() {
        const dropdown = document.querySelector('.notification-dropdown');
        if (!dropdown) return;

        dropdown.style.animation = 'fadeOut 0.2s ease';
        setTimeout(() => {
            dropdown.style.display = 'none';
            this.isOpen = false;
        }, 200);
    },

    async loadNotifications() {
        try {
            const response = await fetch(`${this.baseUrl}ajax/notifications.php?action=get&limit=15`);
            const data = await response.json();

            if (data.success) {
                this.updateBadge(data.unread_count);
                this.renderNotifications(data.notifications);

                // Show desktop notification for new items
                if (data.unread_count > this.lastCount && this.lastCount > 0) {
                    this.showDesktopNotification(data.notifications[0]);
                }
                this.lastCount = data.unread_count;
            }
        } catch (error) {
        }
    },

    updateBadge(count) {
        const badge = document.querySelector('.notification-badge');
        if (!badge) return;

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';

            // Animate badge
            badge.style.animation = 'pulse 0.5s ease';
            setTimeout(() => {
                badge.style.animation = '';
            }, 500);
        } else {
            badge.style.display = 'none';
        }
    },

    renderNotifications(notifications) {
        const listContainer = document.querySelector('.notification-list');
        if (!listContainer) return;

        if (notifications.length === 0) {
            listContainer.innerHTML = `
                <div style="padding: 40px; text-align: center; color: #999;">
                    <i class="fas fa-bell-slash" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                    <p style="margin: 0; font-size: 14px;">No notifications yet</p>
                </div>
            `;
            return;
        }

        const notificationHTML = notifications.map(notif => this.createNotificationItem(notif)).join('');
        listContainer.innerHTML = notificationHTML;

        // Attach click listeners to notification items
        listContainer.querySelectorAll('.notification-item').forEach((item, index) => {
            item.addEventListener('click', () => this.handleNotificationClick(notifications[index]));
        });
    },

    createNotificationItem(notif) {
        const timeAgo = this.getTimeAgo(notif.created_at);
        const isUnread = !notif.is_read;
        const icon = this.getNotificationIcon(notif.type);

        return `
            <div class="notification-item" data-id="${notif.notification_id}" style="padding: 15px 20px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.2s; background: ${isUnread ? '#f0f7ff' : 'white'};" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='${isUnread ? '#f0f7ff' : 'white'}'">
                <div style="display: flex; gap: 12px;">
                    <div style="flex-shrink: 0;">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: ${this.getIconColor(notif.type)}; display: flex; align-items: center; justify-content: center; color: white;">
                            <i class="${icon}" style="font-size: 18px;"></i>
                        </div>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 4px;">
                            <h4 style="margin: 0; font-size: 14px; font-weight: ${isUnread ? '600' : '500'}; color: #333;">${this.escapeHtml(notif.title)}</h4>
                            ${isUnread ? '<span style="width: 8px; height: 8px; background: #4a90e2; border-radius: 50%; flex-shrink: 0; margin-top: 4px;"></span>' : ''}
                        </div>
                        <p style="margin: 0; font-size: 13px; color: #666; line-height: 1.4;">${this.escapeHtml(notif.message)}</p>
                        <span style="font-size: 11px; color: #999; margin-top: 4px; display: block;">${timeAgo}</span>
                    </div>
                </div>
            </div>
        `;
    },

    getNotificationIcon(type) {
        const icons = {
            'course_update': 'fas fa-sync-alt',
            'new_enrollment': 'fas fa-user-plus',
            'review': 'fas fa-star',
            'message': 'fas fa-envelope',
            'achievement': 'fas fa-trophy',
            'certificate': 'fas fa-certificate',
            'assignment': 'fas fa-tasks',
            'discussion': 'fas fa-comments',
            'announcement': 'fas fa-bullhorn',
            'system': 'fas fa-info-circle',
            'payment': 'fas fa-dollar-sign'
        };
        return icons[type] || 'fas fa-bell';
    },

    getIconColor(type) {
        const colors = {
            'course_update': '#4a90e2',
            'new_enrollment': '#52c41a',
            'review': '#f5222d',
            'message': '#1890ff',
            'achievement': '#faad14',
            'certificate': '#13c2c2',
            'assignment': '#722ed1',
            'discussion': '#fa8c16',
            'announcement': '#eb2f96',
            'system': '#595959',
            'payment': '#52c41a'
        };
        return colors[type] || '#4a90e2';
    },

    async handleNotificationClick(notif) {
        // Mark as read
        if (!notif.is_read) {
            await this.markAsRead(notif.notification_id);
        }

        // Navigate to link URL if exists
        if (notif.link_url) {
            window.location.href = notif.link_url;
        }

        this.closeDropdown();
    },

    async markAsRead(notificationId) {
        try {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('notification_id', notificationId);

            await fetch(`${this.baseUrl}ajax/notifications.php`, {
                method: 'POST',
                body: formData
            });

            this.loadNotifications();
        } catch (error) {
        }
    },

    async markAllAsRead() {
        try {
            const formData = new FormData();
            formData.append('action', 'mark_all_read');

            const response = await fetch(`${this.baseUrl}ajax/notifications.php`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                this.loadNotifications();
                if (typeof showNotification === 'function') {
                    showNotification('All notifications marked as read', 'success');
                }
            }
        } catch (error) {
        }
    },

    startPolling() {
        // Initial load
        this.loadNotifications();

        // Poll every 30 seconds
        this.pollTimer = setInterval(() => {
            this.loadNotifications();
        }, this.pollInterval);
    },

    stopPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
    },

    showDesktopNotification(notif) {
        // Request permission for desktop notifications
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification(notif.title, {
                body: notif.message,
                icon: `${this.baseUrl}public/images/logo.png`,
                badge: `${this.baseUrl}public/images/logo.png`,
                tag: notif.notification_id
            });

            notification.onclick = () => {
                window.focus();
                this.handleNotificationClick(notif);
                notification.close();
            };
        } else if ('Notification' in window && Notification.permission !== 'denied') {
            Notification.requestPermission();
        }
    },

    getTimeAgo(timestamp) {
        const now = new Date();
        const notifTime = new Date(timestamp);
        const diff = Math.floor((now - notifTime) / 1000); // seconds

        if (diff < 60) return 'Just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
        return notifTime.toLocaleDateString();
    },

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
};

// Initialize notification system when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => NotificationSystem.init());
} else {
    NotificationSystem.init();
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .notification-list::-webkit-scrollbar {
        width: 6px;
    }
    
    .notification-list::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    .notification-list::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 3px;
    }
    
    .notification-list::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
`;
document.head.appendChild(style);
