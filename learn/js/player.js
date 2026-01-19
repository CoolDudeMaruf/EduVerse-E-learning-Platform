/**
 * Course Player JavaScript
 * Handles video playback, progress tracking, notes, learning time, and navigation
 */

(function () {
    'use strict';

    // State
    const STATE = {
        currentLectureId: CURRENT_LECTURE_ID,
        videoPlayer: null,
        notes: [],
        isCompleted: false,
        sessionStartTime: Date.now(),
        sessionTime: 0,
        totalLearningTime: 0,
        lastProgressSave: 0,
        progressSaveInterval: 15, // Save progress every 15 seconds
        learningTimeInterval: null,
        editingNoteId: null
    };

    // Initialize
    document.addEventListener('DOMContentLoaded', init);

    function init() {
        STATE.videoPlayer = document.getElementById('videoPlayer');

        initTabs();
        initSidebar();
        initVideo();
        initNavigation();
        initNotes();
        initLearningTime();
        loadLectureData();
        loadStats();
    }

    /**
     * AJAX helper
     */
    function ajax(url, options = {}) {
        const method = options.method || 'GET';
        const data = options.data || null;

        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open(method, url, true);

            if (method === 'POST' && !(data instanceof FormData)) {
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            }

            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        resolve(JSON.parse(xhr.responseText));
                    } catch (e) {
                        reject(new Error('Invalid JSON'));
                    }
                } else {
                    reject(new Error(xhr.statusText));
                }
            };

            xhr.onerror = () => reject(new Error('Network error'));

            if (data instanceof FormData) {
                xhr.send(data);
            } else if (data) {
                xhr.send(new URLSearchParams(data).toString());
            } else {
                xhr.send();
            }
        });
    }

    /**
     * Initialize tabs
     */
    function initTabs() {
        const tabBtns = document.querySelectorAll('.tab-btn');
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                const tabId = this.dataset.tab;

                // Update buttons
                tabBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                // Update content
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                const tabContent = document.getElementById('tab-' + tabId);
                if (tabContent) tabContent.classList.add('active');
            });
        });
    }

    /**
     * Initialize sidebar
     */
    function initSidebar() {
        const sidebar = document.getElementById('lectureSidebar');
        const toggleBtn = document.getElementById('btnToggleSidebar');
        const closeBtn = document.getElementById('btnCloseSidebar');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', () => sidebar.classList.remove('open'));
        }

        // Search lectures
        const searchInput = document.getElementById('searchLectures');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const query = this.value.toLowerCase();
                document.querySelectorAll('.lecture-item').forEach(item => {
                    const name = item.querySelector('.lecture-name').textContent.toLowerCase();
                    item.style.display = name.includes(query) ? '' : 'none';
                });
            });
        }
    }

    /**
     * Initialize video player
     */
    function initVideo() {
        if (!STATE.videoPlayer) return;

        // Track progress with debouncing (only for enrolled students)
        let lastSaveTime = 0;
        STATE.videoPlayer.addEventListener('timeupdate', function () {
            const currentTime = Math.floor(this.currentTime);

            // Save progress every 15 seconds (debounced)
            if (!IS_PREVIEW_MODE && currentTime > 0 && currentTime - lastSaveTime >= STATE.progressSaveInterval) {
                lastSaveTime = currentTime;
                saveProgress(currentTime);
            }
        });

        // Auto mark complete when video ends (only for enrolled students)
        STATE.videoPlayer.addEventListener('ended', function () {
            if (!IS_PREVIEW_MODE) {
                markComplete();
            }
        });

        // Pause learning time when video pauses
        STATE.videoPlayer.addEventListener('pause', function () {
            // Learning time continues but we could track active time here
        });
    }

    /**
     * Initialize learning time tracking
     */
    function initLearningTime() {
        if (IS_PREVIEW_MODE) return;

        // Update session timer every second
        STATE.learningTimeInterval = setInterval(() => {
            STATE.sessionTime = Math.floor((Date.now() - STATE.sessionStartTime) / 1000);
            updateLearningTimeDisplay();
        }, 1000);

        // Save learning time periodically (every 60 seconds)
        setInterval(() => {
            if (STATE.sessionTime > 0) {
                saveLearningTime();
            }
        }, 60000);

        // Save learning time when leaving page
        window.addEventListener('beforeunload', () => {
            if (STATE.sessionTime > 0) {
                // Use sendBeacon for reliable delivery
                const formData = new FormData();
                formData.append('action', 'update_learning_time');
                formData.append('course_id', COURSE_ID);
                formData.append('session_time', STATE.sessionTime);
                navigator.sendBeacon(AJAX_BASE + 'player-handler.php', formData);
            }
        });
    }

    /**
     * Update learning time display
     */
    function updateLearningTimeDisplay() {
        const sessionEl = document.getElementById('sessionTime');
        const totalEl = document.getElementById('totalLearningTime');

        if (sessionEl) {
            sessionEl.textContent = formatDuration(STATE.sessionTime);
        }
        if (totalEl) {
            totalEl.textContent = formatDuration(STATE.totalLearningTime + STATE.sessionTime);
        }
    }

    /**
     * Save learning time to server
     */
    function saveLearningTime() {
        const sessionTime = STATE.sessionTime;

        const formData = new FormData();
        formData.append('action', 'update_learning_time');
        formData.append('course_id', COURSE_ID);
        formData.append('session_time', sessionTime);

        ajax(AJAX_BASE + 'player-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success && !response.preview_mode) {
                    STATE.totalLearningTime = response.total_time;
                    STATE.sessionStartTime = Date.now();
                    STATE.sessionTime = 0;
                }
            })
            .catch(() => { }); // Silent fail
    }

    /**
     * Initialize navigation
     */
    function initNavigation() {
        const prevBtn = document.getElementById('btnPrevLecture');
        const nextBtn = document.getElementById('btnNextLecture');
        const completeBtn = document.getElementById('btnMarkComplete');

        if (prevBtn) {
            prevBtn.addEventListener('click', () => navigateLecture('prev'));
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => navigateLecture('next'));
        }

        if (completeBtn) {
            if (IS_PREVIEW_MODE) {
                completeBtn.classList.add('preview-mode');
                completeBtn.innerHTML = '<span class="material-icons">visibility</span><span>Preview Mode</span>';
                completeBtn.disabled = true;
                completeBtn.title = 'Progress is not tracked in preview mode';
            } else {
                completeBtn.addEventListener('click', () => toggleComplete());
            }
        }
    }

    /**
     * Initialize notes
     */
    function initNotes() {
        const addBtn = document.getElementById('btnAddNote');
        if (addBtn) {
            addBtn.addEventListener('click', () => openNoteModal());
        }
    }

    /**
     * Toggle section
     */
    window.toggleSection = function (header) {
        const section = header.closest('.section-item');
        section.classList.toggle('open');
    };

    /**
     * Load lecture
     */
    window.loadLecture = function (lectureId) {
        lectureId = String(lectureId);

        if (lectureId === String(STATE.currentLectureId)) {
            return;
        }

        // Update URL
        const url = new URL(window.location);
        url.searchParams.set('lecture', lectureId);
        window.history.pushState({}, '', url);

        STATE.currentLectureId = lectureId;

        // Update active state in sidebar
        document.querySelectorAll('.lecture-item').forEach(item => {
            item.classList.remove('active');
            if (String(item.dataset.lectureId) === lectureId) {
                item.classList.add('active');
            }
        });

        // Fetch lecture data
        loadLectureData();
    };

    /**
     * Load lecture data via AJAX
     */
    function loadLectureData() {
        if (!STATE.currentLectureId) return;

        ajax(AJAX_BASE + 'player-handler.php?action=get_lecture&lecture_id=' + STATE.currentLectureId + '&course_id=' + COURSE_ID)
            .then(response => {
                if (response.success) {
                    updatePlayerUI(response.lecture);
                    if (response.total_learning_time !== undefined) {
                        STATE.totalLearningTime = response.total_learning_time;
                        updateLearningTimeDisplay();
                    }
                    loadNotes();
                    loadResources();
                } else {
                    console.error('Lecture load failed:', response.error);
                }
            })
            .catch(err => console.error('Failed to load lecture:', err));
    }

    /**
     * Load stats
     */
    function loadStats() {
        if (IS_PREVIEW_MODE) return;

        ajax(AJAX_BASE + 'player-handler.php?action=get_stats&course_id=' + COURSE_ID)
            .then(response => {
                if (response.success && response.stats) {
                    STATE.totalLearningTime = response.stats.total_learning_time || 0;
                    updateLearningTimeDisplay();
                    updateProgress({
                        completed: response.stats.completed_lectures,
                        total: response.stats.total_lectures,
                        percent: response.stats.progress_percent
                    });
                }
            })
            .catch(() => { });
    }

    /**
     * Update player UI with lecture data
     */
    function updatePlayerUI(lecture) {
        // Update title
        const titleEl = document.getElementById('lectureTitle');
        const navTitle = document.getElementById('currentLectureTitle');
        if (titleEl) titleEl.textContent = lecture.title;
        if (navTitle) navTitle.textContent = lecture.title;

        // Update video
        const videoPlaceholder = document.querySelector('.player-placeholder');

        if (STATE.videoPlayer) {
            let videoSrc = '';
            let thumbnailSrc = '';

            if (lecture.video_url) {
                if (lecture.video_source === 'upload') {
                    videoSrc = BASE_URL + lecture.video_url;
                } else {
                    videoSrc = lecture.video_url;
                }
            }

            if (lecture.thumbnail_url) {
                thumbnailSrc = BASE_URL + lecture.thumbnail_url;
            }

            if (videoSrc) {
                STATE.videoPlayer.src = videoSrc;
                STATE.videoPlayer.poster = thumbnailSrc || '';
                STATE.videoPlayer.load();
                STATE.videoPlayer.style.display = 'block';

                // Seek to saved position
                if (lecture.watch_time && lecture.watch_time > 0) {
                    STATE.videoPlayer.currentTime = lecture.watch_time;
                }

                // Auto-play
                STATE.videoPlayer.play().catch(() => {
                    const bigPlayBtn = document.getElementById('bigPlayBtn');
                    if (bigPlayBtn) bigPlayBtn.classList.remove('hidden');
                });

                if (videoPlaceholder) videoPlaceholder.style.display = 'none';
            } else {
                STATE.videoPlayer.style.display = 'none';
                if (videoPlaceholder) {
                    videoPlaceholder.style.display = 'flex';
                }
            }
        }

        // Update description
        const descContainer = document.querySelector('.lecture-description');
        if (descContainer) {
            if (lecture.description) {
                descContainer.innerHTML = `
                    <h3>About this lecture</h3>
                    <p>${escapeHtml(lecture.description).replace(/\n/g, '<br>')}</p>
                `;
                descContainer.style.display = 'block';
            } else {
                descContainer.style.display = 'none';
            }
        }

        // Update learning objectives
        let objectivesContainer = document.querySelector('.learning-objectives');
        const tabPanel = document.querySelector('#tab-overview .tab-panel');

        if (lecture.learning_objectives && lecture.learning_objectives.length > 0) {
            const objectivesHtml = `
                <div class="learning-objectives">
                    <h3>Learning Objectives</h3>
                    <ul class="objectives-list">
                        ${lecture.learning_objectives.map(obj => `
                            <li>
                                <span class="material-icons">check_circle</span>
                                ${escapeHtml(obj)}
                            </li>
                        `).join('')}
                    </ul>
                </div>
            `;

            if (objectivesContainer) {
                objectivesContainer.outerHTML = objectivesHtml;
            } else if (tabPanel) {
                tabPanel.insertAdjacentHTML('beforeend', objectivesHtml);
            }
        } else if (objectivesContainer) {
            objectivesContainer.remove();
        }

        // Update video source badge
        const lectureMeta = document.querySelector('.lecture-meta');
        let sourceBadge = document.querySelector('.video-source-badge');

        if (lecture.video_source) {
            const isUpload = lecture.video_source === 'upload';
            const badgeHtml = `
                <span class="video-source-badge">
                    <span class="material-icons">${isUpload ? 'cloud_upload' : 'link'}</span>
                    ${isUpload ? 'Uploaded' : 'Embedded'}
                </span>
            `;

            if (sourceBadge) {
                sourceBadge.outerHTML = badgeHtml;
            } else if (lectureMeta) {
                lectureMeta.insertAdjacentHTML('beforeend', badgeHtml);
            }
        } else if (sourceBadge) {
            sourceBadge.remove();
        }

        // Update complete button
        STATE.isCompleted = lecture.is_completed;
        updateCompleteButton();
    }

    /**
     * Update complete button state
     */
    function updateCompleteButton() {
        const btn = document.getElementById('btnMarkComplete');
        if (!btn) return;

        if (IS_PREVIEW_MODE) {
            btn.classList.add('preview-mode');
            btn.innerHTML = '<span class="material-icons">visibility</span><span>Preview Mode</span>';
            btn.disabled = true;
            return;
        }

        if (STATE.isCompleted) {
            btn.classList.add('completed');
            btn.innerHTML = '<span class="material-icons">check_circle</span><span>Completed</span>';
        } else {
            btn.classList.remove('completed');
            btn.innerHTML = '<span class="material-icons">check_circle_outline</span><span>Mark as Complete</span>';
        }
    }

    /**
     * Navigate to prev/next lecture
     */
    function navigateLecture(direction) {
        const items = Array.from(document.querySelectorAll('.lecture-item'));
        const currentIndex = items.findIndex(item => item.dataset.lectureId === STATE.currentLectureId);

        let targetIndex = direction === 'prev' ? currentIndex - 1 : currentIndex + 1;

        if (targetIndex >= 0 && targetIndex < items.length) {
            const targetLectureId = items[targetIndex].dataset.lectureId;
            loadLecture(targetLectureId);
        }
    }

    window.navigateLecture = navigateLecture;

    /**
     * Toggle lecture complete status
     */
    function toggleComplete() {
        if (IS_PREVIEW_MODE) return;

        const newStatus = !STATE.isCompleted;

        const formData = new FormData();
        formData.append('action', 'toggle_complete');
        formData.append('lecture_id', STATE.currentLectureId);
        formData.append('course_id', COURSE_ID);
        formData.append('is_complete', newStatus ? 1 : 0);

        ajax(AJAX_BASE + 'player-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    STATE.isCompleted = response.is_completed;
                    updateCompleteButton();

                    // Update sidebar
                    const item = document.querySelector(`.lecture-item[data-lecture-id="${STATE.currentLectureId}"]`);
                    if (item) {
                        if (STATE.isCompleted) {
                            item.classList.add('completed');
                            item.querySelector('.lecture-status').innerHTML = '<span class="material-icons">check_circle</span>';
                        } else {
                            item.classList.remove('completed');
                            item.querySelector('.lecture-status').innerHTML = '<span class="material-icons">play_circle_outline</span>';
                        }
                    }

                    // Update progress
                    updateProgress(response.progress);

                    // Check for course completion
                    if (response.course_completed) {
                        showCompletionCelebration();
                    }
                }
            });
    }

    /**
     * Mark lecture as complete (legacy, for video end)
     */
    function markComplete() {
        if (IS_PREVIEW_MODE || STATE.isCompleted) return;

        const formData = new FormData();
        formData.append('action', 'mark_complete');
        formData.append('lecture_id', STATE.currentLectureId);
        formData.append('course_id', COURSE_ID);

        ajax(AJAX_BASE + 'player-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    STATE.isCompleted = true;
                    updateCompleteButton();

                    const item = document.querySelector(`.lecture-item[data-lecture-id="${STATE.currentLectureId}"]`);
                    if (item) {
                        item.classList.add('completed');
                        item.querySelector('.lecture-status').innerHTML = '<span class="material-icons">check_circle</span>';
                    }

                    updateProgress(response.progress);

                    if (response.course_completed) {
                        showCompletionCelebration();
                    }
                }
            });
    }

    /**
     * Show course completion celebration
     */
    function showCompletionCelebration() {
        const modal = document.getElementById('completionModal');
        if (modal) {
            modal.classList.add('active');
        } else {
            // Create simple celebration notification
            const notification = document.createElement('div');
            notification.className = 'completion-notification';
            notification.innerHTML = `
                <div class="completion-content">
                    <span class="material-icons">emoji_events</span>
                    <h3>Congratulations!</h3>
                    <p>You've completed this course!</p>
                    <button onclick="this.parentElement.parentElement.remove()">Continue</button>
                </div>
            `;
            document.body.appendChild(notification);

            setTimeout(() => notification.classList.add('show'), 100);
        }
    }

    /**
     * Save video progress
     */
    function saveProgress(watchTime) {
        if (IS_PREVIEW_MODE) return;

        const formData = new FormData();
        formData.append('action', 'save_progress');
        formData.append('lecture_id', STATE.currentLectureId);
        formData.append('course_id', COURSE_ID);
        formData.append('watch_time', Math.floor(watchTime));

        ajax(AJAX_BASE + 'player-handler.php', { method: 'POST', data: formData })
            .catch(() => { });
    }

    /**
     * Update progress display
     */
    function updateProgress(progress) {
        const completedEl = document.getElementById('completedCount');
        const percentEl = document.getElementById('progressPercent');
        const progressBar = document.getElementById('sidebarProgress');
        const miniProgress = document.querySelector('.mini-progress-fill');
        const navProgress = document.querySelector('.progress-info span');

        if (completedEl) completedEl.textContent = progress.completed;
        if (percentEl) percentEl.textContent = progress.percent + '%';
        if (progressBar) progressBar.style.width = progress.percent + '%';
        if (miniProgress) miniProgress.style.width = progress.percent + '%';
        if (navProgress && progress.total) {
            navProgress.textContent = progress.completed + ' / ' + progress.total + ' lectures';
        }
    }

    /**
     * Load notes
     */
    function loadNotes() {
        ajax(AJAX_BASE + 'player-handler.php?action=get_notes&lecture_id=' + STATE.currentLectureId)
            .then(response => {
                if (response.success) {
                    STATE.notes = response.notes || [];
                    renderNotes(STATE.notes);
                }
            });
    }

    /**
     * Render notes
     */
    function renderNotes(notes) {
        const container = document.getElementById('notesList');
        if (!container) return;

        if (notes.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="material-icons">note_add</span>
                    <p>No notes yet</p>
                    <p class="subtitle">Take notes while watching to remember key points</p>
                </div>
            `;
            return;
        }

        container.innerHTML = notes.map(note => `
            <div class="note-item" data-note-id="${note.note_id}">
                <div class="note-timestamp">
                    <button onclick="seekTo(${note.timestamp})">
                        <span class="material-icons">play_circle</span>
                        <span>${formatTime(note.timestamp)}</span>
                    </button>
                </div>
                <div class="note-content">
                    <p>${escapeHtml(note.content)}</p>
                    <div class="note-actions">
                        <button onclick="editNote('${note.note_id}')">
                            <span class="material-icons">edit</span>
                            Edit
                        </button>
                        <button onclick="deleteNote('${note.note_id}')">
                            <span class="material-icons">delete</span>
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    /**
     * Load resources
     */
    function loadResources() {
        ajax(AJAX_BASE + 'player-handler.php?action=get_resources&lecture_id=' + STATE.currentLectureId)
            .then(response => {
                if (response.success) {
                    renderResources(response.resources || []);
                }
            });
    }

    /**
     * Render resources
     */
    function renderResources(resources) {
        const container = document.getElementById('resourcesList');
        if (!container) return;

        if (resources.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <span class="material-icons">folder_open</span>
                    <p>No resources for this lecture</p>
                </div>
            `;
            return;
        }

        container.innerHTML = resources.map(res => {
            let resourceUrl = res.file_url;
            if (res.file_type !== 'link' && !res.file_url.startsWith('http')) {
                resourceUrl = BASE_URL + res.file_url;
            }

            return `
                <a href="${resourceUrl}" class="resource-item" target="_blank" ${res.file_type !== 'link' ? 'download' : ''}>
                    <div class="resource-icon">
                        <span class="material-icons">${getResourceIcon(res.file_type)}</span>
                    </div>
                    <div class="resource-info">
                        <h4>${escapeHtml(res.title)}</h4>
                        <span>${res.file_type.toUpperCase()} ${res.file_size ? '• ' + res.file_size : ''}</span>
                    </div>
                </a>
            `;
        }).join('');
    }

    /**
     * Get resource icon
     */
    function getResourceIcon(type) {
        const icons = {
            'pdf': 'picture_as_pdf',
            'zip': 'folder_zip',
            'doc': 'description',
            'docx': 'description',
            'ppt': 'slideshow',
            'pptx': 'slideshow',
            'xls': 'table_chart',
            'xlsx': 'table_chart',
            'link': 'link'
        };
        return icons[type] || 'insert_drive_file';
    }

    /**
     * Open note modal
     */
    function openNoteModal(editNote = null) {
        const modal = document.getElementById('noteModal');
        const timestampEl = document.getElementById('noteTimestamp');
        const noteTextEl = document.getElementById('noteText');
        const modalTitle = modal.querySelector('.modal-header h3');

        if (editNote) {
            STATE.editingNoteId = editNote.note_id;
            modalTitle.textContent = 'Edit Note';
            timestampEl.textContent = formatTime(editNote.timestamp);
            noteTextEl.value = editNote.content;
        } else {
            STATE.editingNoteId = null;
            modalTitle.textContent = 'Add Note';
            if (STATE.videoPlayer) {
                timestampEl.textContent = formatTime(STATE.videoPlayer.currentTime);
            }
            noteTextEl.value = '';
        }

        modal.classList.add('active');
        noteTextEl.focus();
    }

    /**
     * Close modal
     */
    window.closeModal = function (modalId) {
        document.getElementById(modalId).classList.remove('active');
        STATE.editingNoteId = null;
    };

    /**
     * Save note (add or update)
     */
    window.saveNote = function () {
        const noteText = document.getElementById('noteText').value.trim();
        if (!noteText) return;

        const formData = new FormData();

        if (STATE.editingNoteId) {
            // Update existing note
            formData.append('action', 'update_note');
            formData.append('note_id', STATE.editingNoteId);
            formData.append('content', noteText);
        } else {
            // Add new note
            const timestamp = STATE.videoPlayer ? Math.floor(STATE.videoPlayer.currentTime) : 0;
            formData.append('action', 'add_note');
            formData.append('lecture_id', STATE.currentLectureId);
            formData.append('course_id', COURSE_ID);
            formData.append('content', noteText);
            formData.append('timestamp', timestamp);
        }

        ajax(AJAX_BASE + 'player-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    closeModal('noteModal');
                    document.getElementById('noteText').value = '';
                    loadNotes();
                }
            });
    };

    /**
     * Edit note
     */
    window.editNote = function (noteId) {
        const note = STATE.notes.find(n => String(n.note_id) === String(noteId));
        if (note) {
            openNoteModal(note);
        }
    };

    /**
     * Delete note
     */
    window.deleteNote = function (noteId) {
        if (!confirm('Delete this note?')) return;

        const formData = new FormData();
        formData.append('action', 'delete_note');
        formData.append('note_id', noteId);

        ajax(AJAX_BASE + 'player-handler.php', { method: 'POST', data: formData })
            .then(response => {
                if (response.success) {
                    loadNotes();
                }
            });
    };

    /**
     * Seek to timestamp
     */
    window.seekTo = function (seconds) {
        if (STATE.videoPlayer) {
            STATE.videoPlayer.currentTime = seconds;
            STATE.videoPlayer.play();
        }
    };

    /**
     * Format time (seconds to mm:ss)
     */
    function formatTime(seconds) {
        seconds = Math.floor(seconds || 0);
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    /**
     * Format duration (seconds to hh:mm:ss or mm:ss)
     */
    function formatDuration(seconds) {
        seconds = Math.floor(seconds || 0);
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;

        if (h > 0) {
            return h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})();
