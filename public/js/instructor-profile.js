

$(document).ready(function() {
    const instructorId = window.instructorId;
    
    if (!instructorId) {
        window.location.href = window.baseUrl + 'instructors';
        return;
    }

    // Initialize page
    loadCourses();
    setupEventListeners();
    initReviews();
});

// Load courses from database
function loadCourses(page = 1) {
    const instructorId = window.instructorId;
    const sortBy = $('#courses-sort').val() || 'newest';
    
    $.ajax({
        url: window.baseUrl + 'ajax/courses.php',
        type: 'GET',
        data: {
            instructor: instructorId,
            sortby: sortBy,
            page: page,
            instructorId : instructorId,
            limit: 12
        },
        success: function(response) {
            if (response.success && response.courses) {
                const grid = $('#courses-grid');
                grid.empty();
                
                if (response.courses.length === 0) {
                    grid.html('<p style="text-align:center;color:#9ca3af;padding:2rem;">No courses found.</p>');
                    $('#total-courses').text('0');
                    $('#courses-pagination').empty();
                    return;
                }
                
                response.courses.forEach(course => {
                    const card = createCourseCard(course);
                    grid.append(card);
                });
                
                $('#total-courses').text(response.totalCourses || response.courses.length);
                renderPagination('courses', response.currentPage || page, response.totalPages || 1);
            }
        },
        error: function() {
            $('#courses-grid').html('<p style="text-align:center;color:#ef4444;padding:2rem;">Failed to load courses.</p>');
        }
    });
}

// Create course card
function createCourseCard(course) {
    const rating = parseFloat(course.average_rating || 0);
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    let stars = '★'.repeat(fullStars);
    if (hasHalfStar) stars += '☆';
    stars += '☆'.repeat(5 - fullStars - (hasHalfStar ? 1 : 0));
    
    const badge = course.is_bestseller ? '<span class="course-badge">Bestseller</span>' : '';
    const thumbnail = course.thumbnail_url || window.baseUrl + 'public/images/default-course.png';
    const price = parseFloat(course.price || 0);
    const enrollmentCount = parseInt(course.enrollment_count || 0);
    const reviewCount = parseInt(course.review_count || 0);

    return $(`
        <div class="course-card" data-course-id="${course.course_id}">
            <div class="course-thumbnail">
                <img src="${thumbnail}" alt="${escapeHtml(course.title)}">
                ${badge}
            </div>
            <div class="course-info">
                <h3 class="course-title">${escapeHtml(course.title)}</h3>
                <div class="course-meta">
                    <div class="course-meta-item">
                        <span class="material-icons">schedule</span>
                        <span>${course.durationDisplay || 'N/A'}</span>
                    </div>
                    <div class="course-meta-item">
                        <span class="material-icons">movie</span>
                        <span>${course.total_lectures || 0} lectures</span>
                    </div>
                    <div class="course-meta-item">
                        <span class="material-icons">signal_cellular_alt</span>
                        <span>${course.level || 'All Levels'}</span>
                    </div>
                </div>
                <div class="course-rating">
                    <span class="rating-stars">${stars}</span>
                    <span class="rating-value">${rating.toFixed(1)}</span>
                    <span class="rating-count">(${formatNumber(reviewCount)})</span>
                </div>
                <div class="course-footer">
                    <div class="course-price ${course.is_free=='1' ? 'free' : ''}">
                        ${course.is_free == '1' ? 'Free' : '$' + price.toFixed(2)}
                    </div>
                    <div class="course-students">
                        <span class="material-icons" style="font-size: 16px; color: #9ca3af;">people</span>
                        <span style="color: #6b7280; font-size: 0.875rem;">${formatNumber(enrollmentCount)}</span>
                    </div>
                </div>
            </div>
        </div>
    `);

}

// Initialize reviews
function initReviews() {
    loadReviews(1);
    setupReviewEventListeners();
}

