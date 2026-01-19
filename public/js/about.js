// ==========================================
// ABOUT PAGE JAVASCRIPT
// ==========================================

$(document).ready(function() {
    // Initialize animations on scroll
    initScrollAnimations();
    
    // Animate statistics counters
    animateCounters();
    
    // Setup team member hover effects
    setupTeamInteractions();
});

// Scroll animations for sections
function initScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe sections
    const sections = document.querySelectorAll('.mission-card, .vision-card, .value-card, .team-member, .feature-item');
    sections.forEach(section => {
        observer.observe(section);
    });

    // Add CSS for fade-in animation
    if (!document.getElementById('scroll-animations-style')) {
        const style = document.createElement('style');
        style.id = 'scroll-animations-style';
        style.textContent = `
            .mission-card, .vision-card, .value-card, .team-member, .feature-item {
                opacity: 0;
                transform: translateY(30px);
                transition: opacity 0.6s ease, transform 0.6s ease;
            }
            .fade-in {
                opacity: 1 !important;
                transform: translateY(0) !important;
            }
        `;
        document.head.appendChild(style);
    }
}

// Animate number counters
function animateCounters() {
    const counters = document.querySelectorAll('.stat-number');
    let hasAnimated = false;

    const observerOptions = {
        threshold: 0.5
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting && !hasAnimated) {
                hasAnimated = true;
                counters.forEach(counter => {
                    animateCounter(counter);
                });
            }
        });
    }, observerOptions);

    if (counters.length > 0) {
        observer.observe(counters[0].closest('.statistics'));
    }
}

function animateCounter(element) {
    const text = element.textContent;
    const target = parseFloat(text.replace(/[^0-9.]/g, ''));
    const suffix = text.replace(/[0-9.,]/g, '');
    const duration = 2000;
    const steps = 60;
    const increment = target / steps;
    let current = 0;
    const stepDuration = duration / steps;

    element.textContent = '0' + suffix;

    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        
        let displayValue = current.toFixed(1);
        if (suffix.includes('M')) {
            displayValue = (current).toFixed(1);
        } else if (suffix.includes('K')) {
            displayValue = (current).toFixed(0);
        } else if (suffix.includes('+')) {
            displayValue = Math.floor(current).toString();
        }
        
        element.textContent = displayValue + suffix;
    }, stepDuration);
}

// Team member interactions
function setupTeamInteractions() {
    $('.team-member').each(function() {
        const $member = $(this);
        
        $member.on('mouseenter', function() {
            $(this).find('.member-image').css('transform', 'scale(1.1)');
        });
        
        $member.on('mouseleave', function() {
            $(this).find('.member-image').css('transform', 'scale(1)');
        });
    });

    // Add smooth transition to member images
    $('.member-image').css('transition', 'transform 0.3s ease');
}

// Smooth scroll for internal links
$('a[href^="#"]').on('click', function(e) {
    e.preventDefault();
    const target = $(this.getAttribute('href'));
    if (target.length) {
        $('html, body').animate({
            scrollTop: target.offset().top - 80
        }, 600);
    }
});

// Parallax effect for hero section
$(window).on('scroll', function() {
    const scrolled = $(this).scrollTop();
    $('.about-hero').css('transform', 'translateY(' + (scrolled * 0.5) + 'px)');
});

// Add stagger animation to value cards
function staggerAnimation() {
    $('.value-card').each(function(index) {
        $(this).css({
            'animation-delay': (index * 0.1) + 's'
        });
    });
}

// Initialize on page load
staggerAnimation();

// Social share functionality (if needed)
function shareOnSocial(platform) {
    const url = window.location.href;
    const title = 'EduVerse - Empowering Learners Worldwide';
    
    let shareUrl = '';
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
    }
    
    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=400');
    }
}

// Add click handlers for social links if needed
$('.social-links a').on('click', function(e) {
    e.preventDefault();
});

function subscribeNewsletter(email) {
    showToast('Thank you for subscribing!', 'success');
}

// Toast notification helper
function showToast(message, type = 'info') {
    const toast = $(`
        <div class="toast toast-${type}" style="
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: ${type === 'success' ? '#10b981' : '#6366f1'};
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

// Add slide-in animation for toast
if (!document.getElementById('toast-animation-style')) {
    const style = document.createElement('style');
    style.id = 'toast-animation-style';
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
