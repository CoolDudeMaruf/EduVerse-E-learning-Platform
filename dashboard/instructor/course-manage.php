<?php
require_once '../../includes/config.php';

if (!$is_logged_in) {
    redirect('login');
}

if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'instructor') {
    redirect('dashboard');
}

$user_data = get_user_by_id($con, $current_user_id);
$full_name = trim(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? ''));
$full_name = $full_name ?: 'instructor';
$user_email = $user_data['email'] ?? '';
$user_avatar = $user_data['profile_image_url'] ?? '';
$avatar_url = $user_avatar ? $base_url . $user_avatar : 'https://ui-avatars.com/api/?name=' . urlencode($full_name) . '&background=6366f1&color=fff';

$course_id = isset($_GET['id']) ? mysqli_real_escape_string($con, $_GET['id']) : '';

if (empty($course_id)) {
    redirect('index.php#courses');
}

$course_query = "SELECT c.*, cat.name as category_name 
                 FROM courses c 
                 LEFT JOIN categories cat ON c.category_id = cat.category_id 
                 WHERE c.course_id = '$course_id' AND c.instructor_id = '$current_user_id'";
$course_result = mysqli_query($con, $course_query);

if (!$course_result || mysqli_num_rows($course_result) === 0) {
    redirect('index.php#courses');
}

$course = mysqli_fetch_assoc($course_result);

$sections_query = "SELECT * FROM course_sections WHERE course_id = '$course_id' ORDER BY display_order ASC";
$sections_result = mysqli_query($con, $sections_query);
$sections = [];
while ($row = mysqli_fetch_assoc($sections_result)) {
    $sections[] = $row;
}

$lectures_query = "SELECT * FROM lectures WHERE course_id = '$course_id' ORDER BY section_id, display_order ASC";
$lectures_result = mysqli_query($con, $lectures_query);
$lectures_by_section = [];
while ($row = mysqli_fetch_assoc($lectures_result)) {
    $sid = $row['section_id'];
    if (!isset($lectures_by_section[$sid])) {
        $lectures_by_section[$sid] = [];
    }
    $lectures_by_section[$sid][] = $row;
}

$total_sections = count($sections);
$total_lectures = 0;
$total_duration = 0;
foreach ($lectures_by_section as $section_lectures) {
    $total_lectures += count($section_lectures);
    foreach ($section_lectures as $lec) {
        $total_duration += $lec['duration_minutes'];
    }
}
$hours = floor($total_duration / 60);
$mins = $total_duration % 60;
$duration_str = $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";

