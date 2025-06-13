<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$product_ids = isset($_POST['product_ids']) ? $_POST['product_ids'] : []; // Array of product IDs

if (empty($product_ids) || !is_array($product_ids)) {
    echo json_encode(['success' => false, 'message' => 'No product IDs provided.']);
    exit();
}

$conn = get_db_connection();

try {
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $types = str_repeat('i', count($product_ids)); // 'i' for integer

    $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id IN ($placeholders)");
    // Bind user_id first, then product_ids
    $stmt->bind_param("i" . $types, $user_id, ...$product_ids);

    if ($stmt->execute()) {
        // Hitung ulang total keranjang setelah penghapusan
        $query_total = "SELECT SUM(ci.quantity * p.price) AS total_cart_price
                        FROM cart_items ci JOIN products p ON ci.product_id = p.id
                        WHERE ci.user_id = ?";
        $stmt_total = $conn->prepare($query_total);
        $stmt_total->bind_param("i", $user_id);
        $stmt_total->execute();
        $result_total = $stmt_total->get_result();
        $row_total = $result_total->fetch_assoc();
        $new_cart_total = $row_total['total_cart_price'] ?? 0;
        $stmt_total->close();

        echo json_encode([
            'success' => true,
            'message' => 'Selected items removed from cart.',
            'cartTotal' => number_format($new_cart_total, 0, ',', '.'),
            'rawCartTotal' => $new_cart_total
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove items: ' . $stmt->error]);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>