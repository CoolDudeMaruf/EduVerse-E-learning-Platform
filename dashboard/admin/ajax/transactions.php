<?php
require_once '../../../includes/config.php';

// Check if user is logged in and is admin
if (!$is_logged_in || strtolower($_SESSION['role']) !== 'admin') {
    if (($_GET['action'] ?? '') !== 'export') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    } else {
        die('Unauthorized');
    }
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_transactions':
        header('Content-Type: application/json');
        getTransactions($con);
        break;
    case 'get_transaction':
        header('Content-Type: application/json');
        getTransaction($con);
        break;
    case 'export':
        exportTransactions($con);
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getTransactions($con) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $date_from = mysqli_real_escape_string($con, $_GET['date_from'] ?? '');
    $date_to = mysqli_real_escape_string($con, $_GET['date_to'] ?? '');
    
    $where = "1=1";
    if ($date_from) $where .= " AND t.created_at >= '$date_from 00:00:00'";
    if ($date_to) $where .= " AND t.created_at <= '$date_to 23:59:59'";
    
    // Get totals
    $totals_query = "SELECT 
                     COUNT(*) as total_count,
                     SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_amount,
                     SUM(CASE WHEN status = 'completed' THEN platform_fee ELSE 0 END) as total_fees
                     FROM transactions t WHERE $where";
    $totals_result = mysqli_query($con, $totals_query);
    $totals = mysqli_fetch_assoc($totals_result);
    
    $total = $totals['total_count'];
    $total_pages = ceil($total / $limit);
    
    // Get transactions
    $query = "SELECT t.*, 
              CONCAT(u.first_name, ' ', u.last_name) as user_name
              FROM transactions t 
              LEFT JOIN users u ON t.user_id = u.user_id
              WHERE $where 
              ORDER BY t.created_at DESC 
              LIMIT $offset, $limit";
    $result = mysqli_query($con, $query);
    
    $transactions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $transactions[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'total' => $total,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'total_amount' => $totals['total_amount'] ?? 0,
        'total_fees' => $totals['total_fees'] ?? 0,
        'total_count' => $totals['total_count'] ?? 0
    ]);
}

function getTransaction($con) {
    $transaction_id = intval($_GET['transaction_id'] ?? 0);
    
    if (!$transaction_id) {
        echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
        return;
    }
    
    $query = "SELECT t.*, 
              CONCAT(u.first_name, ' ', u.last_name) as user_name,
              c.title as course_title
              FROM transactions t 
              LEFT JOIN users u ON t.user_id = u.user_id
              LEFT JOIN courses c ON t.course_id = c.course_id
              WHERE t.transaction_id = $transaction_id";
    $result = mysqli_query($con, $query);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'transaction' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
    }
}

function exportTransactions($con) {
    $date_from = mysqli_real_escape_string($con, $_GET['date_from'] ?? '');
    $date_to = mysqli_real_escape_string($con, $_GET['date_to'] ?? '');
    
    $where = "1=1";
    if ($date_from) $where .= " AND t.created_at >= '$date_from 00:00:00'";
    if ($date_to) $where .= " AND t.created_at <= '$date_to 23:59:59'";
    
    $query = "SELECT t.transaction_id, u.email as user_email, 
              CONCAT(u.first_name, ' ', u.last_name) as user_name,
              t.transaction_type, t.amount, t.currency, t.payment_method, t.payment_gateway,
              t.status, t.course_id, t.discount_amount, t.platform_fee, t.instructor_revenue,
              t.created_at
              FROM transactions t 
              LEFT JOIN users u ON t.user_id = u.user_id
              WHERE $where 
              ORDER BY t.created_at DESC";
    $result = mysqli_query($con, $query);
    
    // Output CSV
    $filename = 'transactions_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Header row
    fputcsv($output, ['ID', 'User Email', 'User Name', 'Type', 'Amount', 'Currency', 'Payment Method', 
                      'Gateway', 'Status', 'Course ID', 'Discount', 'Platform Fee', 'Instructor Revenue', 'Date']);
    
    // Data rows
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['transaction_id'],
            $row['user_email'],
            $row['user_name'],
            $row['transaction_type'],
            $row['amount'],
            $row['currency'],
            $row['payment_method'],
            $row['payment_gateway'],
            $row['status'],
            $row['course_id'],
            $row['discount_amount'],
            $row['platform_fee'],
            $row['instructor_revenue'],
            $row['created_at']
        ]);
    }
    
    fclose($output);
    exit;
}
?>
