<?php
// api/add_to_cart.php - Menangani penambahan produk ke keranjang (ke database)
session_start();

// ====== DEBUGGING SECTION START ======
// Aktifkan baris di bawah ini jika Anda ingin melihat error langsung di browser
// Nonaktifkan saat produksi
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ====== DEBUGGING SECTION END ======

require_once '../config/database.php'; // Path ini relatif dari api/ ke config/

$conn = get_db_connection();

// Set header Content-Type ke application/json
header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Terjadi kesalahan tidak terduga.',
    'cartItemCount' => 0 // Akan diupdate jika berhasil
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        $response['message'] = "Anda harus login untuk menambahkan produk ke keranjang.";
        echo json_encode($response);
        exit();
    }

    $user_id = $_SESSION['user_id'];
    // Ambil product_id langsung dari POST, tidak perlu 'add_to_cart'
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1; // Default quantity 1

    if ($product_id <= 0 || $quantity <= 0) {
        $response['message'] = "Produk atau kuantitas tidak valid.";
        echo json_encode($response);
        exit();
    }

    // Ambil informasi produk dari tabel products (untuk validasi dan harga)
    $stmt_product = $conn->prepare("SELECT id, name, price, stock FROM products WHERE id = ?");
    if (!$stmt_product) {
        $response['message'] = "Terjadi kesalahan database saat mengambil info produk: " . $conn->error;
        echo json_encode($response);
        exit();
    }
    $stmt_product->bind_param("i", $product_id);
    $stmt_product->execute();
    $result_product = $stmt_product->get_result();
    $product_data = $result_product->fetch_assoc();
    $stmt_product->close();

    if (!$product_data) {
        $response['message'] = "Produk tidak ditemukan.";
        echo json_encode($response);
        exit();
    }

    $available_stock = $product_data['stock'];

    // Cek apakah produk sudah ada di keranjang user di database
    $stmt_check_cart = $conn->prepare("SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
    if (!$stmt_check_cart) {
        $response['message'] = "Terjadi kesalahan database saat memeriksa keranjang: " . $conn->error;
        echo json_encode($response);
        exit();
    }
    $stmt_check_cart->bind_param("ii", $user_id, $product_id);
    $stmt_check_cart->execute();
    $result_check_cart = $stmt_check_cart->get_result();
    $cart_item = $result_check_cart->fetch_assoc();
    $stmt_check_cart->close();

    $new_quantity_in_cart = $quantity; // Kuantitas yang akan ditambahkan/diperbarui

    if ($cart_item) {
        // Produk sudah ada di keranjang, update kuantitas
        $current_quantity_in_cart = $cart_item['quantity'];
        $new_quantity_in_cart = $current_quantity_in_cart + $quantity;

        // Cek stok: Pastikan kuantitas total tidak melebihi stok
        if ($new_quantity_in_cart > $available_stock) {
            $response['message'] = "Jumlah produk melebihi stok yang tersedia. Stok saat ini: " . $available_stock;
            echo json_encode($response);
            exit();
        }

        $stmt_update = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?");
        if (!$stmt_update) {
            $response['message'] = "Terjadi kesalahan database saat update keranjang: " . $conn->error;
            echo json_encode($response);
            exit();
        }
        $stmt_update->bind_param("iii", $new_quantity_in_cart, $user_id, $product_id);
        if ($stmt_update->execute()) {
            $response['success'] = true;
            $response['message'] = "Kuantitas produk berhasil diperbarui di keranjang!";
        } else {
            $response['message'] = "Gagal memperbarui kuantitas produk di keranjang: " . $stmt_update->error;
        }
        $stmt_update->close();

    } else {
        // Produk belum ada di keranjang, masukkan sebagai item baru
        // Cek stok: Pastikan kuantitas yang diminta tidak melebihi stok
        if ($quantity > $available_stock) {
            $response['message'] = "Jumlah produk yang diminta melebihi stok yang tersedia. Stok saat ini: " . $available_stock;
            echo json_encode($response);
            exit();
        }

        $stmt_insert = $conn->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
        if (!$stmt_insert) {
            $response['message'] = "Terjadi kesalahan database saat insert keranjang: " . $conn->error;
            echo json_encode($response);
            exit();
        }
        $stmt_insert->bind_param("iii", $user_id, $product_id, $quantity);
        if ($stmt_insert->execute()) {
            $response['success'] = true;
            $response['message'] = "Produk berhasil ditambahkan ke keranjang!";
        } else {
            $response['message'] = "Gagal menambahkan produk ke keranjang: " . $stmt_insert->error;
        }
        $stmt_insert->close();
    }

    // Hitung total item di keranjang (untuk tampilan di navbar jika diperlukan)
    $stmt_cart_count = $conn->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
    if ($stmt_cart_count) {
        $stmt_cart_count->bind_param("i", $user_id);
        $stmt_cart_count->execute();
        $result_cart_count = $stmt_cart_count->get_result();
        $row_cart_count = $result_cart_count->fetch_assoc();
        $response['cartItemCount'] = $row_cart_count['total_items'] ?? 0;
        $stmt_cart_count->close();
    }

} else {
    // Permintaan bukan POST
    $response['message'] = "Metode permintaan tidak valid.";
}

$conn->close();
echo json_encode($response);
exit(); // Pastikan script berhenti setelah mengirim JSON
?>