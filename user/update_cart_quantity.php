<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

if (!isset($_POST['product_id']) || !isset($_POST['quantity']) || !isset($_POST['csrf_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required data.']);
    exit();
}

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'CSRF token mismatch.']);
    exit();
}

require_once '../config/database.php';
$conn = get_db_connection();

$user_id = $_SESSION['user_id'];
$product_id = (int)$_POST['product_id'];
$new_quantity = (int)$_POST['quantity'];

if ($new_quantity < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Quantity must be at least 1.']);
    $conn->close();
    exit();
}

// Get product stock
$sql_stock = "SELECT stock FROM products WHERE id = ?";
$stmt_stock = $conn->prepare($sql_stock);
if (!$stmt_stock) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare stock query.']);
    $conn->close();
    exit();
}
$stmt_stock->bind_param("i", $product_id);
$stmt_stock->execute();
$result_stock = $stmt_stock->get_result();
$product_data = $result_stock->fetch_assoc();
$stmt_stock->close();

if (!$product_data) {
    echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
    $conn->close();
    exit();
}

$available_stock = $product_data['stock'];

if ($new_quantity > $available_stock) {
    echo json_encode(['status' => 'error', 'message' => 'Not enough stock. Available: ' . $available_stock, 'new_stock' => $available_stock]);
    $conn->close();
    exit();
}

// Update cart item quantity
$sql_update = "UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?";
$stmt_update = $conn->prepare($sql_update);
if (!$stmt_update) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare update query.']);
    $conn->close();
    exit();
}
$stmt_update->bind_param("iii", $new_quantity, $user_id, $product_id);

if ($stmt_update->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Cart quantity updated.', 'new_stock' => $available_stock]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update cart quantity: ' . $stmt_update->error]);
}

$stmt_update->close();
$conn->close();
?>