<?php
session_start();

require_once '../config/database.php'; // Sesuaikan path jika perlu. Harusnya ../config/database.php jika api di root/api

$conn = get_db_connection();

$search_query = $_GET['search'] ?? ''; // Ambil query pencarian
$category_id = $_GET['category_id'] ?? null; // Ambil category_id dari request

$sql = "SELECT id, name, price, stock FROM products WHERE name LIKE ?";
$params = ["s", '%' . $search_query . '%']; // Inisialisasi parameter untuk search

if ($category_id !== null && $category_id !== '') {
    $sql .= " AND category_id = ?"; // Tambahkan filter kategori
    $params[0] .= "i"; // Tambahkan tipe data integer untuk category_id
    $params[] = (int)$category_id; // Tambahkan nilai category_id
}

$sql .= " ORDER BY name ASC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    // Bind parameter secara dinamis
    // Menggunakan call_user_func_array dengan refValues untuk mendukung PHP < 8.0
    call_user_func_array([$stmt, 'bind_param'], refValues($params));
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    $stmt->close();
    echo json_encode(['success' => true, 'data' => $products]);
} else {
    // Memberikan pesan error yang lebih informatif jika prepare statement gagal
    echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan statement: ' . $conn->error]);
}


$conn->close();

// Fungsi helper untuk bind_param (membutuhkan referensi)
function refValues($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) // PHP 5.3+
    {
        $refs = [];
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}
?>