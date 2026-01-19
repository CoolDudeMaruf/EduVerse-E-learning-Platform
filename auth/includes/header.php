<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php if(isset($page_title)) { echo "$page_title"; } else { echo $site_title; } ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo $base_url; ?>public/images/favicon.svg">
    <link rel="stylesheet" href="<?php echo $base_url; ?>public/css/google-font.css">
    <script src="<?php echo $base_url; ?>public/js/jquery.js"></script>

    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>public/css/styles.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>public/css/page-loader.css">
</head>
<body>
    <!-- Page Loader -->
    <div class="page-loader" id="pageLoader">
        <div class="loader-content">
            <div class="loader-logo">
                <span class="material-icons">school</span>
                <span>EduVerse</span>
            </div>
            <div class="loader-spinner">
                <div class="spinner-circle"></div>
            </div>
            <div class="loader-text">Preparing signup...</div>
            <div class="loader-progress">
                <div class="loader-progress-bar"></div>
            </div>
            <div class="loader-dots">
                <div class="loader-dot"></div>
                <div class="loader-dot"></div>
                <div class="loader-dot"></div>
            </div>
        </div>
    </div>