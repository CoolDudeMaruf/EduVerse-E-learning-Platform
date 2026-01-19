// ===================================
// Page Loader Script
// ===================================

(function() {
    'use strict';

    // Initialize page loader
    function initPageLoader() {
        const loader = document.getElementById('pageLoader');
        
        if (!loader) {
            return;
        }

        // Hide loader when page is fully loaded
        window.addEventListener('load', function() {
            hideLoader();
        });

        // Fallback: hide loader after maximum time
        setTimeout(function() {
            if (loader && !loader.classList.contains('fade-out')) {
                hideLoader();
            }
        }, 5000);

        // Hide loader on DOMContentLoaded as backup
        if (document.readyState === 'complete') {
            hideLoader();
        }
    }

    // Hide loader function
    function hideLoader() {
        const loader = document.getElementById('pageLoader');
        
        if (!loader) return;

        // Add minimum display time for smooth experience
        setTimeout(function() {
            loader.classList.add('fade-out');
            
            // Remove from DOM after animation completes
            setTimeout(function() {
                loader.style.display = 'none';
                // Trigger custom event
                document.dispatchEvent(new Event('pageLoaderHidden'));
            }, 500);
        }, 500);
    }

    // Show loader function (useful for SPA navigation)
    window.showPageLoader = function() {
        const loader = document.getElementById('pageLoader');
        if (loader) {
            loader.style.display = 'flex';
            loader.classList.remove('fade-out');
        }
    };

    // Hide loader function (exposed globally)
    window.hidePageLoader = hideLoader;

    // Initialize on script load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPageLoader);
    } else {
        initPageLoader();
    }

})();
