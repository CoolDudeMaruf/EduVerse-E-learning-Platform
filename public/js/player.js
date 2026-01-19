/* ===================================
   Video Player JavaScript
   =================================== */

// Get URL parameters
function getURLParams() {
    const params = new URLSearchParams(window.location.search);
    return {
        courseId: params.get('course') || '1',
        lectureId: params.get('lecture') || '1'
    };
}

const currentParams = getURLParams();

// ===================================
// Video Player Controls
// ===================================
let video, progressContainer, progressFilled, progressBuffer, progressTooltip;
let isPlaying = false;
let isSeeking = false;

function initVideoPlayer() {
    video = document.getElementById('videoPlayer');
    progressContainer = document.getElementById('progressContainer');
    progressFilled = document.getElementById('progressFilled');
    progressBuffer = document.getElementById('progressBuffer');
    progressTooltip = document.getElementById('progressTooltip');
    
    if (!video) return;
    
    // Play/Pause
    const btnPlayPause = document.getElementById('btnPlayPause');
    btnPlayPause.addEventListener('click', togglePlay);
    
    video.addEventListener('click', togglePlay);
    video.addEventListener('play', () => {
        isPlaying = true;
        btnPlayPause.querySelector('.material-icons').textContent = 'pause';
    });
    
    video.addEventListener('pause', () => {
        isPlaying = false;
        btnPlayPause.querySelector('.material-icons').textContent = 'play_arrow';
    });
    
    // Rewind/Forward
    document.getElementById('btnRewind').addEventListener('click', () => {
        video.currentTime = Math.max(0, video.currentTime - 10);
    });
    
    document.getElementById('btnForward').addEventListener('click', () => {
        video.currentTime = Math.min(video.duration, video.currentTime + 10);
    });
    
    // Volume
    const btnVolume = document.getElementById('btnVolume');
    const volumeSlider = document.getElementById('volumeSlider');
    
    volumeSlider.addEventListener('input', (e) => {
        const volume = e.target.value / 100;
        video.volume = volume;
        updateVolumeIcon(volume);
    });
    
    btnVolume.addEventListener('click', () => {
        video.muted = !video.muted;
        updateVolumeIcon(video.muted ? 0 : video.volume);
    });
    
    // Progress
    video.addEventListener('timeupdate', updateProgress);
    video.addEventListener('progress', updateBuffer);
    progressContainer.addEventListener('click', seek);
    progressContainer.addEventListener('mousemove', showProgressTooltip);
    
    // Duration
    video.addEventListener('loadedmetadata', () => {
        document.getElementById('duration').textContent = formatTime(video.duration);
    });
    
    // Fullscreen
    document.getElementById('btnFullscreen').addEventListener('click', toggleFullscreen);
    
    // Speed
    document.getElementById('btnSpeed').addEventListener('click', () => {
        toggleMenu('speedMenu');
    });
    
    document.querySelectorAll('#speedMenu .menu-item').forEach(item => {
        item.addEventListener('click', () => {
            const speed = parseFloat(item.dataset.speed);
            video.playbackRate = speed;
            document.getElementById('btnSpeed').querySelector('span').textContent = speed + 'x';
            
            // Update active state
            document.querySelectorAll('#speedMenu .menu-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            
            toggleMenu('speedMenu');
        });
    });
    
    // Quality (placeholder - would require multiple video sources)
    document.getElementById('btnQuality').addEventListener('click', () => {
        toggleMenu('qualityMenu');
    });
    
    document.querySelectorAll('#qualityMenu .menu-item').forEach(item => {
        item.addEventListener('click', () => {
            const quality = item.dataset.quality;
            
            document.querySelectorAll('#qualityMenu .menu-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            
            toggleMenu('qualityMenu');
            showToast(`Quality set to ${quality}`, 'success');
        });
    });
    
    // Captions
    document.getElementById('btnCaptions').addEventListener('click', toggleCaptions);
    
    // Keyboard shortcuts
    document.addEventListener('keydown', handleKeyboardShortcuts);
    
    // Auto-hide controls
    let hideControlsTimeout;
    const videoOverlay = document.getElementById('videoOverlay');
    
    document.querySelector('.video-container').addEventListener('mousemove', () => {
        videoOverlay.classList.add('show');
        clearTimeout(hideControlsTimeout);
        
        if (isPlaying) {
            hideControlsTimeout = setTimeout(() => {
                videoOverlay.classList.remove('show');
            }, 3000);
        }
    });
    
    document.querySelector('.video-container').addEventListener('mouseleave', () => {
        if (isPlaying) {
            videoOverlay.classList.remove('show');
        }
    });
    
    // Load saved playback position
    loadPlaybackPosition();
    
    // Save position periodically
    setInterval(savePlaybackPosition, 5000);
    
    // Save on page unload
    window.addEventListener('beforeunload', savePlaybackPosition);
}

function togglePlay() {
    if (video.paused) {
        video.play();
    } else {
        video.pause();
    }
}

function updateProgress() {
    const progress = (video.currentTime / video.duration) * 100;
    progressFilled.style.width = progress + '%';
    document.getElementById('currentTime').textContent = formatTime(video.currentTime);
}

function updateBuffer() {
    if (video.buffered.length > 0) {
        const buffered = (video.buffered.end(video.buffered.length - 1) / video.duration) * 100;
        progressBuffer.style.width = buffered + '%';
    }
}

function seek(e) {
    const rect = progressContainer.getBoundingClientRect();
    const pos = (e.clientX - rect.left) / rect.width;
    video.currentTime = pos * video.duration;
}

function showProgressTooltip(e) {
    const rect = progressContainer.getBoundingClientRect();
    const pos = (e.clientX - rect.left) / rect.width;
    const time = pos * video.duration;
    
    progressTooltip.textContent = formatTime(time);
    progressTooltip.style.left = e.clientX - rect.left + 'px';
}

function updateVolumeIcon(volume) {
    const icon = document.querySelector('#btnVolume .material-icons');
    
    if (volume === 0) {
        icon.textContent = 'volume_off';
    } else if (volume < 0.5) {
        icon.textContent = 'volume_down';
    } else {
        icon.textContent = 'volume_up';
    }
}

function toggleFullscreen() {
    const container = document.querySelector('.video-container');
    
    if (!document.fullscreenElement) {
        container.requestFullscreen();
        document.getElementById('btnFullscreen').querySelector('.material-icons').textContent = 'fullscreen_exit';
    } else {
        document.exitFullscreen();
        document.getElementById('btnFullscreen').querySelector('.material-icons').textContent = 'fullscreen';
    }
}

function toggleCaptions() {
    const track = video.textTracks[0];
    const btn = document.getElementById('btnCaptions');
    
    if (track.mode === 'showing') {
        track.mode = 'hidden';
        btn.style.opacity = '0.6';
    } else {
        track.mode = 'showing';
        btn.style.opacity = '1';
    }
}

function toggleMenu(menuId) {
    const menu = document.getElementById(menuId);
    const allMenus = document.querySelectorAll('.video-menu');
    
    allMenus.forEach(m => {
        if (m.id !== menuId) {
            m.classList.remove('show');
        }
    });
    
    menu.classList.toggle('show');
}

function handleKeyboardShortcuts(e) {
    // Ignore if typing in input
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    
    switch(e.key) {
        case ' ':
        case 'k':
            e.preventDefault();
            togglePlay();
            break;
        case 'ArrowLeft':
            e.preventDefault();
            video.currentTime -= 5;
            break;
        case 'ArrowRight':
            e.preventDefault();
            video.currentTime += 5;
            break;
        case 'j':
            e.preventDefault();
            video.currentTime -= 10;
            break;
        case 'l':
            e.preventDefault();
            video.currentTime += 10;
            break;
        case 'f':
            e.preventDefault();
            toggleFullscreen();
            break;
        case 'm':
            e.preventDefault();
            video.muted = !video.muted;
            break;
        case 'ArrowUp':
            e.preventDefault();
            video.volume = Math.min(1, video.volume + 0.1);
            document.getElementById('volumeSlider').value = video.volume * 100;
            break;
        case 'ArrowDown':
            e.preventDefault();
            video.volume = Math.max(0, video.volume - 0.1);
            document.getElementById('volumeSlider').value = video.volume * 100;
            break;
        case 'c':
            e.preventDefault();
            toggleCaptions();
            break;
    }
}

function formatTime(seconds) {
    if (isNaN(seconds)) return '0:00';
    
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    
    if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    return `${minutes}:${secs.toString().padStart(2, '0')}`;
}

// ===================================
// Playback Position Save/Load
// ===================================
function savePlaybackPosition() {
    if (!video || isNaN(video.currentTime)) return;
    
    const key = `playback_${currentParams.courseId}_${currentParams.lectureId}`;
    const data = {
        position: video.currentTime,
        duration: video.duration,
        timestamp: Date.now()
    };
    
    localStorage.setItem(key, JSON.stringify(data));
}

function loadPlaybackPosition() {
    const key = `playback_${currentParams.courseId}_${currentParams.lectureId}`;
    const saved = localStorage.getItem(key);
    
    if (saved) {
        const data = JSON.parse(saved);
        
        // Only resume if video is the same duration and not too old (7 days)
        const daysSince = (Date.now() - data.timestamp) / (1000 * 60 * 60 * 24);
        
        if (daysSince < 7 && Math.abs(data.duration - video.duration) < 1) {
            video.currentTime = data.position;
            
            // Show toast
            if (data.position > 10) {
                showToast(`Resumed from ${formatTime(data.position)}`, 'info');
            }
        }
    }
}

// ===================================
// Sidebar & Lecture Navigation
// ===================================
function initSidebar() {
    // Toggle sections
    document.querySelectorAll('.section-toggle').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const section = btn.closest('.section-item');
            section.classList.toggle('open');
        });
    });
    
    // Lecture items
    document.querySelectorAll('.lecture-item:not(.locked)').forEach(item => {
        item.addEventListener('click', () => {
            const lectureId = item.dataset.lectureId;
            loadLecture(currentParams.courseId, lectureId);
        });
    });
    
    // Search lectures
    const searchInput = document.getElementById('searchLectures');
    searchInput.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        
        document.querySelectorAll('.lecture-item').forEach(item => {
            const name = item.querySelector('.lecture-name').textContent.toLowerCase();
            
            if (name.includes(term)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    });
    
    // Mobile toggle
    const btnCloseSidebar = document.getElementById('btnCloseSidebar');
    if (btnCloseSidebar) {
        btnCloseSidebar.addEventListener('click', () => {
            document.querySelector('.lecture-sidebar').classList.remove('show');
        });
    }
    
    // Previous/Next buttons
    document.getElementById('btnPrevLecture').addEventListener('click', navigateLecture('prev'));
    document.getElementById('btnNextLecture').addEventListener('click', navigateLecture('next'));
}