function setupReviewEventListeners() {
    const searchInput = document.getElementById('searchReviews');
    const sortSelect = document.getElementById('sortReviews');
    const loadMoreBtn = document.getElementById('loadMoreReviews');

    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            loadReviews(1);
        }, 300));
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            loadReviews(1);
        });
    }

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => {
            const currentPage = parseInt(loadMoreBtn.dataset.page || '1');
            loadReviews(currentPage + 1, true);
        });
    }
}

// Load reviews from database
function loadReviews(page = 1, append = false) {
    const instructorId = window.instructorId;
    const searchTerm = document.getElementById('searchReviews')?.value || '';
    const sortBy = document.getElementById('sortReviews')?.value || 'helpful';
    const reviewsList = document.getElementById('reviewsList');

    if (!reviewsList) return;
    
    $.ajax({
        url: window.baseUrl + 'ajax/reviews.php',
        type: 'GET',
        data: {
            action: 'get_reviews',
            instructor_id: instructorId,
            page: page,
            search: searchTerm,
            sort: sortBy
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderReviews(response.reviews, append);
                updateReviewsPagination(response.current_page, response.total_pages);

                if (!append && reviewsList) {
                    reviewsList.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } else {
                if (!append) {
                    reviewsList.innerHTML = '<p class=\"placeholder-text\">No reviews found.</p>';
                }
            }
        },
        error: function() {
            if (!append) {
                reviewsList.innerHTML = '<p class=\"placeholder-text\">Error loading reviews.</p>';
            }
        }
    });
}

