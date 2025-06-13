<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

if ($product_id <= 0 || $quantity < 0) { // Quantity can be 0 if removing
    echo json_encode(['success' => false, 'message' => 'Invalid product ID or quantity.']);
    exit();
}

$conn = get_db_connection();

try {
    if ($quantity == 0) {
        // Hapus item dari keranjang jika kuantitasnya 0
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
    } else {
        // Update kuantitas item
        $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("iii", $quantity, $user_id, $product_id);
    }

    if ($stmt->execute()) {
        // Ambil harga total terbaru untuk item yang diubah
        $query_item_price = "SELECT p.price FROM products p WHERE p.id = ?";
        $stmt_item_price = $conn->prepare($query_item_price);
        $stmt_item_price->bind_param("i", $product_id);
        $stmt_item_price->execute();
        $result_item_price = $stmt_item_price->get_result();
        $item_price_row = $result_item_price->fetch_assoc();
        $unit_price = $item_price_row['price'] ?? 0;
        $stmt_item_price->close();

        $item_total = $unit_price * $quantity;

        // Hitung ulang total keranjang
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
            'message' => 'Cart updated successfully.',
            'newQuantity' => $quantity,
            'itemTotal' => number_format($item_total, 0, ',', '.'),
            'cartTotal' => number_format($new_cart_total, 0, ',', '.'),
            'rawCartTotal' => $new_cart_total // Untuk perhitungan JS jika perlu
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update cart: ' . $stmt->error]);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>