function loadLecture(courseId, lectureId) {
    // Save current position before changing
    savePlaybackPosition();
    
    // Update URL
    const url = new URL(window.location);
    url.searchParams.set('course', courseId);
    url.searchParams.set('lecture', lectureId);
    window.history.pushState({}, '', url);
    
    // In production, load new video and lecture data
    showToast('Loading lecture...', 'info');
    
    // Update active state
    document.querySelectorAll('.lecture-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.lectureId === lectureId) {
            item.classList.add('active');
        }
    });
    
    // Reload page to simulate lecture change (in production, dynamically update content)
    setTimeout(() => {
        location.reload();
    }, 500);
}

function navigateLecture(direction) {
    return () => {
        const lectures = Array.from(document.querySelectorAll('.lecture-item:not(.locked)'));
        const currentIndex = lectures.findIndex(item => item.classList.contains('active'));
        
        let nextIndex;
        if (direction === 'next') {
            nextIndex = currentIndex + 1;
        } else {
            nextIndex = currentIndex - 1;
        }
        
        if (nextIndex >= 0 && nextIndex < lectures.length) {
            const nextLecture = lectures[nextIndex];
            const lectureId = nextLecture.dataset.lectureId;
            loadLecture(currentParams.courseId, lectureId);
        }
    };
}

