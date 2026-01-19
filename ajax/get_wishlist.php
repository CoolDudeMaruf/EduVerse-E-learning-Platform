<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'get';

$is_logged_in = isset($_SESSION['user_id']);
$wishlist_items = [];

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    
    $query = "SELECT c.course_id, c.title, c.subtitle, c.thumbnail_url, c.price, c.original_price, 
              c.currency, c.level, c.duration_hours, c.is_free, cat.name as category_name,
              (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) as average_rating,
              c.enrollment_count, u.first_name, u.last_name
              FROM wishlists w
              JOIN courses c ON w.course_id = c.course_id
              LEFT JOIN categories cat ON c.category_id = cat.category_id
              LEFT JOIN users u ON c.instructor_id = u.user_id
              WHERE w.user_id = '$user_id' AND c.status = 'published'
              ORDER BY w.added_at DESC";
    
    $result = mysqli_query($con, $query);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $thumbnail = $row['thumbnail_url'] ? $base_url . $row['thumbnail_url'] : $base_url . 'public/images/course-placeholder.jpg';
            $is_free = (bool)$row['is_free'];
            
            $wishlist_items[] = [
                'course_id' => $row['course_id'],
                'title' => $row['title'],
                'subtitle' => $row['subtitle'],
                'image' => $thumbnail,
                'price' => $is_free ? 0 : (float)$row['price'],
                'original_price' => $is_free ? 0 : (float)$row['original_price'],
                'currency' => $row['currency'],
                'category' => $row['category_name'] ?? 'Course',
                'level' => $row['level'],
                'duration' => $row['duration_hours'],
                'rating' => round($row['average_rating'], 1),
                'students' => $row['enrollment_count'],
                'instructor' => $row['first_name'] . ' ' . $row['last_name'],
                'is_free' => $is_free
            ];
        }
    }
} else {
    if (isset($_SESSION['wishlist']) && is_array($_SESSION['wishlist']) && !empty($_SESSION['wishlist'])) {
        $course_ids = array_map(function($id) use ($con) {
            return "'" . mysqli_real_escape_string($con, $id) . "'";
        }, $_SESSION['wishlist']);
        
        $ids_string = implode(',', $course_ids);
        
        $query = "SELECT c.course_id, c.title, c.subtitle, c.thumbnail_url, c.price, c.original_price, 
                  c.currency, c.level, c.duration_hours, c.is_free, cat.name as category_name,
                  (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) as average_rating,
                  c.enrollment_count, u.first_name, u.last_name
                  FROM courses c
                  LEFT JOIN categories cat ON c.category_id = cat.category_id
                  LEFT JOIN users u ON c.instructor_id = u.user_id
                  WHERE c.course_id IN ($ids_string) AND c.status = 'published'";
        
        $result = mysqli_query($con, $query);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $thumbnail = $row['thumbnail_url'] ? $base_url . $row['thumbnail_url'] : $base_url . 'public/images/course-placeholder.jpg';
                $is_free = (bool)$row['is_free'];
                
                $wishlist_items[] = [
                    'course_id' => $row['course_id'],
                    'title' => $row['title'],
                    'subtitle' => $row['subtitle'],
                    'image' => $thumbnail,
                    'price' => $is_free ? 0 : (float)$row['price'],
                    'original_price' => $is_free ? 0 : (float)$row['original_price'],
                    'currency' => $row['currency'],
                    'category' => $row['category_name'] ?? 'Course',
                    'level' => $row['level'],
                    'duration' => $row['duration_hours'],
                    'rating' => round($row['average_rating'], 1),
                    'students' => $row['enrollment_count'],
                    'instructor' => $row['first_name'] . ' ' . $row['last_name'],
                    'is_free' => $is_free
                ];
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'items' => $wishlist_items,
    'count' => count($wishlist_items)
]);
?>
