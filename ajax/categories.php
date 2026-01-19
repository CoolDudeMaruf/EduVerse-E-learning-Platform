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
    $perPage = 9;
    $offset = ($page - 1) * $perPage;

    $courseCounts = isset($_GET['courseCount']) ? json_decode($_GET['courseCount'], true) : [];
    $statuses = isset($_GET['status']) ? json_decode($_GET['status'], true) : [];
    $search = isset($_GET['search']) ? mysqli_real_escape_string($con, trim($_GET['search'])) : '';
    $sortby = isset($_GET['sortby']) ? $_GET['sortby'] : 'popular';

    $where = "cat.is_active = 1 AND cat.parent_category_id IS NULL";

    // Search filter
    if (!empty($search)) {
        $where .= " AND (cat.name LIKE '%$search%' OR cat.description LIKE '%$search%')";
    }

    // Course count filter
    if (!empty($courseCounts)) {
        $countConditions = [];
        foreach ($courseCounts as $count) {
            if ($count === '50+') {
                $countConditions[] = "cat.course_count >= 50";
            } elseif ($count === '20-50') {
                $countConditions[] = "(cat.course_count >= 20 AND cat.course_count < 50)";
            } elseif ($count === '10-20') {
                $countConditions[] = "(cat.course_count >= 10 AND cat.course_count < 20)";
            } elseif ($count === '1-10') {
                $countConditions[] = "(cat.course_count >= 1 AND cat.course_count < 10)";
            }
        }
        if (!empty($countConditions)) {
            $where .= " AND (" . implode(" OR ", $countConditions) . ")";
        }
    }

    // Status filter
    if (!empty($statuses)) {
        $statusConditions = [];
        foreach ($statuses as $status) {
            if ($status === 'trending') {
                $statusConditions[] = "cat.course_count >= 30";
            } elseif ($status === 'popular') {
                $statusConditions[] = "cat.course_count >= 20";
            } elseif ($status === 'new') {
                $statusConditions[] = "cat.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            }
        }
        if (!empty($statusConditions)) {
            $where .= " AND (" . implode(" OR ", $statusConditions) . ")";
        }
    }

    // Build ORDER clause
    $orderBy = 'cat.course_count DESC, cat.display_order ASC';
    if ($sortby === 'newest') {
        $orderBy = 'cat.created_at DESC';
    } elseif ($sortby === 'most-courses') {
        $orderBy = 'cat.course_count DESC';
    } elseif ($sortby === 'alphabetical') {
        $orderBy = 'cat.name ASC';
    }

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM categories cat WHERE $where";
    $countResult = mysqli_query($con, $countQuery);
    
    if (!$countResult) {
        throw new Exception("Count query failed: " . mysqli_error($con));
    }
    
    $countRow = mysqli_fetch_assoc($countResult);
    $totalCategories = $countRow['total'];
    $totalPages = ceil($totalCategories / $perPage);

    // Get categories
    $query = "SELECT 
                cat.category_id,
                cat.name,
                cat.slug,
                cat.description,
                cat.icon,
                cat.image_url,
                cat.course_count,
                cat.created_at,
                COUNT(DISTINCT c.course_id) as actual_course_count,
                ROUND(AVG((SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.course_instructor_id = c.course_id AND r.is_published = 1)), 1) as avg_rating
            FROM categories cat
            LEFT JOIN courses c ON cat.category_id = c.category_id AND c.status = 'published'
            WHERE $where
            GROUP BY cat.category_id, cat.name, cat.slug, cat.description, cat.icon, cat.image_url, cat.course_count, cat.created_at
            ORDER BY $orderBy
            LIMIT $perPage OFFSET $offset";

    $result = mysqli_query($con, $query);
    
    if (!$result) {
        throw new Exception("Query failed: " . mysqli_error($con));
    }
    
    $categories = [];

    while ($category = mysqli_fetch_assoc($result)) {
        // Determine if trending/new
        $isNew = strtotime($category['created_at']) > strtotime('-30 days');
        $isTrending = $category['actual_course_count'] >= 30;
        
        $categories[] = [
            'category_id' => $category['category_id'],
            'name' => htmlspecialchars($category['name']),
            'slug' => htmlspecialchars($category['slug']),
            'description' => htmlspecialchars($category['description'] ?? ''),
            'icon' => $category['icon'],
            'image_url' => $category['image_url'],
            'course_count' => intval($category['actual_course_count']),
            'avg_rating' => floatval($category['avg_rating'] ?? 0),
            'is_new' => $isNew,
            'is_trending' => $isTrending
        ];
    }

    // Return successful response
    echo json_encode([
        'success' => true,
        'categories' => $categories,
        'totalCategories' => $totalCategories,
        'totalPages' => $totalPages,
        'currentPage' => $page
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