function renderReviews(reviews, append = false) {
    const reviewsList = document.getElementById('reviewsList');

    if (!append) {
        reviewsList.innerHTML = '';
    }

    if (reviews.length === 0) {
        if (!append) {
            reviewsList.innerHTML = '<p class=\"placeholder-text\">No reviews found matching your search.</p>';
        }
        return;
    }

    reviews.forEach(review => {
        const stars = Array(5).fill(0).map((_, i) => 
            `<span class=\"material-icons\">${i < review.rating ? 'star' : 'star_border'}</span>`
        ).join('');

        const userMarkedHelpful = review.user_marked_helpful ? 'active' : '';
        const userMarkedNotHelpful = review.user_marked_not_helpful ? 'active' : '';
        
        const reviewHTML = `
            <div class=\"review-item\" data-review-id=\"${review.review_id}\">
                <div class=\"review-header\">
                    <div class=\"reviewer-avatar\">${(review.first_name || 'U').charAt(0).toUpperCase()}${(review.last_name || 'U').charAt(0).toUpperCase()}</div>
                    <div class=\"reviewer-info\">
                        <h4>${escapeHtml(review.first_name + ' ' + review.last_name)}</h4>
                        <div class=\"review-meta\">
                            <div class=\"stars\">${stars}</div>
                            <span class=\"review-date\">${formatReviewDate(review.created_at)}</span>
                        </div>
                    </div>
                </div>
                <div class=\"review-content\">
                    ${review.review_title ? `<h5>${escapeHtml(review.review_title)}</h5>` : ''}
                    <p>${escapeHtml(review.review_text)}</p>
                </div>
                ${review.course_title ? `<div class=\"review-course\">Course: <strong>${escapeHtml(review.course_title)}</strong></div>` : ''}
                <div class=\"review-actions\">
                    <button class=\"btn-reaction btn-helpful ${userMarkedHelpful}\" data-review-id=\"${review.review_id}\" data-is-helpful=\"1\">
                        <span class=\"material-icons\">thumb_up</span>
                        <span>Helpful (<span class=\"helpful-count\">${review.helpful_count || 0}</span>)</span>
                    </button>
                    <button class=\"btn-reaction btn-not-helpful ${userMarkedNotHelpful}\" data-review-id=\"${review.review_id}\" data-is-helpful=\"0\">
                        <span class=\"material-icons\">thumb_down</span>
                        <span>Not Helpful (<span class=\"not-helpful-count\">${review.not_helpful_count || 0}</span>)</span>
                    </button>
                    <button class=\"btn-reaction btn-report\" data-review-id=\"${review.review_id}\">
                        <span class=\"material-icons\">flag</span>
                        Report
                    </button>
                </div>
            </div>
        `;

        reviewsList.insertAdjacentHTML('beforeend', reviewHTML);
    });
    
    // Setup review action handlers
    setupReviewActionHandlers();
}

function setupReviewActionHandlers() {
    // Helpful/Not helpful buttons
    $(document).off('click', '.btn-helpful, .btn-not-helpful').on('click', '.btn-helpful, .btn-not-helpful', function() {
        const reviewId = $(this).data('review-id');
        const isHelpful = $(this).data('is-helpful');
        markReviewHelpfulness(reviewId, isHelpful, $(this));
    });

    // Report button
    $(document).off('click', '.btn-report').on('click', '.btn-report', function() {
        const reviewId = $(this).data('review-id');
        if (confirm('Report this review as inappropriate?')) {
            reportReview(reviewId);
        }
    });
}

function markReviewHelpfulness(reviewId, isHelpful, button) {
    if (!window.currentUser) {
        showToast('Please login to mark reviews as helpful', 'info');
        return;
    }

    $.ajax({
        url: window.baseUrl + 'ajax/reviews.php',
        type: 'POST',
        data: {
            action: 'mark_helpful',
            review_id: reviewId,
            is_helpful: isHelpful
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const reviewItem = button.closest('.review-item');
                const helpfulBtn = reviewItem.find('.btn-helpful');
                const notHelpfulBtn = reviewItem.find('.btn-not-helpful');
                
                if (isHelpful) {
                    helpfulBtn.toggleClass('active');
                    helpfulBtn.find('.helpful-count').text(response.helpful_count);
                    notHelpfulBtn.removeClass('active');
                } else {
                    notHelpfulBtn.toggleClass('active');
                    notHelpfulBtn.find('.not-helpful-count').text(response.not_helpful_count);
                    helpfulBtn.removeClass('active');
                }
            } else {
                showToast(response.error || 'Failed to update', 'error');
            }
        },
        error: function() {
            showToast('Error updating review helpfulness', 'error');
        }
    });
}

function reportReview(reviewId) {
    if (!window.currentUser) {
        showToast('Please login to report reviews', 'info');
        return;
    }

    $.ajax({
        url: window.baseUrl + 'ajax/reviews.php',
        type: 'POST',
        data: {
            action: 'report_review',
            review_id: reviewId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('Review reported successfully. Thank you for your feedback.', 'success');
            } else {
                showToast(response.error || 'Failed to report review', 'error');
            }
        },
        error: function() {
            showToast('Error reporting review', 'error');
        }
    });
}

function updateReviewsPagination(currentPage, totalPages) {
    const paginationContainer = document.getElementById('reviewsPagination');
    const loadMoreBtn = document.getElementById('loadMoreReviews');

    if (!paginationContainer || !loadMoreBtn) return;

    if (currentPage < totalPages) {
        paginationContainer.style.display = 'block';
        loadMoreBtn.dataset.page = currentPage;
    } else {
        paginationContainer.style.display = 'none';
    }
}

function formatReviewDate(dateString) {
    if (!dateString) return 'Recently';
    
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Create review card (kept for compatibility)
function createReviewCard(review) {
    const rating = parseInt(review.rating || 0);
    const stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
    const avatar = review.user_avatar || window.baseUrl + 'public/images/default-avatar.png';
    const userName = review.user_name || 'Anonymous';
    const timeAgo = review.time_ago || formatDate(review.created_at);
    
    return $(`
        <div class="review-item">
            <div class="review-header">
                <div class="reviewer-info">
                    <div class="reviewer-avatar">
                        <img src="${avatar}" alt="${escapeHtml(userName)}">
                    </div>
                    <div class="reviewer-details">
                        <h4>${escapeHtml(userName)}</h4>
                        <p class="review-date">${timeAgo}</p>
                    </div>
                </div>
                <div class="review-rating">
                    <span class="stars">${stars}</span>
                </div>
            </div>
            <div class="review-content">
                ${escapeHtml(review.review_text || review.content || '')}
            </div>
            ${review.course_title ? `<div class="review-course">
                Course: <strong>${escapeHtml(review.course_title)}</strong>
            </div>` : ''}
        </div>
    `);
}

// Setup event listeners
function setupEventListeners() {
    // Tab switching
    $('.tab-btn').on('click', function() {
        const tab = $(this).data('tab');
        
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');
        
        $('.tab-content').removeClass('active');
        $(`#${tab}-tab`).addClass('active');
    });
    
    // Follow button
    $('#followBtn').on('click', function() {
        $(this).toggleClass('following');
        if ($(this).hasClass('following')) {
            $(this).html('<span class="material-icons">check</span> Following');
            showToast('You are now following this instructor', 'success');
        } else {
            $(this).html('<span class="material-icons">add</span> Follow');
            showToast('Unfollowed instructor', 'info');
        }
    });
    
    // Message button
    $('.btn-message').on('click', function() {
        // Switch to messages tab
        $('.tab-btn[data-tab="messages"]').click();
    });
    
    // Share button
    $('.btn-share').on('click', function() {
        if (navigator.share) {
            navigator.share({
                title: $('#instructor-name').text(),
                text: 'Check out this instructor on EduVerse!',
                url: window.location.href
            });
        } else {
            copyToClipboard(window.location.href);
            showToast('Link copied to clipboard!', 'success');
        }
    });
    
    // Course sorting
    $('#courses-sort').on('change', function() {
        loadCourses();
    });
    
    // View toggle
    $('.view-btn').on('click', function() {
        const view = $(this).data('view');
        
        $('.view-btn').removeClass('active');
        $(this).addClass('active');
        
        if (view === 'list') {
            $('#courses-grid').addClass('list-view');
        } else {
            $('#courses-grid').removeClass('list-view');
        }
    });
    
    // Course card click
    $(document).on('click', '.course-card', function() {
        const courseId = $(this).data('course-id');
        window.location.href = window.baseUrl + 'course/'+courseId;
    });
    
    // Pagination handlers (for courses only now)
    $(document).on('click', '.pagination-btn', function() {
        const page = $(this).data('page');
        const type = $(this).data('type');
        
        if (type === 'courses') {
            loadCourses(page);
            $('html, body').animate({ scrollTop: $('#courses-tab').offset().top - 100 }, 300);
        }
    });
}

// Render pagination controls
function renderPagination(type, currentPage, totalPages) {
    const containerId = type === 'courses' ? '#courses-pagination' : '#reviews-pagination';
    const container = $(containerId);
    container.empty();
    
    if (totalPages <= 1) {
        return;
    }
    
    let html = '<div class="pagination">';
    
    // Previous button
    if (currentPage > 1) {
        html += `<button class="pagination-btn" data-page="${currentPage - 1}" data-type="${type}">
            <span class="material-icons">chevron_left</span>
        </button>`;
    }
    
    // Page numbers
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    
    // First page
    if (startPage > 1) {
        html += `<button class="pagination-btn" data-page="1" data-type="${type}">1</button>`;
        if (startPage > 2) {
            html += '<span class="pagination-dots">...</span>';
        }
    }
    
    // Page numbers
    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === currentPage ? 'active' : '';
        html += `<button class="pagination-btn ${activeClass}" data-page="${i}" data-type="${type}">${i}</button>`;
    }
    
    // Last page
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += '<span class="pagination-dots">...</span>';
        }
        html += `<button class="pagination-btn" data-page="${totalPages}" data-type="${type}">${totalPages}</button>`;
    }
    
    // Next button
    if (currentPage < totalPages) {
        html += `<button class="pagination-btn" data-page="${currentPage + 1}" data-type="${type}">
            <span class="material-icons">chevron_right</span>
        </button>`;
    }
    
    html += '</div>';
    container.html(html);
}

