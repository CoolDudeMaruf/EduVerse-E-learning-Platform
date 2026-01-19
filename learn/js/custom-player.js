/**
 * Custom Video Player Controller
 * Professional video player with advanced features
 */

(function () {
    'use strict';

    // Elements
    const player = document.getElementById('customPlayer');
    const video = document.getElementById('videoPlayer');
    const loader = document.getElementById('playerLoader');
    const bigPlayBtn = document.getElementById('bigPlayBtn');
    const controls = document.getElementById('playerControls');

    // Control buttons
    const btnPlayPause = document.getElementById('btnPlayPause');
    const playPauseIcon = document.getElementById('playPauseIcon');
    const btnRewind = document.getElementById('btnRewind');
    const btnForward = document.getElementById('btnForward');
    const btnMute = document.getElementById('btnMute');
    const volumeIcon = document.getElementById('volumeIcon');
    const volumeSlider = document.getElementById('volumeSlider');
    const btnFullscreen = document.getElementById('btnFullscreen');
    const fullscreenIcon = document.getElementById('fullscreenIcon');
    const btnPiP = document.getElementById('btnPiP');

    // Progress elements
    const progressBar = document.getElementById('progressBar');
    const progressPlayed = document.getElementById('progressPlayed');
    const progressBuffered = document.getElementById('progressBuffered');
    const progressHandle = document.getElementById('progressHandle');
    const progressTooltip = document.getElementById('progressTooltip');

    // Time elements
    const currentTimeEl = document.getElementById('currentTime');
    const totalTimeEl = document.getElementById('totalTime');

    // Speed elements
    const speedMenu = document.getElementById('speedMenu');
    const speedText = document.getElementById('speedText');

    // Caption elements
    const captionMenu = document.getElementById('captionMenu');

    // Settings
    const toggleAutoplay = document.getElementById('toggleAutoplay');

    // State
    let isFullscreen = false;
    let isDragging = false;
    let hideControlsTimeout = null;
    let lastVolume = 1;

    // Initialize
    if (video && player) {
        init();
    }

    function init() {
        // Video events
        video.addEventListener('loadstart', () => showLoader(true));
        video.addEventListener('canplay', () => showLoader(false));
        video.addEventListener('waiting', () => showLoader(true));
        video.addEventListener('playing', () => {
            showLoader(false);
            updatePlayPauseIcon(true);
            hideBigPlay();
        });
        video.addEventListener('pause', () => updatePlayPauseIcon(false));
        video.addEventListener('timeupdate', updateProgress);
        video.addEventListener('progress', updateBuffered);
        video.addEventListener('loadedmetadata', updateDuration);
        video.addEventListener('ended', onVideoEnd);
        video.addEventListener('volumechange', updateVolumeIcon);

        // Play/Pause
        btnPlayPause?.addEventListener('click', togglePlayPause);
        bigPlayBtn?.addEventListener('click', togglePlayPause);
        video.addEventListener('click', togglePlayPause);

        // Rewind/Forward
        btnRewind?.addEventListener('click', () => seek(-10));
        btnForward?.addEventListener('click', () => seek(10));

        // Volume
        btnMute?.addEventListener('click', toggleMute);
        volumeSlider?.addEventListener('input', changeVolume);

        // Progress bar
        progressBar?.addEventListener('click', seekToPosition);
        progressBar?.addEventListener('mousemove', showTooltip);
        progressBar?.addEventListener('mousedown', startDragging);
        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', stopDragging);

        // Fullscreen
        btnFullscreen?.addEventListener('click', toggleFullscreen);
        document.addEventListener('fullscreenchange', onFullscreenChange);

        // Picture-in-Picture
        btnPiP?.addEventListener('click', togglePiP);

        // Speed menu
        speedMenu?.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => setSpeed(btn.dataset.speed));
        });

        // Caption menu
        captionMenu?.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => setCaption(parseInt(btn.dataset.track)));
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', handleKeyboard);

        // Show/hide controls
        player.addEventListener('mousemove', showControls);
        player.addEventListener('mouseleave', scheduleHideControls);

        // Double click for fullscreen
        video.addEventListener('dblclick', toggleFullscreen);

        // Initial volume from localStorage
        const savedVolume = localStorage.getItem('playerVolume');
        if (savedVolume !== null) {
            video.volume = parseFloat(savedVolume);
            volumeSlider.value = video.volume * 100;
        }
    }

    // ===== Playback Controls =====
    function togglePlayPause() {
        if (video.paused) {
            video.play();
        } else {
            video.pause();
        }
    }

    function updatePlayPauseIcon(playing) {
        if (playPauseIcon) {
            playPauseIcon.textContent = playing ? 'pause' : 'play_arrow';
        }
    }

    function seek(seconds) {
        video.currentTime = Math.max(0, Math.min(video.duration, video.currentTime + seconds));
    }

    // ===== Progress Bar =====
    function updateProgress() {
        if (!video.duration || isDragging) return;

        const percent = (video.currentTime / video.duration) * 100;
        progressPlayed.style.width = percent + '%';
        progressHandle.style.left = percent + '%';

        currentTimeEl.textContent = formatTime(video.currentTime);
    }

    function updateBuffered() {
        if (video.buffered.length > 0) {
            const buffered = (video.buffered.end(video.buffered.length - 1) / video.duration) * 100;
            progressBuffered.style.width = buffered + '%';
        }
    }

    function updateDuration() {
        totalTimeEl.textContent = formatTime(video.duration);
    }

    function seekToPosition(e) {
        const rect = progressBar.getBoundingClientRect();
        const percent = (e.clientX - rect.left) / rect.width;
        video.currentTime = percent * video.duration;
    }

    function showTooltip(e) {
        const rect = progressBar.getBoundingClientRect();
        const percent = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        const time = percent * video.duration;

        progressTooltip.textContent = formatTime(time);
        progressTooltip.style.left = (percent * 100) + '%';
    }

    function startDragging(e) {
        isDragging = true;
        progressHandle.classList.add('dragging');
        drag(e);
    }

    function drag(e) {
        if (!isDragging) return;

        const rect = progressBar.getBoundingClientRect();
        const percent = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));

        progressPlayed.style.width = (percent * 100) + '%';
        progressHandle.style.left = (percent * 100) + '%';
        currentTimeEl.textContent = formatTime(percent * video.duration);
    }

    function stopDragging(e) {
        if (!isDragging) return;

        isDragging = false;
        progressHandle.classList.remove('dragging');

        const rect = progressBar.getBoundingClientRect();
        const percent = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        video.currentTime = percent * video.duration;
    }

    // ===== Volume =====
    function toggleMute() {
        if (video.muted || video.volume === 0) {
            video.muted = false;
            video.volume = lastVolume || 1;
            volumeSlider.value = video.volume * 100;
        } else {
            lastVolume = video.volume;
            video.muted = true;
            volumeSlider.value = 0;
        }
    }

    function changeVolume() {
        const value = volumeSlider.value / 100;
        video.volume = value;
        video.muted = value === 0;
        localStorage.setItem('playerVolume', value);
    }

    function updateVolumeIcon() {
        if (!volumeIcon) return;

        if (video.muted || video.volume === 0) {
            volumeIcon.textContent = 'volume_off';
        } else if (video.volume < 0.5) {
            volumeIcon.textContent = 'volume_down';
        } else {
            volumeIcon.textContent = 'volume_up';
        }
    }

    // ===== Fullscreen =====
    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            player.requestFullscreen().catch(err => {
                // Fallback for browsers that don't support fullscreen
                player.classList.toggle('fullscreen');
                isFullscreen = !isFullscreen;
            });
        } else {
            document.exitFullscreen();
        }
    }

    function onFullscreenChange() {
        isFullscreen = !!document.fullscreenElement;
        fullscreenIcon.textContent = isFullscreen ? 'fullscreen_exit' : 'fullscreen';
        player.classList.toggle('fullscreen', isFullscreen);
    }

    // ===== Picture-in-Picture =====
    async function togglePiP() {
        try {
            if (document.pictureInPictureElement) {
                await document.exitPictureInPicture();
            } else if (document.pictureInPictureEnabled) {
                await video.requestPictureInPicture();
            }
        } catch (err) {
            console.error('PiP error:', err);
        }
    }

    // ===== Playback Speed =====
    function setSpeed(speed) {
        video.playbackRate = parseFloat(speed);
        speedText.textContent = speed === '1' ? '1x' : speed + 'x';

        // Update active state
        speedMenu.querySelectorAll('button').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.speed === speed);
        });
    }

    // ===== Captions/Subtitles =====
    function setCaption(trackIndex) {
        const tracks = video.textTracks;

        for (let i = 0; i < tracks.length; i++) {
            tracks[i].mode = i === trackIndex ? 'showing' : 'hidden';
        }

        // Update active state
        captionMenu?.querySelectorAll('button').forEach(btn => {
            btn.classList.toggle('active', parseInt(btn.dataset.track) === trackIndex);
        });
    }

    // ===== Keyboard Controls =====
    function handleKeyboard(e) {
        // Don't trigger if typing in an input
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        switch (e.key.toLowerCase()) {
            case ' ':
            case 'k':
                e.preventDefault();
                togglePlayPause();
                break;
            case 'arrowleft':
            case 'j':
                e.preventDefault();
                seek(-10);
                break;
            case 'arrowright':
            case 'l':
                e.preventDefault();
                seek(10);
                break;
            case 'arrowup':
                e.preventDefault();
                video.volume = Math.min(1, video.volume + 0.1);
                volumeSlider.value = video.volume * 100;
                break;
            case 'arrowdown':
                e.preventDefault();
                video.volume = Math.max(0, video.volume - 0.1);
                volumeSlider.value = video.volume * 100;
                break;
            case 'm':
                toggleMute();
                break;
            case 'f':
                toggleFullscreen();
                break;
            case 'p':
                togglePiP();
                break;
            case 'c':
                // Toggle captions
                const tracks = video.textTracks;
                if (tracks.length > 0) {
                    const showing = Array.from(tracks).some(t => t.mode === 'showing');
                    setCaption(showing ? -1 : 0);
                }
                break;
            case 'escape':
                if (isFullscreen) {
                    document.exitFullscreen();
                }
                break;
        }
    }

    // ===== UI Helpers =====
    function showLoader(show) {
        loader?.classList.toggle('active', show);
    }

    function hideBigPlay() {
        bigPlayBtn?.classList.add('hidden');
    }

    function showControls() {
        player.classList.add('controls-visible');
        clearTimeout(hideControlsTimeout);
        scheduleHideControls();
    }

    function scheduleHideControls() {
        hideControlsTimeout = setTimeout(() => {
            if (!video.paused) {
                player.classList.remove('controls-visible');
            }
        }, 3000);
    }

    function onVideoEnd() {
        updatePlayPauseIcon(false);
        bigPlayBtn?.classList.remove('hidden');

        // Auto-play next if enabled
        if (toggleAutoplay?.checked && typeof navigateLecture === 'function') {
            showAutoNextCountdown();
        }
    }

    // Auto-next countdown variables
    let autoNextTimer = null;
    let countdownValue = 5;

    function showAutoNextCountdown() {
        countdownValue = 5;

        // Create countdown overlay if it doesn't exist
        let overlay = document.getElementById('autoNextOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'autoNextOverlay';
            overlay.className = 'auto-next-overlay';
            overlay.innerHTML = `
                <div class="auto-next-content">
                    <div class="auto-next-circle">
                        <svg viewBox="0 0 36 36">
                            <circle class="circle-bg" cx="18" cy="18" r="16"></circle>
                            <circle class="circle-progress" cx="18" cy="18" r="16"></circle>
                        </svg>
                        <span class="countdown-number" id="countdownNumber">5</span>
                    </div>
                    <p class="auto-next-text">Next lecture in <span id="countdownSeconds">5</span> seconds</p>
                    <div class="auto-next-buttons">
                        <button class="btn-cancel" id="btnCancelAutoNext">Cancel</button>
                        <button class="btn-play-now" id="btnPlayNow">Play Now</button>
                    </div>
                </div>
            `;
            player.appendChild(overlay);

            // Add event listeners
            document.getElementById('btnCancelAutoNext').addEventListener('click', cancelAutoNext);
            document.getElementById('btnPlayNow').addEventListener('click', playNextNow);
        }

        overlay.classList.add('active');
        updateCountdownDisplay();

        // Start countdown
        autoNextTimer = setInterval(() => {
            countdownValue--;
            updateCountdownDisplay();

            if (countdownValue <= 0) {
                cancelAutoNext();
                navigateLecture('next');
            }
        }, 1000);
    }

    function updateCountdownDisplay() {
        const countdownNumber = document.getElementById('countdownNumber');
        const countdownSeconds = document.getElementById('countdownSeconds');
        const circleProgress = document.querySelector('.circle-progress');

        if (countdownNumber) countdownNumber.textContent = countdownValue;
        if (countdownSeconds) countdownSeconds.textContent = countdownValue;

        // Update circle progress (circumference = 2 * PI * 16 ≈ 100.53)
        if (circleProgress) {
            const offset = 100.53 * (1 - countdownValue / 5);
            circleProgress.style.strokeDashoffset = offset;
        }
    }

    function cancelAutoNext() {
        if (autoNextTimer) {
            clearInterval(autoNextTimer);
            autoNextTimer = null;
        }
        const overlay = document.getElementById('autoNextOverlay');
        if (overlay) overlay.classList.remove('active');
    }

    function playNextNow() {
        cancelAutoNext();
        navigateLecture('next');
    }

    function formatTime(seconds) {
        if (!seconds || isNaN(seconds)) return '0:00';

        const hrs = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);

        if (hrs > 0) {
            return `${hrs}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    // Export for external use
    window.customPlayer = {
        play: () => video.play(),
        pause: () => video.pause(),
        seek: (time) => video.currentTime = time,
        setVolume: (vol) => video.volume = vol,
        setSpeed: setSpeed,
        setCaption: setCaption,
        toggleFullscreen: toggleFullscreen,
        togglePiP: togglePiP
    };

})();