$status_class = strtolower($course['status']);
$status_label = ucfirst($course['status']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Course - EduVerse Instructor</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo $base_url; ?>public/images/favicon.svg">

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/instructor-dashboard.css">
    <link rel="stylesheet" href="css/course-manage.css">
</head>

<body>
    <nav class="course-nav">
        <div class="nav-left">
            <a href="index.php#courses" class="btn-back">
                <span class="material-icons">arrow_back</span>
                <span>Back to Dashboard</span>
            </a>
            <div class="course-info">
                <h1 id="courseTitle"><?php echo htmlspecialchars($course['title']); ?></h1>
                <div class="course-meta">
                    <span class="status <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                    <span class="separator">•</span>
                    <span><?php echo $total_sections; ?> sections</span>
                    <span class="separator">•</span>
                    <span><?php echo $total_lectures; ?> lectures</span>
                    <span class="separator">•</span>
                    <span><?php echo $duration_str; ?> total</span>
                </div>
            </div>
        </div>
        <div class="nav-right">
            <button class="btn-secondary" onclick="window.open('<?= $base_url ?>/learn?id=' + COURSE_ID, '_blank')">
                <span class="material-icons">visibility</span>
                Preview
            </button>
            <button class="btn-primary" onclick="publishChanges()">
                <span class="material-icons">publish</span>
                Publish Changes
            </button>
        </div>
    </nav>

    <div class="manage-layout">
        <aside class="sections-sidebar" id="sectionsSidebar">
            <div class="sidebar-header">
                <h3>Course Content</h3>
                <button class="btn-icon" onclick="addSection()" title="Add Section">
                    <span class="material-icons">add</span>
                </button>
            </div>

            <div class="sections-list" id="sectionsList">
                <?php foreach ($sections as $index => $section): 
                    $section_id = $section['section_id'];
                    $section_lectures = $lectures_by_section[$section_id] ?? [];
                    $lecture_count = count($section_lectures);
                    $section_duration = 0;
                    foreach ($section_lectures as $lec) {
                        $section_duration += $lec['duration_minutes'];
                    }
                    $section_duration_str = $section_duration >= 60 ? floor($section_duration/60) . 'h ' . ($section_duration%60) . 'min' : $section_duration . 'min';
                    $is_first = $index === 0;
                ?>
                <div class="section-item <?php echo $is_first ? 'open' : ''; ?>" data-section-id="<?php echo $section_id; ?>">
                    <div class="section-header">
                        <button class="section-toggle">
                            <span class="material-icons"><?php echo $is_first ? 'expand_more' : 'chevron_right'; ?></span>
                        </button>
                        <div class="section-info" onclick="selectSection(<?php echo $section_id; ?>)">
                            <span class="section-number"><?php echo $index + 1; ?></span>
                            <div class="section-details">
                                <h4><?php echo htmlspecialchars($section['title']); ?></h4>
                                <span class="section-meta"><?php echo $lecture_count; ?> lectures • <?php echo $section_duration_str; ?></span>
                            </div>
                        </div>
                        <div class="section-actions">
                            <button class="btn-icon" onclick="editSection(<?php echo $section_id; ?>)" title="Edit">
                                <span class="material-icons">edit</span>
                            </button>
                            <button class="btn-icon" onclick="deleteSection(<?php echo $section_id; ?>)" title="Delete">
                                <span class="material-icons">delete</span>
                            </button>
                        </div>
                    </div>
                    <div class="lectures-list">
                        <?php foreach ($section_lectures as $lec_index => $lecture): 
                            $lecture_id = $lecture['lecture_id'];
                            $type_icons = [
                                'video' => 'play_circle',
                                'article' => 'article',
                                'quiz' => 'quiz',
                                'assignment' => 'assignment',
                                'resource' => 'folder',
                                'live_session' => 'live_tv'
                            ];
                            $icon = $type_icons[$lecture['lecture_type']] ?? 'play_circle';
                            $duration_mins = $lecture['duration_minutes'];
                            $duration_display = $duration_mins >= 60 ? floor($duration_mins/60) . ':' . str_pad($duration_mins%60, 2, '0', STR_PAD_LEFT) : '0:' . str_pad($duration_mins, 2, '0', STR_PAD_LEFT);
                            $is_first_lecture = $is_first && $lec_index === 0;
                        ?>
                        <div class="lecture-item <?php echo $is_first_lecture ? 'active' : ''; ?>" data-lecture-id="<?php echo $lecture_id; ?>" onclick="selectLecture(<?php echo $lecture_id; ?>)">
                            <span class="material-icons lecture-icon"><?php echo $icon; ?></span>
                            <div class="lecture-info">
                                <p class="lecture-name"><?php echo htmlspecialchars($lecture['title']); ?></p>
                                <span class="lecture-duration"><?php echo $duration_display; ?></span>
                            </div>
                            <div class="lecture-actions">
                                <button class="btn-icon" onclick="event.stopPropagation(); editLecture(<?php echo $lecture_id; ?>)">
                                    <span class="material-icons">edit</span>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <button class="btn-add-lecture" onclick="addLecture(<?php echo $section_id; ?>)">
                            <span class="material-icons">add</span>
                            Add Lecture
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <button class="btn-add-section" onclick="addSection()">
                <span class="material-icons">add</span>
                Add New Section
            </button>
        </aside>

        <main class="manage-main">
            <div class="lecture-editor" id="lectureEditor" <?php echo empty($sections) ? 'style="display:none;"' : ''; ?>>
                <div class="editor-header">
                    <div class="editor-title">
                        <span class="material-icons">play_circle</span>
                        <input type="text" id="lectureTitleInput" value="" placeholder="Lecture Title">
                    </div>
                    <div class="editor-actions">
                        <button class="btn-secondary" onclick="deleteLecture()">
                            <span class="material-icons">delete</span>
                            Delete
                        </button>
                        <button class="btn-primary" onclick="saveLecture()">
                            <span class="material-icons">save</span>
                            Save Changes
                        </button>
                    </div>
                </div>

                <div class="editor-tabs">
                    <button class="tab-btn active" data-tab="video">
                        <span class="material-icons">videocam</span>
                        Video
                    </button>
                    <button class="tab-btn" data-tab="content">
                        <span class="material-icons">description</span>
                        Content
                    </button>
                    <button class="tab-btn" data-tab="resources">
                        <span class="material-icons">folder</span>
                        Resources
                    </button>
                    <button class="tab-btn" data-tab="subtitles">
                        <span class="material-icons">subtitles</span>
                        Subtitles
                    </button>
                    <button class="tab-btn" data-tab="settings">
                        <span class="material-icons">settings</span>
                        Settings
                    </button>
                </div>

                <div class="tab-content active" id="videoTab">
                    <div class="video-upload-section">
                        <div class="current-video">
                            <div class="video-preview">
                                <video id="videoPreview" controls>
                                    <source src="" type="video/mp4">
                                </video>
                                <div class="video-placeholder" id="videoPlaceholder">
                                    <span class="material-icons">cloud_upload</span>
                                    <p>No video uploaded yet</p>
                                </div>
                            </div>
                            <div class="video-info">
                                <p><strong>Current video:</strong> <span id="currentVideoName">No video</span></p>
                                <p><strong>Duration:</strong> <span id="currentVideoDuration">0:00</span></p>
                                <p><strong>Size:</strong> <span id="currentVideoSize">0 MB</span></p>
                            </div>
                        </div>

                        <div class="upload-options">
                            <div class="upload-card" onclick="document.getElementById('videoUpload').click()">
                                <span class="material-icons">upload_file</span>
                                <h4>Upload Video</h4>
                                <p>MP4, WebM, or MOV (max 2GB)</p>
                                <input type="file" id="videoUpload" accept="video/*" hidden onchange="handleVideoUpload(event)">
                            </div>
                            <div class="upload-card" onclick="showEmbedModal()">
                                <span class="material-icons">link</span>
                                <h4>Embed Video</h4>
                                <p>YouTube, Vimeo, or other URL</p>
                            </div>
                        </div>

                        <div class="upload-progress" id="uploadProgress" style="display: none;">
                            <div class="progress-info">
                                <span id="uploadFileName">video.mp4</span>
                                <span id="uploadPercent">0%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" id="uploadProgressFill"></div>
                            </div>
                            <button class="btn-text" onclick="cancelUpload()">Cancel</button>
                        </div>

                        <!-- Thumbnail Upload Section -->
                        <div class="thumbnail-section">
                            <h4>Lecture Thumbnail</h4>
                            <div class="thumbnail-content">
                                <div class="thumbnail-preview" id="thumbnailPreview">
                                    <img id="thumbnailImage" src="" alt="Thumbnail" style="display: none;">
                                    <div class="thumbnail-placeholder" id="thumbnailPlaceholder">
                                        <span class="material-icons">image</span>
                                        <p>No thumbnail</p>
                                    </div>
                                </div>
                                <div class="thumbnail-actions">
                                    <button class="btn-secondary" onclick="document.getElementById('thumbnailUpload').click()">
                                        <span class="material-icons">upload</span>
                                        Upload Thumbnail
                                    </button>
                                    <input type="file" id="thumbnailUpload" accept="image/*" hidden onchange="handleThumbnailUpload(event)">
                                    <button class="btn-text" onclick="removeThumbnail()" id="removeThumbnailBtn" style="display: none;">
                                        <span class="material-icons">delete</span>
                                        Remove
                                    </button>
                                    <p class="thumbnail-hint">Recommended: 1280×720 (16:9), JPG or PNG</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="contentTab">
                    <div class="content-editor">
                        <div class="form-group">
                            <label>Lecture Description</label>
                            <textarea id="lectureDescription" rows="4" placeholder="Describe what students will learn in this lecture..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>Learning Objectives</label>
                            <div class="objectives-list" id="objectivesList">
                            </div>
                            <button class="btn-text" onclick="addObjective()">
                                <span class="material-icons">add</span>
                                Add Objective
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="resourcesTab">
                    <div class="resources-manager">
                        <div class="resources-header">
                            <h3>Lecture Resources</h3>
                            <button class="btn-primary" onclick="showAddResourceModal()">
                                <span class="material-icons">add</span>
                                Add Resource
                            </button>
                        </div>

                        <div class="resources-list" id="resourcesList">
                        </div>

                        <div class="empty-resources" id="emptyResources">
                            <span class="material-icons">folder_open</span>
                            <p>No resources added yet</p>
                            <button class="btn-primary" onclick="showAddResourceModal()">Add First Resource</button>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="subtitlesTab">
                    <div class="subtitles-manager">
                        <div class="subtitles-header">
                            <h3>Lecture Subtitles / Captions</h3>
                            <p class="subtitle-hint">Add subtitle files for different languages (.srt or .vtt format)</p>
                        </div>

                        <div class="existing-subtitles" id="existingSubtitles">
                            <!-- Existing subtitles will be loaded here -->
                        </div>

                        <div class="add-subtitle-section">
                            <h4>Add New Subtitle</h4>
                            <div class="subtitle-form">
                                <div class="subtitle-row">
                                    <select id="newSubtitleLang" class="subtitle-lang">
                                        <option value="en">English</option>
                                        <option value="bn">Bengali</option>
                                        <option value="hi">Hindi</option>
                                        <option value="es">Spanish</option>
                                        <option value="fr">French</option>
                                        <option value="ar">Arabic</option>
                                        <option value="zh">Chinese</option>
                                        <option value="ja">Japanese</option>
                                    </select>
                                    <input type="file" id="newSubtitleFile" class="subtitle-file" accept=".srt,.vtt">
                                    <button type="button" class="btn-primary" onclick="uploadSubtitle()">
                                        <span class="material-icons">upload</span>
                                        Upload
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="empty-subtitles" id="emptySubtitles">
                            <span class="material-icons">subtitles_off</span>
                            <p>No subtitles added yet</p>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="settingsTab">
                    <div class="settings-panel">
                        <div class="setting-group">
                            <h3>Lecture Settings</h3>

                            <div class="form-group">
                                <label>Lecture Type</label>
                                <select id="lectureType">
                                    <option value="video" selected>Video Lecture</option>
                                    <option value="article">Text Article</option>
                                    <option value="quiz">Quiz</option>
                                    <option value="assignment">Assignment</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="toggle-label">
                                    <span>Free Preview</span>
                                    <label class="switch">
                                        <input type="checkbox" id="freePreview">
                                        <span class="slider"></span>
                                    </label>
                                </label>
                                <small>Allow non-enrolled users to preview this lecture</small>
                            </div>

                            <div class="form-group">
                                <label class="toggle-label">
                                    <span>Downloadable Video</span>
                                    <label class="switch">
                                        <input type="checkbox" id="downloadable" checked>
                                        <span class="slider"></span>
                                    </label>
                                </label>
                                <small>Allow students to download this video for offline viewing</small>
                            </div>

                            <div class="form-group">
                                <label>Duration Override (minutes)</label>
                                <input type="number" id="durationOverride" placeholder="Auto-detected" min="1">
                                <small>Leave empty to use video duration</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="empty-state" id="emptyState" <?php echo !empty($sections) ? 'style="display: none;"' : ''; ?>>
                <span class="material-icons">movie_creation</span>
                <h3>Select a lecture to edit</h3>
                <p>Choose a lecture from the sidebar or add a new one</p>
            </div>
        </main>
    </div>

    <div id="addSectionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addSectionModal')">&times;</span>
            <h2>Add New Section</h2>
            <form id="addSectionForm" onsubmit="saveNewSection(event)">
                <div class="form-group">
                    <label for="newSectionTitle">Section Title *</label>
                    <input type="text" id="newSectionTitle" required placeholder="e.g., Introduction to Variables">
                </div>
                <div class="form-group">
                    <label for="newSectionDescription">Description (optional)</label>
                    <textarea id="newSectionDescription" rows="3" placeholder="Brief description of this section..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('addSectionModal')">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <span class="material-icons">add</span>
                        Add Section
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="addLectureModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addLectureModal')">&times;</span>
            <h2>Add New Lecture</h2>
            <form id="addLectureForm" onsubmit="saveNewLecture(event)">
                <input type="hidden" id="newLectureSectionId">
                <div class="form-group">
                    <label for="newLectureTitle">Lecture Title *</label>
                    <input type="text" id="newLectureTitle" required placeholder="e.g., Understanding Variables">
                </div>
                <div class="form-group">
                    <label for="newLectureType">Lecture Type *</label>
                    <select id="newLectureType" required>
                        <option value="video">Video Lecture</option>
                        <option value="article">Text Article</option>
                        <option value="quiz">Quiz</option>
                        <option value="assignment">Assignment</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('addLectureModal')">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <span class="material-icons">add</span>
                        Add Lecture
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="addResourceModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addResourceModal')">&times;</span>
            <h2>Add Resource</h2>
            <form id="addResourceForm" onsubmit="saveNewResource(event)">
                <div class="form-group">
                    <label for="resourceType">Resource Type *</label>
                    <select id="resourceType" required onchange="toggleResourceFields()">
                        <option value="file">Upload File</option>
                        <option value="link">External Link</option>
                    </select>
                </div>

                <div id="fileUploadFields">
                    <div class="form-group">
                        <label for="resourceFile">Select File *</label>
                        <input type="file" id="resourceFile" accept=".pdf,.zip,.doc,.docx,.txt,.ppt,.pptx">
                    </div>
                </div>

                <div id="linkFields" style="display: none;">
                    <div class="form-group">
                        <label for="resourceUrl">URL *</label>
                        <input type="url" id="resourceUrl" placeholder="https://example.com/resource">
                    </div>
                </div>

                <div class="form-group">
                    <label for="resourceName">Display Name *</label>
                    <input type="text" id="resourceName" required placeholder="e.g., Lecture Slides">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('addResourceModal')">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <span class="material-icons">add</span>
                        Add Resource
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="embedVideoModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('embedVideoModal')">&times;</span>
            <h2>Embed Video</h2>
            <form id="embedVideoForm" onsubmit="saveEmbedVideo(event)">
                <div class="form-group">
                    <label for="embedUrl">Video URL *</label>
                    <input type="url" id="embedUrl" required placeholder="https://youtube.com/watch?v=...">
                    <small>Supports YouTube, Vimeo, and other video platforms</small>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('embedVideoModal')">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <span class="material-icons">link</span>
                        Embed Video
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="editSectionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editSectionModal')">&times;</span>
            <h2>Edit Section</h2>
            <form id="editSectionForm" onsubmit="updateSection(event)">
                <input type="hidden" id="editSectionId">
                <div class="form-group">
                    <label for="editSectionTitle">Section Title *</label>
                    <input type="text" id="editSectionTitle" required placeholder="Section title">
                </div>
                <div class="form-group">
                    <label for="editSectionDescription">Description (optional)</label>
                    <textarea id="editSectionDescription" rows="3" placeholder="Brief description..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('editSectionModal')">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <span class="material-icons">save</span>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        var COURSE_ID = '<?php echo $course_id; ?>';
        var AJAX_BASE = 'ajax/';
    </script>
    <script src="js/course-manage.js"></script>
</body>

</html>
