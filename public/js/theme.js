/* ===================================
   Theme System - Light/Dark Mode Toggle
   =================================== */

(function() {
    'use strict';
    
    // Theme configuration
    const THEME_KEY = 'eduverse-theme';
    const THEMES = {
        LIGHT: 'light',
        DARK: 'dark'
    };
    
    // Get saved theme or detect system preference
    function getInitialTheme() {
        const savedTheme = localStorage.getItem(THEME_KEY);
        
        if (savedTheme) {
            return savedTheme;
        }
        
        // Check system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return THEMES.DARK;
        }
        
        return THEMES.LIGHT;
    }
    
    // Apply theme to document
    function applyTheme(theme) {
        if (theme === THEMES.DARK) {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
        
        // Save to localStorage
        localStorage.setItem(THEME_KEY, theme);
        
        // Dispatch custom event for other scripts
        window.dispatchEvent(new CustomEvent('themechange', { detail: { theme } }));
    }
    
    // Toggle between themes
    function toggleTheme() {
        const currentTheme = getCurrentTheme();
        const newTheme = currentTheme === THEMES.DARK ? THEMES.LIGHT : THEMES.DARK;
        applyTheme(newTheme);
    }
    
    // Get current theme
    function getCurrentTheme() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? THEMES.DARK : THEMES.LIGHT;
    }
    
    // Create theme toggle button
    function createThemeToggle() {
        // Check if toggle already exists
        if (document.querySelector('.theme-toggle')) {
            return;
        }
        
        const button = document.createElement('button');
        button.className = 'theme-toggle';
        button.setAttribute('aria-label', 'Toggle dark mode');
        button.setAttribute('title', 'Toggle theme');
        button.innerHTML = `
            <span class="material-icons icon-sun">light_mode</span>
            <span class="material-icons icon-moon">dark_mode</span>
        `;
        
        button.addEventListener('click', toggleTheme);
        
        document.body.appendChild(button);
    }
    
    // Listen for system theme changes
    function watchSystemTheme() {
        if (window.matchMedia) {
            const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
            
            // Check if addEventListener is supported
            if (darkModeQuery.addEventListener) {
                darkModeQuery.addEventListener('change', (e) => {
                    // Only auto-switch if user hasn't manually set a preference
                    if (!localStorage.getItem(THEME_KEY)) {
                        applyTheme(e.matches ? THEMES.DARK : THEMES.LIGHT);
                    }
                });
            }
        }
    }
    
    // Initialize theme system
    function initTheme() {
        const initialTheme = getInitialTheme();
        applyTheme(initialTheme);
        createThemeToggle();
        watchSystemTheme();
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
    
    // Export functions to global scope for manual control
    window.EduVerseTheme = {
        toggle: toggleTheme,
        set: applyTheme,
        get: getCurrentTheme,
        THEMES: THEMES
    };
    
})();

/* ===================================
   Additional Theme Utilities
   =================================== */

// Consolidated theme change handler
window.addEventListener('themechange', (e) => {
    const theme = e.detail.theme;
    
    // Add transition animation class
    document.body.classList.add('theme-transitioning');
    setTimeout(() => {
        document.body.classList.remove('theme-transitioning');
    }, 300);
    
    // Update Chart.js charts if present
    if (window.Chart && window.Chart.instances) {
        Object.values(window.Chart.instances).forEach(chart => {
            if (chart && chart.options) {
                updateChartTheme(chart, theme);
            }
        });
    }
    
    // Save and restore scroll position
    const scrollPosition = window.scrollY;
    requestAnimationFrame(() => {
        window.scrollTo(0, scrollPosition);
    });
});

function updateChartTheme(chart, theme) {
    const isDark = theme === 'dark';
    
    if (chart.options.scales) {
        // Update axis colors
        Object.keys(chart.options.scales).forEach(scaleKey => {
            const scale = chart.options.scales[scaleKey];
            if (scale.ticks) {
                scale.ticks.color = isDark ? '#cbd5e1' : '#475569';
            }
            if (scale.grid) {
                scale.grid.color = isDark ? '#334155' : '#e2e8f0';
            }
        });
    }
    
    if (chart.options.plugins && chart.options.plugins.legend) {
        chart.options.plugins.legend.labels.color = isDark ? '#f1f5f9' : '#0f172a';
    }
    
    chart.update();
}

// Keyboard shortcut for theme toggle (Ctrl/Cmd + Shift + L)
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'L') {
        e.preventDefault();
        window.EduVerseTheme.toggle();
    }
});
