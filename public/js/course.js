
const courseData = window.courseData || {};
const courseId = window.courseId || null;
const baseUrl = window.baseUrl || '/eduverse/';

function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}

function initializePage() {
    initTabs();
    setupEventListeners();
    initCurriculum();
    initReviews();
    initDescriptionToggle();
    initBioToggle();
}

function setupEventListeners() {
    // Enrollment button
    const enrollBtn = document.getElementById('enrollBtn');
    if (enrollBtn) {
        enrollBtn.addEventListener('click', addToCart);
    }
  

    const wishlistBtn = document.getElementById('wishlistBtn');
    if (wishlistBtn) {
        wishlistBtn.addEventListener('click', toggleWishlist);
    }

    const playBtn = document.getElementById('playPreview');
    if (playBtn) {
        playBtn.addEventListener('click', function() {
            openPreviewModal();
        });
    }

    // Curriculum preview buttons - use event delegation for dynamically shown content
    document.addEventListener('click', function(e) {
        const previewBtn = e.target.closest('.btn-preview');
        if (previewBtn) {
            e.preventDefault();
            e.stopPropagation(); // Prevent section toggle
            const videoUrl = previewBtn.getAttribute('data-video-url');
            const lectureTitle = previewBtn.getAttribute('data-lecture-title');
            openPreviewModal(videoUrl, lectureTitle);
        }
    });

    // Handle all modal close buttons
    const modalCloseButtons = document.querySelectorAll('.modal-close, .modal-close-pro');
    modalCloseButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal && modal.id === 'previewModal') {
                closePreviewModal();
            }
        });
    });

    // Handle close button with specific ID
    const closePreviewBtn = document.getElementById('closePreviewModal');
    if (closePreviewBtn) {
        closePreviewBtn.addEventListener('click', function() {
            closePreviewModal();
        });
    }

    // Handle enroll from modal button
    const enrollFromModalBtn = document.getElementById('enrollFromModal');
    if (enrollFromModalBtn) {
        enrollFromModalBtn.addEventListener('click', function() {
            closePreviewModal();
            addToCart();
        });
    }

    const modal = document.getElementById('previewModal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal || e.target.classList.contains('modal-overlay')) {
                closePreviewModal();
            }
        });
    }
}


function checkCartStatus() {
    $.ajax({
        url: baseUrl + 'ajax/cart.php',
        type: 'POST',
        data: {
            action: 'check_cart',
            course_id: courseData.course_id
        },
        dataType: 'json',
        success: function(response) {
            if (response.in_cart) {
                updateEnrollButtons(true);
            }
        }
    });
}

function addToCart() {
    $.ajax({
        url: baseUrl + 'ajax/cart.php',
        type: 'POST',
        data: {
            action: 'toggle_cart',
            course_id: courseData.course_id
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (response.action === 'added') {
                    showNotification('Course added to cart!', 'success');
                    updateEnrollButtons(true);
                } else {
                    showNotification('Course removed from cart', 'info');
                    updateEnrollButtons(false);
                }
            } else {
                showNotification(response.error || 'Error updating cart', 'error');
            }
        },
        error: function() {
            showNotification('Error updating cart', 'error');
        }
    });
}

function updateEnrollButtons(inCart) {
    const enrollBtns = [
        document.getElementById('enrollBtn'),
    ].filter(btn => btn !== null);
    
    enrollBtns.forEach(btn => {
        if (inCart) {
            btn.innerHTML = '<span class="material-icons">check</span> In Cart';
            btn.classList.add('in-cart');
        } else {
            btn.innerHTML = '<span class="material-icons">shopping_cart</span> Add to Cart';
            btn.classList.remove('in-cart');
        }
    });
}

function toggleWishlist() {
    $.ajax({
        url: baseUrl + 'ajax/wishlist.php',
        type: 'POST',
        data: {
            action: 'toggle_wishlist',
            course_id: courseData.course_id
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (response.action === 'added') {
                    showNotification('Added to wishlist ❤️', 'success');
                    updateWishlistUI(true);
                } else {
                    showNotification('Removed from wishlist', 'info');
                    updateWishlistUI(false);
                }
            } else {
                showNotification(response.error || 'Error updating wishlist', 'error');
            }
        },
        error: function() {
            showNotification('Error updating wishlist', 'error');
        }
    });
}

function updateWishlistUI(inWishlist) {
    const wishlistBtns = [
        document.getElementById('wishlistBtn')
    ].filter(btn => btn !== null);
    
    wishlistBtns.forEach(btn => {
        const icon = btn.querySelector('.material-icons');
        if (icon) {
            icon.textContent = inWishlist ? 'favorite' : 'favorite_border';
            btn.innerHTML = '<span class="material-icons">' + (inWishlist ? 'favorite' : 'favorite_border') + '</span> ' + (inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist');
            
        }
    });
}




function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = 'notification notification-' + type;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#10b981' : '#f65a3bff'};
        color: white;
        border-radius: 8px;
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}





