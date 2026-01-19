<?php
require_once '../../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!$is_logged_in || strtolower($_SESSION['role']) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_categories':
        getCategories($con);
        break;
    case 'get_category':
        getCategory($con);
        break;
    case 'add_category':
        addCategory($con);
        break;
    case 'update_category':
        updateCategory($con);
        break;
    case 'delete_category':
        deleteCategory($con);
        break;
    case 'reorder_categories':
        reorderCategories($con);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getCategories($con) {
    $query = "SELECT * FROM categories ORDER BY display_order ASC";
    $result = mysqli_query($con, $query);
    
    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
}

function getCategory($con) {
    $category_id = intval($_GET['category_id'] ?? 0);
    
    if (!$category_id) {
        echo json_encode(['success' => false, 'message' => 'Category ID required']);
        return;
    }
    
    $query = "SELECT * FROM categories WHERE category_id = $category_id";
    $result = mysqli_query($con, $query);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'category' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Category not found']);
    }
}

function addCategory($con) {
    $name = mysqli_real_escape_string($con, $_POST['name'] ?? '');
    $slug = mysqli_real_escape_string($con, $_POST['slug'] ?? '');
    $description = mysqli_real_escape_string($con, $_POST['description'] ?? '');
    $icon = mysqli_real_escape_string($con, $_POST['icon'] ?? '📚');
    $display_order = intval($_POST['display_order'] ?? 0);
    $is_active = intval($_POST['is_active'] ?? 1);
    
    if (!$name || !$slug) {
        echo json_encode(['success' => false, 'message' => 'Name and slug are required']);
        return;
    }
    
    // Check slug uniqueness
    $check = mysqli_query($con, "SELECT category_id FROM categories WHERE slug = '$slug'");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(['success' => false, 'message' => 'Slug already exists']);
        return;
    }
    
    $query = "INSERT INTO categories (name, slug, description, icon, display_order, is_active, created_at)
              VALUES ('$name', '$slug', '$description', '$icon', $display_order, $is_active, NOW())";
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => 'Category added successfully', 'category_id' => mysqli_insert_id($con)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add category']);
    }
}

function updateCategory($con) {
    $category_id = intval($_POST['category_id'] ?? 0);
    $name = mysqli_real_escape_string($con, $_POST['name'] ?? '');
    $slug = mysqli_real_escape_string($con, $_POST['slug'] ?? '');
    $description = mysqli_real_escape_string($con, $_POST['description'] ?? '');
    $icon = mysqli_real_escape_string($con, $_POST['icon'] ?? '📚');
    $display_order = intval($_POST['display_order'] ?? 0);
    $is_active = intval($_POST['is_active'] ?? 1);
    
    if (!$category_id || !$name || !$slug) {
        echo json_encode(['success' => false, 'message' => 'Category ID, name and slug are required']);
        return;
    }
    
    // Check slug uniqueness
    $check = mysqli_query($con, "SELECT category_id FROM categories WHERE slug = '$slug' AND category_id != $category_id");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(['success' => false, 'message' => 'Slug already exists']);
        return;
    }
    
    $query = "UPDATE categories SET 
              name = '$name',
              slug = '$slug',
              description = '$description',
              icon = '$icon',
              display_order = $display_order,
              is_active = $is_active,
              updated_at = NOW()
              WHERE category_id = $category_id";
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update category']);
    }
}

function deleteCategory($con) {
    $category_id = intval($_POST['category_id'] ?? 0);
    
    if (!$category_id) {
        echo json_encode(['success' => false, 'message' => 'Category ID required']);
        return;
    }
    
    // Check if category has courses
    $check = mysqli_query($con, "SELECT COUNT(*) as count FROM courses WHERE category_id = $category_id");
    $count = mysqli_fetch_assoc($check)['count'];
    
    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => "Cannot delete: $count courses in this category"]);
        return;
    }
    
    $query = "DELETE FROM categories WHERE category_id = $category_id";
    
    if (mysqli_query($con, $query)) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete category']);
    }
}

function reorderCategories($con) {
    $order = json_decode($_POST['order'] ?? '[]', true);
    
    if (empty($order)) {
        echo json_encode(['success' => false, 'message' => 'Order data required']);
        return;
    }
    
    mysqli_begin_transaction($con);
    
    try {
        foreach ($order as $index => $category_id) {
            $query = "UPDATE categories SET display_order = $index WHERE category_id = " . intval($category_id);
            if (!mysqli_query($con, $query)) {
                throw new Exception('Failed to update order');
            }
        }
        
        mysqli_commit($con);
        echo json_encode(['success' => true, 'message' => 'Order updated']);
    } catch (Exception $e) {
        mysqli_rollback($con);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
