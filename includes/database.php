<?php
function table_exists($con, $table_name) {
    $result = mysqli_query($con, "SHOW TABLES LIKE '$table_name'");
    return $result && mysqli_num_rows($result) > 0;
}

function create_all_tables($con) {
    $tables = [];
    
    $tables['users'] = "CREATE TABLE IF NOT EXISTS `users` (
        `user_id` VARCHAR(50) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `username` VARCHAR(100) DEFAULT NULL,
        `password_hash` VARCHAR(255) DEFAULT NULL,
        `first_name` VARCHAR(100) DEFAULT NULL,
        `last_name` VARCHAR(100) DEFAULT NULL,
        `profile_image_url` VARCHAR(500) DEFAULT NULL,
        `phone` VARCHAR(20) DEFAULT NULL,
        `country_code` VARCHAR(10) DEFAULT '+880',
        `country` VARCHAR(100) DEFAULT NULL,
        `date_of_birth` DATE DEFAULT NULL,
        `gender` VARCHAR(20) DEFAULT NULL,
        `blood_group` VARCHAR(10) DEFAULT NULL,
        `bio` TEXT,
        `headline` VARCHAR(255) DEFAULT NULL,
        `occupation` VARCHAR(100) DEFAULT NULL,
        `company` VARCHAR(100) DEFAULT NULL,
        `location` VARCHAR(255) DEFAULT NULL,
        `role` ENUM('student', 'instructor', 'admin', 'super_admin', 'inactive') DEFAULT 'student',
        `status` ENUM('active', 'suspended', 'pending', 'inactive') DEFAULT 'active',
        `email_verified` TINYINT(1) DEFAULT 0,
        `email_verification_token` VARCHAR(255) DEFAULT NULL,
        `password_reset_token` VARCHAR(255) DEFAULT NULL,
        `last_login_at` DATETIME DEFAULT NULL,
        `last_active_at` DATETIME DEFAULT NULL,
        `last_ip_address` VARCHAR(45) DEFAULT NULL,
        `total_learning_hours` DECIMAL(10,2) DEFAULT 0,
        `deleted_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`user_id`),
        UNIQUE KEY `email` (`email`),
        KEY `idx_role` (`role`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['user_preferences'] = "CREATE TABLE IF NOT EXISTS `user_preferences` (
        `preference_id` INT AUTO_INCREMENT,
        `user_id` VARCHAR(50) NOT NULL,
        `theme` VARCHAR(20) DEFAULT 'light',
        `email_notifications` TINYINT(1) DEFAULT 1,
        `push_notifications` TINYINT(1) DEFAULT 1,
        `newsletter_subscription` TINYINT(1) DEFAULT 1,
        `notification_email` TINYINT(1) DEFAULT 1,
        `notification_push` TINYINT(1) DEFAULT 1,
        `notification_sms` TINYINT(1) DEFAULT 0,
        `notification_course_updates` TINYINT(1) DEFAULT 1,
        `notification_new_messages` TINYINT(1) DEFAULT 1,
        `notification_achievements` TINYINT(1) DEFAULT 1,
        `notification_certificates` TINYINT(1) DEFAULT 1,
        `notification_promotions` TINYINT(1) DEFAULT 1,
        `privacy_profile_visible` TINYINT(1) DEFAULT 1,
        `privacy_courses_visible` TINYINT(1) DEFAULT 1,
        `privacy_achievements_visible` TINYINT(1) DEFAULT 1,
        `auto_play_videos` TINYINT(1) DEFAULT 1,
        `video_quality` VARCHAR(20) DEFAULT 'auto',
        `subtitle_language` VARCHAR(10) DEFAULT 'en',
        `language` VARCHAR(10) DEFAULT 'en',
        `timezone` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`preference_id`),
        UNIQUE KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['user_social_links'] = "CREATE TABLE IF NOT EXISTS `user_social_links` (
        `link_id` INT AUTO_INCREMENT,
        `user_id` VARCHAR(50) NOT NULL,
        `platform` VARCHAR(50) NOT NULL,
        `url` VARCHAR(500) NOT NULL,
        `display_order` INT DEFAULT 0,
        `is_public` TINYINT(1) DEFAULT 1,
        PRIMARY KEY (`link_id`),
        KEY `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['instructor_profiles'] = "CREATE TABLE IF NOT EXISTS `instructor_profiles` (
        `instructor_profile_id` INT AUTO_INCREMENT,
        `user_id` VARCHAR(50) NOT NULL,
        `instructor_headline` VARCHAR(255) DEFAULT NULL,
        `instructor_bio` TEXT,
        `teaching_experience` TEXT,
        `years_of_experience` VARCHAR(50) DEFAULT NULL,
        `expertise_areas` TEXT,
        `education` TEXT,
        `certifications` TEXT,
        `languages` TEXT,
        `teaching_style` TEXT,
        `payout_email` VARCHAR(255) DEFAULT NULL,
        `tax_id` VARCHAR(100) DEFAULT NULL,
        `tax_information` TEXT,
        `verification_status` ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        `verified_by` VARCHAR(50) DEFAULT NULL,
        `verified_at` DATETIME DEFAULT NULL,
        `is_featured` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`instructor_profile_id`),
        UNIQUE KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['categories'] = "CREATE TABLE IF NOT EXISTS `categories` (
        `category_id` INT AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        `slug` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `icon` VARCHAR(100) DEFAULT NULL,
        `display_order` INT DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`category_id`),
        UNIQUE KEY `slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['courses'] = "CREATE TABLE IF NOT EXISTS `courses` (
        `course_id` VARCHAR(50) NOT NULL,
        `instructor_id` VARCHAR(50) NOT NULL,
        `category_id` INT DEFAULT NULL,
        `title` VARCHAR(255) NOT NULL,
        `subtitle` VARCHAR(500) DEFAULT NULL,
        `slug` VARCHAR(255) DEFAULT NULL,
        `description` TEXT,
        `long_description` LONGTEXT,
        `thumbnail_url` VARCHAR(500) DEFAULT 'public/images/default-course.jpg',
        `preview_video_url` VARCHAR(500) DEFAULT NULL,
        `level` ENUM('beginner', 'intermediate', 'advanced', 'all_levels') DEFAULT 'beginner',
        `language` VARCHAR(50) DEFAULT 'English',
        `subtitles` JSON DEFAULT NULL,
        `duration_hours` DECIMAL(10,2) DEFAULT 0,
        `total_duration_minutes` INT DEFAULT 0,
        `price` DECIMAL(10,2) DEFAULT 0,
        `original_price` DECIMAL(10,2) DEFAULT NULL,
        `currency` VARCHAR(10) DEFAULT 'BDT',
        `is_free` TINYINT(1) DEFAULT 0,
        `is_featured` TINYINT(1) DEFAULT 0,
        `is_bestseller` TINYINT(1) DEFAULT 0,
        `status` ENUM('draft', 'pending', 'published', 'archived') DEFAULT 'draft',
        `total_sections` INT DEFAULT 0,
        `total_lectures` INT DEFAULT 0,
        `total_resources` INT DEFAULT 0,
        `enrollment_count` INT DEFAULT 0,
        `enrolled_count` INT DEFAULT 0,
        `average_rating` DECIMAL(3,2) DEFAULT 0,
        `total_reviews` INT DEFAULT 0,
        `learning_objectives` TEXT,
        `requirements` TEXT,
        `target_audience` TEXT,
        `last_updated_at` DATETIME DEFAULT NULL,
        `published_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`course_id`),
        KEY `idx_instructor` (`instructor_id`),
        KEY `idx_category` (`category_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['course_sections'] = "CREATE TABLE IF NOT EXISTS `course_sections` (
        `section_id` VARCHAR(50) NOT NULL,
        `course_id` VARCHAR(50) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `display_order` INT DEFAULT 0,
        `sort_order` INT DEFAULT NULL,
        `total_lectures` INT DEFAULT 0,
        `is_published` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`section_id`),
        KEY `idx_course` (`course_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['lectures'] = "CREATE TABLE IF NOT EXISTS `lectures` (
        `lecture_id` VARCHAR(50) NOT NULL,
        `section_id` VARCHAR(50) NOT NULL,
        `course_id` VARCHAR(50) DEFAULT NULL,
        `title` VARCHAR(255) NOT NULL,
        `lecture_type` ENUM('video', 'text', 'quiz', 'assignment') DEFAULT 'video',
        `content_url` VARCHAR(500) DEFAULT NULL,
        `video_url` VARCHAR(500) DEFAULT NULL,
        `video_source` VARCHAR(50) DEFAULT NULL,
        `video_provider` VARCHAR(50) DEFAULT NULL,
        `thumbnail_url` VARCHAR(500) DEFAULT NULL,
        `duration` INT DEFAULT 0,
        `duration_seconds` INT DEFAULT 0,
        `duration_minutes` INT DEFAULT 0,
        `is_preview` TINYINT(1) DEFAULT 0,
        `display_order` INT DEFAULT 0,
        `sort_order` INT DEFAULT NULL,
        `is_published` TINYINT(1) DEFAULT 1,
        `subtitles` JSON DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`lecture_id`),
        KEY `idx_section` (`section_id`),
        KEY `idx_course` (`course_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['lecture_resources'] = "CREATE TABLE IF NOT EXISTS `lecture_resources` (
        `resource_id` INT AUTO_INCREMENT,
        `lecture_id` VARCHAR(50) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `resource_type` ENUM('file', 'link') DEFAULT 'file',
        `file_url` VARCHAR(500) DEFAULT NULL,
        `file_name` VARCHAR(255) DEFAULT NULL,
        `file_size_kb` INT DEFAULT NULL,
        `display_order` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`resource_id`),
        KEY `idx_lecture` (`lecture_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['enrollments'] = "CREATE TABLE IF NOT EXISTS `enrollments` (
        `enrollment_id` INT AUTO_INCREMENT,
        `student_id` VARCHAR(50) NOT NULL,
        `course_id` VARCHAR(50) NOT NULL,
        `transaction_id` INT DEFAULT NULL,
        `enrollment_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `enrollment_source` ENUM('free', 'direct', 'promotion', 'gift') DEFAULT 'direct',
        `price_paid` DECIMAL(10,2) DEFAULT 0,
        `currency` VARCHAR(10) DEFAULT 'BDT',
        `payment_method` VARCHAR(50) DEFAULT NULL,
        `coupon_code` VARCHAR(50) DEFAULT NULL,
        `discount_amount` DECIMAL(10,2) DEFAULT 0,
        `status` ENUM('active', 'completed', 'expired', 'refunded') DEFAULT 'active',
        `progress_percentage` INT DEFAULT 0,
        `lectures_completed` INT DEFAULT 0,
        `total_learning_time_seconds` INT DEFAULT 0,
        `last_accessed_at` DATETIME DEFAULT NULL,
        `completion_date` DATETIME DEFAULT NULL,
        PRIMARY KEY (`enrollment_id`),
        UNIQUE KEY `unique_enrollment` (`student_id`, `course_id`),
        KEY `idx_student` (`student_id`),
        KEY `idx_course` (`course_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['lecture_progress'] = "CREATE TABLE IF NOT EXISTS `lecture_progress` (
        `progress_id` INT AUTO_INCREMENT,
        `student_id` VARCHAR(50) NOT NULL,
        `lecture_id` VARCHAR(50) NOT NULL,
        `enrollment_id` INT DEFAULT NULL,
        `watch_duration_seconds` INT DEFAULT 0,
        `is_completed` TINYINT(1) DEFAULT 0,
        `completed_at` DATETIME DEFAULT NULL,
        `last_watched_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`progress_id`),
        UNIQUE KEY `unique_progress` (`student_id`, `lecture_id`),
        KEY `idx_student` (`student_id`),
        KEY `idx_lecture` (`lecture_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['lecture_notes'] = "CREATE TABLE IF NOT EXISTS `lecture_notes` (
        `note_id` INT AUTO_INCREMENT,
        `user_id` VARCHAR(50) NOT NULL,
        `lecture_id` VARCHAR(50) NOT NULL,
        `content` TEXT,
        `timestamp` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`note_id`),
        KEY `idx_user_lecture` (`user_id`, `lecture_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['course_notes'] = "CREATE TABLE IF NOT EXISTS `course_notes` (
        `note_id` INT AUTO_INCREMENT,
        `student_id` VARCHAR(50) NOT NULL,
        `course_id` VARCHAR(50) NOT NULL,
        `lecture_id` VARCHAR(50) DEFAULT NULL,
        `note_title` VARCHAR(255) DEFAULT NULL,
        `note_content` TEXT,
        `is_public` TINYINT(1) DEFAULT 0,
        `is_starred` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`note_id`),
        KEY `idx_student` (`student_id`),
        KEY `idx_course` (`course_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['certificates'] = "CREATE TABLE IF NOT EXISTS `certificates` (
        `certificate_id` INT AUTO_INCREMENT,
        `student_id` VARCHAR(50) NOT NULL,
        `course_id` VARCHAR(50) NOT NULL,
        `certificate_number` VARCHAR(100) NOT NULL,
        `issued_date` DATE DEFAULT NULL,
        `final_grade` VARCHAR(10) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`certificate_id`),
        UNIQUE KEY `certificate_number` (`certificate_number`),
        KEY `idx_student` (`student_id`),
        KEY `idx_course` (`course_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['reviews'] = "CREATE TABLE IF NOT EXISTS `reviews` (
        `review_id` INT AUTO_INCREMENT,
        `course_id` VARCHAR(50) DEFAULT NULL,
        `course_instructor_id` VARCHAR(50) DEFAULT NULL,
        `student_id` VARCHAR(50) NOT NULL,
        `rating` DECIMAL(2,1) NOT NULL,
        `review_title` VARCHAR(255) DEFAULT NULL,
        `review_text` TEXT,
        `is_verified_purchase` TINYINT(1) DEFAULT 0,
        `is_published` TINYINT(1) DEFAULT 1,
        `helpful_count` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`review_id`),
        KEY `idx_course` (`course_id`),
        KEY `idx_course_instructor` (`course_instructor_id`),
        KEY `idx_student` (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['review_helpfulness'] = "CREATE TABLE IF NOT EXISTS `review_helpfulness` (
        `helpfulness_id` INT AUTO_INCREMENT,
        `review_id` INT NOT NULL,
        `user_id` VARCHAR(50) NOT NULL,
        `is_helpful` TINYINT(1) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`helpfulness_id`),
        UNIQUE KEY `unique_vote` (`review_id`, `user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['review_reports'] = "CREATE TABLE IF NOT EXISTS `review_reports` (
        `report_id` INT AUTO_INCREMENT,
        `review_id` INT NOT NULL,
        `reporter_user_id` VARCHAR(50) NOT NULL,
        `reason` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('pending', 'reviewed', 'dismissed') DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`report_id`),
        KEY `idx_review` (`review_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['transactions'] = "CREATE TABLE IF NOT EXISTS `transactions` (
        `transaction_id` INT AUTO_INCREMENT,
        `user_id` VARCHAR(50) NOT NULL,
        `transaction_type` ENUM('enrollment', 'refund', 'payout') DEFAULT 'enrollment',
        `amount` DECIMAL(10,2) NOT NULL,
        `currency` VARCHAR(10) DEFAULT 'BDT',
        `payment_method` VARCHAR(50) DEFAULT NULL,
        `payment_gateway` VARCHAR(50) DEFAULT NULL,
        `status` ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
        `course_id` VARCHAR(50) DEFAULT NULL,
        `enrollment_id` INT DEFAULT NULL,
        `coupon_id` INT DEFAULT NULL,
        `discount_amount` DECIMAL(10,2) DEFAULT 0,
        `platform_fee` DECIMAL(10,2) DEFAULT 0,
        `platform_fee_percentage` DECIMAL(5,2) DEFAULT 20.00,
        `instructor_revenue` DECIMAL(10,2) DEFAULT 0,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` TEXT,
        `processed_at` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`transaction_id`),
        KEY `idx_user` (`user_id`),
        KEY `idx_course` (`course_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['coupons'] = "CREATE TABLE IF NOT EXISTS `coupons` (
        `coupon_id` INT AUTO_INCREMENT,
        `code` VARCHAR(50) NOT NULL,
        `description` TEXT,
        `discount_type` ENUM('percentage', 'fixed') DEFAULT 'percentage',
        `discount_value` DECIMAL(10,2) NOT NULL,
        `min_purchase_amount` DECIMAL(10,2) DEFAULT 0,
        `max_discount_amount` DECIMAL(10,2) DEFAULT NULL,
        `valid_from` DATE DEFAULT NULL,
        `valid_until` DATE DEFAULT NULL,
        `usage_limit` INT DEFAULT NULL,
        `per_user_limit` INT DEFAULT 1,
        `usage_count` INT DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_by` VARCHAR(50) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`coupon_id`),
        UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['coupon_usage'] = "CREATE TABLE IF NOT EXISTS `coupon_usage` (
        `usage_id` INT AUTO_INCREMENT,
        `coupon_id` INT NOT NULL,
        `user_id` VARCHAR(50) NOT NULL,
        `enrollment_id` INT DEFAULT NULL,
        `transaction_id` INT DEFAULT NULL,
        `discount_amount` DECIMAL(10,2) DEFAULT 0,
        `used_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`usage_id`),
        KEY `idx_coupon` (`coupon_id`),
        KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['wishlists'] = "CREATE TABLE IF NOT EXISTS `wishlists` (
        `wishlist_id` INT AUTO_INCREMENT,
        `user_id` VARCHAR(50) NOT NULL,
        `course_id` VARCHAR(50) NOT NULL,
        `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`wishlist_id`),
        UNIQUE KEY `unique_wishlist` (`user_id`, `course_id`),
        KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['shopping_carts'] = "CREATE TABLE IF NOT EXISTS `shopping_carts` (
        `cart_id` INT AUTO_INCREMENT,
        `user_id` VARCHAR(50) NOT NULL,
        `course_id` VARCHAR(50) NOT NULL,
        `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`cart_id`),
        UNIQUE KEY `unique_cart` (`user_id`, `course_id`),
        KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['notifications'] = "CREATE TABLE IF NOT EXISTS `notifications` (
        `notification_id` INT AUTO_INCREMENT,
        `user_id` VARCHAR(50) NOT NULL,
        `notification_type` VARCHAR(50) DEFAULT 'info',
        `title` VARCHAR(255) NOT NULL,
        `message` TEXT,
        `link_url` VARCHAR(500) DEFAULT NULL,
        `related_entity_type` VARCHAR(50) DEFAULT NULL,
        `related_entity_id` VARCHAR(50) DEFAULT NULL,
        `is_read` TINYINT(1) DEFAULT 0,
        `read_at` DATETIME DEFAULT NULL,
        `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`notification_id`),
        KEY `idx_user` (`user_id`),
        KEY `idx_read` (`is_read`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['notification_reads'] = "CREATE TABLE IF NOT EXISTS `notification_reads` (
        `read_id` INT AUTO_INCREMENT,
        `notification_id` INT NOT NULL,
        `user_id` VARCHAR(50) NOT NULL,
        `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`read_id`),
        KEY `idx_notification` (`notification_id`),
        KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['system_settings'] = "CREATE TABLE IF NOT EXISTS `system_settings` (
        `setting_id` INT AUTO_INCREMENT,
        `setting_key` VARCHAR(100) NOT NULL,
        `setting_value` TEXT,
        `updated_by` VARCHAR(50) DEFAULT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`setting_id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tables['course_pricing_history'] = "CREATE TABLE IF NOT EXISTS `course_pricing_history` (
        `history_id` INT AUTO_INCREMENT,
        `course_id` VARCHAR(50) NOT NULL,
        `price` DECIMAL(10,2) DEFAULT 0,
        `original_price` DECIMAL(10,2) DEFAULT 0,
        `currency` VARCHAR(10) DEFAULT 'BDT',
        `is_free` TINYINT(1) DEFAULT 0,
        `changed_by` VARCHAR(50) DEFAULT NULL,
        `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`history_id`),
        KEY `idx_course` (`course_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";


    $created_tables = [];
    $failed_tables = [];

    foreach ($tables as $table_name => $create_query) {
        if (!table_exists($con, $table_name)) {
            $result = mysqli_query($con, $create_query);
            if ($result) {
                $created_tables[] = $table_name;
            } else {
                $failed_tables[] = [
                    'table' => $table_name,
                    'error' => mysqli_error($con)
                ];
            }
        }
    }

    return [
        'created' => $created_tables,
        'failed' => $failed_tables,
        'total_tables' => count($tables)
    ];
}

function create_database_if_not_exists($host, $user, $pass, $db) {
    $temp_con = @mysqli_connect($host, $user, $pass);
    
    if (!$temp_con) {
        return false;
    }
    
    $result = mysqli_query($temp_con, "CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    mysqli_close($temp_con);
    
    return $result;
}

global $host, $user, $pass, $db, $con;

create_database_if_not_exists($host, $user, $pass, $db);

if (isset($con) && $con) {
    $result = create_all_tables($con);
}
?>
