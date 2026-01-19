// ===================================
// Home Page JavaScript
// ===================================

$(document).ready(function() {
    // Courses loaded from server via PHP
    
    // Function to create course card HTML
    function createCourseCard(course) {
        const priceClass = course.price === "Free" ? "free" : "";
        const levelBadge = course.level;
        
        // Generate gradient for thumbnail based on category
        const gradients = {
            'Web Development': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'Data Science': 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            'Design': 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
            'Programming': 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)'
        };
        
        const gradient = gradients[course.category] || gradients['Web Development'];
        
        return `
            <div class="course-card" data-course-id="${course.id}">
                <div class="course-thumbnail" style="background: ${gradient};">
                    ${course.image ? `<img src="${course.image}" alt="${course.title}">` : ''}
                    <div class="play-icon">
                        <span class="material-icons">play_arrow</span>
                    </div>
                    <span class="course-badge">${levelBadge}</span>
                </div>
                <div class="course-info">
                    <div class="course-meta">
                        <span>
                            <span class="material-icons" style="font-size: 1rem;">schedule</span>
                            ${course.duration}
                        </span>
                        <span>
                            <span class="material-icons" style="font-size: 1rem;">people</span>
                            ${course.students} students
                        </span>
                    </div>
                    <h3 class="course-title">${truncateText(course.title, 60)}</h3>
                    <div class="course-instructor">
                        <span class="material-icons" style="font-size: 1rem;">person</span>
                        ${course.instructor}
                    </div>
                    <div class="course-footer">
                        <div class="course-rating">
                            <span class="material-icons">star</span>
                            <span>${course.rating}</span>
                            <span style="color: var(--gray); font-weight: normal;">(${course.students.replace(',', '')})</span>
                        </div>
                        <div class="course-price ${priceClass}">${course.price}</div>
                    </div>
                </div>
            </div>
        `;
    }

    // Render featured courses
    function renderFeaturedCourses() {
        // Courses are loaded from server via PHP in index.php
        // This function is kept for future JavaScript-based filtering if needed
        
        // Add click event to course cards
        $('.course-card').on('click', function() {
            const courseId = $(this).data('course-id');
            window.location.href = `course.html?id=${courseId}`;
        });
    }

    // Animate stats counter
    function animateCounter(element, target) {
        const duration = 2000;
        const start = 0;
        const increment = target / (duration / 16);
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                element.text(formatNumber(Math.floor(target)));
                clearInterval(timer);
            } else {
                element.text(formatNumber(Math.floor(current)));
            }
        }, 16);
    }

    // Initialize counters when they come into view
    function initCounters() {
        const counters = $('.stat-item h3');
        let animated = false;
        
        $(window).on('scroll', function() {
            if (!animated) {
                const heroStats = $('.hero-stats');
                if (heroStats.length) {
                    const scrollPos = $(window).scrollTop() + $(window).height();
                    const statsPos = heroStats.offset().top;
                    
                    if (scrollPos > statsPos) {
                        animated = true;
                        counters.each(function() {
                            const text = $(this).text();
                            const number = parseInt(text.replace(/,/g, '').replace('+', ''));
                            $(this).text('0');
                            animateCounter($(this), number);
                        });
                    }
                }
            }
        });
    }

    // Add parallax effect to hero shapes
    function initParallax() {
        $(window).on('scroll', function() {
            const scrolled = $(window).scrollTop();
            $('.hero-shape.shape-1').css('transform', `translateY(${scrolled * 0.3}px)`);
            $('.hero-shape.shape-2').css('transform', `translateY(${scrolled * -0.2}px)`);
            $('.hero-shape.shape-3').css('transform', `translateY(${scrolled * 0.15}px)`);
        });
    }

    // Category card interactions
    function initCategoryCards() {
        $('.category-card').on('click', function() {
            const category = $(this).find('h3').text();
            window.location.href = `courses.html?category=${encodeURIComponent(category)}`;
        });
    }

    // Add hover effect to feature cards
    function initFeatureCards() {
        $('.feature-card').each(function(index) {
            $(this).css({
                'animation': `slideUp 0.6s ease-out ${index * 0.1}s backwards`
            });
        });
    }

    // Testimonial carousel (simple version)
    let currentTestimonial = 0;
    const testimonials = $('.testimonial-card');
    
    function rotateTestimonials() {
        if (testimonials.length > 3) {
            testimonials.eq(currentTestimonial).fadeOut(400, function() {
                $(this).css('display', 'none');
            });
            
            currentTestimonial = (currentTestimonial + 1) % testimonials.length;
            
            const nextIndex = (currentTestimonial + 2) % testimonials.length;
            testimonials.eq(nextIndex).fadeIn(400);
        }
    }

    // Auto-rotate testimonials every 5 seconds
    // setInterval(rotateTestimonials, 5000);

    // Add click-to-copy for email
    function initEmailCopy() {
        $('.footer-section a[href^="mailto:"]').on('click', function(e) {
            e.preventDefault();
            const email = $(this).attr('href').replace('mailto:', '');
            
            // Copy to clipboard
            navigator.clipboard.writeText(email).then(() => {
                showToast('Email copied to clipboard!', 'success');
            }).catch(() => {
                showToast('Failed to copy email', 'error');
            });
        });
    }

    // Social share functionality
    function initSocialShare() {
        $('.social-link').on('click', function(e) {
            e.preventDefault();
            const platform = $(this).find('.material-icons').text();
            const url = window.location.href;
            const title = 'EduVerse - Transform Your Future Through Learning';
            
            let shareUrl = '';
            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
                    break;
                case 'linkedin':
                    shareUrl = `https://www.linkedin.com/shareArticle?mini=true&url=${url}&title=${title}`;
                    break;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        });
    }

    // Newsletter subscription (placeholder)
    function initNewsletterForm() {
        const newsletterForm = $('#newsletterForm');
        if (newsletterForm.length) {
            newsletterForm.on('submit', function(e) {
                e.preventDefault();
                const email = $(this).find('input[type="email"]').val();
                
                if (email) {
                    setLoadingState($(this).find('button'), true);
                    
                    // Simulate API call
                    setTimeout(() => {
                        setLoadingState($(this).find('button'), false);
                        showToast('Successfully subscribed to newsletter!', 'success');
                        $(this).find('input[type="email"]').val('');
                    }, 1500);
                }
            });
        }
    }

    // Add keyboard navigation
    function initKeyboardNav() {
        let focusedCourseIndex = -1;
        const courseCards = $('.course-card');
        
        $(document).on('keydown', function(e) {
            if (courseCards.is(':focus') || focusedCourseIndex >= 0) {
                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    focusedCourseIndex = Math.min(focusedCourseIndex + 1, courseCards.length - 1);
                    courseCards.eq(focusedCourseIndex).focus();
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    focusedCourseIndex = Math.max(focusedCourseIndex - 1, 0);
                    courseCards.eq(focusedCourseIndex).focus();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    courseCards.eq(focusedCourseIndex).click();
                }
            }
        });
        
        courseCards.on('focus', function() {
            focusedCourseIndex = courseCards.index(this);
        });
    }

    // Initialize all features
    function init() {
        renderFeaturedCourses();
        initCounters();
        initParallax();
        initCategoryCards();
        initFeatureCards();
        initEmailCopy();
        initSocialShare();
        initNewsletterForm();
        initKeyboardNav();
    }

    // Run initialization
    init();

    // Add scroll reveal animation
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
            }
        });
    }, observerOptions);

    // Observe sections
    $('.section').each(function() {
        observer.observe(this);
    });
});

// Helper function for truncating text (if not in main.js)
if (typeof window.truncateText === 'undefined') {
    window.truncateText = function(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substr(0, maxLength) + '...';
    };
}

// Helper function for formatting numbers (if not in main.js)
if (typeof window.formatNumber === 'undefined') {
    window.formatNumber = function(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    };
}