// Utility functions
function formatNumber(num) {
    num = parseInt(num) || 0;
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}

function formatDate(dateString) {
    if (!dateString) return 'Recently';
    
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays < 1) return 'Today';
    if (diffDays < 2) return 'Yesterday';
    if (diffDays < 7) return `${diffDays} days ago`;
    if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
    if (diffDays < 365) return `${Math.floor(diffDays / 30)} months ago`;
    return `${Math.floor(diffDays / 365)} years ago`;
}

function copyToClipboard(text) {
    const temp = $('<input>');
    $('body').append(temp);
    temp.val(text).select();
    document.execCommand('copy');
    temp.remove();
}

function showToast(message, type = 'info') {
    const bgColor = {
        success: '#10b981',
        info: '#6366f1',
        warning: '#f59e0b',
        error: '#ef4444'
    };
    
    const toast = $(`
        <div class="toast" style="
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: ${bgColor[type]};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            animation: slideIn 0.3s ease;
        ">
            ${message}
        </div>
    `);
    
    $('body').append(toast);
    
    setTimeout(() => {
        toast.fadeOut(300, function() {
            $(this).remove();
        });
    }, 3000);
}

// Add animation styles
if (!document.getElementById('toast-animation')) {
    const style = document.createElement('style');
    style.id = 'toast-animation';
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    `;
    document.head.appendChild(style);
}

// ==========================================
// MESSAGING FUNCTIONALITY
// ==========================================

// Load chat history when messages tab is opened
$(document).on('click', '.tab-btn[data-tab="messages"]', function() {
    if ($('#chat-messages').children('.message-wrapper').length === 0) {
        loadChatHistory();
    }
});

// Send message
$('#send-message-btn').on('click', function() {
    sendMessage();
});

// Send on Enter, new line on Shift+Enter
$('#message-input').on('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

// Auto-resize textarea
$('#message-input').on('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Initialize emoji picker
let emojiPickerInitialized = false;

// Emoji picker toggle
$('.emoji-btn').on('click', function(e) {
    e.stopPropagation();
    
    // Initialize emoji picker on first click
    if (!emojiPickerInitialized && typeof window.initEmojiPicker === 'function') {
        window.initEmojiPicker();
        emojiPickerInitialized = true;
    }
    
    const container = $('#emoji-picker-container');
    container.toggle();
});

// Close emoji picker when clicking outside
$(document).on('click', function(e) {
    if (!$(e.target).closest('.emoji-picker-container, .emoji-btn, emoji-picker').length) {
        $('#emoji-picker-container').hide();
    }
});

// Handle emoji selection
window.onEmojiSelected = function(emoji) {
    const textarea = $('#message-input')[0];
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    
    textarea.value = text.substring(0, start) + emoji + text.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
    textarea.focus();
    
    // Auto-resize textarea
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    
    $('#emoji-picker-container').hide();
};

// File attachment
let attachedFiles = [];

$('#attach-btn').on('click', function() {
    $('#file-input').click();
});

$('#file-input').on('change', function(e) {
    const files = Array.from(e.target.files);
    handleFiles(files);
    $(this).val(''); // Reset input
});

// Drag and drop functionality
const messagesContainer = $('.messages-container');
const dragDropOverlay = $('#drag-drop-overlay');

messagesContainer.on('dragenter', function(e) {
    e.preventDefault();
    e.stopPropagation();
    dragDropOverlay.show().addClass('active');
});

dragDropOverlay.on('dragleave', function(e) {
    e.preventDefault();
    e.stopPropagation();
    if (e.target === this) {
        $(this).removeClass('active').hide();
    }
});

dragDropOverlay.on('dragover', function(e) {
    e.preventDefault();
    e.stopPropagation();
});

dragDropOverlay.on('drop', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass('active').hide();
    
    const files = Array.from(e.originalEvent.dataTransfer.files);
    handleFiles(files);
});

// Handle files
function handleFiles(files) {
    const validFiles = files.filter(file => {
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 
                           'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                           'text/plain', 'application/zip', 'application/x-zip-compressed'];
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        if (!validTypes.includes(file.type) && !file.name.match(/\.(jpg|jpeg|png|gif|webp|pdf|doc|docx|txt|zip)$/i)) {
            showToast(`Invalid file type: ${file.name}`, 'error');
            return false;
        }
        
        if (file.size > maxSize) {
            showToast(`File too large: ${file.name} (Max 10MB)`, 'error');
            return false;
        }
        
        return true;
    });
    
    if (validFiles.length > 0) {
        attachedFiles.push(...validFiles);
        renderFilePreview();
    }
}

// Render file preview
function renderFilePreview() {
    const previewArea = $('#file-preview-area');
    const previewList = $('#file-preview-list');
    
    if (attachedFiles.length === 0) {
        previewArea.hide();
        return;
    }
    
    previewArea.show();
    previewList.empty();
    
    attachedFiles.forEach((file, index) => {
        const fileItem = createFilePreviewItem(file, index);
        previewList.append(fileItem);
    });
}

// Create file preview item
function createFilePreviewItem(file, index) {
    const fileSize = formatFileSize(file.size);
    const isImage = file.type.startsWith('image/');
    
    let iconHTML;
    if (isImage) {
        const imageUrl = URL.createObjectURL(file);
        iconHTML = `<div class="file-preview-icon image"><img src="${imageUrl}" alt="${file.name}"></div>`;
    } else {
        let iconName = 'description';
        if (file.type.includes('pdf')) iconName = 'picture_as_pdf';
        else if (file.type.includes('word') || file.name.endsWith('.doc') || file.name.endsWith('.docx')) iconName = 'description';
        else if (file.type.includes('zip')) iconName = 'folder_zip';
        
        iconHTML = `
            <div class="file-preview-icon">
                <span class="material-icons">${iconName}</span>
            </div>
        `;
    }
    
    return $(`
        <div class="file-preview-item" data-index="${index}">
            ${iconHTML}
            <div class="file-preview-info">
                <div class="file-preview-name">${escapeHtml(file.name)}</div>
                <div class="file-preview-size">${fileSize}</div>
            </div>
            <button class="file-preview-remove" data-index="${index}">
                <span class="material-icons">close</span>
            </button>
        </div>
    `);
}

// Remove file
$(document).on('click', '.file-preview-remove', function() {
    const index = $(this).data('index');
    attachedFiles.splice(index, 1);
    renderFilePreview();
});

// Clear all files
$('#clear-all-files').on('click', function() {
    attachedFiles = [];
    renderFilePreview();
});

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Send message function
function sendMessage() {
    const input = $('#message-input');
    const message = input.val().trim();
    
    if (message === '' && attachedFiles.length === 0) return;
    
    // Add sent message with files
    if (message !== '') {
        displayMessage(message, true);
    }
    
    // Send files
    if (attachedFiles.length > 0) {
        displayFileMessage(attachedFiles, true);
        attachedFiles = [];
        renderFilePreview();
    }
    
    // Clear input
    input.val('');
    input.css('height', 'auto');
    
    // Scroll to bottom
    scrollToBottom();
    
    // Simulate instructor reply
    setTimeout(() => {
        showTypingIndicator();
        setTimeout(() => {
            hideTypingIndicator();
            simulateInstructorReply();
        }, 2000);
    }, 1000);
}

// Display file message
function displayFileMessage(files, isSent) {
    const currentTime = new Date();
    const timeString = currentTime.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
    
    const filesHTML = files.map(file => {
        const isImage = file.type.startsWith('image/');
        const fileSize = formatFileSize(file.size);
        
        if (isImage) {
            const imageUrl = URL.createObjectURL(file);
            return `
                <div class="message-file image-file">
                    <img src="${imageUrl}" alt="${escapeHtml(file.name)}" style="max-width: 300px; max-height: 200px; border-radius: 8px;">
                    <div class="file-info">
                        <span class="file-name">${escapeHtml(file.name)}</span>
                        <span class="file-size">${fileSize}</span>
                    </div>
                </div>
            `;
        } else {
            let iconName = 'description';
            if (file.type.includes('pdf')) iconName = 'picture_as_pdf';
            else if (file.type.includes('word') || file.name.endsWith('.doc') || file.name.endsWith('.docx')) iconName = 'description';
            else if (file.type.includes('zip')) iconName = 'folder_zip';
            
            return `
                <div class="message-file document-file">
                    <div class="file-icon">
                        <span class="material-icons">${iconName}</span>
                    </div>
                    <div class="file-info">
                        <span class="file-name">${escapeHtml(file.name)}</span>
                        <span class="file-size">${fileSize}</span>
                    </div>
                    <button class="download-btn">
                        <span class="material-icons">download</span>
                    </button>
                </div>
            `;
        }
    }).join('');
    
    const messageHTML = `
        <div class="message-wrapper ${isSent ? 'sent' : 'received'}">
            ${!isSent ? `
                <div class="message-avatar">
                    <img src="${$('.chat-instructor-info img').attr('src')}" alt="Instructor">
                </div>
            ` : ''}
            <div class="message-bubble">
                <div class="message-files">
                    ${filesHTML}
                </div>
                <div class="message-time">${timeString}</div>
                ${isSent ? `
                    <div class="message-status">
                        <span class="material-icons">done_all</span>
                    </div>
                ` : ''}
            </div>
        </div>
    `;
    
    $('#chat-messages').append(messageHTML);
    scrollToBottom();
}

// Display message
function displayMessage(text, isSent) {
    const currentTime = new Date();
    const timeString = currentTime.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
    
    const messageHTML = `
        <div class="message-wrapper ${isSent ? 'sent' : 'received'}">
            ${!isSent ? `
                <div class="message-avatar">
                    <img src="${$('.chat-instructor-info img').attr('src')}" alt="Instructor">
                </div>
            ` : ''}
            <div class="message-bubble">
                <div class="message-text">${escapeHtml(text)}</div>
                <div class="message-time">${timeString}</div>
                ${isSent ? `
                    <div class="message-status">
                        <span class="material-icons">done_all</span>
                    </div>
                ` : ''}
            </div>
        </div>
    `;
    
    $('#chat-messages').append(messageHTML);
    scrollToBottom();
}

// Show typing indicator
function showTypingIndicator() {
    $('.typing-indicator').show();
    scrollToBottom();
}

// Hide typing indicator
function hideTypingIndicator() {
    $('.typing-indicator').hide();
}

// Simulate instructor reply
function simulateInstructorReply() {
    // This would be replaced with actual messaging system
    const reply = "Thank you for your message. The instructor will respond soon.";
    displayMessage(reply, false);
}

// Load chat history
function loadChatHistory() {
    // Load actual messages from database if needed
    // For now, show empty state or implement messaging system
    const chatMessages = $('#chat-messages');
    if (chatMessages.children('.message-wrapper').length === 0) {
        chatMessages.html('<p style="text-align:center;color:#9ca3af;padding:2rem;">Start a conversation with the instructor.</p>');
    }
}

// Scroll to bottom of chat
function scrollToBottom() {
    const chatMessages = $('#chat-messages')[0];
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
