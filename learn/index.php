<?php
require_once '../includes/config.php';

// Must be logged in
if (!$is_logged_in) {
    redirect('login');
}

$course_id = isset($_GET['id']) ? mysqli_real_escape_string($con, $_GET['id']) : '';
$lecture_id = isset($_GET['lecture']) ? mysqli_real_escape_string($con, $_GET['lecture']) : '';

if (empty($course_id)) {
    redirect('dashboard');
}

// Check access: user must be enrolled OR be the course owner/instructor
// Course must be published for enrolled students, instructor can preview unpublished
$access_query = "SELECT 
                    c.course_id, c.title as course_title, c.instructor_id, c.thumbnail_url, c.status,
                    CASE 
                        WHEN c.instructor_id = '$current_user_id' THEN 'instructor'
                        WHEN e.enrollment_id IS NOT NULL THEN 'enrolled'
                        ELSE 'none'
                    END as access_type,
                    e.progress_percentage as enrollment_progress
                 FROM courses c
                 LEFT JOIN enrollments e ON c.course_id = e.course_id AND e.student_id = '$current_user_id'
                 WHERE c.course_id = '$course_id'";

// Get enrollment details for learning time
$enrollment_data = [
    'total_learning_time_seconds' => 0,
    'enrollment_date' => null
];
$enrollment_query = "SELECT total_learning_time_seconds, enrollment_date FROM enrollments WHERE course_id = '$course_id' AND student_id = '$current_user_id'";
$enrollment_result_data = mysqli_query($con, $enrollment_query);
if ($enrollment_result_data && $e_row = mysqli_fetch_assoc($enrollment_result_data)) {
    $enrollment_data = $e_row;
}
$total_learning_time = (int)($enrollment_data['total_learning_time_seconds'] ?? 0);
$access_result = mysqli_query($con, $access_query);

if (!$access_result || mysqli_num_rows($access_result) == 0) {
    redirect('dashboard');
}

$course = mysqli_fetch_assoc($access_result);

// Check if user is admin
$user_role = strtolower($_SESSION['role'] ?? 'student');
$is_admin = ($user_role === 'admin');

// Check if course is published (required for enrolled students, instructor can preview)
$is_instructor = ($course['access_type'] === 'instructor');
$is_enrolled = ($course['access_type'] === 'enrolled');
$is_published = ($course['status'] === 'published');

// Access rules:
// 1. Admin can always access any course (preview mode)
// 2. Instructor can always access (preview mode for unpublished)
// 3. Enrolled students can only access published courses
// 4. Non-enrolled users have no access
if (!$is_admin && $course['access_type'] === 'none') {
   redirect('course/' . $course_id);
    exit;
}

if (!$is_admin && $is_enrolled && !$is_published) {
    redirect('course/' . $course_id);
    exit;
}

// Instructor and Admin are in preview mode - can view but NOT update progress
// All facilities same as student (no manage tab, no special privileges)
$is_preview_mode = $is_instructor || $is_admin;

// Get course sections and lectures
$sections_query = "SELECT cs.section_id, cs.title, cs.display_order
                   FROM course_sections cs
                   WHERE cs.course_id = '$course_id'
                   ORDER BY cs.display_order ASC";
$sections_result = mysqli_query($con, $sections_query);

$sections = [];
while ($sections_result && $section = mysqli_fetch_assoc($sections_result)) {
    $section_id = $section['section_id'];
    
    // Get lectures for this section
    $lectures_query = "SELECT l.lecture_id, l.title, l.description, l.content_url as video_url, 
                              l.thumbnail_url, l.video_source, l.duration_seconds as duration, 
                              l.display_order, l.is_preview, l.learning_objectives, l.subtitles,
                              lp.is_completed, lp.watch_duration_seconds as watch_time
                       FROM lectures l
                       LEFT JOIN lecture_progress lp ON l.lecture_id = lp.lecture_id AND lp.student_id = '$current_user_id'
                       WHERE l.section_id = '$section_id'
                       ORDER BY l.display_order ASC";
    $lectures_result = mysqli_query($con, $lectures_query);
    
    $section['lectures'] = [];
    while ($lectures_result && $lecture = mysqli_fetch_assoc($lectures_result)) {
        $section['lectures'][] = $lecture;
        
        // Set first lecture as default if none specified
        if (empty($lecture_id) && empty($current_lecture)) {
            $current_lecture = $lecture;
            $lecture_id = $lecture['lecture_id'];
        }
        
        // Get the current lecture data
        if ($lecture['lecture_id'] === $lecture_id) {
            $current_lecture = $lecture;
            $current_section = $section;
        }
    }
    
    $sections[] = $section;
}

