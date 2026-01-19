(function () {
    'use strict';

    const STATE = {
        currentSectionId: 0,
        currentLectureId: 0,
        isUploading: false
    };

    // Helper function to format duration in MM:SS format
    function formatDuration(totalSeconds) {
        if (!totalSeconds || totalSeconds <= 0) return '0:00';
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return minutes + ':' + seconds.toString().padStart(2, '0');
    }

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        initTabs();
        initSectionToggles();
        initModalCloseHandlers();
        loadFirstLecture();
    }

    function loadFirstLecture() {
        const firstLecture = document.querySelector('.lecture-item');
        if (firstLecture) {
            const lectureId = firstLecture.getAttribute('data-lecture-id');
            if (lectureId) {
                selectLecture(parseInt(lectureId));
            }
        }
    }

    function initTabs() {
        document.querySelectorAll('.editor-tabs .tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const tabId = this.getAttribute('data-tab');

                document.querySelectorAll('.editor-tabs .tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                document.querySelectorAll('.lecture-editor .tab-content').forEach(content => {
                    content.classList.remove('active');
                });

                const tabElement = document.getElementById(tabId + 'Tab');
                if (tabElement) {
                    tabElement.classList.add('active');
                } else {
                    console.error('Tab content not found:', tabId + 'Tab');
                }
            });
        });
    }

    function initSectionToggles() {
        document.querySelectorAll('.section-toggle').forEach(toggle => {
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                const sectionItem = this.closest('.section-item');
                sectionItem.classList.toggle('open');

                const icon = this.querySelector('.material-icons');
                icon.textContent = sectionItem.classList.contains('open') ? 'expand_more' : 'chevron_right';
            });
        });
    }

    function initModalCloseHandlers() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });
    }

    window.addSection = function () {
        document.getElementById('newSectionTitle').value = '';
        document.getElementById('newSectionDescription').value = '';
        document.getElementById('addSectionModal').style.display = 'flex';
    };

    window.saveNewSection = function (event) {
        event.preventDefault();

        const title = document.getElementById('newSectionTitle').value;
        const description = document.getElementById('newSectionDescription').value;

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('course_id', COURSE_ID);
        formData.append('title', title);
        formData.append('description', description);

        fetch(AJAX_BASE + 'section-handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const sectionsList = document.getElementById('sectionsList');
                    const sectionCount = document.querySelectorAll('.section-item').length + 1;

                    const newSection = document.createElement('div');
                    newSection.className = 'section-item open';
                    newSection.setAttribute('data-section-id', data.section_id);
                    newSection.innerHTML = `
                    <div class="section-header">
                        <button class="section-toggle">
                            <span class="material-icons">expand_more</span>
                        </button>
                        <div class="section-info" onclick="selectSection(${data.section_id})">
                            <span class="section-number">${sectionCount}</span>
                            <div class="section-details">
                                <h4>${escapeHtml(title)}</h4>
                                <span class="section-meta">0 lectures • 0min</span>
                            </div>
                        </div>
                        <div class="section-actions">
                            <button class="btn-icon" onclick="editSection(${data.section_id})" title="Edit">
                                <span class="material-icons">edit</span>
                            </button>
                            <button class="btn-icon" onclick="deleteSection(${data.section_id})" title="Delete">
                                <span class="material-icons">delete</span>
                            </button>
                        </div>
                    </div>
                    <div class="lectures-list">
                        <button class="btn-add-lecture" onclick="addLecture(${data.section_id})">
                            <span class="material-icons">add</span>
                            Add Lecture
                        </button>
                    </div>
                `;

                    sectionsList.appendChild(newSection);

                    newSection.querySelector('.section-toggle').addEventListener('click', function (e) {
                        e.stopPropagation();
                        newSection.classList.toggle('open');
                        const icon = this.querySelector('.material-icons');
                        icon.textContent = newSection.classList.contains('open') ? 'expand_more' : 'chevron_right';
                    });

                    closeModal('addSectionModal');
                    showNotification('Section "' + title + '" added successfully!', 'success');
                } else {
                    showNotification(data.error || 'Failed to add section', 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to add section', 'error');
            });
    };

    window.selectSection = function (sectionId) {
        STATE.currentSectionId = sectionId;
    };

    window.editSection = function (sectionId) {
        fetch(AJAX_BASE + 'section-handler.php?action=get&course_id=' + COURSE_ID + '&section_id=' + sectionId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('editSectionId').value = sectionId;
                    document.getElementById('editSectionTitle').value = data.section.title;
                    document.getElementById('editSectionDescription').value = data.section.description || '';
                    document.getElementById('editSectionModal').style.display = 'flex';
                } else {
                    showNotification(data.error || 'Failed to load section', 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to load section', 'error');
            });
    };

    window.updateSection = function (event) {
        event.preventDefault();

        const sectionId = document.getElementById('editSectionId').value;
        const title = document.getElementById('editSectionTitle').value;
        const description = document.getElementById('editSectionDescription').value;

        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('course_id', COURSE_ID);
        formData.append('section_id', sectionId);
        formData.append('title', title);
        formData.append('description', description);

        fetch(AJAX_BASE + 'section-handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const section = document.querySelector(`[data-section-id="${sectionId}"]`);
                    if (section) {
                        section.querySelector('.section-details h4').textContent = title;
                    }
                    closeModal('editSectionModal');
                    showNotification('Section updated!', 'success');
                } else {
                    showNotification(data.error || 'Failed to update section', 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to update section', 'error');
            });
    };

    window.deleteSection = function (sectionId) {
        if (confirm('Are you sure you want to delete this section? All lectures will be removed.')) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('course_id', COURSE_ID);
            formData.append('section_id', sectionId);

            fetch(AJAX_BASE + 'section-handler.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const section = document.querySelector(`[data-section-id="${sectionId}"]`);
                        if (section) {
                            section.remove();
                        }
                        showNotification('Section deleted!', 'success');
                        renumberSections();
                    } else {
                        showNotification(data.error || 'Failed to delete section', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Failed to delete section', 'error');
                });
        }
    };

    function renumberSections() {
        document.querySelectorAll('.section-item').forEach((section, index) => {
            const numberEl = section.querySelector('.section-number');
            if (numberEl) {
                numberEl.textContent = index + 1;
            }
        });
    }

    window.addLecture = function (sectionId) {
        document.getElementById('newLectureSectionId').value = sectionId;
        document.getElementById('newLectureTitle').value = '';
        document.getElementById('addLectureModal').style.display = 'flex';
    };

    // Subtitle management functions for Subtitles tab
    window.uploadSubtitle = function () {
        if (!STATE.currentLectureId) {
            showNotification('Please select a lecture first', 'error');
            return;
        }

        const langSelect = document.getElementById('newSubtitleLang');
        const fileInput = document.getElementById('newSubtitleFile');

        if (!fileInput.files.length) {
            showNotification('Please select a subtitle file', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'add_subtitle');
        formData.append('course_id', COURSE_ID);
        formData.append('lecture_id', STATE.currentLectureId);
        formData.append('subtitle_file', fileInput.files[0]);
        formData.append('subtitle_lang', langSelect.value);

        fetch(AJAX_BASE + 'lecture-handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    fileInput.value = '';
                    loadLectureSubtitles(STATE.currentLectureId);
                    showNotification('Subtitle added successfully!', 'success');
                } else {
                    showNotification(data.error || 'Failed to add subtitle', 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to add subtitle', 'error');
            });
    };

    window.loadLectureSubtitles = function (lectureId) {
        fetch(AJAX_BASE + 'lecture-handler.php?action=get_subtitles&course_id=' + COURSE_ID + '&lecture_id=' + lectureId)
            .then(response => response.json())
            .then(data => {
                const existingSubtitles = document.getElementById('existingSubtitles');
                const emptySubtitles = document.getElementById('emptySubtitles');
                existingSubtitles.innerHTML = '';

                if (data.success && data.subtitles && data.subtitles.length > 0) {
                    emptySubtitles.style.display = 'none';
                    const langNames = {
                        'en': 'English', 'bn': 'Bengali', 'hi': 'Hindi',
                        'es': 'Spanish', 'fr': 'French', 'ar': 'Arabic',
                        'zh': 'Chinese', 'ja': 'Japanese'
                    };

                    data.subtitles.forEach((sub, index) => {
                        const subEl = document.createElement('div');
                        subEl.className = 'subtitle-item existing';
                        subEl.innerHTML = `
                            <span class="material-icons">subtitles</span>
                            <div class="subtitle-info">
                                <span class="subtitle-lang-name">${langNames[sub.lang] || sub.lang}</span>
                                <span class="subtitle-file-name">${escapeHtml(sub.original_name)}</span>
                            </div>
                            <button class="btn-icon" onclick="deleteSubtitle(${index})" title="Delete">
                                <span class="material-icons">delete</span>
                            </button>
                        `;
                        existingSubtitles.appendChild(subEl);
                    });
                } else {
                    emptySubtitles.style.display = 'block';
                }
            });
    };

    window.deleteSubtitle = function (index) {
        if (!confirm('Are you sure you want to delete this subtitle?')) return;

        const formData = new FormData();
        formData.append('action', 'delete_subtitle');
        formData.append('course_id', COURSE_ID);
        formData.append('lecture_id', STATE.currentLectureId);
        formData.append('subtitle_index', index);

        fetch(AJAX_BASE + 'lecture-handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadLectureSubtitles(STATE.currentLectureId);
                    showNotification('Subtitle deleted!', 'success');
                } else {
                    showNotification(data.error || 'Failed to delete subtitle', 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to delete subtitle', 'error');
            });
    };

    window.saveNewLecture = function (event) {
        event.preventDefault();

        const sectionId = document.getElementById('newLectureSectionId').value;
        const title = document.getElementById('newLectureTitle').value;
        const type = document.getElementById('newLectureType').value;

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('course_id', COURSE_ID);
        formData.append('section_id', sectionId);
        formData.append('title', title);
        formData.append('lecture_type', type);

        fetch(AJAX_BASE + 'lecture-handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const section = document.querySelector(`[data-section-id="${sectionId}"]`);
                    const lecturesList = section.querySelector('.lectures-list');
                    const addBtn = lecturesList.querySelector('.btn-add-lecture');

                    const typeIcons = {
                        'video': 'play_circle',
                        'article': 'article',
                        'quiz': 'quiz',
                        'assignment': 'assignment'
                    };

                    const newLecture = document.createElement('div');
                    newLecture.className = 'lecture-item';
                    newLecture.setAttribute('data-lecture-id', data.lecture_id);
                    newLecture.setAttribute('onclick', `selectLecture(${data.lecture_id})`);
                    newLecture.innerHTML = `
                    <span class="material-icons lecture-icon">${typeIcons[type] || 'play_circle'}</span>
                    <div class="lecture-info">
                        <p class="lecture-name">${escapeHtml(title)}</p>
                        <span class="lecture-duration">0:00</span>
                    </div>
                    <div class="lecture-actions">
                        <button class="btn-icon" onclick="event.stopPropagation(); editLecture(${data.lecture_id})">
                            <span class="material-icons">edit</span>
                        </button>
                    </div>
                `;

                    lecturesList.insertBefore(newLecture, addBtn);

                    closeModal('addLectureModal');
                    selectLecture(data.lecture_id);
                    showNotification('Lecture "' + title + '" added!', 'success');
                } else {
                    showNotification(data.error || 'Failed to add lecture', 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to add lecture', 'error');
            });
    };

    window.selectLecture = function (lectureId) {
        document.querySelectorAll('.lecture-item').forEach(item => item.classList.remove('active'));
        const lecture = document.querySelector(`[data-lecture-id="${lectureId}"]`);
        if (lecture) {
            lecture.classList.add('active');
            STATE.currentLectureId = lectureId;

            fetch(AJAX_BASE + 'lecture-handler.php?action=get&course_id=' + COURSE_ID + '&lecture_id=' + lectureId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const lec = data.lecture;
                        document.getElementById('lectureTitleInput').value = lec.title || '';
                        document.getElementById('lectureDescription').value = lec.description || '';
                        document.getElementById('lectureType').value = lec.lecture_type || 'video';
                        document.getElementById('freePreview').checked = lec.is_preview == 1;
                        document.getElementById('downloadable').checked = lec.is_downloadable == 1;

                        const durationInput = document.getElementById('durationOverride');
                        if (durationInput) {
                            durationInput.value = lec.duration_minutes > 0 ? lec.duration_minutes : '';
                        }

                        // Update video preview if content_url exists
                        if (lec.content_url) {
                            document.getElementById('currentVideoName').textContent = lec.content_url.split('/').pop();

                            // Display duration from database (stored in seconds)
                            const durationEl = document.getElementById('currentVideoDuration');
                            if (durationEl) {
                                const totalSeconds = parseInt(lec.duration_seconds) || (parseInt(lec.duration_minutes) * 60) || 0;
                                durationEl.textContent = formatDuration(totalSeconds);
                            }

                            // Display file size from database
                            const sizeEl = document.getElementById('currentVideoSize');
                            if (sizeEl) {
                                const sizeBytes = parseInt(lec.video_file_size) || 0;
                                if (sizeBytes > 0) {
                                    const sizeMB = (sizeBytes / (1024 * 1024)).toFixed(2);
                                    sizeEl.textContent = sizeMB + ' MB';
                                } else {
                                    sizeEl.textContent = '0 MB';
                                }
                            }

                            const placeholder = document.getElementById('videoPlaceholder');
                            if (lec.content_url.includes('youtube.com') || lec.content_url.includes('vimeo.com')) {
                                placeholder.innerHTML = `<iframe width="100%" height="100%" src="${lec.content_url}" frameborder="0" allowfullscreen></iframe>`;
                                placeholder.style.display = 'block';
                                document.getElementById('videoPreview').style.display = 'none';
                            } else {
                                const videoPreview = document.getElementById('videoPreview');
                                videoPreview.src = '../../' + lec.content_url;
                                videoPreview.style.display = 'block';
                                placeholder.style.display = 'none';
                            }
                        } else {
                            document.getElementById('currentVideoName').textContent = 'No video';
                            document.getElementById('currentVideoDuration').textContent = '0:00';
                            document.getElementById('currentVideoSize').textContent = '0 MB';
                            document.getElementById('videoPlaceholder').style.display = 'flex';
                            document.getElementById('videoPreview').style.display = 'none';
                        }

                        // Load thumbnail if exists
                        const thumbnailImage = document.getElementById('thumbnailImage');
                        const thumbnailPlaceholder = document.getElementById('thumbnailPlaceholder');
                        const removeThumbnailBtn = document.getElementById('removeThumbnailBtn');

                        if (lec.thumbnail_url) {
                            thumbnailImage.src = '../../' + lec.thumbnail_url;
                            thumbnailImage.style.display = 'block';
                            thumbnailPlaceholder.style.display = 'none';
                            removeThumbnailBtn.style.display = 'block';
                        } else {
                            thumbnailImage.src = '';
                            thumbnailImage.style.display = 'none';
                            thumbnailPlaceholder.style.display = 'flex';
                            removeThumbnailBtn.style.display = 'none';
                        }

                        document.getElementById('lectureEditor').style.display = 'block';
                        document.getElementById('emptyState').style.display = 'none';

                        // Load resources for this lecture
                        loadLectureResources(lectureId);

                        // Load learning objectives for this lecture
                        loadLectureObjectives(lec.learning_objectives);

                        // Load subtitles for this lecture
                        loadLectureSubtitles(lectureId);
                    }
                });
        }
    };

    function loadLectureObjectives(objectivesJson) {
        const list = document.getElementById('objectivesList');
        list.innerHTML = '';

        if (!objectivesJson) return;

        try {
            const objectives = JSON.parse(objectivesJson);
            if (Array.isArray(objectives)) {
                objectives.forEach(obj => {
                    const item = document.createElement('div');
                    item.className = 'objective-item';
                    item.innerHTML = `
                        <span class="material-icons">check_circle</span>
                        <input type="text" value="${escapeHtml(obj)}" placeholder="Learning objective" onblur="saveObjectives()">
                        <button class="btn-icon" onclick="removeObjective(this)">
                            <span class="material-icons">close</span>
                        </button>
                    `;
                    list.appendChild(item);
                });
            }
        } catch (e) {
            console.error('Error parsing objectives:', e);
        }
    }

    function loadLectureResources(lectureId) {
        fetch(AJAX_BASE + 'resource-handler.php?action=list&course_id=' + COURSE_ID + '&lecture_id=' + lectureId)
            .then(response => response.json())
            .then(data => {
                const resourcesList = document.getElementById('resourcesList');
                const emptyResources = document.getElementById('emptyResources');
                resourcesList.innerHTML = '';

                if (data.success && data.resources.length > 0) {
                    emptyResources.style.display = 'none';
                    data.resources.forEach(res => {
                        const iconClass = res.resource_type === 'link' ? 'link' : 'doc';
                        const iconName = res.resource_type === 'link' ? 'link' : 'description';
                        const fileInfo = res.resource_type === 'link' ? 'External Link' : (res.file_type || 'File');

                        const resEl = document.createElement('div');
                        resEl.className = 'resource-item';
                        resEl.setAttribute('data-resource-id', res.resource_id);
                        resEl.innerHTML = `
                            <div class="resource-icon ${iconClass}">
                                <span class="material-icons">${iconName}</span>
                            </div>
                            <div class="resource-info">
                                <h4>${escapeHtml(res.resource_name)}</h4>
                                <p>${fileInfo}</p>
                            </div>
                            <div class="resource-actions">
                                <button class="btn-icon" onclick="editResource(${res.resource_id})">
                                    <span class="material-icons">edit</span>
                                </button>
                                <button class="btn-icon" onclick="deleteResource(${res.resource_id})">
                                    <span class="material-icons">delete</span>
                                </button>
                            </div>
                        `;
                        resourcesList.appendChild(resEl);
                    });
                } else {
                    emptyResources.style.display = 'block';
                }
            });
    }

    window.editLecture = function (lectureId) {
        selectLecture(lectureId);
    };

    window.saveLecture = function () {
        const title = document.getElementById('lectureTitleInput').value;
        const description = document.getElementById('lectureDescription').value;
        const lectureType = document.getElementById('lectureType').value;
        const isPreview = document.getElementById('freePreview').checked ? 1 : 0;
        const isDownloadable = document.getElementById('downloadable').checked ? 1 : 0;
        const durationOverride = document.getElementById('durationOverride').value;

        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('course_id', COURSE_ID);
        formData.append('lecture_id', STATE.currentLectureId);
        formData.append('title', title);
        formData.append('description', description);
        formData.append('lecture_type', lectureType);
        formData.append('is_preview', isPreview);
        formData.append('is_downloadable', isDownloadable);
        if (durationOverride) {
            formData.append('duration_minutes', durationOverride);
        }

        fetch(AJAX_BASE + 'lecture-handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const lecture = document.querySelector(`[data-lecture-id="${STATE.currentLectureId}"]`);
                    if (lecture) {
                        lecture.querySelector('.lecture-name').textContent = title;
                    }
                    showNotification('Lecture saved!', 'success');
                } else {
                    showNotification(data.error || 'Failed to save lecture', 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to save lecture', 'error');
            });
    };

    window.deleteLecture = function () {
        if (confirm('Are you sure you want to delete this lecture?')) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('course_id', COURSE_ID);
            formData.append('lecture_id', STATE.currentLectureId);

            fetch(AJAX_BASE + 'lecture-handler.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const lecture = document.querySelector(`[data-lecture-id="${STATE.currentLectureId}"]`);
                        if (lecture) {
                            lecture.remove();
                        }
                        document.getElementById('lectureEditor').style.display = 'none';
                        document.getElementById('emptyState').style.display = 'flex';
                        STATE.currentLectureId = 0;
                        showNotification('Lecture deleted!', 'success');
                    } else {
                        showNotification(data.error || 'Failed to delete lecture', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Failed to delete lecture', 'error');
                });
        }
    };

    window.showAddResourceModal = function () {
        document.getElementById('resourceName').value = '';
        document.getElementById('resourceUrl').value = '';
        document.getElementById('resourceFile').value = '';
        document.getElementById('resourceType').value = 'file';
        toggleResourceFields();
        document.getElementById('addResourceModal').style.display = 'flex';
    };

    window.toggleResourceFields = function () {
        const type = document.getElementById('resourceType').value;
        document.getElementById('fileUploadFields').style.display = type === 'file' ? 'block' : 'none';
        document.getElementById('linkFields').style.display = type === 'link' ? 'block' : 'none';
    };

    window.saveNewResource = function (event) {
        event.preventDefault();

        if (!STATE.currentLectureId) {
            showNotification('Please select a lecture first', 'error');
            return;
        }

        const name = document.getElementById('resourceName').value;
        const type = document.getElementById('resourceType').value;

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('course_id', COURSE_ID);
        formData.append('lecture_id', STATE.currentLectureId);
        formData.append('resource_name', name);
        formData.append('resource_type', type);

        if (type === 'link') {
            formData.append('resource_url', document.getElementById('resourceUrl').value);
        } else {
            const fileInput = document.getElementById('resourceFile');
            if (fileInput.files.length > 0) {
                formData.append('resource_file', fileInput.files[0]);
            } else {
                showNotification('Please select a file', 'error');
                return;
            }
        }

        fetch(AJAX_BASE + 'resource-handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal('addResourceModal');
                    showNotification('Resource "' + name + '" added!', 'success');
                    loadLectureResources(STATE.currentLectureId);
                } else {
                    showNotification(data.error || 'Failed to add resource', 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to add resource', 'error');
            });
    };

    window.editResource = function (resourceId) {
        const resource = document.querySelector(`[data-resource-id="${resourceId}"]`);
        const name = resource.querySelector('h4').textContent;

        const newName = prompt('Edit resource name:', name);
        if (newName && newName !== name) {
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('course_id', COURSE_ID);
            formData.append('resource_id', resourceId);
            formData.append('resource_name', newName);

            fetch(AJAX_BASE + 'resource-handler.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resource.querySelector('h4').textContent = newName;
                        showNotification('Resource updated!', 'success');
                    } else {
                        showNotification(data.error || 'Failed to update resource', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Failed to update resource', 'error');
                });
        }
    };

    window.deleteResource = function (resourceId) {
        if (confirm('Are you sure you want to delete this resource?')) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('course_id', COURSE_ID);
            formData.append('resource_id', resourceId);

            fetch(AJAX_BASE + 'resource-handler.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const resource = document.querySelector(`[data-resource-id="${resourceId}"]`);
                        resource.remove();

                        const resourcesList = document.getElementById('resourcesList');
                        if (resourcesList.children.length === 0) {
                            const emptyResources = document.getElementById('emptyResources');
                            if (emptyResources) {
                                emptyResources.style.display = 'block';
                            }
                        }
                        showNotification('Resource deleted!', 'success');
                    } else {
                        showNotification(data.error || 'Failed to delete resource', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Failed to delete resource', 'error');
                });
        }
    };

    window.handleVideoUpload = function (event) {
        const file = event.target.files[0];
        if (!file) return;

        if (!STATE.currentLectureId) {
            showNotification('Please select a lecture first', 'error');
            return;
        }

        // Validate file type
        const allowedTypes = ['video/mp4', 'video/webm', 'video/quicktime'];
        if (!allowedTypes.includes(file.type)) {
            showNotification('Invalid video format. Allowed: MP4, WebM, MOV', 'error');
            return;
        }

        // Check file size (2GB max)
        const maxSize = 2 * 1024 * 1024 * 1024;
        if (file.size > maxSize) {
            showNotification('Video too large. Maximum 2GB allowed', 'error');
            return;
        }

        // Detect video duration using HTML5 video element
        const video = document.createElement('video');
        video.preload = 'metadata';

        video.onloadedmetadata = function () {
            window.URL.revokeObjectURL(video.src);
            const durationSeconds = Math.round(video.duration);

            // Now upload with the detected duration in seconds
            uploadVideoFile(file, durationSeconds);
        };

        video.onerror = function () {
            // Fallback: upload without duration, server will estimate
            uploadVideoFile(file, 0);
        };

        video.src = URL.createObjectURL(file);
    };

    function uploadVideoFile(file, durationSeconds) {
        document.getElementById('uploadProgress').style.display = 'block';
        document.getElementById('uploadFileName').textContent = file.name;
        STATE.isUploading = true;

        const formData = new FormData();
        formData.append('action', 'upload_video');
        formData.append('course_id', COURSE_ID);
        formData.append('lecture_id', STATE.currentLectureId);
        formData.append('video', file);
        formData.append('duration_seconds', durationSeconds);
        formData.append('file_size', file.size);

        const xhr = new XMLHttpRequest();
        STATE.currentUploadXhr = xhr;

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                document.getElementById('uploadPercent').textContent = percent + '%';
                document.getElementById('uploadProgressFill').style.width = percent + '%';
            }
        });

        xhr.addEventListener('load', function () {
            STATE.isUploading = false;
            document.getElementById('uploadProgress').style.display = 'none';

            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success) {
                    document.getElementById('currentVideoName').textContent = data.file_name;
                    document.getElementById('currentVideoSize').textContent = data.file_size;
                    document.getElementById('currentVideoDuration').textContent = formatDuration(data.duration_seconds);
                    document.getElementById('videoPlaceholder').style.display = 'none';

                    const videoPreview = document.getElementById('videoPreview');
                    videoPreview.src = '../../' + data.video_url;
                    videoPreview.style.display = 'block';

                    showNotification('Video uploaded successfully!', 'success');
                } else {
                    showNotification(data.error || 'Upload failed', 'error');
                }
            } catch (e) {
                showNotification('Upload failed', 'error');
            }
        });

        xhr.addEventListener('error', function () {
            STATE.isUploading = false;
            document.getElementById('uploadProgress').style.display = 'none';
            showNotification('Upload failed', 'error');
        });

        xhr.open('POST', AJAX_BASE + 'upload-handler.php');
        xhr.send(formData);
    }

    window.cancelUpload = function () {
        if (STATE.currentUploadXhr) {
            STATE.currentUploadXhr.abort();
            STATE.currentUploadXhr = null;
        }
        STATE.isUploading = false;
        document.getElementById('uploadProgress').style.display = 'none';
        showNotification('Upload cancelled', 'info');
    };

    // Thumbnail Upload Functions
    window.handleThumbnailUpload = function (event) {
        const file = event.target.files[0];
        if (!file) return;

        if (!STATE.currentLectureId) {
            showNotification('Please select a lecture first', 'error');
            return;
        }

        // Validate file type
        if (!file.type.startsWith('image/')) {
            showNotification('Please select an image file', 'error');
            return;
        }

        // Max 5MB for thumbnails
        if (file.size > 5 * 1024 * 1024) {
            showNotification('Image too large. Maximum 5MB allowed', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'upload_thumbnail');
        formData.append('course_id', COURSE_ID);
        formData.append('lecture_id', STATE.currentLectureId);
        formData.append('thumbnail', file);

        fetch(AJAX_BASE + 'upload-handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {

                console.log(data);
                if (data.success) {
                    document.getElementById('thumbnailImage').src = '../../' + data.thumbnail_url;
                    document.getElementById('thumbnailImage').style.display = 'block';
                    document.getElementById('thumbnailPlaceholder').style.display = 'none';
                    document.getElementById('removeThumbnailBtn').style.display = 'block';
                    showNotification('Thumbnail uploaded!', 'success');
                } else {
                    showNotification(data.error || 'Failed to upload thumbnail', 'error');
                }
            })
            .catch(error => {
                console.error('Thumbnail upload error:', error);
                showNotification('Failed to upload thumbnail', 'error');
            });
    };

    window.removeThumbnail = function () {
        if (!STATE.currentLectureId) return;

        const formData = new FormData();
        formData.append('action', 'delete_thumbnail');
        formData.append('course_id', COURSE_ID);
        formData.append('lecture_id', STATE.currentLectureId);

        fetch(AJAX_BASE + 'upload-handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('thumbnailImage').src = '';
                    document.getElementById('thumbnailImage').style.display = 'none';
                    document.getElementById('thumbnailPlaceholder').style.display = 'flex';
                    document.getElementById('removeThumbnailBtn').style.display = 'none';
                    showNotification('Thumbnail removed!', 'success');
                } else {
                    showNotification(data.error || 'Failed to remove thumbnail', 'error');
                }
            });
    };

    window.showEmbedModal = function () {
        document.getElementById('embedUrl').value = '';
        document.getElementById('embedVideoModal').style.display = 'flex';
    };

    window.saveEmbedVideo = function (event) {
        event.preventDefault();

        if (!STATE.currentLectureId) {
            showNotification('Please select a lecture first', 'error');
            return;
        }

        const url = document.getElementById('embedUrl').value;

        const formData = new FormData();
        formData.append('action', 'embed_video');
        formData.append('course_id', COURSE_ID);
        formData.append('lecture_id', STATE.currentLectureId);
        formData.append('embed_url', url);

        fetch(AJAX_BASE + 'upload-handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('currentVideoName').textContent = 'Embedded: ' + url;
                    const placeholder = document.getElementById('videoPlaceholder');
                    placeholder.innerHTML = `<iframe width="100%" height="100%" src="${data.embed_url}" frameborder="0" allowfullscreen></iframe>`;
                    placeholder.style.display = 'block';
                    document.getElementById('videoPreview').style.display = 'none';

                    closeModal('embedVideoModal');
                    showNotification('Video embedded successfully!', 'success');
                } else {
                    showNotification(data.error || 'Failed to embed video', 'error');
                }
            })
            .catch(error => {
                showNotification('Failed to embed video', 'error');
            });
    };

    window.previewCourse = function () {
        window.open(AJAX_BASE + '/course/' + COURSE_ID, '_blank');
    };

    window.publishChanges = function () {
        if (confirm('Publish all changes? Students will see the updated content.')) {
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('course_id', COURSE_ID);
            formData.append('status', 'published');

            fetch(AJAX_BASE + 'course-handler.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Course published successfully!', 'success');
                        // Update status badge
                        const statusEl = document.querySelector('.course-meta .status');
                        if (statusEl) {
                            statusEl.className = 'status published';
                            statusEl.textContent = 'Published';
                        }
                    } else {
                        showNotification(data.error || 'Failed to publish', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Failed to publish', 'error');
                });
        }
    };

    window.deleteCourseFromManage = function () {
        if (!COURSE_ID) {
            showNotification('Course ID not found', 'error');
            return;
        }

        const courseTitle = document.getElementById('courseTitle')?.textContent || 'this course';

        if (!confirm(`Are you sure you want to delete "${courseTitle}"?\n\nThis action cannot be undone. All course content, sections, lectures, and enrollments will be permanently deleted.`)) {
            return;
        }

        // Second confirmation for safety
        if (!confirm('Please confirm: This will PERMANENTLY DELETE all course data. Continue?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('course_id', COURSE_ID);

        // Show loading state
        const deleteBtn = event.target.closest('button');
        if (deleteBtn) {
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<span class="material-icons">hourglass_empty</span> Deleting...';
        }

        fetch(AJAX_BASE + 'course-handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Course deleted successfully. Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = 'index.php#courses';
                    }, 1500);
                } else {
                    showNotification(data.error || 'Failed to delete course', 'error');
                    if (deleteBtn) {
                        deleteBtn.disabled = false;
                        deleteBtn.innerHTML = '<span class="material-icons">delete</span> Delete';
                    }
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                showNotification('Failed to delete course', 'error');
                if (deleteBtn) {
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = '<span class="material-icons">delete</span> Delete';
                }
            });
    };

    window.addObjective = function () {
        const list = document.getElementById('objectivesList');
        const item = document.createElement('div');
        item.className = 'objective-item';
        item.innerHTML = `
            <span class="material-icons">check_circle</span>
            <input type="text" value="" placeholder="Learning objective" onblur="saveObjectives()">
            <button class="btn-icon" onclick="removeObjective(this)">
                <span class="material-icons">close</span>
            </button>
        `;
        list.appendChild(item);
        item.querySelector('input').focus();
    };

    window.removeObjective = function (btn) {
        btn.closest('.objective-item').remove();
        saveObjectives();
    };

    window.saveObjectives = function () {
        if (!STATE.currentLectureId) {
            console.log('No lecture selected');
            return;
        }

        const objectives = [];
        document.querySelectorAll('#objectivesList .objective-item input').forEach(input => {
            const value = input.value.trim();
            if (value) {
                objectives.push(value);
            }
        });

        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('course_id', COURSE_ID);
        formData.append('lecture_id', STATE.currentLectureId);
        formData.append('learning_objectives', JSON.stringify(objectives));

        fetch(AJAX_BASE + 'lecture-handler.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Objectives saved');
                } else {
                    showNotification(data.error || 'Failed to save objectives', 'error');
                }
            })
            .catch(error => {
                console.error('Error saving objectives:', error);
            });
    };

    window.closeModal = function (modalId) {
        document.getElementById(modalId).style.display = 'none';
    };

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = 'toast-notification ' + type;
        notification.innerHTML = `
            <span class="material-icons">${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}</span>
            <span>${message}</span>
        `;
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: ${type === 'success' ? '#28A745' : type === 'error' ? '#DC3545' : '#17A2B8'};
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 3000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

})();