// Initialize reviews (placeholder)
function initReviews() {
    loadRatingBreakdown();
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

function loadReviews(page = 1, append = false) {
    const courseId = courseData.course_id;
    const searchTerm = document.getElementById('searchReviews')?.value || '';
    const sortBy = document.getElementById('sortReviews')?.value || 'helpful';
    const reviewsList = document.getElementById('reviewsList');

    if (!reviewsList) return;

    $.ajax({
        url: baseUrl + 'ajax/reviews.php',
        type: 'GET',
        data: {
            action: 'get_reviews',
            course_id: courseId,
            page: page,
            search: searchTerm,
            sort: sortBy
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderReviews(response.reviews, append);
                updatePagination(response.current_page, response.total_pages);

                if (!append) {
                    reviewsList.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } else {
                showNotification(response.error || 'Failed to load reviews', 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error loading reviews', 'error');
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
            reviewsList.innerHTML = '<p class="placeholder-text">No reviews found matching your search.</p>';
        }
        return;
    }

    reviews.forEach(review => {
        const stars = Array(5).fill(0).map((_, i) => 
            `<span class="material-icons">${i < review.rating ? 'star' : 'star_border'}</span>`
        ).join('');

        const isCurrentUserReview = window.currentUser && window.currentUser.user_id === review.user_id;
        const isInstructor = window.currentUser && window.currentUser.user_id === courseData.instructor_id;
        
        const deleteButton = isCurrentUserReview ? `
            <button class="btn-reaction btn-delete" data-review-id="${review.review_id}">
                <span class="material-icons">delete</span>
                Delete
            </button>
        ` : '';
        
        const editButton = isCurrentUserReview ? `
            <button class="btn-reaction btn-edit" data-review-id="${review.review_id}">
                <span class="material-icons">edit</span>
                Edit
            </button>
        ` : '';
        
        const respondButton = isInstructor && !isCurrentUserReview ? `
            <button class="btn-reaction btn-respond" data-review-id="${review.review_id}">
                <span class="material-icons">reply</span>
                Respond
            </button>
        ` : '';
        
        const instructorResponseHtml = review.instructor_response ? `
            <div class="instructor-response">
                <div class="response-header">
                    <span class="instructor-badge">Instructor Response</span>
                    <span class="response-date">${formatDate(review.instructor_responded_at)}</span>
                </div>
                <p>${escapeHtml(review.instructor_response)}</p>
            </div>
        ` : '';

        const userMarkedHelpful = review.user_marked_helpful ? 'active' : '';
        const userMarkedNotHelpful = review.user_marked_not_helpful ? 'active' : '';
        const reviewHTML = `
            <div class="review-item" data-review-id="${review.review_id}">
                <div class="review-header">
                    <div class="reviewer-avatar">${(review.first_name || 'U').charAt(0).toUpperCase()}${(review.last_name || 'U').charAt(0).toUpperCase()}</div>
                    <div class="reviewer-info">
                        <h4>${escapeHtml(review.first_name + ' ' + review.last_name)}</h4>
                        <div class="review-meta">
                            <div class="stars">${stars}</div>
                            <span class="review-date">${formatDate(review.created_at)}</span>
                            ${isCurrentUserReview ? '<span class="your-review-badge">Your Review</span>' : ''}
                        </div>
                    </div>
                </div>
                <div class="review-content">
                    <p>${escapeHtml(review.review_text)}</p>
                </div>
                ${instructorResponseHtml}
                <div class="review-actions">
                    <button class="btn-reaction btn-helpful ${userMarkedHelpful}" data-review-id="${review.review_id}" data-is-helpful="1">
                        <span class="material-icons">thumb_up</span>
                        <span>Helpful (<span class="helpful-count">${review.helpful_count}</span>)</span>
                    </button>
                    <button class="btn-reaction btn-not-helpful ${userMarkedNotHelpful}" data-review-id="${review.review_id}" data-is-helpful="0">
                        <span class="material-icons">thumb_down</span>
                        <span>Not Helpful (<span class="not-helpful-count">${review.not_helpful_count}</span>)</span>
                    </button>
                    ${respondButton}
                    ${editButton}
                    <button class="btn-reaction btn-report" data-review-id="${review.review_id}">
                        <span class="material-icons">flag</span>
                        Report
                    </button>
                    ${deleteButton}
                </div>
            </div>
        `;


        reviewsList.insertAdjacentHTML('beforeend', reviewHTML);
    });

    // Attach event listeners to new buttons
    attachReviewButtonListeners();
}

function attachReviewButtonListeners() {
    // Helpful buttons (both helpful and not helpful)
    document.querySelectorAll('.btn-helpful, .btn-not-helpful').forEach(btn => {
        btn.addEventListener('click', markReviewHelpful);
    });

    // Edit buttons
    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const reviewId = this.dataset.reviewId;
            openEditReviewModal(reviewId);
        });
    });

    // Respond buttons
    document.querySelectorAll('.btn-respond').forEach(btn => {
        btn.addEventListener('click', function() {
            const reviewId = this.dataset.reviewId;
            openRespondModal(reviewId);
        });
    });

    // Report buttons
    document.querySelectorAll('.btn-report').forEach(btn => {
        btn.addEventListener('click', function() {
            const isLoggedIn = window.currentUser && window.currentUser.user_id;
            if (!isLoggedIn) {
                showNotification('Please login to report reviews', 'error');
                setTimeout(() => window.location.href = baseUrl + 'login', 1500);
            } else {
                showNotification('Review reported. Thank you for helping us keep the community clean!', 'success');
            }
        });
    });

    // Delete buttons
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const reviewId = this.dataset.reviewId;
            if (confirm('Are you sure you want to delete this review? This action cannot be undone.')) {
                deleteReview(reviewId);
            }
        });
    });
}

