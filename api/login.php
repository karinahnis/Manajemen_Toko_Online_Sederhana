<?php
// api/login.php
session_start(); // Mulai sesi PHP

// Include file koneksi database
// Pastikan path ini benar sesuai struktur folder Anda
require_once '../config/database.php';

// PENTING: Inisialisasi koneksi database di sini, sebelum digunakan!
$conn = get_db_connection(); // Panggil fungsi untuk mendapatkan koneksi

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validasi input sederhana
    if (empty($email) || empty($password)) {
        // Jika ada error input, tutup koneksi dan redirect
        if ($conn) $conn->close(); // Pastikan koneksi ada sebelum ditutup
        header("Location: ../login.html?error=" . urlencode("Email dan password harus diisi."));
        exit();
    }

    // Siapkan query untuk mengambil data pengguna berdasarkan email
    // *** TAMBAHKAN KOLOM 'name' KE DALAM QUERY SELECT ***
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    // ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ Pastikan 'name' adalah nama kolom di database Anda

    // Periksa apakah prepare statement berhasil
    if (!$stmt) {
        if ($conn) $conn->close(); // Pastikan koneksi ada sebelum ditutup
        // Menggunakan pesan error yang lebih spesifik jika prepare gagal
        header("Location: ../login.html?error=" . urlencode("Terjadi kesalahan sistem (prepare login): " . $conn->error));
        exit();
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verifikasi password yang diinput dengan password hash di database
        if (password_verify($password, $user['password'])) {
            // Login Berhasil!
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            
            // *** PERBAIKAN UTAMA DI SINI ***
            // Mengubah 'username' menjadi 'user_name' agar konsisten dengan user/index.php
            // Memastikan nama kolom dari database adalah 'name' (atau sesuaikan jika nama kolom Anda berbeda, misal 'full_name')
            $_SESSION['user_name'] = $user['name']; // <--- PERBAIKAN

            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role']; // Ambil peran/role user

            $stmt->close(); // Tutup statement
            if ($conn) $conn->close(); // Tutup koneksi

            // Redirect berdasarkan role
            if ($user['role'] === 'admin') {
                header("Location: ../admin/index.php"); // Arahkan ke Admin Dashboard
            } else if ($user['role'] === 'user') {
                header("Location: ../user/dashboard_customer.php"); // Arahkan ke User Dashboard
            } else {
                // Jika role tidak dikenali atau tidak ada
                header("Location: ../login.html?error=" . urlencode("Role pengguna tidak valid."));
            }
            exit();

        } else {
            // Password Salah
            $stmt->close();
            if ($conn) $conn->close();
            header("Location: ../login.html?error=" . urlencode("Email atau password salah."));
            exit();
        }
    } else {
        // User tidak ditemukan (atau lebih dari satu, yang seharusnya tidak terjadi jika email unik)
        $stmt->close();
        if ($conn) $conn->close();
        header("Location: ../login.html?error=" . urlencode("Email atau password salah."));
        exit();
    }
} else {
    // Jika bukan POST request, arahkan kembali ke halaman login
    // Tutup koneksi juga jika ini bukan POST request
    if ($conn) { // Cek $conn ada dan bukan null
        $conn->close();
    }
    header("Location: ../login.html");
    exit();
}
?>