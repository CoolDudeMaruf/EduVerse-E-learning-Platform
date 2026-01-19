// ===================================
// Main JavaScript File
// ===================================

// Set base URL (fallback if not set in page)
if (typeof window.baseUrl === 'undefined') {
    window.baseUrl = '/eduverse/';
}

// Toast notification function
function showNotification(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        z-index: 10000;
        animation: slideIn 0.3s ease;
        font-weight: 500;
    `;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Alias for showToast
window.showToast = showNotification;
window.showNotification = showNotification;

$(document).ready(function() {
    // Navbar scroll effect
    $(window).on('scroll', function() {
        if ($(window).scrollTop() > 50) {
            $('.navbar').addClass('scrolled');
        } else {
            $('.navbar').removeClass('scrolled');
        }
    });

    // Mobile menu toggle
    $('#navToggle').on('click', function() {
        $('#navMenu').toggleClass('active');
        $(this).toggleClass('active');
    });

    // Close mobile menu when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.navbar').length) {
            $('#navMenu').removeClass('active');
            $('#navToggle').removeClass('active');
        }
    });

    // Smooth scroll for anchor links
    $('a[href^="#"]').on('click', function(e) {
        const target = $(this).attr('href');
        if (target !== '#' && $(target).length) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: $(target).offset().top - 80
            }, 600);
            
            // Close mobile menu if open
            $('#navMenu').removeClass('active');
            $('#navToggle').removeClass('active');
        }
    });

    // User menu dropdown toggle
    $('#userMenuToggle').on('click', function(e) {
        e.stopPropagation();
        const dropdown = $('#userDropdown');
        dropdown.toggle();
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.nav-user').length) {
            $('#userDropdown').hide();
        }
    });

    // Prevent dropdown from closing when clicking inside
    $('#userDropdown').on('click', function(e) {
        // Allow logout button to work
        if (!$(e.target).closest('#logoutBtn').length) {
            e.stopPropagation();
        }
    });

    // Perform search function
    function performSearch(query) {
        window.location.href = `courses?search=${encodeURIComponent(query)}`;
    }

});




function updateBadges() {
    updateCartBadge();
    updateWishlistBadge();
}

function updateCartBadge() {
    $.ajax({
        url: window.baseUrl + 'ajax/get_cart.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const count = response.count || 0;
                const badges = document.querySelectorAll('.cart-badge, .cart-count');
                
                badges.forEach(badge => {
                    badge.textContent = count;
                    badge.style.display = count > 0 ? 'flex' : 'none';
                });
            }
        }
    });
}

function updateWishlistBadge() {
    $.ajax({
        url: window.baseUrl + 'ajax/get_wishlist.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const count = response.count || 0;
                const badges = document.querySelectorAll('.wishlist-badge, .wishlist-count');
                
                badges.forEach(badge => {
                    badge.textContent = count;
                    badge.style.display = count > 0 ? 'flex' : 'none';
                });
            }
        }
    });
}

function showCartModal() {
    $.ajax({
        url: window.baseUrl + 'ajax/get_cart.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (!response.success) {
                showToast('Error loading cart', 'error');
                return;
            }

            
            const cart = response.items || [];
            
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop';
            backdrop.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.3s ease;
            `;
            
            const modal = document.createElement('div');
            modal.className = 'cart-modal';
            modal.style.cssText = `
                background: white;
                border-radius: 16px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                display: flex;
                flex-direction: column;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
                animation: slideUp 0.3s ease;
            `;
            
            let total = 0;
            cart.forEach(item => total += item.price || 0);
            
            const itemsHTML = cart.length > 0 ? cart.map((item) => {
                const discount = item.original_price ? Math.round((1 - item.price / item.original_price) * 100) : 0;
                return `
                    <div class="cart-item" style="display: flex; gap: 1rem; padding: 1rem; border-bottom: 1px solid #e5e7eb; align-items: center;">
                        <img src="${item.image}" alt="${item.title}" 
                             style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; background: #f3f4f6;">
                        <div style="flex: 1; min-width: 0;">
                            <h4 style="margin: 0 0 0.25rem 0; font-size: 0.9375rem; font-weight: 600; color: #1f2937; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                ${item.title}
                            </h4>
                            <p style="margin: 0; font-size: 0.8125rem; color: #6b7280;">
                                ${item.category || 'Course'} • ${item.instructor || ''}
                            </p>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                                <span style="font-size: 1.125rem; font-weight: 700; color: #6366f1;">${item.currency}${item.price.toFixed(2)}</span>
                                ${item.original_price ? `
                                    <span style="font-size: 0.875rem; color: #9ca3af; text-decoration: line-through;">${item.currency}${item.original_price.toFixed(2)}</span>
                                    <span style="font-size: 0.75rem; background: #dcfce7; color: #16a34a; padding: 0.125rem 0.5rem; border-radius: 4px; font-weight: 600;">
                                        ${discount}% OFF
                                    </span>
                                ` : ''}
                            </div>
                        </div>
                        <button class="remove-cart-item" data-course-id="${item.course_id}" 
                                style="width: 32px; height: 32px; border: none; background: #fee2e2; color: #ef4444; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;"
                                onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                            <span class="material-icons" style="font-size: 18px;">close</span>
                        </button>
                    </div>
                `;
            }).join('') : `
                <div style="padding: 3rem; text-align: center;">
                    <span class="material-icons" style="font-size: 80px; color: #d1d5db; margin-bottom: 1rem;">shopping_cart</span>
                    <h3 style="margin: 0 0 0.5rem 0; color: #6b7280;">Your cart is empty</h3>
                    <p style="margin: 0; color: #9ca3af; font-size: 0.875rem;">Add courses to get started!</p>
                </div>
            `;
            
            modal.innerHTML = `
                <div style="padding: 1.5rem; border-bottom: 2px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="margin: 0; font-size: 1.5rem; color: #1f2937;">
                        <span class="material-icons" style="vertical-align: middle; margin-right: 0.5rem;">shopping_cart</span>
                        Shopping Cart (${cart.length})
                    </h2>
                    <button class="close-modal" style="background: none; border: none; cursor: pointer; color: #9ca3af; font-size: 1.5rem;">
                        <span class="material-icons">close</span>
                    </button>
                </div>
                
                <div style="flex: 1; overflow-y: auto;">
                    ${itemsHTML}
                </div>
                
                ${cart.length > 0 ? `
                    <div style="padding: 1.5rem; border-top: 2px solid #f3f4f6; background: #f9fafb;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span style="font-size: 1.125rem; font-weight: 600; color: #6b7280;">Total:</span>
                            <span style="font-size: 1.5rem; font-weight: 700; color: #6366f1;">${cart[0]?.currency || '৳'}${total.toFixed(2)}</span>
                        </div>
                        <button class="btn-checkout" 
                                style="width: 100%; padding: 1rem; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.5rem;"
                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 16px rgba(99, 102, 241, 0.3)'"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                            <span class="material-icons">payment</span>
                            Proceed to Checkout
                        </button>
                    </div>
                ` : ''}
            `;
            
            backdrop.appendChild(modal);
            document.body.appendChild(backdrop);
            
            const closeModal = () => {
                backdrop.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => backdrop.remove(), 300);
            };
            
            backdrop.querySelector('.close-modal').addEventListener('click', closeModal);
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) closeModal();
            });
            
            backdrop.querySelectorAll('.remove-cart-item').forEach(btn => {
                btn.addEventListener('click', () => {
                    const courseId = btn.dataset.courseId;
                    $.ajax({
                        url: window.baseUrl + 'ajax/cart.php',
                        type: 'POST',
                        data: {
                            action: 'toggle_cart',
                            course_id: courseId
                        },
                        dataType: 'json',
                        success: function(res) {
                            if (res.success) {
                                updateCartBadge();
                                closeModal();
                                showToast('Item removed from cart', 'info');
                                setTimeout(() => showCartModal(), 300);
                            } else {
                                showToast('Error removing item', 'error');
                            }
                        }
                    });
                });
            });
            
            const checkoutBtn = backdrop.querySelector('.btn-checkout');
            if (checkoutBtn) {
                checkoutBtn.addEventListener('click', () => {
                    closeModal();
                    showToast('Redirecting to checkout...', 'info');
                    setTimeout(() => {
                        window.location.href = window.baseUrl + 'checkout';
                    }, 500);
                });
            }
        },
        error: function() {
            showToast('Error loading cart', 'error');
        }
    });
}