function markReviewHelpful(e) {
    e.preventDefault();
    const reviewId = this.dataset.reviewId;
    const isHelpful = this.dataset.isHelpful;

    $.ajax({
        url: baseUrl + 'ajax/reviews.php',
        type: 'POST',
        data: {
            action: 'mark_helpful',
            review_id: reviewId,
            is_helpful: isHelpful
        },
        dataType: 'json',
        success: function(response) {
            if (response.need_login) {
                showNotification('Please login to mark reviews', 'error');
                setTimeout(() => window.location.href = baseUrl + 'login', 1500);
            } else if (response.success) {
                // Update both helpful and not helpful buttons
                const reviewElement = document.querySelector(`[data-review-id="${reviewId}"]`);
                if (reviewElement) {
                    const helpfulBtn = reviewElement.querySelector('.btn-helpful');
                    const notHelpfulBtn = reviewElement.querySelector('.btn-not-helpful');
                    const helpfulCount = reviewElement.querySelector('.helpful-count');
                    const notHelpfulCount = reviewElement.querySelector('.not-helpful-count');
                    
                    // Update counts
                    if (helpfulCount) helpfulCount.textContent = response.helpful_count;
                    if (notHelpfulCount) notHelpfulCount.textContent = response.not_helpful_count;
                    
                    // Update button states
                    if (isHelpful == 1) {
                        helpfulBtn.classList.toggle('active');
                        notHelpfulBtn.classList.remove('active');
                        showNotification(helpfulBtn.classList.contains('active') ? 'Marked as helpful!' : 'Removed from helpful', 'success');
                    } else {
                        notHelpfulBtn.classList.toggle('active');
                        helpfulBtn.classList.remove('active');
                        showNotification(notHelpfulBtn.classList.contains('active') ? 'Marked as not helpful!' : 'Removed from not helpful', 'success');
                    }
                }
            } else {
                showNotification(response.error || 'Error marking review', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error marking review:', status, error, xhr);
            showNotification('Error marking review', 'error');
        }
    });
}

function deleteReview(reviewId) {
    $.ajax({
        url: baseUrl + 'ajax/submit_review.php',
        type: 'POST',
        data: {
            action: 'delete_review',
            review_id: reviewId,
            course_id: courseData.course_id
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('Review deleted successfully', 'success');
                const reviewElement = document.querySelector(`[data-review-id="${reviewId}"]`);
                if (reviewElement) {
                    reviewElement.style.opacity = '0';
                    reviewElement.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        reviewElement.remove();
                        // Reload reviews and rating
                        loadReviews(1);
                        loadRatingBreakdown();
                    }, 300);
                }
            } else {
                showNotification(response.error || 'Error deleting review', 'error');
            }
        },
        error: function() {
            showNotification('Error deleting review', 'error');
        }
    });
}

function loadRatingBreakdown() {
    const courseId = courseData.course_id;
    const breakdownContainer = document.getElementById('ratingBreakdown');

    if (!breakdownContainer) return;

    $.ajax({
        url: baseUrl + 'ajax/reviews.php',
        type: 'GET',
        data: {
            action: 'get_rating_breakdown',
            course_id: courseId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderRatingBreakdown(response.percentages);
            }
        },
        error: function() {
        }
    });
}

function renderRatingBreakdown(percentages) {
    const breakdownContainer = document.getElementById('ratingBreakdown');
    let html = '';

    for (let i = 5; i >= 1; i--) {
        const percentage = percentages[i] || 0;
        const stars = Array(5).fill(0).map((_, idx) => 
            `<span class="material-icons">${idx < i ? 'star' : 'star_border'}</span>`
        ).join('');

        html += `
            <div class="rating-bar-item">
                <div class="bar-label">${stars}</div>
                <div class="bar-progress">
                    <div class="bar-fill" style="width: ${percentage}%"></div>
                </div>
                <span class="bar-percentage">${percentage}%</span>
            </div>
        `;
    }

    breakdownContainer.innerHTML = html;
}

