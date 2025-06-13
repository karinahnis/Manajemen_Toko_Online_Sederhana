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

if (!isset($_POST['product_ids']) || !is_array($_POST['product_ids']) || !isset($_POST['csrf_token'])) {
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
$product_ids = array_map('intval', $_POST['product_ids']); // Sanitize product IDs

if (empty($product_ids)) {
    echo json_encode(['status' => 'error', 'message' => 'No product IDs provided.']);
    $conn->close();
    exit();
}

// Create a placeholder string for the IN clause
$placeholders = implode(',', array_fill(0, count($product_ids), '?'));
$types = str_repeat('i', count($product_ids)); // 'i' for integer

$sql_delete = "DELETE FROM cart_items WHERE user_id = ? AND product_id IN ($placeholders)";
$stmt_delete = $conn->prepare($sql_delete);

if (!$stmt_delete) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare delete query.']);
    $conn->close();
    exit();
}

// Bind parameters dynamically
$params = array_merge([$user_id], $product_ids);
$stmt_delete->bind_param("i" . $types, ...$params);

if ($stmt_delete->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Selected items removed from cart.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to remove items from cart: ' . $stmt_delete->error]);
}

$stmt_delete->close();
$conn->close();
?>