<?php
ob_clean();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

include('../includes/config.php');
header('Content-Type: application/json; charset=utf-8');

try {

    /* ================= Pagination ================= */
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 12;
    $offset = ($page - 1) * $perPage;

    /* ================= Filters ================= */
    $years = json_decode($_GET['years_of_experience'] ?? '[]', true);
    $areas = json_decode($_GET['expertise_areas'] ?? '[]', true);
    $ratings = json_decode($_GET['rating'] ?? '[]', true);
    $verified = $_GET['verified'] ?? null;
    $featured = $_GET['featured'] ?? null;
    $courses = $_GET['courses'] ?? 'all';
    $sortby = $_GET['sortby'] ?? 'popular';

    $search = substr(mysqli_real_escape_string($con, trim($_GET['search'] ?? '')), 0, 50);

    if (!is_array($years)) $years = [];
    if (!is_array($areas)) $areas = [];
    if (!is_array($ratings)) $ratings = [];

    /* ================= WHERE ================= */
    $where = "u.role='instructor'";

    if ($search !== '') {
        $where .= " AND (u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%' OR ip.instructor_bio LIKE '%$search%' OR ip.expertise_areas LIKE '%$search%')";
    }
    
    if ($years) {
        $exp = implode(',', array_map(fn($e) => "'" . mysqli_real_escape_string($con, $e) . "'", $years));
        $where .= " AND ip.years_of_experience IN ($exp)";
    }
    
    if ($areas) {
        $conds = [];
        foreach ($areas as $a) {
            $a = mysqli_real_escape_string($con, $a);
            $conds[] = "ip.expertise_areas LIKE '%$a%'";
        }
        $where .= " AND (" . implode(' OR ', $conds) . ")";
    }
    
    if ($verified){
        $where .= " AND ip.verification_status=1";
        
    }
    if ($featured){
        $where .= " AND ip.is_featured=1";
    }
    /* ================= HAVING ================= */
    $havingArr = [];
    
    foreach ($ratings as $r) {
        $havingArr[] = "AVG(r.rating)>=" . floatval($r);
    }
    
    if ($courses === '10+') $havingArr[] = "COUNT(DISTINCT c.course_id)>=10";
    elseif ($courses === '5-10') $havingArr[] = "COUNT(DISTINCT c.course_id) BETWEEN 5 AND 10";
    elseif ($courses === '1-5') $havingArr[] = "COUNT(DISTINCT c.course_id) BETWEEN 1 AND 5";
    
    $having = $havingArr ? " HAVING " . implode(" AND ", $havingArr) : "";

    /* ================= ORDER ================= */
    $orderBy = match ($sortby) {
        'name_asc' => 'u.first_name ASC',
        'name_desc' => 'u.first_name DESC',
        'rating' => 'AVG(r.rating) DESC, COUNT(DISTINCT r.review_id) DESC',
        'courses' => 'COUNT(DISTINCT c.course_id) DESC',
        'students' => 'COUNT(DISTINCT e.enrollment_id) DESC',
        'newest' => 'u.created_at DESC',
        default => 'COUNT(DISTINCT e.enrollment_id) DESC, AVG(r.rating) DESC',
    };

    /* ================= COUNT QUERY ================= */
    $countQuery = "
        SELECT COUNT(*) total FROM (
        SELECT u.user_id FROM users u INNER JOIN instructor_profiles ip ON u.user_id=ip.user_id
        LEFT JOIN courses c ON u.user_id=c.instructor_id AND c.status='published'
        LEFT JOIN reviews r ON c.course_id=r.course_instructor_id LEFT JOIN enrollments e ON c.course_id=e.course_id
        WHERE $where GROUP BY u.user_id $having
        ) t";

    $countResult = mysqli_query($con, $countQuery);
    if (!$countResult) throw new Exception(mysqli_error($con));

    $totalInstructors = (int)mysqli_fetch_assoc($countResult)['total'];
    $totalPages = ceil($totalInstructors / $perPage);

    /* ================= MAIN QUERY ================= */
    $query = "
        SELECT u.user_id id,u.first_name,u.last_name,u.email,u.profile_image_url avatar,
        ip.instructor_bio,ip.years_of_experience,ip.expertise_areas,ip.verification_status,ip.is_featured,
        COALESCE(AVG(r.rating),0) rating,COUNT(DISTINCT r.review_id) reviewCount,
        COUNT(DISTINCT c.course_id) coursesCount,COUNT(DISTINCT e.enrollment_id) studentsCount
        FROM users u INNER JOIN instructor_profiles ip ON u.user_id=ip.user_id
        LEFT JOIN courses c ON u.user_id=c.instructor_id AND c.status='published'
        LEFT JOIN reviews r ON c.course_id=r.course_instructor_id LEFT JOIN enrollments e ON c.course_id=e.course_id
        WHERE $where GROUP BY u.user_id $having
        ORDER BY $orderBy LIMIT $perPage OFFSET $offset";


        // echo $query;

    $result = mysqli_query($con, $query);
    if (!$result) throw new Exception(mysqli_error($con));

    /* ================= RESPONSE ================= */
    $instructors = [];

    while ($row = mysqli_fetch_assoc($result)) {

        $areasParsed = [];
        if ($row['expertise_areas']) {
            $json = json_decode($row['expertise_areas'], true);
            $areasParsed = (json_last_error() === JSON_ERROR_NONE) ? $json : array_map('trim', explode(',', $row['expertise_areas']));
        }

        $instructors[] = [
            'id' => $row['id'],
            'name' => $row['first_name'].' '.$row['last_name'],
            'email' => $row['email'],
            'avatar' => $row['avatar'] ?: 'public/images/default-avatar.png',
            'bio' => $row['instructor_bio'],
            'years_of_experience' => $row['years_of_experience'],
            'expertise_areas' => $areasParsed,
            'verified' => (bool)$row['verification_status'],
            'featured' => (bool)$row['is_featured'],
            'rating' => (float)$row['rating'],
            'reviewCount' => (int)$row['reviewCount'],
            'coursesCount' => (int)$row['coursesCount'],
            'studentsCount' => (int)$row['studentsCount']
        ];
    }

    // Get years_of_experience filter counts
    $years_of_experienceMap = [];
    $expQuery = "SELECT ip.years_of_experience, COUNT(DISTINCT u.user_id) as count
                 FROM users u
                 INNER JOIN instructor_profiles ip ON u.user_id = ip.user_id
                 WHERE u.role = 'instructor' AND ip.years_of_experience IS NOT NULL AND ip.years_of_experience != ''
                 GROUP BY ip.years_of_experience
                 ORDER BY ip.years_of_experience";
    $expResult = mysqli_query($con, $expQuery);
    if ($expResult) {
        while ($row = mysqli_fetch_assoc($expResult)) {
            $years_of_experienceMap[] = [
                'name' => $row['years_of_experience'],
                'count' => (int)$row['count']
            ];
        }
    }

    // Get expertise_areas filter counts
    $expertise_areasMap = [];
    $areaQuery = "SELECT ip.expertise_areas
                  FROM users u
                  INNER JOIN instructor_profiles ip ON u.user_id = ip.user_id
                  WHERE u.role = 'instructor' AND ip.expertise_areas IS NOT NULL AND ip.expertise_areas != ''";
    $areaResult = mysqli_query($con, $areaQuery);
    $areaCounts = [];
    if ($areaResult) {
        while ($row = mysqli_fetch_assoc($areaResult)) {
            $areas = [];
            $decoded = json_decode($row['expertise_areas'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $areas = $decoded;
            } else {
                $areas = array_map('trim', explode(',', $row['expertise_areas']));
            }
            foreach ($areas as $area) {
                if (!empty($area)) {
                    if (!isset($areaCounts[$area])) {
                        $areaCounts[$area] = 0;
                    }
                    $areaCounts[$area]++;
                }
            }
        }
    }
    foreach ($areaCounts as $area => $count) {
        $expertise_areasMap[] = [
            'name' => $area,
            'count' => $count
        ];
    }
    // Sort by count descending
    usort($expertise_areasMap, function($a, $b) {
        return $b['count'] - $a['count'];
    });

    echo json_encode([
        'success' => true,
        'instructors' => $instructors,
        'totalInstructors' => $totalInstructors,
        'totalPages' => $totalPages,
        'currentPage' => $page,
        'years_of_experienceMap' => $years_of_experienceMap,
        'expertise_areasMap' => $expertise_areasMap


    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