function updatePagination(currentPage, totalPages) {
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

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
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

// Initialize description toggle
function initDescriptionToggle() {
    const descriptionBtn = document.getElementById('expandDescription');
    if (!descriptionBtn) return;

    const descriptionDiv = descriptionBtn.parentElement;
    if (!descriptionDiv) return;

    // Check if description needs expanding
    const contentHeight = descriptionDiv.scrollHeight;
    const maxHeight = 150;

    // Set initial state - collapsed if content is longer than max height
    if (contentHeight > maxHeight) {
        descriptionDiv.classList.add('collapsed');
        descriptionBtn.innerHTML = 'Show more <span class="material-icons">expand_more</span>';
    } else {
        // Hide button if content fits
        descriptionBtn.style.display = 'none';
    }

    descriptionBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const isExpanded = descriptionDiv.classList.contains('expanded');

        if (isExpanded) {
            descriptionDiv.classList.remove('expanded');
            descriptionDiv.classList.add('collapsed');
            descriptionBtn.innerHTML = 'Show more <span class="material-icons">expand_more</span>';
        } else {
            descriptionDiv.classList.remove('collapsed');
            descriptionDiv.classList.add('expanded');
            descriptionBtn.innerHTML = 'Show less <span class="material-icons">expand_less</span>';
        }
    });
}

// Initialize bio toggle
function initBioToggle() {
    const expandBioBtn = document.getElementById('expandBio');    
    if (!expandBioBtn) {
        console.warn('expandBio button not found');
        return;
    }

    const instructorBio = expandBioBtn.closest('.instructor-bio');
    
    if (!instructorBio) {
        console.warn('instructor-bio container not found');
        return;
    }

    const bioText = instructorBio.querySelector('p');
    
    if (!bioText) {
        console.warn('bio text paragraph not found');
        return;
    }

    const fullText = bioText.textContent.trim();
    
    const charLimit = 250;
    let isExpanded = false;

    // Check if text needs truncation
    if (fullText.length <= charLimit) {
        expandBioBtn.style.display = 'none';
        return;
    }

    // Store full text and create truncated version
    const truncatedText = fullText.substring(0, charLimit) + '...';
    
    // Set initial truncated state
    bioText.textContent = truncatedText;
    bioText.dataset.fullText = fullText;
    bioText.dataset.truncatedText = truncatedText;


    // Handle button click
    expandBioBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        isExpanded = !isExpanded;

        if (isExpanded) {
            bioText.textContent = fullText;
            expandBioBtn.innerHTML = 'Show less <span class="material-icons">expand_less</span>';
        } else {
            bioText.textContent = truncatedText;
            expandBioBtn.innerHTML = 'Show more <span class="material-icons">expand_more</span>';
        }
    });
    
}


function initTabs() {
    
    const tabButtons = document.querySelectorAll('.tab-btn');
    
    if (tabButtons.length === 0) {
        console.warn('No tab buttons found');
        return;
    }
    
    tabButtons.forEach((btn, index) => {
        
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const tabId = this.getAttribute('data-tab');
            switchTab(tabId);
        });
    });
    if (window.location.hash) {
        switchTab(window.location.hash.substring(1));
    } else {
        switchTab('overview');
    }

}

function switchTab(tabName) {
    
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    
    // Remove active class from all
    tabButtons.forEach(b => {
        b.classList.remove('active');
    });
    
    tabContents.forEach(c => {
        c.classList.remove('active');
    });
    
    // Add active class to selected
    const selectedBtn = document.querySelector(`.tab-btn[data-tab="${tabName}"]`);
    const selectedContent = document.getElementById(tabName);
    
   
    
    if (selectedBtn) {
        selectedBtn.classList.add('active');
    }
    
    if (selectedContent) {
        selectedContent.classList.add('active');
        window.location.hash = tabName;
        
        // Scroll into view on mobile
        if (window.innerWidth < 768 && selectedBtn) {
            selectedBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
}

function openPreviewModal(videoUrl, lectureTitle) {
    const modal = document.getElementById('previewModal');
    if (modal) {
        const video = modal.querySelector('video');
        const loadingOverlay = document.getElementById('videoLoadingOverlay');
        
        // Show loading overlay
        if (loadingOverlay) {
            loadingOverlay.classList.add('active');
        }
        
        // Update video source if provided
        if (videoUrl) {
            const source = video.querySelector('source');
            if (source) {
                source.src = videoUrl;
                video.load();
            }
        }
        
        // Update modal title if provided
        if (lectureTitle) {
            const modalTitle = document.getElementById('previewModalTitle');
            const modalSubtitle = document.getElementById('previewModalSubtitle');
            if (modalTitle) modalTitle.textContent = lectureTitle;
            if (modalSubtitle) modalSubtitle.textContent = 'Preview this lecture';
        } else {
            const modalTitle = document.getElementById('previewModalTitle');
            const modalSubtitle = document.getElementById('previewModalSubtitle');
            if (modalTitle) modalTitle.textContent = 'Course Preview';
            if (modalSubtitle) modalSubtitle.textContent = 'Preview this course before enrolling';
        }
        
        // Hide loading when video can play and auto-play
        if (video) {
            video.addEventListener('canplay', function hideLoading() {
                if (loadingOverlay) {
                    loadingOverlay.classList.remove('active');
                }
                // Auto-play the video
                video.play().catch(function(error) {
                    console.log('Auto-play was prevented:', error);
                });
                video.removeEventListener('canplay', hideLoading);
            });
            
            // Fallback timeout to hide loading and try to play
            setTimeout(() => {
                if (loadingOverlay) {
                    loadingOverlay.classList.remove('active');
                }
                video.play().catch(function(error) {
                    console.log('Auto-play was prevented:', error);
                });
            }, 3000);
        }
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closePreviewModal() {
    const modal = document.getElementById('previewModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        const video = modal.querySelector('video');
        if (video) {
            video.pause();
            video.currentTime = 0;
        }
        
        // Reset loading overlay
        const loadingOverlay = document.getElementById('videoLoadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.classList.remove('active');
        }
    }
}

// ===================================
// Curriculum Sections Toggle
// ===================================
function initCurriculum() {
    const sections = document.querySelectorAll('.curriculum-section');
    
    sections.forEach(section => {
        const header = section.querySelector('.section-header');
        
        header.addEventListener('click', () => {
            const isOpen = section.classList.contains('open');
            
            // Close all sections first
            sections.forEach(s => s.classList.remove('open'));
            
            // Toggle current section
            if (!isOpen) {
                section.classList.add('open');
            }
        });
    });
    
    // Expand/Collapse All
    const expandAllBtn = document.getElementById('expandAll');
    const collapseAllBtn = document.getElementById('collapseAll');
    
    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', () => {
            sections.forEach(section => section.classList.add('open'));
        });
    }
    
    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', () => {
            sections.forEach(section => section.classList.remove('open'));
        });
    }
}

