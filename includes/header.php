<?php
    session_start();
    include_once('config.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="EduVerse - Modern E-Learning Platform. Learn from top instructors worldwide.">
    <meta name="keywords" content="online learning, courses, education, e-learning">
    <title><?php if(isset($page_title)) { echo "$page_title"; } else { echo $site_title; } ?></title>
    
    <link rel="icon" type="image/svg+xml" href="<?php echo $base_url; ?>public/images/favicon.svg">

    <link rel="stylesheet" href="<?php echo $base_url; ?>public/css/google-font.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="<?php echo $base_url; ?>public/js/jquery.js"></script>

    <link rel="stylesheet" href="<?php echo $base_url; ?>public/css/main.css">
    <?php if(isset($additional_css)) { ?>
        <link rel="stylesheet" href="<?php echo $base_url . $additional_css; ?>">
    <?php } ?>
</head>
<body>