<?php
session_start();
require_once '../includes/config.php';
require_once '../dashboard/instructor/includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in and is an instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_report':
            getReport($con, $user_id);
            break;
        case 'export_csv':
            exportCSV($con, $user_id);
            break;
        case 'export_pdf':
            exportPDF($con, $user_id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function getReport($con, $instructor_id) {
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    
    $report = getAnalyticsReport($con, $instructor_id, $start_date, $end_date);
    
    echo json_encode([
        'success' => true,
        'data' => $report
    ]);
}

function exportCSV($con, $instructor_id) {
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    $report = getAnalyticsReport($con, $instructor_id, $start_date, $end_date);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="analytics_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write summary
    fputcsv($output, ['Analytics Report']);
    fputcsv($output, ['Date Range', $start_date . ' to ' . $end_date]);
    fputcsv($output, []);
    
    fputcsv($output, ['Summary Statistics']);
    fputcsv($output, ['Total Enrollments', $report['summary']['total_enrollments']]);
    fputcsv($output, ['Total Revenue', '$' . number_format($report['summary']['total_revenue'], 2)]);
    fputcsv($output, ['Total Reviews', $report['summary']['total_reviews']]);
    fputcsv($output, ['Average Rating', number_format($report['summary']['avg_rating'], 2)]);
    fputcsv($output, []);
    
    // Write enrollments
    fputcsv($output, ['Enrollments by Date']);
    fputcsv($output, ['Date', 'Course', 'Enrollments']);
    foreach ($report['enrollments'] as $enrollment) {
        fputcsv($output, [
            $enrollment['date'],
            $enrollment['course_title'],
            $enrollment['enrollments']
        ]);
    }
    fputcsv($output, []);
    
    // Write revenue
    fputcsv($output, ['Revenue by Date']);
    fputcsv($output, ['Date', 'Revenue', 'Transactions']);
    foreach ($report['revenue'] as $revenue) {
        fputcsv($output, [
            $revenue['date'],
            '$' . number_format($revenue['revenue'], 2),
            $revenue['transactions']
        ]);
    }
    fputcsv($output, []);
    
    // Write reviews
    fputcsv($output, ['Reviews by Date']);
    fputcsv($output, ['Date', 'Course', 'Review Count', 'Average Rating']);
    foreach ($report['reviews'] as $review) {
        fputcsv($output, [
            $review['date'],
            $review['course_title'],
            $review['review_count'],
            number_format($review['avg_rating'], 1)
        ]);
    }
    
    fclose($output);
    exit;
}

function exportPDF($con, $instructor_id) {
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    $report = getAnalyticsReport($con, $instructor_id, $start_date, $end_date);
    
    // Generate HTML for PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            h1 { color: #333; border-bottom: 2px solid #6366f1; padding-bottom: 10px; }
            h2 { color: #555; margin-top: 30px; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #6366f1; color: white; }
            .summary-box { background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .summary-item { margin: 10px 0; font-size: 16px; }
            .date-range { color: #666; font-size: 14px; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <h1>Analytics Report</h1>
        <div class="date-range">Date Range: ' . $start_date . ' to ' . $end_date . '</div>
        
        <div class="summary-box">
            <h2>Summary Statistics</h2>
            <div class="summary-item"><strong>Total Enrollments:</strong> ' . number_format($report['summary']['total_enrollments']) . '</div>
            <div class="summary-item"><strong>Total Revenue:</strong> $' . number_format($report['summary']['total_revenue'], 2) . '</div>
            <div class="summary-item"><strong>Total Reviews:</strong> ' . number_format($report['summary']['total_reviews']) . '</div>
            <div class="summary-item"><strong>Average Rating:</strong> ' . number_format($report['summary']['avg_rating'], 2) . '</div>
        </div>
        
        <h2>Enrollments by Date</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Course</th>
                    <th>Enrollments</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($report['enrollments'] as $enrollment) {
        $html .= '<tr>
            <td>' . htmlspecialchars($enrollment['date']) . '</td>
            <td>' . htmlspecialchars($enrollment['course_title']) . '</td>
            <td>' . htmlspecialchars($enrollment['enrollments']) . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>
        
        <h2>Revenue by Date</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Revenue</th>
                    <th>Transactions</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($report['revenue'] as $revenue) {
        $html .= '<tr>
            <td>' . htmlspecialchars($revenue['date']) . '</td>
            <td>$' . number_format($revenue['revenue'], 2) . '</td>
            <td>' . htmlspecialchars($revenue['transactions']) . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>
        
        <h2>Reviews by Date</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Course</th>
                    <th>Review Count</th>
                    <th>Average Rating</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($report['reviews'] as $review) {
        $html .= '<tr>
            <td>' . htmlspecialchars($review['date']) . '</td>
            <td>' . htmlspecialchars($review['course_title']) . '</td>
            <td>' . htmlspecialchars($review['review_count']) . '</td>
            <td>' . number_format($review['avg_rating'], 1) . '</td>
        </tr>';
    }
    
    $html .= '</tbody></table>
    </body>
    </html>';
    
    // For simple PDF generation, we'll use HTML to PDF conversion
    // In production, you'd use a library like TCPDF or Dompdf
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="analytics_report_' . date('Y-m-d') . '.pdf"');
    
    // Simple fallback: output as HTML with print styling
    // For actual PDF, install a library like: composer require dompdf/dompdf
    echo $html;
    
    // Note: For production, uncomment below and install dompdf
    /*
    require_once '../vendor/autoload.php';
    use Dompdf\Dompdf;
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('analytics_report_' . date('Y-m-d') . '.pdf');
    */
    
    exit;
}
?>