function initReviewsFilter() {
    const searchInput = document.getElementById('searchReviews');
    const filterSelect = document.getElementById('filterRating');
    const reviews = document.querySelectorAll('.review-item');
    
    function filterReviews() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const filterRating = filterSelect ? filterSelect.value : 'all';
        
        reviews.forEach(review => {
            const reviewText = review.querySelector('.review-content').textContent.toLowerCase();
            const reviewerName = review.querySelector('.reviewer-info h4').textContent.toLowerCase();
            const reviewRating = review.querySelectorAll('.stars .material-icons:not(.material-icons-outlined)').length;
            
            const matchesSearch = reviewText.includes(searchTerm) || reviewerName.includes(searchTerm);
            const matchesRating = filterRating === 'all' || reviewRating === parseInt(filterRating);
            
            if (matchesSearch && matchesRating) {
                review.style.display = 'block';
            } else {
                review.style.display = 'none';
            }
        });
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', filterReviews);
    }
    
    if (filterSelect) {
        filterSelect.addEventListener('change', filterReviews);
    }
}


function initReviewReactions() {
    const helpfulBtns = document.querySelectorAll('.btn-reaction[data-action="helpful"]');
    const unhelpfulBtns = document.querySelectorAll('.btn-reaction[data-action="unhelpful"]');
    
    helpfulBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const countSpan = btn.querySelector('span:last-child');
            let count = parseInt(countSpan.textContent);
            
            if (btn.classList.contains('active')) {
                count--;
                btn.classList.remove('active');
            } else {
                count++;
                btn.classList.add('active');
                
                // Remove unhelpful if active
                const unhelpfulBtn = btn.parentElement.querySelector('.btn-reaction[data-action="unhelpful"]');
                if (unhelpfulBtn && unhelpfulBtn.classList.contains('active')) {
                    const unhelpfulCount = unhelpfulBtn.querySelector('span:last-child');
                    unhelpfulCount.textContent = parseInt(unhelpfulCount.textContent) - 1;
                    unhelpfulBtn.classList.remove('active');
                }
            }
            
            countSpan.textContent = count;
        });
    });
    
    unhelpfulBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const countSpan = btn.querySelector('span:last-child');
            let count = parseInt(countSpan.textContent);
            
            if (btn.classList.contains('active')) {
                count--;
                btn.classList.remove('active');
            } else {
                count++;
                btn.classList.add('active');
                
                // Remove helpful if active
                const helpfulBtn = btn.parentElement.querySelector('.btn-reaction[data-action="helpful"]');
                if (helpfulBtn && helpfulBtn.classList.contains('active')) {
                    const helpfulCount = helpfulBtn.querySelector('span:last-child');
                    helpfulCount.textContent = parseInt(helpfulCount.textContent) - 1;
                    helpfulBtn.classList.remove('active');
                }
            }
            
            countSpan.textContent = count;
        });
    });
}

function initStickyCard() {
    const card = document.querySelector('.course-preview-card');
    if (!card) return;
    
    const handleScroll = () => {
        const cardRect = card.getBoundingClientRect();
        const heroSection = document.querySelector('.course-hero');
        
        if (window.innerWidth >= 1024) {
            if (heroSection) {
                const heroBottom = heroSection.getBoundingClientRect().bottom;
                
                if (heroBottom < 120) {
                    card.style.position = 'sticky';
                    card.style.top = '100px';
                } else {
                    card.style.position = 'static';
                }
            }
        } else {
            card.style.position = 'static';
        }
    };
    
    window.addEventListener('scroll', handleScroll);
    window.addEventListener('resize', handleScroll);
}