// ===================================
// Tabs
// ===================================
function initTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.dataset.tab;
            
            tabButtons.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            btn.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
}

// ===================================
// Notes Management
// ===================================
let notes = [];

function initNotes() {
    loadNotes();
    
    const btnAddNote = document.getElementById('btnAddNote');
    const btnNotes = document.getElementById('btnNotes');
    
    btnAddNote.addEventListener('click', openNoteModal);
    
    if (btnNotes) {
        btnNotes.addEventListener('click', () => {
            // Switch to notes tab
            document.querySelector('.tab-btn[data-tab="notes"]').click();
        });
    }
    
    // Edit/Delete notes
    document.addEventListener('click', (e) => {
        if (e.target.closest('.btn-edit-note')) {
            const noteItem = e.target.closest('.note-item');
            const noteId = parseInt(noteItem.dataset.noteId);
            editNote(noteId);
        }
        
        if (e.target.closest('.btn-delete-note')) {
            const noteItem = e.target.closest('.note-item');
            const noteId = parseInt(noteItem.dataset.noteId);
            deleteNote(noteId);
        }
        
        if (e.target.closest('.btn-timestamp')) {
            const time = parseFloat(e.target.closest('.btn-timestamp').dataset.time);
            video.currentTime = time;
            video.play();
        }
    });
    
    renderNotes();
}

