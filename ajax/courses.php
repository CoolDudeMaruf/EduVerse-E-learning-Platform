<?php
ob_clean();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

include('../includes/config.php');
header('Content-Type: application/json; charset=utf-8');

try {
    // Get parameters
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $perPage = isset($_GET['limit']) ? intval($_GET['limit']) : 9;

    $offset = ($page - 1) * $perPage;

    $categories = isset($_GET['category']) ? json_decode($_GET['category'], true) : [];
    $levels = isset($_GET['level']) ? json_decode($_GET['level'], true) : [];
    $price = isset($_GET['price']) ? $_GET['price'] : 'all';
    $ratings = isset($_GET['rating']) ? json_decode($_GET['rating'], true) : [];
    $durations = isset($_GET['duration']) ? json_decode($_GET['duration'], true) : [];
    $search = isset($_GET['search']) ? mysqli_real_escape_string($con, trim($_GET['search'])) : '';
    $sortby = isset($_GET['sortby']) ? $_GET['sortby'] : 'relevance';
   
    //instructor profile
    $instructorId = isset($_GET['instructorId']) ? $_GET['instructorId'] : null;

    $where = "c.status = 'published'";

    // Search filter
    if (!empty($search)) {
        $where .= " AND (c.title LIKE '%$search%' OR c.subtitle LIKE '%$search%' OR c.description LIKE '%$search%')";
    }
    //instructor profiles
    if (!empty($instructorId)) {
        $where .= " AND c.instructor_id = '$instructorId'";
    }

    // Category filter
    if (!empty($categories)) {
        $cat_list = implode(',', array_map('intval', $categories));
        $where .= " AND c.category_id IN ($cat_list)";
    }

    // Level filter
    if (!empty($levels)) {
        $level_escaped = array_map(function($l) use ($con) { 
            return "'" . mysqli_real_escape_string($con, $l) . "'"; 
        }, $levels);
        $level_list = implode(',', $level_escaped);
        $where .= " AND c.level IN ($level_list)";
    }

    // Price filter
    if ($price === 'free') {
        $where .= " AND (c.is_free = 1 OR c.price = 0)";
    } elseif ($price === 'paid') {
        $where .= " AND (c.is_free = 0 AND c.price > 0)";
    }

    // Rating filter
    if (!empty($ratings)) {
        $rating_conditions = [];
        foreach ($ratings as $r) {
            $rating_conditions[] = "(SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) >= " . floatval($r);
        }
        if (!empty($rating_conditions)) {
            $where .= " AND (" . implode(" OR ", $rating_conditions) . ")";
        }
    }

    // Duration filter
    if (!empty($durations)) {
        $duration_cond = [];
        foreach ($durations as $dur) {
            if ($dur === 'short') {
                $duration_cond[] = "c.duration_hours <= 5";
            } elseif ($dur === 'medium') {
                $duration_cond[] = "(c.duration_hours > 5 AND c.duration_hours <= 20)";
            } elseif ($dur === 'long') {
                $duration_cond[] = "c.duration_hours > 20";
            }
        }
        if (!empty($duration_cond)) {
            $where .= " AND (" . implode(" OR ", $duration_cond) . ")";
        }
    }

    // Build ORDER clause
    $orderBy = 'c.is_featured DESC, c.created_at DESC';
    if ($sortby === 'popular') {
        $orderBy = 'c.enrollment_count DESC';
    } elseif ($sortby === 'newest') {
        $orderBy = 'c.created_at DESC';
    } elseif ($sortby === 'rating') {
        $orderBy = 'avg_rating DESC, total_reviews DESC';
    } elseif ($sortby === 'price-low') {
        $orderBy = 'c.price ASC';
    } elseif ($sortby === 'price-high') {
        $orderBy = 'c.price DESC';
    }

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM courses c WHERE $where";
    $countResult = mysqli_query($con, $countQuery);
    
    if (!$countResult) {
        throw new Exception("Count query failed: " . mysqli_error($con));
    }
    
    $countRow = mysqli_fetch_assoc($countResult);
    $totalCourses = $countRow['total'];
    $totalPages = ceil($totalCourses / $perPage);

    // Get courses
    $query = "SELECT 
                c.course_id,
                c.title,
                c.slug,
                c.subtitle,
                c.description,
                c.thumbnail_url,
                c.level,
                c.price,
                c.original_price,
                c.is_free,
                c.currency,
                c.duration_hours,
                c.total_sections,
                c.total_lectures,
                (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id) as enrollment_count,
                (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) as average_rating,
                (SELECT COUNT(*) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1) as total_reviews,
                c.category_id,
                cat.name as category_name,
                u.first_name,
                u.last_name,
                u.profile_image_url
            FROM courses c
            LEFT JOIN categories cat ON c.category_id = cat.category_id
            LEFT JOIN users u ON c.instructor_id = u.user_id
            WHERE $where
            ORDER BY $orderBy
            LIMIT $perPage OFFSET $offset";

    $result = mysqli_query($con, $query);
    
    if (!$result) {
        throw new Exception("Query failed: " . mysqli_error($con));
    }
    
    $courses = [];

    while ($course = mysqli_fetch_assoc($result)) {
        // Format price
        if ($course['is_free']) {
            $course['displayPrice'] = 'Free';
        } else {
            $course['displayPrice'] = '৳' . number_format($course['price'], 0);
        }

        // Format duration
        $hours = intval($course['duration_hours']);
        $minutes = intval(($course['duration_hours'] - $hours) * 60);
        $course['durationDisplay'] = '';
        if ($hours > 0) {
            $course['durationDisplay'] .= $hours . 'h';
        }
        if ($minutes > 0) {
            if (!empty($course['durationDisplay'])) {
                $course['durationDisplay'] .= ' ';
            }
            $course['durationDisplay'] .= $minutes . 'm';
        }

        // Format instructor name
        $course['instructor_name'] = $course['first_name'] . ' ' . $course['last_name'];

        // Default images
        if (empty($course['thumbnail_url'])) {
            $course['thumbnail_url'] = 'public/images/default-course.jpg';
        }
        if (empty($course['profile_image_url'])) {
            $course['profile_image_url'] = 'public/images/default-profile.jpg';
        }

        $courses[] = $course;
    }





    // Get category counts
    $categoryMap = [];
    $catQuery = "SELECT 
                    c.category_id, 
                    cat.name,
                    COUNT(c.course_id) as course_count 
                FROM courses c
                LEFT JOIN categories cat ON c.category_id = cat.category_id
                WHERE $where 
                GROUP BY c.category_id, cat.name
                ORDER BY COUNT(c.course_id) DESC";
    $catResult = mysqli_query($con, $catQuery);
    
    if ($catResult) {
        while ($cat = mysqli_fetch_assoc($catResult)) {
            $categoryMap[] = [
                'category_id' => intval($cat['category_id']),
                'name' => $cat['name'],
                'count' => intval($cat['course_count'])
            ];
        }
    }
    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'totalCourses' => $totalCourses,
        'totalPages' => $totalPages,
        'currentPage' => $page,
        'perPage' => $perPage,
        'categoryMap' => $categoryMap
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    exit;
}
?>
