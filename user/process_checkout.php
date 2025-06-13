<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
    $redirect_error = "Akses tidak diizinkan.";
    header("Location: ../login.html?error=" . urlencode($redirect_error));
    exit();
}

require_once '../config/database.php';
$conn = get_db_connection();

$user_id = $_SESSION['user_id'];

if (isset($_POST['checkout_selected'])) {
    if (isset($_POST['selected_products']) && is_array($_POST['selected_products'])) {
        $product_ids_to_checkout = array_map('intval', $_POST['selected_products']);
        
        if (empty($product_ids_to_checkout)) {
            header("Location: cart.php?error=" . urlencode("Tidak ada produk yang dipilih untuk checkout."));
            exit();
        }

        // Mulai transaksi
        $conn->begin_transaction();

        try {
            $total_amount = 0;
            $order_items_data = [];
            $products_to_update_stock = [];

            // 1. Ambil detail produk yang dipilih dari keranjang dan validasi stok
            $placeholders = implode(',', array_fill(0, count($product_ids_to_checkout), '?'));
            $sql_get_cart_items = "SELECT ci.product_id, ci.quantity, p.price, p.stock 
                                    FROM cart_items ci 
                                    JOIN products p ON ci.product_id = p.id 
                                    WHERE ci.user_id = ? AND ci.product_id IN ($placeholders)";
            
            $stmt_get_cart_items = $conn->prepare($sql_get_cart_items);
            $types = "i" . str_repeat("i", count($product_ids_to_checkout));
            $params = array_merge([$user_id], $product_ids_to_checkout);
            $stmt_get_cart_items->bind_param($types, ...$params);
            $stmt_get_cart_items->execute();
            $result_cart_items = $stmt_get_cart_items->get_result();

            if ($result_cart_items->num_rows !== count($product_ids_to_checkout)) {
                throw new Exception("Beberapa produk yang dipilih tidak ditemukan di keranjang.");
            }

            while ($item = $result_cart_items->fetch_assoc()) {
                if ($item['quantity'] > $item['stock']) {
                    throw new Exception("Stok produk '" . htmlspecialchars($item['product_name']) . "' tidak mencukupi. Hanya tersedia " . htmlspecialchars($item['stock']) . ".");
                }
                $subtotal = $item['quantity'] * $item['price'];
                $total_amount += $subtotal;
                $order_items_data[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $subtotal
                ];
                $products_to_update_stock[$item['product_id']] = $item['quantity'];
            }
            $stmt_get_cart_items->close();

            // 2. Buat entri pesanan baru di tabel 'orders'
            $order_date = date('Y-m-d H:i:s');
            $status = 'Completed'; // Langsung selesai sesuai permintaan
            $sql_insert_order = "INSERT INTO orders (user_id, order_date, total_amount, status) VALUES (?, ?, ?, ?)";
            $stmt_insert_order = $conn->prepare($sql_insert_order);
            $stmt_insert_order->bind_param("isds", $user_id, $order_date, $total_amount, $status);
            
            if (!$stmt_insert_order->execute()) {
                throw new Exception("Gagal membuat pesanan baru: " . $stmt_insert_order->error);
            }
            $order_id = $conn->insert_id;
            $stmt_insert_order->close();

            // 3. Masukkan item pesanan ke tabel 'order_items'
            $sql_insert_order_item = "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert_order_item = $conn->prepare($sql_insert_order_item);

            foreach ($order_items_data as $item_data) {
                $stmt_insert_order_item->bind_param("iiidd", $order_id, $item_data['product_id'], $item_data['quantity'], $item_data['price'], $item_data['subtotal']);
                if (!$stmt_insert_order_item->execute()) {
                    throw new Exception("Gagal menambahkan item ke pesanan: " . $stmt_insert_order_item->error);
                }
            }
            $stmt_insert_order_item->close();

            // 4. Kurangi stok produk
            $sql_update_stock = "UPDATE products SET stock = stock - ? WHERE id = ?";
            $stmt_update_stock = $conn->prepare($sql_update_stock);

            foreach ($products_to_update_stock as $product_id => $quantity_deducted) {
                $stmt_update_stock->bind_param("ii", $quantity_deducted, $product_id);
                if (!$stmt_update_stock->execute()) {
                    throw new Exception("Gagal mengurangi stok produk: " . $stmt_update_stock->error);
                }
            }
            $stmt_update_stock->close();

            // 5. Hapus item dari keranjang
            $sql_delete_cart_items = "DELETE FROM cart_items WHERE user_id = ? AND product_id IN ($placeholders)";
            $stmt_delete_cart_items = $conn->prepare($sql_delete_cart_items);
            $types_delete = "i" . str_repeat("i", count($product_ids_to_checkout));
            $params_delete = array_merge([$user_id], $product_ids_to_checkout);
            $stmt_delete_cart_items->bind_param($types_delete, ...$params_delete);
            
            if (!$stmt_delete_cart_items->execute()) {
                throw new Exception("Gagal menghapus item dari keranjang: " . $stmt_delete_cart_items->error);
            }
            $stmt_delete_cart_items->close();

            // Commit transaksi jika semua berhasil
            $conn->commit();
            // Redirect ke my_orders.php dengan pesan sukses
            header("Location: my_orders.php?message=" . urlencode("Pesanan Anda dengan ID #" . $order_id . " berhasil dibuat dan diselesaikan!"));
            exit();

        } catch (Exception $e) {
            // Rollback transaksi jika terjadi kesalahan
            $conn->rollback();
            header("Location: cart.php?error=" . urlencode("Checkout gagal: " . $e->getMessage()));
            exit();
        } finally {
            $conn->close();
        }
    } else {
        header("Location: cart.php?error=" . urlencode("Tidak ada produk yang dipilih untuk checkout."));
        exit();
    }
} else {
    // Jika diakses langsung tanpa submit form
    header("Location: cart.php");
    exit();
}
?>