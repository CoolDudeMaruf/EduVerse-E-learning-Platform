$(document).ready(function() {
    
    let currentFilters = {
        courseCount: [],
        status: [],
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
        $.ajax({
            url: baseUrl + 'ajax/categories.php',
            type: 'GET',
            dataType: 'json',
            data: {
                courseCount: JSON.stringify(currentFilters.courseCount),
                status: JSON.stringify(currentFilters.status),
                search: currentFilters.search,
                sortby: currentFilters.sortby,
                page: currentFilters.page
            },
            success: function(response) {
                $('#loadingSpinner').hide();
                
                if (response.success) {
                    renderCategories(response.categories);
                    renderPagination(response.totalPages, response.currentPage);
                    updateActiveFilters();
                    $('#resultsCount').text(response.totalCategories);
                    updateURL();
                } else {
                    $('#categoriesGrid').html('<p class="text-danger">Error loading categories</p>');
                }
            },
            error: function(xhr, status, error) {
                $('#loadingSpinner').hide();
                $('#categoriesGrid').html('<p class="text-danger">Failed to load categories. Please try again.</p>');
            }
        });
    }

    function loadFiltersFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        
        const countParam = urlParams.get('courseCount');
        if (countParam) {
            const counts = countParam.split(',').map(c => c.trim());
            currentFilters.courseCount = counts;
            counts.forEach(count => {
                $(`input[name="courseCount"][value="${count}"]`).prop('checked', true);
            });
        }
        
        const statusParam = urlParams.get('status');
        if (statusParam) {
            const statuses = statusParam.split(',').map(s => s.trim());
            currentFilters.status = statuses;
            statuses.forEach(status => {
                $(`input[name="status"][value="${status}"]`).prop('checked', true);
            });
        }
        
        const searchParam = urlParams.get('search');
        if (searchParam) {
            currentFilters.search = decodeURIComponent(searchParam);
            $('#categorySearch').val(currentFilters.search);
        }
        
        const sortParam = urlParams.get('sort');
        if (sortParam) {
            currentFilters.sortby = sortParam;
            $('#sortSelect').val(sortParam);
        }
        
        const pageParam = urlParams.get('page');
        if (pageParam) {
            const pageNum = parseInt(pageParam);
            if (!isNaN(pageNum) && pageNum > 0) {
                currentFilters.page = pageNum;
            }
        }
    }

    function updateURL() {
        const params = new URLSearchParams();
        
        if (currentFilters.courseCount.length > 0) {
            params.set('courseCount', currentFilters.courseCount.join(','));
        }
        
        if (currentFilters.status.length > 0) {
            params.set('status', currentFilters.status.join(','));
        }
        
        if (currentFilters.search.trim() !== '') {
            params.set('search', encodeURIComponent(currentFilters.search));
        }
        
        if (currentFilters.sortby !== 'popular') {
            params.set('sort', currentFilters.sortby);
        }
        
        if (currentFilters.page > 1) {
            params.set('page', currentFilters.page);
        }
        
        const newUrl = params.toString() ? `${window.location.pathname}?${params.toString()}` : window.location.pathname;
        window.history.replaceState({}, '', newUrl);
    }

    function renderCategories(categories) {
        const container = $('#categoriesGrid');
        container.empty();

        if (!categories || categories.length === 0) {
            container.html(`
                <div class="no-results">
                    <span class="material-icons">search_off</span>
                    <h3>No categories found</h3>
                    <p>Try adjusting your filters or search query</p>
                </div>
            `);
            return;
        }

        categories.forEach(category => {
            let badgesHtml = '';
            if (category.is_trending) {
                badgesHtml += '<span class="badge trending"><span class="material-icons">trending_up</span>Trending</span>';
            }
            if (category.is_new) {
                badgesHtml += '<span class="badge new"><span class="material-icons">new_releases</span>New</span>';
            }

            const card = `
                <div class="category-card" data-category-id="${category.category_id}">
                    <div class="category-thumbnail ${category.slug}">
                        <span class="material-icons category-icon">${category.icon || 'folder'}</span>
                        <div class="category-badges">
                            ${badgesHtml}
                        </div>
                    </div>
                    <div class="category-info">
                        <h3 class="category-name">${category.name}</h3>
                        <p class="category-description">${category.description || 'Explore this category'}</p>
                        
                        <div class="category-stats">
                            <span class="stat">
                                <span class="material-icons">school</span>
                                ${formatNumber(category.course_count)} courses
                            </span>
                            ${category.avg_rating > 0 ? `
                                <span class="stat">
                                    <span class="material-icons">star</span>
                                    ${category.avg_rating.toFixed(1)} rating
                                </span>
                            ` : ''}
                        </div>

                        <a href="${baseUrl}courses?category=${category.slug}" class="explore-btn">
                            Explore Courses
                            <span class="material-icons">arrow_forward</span>
                        </a>
                    </div>
                </div>
            `;
            container.append(card);
        });
    }

    function formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
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

        currentFilters.courseCount.forEach(count => {
            addFilterTag(count, 'courseCount', count);
        });

        currentFilters.status.forEach(status => {
            const label = status.charAt(0).toUpperCase() + status.slice(1);
            addFilterTag(label, 'status', status);
        });

        $('.filter-tag .material-icons').on('click', function(e) {
            e.stopPropagation();
            const filterType = $(this).data('filter-type');
            const filterValue = $(this).data('filter-value');
            removeFilter(filterType, filterValue);
        });
    }

    function removeFilter(type, value) {
        const index = currentFilters[type].indexOf(value);
        if (index > -1) {
            currentFilters[type].splice(index, 1);
        }
        $(`input[name="${type}"][value="${value}"]`).prop('checked', false);
        currentFilters.page = 1;
        applyFilters();
    }

    $('input[name="courseCount"]').on('change', function() {
        const value = $(this).val();
        if ($(this).is(':checked')) {
            currentFilters.courseCount.push(value);
        } else {
            const index = currentFilters.courseCount.indexOf(value);
            if (index > -1) currentFilters.courseCount.splice(index, 1);
        }
        currentFilters.page = 1;
        applyFilters();
    });

    $('input[name="status"]').on('change', function() {
        const value = $(this).val();
        if ($(this).is(':checked')) {
            currentFilters.status.push(value);
        } else {
            const index = currentFilters.status.indexOf(value);
            if (index > -1) currentFilters.status.splice(index, 1);
        }
        currentFilters.page = 1;
        applyFilters();
    });

    let searchTimeout;
    $('#categorySearch').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentFilters.search = $(this).val();
            currentFilters.page = 1;
            applyFilters();
        }, 300);
    });

    $('#sortSelect').on('change', function() {
        currentFilters.sortby = $(this).val();
        currentFilters.page = 1;
        applyFilters();
    });

    $('#clearFilters').on('click', function() {
        currentFilters = {
            courseCount: [],
            status: [],
            search: '',
            sortby: 'popular',
            page: 1
        };
        $('input[type="checkbox"]').prop('checked', false);
        $('#categorySearch').val('');
        $('#sortSelect').val('popular');
        currentFilters.page = 1;
        applyFilters();
    });

    $('#filterToggle').on('click', function() {
        $('.filters-sidebar').toggleClass('active');
        $('body').toggleClass('filter-open');
    });

    function renderPagination(totalPages, currentPage) {
        const pagination = $('#pagination');
        pagination.empty();

        if (totalPages <= 1) return;

        const prevDisabled = currentPage === 1 ? 'disabled' : '';
        pagination.append(`
            <button class="page-btn ${prevDisabled}" data-page="${currentPage - 1}" ${prevDisabled ? 'disabled' : ''}>
                <span class="material-icons">chevron_left</span>
            </button>
        `);

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

        const pageNumbers = $('<div class="page-numbers"></div>');

        if (startPage > 1) {
            pageNumbers.append(`<button class="page-number" data-page="1">1</button>`);
            if (startPage > 2) {
                pageNumbers.append(`<span class="page-ellipsis">...</span>`);
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === currentPage ? 'active' : '';
            pageNumbers.append(`<button class="page-number ${activeClass}" data-page="${i}">${i}</button>`);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                pageNumbers.append(`<span class="page-ellipsis">...</span>`);
            }
            pageNumbers.append(`<button class="page-number" data-page="${totalPages}">${totalPages}</button>`);
        }

        pagination.append(pageNumbers);

        const nextDisabled = currentPage === totalPages ? 'disabled' : '';
        pagination.append(`
            <button class="page-btn ${nextDisabled}" data-page="${currentPage + 1}" ${nextDisabled ? 'disabled' : ''}>
                <span class="material-icons">chevron_right</span>
            </button>
        `);

        // Bind pagination click handler
        pagination.off('click').on('click', '.page-number, .page-btn', function(e) {
            if ($(this).is('[disabled]')) return;
            const page = parseInt($(this).data('page'));
            if (page && page !== currentFilters.page && page > 0 && page <= totalPages) {
                currentFilters.page = page;
                applyFilters();
                window.scrollTo(0, 0);
            }
        });
    }

});