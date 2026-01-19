$(document).ready(function() {
    
    let categoryMap = {};
    const categoryInputs = document.querySelectorAll('input[name="category"]');
    for(let i = 0; i < categoryInputs.length; i++){
        categoryMap[categoryInputs[i].value] = categoryInputs[i].dataset.categoryId;
    }

    let currentFilters = {
        category: [],
        level: [],
        price: 'all',
        rating: [],
        duration: [],
        search: '',
        sortby:'relevance',
        page:1
    };

    // Get base URL from window variable
    const baseUrl = window.baseUrl || '/eduverse/';

    // Load filters from URL on page load
    loadFiltersFromURL();
    applyFilters();
  
    function applyFilters() {
        $('#loadingSpinner').show();
        const category_id = currentFilters.category.map(cat => categoryMap[cat]);
        $.ajax({
            url: baseUrl + 'ajax/courses.php',
            type: 'GET',
            dataType: 'json',
            data: {
                category: JSON.stringify(category_id),
                level: JSON.stringify(currentFilters.level),
                price: currentFilters.price,
                rating: JSON.stringify(currentFilters.rating),
                duration: JSON.stringify(currentFilters.duration),
                search: currentFilters.search,
                sortby: currentFilters.sortby,
                page: currentFilters.page
            },
            success: function(response) {
                $('#loadingSpinner').hide();
                
                if (response.success) {
                    renderCourses(response.courses);
                    updateCategoryFilters(response.categoryMap);
                    renderPagination(response.totalPages, response.currentPage);
                    updateActiveFilters();
                    $('#resultsCount').text(response.totalCourses);
                    // Update URL after successful filter application
                    updateURL();
                    // window.scrollTo(0, 0);
                } else {
                    $('#coursesGrid').html('<p class="text-danger">Error loading courses</p>');
                }
            },
            error: function(xhr, status, error) {
                $('#loadingSpinner').hide();
                $('#coursesGrid').html('<p class="text-danger">Failed to load courses. Please try again.</p>');
            }
        });
    }

    function loadFiltersFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Load categories
        const categoryParam = urlParams.get('category');
        if (categoryParam) {
            const categorySlugs = categoryParam.split(',').map(slug => slug.trim());
            categorySlugs.forEach(slug => {
                // Verify the category exists in categoryMap
                if (categoryMap.hasOwnProperty(slug)) {
                    currentFilters.category.push(slug);
                    $(`input[name="category"][value="${slug}"]`).prop('checked', true);
                }
            });
        }
        
        // Load levels
        const levelParam = urlParams.get('level');
        if (levelParam) {
            const levels = levelParam.split(',').map(l => l.trim());
            currentFilters.level = levels;
            levels.forEach(level => {
                $(`input[name="level"][value="${level}"]`).prop('checked', true);
            });
        }
        
        // Load price
        const priceParam = urlParams.get('price');
        if (priceParam && ['all', 'free', 'paid'].includes(priceParam)) {
            currentFilters.price = priceParam;
            $(`input[name="price"][value="${priceParam}"]`).prop('checked', true);
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
        
        // Load durations
        const durationParam = urlParams.get('duration');
        if (durationParam) {
            const durations = durationParam.split(',').map(d => d.trim());
            currentFilters.duration = durations;
            durations.forEach(duration => {
                $(`input[name="duration"][value="${duration}"]`).prop('checked', true);
            });
        }
        
        // Load search
        const searchParam = urlParams.get('search');
        if (searchParam) {
            currentFilters.search = decodeURIComponent(searchParam);
            $('#courseSearch').val(currentFilters.search);
        }
        
        // Load sort
        const sortParam = urlParams.get('sort');
        if (sortParam) {
            currentFilters.sortby = sortParam;
            $('#sortSelect').val(sortParam);
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
        
        // Add category slugs to URL
        if (currentFilters.category.length > 0) {
            params.set('category', currentFilters.category.join(','));
        }
        
        // Add levels to URL
        if (currentFilters.level.length > 0) {
            params.set('level', currentFilters.level.join(','));
        }
        
        // Add price to URL
        if (currentFilters.price !== 'all') {
            params.set('price', currentFilters.price);
        }
        
        // Add ratings to URL
        if (currentFilters.rating.length > 0) {
            params.set('rating', currentFilters.rating.join(','));
        }
        
        // Add durations to URL
        if (currentFilters.duration.length > 0) {
            params.set('duration', currentFilters.duration.join(','));
        }
        
        // Add search to URL
        if (currentFilters.search.trim() !== '') {
            params.set('search', encodeURIComponent(currentFilters.search));
        }
        
        // Add sort to URL if not default
        if (currentFilters.sortby !== 'relevance') {
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



  function renderCourses(courses) {
        const container = $('#coursesGrid');
        container.empty();

        if (!courses || courses.length === 0) {
            container.html(`
                <div class="no-results">
                    <span class="material-icons">search_off</span>
                    <h3>No courses found</h3>
                    <p>Try adjusting your filters or search query</p>
                </div>
            `);
            return;
        }

        courses.forEach(course => {
            const priceClass = course.displayPrice === "Free" ? "free" : "paid";
            const stars = renderStars(course.average_rating);
            const durationText = course.durationDisplay || 'Self-paced';

            const card = `
                <div class="course-card" data-course-id="${course.course_id}">
                    <div class="course-image">
                        <img src="${course.thumbnail_url}" alt="${course.title}" class="img-fluid">
                        <span class="course-badge badge-${course.level}">${formatLevel(course.level)}</span>
                    </div>
                    <div class="course-content">
                        <div class="course-category">${course.category_name}</div>
                        <h3 class="course-title">${course.title}</h3>
                        <p class="course-subtitle">${course.subtitle || ''}</p>
                        
                        <div class="course-instructor">
                            <img src="${course.profile_image_url}" alt="${course.instructor_name}" class="instructor-avatar">
                            <span class="instructor-name">${course.instructor_name}</span>
                        </div>

                        <div class="course-rating">
                            ${stars}
                            <span class="rating-value">${parseFloat(course.average_rating).toFixed(1)}</span>
                            <span class="review-count">(${course.total_reviews})</span>
                        </div>

                        <div class="course-stats">
                            <span class="stat">
                                <span class="material-icons">video_library</span>
                                ${course.total_lectures} lectures
                            </span>
                            <span class="stat">
                                <span class="material-icons">schedule</span>
                                ${durationText}
                            </span>
                            <span class="stat">
                                <span class="material-icons">people</span>
                                ${formatNumber(course.enrollment_count)}
                            </span>
                        </div>

                        <div class="course-footer">
                            <span class="price ${priceClass}">${course.displayPrice}</span>
                            <a href="course/${course.course_id}" class="btn btn-primary">View Course</a>
                        </div>
                    </div>
                </div>
            `;
            container.append(card);
        });
    }


    function renderStars(rating) {
        let stars = '';
        const rate = parseFloat(rating);
        for (let i = 1; i <= 5; i++) {
            if (i <= Math.floor(rate)) {
                stars += '<span class="material-icons">star</span>';
            } else if (i === Math.ceil(rate) && rate % 1 !== 0) {
                stars += '<span class="material-icons">star_half</span>';
            } else {
                stars += '<span class="material-icons">star_outline</span>';
            }
        }
        return stars;
    }

    function formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    }

  
    function updateCategoryFilters(categoryMap) {
        // Create a map for easier lookup by category_id
        const countById = {};
        categoryMap.forEach(cat => {
            countById[cat.category_id] = cat.count;
        });

        // Update each category filter's count display
        $('#categoryFilters input[type="checkbox"]').each(function() {
            const categoryId = $(this).data('category-id');
            const count = countById[categoryId] || 0;
            const countSpan = $(this).closest('.filter-option').find('.count');
            countSpan.text('(' + formatNumber(count) + ')');
        });
    }
    function updateActiveFilters() {
        const container = $('#activeFilters');
        container.empty();

        const addFilterTag = (label, filterType, value) => {
            const tag = $(`
                <span class="filter-tag">
                    ${label}
                    <span class="material-icons" data-filter-type="${filterType}" data-filter-value="${value}">close</span>
                </span>
            `);
            container.append(tag);
        };

        // Add category filters
        currentFilters.category.forEach(cat => {
            addFilterTag(formatCategory(cat), 'category', cat);
        });

        // Add level filters
        currentFilters.level.forEach(level => {
            addFilterTag(formatLevel(level), 'level', level);
        });

        // Add price filter
        if (currentFilters.price !== 'all') {
            addFilterTag(formatLevel(currentFilters.price), 'price', currentFilters.price);
        }

        // Add rating filters
        currentFilters.rating.forEach(rating => {
            addFilterTag(`${rating}+ stars`, 'rating', rating);
        });

        // Add duration filters
        currentFilters.duration.forEach(dur => {
            addFilterTag(formatDuration(dur), 'duration', dur);
        });

        // Handle filter tag removal
        $('.filter-tag .material-icons').on('click', function(e) {
            e.stopPropagation();
            const filterType = $(this).data('filter-type');
            const filterValue = $(this).data('filter-value');
            removeFilter(filterType, filterValue);
        });
    }

    function removeFilter(type, value) {
        if (type === 'price') {
            currentFilters.price = 'all';
            $('input[name="price"][value="all"]').prop('checked', true);
        } else {
            const index = currentFilters[type].indexOf(value);
            if (index > -1) {
                currentFilters[type].splice(index, 1);
            }
            $(`input[name="${type}"][value="${value}"]`).prop('checked', false);
        }
        applyFilters();
        currentFilters.page = 1; 
    }

    // Event: Category checkbox
    $('input[name="category"]').on('change', function() {
        const value = $(this).val();
        if ($(this).is(':checked')) {
            currentFilters.category.push(value);
        } else {
            const index = currentFilters.category.indexOf(value);
            if (index > -1) currentFilters.category.splice(index, 1);
        }
        currentFilters.page = 1; 
        applyFilters();
    });

    // Event: Level checkbox
    $('input[name="level"]').on('change', function() {
        const value = $(this).val();
        if ($(this).is(':checked')) {
            currentFilters.level.push(value);
        } else {
            const index = currentFilters.level.indexOf(value);
            if (index > -1) currentFilters.level.splice(index, 1);
        }
        currentFilters.page = 1; 
        applyFilters();
    });

    // Event: Price radio
    $('input[name="price"]').on('change', function() {
        currentFilters.price = $(this).val();
        currentFilters.page = 1; 
        applyFilters();
    });

    // Event: Rating checkbox
    $('input[name="rating"]').on('change', function() {
        const value = parseFloat($(this).val());
        if ($(this).is(':checked')) {
            currentFilters.rating.push(value);
        } else {
            const index = currentFilters.rating.indexOf(value);
            if (index > -1) currentFilters.rating.splice(index, 1);
        }
        currentFilters.page = 1; 
        applyFilters();
    });

    // Event: Duration checkbox
    $('input[name="duration"]').on('change', function() {
        const value = $(this).val();
        if ($(this).is(':checked')) {
            currentFilters.duration.push(value);
        } else {
            const index = currentFilters.duration.indexOf(value);
            if (index > -1) currentFilters.duration.splice(index, 1);
        }
        currentFilters.page = 1; 
        applyFilters();
    });

    // Event: Search input
    let searchTimeout;
    $('#courseSearch').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentFilters.search = $(this).val();
            currentFilters.page = 1; 
            applyFilters();
        }, 300);
    });

    // Event: Sort select
    $('#sortSelect').on('change', function() {
        currentFilters.sortby = $(this).val();
        currentFilters.page = 1; 
        applyFilters();
    });

    // Event: Clear all filters
    $('#clearFilters').on('click', function() {
        currentFilters = {
            category: [],
            level: [],
            price: 'all',
            rating: [],
            duration: [],
            search: '',
            sortby:'relevance',
            page:1
        };
        $('.filters-sidebar input[type="checkbox"]').prop('checked', false);
        $('input[name="price"][value="all"]').prop('checked', true);
        $('#courseSearch').val('');
        currentFilters.page = 1; 
        applyFilters();
    });

    // Event: Mobile filter toggle
    $('#filterToggle').on('click', function() {
        $('.filters-sidebar').toggleClass('active');
        $('body').toggleClass('filter-open');
    });

 
    function formatLevel(level) {
        const levels = {
            'beginner': 'Beginner',
            'intermediate': 'Intermediate',
            'advanced': 'Advanced',
            'all': 'All Levels',
        };
        return levels[level] || level;
    }

    function formatCategory(category) {
        return category.split('-').map(word => 
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    }
    function formatDuration(duration) {
        const durations = {
            'short': '0-5 hours',
            'medium': '5-20 hours',
            'long': '20+ hours'
        };
        return durations[duration] || duration;
    }

    function renderPagination(totalPages, currentPage) {
        const pagination = $('#pagination');
        pagination.empty();

        if (totalPages <= 1) return;

        // Previous button
        const prevDisabled = currentPage === 1 ? 'disabled' : '';
        pagination.append(`
            <button class="btn-page ${prevDisabled}" data-page="${currentPage - 1}" ${prevDisabled ? 'disabled' : ''}>
                <span class="material-icons">chevron_left</span>
            </button>
        `);

        // Calculate page range to display
        let startPage = 1;
        let endPage = totalPages;
        const maxVisiblePages = 5;

        if (totalPages > maxVisiblePages) {
            startPage = Math.max(1, currentPage - 2);
            endPage = Math.min(totalPages, currentPage + 2);

            if (currentPage <= 3) {
                endPage = maxVisiblePages;
            } else if (currentPage > totalPages - 3) {
                startPage = totalPages - maxVisiblePages + 1;
            }
        }

        // Add first page button if needed
        if (startPage > 1) {
            pagination.append(`<button class="btn-page" data-page="1">1</button>`);
            if (startPage > 2) {
                pagination.append(`<button class="btn-page" disabled>...</button>`);
            }
        }

        // Add page number buttons
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === currentPage ? 'active' : '';
            pagination.append(`<button class="btn-page ${activeClass}" data-page="${i}">${i}</button>`);
        }

        // Add last page button if needed
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                pagination.append(`<button class="btn-page" disabled>...</button>`);
            }
            pagination.append(`<button class="btn-page" data-page="${totalPages}">${totalPages}</button>`);
        }

        // Next button
        const nextDisabled = currentPage === totalPages ? 'disabled' : '';
        pagination.append(`
            <button class="btn-page ${nextDisabled}" data-page="${currentPage + 1}" ${nextDisabled ? 'disabled' : ''}>
                <span class="material-icons">chevron_right</span>
            </button>
        `);

        // Pagination click handler
        pagination.off('click').on('click', '.btn-page:not([disabled])', function() {
            const page = $(this).data('page');
            if (page && page !== currentFilters.page) {
                currentFilters.page = page;
                applyFilters();
                window.scrollTo(0, 0);
            }
        });
    }

});
