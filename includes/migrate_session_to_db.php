<?php

if (!isset($_SESSION['user_id']) || !isset($con)) {
    return;
}

$user_id = $_SESSION['user_id'];

if (isset($_SESSION['cart']) && is_array($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $course_id) {
        $course_id_escaped = mysqli_real_escape_string($con, $course_id);
        
        $check_query = "SELECT cart_id FROM shopping_carts WHERE user_id = '$user_id' AND course_id = '$course_id_escaped'";
        $check_result = mysqli_query($con, $check_query);
        
        if (mysqli_num_rows($check_result) === 0) {
            $insert_query = "INSERT INTO shopping_carts (user_id, course_id) VALUES ('$user_id', '$course_id_escaped')";
            mysqli_query($con, $insert_query);
        }
    }
    
    unset($_SESSION['cart']);
}

if (isset($_SESSION['wishlist']) && is_array($_SESSION['wishlist']) && !empty($_SESSION['wishlist'])) {
    foreach ($_SESSION['wishlist'] as $course_id) {
        $course_id_escaped = mysqli_real_escape_string($con, $course_id);
        
        $check_query = "SELECT wishlist_id FROM wishlists WHERE user_id = '$user_id' AND course_id = '$course_id_escaped'";
        $check_result = mysqli_query($con, $check_query);
        
        if (mysqli_num_rows($check_result) === 0) {
            $insert_query = "INSERT INTO wishlists (user_id, course_id) VALUES ('$user_id', '$course_id_escaped')";
            mysqli_query($con, $insert_query);
        }
    }
    
    unset($_SESSION['wishlist']);
}
?>
