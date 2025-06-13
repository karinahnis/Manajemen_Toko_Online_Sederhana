<?php
session_start();
// Aktifkan pelaporan error untuk debugging (HANYA SAAT PENGEMBANGAN)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Pastikan user sudah login dan memiliki role 'user'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
    header("Location: ../login.html?error=" . urlencode("Akses tidak diizinkan. Silakan login sebagai user."));
    exit();
}

require_once '../config/database.php'; // Pastikan path ini benar

$conn = get_db_connection();

// Mulai transaksi database
$conn->begin_transaction();

try {
    // Ambil data dari POST request
    $user_id = $_SESSION['user_id'];
    $customer_name = htmlspecialchars($_POST['customer_name'] ?? '');
    $customer_email = htmlspecialchars($_POST['customer_email'] ?? ''); // Email diambil dari sesi, tapi validasi tetap dilakukan
    $customer_address = htmlspecialchars($_POST['customer_address'] ?? ''); // Ini adalah alamat lengkap dari form checkout
    $customer_phone = htmlspecialchars($_POST['customer_phone'] ?? '');
    $payment_method = htmlspecialchars($_POST['payment_method'] ?? '');
    $grand_total = floatval($_POST['grand_total'] ?? 0);
    $selected_items_json = $_POST['selected_items_json'] ?? '';

    $selected_items = json_decode($selected_items_json, true);

    // Validasi dasar input
    if (empty($customer_name) || empty($customer_address) || empty($customer_phone) || empty($payment_method) || empty($selected_items) || $grand_total <= 0) {
        throw new Exception("Data pesanan tidak lengkap atau tidak valid.");
    }

    // Tentukan status default sebagai variabel
    $order_status = 'pending'; // <--- Tambahkan variabel ini

    // 1. Masukkan data pesanan ke tabel 'orders'
    $query_order = "INSERT INTO orders (user_id, total_amount, payment_method, recipient_name, phone_number, shipping_address, status) VALUES (?, ?, ?, ?, ?, ?, ?)"; // <--- Ubah 'pending' menjadi '?'
    $stmt_order = $conn->prepare($query_order);

    if (!$stmt_order) {
        throw new Exception("Gagal menyiapkan query pesanan: " . $conn->error);
    }

    // Pastikan urutan parameter bind_param sesuai dengan urutan kolom di query.
    // Sekarang, 'order_status' adalah variabel yang dilewatkan.
    $stmt_order->bind_param("idsssss", $user_id, $grand_total, $payment_method, $customer_name, $customer_phone, $customer_address, $order_status); // <--- Gunakan $order_status
    $stmt_order->execute();

    if ($stmt_order->affected_rows === 0) {
        throw new Exception("Gagal menyimpan pesanan utama.");
    }

    $order_id = $conn->insert_id; // Ambil ID pesanan yang baru saja dibuat
    $stmt_order->close();

    // 2. Proses setiap item pesanan
    foreach ($selected_items as $item) {
        $product_id = intval($item['product_id']);
        $quantity = intval($item['quantity']);
        $price_at_order = floatval($item['price']); // Harga saat pesanan dibuat

        // Validasi dan ambil stok saat ini dari database lagi untuk keamanan
        $query_check_stock = "SELECT stock, name FROM products WHERE id = ? FOR UPDATE"; // FOR UPDATE untuk locking baris
        $stmt_check_stock = $conn->prepare($query_check_stock);
        if (!$stmt_check_stock) {
            throw new Exception("Gagal menyiapkan query cek stok: " . $conn->error);
        }
        $stmt_check_stock->bind_param("i", $product_id);
        $stmt_check_stock->execute();
        $result_stock = $stmt_check_stock->get_result();
        $product_data = $result_stock->fetch_assoc();
        $stmt_check_stock->close();

        if (!$product_data || $quantity > $product_data['stock']) {
            throw new Exception("Stok untuk produk '{$product_data['name']}' tidak cukup. Tersedia: {$product_data['stock']}, Diminta: {$quantity}.");
        }

        // Masukkan item ke tabel 'order_items'
        $query_order_item = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
        $stmt_order_item = $conn->prepare($query_order_item);
        if (!$stmt_order_item) {
            throw new Exception("Gagal menyiapkan query item pesanan: " . $conn->error);
        }
        $stmt_order_item->bind_param("iiid", $order_id, $product_id, $quantity, $price_at_order);
        $stmt_order_item->execute();
        if ($stmt_order_item->affected_rows === 0) {
            throw new Exception("Gagal menyimpan item pesanan untuk produk ID: " . $product_id);
        }
        $stmt_order_item->close();

        // Kurangi stok produk
        $query_update_stock = "UPDATE products SET stock = stock - ? WHERE id = ?";
        $stmt_update_stock = $conn->prepare($query_update_stock);
        if (!$stmt_update_stock) {
            throw new Exception("Gagal menyiapkan query update stok: " . $conn->error);
        }
        $stmt_update_stock->bind_param("ii", $quantity, $product_id);
        $stmt_update_stock->execute();
        if ($stmt_update_stock->affected_rows === 0) {
            throw new Exception("Gagal mengurangi stok untuk produk ID: " . $product_id);
        }
        $stmt_update_stock->close();

        // Hapus item dari keranjang setelah berhasil diproses ke pesanan
        // Ini akan menghapus hanya item yang diproses dari keranjang user saat ini
        $query_delete_cart_item = "DELETE FROM cart_items WHERE user_id = ? AND product_id = ?";
        $stmt_delete_cart_item = $conn->prepare($query_delete_cart_item);
        if (!$stmt_delete_cart_item) {
            throw new Exception("Gagal menyiapkan query hapus item keranjang: " . $conn->error);
        }
        $stmt_delete_cart_item->bind_param("ii", $user_id, $product_id);
        $stmt_delete_cart_item->execute();
        $stmt_delete_cart_item->close();
    }

    // Commit transaksi jika semua operasi berhasil
    $conn->commit();

    // Redirect LANGSUNG ke halaman pesanan saya dengan ID pesanan (opsional, untuk penyorotan)
    header("Location: ../user/my_orders.php?order_id=" . $order_id);
    exit();

} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    $conn->rollback();
    error_log("Order processing failed for user_id: {$user_id} - " . $e->getMessage());
    // Redirect kembali ke halaman checkout dengan pesan error
    header("Location: ../user/checkout.php?error=" . urlencode($e->getMessage()));
    exit();
} finally {
    $conn->close();
}
?>