// If still no lecture, course might be empty
if (empty($current_lecture)) {
    $current_lecture = [
        'lecture_id' => '',
        'title' => 'No lectures available',
        'video_url' => '',
        'duration' => 0,
        'description' => '',
        'thumbnail_url' => '',
        'video_source' => '',
        'learning_objectives' => null
    ];
}

// Parse learning objectives
$objectives = [];
if (!empty($current_lecture['learning_objectives'])) {
    $objectives = json_decode($current_lecture['learning_objectives'], true) ?? [];
}

// Calculate total lectures and completed
$total_lectures = 0;
$completed_lectures = 0;
foreach ($sections as $s) {
    foreach ($s['lectures'] as $l) {
        $total_lectures++;
        if (!empty($l['is_completed'])) {
            $completed_lectures++;
        }
    }
}
$progress_percent = $total_lectures > 0 ? round(($completed_lectures / $total_lectures) * 100) : 0;

// Get instructor info
$instructor_query = "SELECT user_id, CONCAT(first_name, ' ', last_name) as name, profile_image_url as avatar FROM users WHERE user_id = '{$course['instructor_id']}'";
$instructor_result = mysqli_query($con, $instructor_query);
$instructor = mysqli_fetch_assoc($instructor_result) ?? ['name' => 'Unknown', 'avatar' => ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($current_lecture['title']); ?> - <?php echo htmlspecialchars($course['course_title']); ?> | EduVerse</title>
    <meta name="description" content="Watch <?php echo htmlspecialchars($current_lecture['title']); ?> from <?php echo htmlspecialchars($course['course_title']); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo $base_url; ?>public/images/favicon.svg">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/player.css">
</head>
<body class="player-page">
    <!-- Top Navigation Bar -->
    <nav class="player-nav">
        <div class="nav-left">
            <a href="javascript:history.back()" class="btn-back">
                <span class="material-icons">arrow_back</span>
                <span>Back</span>
            </a>
            <div class="course-info">
                <h1 class="course-title"><?php echo htmlspecialchars($course['course_title']); ?></h1>
                <p class="lecture-title" id="currentLectureTitle"><?php echo htmlspecialchars($current_lecture['title']); ?></p>
            </div>
        </div>
        <div class="nav-right">
            <div class="progress-info">
                <span><?php echo $completed_lectures; ?> / <?php echo $total_lectures; ?> lectures</span>
                <div class="mini-progress">
                    <div class="mini-progress-fill" style="width: <?php echo $progress_percent; ?>%"></div>
                </div>
            </div>
            <button class="btn-icon" id="btnToggleSidebar" title="Toggle Sidebar">
                <span class="material-icons">menu</span>
            </button>
        </div>
    </nav>

    <!-- Main Player Layout -->
    <div class="player-layout">
        <!-- Sidebar - Lecture List -->
        <aside class="lecture-sidebar" id="lectureSidebar">
            <div class="sidebar-header">
                <h3>Course Content</h3>
                <button class="btn-icon btn-close-sidebar" id="btnCloseSidebar">
                    <span class="material-icons">close</span>
                </button>
            </div>
            
            <div class="sidebar-progress">
                <div class="progress-stats">
                    <span><strong id="completedCount"><?php echo $completed_lectures; ?></strong> of <?php echo $total_lectures; ?> lectures</span>
                    <span><strong id="progressPercent"><?php echo $progress_percent; ?>%</strong> complete</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="sidebarProgress" style="width: <?php echo $progress_percent; ?>%"></div>
                </div>
                <?php if (!$is_preview_mode): ?>
                <div class="learning-time-stats">
                    <div class="time-stat">
                        <span class="material-icons">schedule</span>
                        <div class="time-info">
                            <span class="time-label">Session</span>
                            <span class="time-value" id="sessionTime">0:00</span>
                        </div>
                    </div>
                    <div class="time-stat">
                        <span class="material-icons">hourglass_bottom</span>
                        <div class="time-info">
                            <span class="time-label">Total</span>
                            <span class="time-value" id="totalLearningTime"><?php 
                                $hours = floor($total_learning_time / 3600);
                                $mins = floor(($total_learning_time % 3600) / 60);
                                $secs = $total_learning_time % 60;
                                if ($hours > 0) {
                                    echo $hours . ':' . str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
                                } else {
                                    echo $mins . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
                                }
                            ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="sidebar-search">
                <input type="text" id="searchLectures" placeholder="Search lectures...">
                <span class="material-icons">search</span>
            </div>

            <div class="sections-list" id="sectionsList">
                <?php foreach ($sections as $index => $section): ?>
                <div class="section-item <?php echo $index === 0 ? 'open' : ''; ?>">
                    <div class="section-header" onclick="toggleSection(this)">
                        <button class="section-toggle">
                            <span class="material-icons">chevron_right</span>
                        </button>
                        <div class="section-info">
                            <h4><?php echo htmlspecialchars($section['title']); ?></h4>
                            <span class="section-meta"><?php echo count($section['lectures']); ?> lectures</span>
                        </div>
                    </div>
                    <div class="section-lectures">
                        <?php foreach ($section['lectures'] as $lecture): ?>
                        <div class="lecture-item <?php echo $lecture['lecture_id'] === $lecture_id ? 'active' : ''; ?> <?php echo $lecture['is_completed'] ? 'completed' : ''; ?>" 
                             data-lecture-id="<?php echo $lecture['lecture_id']; ?>"
                             onclick="loadLecture('<?php echo $lecture['lecture_id']; ?>')">
                            <span class="lecture-status">
                                <?php if ($lecture['is_completed']): ?>
                                    <span class="material-icons">check_circle</span>
                                <?php else: ?>
                                    <span class="material-icons">play_circle_outline</span>
                                <?php endif; ?>
                            </span>
                            <div class="lecture-info">
                                <p class="lecture-name"><?php echo htmlspecialchars($lecture['title']); ?></p>
                                <span class="lecture-duration"><?php echo gmdate("i:s", $lecture['duration'] ?? 0); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="player-main">
            <!-- Custom Video Player -->
            <div class="custom-player" id="customPlayer">
                <?php 
                    $video_src = '';
                    $thumbnail_src = '';
                    $subtitles = [];
                    
                    if (!empty($current_lecture['video_url'])) {
                        if ($current_lecture['video_source'] === 'upload') {
                            $video_src = $base_url . $current_lecture['video_url'];
                        } else {
                            $video_src = $current_lecture['video_url'];
                        }
                    }
                    
                    if (!empty($current_lecture['thumbnail_url'])) {
                        $thumbnail_src = $base_url . $current_lecture['thumbnail_url'];
                    }
                    
                    // Parse subtitles JSON
                    if (!empty($current_lecture['subtitles'])) {
                        $subtitles = json_decode($current_lecture['subtitles'], true) ?? [];
                    }
                ?>
                
                <!-- Video Element -->
                <div class="player-video-wrapper">
                    <video id="videoPlayer" 
                           <?php echo $thumbnail_src ? 'poster="' . htmlspecialchars($thumbnail_src) . '"' : ''; ?>
                           preload="metadata">
                        <?php if (!empty($video_src)): ?>
                        <source src="<?php echo htmlspecialchars($video_src); ?>" type="video/mp4">
                        <?php endif; ?>
                        <?php foreach ($subtitles as $sub): ?>
                        <track kind="subtitles" 
                               src="<?php echo $base_url . $sub['file']; ?>" 
                               srclang="<?php echo $sub['lang']; ?>" 
                               label="<?php echo $sub['lang'] === 'en' ? 'English' : ($sub['lang'] === 'bn' ? 'বাংলা' : strtoupper($sub['lang'])); ?>">
                        <?php endforeach; ?>
                    </video>
                    
                    <!-- Loading Spinner -->
                    <div class="player-loader" id="playerLoader">
                        <div class="spinner"></div>
                    </div>
                    
                    <!-- Big Play Button -->
                    <div class="player-big-play" id="bigPlayBtn">
                        <span class="material-icons">school</span>
                    </div>
                    
                    <!-- Video Placeholder -->
                    <?php if (empty($video_src)): ?>
                    <div class="player-placeholder">
                        <span class="material-icons">videocam_off</span>
                        <p>No video available for this lecture</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Player Controls -->
                <div class="player-controls" id="playerControls">
                    <!-- Progress Bar -->
                    <div class="player-progress">
                        <div class="progress-bar" id="progressBar">
                            <div class="progress-buffered" id="progressBuffered"></div>
                            <div class="progress-played" id="progressPlayed"></div>
                            <div class="progress-handle" id="progressHandle"></div>
                        </div>
                        <div class="progress-tooltip" id="progressTooltip">0:00</div>
                    </div>
                    
                    <!-- Control Buttons -->
                    <div class="player-controls-row">
                        <!-- Left Controls -->
                        <div class="controls-left">
                            <button class="player-btn" id="btnPlayPause" title="Play/Pause (Space)">
                                <span class="material-icons" id="playPauseIcon">play_arrow</span>
                            </button>
                            
                            <button class="player-btn" id="btnRewind" title="Rewind 10s (←)">
                                <span class="material-icons">replay_10</span>
                            </button>
                            
                            <button class="player-btn" id="btnForward" title="Forward 10s (→)">
                                <span class="material-icons">forward_10</span>
                            </button>
                            
                            <!-- Volume -->
                            <div class="player-volume">
                                <button class="player-btn" id="btnMute" title="Mute (M)">
                                    <span class="material-icons" id="volumeIcon">volume_up</span>
                                </button>
                                <div class="volume-slider-wrapper">
                                    <input type="range" class="volume-slider" id="volumeSlider" min="0" max="100" value="100">
                                </div>
                            </div>
                            
                            <!-- Time Display -->
                            <div class="player-time">
                                <span id="currentTime">0:00</span>
                                <span>/</span>
                                <span id="totalTime">0:00</span>
                            </div>
                        </div>
                        
                        <!-- Right Controls -->
                        <div class="controls-right">
                            <!-- Playback Speed -->
                            <div class="player-dropdown" id="speedDropdown">
                                <button class="player-btn player-btn-text" id="btnSpeed" title="Playback Speed">
                                    <span id="speedText">1x</span>
                                </button>
                                <div class="dropdown-menu" id="speedMenu">
                                    <button data-speed="0.25">0.25x</button>
                                    <button data-speed="0.5">0.5x</button>
                                    <button data-speed="0.75">0.75x</button>
                                    <button data-speed="1" class="active">Normal</button>
                                    <button data-speed="1.25">1.25x</button>
                                    <button data-speed="1.5">1.5x</button>
                                    <button data-speed="1.75">1.75x</button>
                                    <button data-speed="2">2x</button>
                                </div>
                            </div>
                            
                            <!-- Subtitles -->
                            <?php if (!empty($subtitles)): ?>
                            <div class="player-dropdown" id="captionDropdown">
                                <button class="player-btn" id="btnCaptions" title="Subtitles/CC (C)">
                                    <span class="material-icons">closed_caption</span>
                                </button>
                                <div class="dropdown-menu" id="captionMenu">
                                    <button data-track="-1" class="active">Off</button>
                                    <?php foreach ($subtitles as $index => $sub): ?>
                                    <button data-track="<?php echo $index; ?>">
                                        <?php echo $sub['lang'] === 'en' ? 'English' : ($sub['lang'] === 'bn' ? 'বাংলা' : strtoupper($sub['lang'])); ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Settings -->
                            <div class="player-dropdown" id="settingsDropdown">
                                <button class="player-btn" id="btnSettings" title="Settings">
                                    <span class="material-icons">settings</span>
                                </button>
                                <div class="dropdown-menu settings-menu" id="settingsMenu">
                                    <div class="settings-item">
                                        <span>Autoplay next</span>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="toggleAutoplay" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Picture in Picture -->
                            <button class="player-btn" id="btnPiP" title="Picture-in-Picture (P)">
                                <span class="material-icons">picture_in_picture_alt</span>
                            </button>
                            
                            <!-- Fullscreen -->
                            <button class="player-btn" id="btnFullscreen" title="Fullscreen (F)">
                                <span class="material-icons" id="fullscreenIcon">fullscreen</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lecture Navigation -->
            <div class="lecture-navigation">
                <button class="btn-nav btn-prev" id="btnPrevLecture">
                    <span class="material-icons">navigate_before</span>
                    <span>Previous</span>
                </button>
                <button class="btn-mark-complete" id="btnMarkComplete">
                    <span class="material-icons">check_circle</span>
                    <span>Mark as Complete</span>
                </button>
                <button class="btn-nav btn-next" id="btnNextLecture">
                    <span>Next</span>
                    <span class="material-icons">navigate_next</span>
                </button>
            </div>

            <!-- Tabs Section -->
            <div class="content-tabs">
                <div class="tabs-nav">
                    <button class="tab-btn active" data-tab="overview">Overview</button>
                    <?php if (!$is_preview_mode): ?>
                    <button class="tab-btn" data-tab="notes">My Notes</button>
                    <?php endif; ?>
                    <button class="tab-btn" data-tab="resources">Resources</button>
                </div>

                <!-- Overview Tab -->
                <div class="tab-content active" id="tab-overview">
                    <div class="tab-panel">
                        <h2 id="lectureTitle"><?php echo htmlspecialchars($current_lecture['title']); ?></h2>
                        <div class="lecture-meta">
                            <span class="instructor-info">
                                <?php 
                                    $avatar_src = 'https://ui-avatars.com/api/?name=' . urlencode($instructor['name']);
                                    if (!empty($instructor['avatar'])) {
                                        $avatar_src = $base_url  . $instructor['avatar'];
                                    }
                                ?>
                                <img src="<?php echo $avatar_src; ?>" alt="">
                                <?php echo htmlspecialchars($instructor['name']); ?>
                            </span>
                            <?php if (!empty($current_lecture['video_source'])): ?>
                            <span class="video-source-badge">
                                <span class="material-icons"><?php echo $current_lecture['video_source'] === 'upload' ? 'cloud_upload' : 'link'; ?></span>
                                <?php echo $current_lecture['video_source'] === 'upload' ? 'Uploaded' : 'Embedded'; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($current_lecture['description'])): ?>
                        <div class="lecture-description" id="lectureDescription">
                            <h3>About this lecture</h3>
                            <p><?php echo nl2br(htmlspecialchars($current_lecture['description'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($objectives)): ?>
                        <div class="learning-objectives">
                            <h3>Learning Objectives</h3>
                            <ul class="objectives-list">
                                <?php foreach ($objectives as $objective): ?>
                                <li>
                                    <span class="material-icons">check_circle</span>
                                    <?php echo htmlspecialchars($objective); ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$is_preview_mode): ?>
                <!-- Notes Tab -->
                <div class="tab-content" id="tab-notes">
                    <div class="tab-panel">
                        <div class="notes-header">
                            <h2>My Notes</h2>
                            <button class="btn-primary" id="btnAddNote">
                                <span class="material-icons">add</span>
                                Add Note
                            </button>
                        </div>
                        <div class="notes-list" id="notesList">
                            <div class="empty-state">
                                <span class="material-icons">note_add</span>
                                <p>No notes yet</p>
                                <p class="subtitle">Take notes while watching to remember key points</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Resources Tab -->
                <div class="tab-content" id="tab-resources">
                    <div class="tab-panel">
                        <h2>Resources</h2>
                        <div class="resources-list" id="resourcesList">
                            <div class="empty-state">
                                <span class="material-icons">folder_open</span>
                                <p>No resources for this lecture</p>
                            </div>
                        </div>
                    </div>
                </div>


            </div>
        </main>
    </div>

    <!-- Add Note Modal -->
    <div class="modal" id="noteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Note</h3>
                <button class="modal-close" onclick="closeModal('noteModal')">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Note at <strong id="noteTimestamp">0:00</strong></label>
                    <textarea id="noteText" rows="5" placeholder="Type your note here..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('noteModal')">Cancel</button>
                <button class="btn-primary" onclick="saveNote()">Save Note</button>
            </div>
        </div>
    </div>

    <!-- Course Completion Modal -->
    <div class="modal" id="completionModal">
        <div class="modal-content completion-modal">
            <div class="completion-icon">
                <span class="material-icons">emoji_events</span>
            </div>
            <h2>Congratulations! 🎉</h2>
            <p>You have successfully completed <strong><?php echo htmlspecialchars($course['course_title']); ?></strong></p>
            <div class="completion-stats">
                <div class="stat-item">
                    <span class="material-icons">school</span>
                    <span><?php echo $total_lectures; ?> Lectures Completed</span>
                </div>
                <div class="stat-item">
                    <span class="material-icons">schedule</span>
                    <span id="completionLearningTime">Total Learning Time</span>
                </div>
            </div>
            <div class="completion-actions">
                <button class="btn-secondary" onclick="closeModal('completionModal')">Continue Learning</button>
                <a href="../certificate.php?course=<?php echo $course_id; ?>" class="btn-primary">
                    <span class="material-icons">workspace_premium</span>
                    Get Certificate
                </a>
            </div>
        </div>
    </div>

    <style>
        /* Learning Time Stats */
        .learning-time-stats {
            display: flex;
            gap: 16px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .time-stat {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }
        .time-stat .material-icons {
            font-size: 18px;
            color: var(--primary-light, #818cf8);
        }
        .time-info {
            display: flex;
            flex-direction: column;
        }
        .time-label {
            font-size: 10px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.6);
        }
        .time-value {
            font-size: 14px;
            font-weight: 600;
            color: #fff;
        }

        /* Completion Modal */
        .completion-modal {
            text-align: center;
            max-width: 450px;
        }
        .completion-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f59e0b, #eab308);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .completion-icon .material-icons {
            font-size: 40px;
            color: #fff;
        }
        .completion-modal h2 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        .completion-modal > p {
            color: var(--text-muted, #94a3b8);
            margin-bottom: 24px;
        }
        .completion-stats {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-bottom: 24px;
            padding: 16px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 12px;
        }
        .completion-stats .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .completion-stats .material-icons {
            color: var(--primary, #6366f1);
        }
        .completion-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .completion-actions .btn-primary {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Completion Notification (fallback) */
        .completion-notification {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .completion-notification.show { opacity: 1; }
        .completion-content {
            background: var(--bg-card, #1e293b);
            padding: 32px 48px;
            border-radius: 16px;
            text-align: center;
        }
        .completion-content .material-icons {
            font-size: 64px;
            color: #f59e0b;
            margin-bottom: 16px;
        }
        .completion-content h3 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        .completion-content p {
            color: var(--text-muted, #94a3b8);
            margin-bottom: 20px;
        }
        .completion-content button {
            padding: 10px 24px;
            background: var(--primary, #6366f1);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        /* Note Actions with Edit */
        .note-actions {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }
        .note-actions button {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            font-size: 12px;
            color: var(--text-muted);
            background: transparent;
            border: 1px solid transparent;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .note-actions button:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            border-color: rgba(99, 102, 241, 0.2);
        }
        .note-actions button .material-icons {
            font-size: 16px;
        }
    </style>

    <script>
        var COURSE_ID = '<?php echo $course_id; ?>';
        var CURRENT_LECTURE_ID = '<?php echo $lecture_id; ?>';
        var USER_ID = '<?php echo $current_user_id; ?>';
        var IS_PREVIEW_MODE = <?php echo $is_preview_mode ? 'true' : 'false'; ?>;
        var BASE_URL = '<?php echo $base_url; ?>';
        var AJAX_BASE = 'ajax/';
    </script>
    <script src="js/custom-player.js"></script>
    <script src="js/player.js"></script>
</body>
</html>
