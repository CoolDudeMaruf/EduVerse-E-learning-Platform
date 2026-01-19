// ==========================================
// INSTRUCTORS PAGE JAVASCRIPT - DYNAMIC VERSION
// ==========================================

$(document).ready(function() {

    let currentFilters = {
        years_of_experience: [],
        expertise_areas: [],
        rating: [],
        verified: null,
        featured: null,
        courses: 'all',
        search: '',
        sortby: 'popular',
        page: 1
    };

    // Get base URL from window variable
    const baseUrl = window.baseUrl || '/eduverse/';

    // Load filters from URL on page load
    loadFiltersFromURL();
    applyFilters();
  
    function applyFilters() {
        $('#loadingSpinner').show();
        $('#instructors-grid').hide();
        
        $.ajax({
            url: baseUrl + 'ajax/instructors.php',
            type: 'GET',
            dataType: 'json',
            data: {
                years_of_experience: JSON.stringify(currentFilters.years_of_experience),
                expertise_areas: JSON.stringify(currentFilters.expertise_areas),
                rating: JSON.stringify(currentFilters.rating),
                verified: currentFilters.verified,
                featured: currentFilters.featured,
                courses: currentFilters.courses,
                search: currentFilters.search,
                sortby: currentFilters.sortby,
                page: currentFilters.page
            },
            success: function(response) {
                $('#loadingSpinner').hide();
                $('#instructors-grid').show();

               
                
                if (response.success) {
                    renderInstructors(response.instructors);
                    updateyears_of_experienceFilters(response.years_of_experienceMap);
                    updateexpertise_areasFilters(response.expertise_areasMap);
                    renderPagination(response.totalPages, response.currentPage);
                    updateActiveFilters();
                    $('#results-count').text(response.totalInstructors);
                    // // Update URL after successful filter application
                    updateURL();
                } else {
                    $('#instructors-grid').html('<p class="text-danger">Error loading instructors</p>');
                }
            },
            error: function(xhr, status, error) {
                $('#loadingSpinner').hide();
                $('#instructors-grid').show();
                $('#instructors-grid').html('<p class="text-danger">Failed to load instructors. Please try again.</p>');
            }
        });
    }

    function loadFiltersFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Load years_of_experience
        const years_of_experienceParam = urlParams.get('years_of_experience');
        if (years_of_experienceParam) {
            const years_of_experienceList = years_of_experienceParam.split(',').map(e => decodeURIComponent(e.trim()));
            currentFilters.years_of_experience = years_of_experienceList;
            years_of_experienceList.forEach(exp => {
                $(`input[name="years_of_experience"][value="${exp}"]`).prop('checked', true);
            });
        }
        
        // Load expertise_areas
        const expertise_areasParam = urlParams.get('expertise_areas');
        if (expertise_areasParam) {
            const expertise_areasList = expertise_areasParam.split(',').map(e => decodeURIComponent(e.trim()));
            currentFilters.expertise_areas = expertise_areasList;
            expertise_areasList.forEach(area => {
                $(`input[name="expertise_areas"][value="${area}"]`).prop('checked', true);
            });
        }
        
        // Load ratings
        const ratingParam = urlParams.get('rating');
        if (ratingParam) {
            const ratings = ratingParam.split(',').map(r => parseFloat(r.trim())).filter(r => !isNaN(r));
            currentFilters.rating = ratings;
            ratings.forEach(rating => {
                $(`input[name="rating"][value="${rating}"]`).prop('checked', true);
            });
        }
        
        // Load verified
        const verifiedParam = urlParams.get('verified');
        if (verifiedParam === 'true') {
            currentFilters.verified = true;
            $('input[name="verified"]').prop('checked', true);
        }
        
        // Load featured
        const featuredParam = urlParams.get('featured');
        if (featuredParam === 'true') {
            currentFilters.featured = true;
            $('input[name="featured"]').prop('checked', true);
        }
        
        // Load courses range
        const coursesParam = urlParams.get('courses');
        if (coursesParam && ['all', '10+', '5-10', '1-5'].includes(coursesParam)) {
            currentFilters.courses = coursesParam;
            $(`input[name="courses"][value="${coursesParam}"]`).prop('checked', true);
        }
        
        // Load search
        const searchParam = urlParams.get('search');
        if (searchParam) {
            currentFilters.search = decodeURIComponent(searchParam);
            $('#instructor-search').val(currentFilters.search);
        }
        
        // Load sort
        const sortParam = urlParams.get('sort');
        if (sortParam) {
            currentFilters.sortby = sortParam;
            $('#sort-select').val(sortParam);
        }
        
        // Load page
        const pageParam = urlParams.get('page');
        if (pageParam) {
            const pageNum = parseInt(pageParam);
            if (!isNaN(pageNum) && pageNum > 0) {
                currentFilters.page = pageNum;
            }
        }
    }

    // Update URL with current filters
    function updateURL() {
        const params = new URLSearchParams();
        
        // Add years_of_experience to URL
        if (currentFilters.years_of_experience.length > 0) {
            params.set('years_of_experience', currentFilters.years_of_experience.map(e => encodeURIComponent(e)).join(','));
        }
        
        // Add expertise_areas to URL
        if (currentFilters.expertise_areas.length > 0) {
            params.set('expertise_areas', currentFilters.expertise_areas.map(e => encodeURIComponent(e)).join(','));
        }
        
        // Add ratings to URL
        if (currentFilters.rating.length > 0) {
            params.set('rating', currentFilters.rating.join(','));
        }
        
        // Add verified to URL
        if (currentFilters.verified === true) {
            params.set('verified', 'true');
        }
        
        // Add featured to URL
        if (currentFilters.featured === true) {
            params.set('featured', 'true');
        }
        
        // Add courses to URL
        if (currentFilters.courses !== 'all') {
            params.set('courses', currentFilters.courses);
        }
        
        // Add search to URL
        if (currentFilters.search.trim() !== '') {
            params.set('search', encodeURIComponent(currentFilters.search));
        }
        
        // Add sort to URL if not default
        if (currentFilters.sortby !== 'popular') {
            params.set('sort', currentFilters.sortby);
        }
        
        // Add page to URL if not first page
        if (currentFilters.page > 1) {
            params.set('page', currentFilters.page);
        }
        
        // Update browser URL without page reload
        const newUrl = params.toString() ? `${window.location.pathname}?${params.toString()}` : window.location.pathname;
        window.history.replaceState({}, '', newUrl);
    }

    function renderInstructors(instructors) {
        const container = $('#instructors-grid');
        container.empty();

        if (!instructors || instructors.length === 0) {
            container.html(`
                <div class="no-results" style="grid-column: 1/-1; text-align: center; padding: 3rem;">
                    <span class="material-icons" style="font-size: 64px; color: #9ca3af; margin-bottom: 1rem;">search_off</span>
                    <h3 style="color: #6b7280;">No instructors found</h3>
                    <p style="color: #9ca3af;">Try adjusting your filters or search terms</p>
                </div>
            `);
            return;
        }

        instructors.forEach(instructor => {
            const card = createInstructorCard(instructor);
            container.append(card);
        });
    }

    function createInstructorCard(instructor) {
        const rating = parseFloat(instructor.rating) || 0;
        const fullStars = Math.floor(rating);
        const hasHalfStar = rating % 1 >= 0.5;
        
        let starsHTML = '';
        for (let i = 0; i < fullStars; i++) {
            starsHTML += '<span class="material-icons">star</span>';
        }
        if (hasHalfStar) {
            starsHTML += '<span class="material-icons">star_half</span>';
        }
        const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
        for (let i = 0; i < emptyStars; i++) {
            starsHTML += '<span class="material-icons">star_border</span>';
        }

        const badges = [];
        if (instructor.verified) {
            badges.push('<span class="badge verified"><span class="material-icons">verified</span>Verified</span>');
        }
        if (instructor.featured) {
            badges.push('<span class="badge featured"><span class="material-icons">star</span>Featured</span>');
        }

        const topCoursesHTML = instructor.topCourses && instructor.topCourses.length > 0
            ? instructor.topCourses.map(course => `<li>${course}</li>`).join('')
            : '<li>No courses yet</li>';

        const expertise_areas = instructor.expertise_areas && instructor.expertise_areas.length > 0
            ? instructor.expertise_areas[0]
            : instructor.expertise_areas || 'General';

        return `
            <div class="instructor-card" data-id="${instructor.id}">
                <div class="instructor-avatar">
                    <img src="${instructor.avatar}" alt="${instructor.name}">
                    <div class="instructor-badges">
                        ${badges.join('')}
                    </div>
                </div>
                <div class="instructor-info">
                    <h3 class="instructor-name">${instructor.name}</h3>
                    <div class="instructor-expertise">
                        <span class="material-icons">local_library</span>
                        <span>${expertise_areas}</span>
                    </div>
                    <div class="instructor-stats">
                        <div class="stat">
                            <span class="stat-value">${instructor.coursesCount}</span>
                            <span class="stat-label">Courses</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value">${formatNumber(instructor.studentsCount)}</span>
                            <span class="stat-label">Students</span>
                        </div>
                    </div>
                    <div class="instructor-rating">
                        <span class="rating-stars">${starsHTML}</span>
                        <span class="rating-value">${rating.toFixed(1)}</span>
                        <span class="rating-count">(${formatNumber(instructor.reviewCount)})</span>
                    </div>
                    <button class="view-profile-btn">
                        View Profile
                        <span class="material-icons">arrow_forward</span>
                    </button>
                </div>
            </div>
        `;
    }

    function updateyears_of_experienceFilters(years_of_experienceMap) {
        if (years_of_experienceMap && Array.isArray(years_of_experienceMap)) {
            years_of_experienceMap.forEach(exp => {
                const input = $(`input[name="years_of_experience"][value="${exp.name}"]`);
                if (input.length) {
                    input.siblings('.count').text(`(${exp.count})`);
                }
            });
        }
    }

    function updateexpertise_areasFilters(expertise_areasMap) {
        if (expertise_areasMap && Array.isArray(expertise_areasMap)) {
            expertise_areasMap.forEach(area => {
                const input = $(`input[name="expertise_areas"][value="${area.name}"]`);
                if (input.length) {
                    input.siblings('.count').text(`(${area.count})`);
                }
            });
        }
    }

    function renderPagination(totalPages, currentPage) {
        const container = $('#pagination');
        container.empty();

        if (totalPages <= 1) {
            return;
        }

        // Previous button
        const prevBtn = $(`
            <button class="page-btn prev-btn" ${currentPage === 1 ? 'disabled' : ''}>
                <span class="material-icons">chevron_left</span>
            </button>
        `);
        prevBtn.on('click', function() {
            if (currentPage > 1) {
                currentFilters.page = currentPage - 1;
                applyFilters();
            }
        });
        container.append(prevBtn);

        // Page numbers
        const pageNumbers = $('<div class="page-numbers"></div>');
        
        // Always show first page
        if (currentPage > 3) {
            const btn = createPageButton(1, currentPage);
            pageNumbers.append(btn);
            
            if (currentPage > 4) {
                pageNumbers.append('<span class="page-ellipsis">...</span>');
            }
        }
        
        // Show pages around current
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const btn = createPageButton(i, currentPage);
            pageNumbers.append(btn);
        }
        
        // Always show last page
        if (currentPage < totalPages - 2) {
            if (currentPage < totalPages - 3) {
                pageNumbers.append('<span class="page-ellipsis">...</span>');
            }
            const btn = createPageButton(totalPages, currentPage);
            pageNumbers.append(btn);
        }
        
        container.append(pageNumbers);

        // Next button
        const nextBtn = $(`
            <button class="page-btn next-btn" ${currentPage === totalPages ? 'disabled' : ''}>
                <span class="material-icons">chevron_right</span>
            </button>
        `);
        nextBtn.on('click', function() {
            if (currentPage < totalPages) {
                currentFilters.page = currentPage + 1;
                applyFilters();
            }
        });
        container.append(nextBtn);
    }

    function createPageButton(pageNum, currentPage) {
        const btn = $(`
            <button class="page-number ${pageNum === currentPage ? 'active' : ''}">
                ${pageNum}
            </button>
        `);
        btn.on('click', function() {
            currentFilters.page = pageNum;
            applyFilters();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        return btn;
    }

    function updateActiveFilters() {
        const container = $('#active-filters');
        container.empty();

        const tags = [];

        // years_of_experience tags
        currentFilters.years_of_experience.forEach(exp => {
            tags.push({
                label: exp,
                type: 'years_of_experience',
                value: exp
            });
        });

        // expertise_areas tags
        currentFilters.expertise_areas.forEach(area => {
            tags.push({
                label: area,
                type: 'expertise_areas',
                value: area
            });
        });

        // Rating tags
        currentFilters.rating.forEach(rating => {
            tags.push({
                label: `${rating}+ Stars`,
                type: 'rating',
                value: rating
            });
        });

        // Verified tag
        if (currentFilters.verified === true) {
            tags.push({
                label: 'Verified',
                type: 'verified',
                value: true
            });
        }

        // Featured tag
        if (currentFilters.featured === true) {
            tags.push({
                label: 'Featured',
                type: 'featured',
                value: true
            });
        }

        // Courses tag
        if (currentFilters.courses !== 'all') {
            tags.push({
                label: `${currentFilters.courses} courses`,
                type: 'courses',
                value: currentFilters.courses
            });
        }

        // Search tag
        if (currentFilters.search.trim() !== '') {
            tags.push({
                label: `Search: "${currentFilters.search}"`,
                type: 'search',
                value: currentFilters.search
            });
        }

        // Render tags
        if (tags.length > 0) {
            tags.forEach(tag => {
                const tagEl = $(`
                    <span class="filter-tag">
                        ${tag.label}
                        <button class="remove-filter" data-type="${tag.type}" data-value="${tag.value}">
                            <span class="material-icons">close</span>
                        </button>
                    </span>
                `);
                container.append(tagEl);
            });

            // Add clear all button
            const clearAllBtn = $(`
                <button class="clear-all-filters">Clear All</button>
            `);
            clearAllBtn.on('click', clearAllFilters);
            container.append(clearAllBtn);

            container.show();
        } else {
            container.hide();
        }
    }

    function clearAllFilters() {
        // Uncheck all checkboxes
        $('input[type="checkbox"]').prop('checked', false);
        $('input[name="courses"][value="all"]').prop('checked', true);
        
        // Reset filters
        currentFilters = {
            years_of_experience: [],
            expertise_areas: [],
            rating: [],
            verified: null,
            featured: null,
            courses: 'all',
            search: '',
            sortby: currentFilters.sortby,
            page: 1
        };

        // Clear search
        $('#instructor-search').val('');

        // Apply filters
        applyFilters();
    }

    // Event Listeners
    
    // years_of_experience filter change
    $(document).on('change', 'input[name="years_of_experience"]', function() {
        const value = $(this).val();
        if ($(this).is(':checked')) {
            if (!currentFilters.years_of_experience.includes(value)) {
                currentFilters.years_of_experience.push(value);
            }
        } else {
            currentFilters.years_of_experience = currentFilters.years_of_experience.filter(v => v !== value);
        }
        currentFilters.page = 1;
        applyFilters();
    });

    // expertise_areas filter change
    $(document).on('change', 'input[name="expertise_areas"]', function() {
        const value = $(this).val();
        if ($(this).is(':checked')) {
            if (!currentFilters.expertise_areas.includes(value)) {
                currentFilters.expertise_areas.push(value);
            }
        } else {
            currentFilters.expertise_areas = currentFilters.expertise_areas.filter(v => v !== value);
        }
        currentFilters.page = 1;
        applyFilters();
    });

    // Rating filter change
    $(document).on('change', 'input[name="rating"]', function() {
        const value = parseFloat($(this).val());
        if ($(this).is(':checked')) {
            if (!currentFilters.rating.includes(value)) {
                currentFilters.rating.push(value);
            }
        } else {
            currentFilters.rating = currentFilters.rating.filter(v => v !== value);
        }
        currentFilters.page = 1;
        applyFilters();
    });

    // Verified filter change
    $(document).on('change', 'input[name="verified"]', function() {
        currentFilters.verified = $(this).is(':checked') ? true : null;
        currentFilters.page = 1;
        applyFilters();
    });

    // Featured filter change
    $(document).on('change', 'input[name="featured"]', function() {
        currentFilters.featured = $(this).is(':checked') ? true : null;
        currentFilters.page = 1;
        applyFilters();
    });

    // Courses filter change (radio)
    $(document).on('change', 'input[name="courses"]', function() {
        currentFilters.courses = $(this).val();
        currentFilters.page = 1;
        applyFilters();
    });

    // Sort change
    $('#sort-select').on('change', function() {
        currentFilters.sortby = $(this).val();
        currentFilters.page = 1;
        applyFilters();
    });

    // Search
    let searchTimeout;
    $('#instructor-search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentFilters.search = $(this).val();
            currentFilters.page = 1;
            applyFilters();
        }, 500);
    });

    // Clear all filters button
    $('.clear-filters, #clearFilters').on('click', clearAllFilters);

    // Remove individual filter tag
    $(document).on('click', '.remove-filter', function() {
        const type = $(this).data('type');
        const value = $(this).data('value');

        switch (type) {
            case 'years_of_experience':
                currentFilters.years_of_experience = currentFilters.years_of_experience.filter(v => v !== value);
                $(`input[name="years_of_experience"][value="${value}"]`).prop('checked', false);
                break;
            case 'expertise_areas':
                currentFilters.expertise_areas = currentFilters.expertise_areas.filter(v => v !== value);
                $(`input[name="expertise_areas"][value="${value}"]`).prop('checked', false);
                break;
            case 'rating':
                currentFilters.rating = currentFilters.rating.filter(v => v !== parseFloat(value));
                $(`input[name="rating"][value="${value}"]`).prop('checked', false);
                break;
            case 'verified':
                currentFilters.verified = null;
                $('input[name="verified"]').prop('checked', false);
                break;
            case 'featured':
                currentFilters.featured = null;
                $('input[name="featured"]').prop('checked', false);
                break;
            case 'courses':
                currentFilters.courses = 'all';
                $('input[name="courses"][value="all"]').prop('checked', true);
                break;
            case 'search':
                currentFilters.search = '';
                $('#instructor-search').val('');
                break;
        }

        currentFilters.page = 1;
        applyFilters();
    });

    // Filter group toggle
    $('.filter-group h4').on('click', function() {
        $(this).toggleClass('collapsed');
        $(this).next('.filter-content').slideToggle(300);
    });

    // Mobile filter toggle
    $('.filter-toggle-mobile').on('click', function() {
        $('.filters-sidebar').toggleClass('show');
    });

    // Close mobile filters when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.filters-sidebar, .filter-toggle-mobile').length) {
            $('.filters-sidebar').removeClass('show');
        }
    });

    // Instructor card click
    $(document).on('click', '.instructor-card', function(e) {
        if (!$(e.target).closest('.view-profile-btn').length) {
            const instructorId = $(this).data('id');
            window.location.href = baseUrl + `instructor-profile?instructor_id=${instructorId}`;
        }
    });
    
    // View profile button
    $(document).on('click', '.view-profile-btn', function(e) {
        e.stopPropagation();
        const instructorId = $(this).closest('.instructor-card').data('id');
        window.location.href = baseUrl + `instructor-profile?instructor_id=${instructorId}`;
    });

    // Utility function to format numbers
    function formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }
});