function showWishlistModal() {
    $.ajax({
        url: window.baseUrl + 'ajax/get_wishlist.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (!response.success) {
                showToast('Error loading wishlist', 'error');
                return;
            }
            
            const wishlist = response.items || [];
            
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop';
            backdrop.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.3s ease;
            `;
            
            const modal = document.createElement('div');
            modal.className = 'wishlist-modal';
            modal.style.cssText = `
                background: white;
                border-radius: 16px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                display: flex;
                flex-direction: column;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
                animation: slideUp 0.3s ease;
            `;
            
            const itemsHTML = wishlist.length > 0 ? wishlist.map((course) => {
                const discount = course.original_price ? Math.round((1 - course.price / course.original_price) * 100) : 0;
                
                return `
                    <div class="wishlist-item" style="display: flex; gap: 1rem; padding: 1rem; border-bottom: 1px solid #e5e7eb; align-items: center;">
                        <img src="${course.image}" alt="${course.title}" 
                             style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; background: #f3f4f6;">
                        <div style="flex: 1; min-width: 0;">
                            <h4 style="margin: 0 0 0.25rem 0; font-size: 0.9375rem; font-weight: 600; color: #1f2937; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                ${course.title}
                            </h4>
                            <p style="margin: 0 0 0.5rem 0; font-size: 0.8125rem; color: #6b7280;">
                                ${course.category} • ${course.instructor}
                            </p>
                            <div style="display: flex; align-items: center; gap: 0.25rem; margin-bottom: 0.5rem;">
                                <span style="color: #fbbf24; font-size: 1rem;">★</span>
                                <span style="font-size: 0.875rem; font-weight: 600; color: #1f2937;">${course.rating}</span>
                                <span style="font-size: 0.75rem; color: #9ca3af;">(${course.students || 0})</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-size: 1rem; font-weight: 700; color: #6366f1;">${course.currency}${course.price.toFixed(2)}</span>
                                ${course.original_price ? `
                                    <span style="font-size: 0.875rem; color: #9ca3af; text-decoration: line-through;">${course.currency}${course.original_price.toFixed(2)}</span>
                                    <span style="font-size: 0.75rem; background: #dcfce7; color: #16a34a; padding: 0.125rem 0.5rem; border-radius: 4px; font-weight: 600;">
                                        ${discount}% OFF
                                    </span>
                                ` : ''}
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <button class="add-to-cart-from-wishlist" data-course-id="${course.course_id}" 
                                    style="padding: 0.5rem 1rem; background: #6366f1; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 0.875rem; font-weight: 600; transition: background 0.2s; white-space: nowrap;"
                                    onmouseover="this.style.background='#4f46e5'" onmouseout="this.style.background='#6366f1'">
                                Add to Cart
                            </button>
                            <button class="remove-wishlist-item" data-course-id="${course.course_id}" 
                                    style="padding: 0.5rem 1rem; background: #fee2e2; color: #ef4444; border: none; border-radius: 8px; cursor: pointer; font-size: 0.875rem; font-weight: 600; transition: background 0.2s;"
                                    onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
                                Remove
                            </button>
                        </div>
                    </div>
                `;
            }).join('') : `
                <div style="padding: 3rem; text-align: center;">
                    <span class="material-icons" style="font-size: 80px; color: #d1d5db; margin-bottom: 1rem;">favorite_border</span>
                    <h3 style="margin: 0 0 0.5rem 0; color: #6b7280;">Your wishlist is empty</h3>
                    <p style="margin: 0; color: #9ca3af; font-size: 0.875rem;">Save courses you're interested in!</p>
                </div>
            `;
            
            modal.innerHTML = `
                <div style="padding: 1.5rem; border-bottom: 2px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="margin: 0; font-size: 1.5rem; color: #1f2937;">
                        <span class="material-icons" style="vertical-align: middle; margin-right: 0.5rem; color: #ef4444;">favorite</span>
                        My Wishlist (${wishlist.length})
                    </h2>
                    <button class="close-modal" style="background: none; border: none; cursor: pointer; color: #9ca3af; font-size: 1.5rem;">
                        <span class="material-icons">close</span>
                    </button>
                </div>
                
                <div style="flex: 1; overflow-y: auto;">
                    ${itemsHTML}
                </div>
            `;
            
            backdrop.appendChild(modal);
            document.body.appendChild(backdrop);
            
            const closeModal = () => {
                backdrop.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => backdrop.remove(), 300);
            };
            
            backdrop.querySelector('.close-modal').addEventListener('click', closeModal);
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) closeModal();
            });
            
            backdrop.querySelectorAll('.remove-wishlist-item').forEach(btn => {
                btn.addEventListener('click', () => {
                    const courseId = btn.dataset.courseId;
                    $.ajax({
                        url: window.baseUrl + 'ajax/wishlist.php',
                        type: 'POST',
                        data: {
                            action: 'toggle_wishlist',
                            course_id: courseId
                        },
                        dataType: 'json',
                        success: function(res) {
                            if (res.success) {
                                updateWishlistBadge();
                                closeModal();
                                showToast('Removed from wishlist', 'info');
                                setTimeout(() => showWishlistModal(), 300);
                            } else {
                                showToast('Error removing item', 'error');
                            }
                        }
                    });
                });
            });
            
            backdrop.querySelectorAll('.add-to-cart-from-wishlist').forEach(btn => {
                btn.addEventListener('click', () => {
                    const courseId = btn.dataset.courseId;
                    
                    $.ajax({
                        url: window.baseUrl + 'ajax/cart.php',
                        type: 'POST',
                        data: {
                            action: 'toggle_cart',
                            course_id: courseId
                        },
                        dataType: 'json',
                        success: function(res) {
                            if (res.success && res.action === 'added') {
                                updateCartBadge();
                                showToast('Added to cart! 🛒', 'success');
                                btn.textContent = 'In Cart ✓';
                                btn.disabled = true;
                                btn.style.background = '#10b981';
                            } else if (res.action === 'removed') {
                                showToast('Already in cart', 'info');
                            } else {
                                showToast('Error adding to cart', 'error');
                            }
                        }
                    });
                });
            });
        },
        error: function() {
            showToast('Error loading wishlist', 'error');
        }
    });
}




$(document).on('click', '.cart-icon, .btn-cart, [data-action="cart"]', function(e) {
    e.preventDefault();
    showCartModal();
});

$(document).on('click', '.wishlist-icon, .btn-wishlist-view, [data-action="wishlist-view"]', function(e) {
    e.preventDefault();
    showWishlistModal();
});

// Initialize badges when page loads
updateBadges();

// Listen for storage changes from other tabs
window.addEventListener('storage', function(e) {
    if (e.key === 'cart' || e.key === 'wishlist') {
        updateBadges();
    }
});

window.showCartModal = showCartModal;
window.showWishlistModal = showWishlistModal;
window.updateCartBadge = updateCartBadge;
window.updateWishlistBadge = updateWishlistBadge;
