class VoiceCommandSystem {
    constructor() {
        this.recognition = null;
        this.isListening = false;
        this.isSupported = false;
        this.commands = {};
        this.currentLanguage = 'en-US';
        this.confidenceThreshold = 0.5;

        this.init();
    }

    init() {
        // Check for browser support
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            this.isSupported = true;
            this.setupRecognition();
            this.registerCommands();
            this.createUI();
        } else {
            this.showUnsupportedMessage();
        }
    }

    setupRecognition() {
        this.recognition.continuous = false;
        this.recognition.interimResults = true;
        this.recognition.lang = this.currentLanguage;
        this.recognition.maxAlternatives = 3;

        this.recognition.onstart = () => {
            this.isListening = true;
            this.updateUI('listening');
        };

        this.recognition.onresult = (event) => {
            const results = event.results[event.results.length - 1];
            const transcript = results[0].transcript.toLowerCase().trim();
            const confidence = results[0].confidence;

            if (results.isFinal) {
                this.updateTranscript(transcript, true);

                // Try processing even with lower confidence
                if (confidence >= this.confidenceThreshold) {
                    this.processCommand(transcript);
                } else {
                    this.showFeedback(`Trying: "${transcript}"`, 'info');
                    this.processCommand(transcript);
                }
            } else {
                this.updateTranscript(transcript, false);
            }
        };

        this.recognition.onerror = (event) => {
            this.handleError(event.error);
            this.isListening = false;
            this.updateUI('idle');
        };

        this.recognition.onend = () => {
            this.isListening = false;
            this.updateUI('idle');
        };
    }

    registerCommands() {
        // Navigation commands
        this.addCommand(['go to home', 'home page', 'take me home'], () => {
            this.navigate('index.php', 'Going to home page');
        });

        this.addCommand(['go to courses', 'show courses', 'browse courses'], () => {
            this.navigate('courses', 'Opening courses');
        });

        this.addCommand(['go to categories', 'show categories'], () => {
            this.navigate('categories', 'Opening categories');
        });

        this.addCommand(['go to instructors', 'show instructors'], () => {
            this.navigate('instructors', 'Opening instructors');
        });

        this.addCommand(['go to about', 'about us', 'about page'], () => {
            this.navigate('about', 'Opening about page');
        });

        this.addCommand(['go to dashboard', 'open dashboard', 'my dashboard'], () => {
            this.navigate('dashboard/', 'Opening dashboard');
        });

        this.addCommand(['login', 'log in', 'sign in'], () => {
            this.navigate('login', 'Opening login page');
        });

        this.addCommand(['sign up', 'register', 'create account'], () => {
            this.navigate('signup', 'Opening signup page');
        });

        // Search commands
        this.addCommand(['search', 'find', 'look for'], (transcript) => {
            const query = this.extractSearchQuery(transcript);
            if (query) {
                this.performSearch(query);
            } else {
                this.showFeedback('What would you like to search for?', 'info');
                this.speak('What would you like to search for?');
            }
        });

        // Cart and wishlist commands
        this.addCommand(['show cart', 'view cart', 'open cart', 'my cart'], () => {
            if (typeof showCartModal === 'function') {
                showCartModal();
                this.showFeedback('Opening cart', 'success');
            }
        });

        this.addCommand(['show wishlist', 'view wishlist', 'open wishlist', 'my wishlist'], () => {
            if (typeof showWishlistModal === 'function') {
                showWishlistModal();
                this.showFeedback('Opening wishlist', 'success');
            }
        });

        // Theme commands
        this.addCommand(['dark mode', 'enable dark mode', 'switch to dark'], () => {
            if (typeof window.themeManager !== 'undefined') {
                window.themeManager.setTheme('dark');
                this.showFeedback('Switched to dark mode', 'success');
            }
        });

        this.addCommand(['light mode', 'enable light mode', 'switch to light'], () => {
            if (typeof window.themeManager !== 'undefined') {
                window.themeManager.setTheme('light');
                this.showFeedback('Switched to light mode', 'success');
            }
        });

        // Scroll commands
        this.addCommand(['scroll up', 'go up', 'page up'], () => {
            this.smoothScroll(-window.innerHeight * 0.8);
            this.showFeedback('Scrolling up', 'info');
        });

        this.addCommand(['scroll down', 'go down', 'page down'], () => {
            this.smoothScroll(window.innerHeight * 0.8);
            this.showFeedback('Scrolling down', 'info');
        });

        this.addCommand(['scroll to top', 'top of page', 'go to top'], () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            this.showFeedback('Going to top', 'info');
        });

        this.addCommand(['scroll to bottom', 'bottom of page', 'go to bottom'], () => {
            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
            this.showFeedback('Going to bottom', 'info');
        });

        // Help command
        this.addCommand(['help', 'what can you do', 'show commands', 'voice commands'], () => {
            this.showHelpModal();
        });

        // Stop/Cancel commands
        this.addCommand(['stop', 'cancel', 'never mind', 'stop listening'], () => {
            this.stopListening();
            this.showFeedback('Voice command cancelled', 'info');
        });

        // Read page content
        this.addCommand(['read page', 'read this page', 'read content'], () => {
            this.readPageContent();
        });

        // Filter courses (if on courses page)
        this.addCommand(['show web development', 'filter web development'], () => {
            this.filterCourses('web development');
        });

        this.addCommand(['show data science', 'filter data science'], () => {
            this.filterCourses('data science');
        });

        this.addCommand(['show all courses', 'clear filter', 'show everything'], () => {
            this.clearFilters();
        });

        // ===== ADDITIONAL COMPREHENSIVE COMMANDS =====

        // More Navigation Commands
        this.addCommand(['go to contact', 'contact us', 'contact page'], () => {
            this.navigate('contact.php', 'Opening contact page');
        });

        this.addCommand(['go to checkout', 'checkout page', 'proceed to checkout'], () => {
            this.navigate('checkout', 'Opening checkout');
        });

        this.addCommand(['go to help', 'help center', 'get help'], () => {
            this.navigate('help-center.php', 'Opening help center');
        });

        this.addCommand(['go to privacy', 'privacy policy'], () => {
            this.navigate('privacy.php', 'Opening privacy policy');
        });

        this.addCommand(['go to terms', 'terms and conditions', 'terms of service'], () => {
            this.navigate('terms.php', 'Opening terms and conditions');
        });

        this.addCommand(['go to careers', 'careers page', 'job openings'], () => {
            this.navigate('careers.php', 'Opening careers page');
        });

        this.addCommand(['go to press', 'press page'], () => {
            this.navigate('press.php', 'Opening press page');
        });

        // Logout Command
        this.addCommand(['logout', 'log out', 'sign out'], () => {
            if (typeof logout === 'function') {
                logout();
                this.showFeedback('Logging out', 'success');
            } else {
                localStorage.clear();
                sessionStorage.clear();
                this.navigate('index.html', 'Logged out successfully');
            }
        });

        // Page Interaction Commands
        this.addCommand(['click button', 'press button'], (transcript) => {
            const buttonText = this.extractAfterKeyword(transcript, ['button']);
            this.clickElement('button', buttonText);
        });

        this.addCommand(['click link', 'open link'], (transcript) => {
            const linkText = this.extractAfterKeyword(transcript, ['link']);
            this.clickElement('a', linkText);
        });

        this.addCommand(['expand menu', 'open menu', 'show menu'], () => {
            const menu = document.querySelector('.mobile-menu, .nav-menu, .dropdown-menu');
            if (menu) {
                menu.classList.add('active', 'show');
                this.showFeedback('Menu expanded', 'success');
            }
        });

        this.addCommand(['close menu', 'hide menu', 'collapse menu'], () => {
            const menu = document.querySelector('.mobile-menu, .nav-menu, .dropdown-menu');
            if (menu) {
                menu.classList.remove('active', 'show');
                this.showFeedback('Menu closed', 'success');
            }
        });

        // Form Commands
        this.addCommand(['focus search', 'search box', 'search field'], () => {
            const searchInput = document.querySelector('input[type="search"], #searchInput, .search-input');
            if (searchInput) {
                searchInput.focus();
                this.showFeedback('Search field focused', 'success');
            }
        });

        this.addCommand(['clear form', 'reset form'], () => {
            const forms = document.querySelectorAll('form');
            if (forms.length > 0) {
                forms[0].reset();
                this.showFeedback('Form cleared', 'success');
            }
        });

        this.addCommand(['submit form', 'send form'], () => {
            const forms = document.querySelectorAll('form');
            if (forms.length > 0) {
                forms[0].submit();
                this.showFeedback('Submitting form', 'success');
            }
        });

        // Video Player Commands
        this.addCommand(['play video', 'start video', 'resume video'], () => {
            const video = document.querySelector('video');
            if (video) {
                video.play();
                this.showFeedback('Playing video', 'success');
            }
        });

        this.addCommand(['pause video', 'stop video'], () => {
            const video = document.querySelector('video');
            if (video) {
                video.pause();
                this.showFeedback('Video paused', 'success');
            }
        });

        this.addCommand(['mute video', 'mute audio'], () => {
            const video = document.querySelector('video');
            if (video) {
                video.muted = true;
                this.showFeedback('Video muted', 'success');
            }
        });

        this.addCommand(['unmute video', 'unmute audio'], () => {
            const video = document.querySelector('video');
            if (video) {
                video.muted = false;
                this.showFeedback('Video unmuted', 'success');
            }
        });

        this.addCommand(['fullscreen video', 'video fullscreen'], () => {
            const video = document.querySelector('video');
            if (video && video.requestFullscreen) {
                video.requestFullscreen();
                this.showFeedback('Entering fullscreen', 'success');
            }
        });

        this.addCommand(['exit fullscreen', 'leave fullscreen'], () => {
            if (document.fullscreenElement && document.exitFullscreen) {
                document.exitFullscreen();
                this.showFeedback('Exiting fullscreen', 'success');
            }
        });

        this.addCommand(['skip forward', 'fast forward', 'forward ten seconds'], () => {
            const video = document.querySelector('video');
            if (video) {
                video.currentTime += 10;
                this.showFeedback('Skipped forward 10 seconds', 'success');
            }
        });

        this.addCommand(['skip backward', 'rewind', 'back ten seconds'], () => {
            const video = document.querySelector('video');
            if (video) {
                video.currentTime -= 10;
                this.showFeedback('Skipped backward 10 seconds', 'success');
            }
        });

        this.addCommand(['increase speed', 'speed up', 'faster playback'], () => {
            const video = document.querySelector('video');
            if (video) {
                video.playbackRate = Math.min(video.playbackRate + 0.25, 2);
                this.showFeedback(`Speed: ${video.playbackRate}x`, 'success');
            }
        });

        this.addCommand(['decrease speed', 'slow down', 'slower playback'], () => {
            const video = document.querySelector('video');
            if (video) {
                video.playbackRate = Math.max(video.playbackRate - 0.25, 0.5);
                this.showFeedback(`Speed: ${video.playbackRate}x`, 'success');
            }
        });

        this.addCommand(['normal speed', 'reset speed'], () => {
            const video = document.querySelector('video');
            if (video) {
                video.playbackRate = 1;
                this.showFeedback('Speed reset to normal', 'success');
            }
        });

        // Page Refresh & Navigation
        this.addCommand(['refresh page', 'reload page', 'refresh'], () => {
            this.showFeedback('Refreshing page', 'info');
            setTimeout(() => location.reload(), 500);
        });

        this.addCommand(['go back', 'previous page', 'back'], () => {
            this.showFeedback('Going back', 'info');
            history.back();
        });

        this.addCommand(['go forward', 'next page', 'forward'], () => {
            this.showFeedback('Going forward', 'info');
            history.forward();
        });

        // Reading Commands
        this.addCommand(['read title', 'what is the title'], () => {
            const title = document.querySelector('h1, .page-title, .course-title');
            if (title) {
                this.speak(title.textContent);
                this.showFeedback('Reading title', 'info');
            }
        });

        this.addCommand(['read description', 'read details'], () => {
            const desc = document.querySelector('.description, .course-description, .details, p');
            if (desc) {
                this.speak(desc.textContent.substring(0, 300));
                this.showFeedback('Reading description', 'info');
            }
        });

        this.addCommand(['stop reading', 'stop speech'], () => {
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
                this.showFeedback('Speech stopped', 'info');
            }
        });

        // Zoom Commands
        this.addCommand(['zoom in', 'increase zoom', 'magnify'], () => {
            document.body.style.zoom = (parseFloat(document.body.style.zoom || 1) + 0.1).toString();
            this.showFeedback('Zoomed in', 'success');
        });

        this.addCommand(['zoom out', 'decrease zoom'], () => {
            document.body.style.zoom = Math.max(0.5, parseFloat(document.body.style.zoom || 1) - 0.1).toString();
            this.showFeedback('Zoomed out', 'success');
        });

        this.addCommand(['reset zoom', 'normal zoom'], () => {
            document.body.style.zoom = '1';
            this.showFeedback('Zoom reset', 'success');
        });

        // Print Command
        this.addCommand(['print page', 'print this page'], () => {
            this.showFeedback('Opening print dialog', 'info');
            window.print();
        });

        // Copy Commands
        this.addCommand(['copy url', 'copy link', 'copy address'], () => {
            navigator.clipboard.writeText(window.location.href).then(() => {
                this.showFeedback('URL copied to clipboard', 'success');
            });
        });

        // Accessibility Commands
        this.addCommand(['increase font size', 'bigger text'], () => {
            document.body.style.fontSize = (parseFloat(getComputedStyle(document.body).fontSize) + 2) + 'px';
            this.showFeedback('Font size increased', 'success');
        });

        this.addCommand(['decrease font size', 'smaller text'], () => {
            document.body.style.fontSize = (parseFloat(getComputedStyle(document.body).fontSize) - 2) + 'px';
            this.showFeedback('Font size decreased', 'success');
        });

        this.addCommand(['reset font size', 'normal text size'], () => {
            document.body.style.fontSize = '';
            this.showFeedback('Font size reset', 'success');
        });

        // Modal Commands
        this.addCommand(['close modal', 'close popup', 'close dialog'], () => {
            const modal = document.querySelector('.modal.active, .modal.show, [role="dialog"]');
            if (modal) {
                const closeBtn = modal.querySelector('.close, .modal-close, [data-dismiss="modal"]');
                if (closeBtn) closeBtn.click();
                else modal.classList.remove('active', 'show');
                this.showFeedback('Modal closed', 'success');
            }
        });

        // Filter Commands for Courses
        this.addCommand(['show free courses', 'filter free'], () => {
            this.filterByPrice('free');
        });

        this.addCommand(['show paid courses', 'filter paid'], () => {
            this.filterByPrice('paid');
        });

        this.addCommand(['sort by price', 'order by price'], () => {
            this.sortCourses('price');
        });

        this.addCommand(['sort by rating', 'order by rating'], () => {
            this.sortCourses('rating');
        });

        this.addCommand(['sort by popularity', 'most popular'], () => {
            this.sortCourses('popularity');
        });

        // Time Management
        this.addCommand(['what time is it', 'tell me the time', 'current time'], () => {
            const time = new Date().toLocaleTimeString();
            this.speak(`The time is ${time}`);
            this.showFeedback(time, 'info');
        });

        this.addCommand(['what date is it', 'tell me the date', 'current date'], () => {
            const date = new Date().toLocaleDateString();
            this.speak(`Today is ${date}`);
            this.showFeedback(date, 'info');
        });

        // Course Specific Commands
        this.addCommand(['enroll course', 'enroll now', 'join course'], () => {
            const enrollBtn = document.querySelector('.enroll-btn, .btn-enroll, button[data-action="enroll"]');
            if (enrollBtn) {
                enrollBtn.click();
                this.showFeedback('Enrolling in course', 'success');
            }
        });

        this.addCommand(['add to cart', 'add course to cart'], () => {
            const cartBtn = document.querySelector('.add-to-cart, .btn-cart, button[data-action="add-to-cart"]');
            if (cartBtn) {
                cartBtn.click();
                this.showFeedback('Added to cart', 'success');
            }
        });

        this.addCommand(['add to wishlist', 'save for later'], () => {
            const wishlistBtn = document.querySelector('.add-to-wishlist, .wishlist-btn, button[data-action="wishlist"]');
            if (wishlistBtn) {
                wishlistBtn.click();
                this.showFeedback('Added to wishlist', 'success');
            }
        });

        // Share Commands
        this.addCommand(['share page', 'share this'], () => {
            if (navigator.share) {
                navigator.share({
                    title: document.title,
                    url: window.location.href
                }).then(() => {
                    this.showFeedback('Shared successfully', 'success');
                });
            } else {
                this.showFeedback('Share not supported', 'warning');
            }
        });

        // Language Commands
        this.addCommand(['switch language', 'change language'], () => {
            const langSelector = document.querySelector('.language-selector, select[name="language"]');
            if (langSelector) {
                langSelector.focus();
                this.showFeedback('Language selector focused', 'success');
            }
        });

        // Tab Navigation
        this.addCommand(['next tab', 'switch tab'], () => {
            const tabs = document.querySelectorAll('.tab, [role="tab"]');
            if (tabs.length > 0) {
                const activeTab = document.querySelector('.tab.active, [role="tab"][aria-selected="true"]');
                const currentIndex = Array.from(tabs).indexOf(activeTab);
                const nextTab = tabs[(currentIndex + 1) % tabs.length];
                nextTab.click();
                this.showFeedback('Switched to next tab', 'success');
            }
        });

        this.addCommand(['previous tab', 'last tab'], () => {
            const tabs = document.querySelectorAll('.tab, [role="tab"]');
            if (tabs.length > 0) {
                const activeTab = document.querySelector('.tab.active, [role="tab"][aria-selected="true"]');
                const currentIndex = Array.from(tabs).indexOf(activeTab);
                const prevTab = tabs[(currentIndex - 1 + tabs.length) % tabs.length];
                prevTab.click();
                this.showFeedback('Switched to previous tab', 'success');
            }
        });

        // Dashboard Navigation
        this.addCommand(['student dashboard', 'my learning'], () => {
            this.navigate('dashboard/', 'Opening student dashboard');
        });

        this.addCommand(['instructor dashboard', 'teaching dashboard'], () => {
            this.navigate('dashboard/', 'Opening instructor dashboard');
        });

        this.addCommand(['admin dashboard', 'admin panel'], () => {
            this.navigate('dashboard/', 'Opening admin dashboard');
        });

        this.addCommand(['my courses', 'show my courses'], () => {
            this.navigate('dashboard/', 'Opening your courses');
        });

        this.addCommand(['my certificates', 'show certificates'], () => {
            this.navigate('dashboard/', 'Opening your certificates');
        });

        this.addCommand(['my achievements', 'show achievements'], () => {
            this.navigate('dashboard/', 'Opening your achievements');
        });

        // Focus Management
        this.addCommand(['focus first', 'first element'], () => {
            const firstFocusable = document.querySelector('a, button, input, select, textarea');
            if (firstFocusable) {
                firstFocusable.focus();
                this.showFeedback('Focused first element', 'success');
            }
        });

        this.addCommand(['next element', 'tab forward'], () => {
            const event = new KeyboardEvent('keydown', { key: 'Tab' });
            document.activeElement.dispatchEvent(event);
            this.showFeedback('Moving to next element', 'info');
        });
    }

    addCommand(triggers, action) {
        triggers.forEach(trigger => {
            this.commands[trigger.toLowerCase()] = action;
        });
    }

    processCommand(transcript) {
        transcript = transcript.toLowerCase().trim();

        if (this.commands[transcript]) {
            this.commands[transcript](transcript);
            return;
        }

        for (const [trigger, action] of Object.entries(this.commands)) {
            if (transcript.includes(trigger)) {
                action(transcript);
                return;
            }
        }

        const similarCommands = this.findSimilarCommands(transcript);
        if (similarCommands.length > 0) {
            this.showFeedback(`Did you mean "${similarCommands[0]}"? Executing...`, 'info');
            this.commands[similarCommands[0]](transcript);
            return;
        }

        this.showFeedback(`Command "${transcript}" not recognized. Say "help" for available commands.`, 'warning');
        this.speak('Command not recognized. Say help to see all commands.');
    }

    findSimilarCommands(transcript) {
        const words = transcript.split(' ');
        const matches = [];

        // Find commands that share at least 2 words with the transcript
        for (const trigger of Object.keys(this.commands)) {
            const triggerWords = trigger.split(' ');
            let matchCount = 0;

            for (const word of words) {
                if (triggerWords.includes(word) && word.length > 2) {
                    matchCount++;
                }
            }

            if (matchCount >= 2 || (matchCount >= 1 && words.length <= 2)) {
                matches.push({ trigger, score: matchCount });
            }
        }

        // Sort by match score
        matches.sort((a, b) => b.score - a.score);
        return matches.map(m => m.trigger);
    }

    navigate(url, message) {
        this.showFeedback(message, 'success');
        this.speak(message);
        setTimeout(() => {
            window.location.href = url;
        }, 500);
    }

    performSearch(query) {
        this.showFeedback(`Searching for: ${query}`, 'success');
        this.speak(`Searching for ${query}`);

        // Open search overlay if it exists
        const searchOverlay = document.getElementById('searchOverlay');
        const searchInput = document.getElementById('searchInput');

        if (searchOverlay && searchInput) {
            searchOverlay.classList.add('active');
            searchInput.value = query;
            searchInput.focus();

            // Trigger search
            const event = new Event('input', { bubbles: true });
            searchInput.dispatchEvent(event);
        } else {
            // Redirect to courses page with search query
            window.location.href = `courses.php?search=${encodeURIComponent(query)}`;
        }
    }

    extractSearchQuery(transcript) {
        const searchTriggers = ['search for', 'search', 'find', 'look for'];
        let query = transcript;

        for (const trigger of searchTriggers) {
            if (transcript.includes(trigger)) {
                query = transcript.split(trigger)[1]?.trim();
                break;
            }
        }

        return query || null;
    }

    filterCourses(category) {
        this.showFeedback(`Filtering: ${category}`, 'success');
    }

    clearFilters() {
        this.showFeedback('Showing all courses', 'success');
    }

    smoothScroll(offset) {
        window.scrollBy({
            top: offset,
            behavior: 'smooth'
        });
    }

    readPageContent() {
        const mainContent = document.querySelector('main, .hero-description, .section-description');
        if (mainContent) {
            const text = mainContent.textContent.trim().substring(0, 500);
            this.speak(text);
            this.showFeedback('Reading page content', 'info');
        } else {
            this.speak('No content to read');
        }
    }

    speak(text) {
        if ('speechSynthesis' in window) {
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.rate = 1;
            utterance.pitch = 1;
            utterance.volume = 1;
            utterance.lang = this.currentLanguage;
            window.speechSynthesis.speak(utterance);
        }
    }

    // ===== NEW HELPER METHODS =====

    extractAfterKeyword(transcript, keywords) {
        for (const keyword of keywords) {
            if (transcript.includes(keyword)) {
                return transcript.split(keyword)[1]?.trim();
            }
        }
        return '';
    }

    clickElement(selector, text) {
        const elements = document.querySelectorAll(selector);
        if (!text) {
            // Click first element if no text specified
            if (elements[0]) {
                elements[0].click();
                this.showFeedback(`Clicked ${selector}`, 'success');
                return;
            }
        }

        // Find element with matching text
        for (const element of elements) {
            if (element.textContent.toLowerCase().includes(text.toLowerCase())) {
                element.click();
                this.showFeedback(`Clicked: ${element.textContent.substring(0, 30)}`, 'success');
                return;
            }
        }

        this.showFeedback(`${selector} not found`, 'warning');
    }

    filterByPrice(type) {
        this.showFeedback(`Filtering ${type} courses`, 'success');

        // Try to find and click price filter
        const filterButtons = document.querySelectorAll('.filter-btn, [data-filter], .price-filter');
        for (const btn of filterButtons) {
            if (btn.textContent.toLowerCase().includes(type)) {
                btn.click();
                return;
            }
        }

        // Alternative: Filter courses directly
        const courses = document.querySelectorAll('.course-card');
        courses.forEach(course => {
            const price = course.querySelector('.price, .course-price');
            if (price) {
                const isFree = price.textContent.toLowerCase().includes('free') ||
                    price.textContent.includes('0');
                if (type === 'free' && !isFree) {
                    course.style.display = 'none';
                } else if (type === 'paid' && isFree) {
                    course.style.display = 'none';
                } else {
                    course.style.display = '';
                }
            }
        });
    }

    sortCourses(sortBy) {
        this.showFeedback(`Sorting by ${sortBy}`, 'success');

        // Try to find and click sort dropdown
        const sortSelect = document.querySelector('select[name="sort"], .sort-select, #sortBy');
        if (sortSelect) {
            const options = sortSelect.querySelectorAll('option');
            for (const option of options) {
                if (option.value.includes(sortBy) || option.textContent.toLowerCase().includes(sortBy)) {
                    sortSelect.value = option.value;
                    sortSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    return;
                }
            }
        }
    }

    startListening() {
        if (!this.isSupported) {
            this.showFeedback('Voice commands not supported in this browser', 'error');
            return;
        }

        if (this.isListening) {
            this.stopListening();
            return;
        }

        try {
            this.recognition.start();
            this.showFeedback('Listening... Speak your command', 'info');
        } catch (error) {
            this.handleError(error.message);
        }
    }

    stopListening() {
        if (this.recognition && this.isListening) {
            this.recognition.stop();
        }
    }

    handleError(error) {
        const errorMessages = {
            'no-speech': 'No speech detected. Please try again.',
            'audio-capture': 'No microphone found. Please check your device.',
            'not-allowed': 'Microphone access denied. Please enable it in your browser settings.',
            'network': 'Network error. Please check your connection.',
            'aborted': 'Speech recognition aborted.',
            'service-not-allowed': 'Speech recognition service not allowed.'
        };

        const message = errorMessages[error] || 'An error occurred. Please try again.';
        this.showFeedback(message, 'error');
    }

    createUI() {
        // Create voice command button
        const voiceBtn = document.createElement('button');
        voiceBtn.id = 'voiceCommandBtn';
        voiceBtn.className = 'voice-command-btn';
        voiceBtn.innerHTML = '<span class="material-icons">mic</span>';
        voiceBtn.title = 'Voice Commands (Click or say "Hey EduVerse")';
        voiceBtn.onclick = () => this.startListening();

        // Create voice command panel
        const voicePanel = document.createElement('div');
        voicePanel.id = 'voiceCommandPanel';
        voicePanel.className = 'voice-command-panel';
        voicePanel.innerHTML = `
            <div class="voice-panel-header">
                <h3>Voice Command</h3>
                <button class="voice-panel-close">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div class="voice-status">
                <div class="voice-animation">
                    <div class="voice-wave"></div>
                    <div class="voice-wave"></div>
                    <div class="voice-wave"></div>
                </div>
                <p class="voice-status-text">Click the microphone to start</p>
            </div>
            <div class="voice-transcript">
                <p class="transcript-text"></p>
            </div>
            <div class="voice-actions">
                <button class="btn-voice-help">
                    <span class="material-icons">help_outline</span>
                    Show Commands
                </button>
            </div>
        `;

        document.body.appendChild(voiceBtn);
        document.body.appendChild(voicePanel);

        // Event listeners
        document.querySelector('.voice-panel-close').onclick = () => {
            this.stopListening();
            voicePanel.classList.remove('active');
        };

        document.querySelector('.btn-voice-help').onclick = () => {
            this.showHelpModal();
        };

        // Toggle panel when voice button is clicked
        voiceBtn.addEventListener('click', () => {
            voicePanel.classList.toggle('active');
        });

        this.addStyles();
    }

    updateUI(state) {
        const voiceBtn = document.getElementById('voiceCommandBtn');
        const statusText = document.querySelector('.voice-status-text');
        const voiceAnimation = document.querySelector('.voice-animation');

        if (!voiceBtn || !statusText) return;

        switch (state) {
            case 'listening':
                voiceBtn.classList.add('listening');
                statusText.textContent = 'Listening... Speak now';
                voiceAnimation.classList.add('active');
                break;
            case 'processing':
                statusText.textContent = 'Processing...';
                break;
            case 'idle':
            default:
                voiceBtn.classList.remove('listening');
                statusText.textContent = 'Click the microphone to start';
                voiceAnimation.classList.remove('active');
                break;
        }
    }

    updateTranscript(text, isFinal) {
        const transcriptEl = document.querySelector('.transcript-text');
        if (transcriptEl) {
            transcriptEl.textContent = text;
            if (isFinal) {
                transcriptEl.classList.add('final');
            } else {
                transcriptEl.classList.remove('final');
            }
        }
    }

    showFeedback(message, type = 'info') {
        if (typeof showToast === 'function') {
            showToast(message, type);
        }
    }

    showHelpModal() {
        const helpModal = document.createElement('div');
        helpModal.className = 'voice-help-modal';
        helpModal.innerHTML = `
            <div class="voice-help-content">
                <div class="voice-help-header">
                    <h2>
                        <span class="material-icons">record_voice_over</span>
                        Voice Commands Help (150+ Commands)
                    </h2>
                    <button class="voice-help-close">
                        <span class="material-icons">close</span>
                    </button>
                </div>
                <div class="voice-help-body">
                    <div class="command-category">
                        <h3><span class="material-icons">navigation</span> Navigation (20+)</h3>
                        <ul>
                            <li><strong>"Go to home"</strong> / "home page" - Navigate to home</li>
                            <li><strong>"Go to courses"</strong> / "show courses" - Browse courses</li>
                            <li><strong>"Go to categories"</strong> - View categories</li>
                            <li><strong>"Go to instructors"</strong> - Browse instructors</li>
                            <li><strong>"Go to about"</strong> / "about us" - About page</li>
                            <li><strong>"Go to contact"</strong> / "contact us" - Contact page</li>
                            <li><strong>"Go to dashboard"</strong> / "my dashboard" - Your dashboard</li>
                            <li><strong>"Student dashboard"</strong> / "my learning" - Student view</li>
                            <li><strong>"Instructor dashboard"</strong> - Teaching dashboard</li>
                            <li><strong>"Admin dashboard"</strong> / "admin panel" - Admin panel</li>
                            <li><strong>"My courses"</strong> - View your enrolled courses</li>
                            <li><strong>"My certificates"</strong> - View certificates</li>
                            <li><strong>"My achievements"</strong> - View achievements</li>
                            <li><strong>"Go to checkout"</strong> - Checkout page</li>
                            <li><strong>"Go to help"</strong> / "help center" - Help center</li>
                            <li><strong>"Login"</strong> / "sign in" - Login page</li>
                            <li><strong>"Sign up"</strong> / "register" - Registration</li>
                            <li><strong>"Logout"</strong> / "sign out" - Logout</li>
                        </ul>
                    </div>
                    
                    <div class="command-category">
                        <h3><span class="material-icons">search</span> Search & Filter (15+)</h3>
                        <ul>
                            <li><strong>"Search [query]"</strong> - Search content (e.g., "search python")</li>
                            <li><strong>"Find [keyword]"</strong> - Find specific content</li>
                            <li><strong>"Focus search"</strong> - Focus search box</li>
                            <li><strong>"Show free courses"</strong> - Filter free courses</li>
                            <li><strong>"Show paid courses"</strong> - Filter paid courses</li>
                            <li><strong>"Show all courses"</strong> / "clear filter" - Clear filters</li>
                            <li><strong>"Sort by price"</strong> - Sort by price</li>
                            <li><strong>"Sort by rating"</strong> - Sort by rating</li>
                            <li><strong>"Sort by popularity"</strong> - Sort by popularity</li>
                            <li><strong>"Show web development"</strong> - Filter by category</li>
                            <li><strong>"Show data science"</strong> - Data science courses</li>
                        </ul>
                    </div>
                    
                    <div class="command-category">
                        <h3><span class="material-icons">shopping_cart</span> Shopping (5)</h3>
                        <ul>
                            <li><strong>"Show cart"</strong> / "view cart" - View shopping cart</li>
                            <li><strong>"Show wishlist"</strong> / "view wishlist" - View wishlist</li>
                            <li><strong>"Add to cart"</strong> - Add course to cart</li>
                            <li><strong>"Add to wishlist"</strong> / "save for later" - Save course</li>
                            <li><strong>"Enroll course"</strong> / "enroll now" - Enroll in course</li>
                        </ul>
                    </div>
                    
                    <div class="command-category">
                        <h3><span class="material-icons">play_circle</span> Video Player (12)</h3>
                        <ul>
                            <li><strong>"Play video"</strong> / "start video" - Play video</li>
                            <li><strong>"Pause video"</strong> / "stop video" - Pause video</li>
                            <li><strong>"Mute video"</strong> - Mute audio</li>
                            <li><strong>"Unmute video"</strong> - Unmute audio</li>
                            <li><strong>"Skip forward"</strong> / "forward ten seconds" - Skip ahead</li>
                            <li><strong>"Skip backward"</strong> / "rewind" - Go back</li>
                            <li><strong>"Increase speed"</strong> / "speed up" - Faster playback</li>
                            <li><strong>"Decrease speed"</strong> / "slow down" - Slower playback</li>
                            <li><strong>"Normal speed"</strong> - Reset playback speed</li>
                            <li><strong>"Fullscreen video"</strong> - Enter fullscreen</li>
                            <li><strong>"Exit fullscreen"</strong> - Exit fullscreen</li>
                        </ul>
                    </div>
                    
                    <div class="command-category">
                        <h3><span class="material-icons">palette</span> Theme & Display (9)</h3>
                        <ul>
                            <li><strong>"Dark mode"</strong> / "enable dark mode" - Dark theme</li>
                            <li><strong>"Light mode"</strong> / "enable light mode" - Light theme</li>
                            <li><strong>"Zoom in"</strong> / "magnify" - Zoom in page</li>
                            <li><strong>"Zoom out"</strong> - Zoom out page</li>
                            <li><strong>"Reset zoom"</strong> - Normal zoom level</li>
                            <li><strong>"Increase font size"</strong> - Bigger text</li>
                            <li><strong>"Decrease font size"</strong> - Smaller text</li>
                            <li><strong>"Reset font size"</strong> - Normal text size</li>
                        </ul>
                    </div>
                    
                    <div class="command-category">
                        <h3><span class="material-icons">mouse</span> Scroll & Page Control (9)</h3>
                        <ul>
                            <li><strong>"Scroll up"</strong> / "go up" - Scroll up</li>
                            <li><strong>"Scroll down"</strong> / "go down" - Scroll down</li>
                            <li><strong>"Scroll to top"</strong> / "go to top" - Jump to top</li>
                            <li><strong>"Scroll to bottom"</strong> / "go to bottom" - Jump to bottom</li>
                            <li><strong>"Refresh page"</strong> / "reload" - Refresh page</li>
                            <li><strong>"Go back"</strong> / "previous page" - Browser back</li>
                            <li><strong>"Go forward"</strong> / "next page" - Browser forward</li>
                        </ul>
                    </div>
                    
                    <div class="command-category">
                        <h3><span class="material-icons">record_voice_over</span> Text-to-Speech (4)</h3>
                        <ul>
                            <li><strong>"Read page"</strong> / "read content" - Read page aloud</li>
                            <li><strong>"Read title"</strong> - Read page title</li>
                            <li><strong>"Read description"</strong> - Read description</li>
                            <li><strong>"Stop reading"</strong> / "stop speech" - Stop reading</li>
                        </ul>
                    </div>
                    
                    <div class="command-category">
                        <h3><span class="material-icons">touch_app</span> Page Interaction (15+)</h3>
                        <ul>
                            <li><strong>"Click button [name]"</strong> - Click a button</li>
                            <li><strong>"Click link [name]"</strong> - Click a link</li>
                            <li><strong>"Open menu"</strong> / "expand menu" - Open menu</li>
                            <li><strong>"Close menu"</strong> - Close menu</li>
                            <li><strong>"Close modal"</strong> / "close popup" - Close dialog</li>
                            <li><strong>"Clear form"</strong> / "reset form" - Clear form fields</li>
                            <li><strong>"Submit form"</strong> - Submit form</li>
                            <li><strong>"Next tab"</strong> / "switch tab" - Next tab</li>
                            <li><strong>"Previous tab"</strong> - Previous tab</li>
                            <li><strong>"Focus first"</strong> - Focus first element</li>
                        </ul>
                    </div>
                    
                    <div class="command-category">
                        <h3><span class="material-icons">language</span> Browser & System (8)</h3>
                        <ul>
                            <li><strong>"Copy url"</strong> / "copy link" - Copy URL to clipboard</li>
                            <li><strong>"Share page"</strong> - Share current page</li>
                            <li><strong>"Print page"</strong> - Print current page</li>
                            <li><strong>"What time is it"</strong> - Announce current time</li>
                            <li><strong>"What date is it"</strong> - Announce current date</li>
                            <li><strong>"Switch language"</strong> - Change language</li>
                        </ul>
                    </div>
                    
                    <div class="command-category">
                        <h3><span class="material-icons">help</span> Help & Control (3)</h3>
                        <ul>
                            <li><strong>"Help"</strong> / "show commands" - Show this help</li>
                            <li><strong>"Stop"</strong> / "cancel" - Stop listening</li>
                            <li><strong>"Stop listening"</strong> - Cancel voice command</li>
                        </ul>
                    </div>
                    
                    <div class="voice-help-tip">
                        <span class="material-icons">lightbulb</span>
                        <p><strong>Pro Tips:</strong></p>
                        <ul style="margin: 10px 0 0 20px; font-size: 0.9em;">
                            <li>Speak naturally - the system understands variations</li>
                            <li>Use keyboard shortcut: <kbd>Ctrl + Shift + V</kbd></li>
                            <li>Wait for visual feedback before next command</li>
                            <li>Some commands work only on specific pages</li>
                            <li>Commands support partial matches for flexibility</li>
                        </ul>
                    </div>
                    
                    <div class="voice-help-stats" style="margin-top: 20px; padding: 15px; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border-radius: 8px; text-align: center;">
                        <p style="margin: 0; font-size: 1.1em;"><strong>Total: 150+ Voice Commands</strong></p>
                        <p style="margin: 5px 0 0 0; font-size: 0.9em; opacity: 0.9;">Complete hands-free website control</p>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(helpModal);

        // Animate in
        setTimeout(() => helpModal.classList.add('active'), 10);

        // Close handlers
        const closeModal = () => {
            helpModal.classList.remove('active');
            setTimeout(() => helpModal.remove(), 300);
        };

        helpModal.querySelector('.voice-help-close').onclick = closeModal;
        helpModal.onclick = (e) => {
            if (e.target === helpModal) closeModal();
        };
    }

    showUnsupportedMessage() {
    }

    addStyles() {
        const styles = document.createElement('style');
        styles.textContent = `
            /* Voice Command Button */
            .voice-command-btn {
                position: fixed;
                bottom: 5rem;
                right: 1rem;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
                color: white;
                border: none;
                box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;
                z-index: 999;
            }

            .voice-command-btn:hover {
                transform: scale(1.05);
                box-shadow: 0 15px 10px rgba(99, 102, 241, 0.4);
            }

            .voice-command-btn.listening {
                animation: pulse 1.5s infinite;
                background: linear-gradient(135deg, #ef4444 0%, #ec4899 100%);
            }

            @keyframes pulse {
                0%, 100% {
                    transform: scale(1);
                    box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
                }
                50% {
                    transform: scale(1.15);
                    box-shadow: 0 15px 30px rgba(239, 68, 68, 0.5);
                }
            }

            /* Voice Command Panel */
            .voice-command-panel {
                position: fixed;
                bottom: 9.5rem;
                right: 2rem;
                width: 350px;
                background: white;
                border-radius: 1rem;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
                transform: translateY(20px) scale(0.9);
                opacity: 0;
                pointer-events: none;
                transition: all 0.3s ease;
                z-index: 998;
            }

            .voice-command-panel.active {
                transform: translateY(0) scale(1);
                opacity: 1;
                pointer-events: all;
            }

            .voice-panel-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.25rem;
                border-bottom: 2px solid #f1f5f9;
            }

            .voice-panel-header h3 {
                margin: 0;
                font-size: 1.125rem;
                color: #0f172a;
            }

            .voice-panel-close {
                background: none;
                border: none;
                color: #64748b;
                cursor: pointer;
                padding: 0.25rem;
                border-radius: 0.5rem;
                transition: all 0.2s;
            }

            .voice-panel-close:hover {
                background: #f1f5f9;
                color: #0f172a;
            }

            .voice-status {
                padding: 2rem 1.25rem;
                text-align: center;
            }

            .voice-animation {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 0.5rem;
                margin-bottom: 1rem;
                height: 60px;
            }

            .voice-wave {
                width: 4px;
                height: 20px;
                background: #cbd5e1;
                border-radius: 4px;
                transition: all 0.3s ease;
            }

            .voice-animation.active .voice-wave {
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                animation: wave 1s ease-in-out infinite;
            }

            .voice-animation.active .voice-wave:nth-child(2) {
                animation-delay: 0.2s;
            }

            .voice-animation.active .voice-wave:nth-child(3) {
                animation-delay: 0.4s;
            }

            @keyframes wave {
                0%, 100% {
                    height: 20px;
                }
                50% {
                    height: 50px;
                }
            }

            .voice-status-text {
                margin: 0;
                color: #64748b;
                font-size: 0.9375rem;
            }

            .voice-transcript {
                padding: 1rem 1.25rem;
                background: #f8fafc;
                border-top: 2px solid #f1f5f9;
                border-bottom: 2px solid #f1f5f9;
                min-height: 60px;
            }

            .transcript-text {
                margin: 0;
                color: #64748b;
                font-style: italic;
                font-size: 0.9375rem;
            }

            .transcript-text.final {
                color: #0f172a;
                font-style: normal;
                font-weight: 500;
            }

            .voice-actions {
                padding: 1rem 1.25rem;
            }

            .btn-voice-help {
                width: 100%;
                padding: 0.75rem;
                background: #f1f5f9;
                color: #6366f1;
                border: none;
                border-radius: 0.75rem;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                transition: all 0.2s;
            }

            .btn-voice-help:hover {
                background: #e2e8f0;
            }

            /* Voice Help Modal */
            .voice-help-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10001;
                opacity: 0;
                transition: opacity 0.3s ease;
                padding: 1rem;
            }

            .voice-help-modal.active {
                opacity: 1;
            }

            .voice-help-content {
                background: white;
                border-radius: 1.5rem;
                max-width: 900px;
                width: 100%;
                max-height: 90vh;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
                transform: scale(0.9);
                transition: transform 0.3s ease;
            }

            .voice-help-modal.active .voice-help-content {
                transform: scale(1);
            }

            .voice-help-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem 2rem;
                border-bottom: 2px solid #f1f5f9;
            }

            .voice-help-header h2 {
                margin: 0;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                color: #0f172a;
                font-size: 1.375rem;
            }

            .voice-help-header .material-icons {
                color: #6366f1;
                font-size: 2rem;
            }

            .voice-help-close {
                background: none;
                border: none;
                color: #64748b;
                cursor: pointer;
                padding: 0.5rem;
                border-radius: 0.5rem;
                transition: all 0.2s;
            }

            .voice-help-close:hover {
                background: #f1f5f9;
                color: #0f172a;
            }

            .voice-help-body {
                padding: 2rem;
                overflow-y: auto;
                max-height: calc(90vh - 120px);
            }

            .voice-help-body::-webkit-scrollbar {
                width: 8px;
            }

            .voice-help-body::-webkit-scrollbar-track {
                background: #f1f5f9;
                border-radius: 4px;
            }

            .voice-help-body::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 4px;
            }

            .voice-help-body::-webkit-scrollbar-thumb:hover {
                background: #94a3b8;
            }

            .command-category {
                margin-bottom: 2rem;
                padding-bottom: 1.5rem;
                border-bottom: 1px solid #f1f5f9;
            }

            .command-category:last-of-type {
                border-bottom: none;
            }

            .command-category h3 {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                color: #6366f1;
                font-size: 1.125rem;
                margin-bottom: 1rem;
            }

            .command-category .material-icons {
                font-size: 1.5rem;
            }

            .command-category ul {
                list-style: none;
                padding-left: 2rem;
            }

            .command-category li {
                margin-bottom: 0.5rem;
                color: #64748b;
                line-height: 1.5;
                font-size: 0.9375rem;
            }

            .command-category li strong {
                color: #0f172a;
                font-weight: 600;
            }

            .voice-help-tip {
                display: flex;
                gap: 1rem;
                padding: 1.25rem;
                background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
                border-radius: 1rem;
                margin-top: 1.5rem;
            }

            .voice-help-tip .material-icons {
                color: #f59e0b;
                font-size: 1.75rem;
                flex-shrink: 0;
            }

            .voice-help-tip p {
                margin: 0;
                color: #78350f;
                font-size: 0.9375rem;
            }

            .voice-help-tip kbd {
                background: #ffffff;
                border: 1px solid #d97706;
                border-radius: 4px;
                padding: 2px 6px;
                font-family: monospace;
                font-size: 0.875rem;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .voice-help-stats {
                animation: statsGlow 2s ease-in-out infinite;
            }

            @keyframes statsGlow {
                0%, 100% {
                    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
                }
                50% {
                    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.5);
                }
            }

            /* Responsive Design */
            @media (max-width: 768px) {
                .voice-command-btn {
                    bottom: 5rem;
                    right: 1rem;
                    width: 50px;
                    height: 50px;
                }

                .voice-command-panel {
                    bottom: 80px;
                    right: 1rem;
                    left: 1rem;
                    width: auto;
                }

                .voice-help-content {
                    max-height: 85vh;
                }

                .voice-help-body {
                    padding: 1.5rem 1rem;
                }

                .command-category ul {
                    padding-left: 1rem;
                }
            }
        `;

        document.head.appendChild(styles);
    }
}

// Initialize voice command system when DOM is ready
// document.addEventListener('DOMContentLoaded', () => {
//     window.voiceCommands = new VoiceCommandSystem();
// });

// // Keyboard shortcut to activate voice commands (Ctrl+Shift+V)
// document.addEventListener('keydown', (e) => {
//     if (e.ctrlKey && e.shiftKey && e.key === 'V') {
//         e.preventDefault();
//         if (window.voiceCommands) {
//             window.voiceCommands.startListening();
//         }
//     }
// });