function openNoteModal(editId = null) {
    const modal = document.getElementById('noteModal');
    const title = document.getElementById('noteModalTitle');
    const textarea = document.getElementById('noteText');
    const highlight = document.getElementById('noteHighlight');
    const timestamp = document.getElementById('noteTimestamp');
    
    if (editId !== null) {
        const note = notes.find(n => n.id === editId);
        title.textContent = 'Edit Note';
        textarea.value = note.text;
        highlight.checked = note.highlight;
        timestamp.textContent = formatTime(note.timestamp);
    } else {
        title.textContent = 'Add Note';
        textarea.value = '';
        highlight.checked = false;
        timestamp.textContent = formatTime(video.currentTime);
    }
    
    modal.classList.add('active');
    textarea.focus();
    
    // Save handler
    document.getElementById('btnSaveNote').onclick = () => {
        const text = textarea.value.trim();
        
        if (!text) {
            showToast('Please enter a note', 'error');
            return;
        }
        
        if (editId !== null) {
            updateNote(editId, text, highlight.checked);
        } else {
            addNote(text, video.currentTime, highlight.checked);
        }
        
        modal.classList.remove('active');
    };
}

function addNote(text, timestamp, highlight) {
    const note = {
        id: Date.now(),
        text,
        timestamp,
        highlight,
        date: new Date().toISOString(),
        courseId: currentParams.courseId,
        lectureId: currentParams.lectureId
    };
    
    notes.push(note);
    saveNotes();
    renderNotes();
    showToast('Note added', 'success');
}

function updateNote(id, text, highlight) {
    const note = notes.find(n => n.id === id);
    if (note) {
        note.text = text;
        note.highlight = highlight;
        saveNotes();
        renderNotes();
        showToast('Note updated', 'success');
    }
}

function deleteNote(id) {
    if (confirm('Delete this note?')) {
        notes = notes.filter(n => n.id !== id);
        saveNotes();
        renderNotes();
        showToast('Note deleted', 'success');
    }
}

function editNote(id) {
    openNoteModal(id);
}