// ===================================
// Animate Rating Bars
// ===================================
function animateRatingBars() {
    const bars = document.querySelectorAll('.bar-fill');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const bar = entry.target;
                const percentage = bar.dataset.percentage;
                bar.style.width = percentage + '%';
            }
        });
    }, { threshold: 0.5 });
    
    bars.forEach(bar => observer.observe(bar));
}

// Check if course is in cart and wishlist on page load
function checkCartStatus() {
    $.ajax({
        url: baseUrl + 'ajax/cart.php',
        type: 'POST',
        data: {
            action: 'check_cart',
            course_id: courseData.course_id
        },
        dataType: 'json',
        success: function(response) {
            if (response.in_cart) {
                updateEnrollButtons(true);
            }
        }
    });
    
    $.ajax({
        url: baseUrl + 'ajax/wishlist.php',
        type: 'POST',
        data: {
            action: 'check_wishlist',
            course_id: courseData.course_id
        },
        dataType: 'json',
        success: function(response) {
            if (response.in_wishlist) {
                updateWishlistUI(true);
            }
        }
    });
}

// ===================================
// Initialize Page
// ===================================
document.addEventListener('DOMContentLoaded', () => {
    initializePage();
    initReviewsFilter();
    initStickyCard();
    animateRatingBars();
    checkCartStatus();
    initReviewForm();
});

// ===================================
// Review Form Functions
// ===================================
function initReviewForm() {
    const addReviewBtn = document.getElementById('addReviewBtn');
    const reviewForm = document.getElementById('reviewForm');
    const reviewModal = document.getElementById('reviewModal');
    const starRating = document.getElementById('starRating');
    
    if (addReviewBtn) {
        addReviewBtn.addEventListener('click', function() {
            if (!window.currentUser || !window.currentUser.user_id) {
                showNotification('Please login to write a review', 'error');
                setTimeout(() => window.location.href = baseUrl + 'auth/login.php', 1500);
            } else {
                checkUserReview();
            }
        });
    }
    
    // Star rating selection
    if (starRating) {
        const stars = starRating.querySelectorAll('.star-btn');
        stars.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const rating = this.dataset.rating;
                updateStarDisplay(rating);
                document.getElementById('selectedRating').value = rating;
            });
            
            btn.addEventListener('mouseover', function(e) {
                const rating = this.dataset.rating;
                updateStarDisplay(rating);
            });
        });
        
        starRating.addEventListener('mouseleave', function() {
            const selected = document.getElementById('selectedRating').value;
            if (selected) {
                updateStarDisplay(selected);
            } else {
                resetStarDisplay();
            }
        });
    }
    
    // Form submission
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitReview();
        });
    }
    
    // Close modal
    const modalClose = document.querySelector('#reviewModal .modal-close');
    if (modalClose) {
        modalClose.addEventListener('click', closeReviewModal);
    }
    
    const modal = document.getElementById('reviewModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeReviewModal();
        });
    }
}

function checkUserReview() {
    $.ajax({
        url: baseUrl + 'ajax/submit_review.php',
        type: 'POST',
        data: {
            action: 'check_user_review',
            course_id: courseData.course_id
        },
        dataType: 'json',
        success: function(response) {
            if (response.has_review) {
                showNotification('You have already reviewed this course', 'error');
            } else {
                openReviewModal();
            }
        }
    });
}

