<?php
session_start();
include('../includes/config.php');

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page with logout message
header("Location: " . $base_url . "?logout=success");
exit();
?>