function saveNotes() {
    const key = `notes_${currentParams.courseId}_${currentParams.lectureId}`;
    localStorage.setItem(key, JSON.stringify(notes));
}

function loadNotes() {
    const key = `notes_${currentParams.courseId}_${currentParams.lectureId}`;
    const saved = localStorage.getItem(key);
    notes = saved ? JSON.parse(saved) : [];
}

function renderNotes() {
    const container = document.getElementById('notesList');
    const emptyState = document.querySelector('#notes .empty-state');
    
    if (notes.length === 0) {
        container.style.display = 'none';
        if (emptyState) emptyState.style.display = 'block';
        return;
    }
    
    container.style.display = 'flex';
    if (emptyState) emptyState.style.display = 'none';
    
    // Sort by timestamp
    notes.sort((a, b) => a.timestamp - b.timestamp);
    
    container.innerHTML = notes.map(note => `
        <div class="note-item" data-note-id="${note.id}">
            <div class="note-timestamp">
                <button class="btn-timestamp" data-time="${note.timestamp}">
                    <span class="material-icons">play_circle</span>
                    <span>${formatTime(note.timestamp)}</span>
                </button>
            </div>
            <div class="note-content">
                <p ${note.highlight ? 'class="highlight"' : ''}>${escapeHtml(note.text)}</p>
                <div class="note-actions">
                    <button class="btn-text btn-edit-note">
                        <span class="material-icons">edit</span>
                        Edit
                    </button>
                    <button class="btn-text btn-delete-note">
                        <span class="material-icons">delete</span>
                        Delete
                    </button>
                    <span class="note-date">${formatDate(note.date)}</span>
                </div>
            </div>
        </div>
    `).join('');
}

// ===================================
// Q&A Section
// ===================================
function initQA() {
    const btnAskQuestion = document.getElementById('btnAskQuestion');
    btnAskQuestion.addEventListener('click', openQuestionModal);
    
    // Filter questions
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.filter;
            
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            filterQuestions(filter);
        });
    });
    
    // Search questions
    document.querySelector('.qa-search input').addEventListener('input', (e) => {
        searchQuestions(e.target.value);
    });
    
    // Vote buttons
    document.querySelectorAll('.btn-upvote, .btn-downvote').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.classList.toggle('active');
            
            // Update count (in production, sync with server)
            const countSpan = btn.parentElement.querySelector('.vote-count');
            let count = parseInt(countSpan.textContent);
            
            if (btn.classList.contains('active')) {
                count++;
            } else {
                count--;
            }
            
            countSpan.textContent = count;
        });
    });
    
    // Show more replies
    document.querySelectorAll('.btn-show-replies').forEach(btn => {
        btn.addEventListener('click', () => {
            // In production, load more replies
            showToast('Loading more replies...', 'info');
        });
    });
}

function openQuestionModal() {
    const modal = document.getElementById('questionModal');
    document.getElementById('questionTitle').value = '';
    document.getElementById('questionDetails').value = '';
    document.getElementById('questionTimestamp').value = formatTime(video.currentTime);
    
    modal.classList.add('active');
    document.getElementById('questionTitle').focus();
    
    document.getElementById('btnPostQuestion').onclick = () => {
        const title = document.getElementById('questionTitle').value.trim();
        const details = document.getElementById('questionDetails').value.trim();
        
        if (!title) {
            showToast('Please enter a question title', 'error');
            return;
        }
        
        // In production, post to server
        showToast('Question posted successfully', 'success');
        modal.classList.remove('active');
    };
}

function filterQuestions(filter) {
    document.querySelectorAll('.qa-item').forEach(item => {
        switch(filter) {
            case 'all':
                item.style.display = 'flex';
                break;
            case 'answered':
                item.style.display = item.classList.contains('answered') ? 'flex' : 'none';
                break;
            case 'unanswered':
                item.style.display = !item.classList.contains('answered') ? 'flex' : 'none';
                break;
            case 'instructor':
                item.style.display = item.querySelector('.instructor-reply') ? 'flex' : 'none';
                break;
        }
    });
}