function openReviewModal() {
    const modal = document.getElementById('reviewModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeReviewModal() {
    const modal = document.getElementById('reviewModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    // Reset form
    document.getElementById('reviewForm').reset();
    document.getElementById('selectedRating').value = '';
    resetStarDisplay();
}

function updateStarDisplay(rating) {
    const stars = document.querySelectorAll('#starRating .star-btn');
    const ratingText = document.getElementById('ratingText');
    
    const ratings = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
    
    stars.forEach((star, index) => {
        if (index < rating) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });
    
    ratingText.textContent = ratings[rating] || '';
}

function resetStarDisplay() {
    const stars = document.querySelectorAll('#starRating .star-btn');
    const ratingText = document.getElementById('ratingText');
    
    stars.forEach(star => star.classList.remove('active'));
    ratingText.textContent = '';
}

function submitReview() {
    const rating = document.getElementById('selectedRating').value;
    const reviewText = document.getElementById('reviewText').value;
    const reviewTitle = document.getElementById('reviewTitle').value;
    
    if (!rating) {
        showNotification('Please select a rating', 'error');
        return;
    }
    
    if (!reviewText.trim()) {
        showNotification('Please write a review', 'error');
        return;
    }
    
    const submitBtn = document.querySelector('#reviewForm button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Submitting...';
    
    $.ajax({
        url: baseUrl + 'ajax/submit_review.php',
        type: 'POST',
        data: {
            action: 'submit_review',
            course_id: courseData.course_id,
            rating: rating,
            review_text: reviewText,
            review_title: reviewTitle
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('Review submitted successfully!', 'success');
                closeReviewModal();
                loadReviews(1);
                loadRatingBreakdown();
            } else {
                showNotification(response.error || 'Error submitting review', 'error');
            }
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="material-icons">send</i> Submit Review';
        },
        error: function() {
            showNotification('Error submitting review', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="material-icons">send</i> Submit Review';
        }
    });
}

// ===================================
// Edit Review Functions
// ===================================
function openEditReviewModal(reviewId) {
    const reviewElement = document.querySelector(`[data-review-id="${reviewId}"]`);
    const reviewContent = reviewElement.querySelector('.review-content p');
    const reviewRating = reviewElement.querySelectorAll('.stars .material-icons:not(.material-icons-outlined)').length;
    const reviewText = reviewContent.textContent;
    
    const modal = document.createElement('div');
    modal.id = 'editReviewModal_' + reviewId;
    modal.className = 'modal edit-review-modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Your Review</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editReviewForm_${reviewId}">
                    <div class="form-group">
                        <label>Rating</label>
                        <div id="editStarRating_${reviewId}" class="star-rating">
                            ${[1, 2, 3, 4, 5].map(i => `
                                <button type="button" class="star-btn" data-rating="${i}" style="${i <= reviewRating ? 'color: #fbbf24' : ''}">
                                    <span class="material-icons">${i <= reviewRating ? 'star' : 'star_border'}</span>
                                </button>
                            `).join('')}
                        </div>
                        <input type="hidden" id="editSelectedRating_${reviewId}" value="${reviewRating}">
                        <p id="editRatingText_${reviewId}" class="rating-text" style="margin-top: 0.5rem; color: #6b7280; font-size: 0.9rem;"></p>
                    </div>
                    <div class="form-group">
                        <label for="editReviewText_${reviewId}">Review</label>
                        <textarea id="editReviewText_${reviewId}" class="form-control" rows="5" placeholder="Share your experience with this course..." required>${reviewText}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Review</button>
                </form>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Setup star rating for edit modal
    setupEditStarRating(reviewId, reviewRating);
    
    // Close button
    modal.querySelector('.modal-close').addEventListener('click', () => {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(() => modal.remove(), 300);
    });
    
    // Click outside to close
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            setTimeout(() => modal.remove(), 300);
        }
    });
    
    // Form submission
    document.getElementById('editReviewForm_' + reviewId).addEventListener('submit', (e) => {
        e.preventDefault();
        submitEditReview(reviewId, modal);
    });
}

function setupEditStarRating(reviewId, initialRating) {
    const starRating = document.getElementById('editStarRating_' + reviewId);
    const selectedInput = document.getElementById('editSelectedRating_' + reviewId);
    const ratingText = document.getElementById('editRatingText_' + reviewId);
    const ratings = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
    
    const stars = starRating.querySelectorAll('.star-btn');
    stars.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const rating = btn.dataset.rating;
            selectedInput.value = rating;
            
            stars.forEach((s, idx) => {
                if (idx < rating) {
                    s.style.color = '#fbbf24';
                    s.querySelector('.material-icons').textContent = 'star';
                } else {
                    s.style.color = '';
                    s.querySelector('.material-icons').textContent = 'star_border';
                }
            });
            
            ratingText.textContent = ratings[rating] || '';
        });
        
        btn.addEventListener('mouseover', (e) => {
            const rating = btn.dataset.rating;
            stars.forEach((s, idx) => {
                if (idx < rating) {
                    s.style.color = '#fbbf24';
                } else {
                    s.style.color = '';
                }
            });
        });
    });
    
    starRating.addEventListener('mouseleave', () => {
        const rating = selectedInput.value;
        stars.forEach((s, idx) => {
            if (idx < rating) {
                s.style.color = '#fbbf24';
                s.querySelector('.material-icons').textContent = 'star';
            } else {
                s.style.color = '';
                s.querySelector('.material-icons').textContent = 'star_border';
            }
        });
    });
    
    ratingText.textContent = ratings[initialRating] || '';
}

function submitEditReview(reviewId, modal) {
    const rating = document.getElementById('editSelectedRating_' + reviewId).value;
    const reviewText = document.getElementById('editReviewText_' + reviewId).value;
    
    if (!rating) {
        showNotification('Please select a rating', 'error');
        return;
    }
    
    if (!reviewText.trim() || reviewText.length < 10) {
        showNotification('Review must be at least 10 characters', 'error');
        return;
    }
    
    $.ajax({
        url: baseUrl + 'ajax/submit_review.php',
        type: 'POST',
        data: {
            action: 'edit_review',
            review_id: reviewId,
            rating: rating,
            review_text: reviewText,
            review_title: ''
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('Review updated successfully!', 'success');
                modal.classList.remove('active');
                document.body.style.overflow = '';
                setTimeout(() => modal.remove(), 300);
                loadReviews(1);
                loadRatingBreakdown();
            } else {
                showNotification(response.error || 'Error updating review', 'error');
            }
        },
        error: function() {
            showNotification('Error updating review', 'error');
        }
    });
}

