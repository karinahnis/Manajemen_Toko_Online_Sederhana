<?php
session_start();

require_once '../config/database.php'; // Pastikan path ini benar

$conn = get_db_connection();

$message_type = "danger";
$message = "Terjadi kesalahan.";

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];

    // Ambil URL gambar sebelum menghapus produk
    $get_image_sql = "SELECT image_url FROM products WHERE id = ?";
    $stmt_img = $conn->prepare($get_image_sql);
    $stmt_img->bind_param("i", $product_id);
    $stmt_img->execute();
    $result_img = $stmt_img->get_result();
    $image_url_to_delete = null;
    if ($result_img->num_rows > 0) {
        $row_img = $result_img->fetch_assoc();
        $image_url_to_delete = $row_img['image_url'];
    }
    $stmt_img->close();

    // Hapus produk dari database
    $delete_sql = "DELETE FROM products WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $product_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $message_type = "success";
            $message = "Produk berhasil dihapus.";

            // Hapus file gambar fisik jika ada
            if (!empty($image_url_to_delete)) {
                $image_path = "../" . $image_url_to_delete; // Path lengkap ke gambar
                if (file_exists($image_path) && is_file($image_path)) {
                    unlink($image_path); // Hapus file
                }
            }
        } else {
            $message = "Produk dengan ID tersebut tidak ditemukan.";
        }
    } else {
        $message = "Error saat menghapus produk: " . $stmt->error;
    }
    $stmt->close();
} else {
    $message = "ID Produk tidak valid untuk dihapus.";
}

$conn->close();

// Redirect kembali ke manage_products.php dengan pesan status
header("Location: manage_products.php?status=" . $message_type . "&message=" . urlencode($message));
exit();
?>