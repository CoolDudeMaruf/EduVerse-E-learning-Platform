/* ===================================
   Student Dashboard Extended Features
   Theme, Notifications, AI Chat, Tab Switching
   =================================== */

$(document).ready(function() {
    // ========================
    // THEME TOGGLE FUNCTIONALITY
    // ========================
    
    // Detect browser's preferred color scheme
    const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
    
    // Get stored theme or use browser preference
    function getInitialTheme() {
        const storedTheme = localStorage.getItem('theme');
        if (storedTheme) {
            return storedTheme;
        }
        return prefersDarkScheme.matches ? 'dark' : 'light';
    }
    
    // Apply theme to body
    function applyTheme(theme) {
        document.body.setAttribute('data-theme', theme);
        const icon = $('#btnThemeToggle .material-icons');
        icon.text(theme === 'dark' ? 'light_mode' : 'dark_mode');
    }
    
    // Set initial theme
    const initialTheme = getInitialTheme();
    applyTheme(initialTheme);
    
    // Theme toggle button click handler
    $('#btnThemeToggle').on('click', function() {
        const currentTheme = document.body.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        applyTheme(newTheme);
        localStorage.setItem('theme', newTheme);
    });
    
    // Listen for system theme changes
    prefersDarkScheme.addEventListener('change', (e) => {
        if (!localStorage.getItem('theme')) {
            applyTheme(e.matches ? 'dark' : 'light');
        }
    });

    // ========================
    // NOTIFICATIONS FUNCTIONALITY
    // ========================
    
    // Notification filter buttons
    $('.notification-filters .filter-btn').on('click', function() {
        $('.notification-filters .filter-btn').removeClass('active');
        $(this).addClass('active');
        
        const filter = $(this).data('filter');
        
        if (filter === 'all') {
            $('.notification-full-item').show();
        } else {
            $('.notification-full-item').hide();
            $(`.notification-full-item[data-type="${filter}"]`).show();
        }
    });
    
    // Mark notification as read
    $('.mark-read').on('click', function(e) {
        e.stopPropagation();
        $(this).closest('.notification-full-item').removeClass('unread');
        $(this).fadeOut();
        updateNotificationCount();
    });
    
    // Update notification count
    function updateNotificationCount() {
        const unreadCount = $('.notification-full-item.unread').length;
        $('.notifications-group .count-badge').text(unreadCount);
        $('.notification-badge').text(unreadCount);
        
        if (unreadCount === 0) {
            $('.notification-badge').hide();
            $('.count-badge').hide();
        }
    }
    
    // Dismiss notification button
    $('.notification-actions .btn-secondary').on('click', function() {
        $(this).closest('.notification-full-item').fadeOut(300, function() {
            $(this).remove();
            updateNotificationCount();
        });
    });
    
    // Top notification bell button
    $('#btnNotifications').on('click', function() {
        // Hide all sections
        $('.dashboard-section').hide();
        // Show notifications tab
        $('#notifications-tab').show();
        // Update sidebar active state
        $('.sidebar-link').removeClass('active');
        $('a[href="#notifications-tab"]').addClass('active');
    });

    // ========================
    // AI CHAT POPUP FUNCTIONALITY
    // ========================
    let chatMinimized = false;

    // Open AI Chat
    $('#aiAssistantBtn').on('click', function() {
        $('#aiChatPopup').fadeIn(300);
        if (chatMinimized) {
            $('#aiChatPopup').removeClass('minimized');
            chatMinimized = false;
        }
        $('#aiChatInput').focus();
    });

    // Close AI Chat
    $('#btnCloseChat').on('click', function() {
        $('#aiChatPopup').fadeOut(300);
        chatMinimized = false;
    });

    // Minimize AI Chat
    $('#btnMinimizeChat').on('click', function() {
        if (chatMinimized) {
            $('#aiChatPopup').removeClass('minimized');
            chatMinimized = false;
        } else {
            $('#aiChatPopup').addClass('minimized');
            chatMinimized = true;
        }
    });

    // Send Message Function
    function sendAIMessage(message) {
        if (!message.trim()) return;

        // Add user message
        const userMessageHTML = `
            <div class="user-message-wrapper">
                <div class="user-message">
                    <div class="message-content">
                        <p>${message}</p>
                    </div>
                    <div class="message-time">${getCurrentTime()}</div>
                </div>
                <div class="user-message-avatar">
                    <img src="https://ui-avatars.com/api/?name=John+Doe&background=6366f1&color=fff" alt="You">
                </div>
            </div>
        `;
        
        $('.ai-quick-actions').remove(); // Remove quick actions after first message
        $('#aiChatMessages').append(userMessageHTML);
        $('#aiChatInput').val('');
        scrollToBottom();

        // Show typing indicator
        $('#typingIndicator').fadeIn();

        // Simulate AI response
        setTimeout(function() {
            $('#typingIndicator').fadeOut();
            const aiResponse = getAIResponse(message);
            const aiMessageHTML = `
                <div class="ai-message-wrapper">
                    <div class="ai-message-avatar">
                        <span class="material-icons">smart_toy</span>
                    </div>
                    <div class="ai-message">
                        <div class="message-content">
                            ${aiResponse}
                        </div>
                        <div class="message-time">${getCurrentTime()}</div>
                    </div>
                </div>
            `;
            $('#aiChatMessages').append(aiMessageHTML);
            scrollToBottom();
        }, 1500);
    }

    // Get current time
    function getCurrentTime() {
        const now = new Date();
        return now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }

    // Scroll chat to bottom
    function scrollToBottom() {
        const chatMessages = document.getElementById('aiChatMessages');
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // AI Response Generator
    function getAIResponse(userMessage) {
        const messageLower = userMessage.toLowerCase();
        
        if (messageLower.includes('recommend') || messageLower.includes('course') || messageLower.includes('learn')) {
            return `<p>Based on your current progress and interests, I recommend:</p>
                    <ul>
                        <li><strong>React Masterclass 2024</strong> - Since you're 65% through Web Development, React is the perfect next step!</li>
                        <li><strong>Advanced JavaScript Patterns</strong> - Deepen your JS knowledge with design patterns and best practices.</li>
                    </ul>
                    <p>Would you like to know more about any of these courses?</p>`;
        }
        
        if (messageLower.includes('async') || messageLower.includes('await') || messageLower.includes('promise')) {
            return `<p><strong>Async/Await Explained:</strong></p>
                    <p>Async/await is syntactic sugar for working with Promises in JavaScript:</p>
                    <ul>
                        <li><code>async</code> functions always return a Promise</li>
                        <li><code>await</code> pauses execution until the Promise resolves</li>
                        <li>Use try/catch for error handling</li>
                    </ul>
                    <p>Example:</p>
                    <pre>async function getData() {
  try {
    const response = await fetch(url);
    const data = await response.json();
    return data;
  } catch (error) {
    console.error(error);
  }
}</pre>
                    <p>Need more examples or have questions?</p>`;
        }
        
        if (messageLower.includes('progress') || messageLower.includes('how am i doing')) {
            return `<p>Great question! Here's your progress summary:</p>
                    <ul>
                        <li>📚 <strong>8 courses enrolled</strong></li>
                        <li>✅ <strong>3 courses completed</strong></li>
                        <li>⏱️ <strong>42 hours learning time</strong></li>
                        <li>🔥 <strong>7-day learning streak</strong></li>
                    </ul>
                    <p>You're doing excellent! Your Web Development Bootcamp is 65% complete. Keep up the momentum! 🚀</p>`;
        }
        
        if (messageLower.includes('note') || messageLower.includes('notes')) {
            return `<p><strong>Effective Note-Taking Tips:</strong></p>
                    <ol>
                        <li><strong>Cornell Method:</strong> Divide notes into cues, notes, and summary</li>
                        <li><strong>Use the built-in notes feature:</strong> Click the notes icon during lectures</li>
                        <li><strong>Review within 24 hours:</strong> Reinforces learning by 60%</li>
                        <li><strong>Add examples:</strong> Write your own code examples</li>
                        <li><strong>Use colors:</strong> Highlight key concepts</li>
                    </ol>
                    <p>Would you like me to show you how to use our notes feature?</p>`;
        }
        
        if (messageLower.includes('study') || messageLower.includes('tips') || messageLower.includes('learn faster')) {
            return `<p><strong>Study Tips to Learn Faster:</strong></p>
                    <ul>
                        <li>🎯 <strong>Pomodoro Technique:</strong> 25 min focus + 5 min break</li>
                        <li>🔄 <strong>Active Recall:</strong> Test yourself frequently</li>
                        <li>📅 <strong>Spaced Repetition:</strong> Review material at intervals</li>
                        <li>💻 <strong>Build Projects:</strong> Apply what you learn immediately</li>
                        <li>👥 <strong>Join Study Groups:</strong> Learn with others</li>
                    </ul>
                    <p>Your current 7-day streak shows you're consistent - that's key! 🔥</p>`;
        }

        if (messageLower.includes('certificate') || messageLower.includes('certification')) {
            return `<p>You have <strong>3 certificates</strong> earned! 🎓</p>
                    <ul>
                        <li>Web Development Fundamentals</li>
                        <li>JavaScript Essentials</li>
                        <li>Responsive Web Design</li>
                    </ul>
                    <p>Complete your current courses to earn more certificates. Each certificate is verified and can be shared on LinkedIn!</p>`;
        }
        
        // Default response
        return `<p>That's a great question! I'm here to help with:</p>
                <ul>
                    <li>📚 Course recommendations and guidance</li>
                    <li>💡 Explaining programming concepts</li>
                    <li>📊 Tracking your learning progress</li>
                    <li>✍️ Study tips and strategies</li>
                    <li>❓ Answering specific questions</li>
                </ul>
                <p>Could you provide more details about what you'd like help with?</p>`;
    }

    // Send message on button click
    $('#btnSendMessage').on('click', function() {
        const message = $('#aiChatInput').val();
        sendAIMessage(message);
    });

    // Send message on Enter key
    $('#aiChatInput').on('keypress', function(e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            const message = $(this).val();
            sendAIMessage(message);
        }
    });

    // Quick Action Buttons
    $('.quick-action-btn').on('click', function() {
        const action = $(this).data('action');
        let message = '';
        
        switch(action) {
            case 'recommend':
                message = 'Can you recommend a course for me?';
                break;
            case 'explain':
                message = 'Can you explain a programming concept?';
                break;
            case 'tips':
                message = 'What are some good study tips?';
                break;
            case 'progress':
                message = 'How am I doing with my learning progress?';
                break;
        }
        
        $('#aiChatInput').val(message);
        sendAIMessage(message);
    });

    // Suggestion Chips
    $('.suggestion-chip').on('click', function() {
        const message = $(this).text();
        $('#aiChatInput').val(message);
        sendAIMessage(message);
    });

    // Voice Input (placeholder)
    $('#btnVoiceInput').on('click', function() {
        alert('Voice input feature coming soon! 🎤');
    });

    // Attachment (placeholder)
    $('#btnAttachment').on('click', function() {
        alert('File attachment feature coming soon! 📎');
    });

    // ========================
    // TAB SWITCHING
    // ========================
    
    // Sidebar Links - Tab Switching
    $('.sidebar-link').on('click', function(e) {
        e.preventDefault();
        $('.sidebar-link').removeClass('active');
        $(this).addClass('active');
        const target = $(this).attr('href').substring(1);
        
        // Hide all tab content
        $('.tab-content').hide();
        
        // Show overview content (default sections) or specific tab
        if (target === 'overview') {
            // Show all default sections
            $('.dashboard-section').not('.tab-content').show();
        } else {
            // Hide all default sections except the target
            $('.dashboard-section').not('.tab-content').hide();
            // Show the specific tab
            $('#' + target).show();
        }
        
        // Scroll to top
        $('.dashboard-main').scrollTop(0);
    });

    // Filter tabs for My Learning
    $('.filter-tab').on('click', function() {
        $('.filter-tab').removeClass('active');
        $(this).addClass('active');
        const filter = $(this).data('filter');
        
        if (filter === 'all') {
            $('.course-card').show();
        } else {
            $('.course-card').hide();
            $(`.course-card[data-status="${filter}"]`).show();
        }
    });
});
