<?php
// config/database.php

function get_db_connection() {
    $servername = "localhost"; // Ganti jika database Anda di server lain
    $username = "root"; // Ganti dengan username database Anda
    $password = ""; // Ganti dengan password database Anda
    $dbname = "basic_online_store"; // Ganti dengan nama database Anda

    // Buat koneksi
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Cek koneksi
    if ($conn->connect_error) {
        die("Koneksi database gagal: " . $conn->connect_error);
    }

    // Mengatur charset untuk koneksi agar mendukung karakter khusus
    $conn->set_charset("utf8mb4");

    return $conn;
}

// Opsional: Untuk debugging, bisa dihapus setelah yakin koneksi berhasil
// Anda bisa test koneksi dengan memanggil fungsi ini:
// $test_conn = get_db_connection();
// if ($test_conn) {
//     echo "Koneksi ke database berhasil!";
//     $test_conn->close();
// } else {
//     echo "Koneksi gagal saat testing.";
// }

?>