// ===================================
// Instructor Response Functions
// ===================================
function openRespondModal(reviewId) {
    const modal = document.createElement('div');
    modal.id = 'respondModal_' + reviewId;
    modal.className = 'modal respond-modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Respond to Review</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="respondForm_${reviewId}">
                    <div class="form-group">
                        <label for="respondText_${reviewId}">Your Response</label>
                        <textarea id="respondText_${reviewId}" class="form-control" rows="5" placeholder="Write your response to this review..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Response</button>
                </form>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Close button
    modal.querySelector('.modal-close').addEventListener('click', () => {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(() => modal.remove(), 300);
    });
    
    // Click outside to close
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            setTimeout(() => modal.remove(), 300);
        }
    });
    
    // Form submission
    document.getElementById('respondForm_' + reviewId).addEventListener('submit', (e) => {
        e.preventDefault();
        submitResponse(reviewId, modal);
    });
}

function submitResponse(reviewId, modal) {
    const responseText = document.getElementById('respondText_' + reviewId).value;
    
    if (!responseText.trim()) {
        showNotification('Please write a response', 'error');
        return;
    }
    
    $.ajax({
        url: baseUrl + 'ajax/reviews.php',
        type: 'POST',
        data: {
            action: 'respond_to_review',
            review_id: reviewId,
            response_text: responseText
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('Response added successfully!', 'success');
                modal.classList.remove('active');
                document.body.style.overflow = '';
                setTimeout(() => modal.remove(), 300);
                loadReviews(1);
            } else {
                showNotification(response.error || 'Error adding response', 'error');
            }
        },
        error: function() {
            showNotification('Error adding response', 'error');
        }
    });
}

// Share Course Functionality
function initShareFunctionality() {
    const shareCourseBtn = document.getElementById('shareCourseBtn');
    if (shareCourseBtn) {
        shareCourseBtn.addEventListener('click', showShareModal);
    }

    const giftCourseBtn = document.getElementById('giftCourseBtn');
    if (giftCourseBtn) {
        giftCourseBtn.addEventListener('click', showGiftModal);
    }
}

function showShareModal() {
    const courseUrl = window.location.href;
    const courseTitle = courseData.title || 'Check out this course';
    
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.id = 'shareModal';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>Share This Course</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #1f2937;">Course Link</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="shareCourseUrl" value="${courseUrl}" readonly 
                               style="flex: 1; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 0.875rem;">
                        <button id="copyCourseLink" class="btn btn-primary" style="white-space: nowrap;">
                            <span class="material-icons" style="font-size: 20px;">content_copy</span>
                            Copy
                        </button>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 12px; font-weight: 600; color: #1f2937;">Share via Social Media</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px;">
                        <button class="share-social-btn" data-platform="facebook" style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: white; cursor: pointer; transition: all 0.2s; font-weight: 500;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="#1877F2">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                            Facebook
                        </button>
                        <button class="share-social-btn" data-platform="twitter" style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: white; cursor: pointer; transition: all 0.2s; font-weight: 500;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="#1DA1F2">
                                <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                            </svg>
                            Twitter
                        </button>
                        <button class="share-social-btn" data-platform="linkedin" style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: white; cursor: pointer; transition: all 0.2s; font-weight: 500;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="#0A66C2">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                            LinkedIn
                        </button>
                        <button class="share-social-btn" data-platform="whatsapp" style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: white; cursor: pointer; transition: all 0.2s; font-weight: 500;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="#25D366">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                            WhatsApp
                        </button>
                        <button class="share-social-btn" data-platform="email" style="display: flex; align-items: center; gap: 8px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: white; cursor: pointer; transition: all 0.2s; font-weight: 500;">
                            <span class="material-icons" style="color: #6366f1;">email</span>
                            Email
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    
    // Close modal handler
    modal.querySelector('.modal-close').addEventListener('click', () => {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(() => modal.remove(), 300);
    });
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            setTimeout(() => modal.remove(), 300);
        }
    });
    
    // Copy link handler
    document.getElementById('copyCourseLink').addEventListener('click', () => {
        const input = document.getElementById('shareCourseUrl');
        input.select();
        document.execCommand('copy');
        showNotification('Link copied to clipboard!', 'success');
    });
    
    // Social share handlers
    document.querySelectorAll('.share-social-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const platform = this.dataset.platform;
            shareOnPlatform(platform, courseUrl, courseTitle);
        });
        
        // Hover effects
        btn.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f3f4f6';
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'white';
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
    });
}

function shareOnPlatform(platform, url, title) {
    let shareUrl;
    
    switch(platform) {
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(title)}`;
            break;
        case 'linkedin':
            shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`;
            break;
        case 'whatsapp':
            shareUrl = `https://wa.me/?text=${encodeURIComponent(title + ' ' + url)}`;
            break;
        case 'email':
            shareUrl = `mailto:?subject=${encodeURIComponent(title)}&body=${encodeURIComponent('Check out this course: ' + url)}`;
            break;
    }
    
    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=400');
    }
}

function showGiftModal() {
    showNotification('Gift functionality coming soon!', 'info');
}

// Initialize share functionality when page loads
document.addEventListener('DOMContentLoaded', initShareFunctionality);