function searchQuestions(term) {
    term = term.toLowerCase();
    
    document.querySelectorAll('.qa-item').forEach(item => {
        const question = item.querySelector('.qa-question h4').textContent.toLowerCase();
        const content = item.querySelector('.qa-question p').textContent.toLowerCase();
        
        if (question.includes(term) || content.includes(term)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

// ===================================
// Transcript Auto-scroll
// ===================================
function initTranscript() {
    let autoScroll = true;
    const btnAutoScroll = document.getElementById('btnAutoScroll');
    
    if (btnAutoScroll) {
        btnAutoScroll.addEventListener('click', () => {
            autoScroll = !autoScroll;
            btnAutoScroll.style.opacity = autoScroll ? '1' : '0.6';
            showToast(autoScroll ? 'Auto-scroll enabled' : 'Auto-scroll disabled', 'info');
        });
    }
    
    // Highlight active segment
    if (video) {
        video.addEventListener('timeupdate', () => {
            const segments = document.querySelectorAll('.transcript-segment');
            segments.forEach(segment => {
                const time = parseFloat(segment.dataset.time);
                const nextSegment = segment.nextElementSibling;
                const nextTime = nextSegment ? parseFloat(nextSegment.dataset.time) : Infinity;
                
                if (video.currentTime >= time && video.currentTime < nextTime) {
                    segment.classList.add('active');
                    
                    if (autoScroll) {
                        segment.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else {
                    segment.classList.remove('active');
                }
            });
        });
    }
    
    // Click to seek
    document.querySelectorAll('.transcript-time').forEach(btn => {
        btn.addEventListener('click', () => {
            const time = parseFloat(btn.parentElement.dataset.time);
            video.currentTime = time;
            video.play();
        });
    });
}

// ===================================
// Modals
// ===================================
function initModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        const overlay = modal.querySelector('.modal-overlay');
        const closeBtn = modal.querySelector('.modal-close');
        const cancelBtn = modal.querySelector('.btn-secondary');
        
        if (overlay) {
            overlay.addEventListener('click', () => {
                modal.classList.remove('active');
            });
        }
        
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                modal.classList.remove('active');
            });
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                modal.classList.remove('active');
            });
        }
    });
    
    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(modal => {
                modal.classList.remove('active');
            });
        }
    });
}

// ===================================
// Utility Functions
// ===================================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    
    if (seconds < 60) return 'just now';
    if (minutes < 60) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    if (days < 7) return `${days} day${days > 1 ? 's' : ''} ago`;
    
    return date.toLocaleDateString();
}

function showToast(message, type = 'info') {
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
    `;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ===================================
// Initialize Everything
// ===================================
document.addEventListener('DOMContentLoaded', () => {
    initVideoPlayer();
    initSidebar();
    initTabs();
    initNotes();
    initQA();
    initTranscript();
    initModals();
});

// ===================================
// Mark lecture as completed
// ===================================
if (video) {
    video.addEventListener('ended', () => {
        // Mark current lecture as completed
        const key = `completed_${currentParams.courseId}_${currentParams.lectureId}`;
        localStorage.setItem(key, 'true');
        
        // Update UI
        const currentLecture = document.querySelector(`.lecture-item[data-lecture-id="${currentParams.lectureId}"]`);
        if (currentLecture) {
            const status = currentLecture.querySelector('.lecture-status');
            status.classList.add('completed');
            status.querySelector('.material-icons').textContent = 'check_circle';
        }
        
        // Show next lecture prompt
        showToast('Lecture completed! 🎉', 'success');
        
        // Auto-play next lecture after 3 seconds
        setTimeout(() => {
            const btnNext = document.getElementById('btnNextLecture');
            if (btnNext && !btnNext.disabled) {
                btnNext.click();
            }
        }, 3000);
    });
}
