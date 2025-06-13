<?php
// Pastikan semua error ditampilkan saat development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Konfigurasi koneksi database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "basic_online_store";

$conn = null;

// Buat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi di awal
if ($conn->connect_error) {
    header("Location: ../reset-password.html?error=" . urlencode("Koneksi database gagal: " . $conn->connect_error));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = trim($_POST['token'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    // Validasi input
    if (empty($token) || empty($new_password) || empty($confirm_new_password)) {
        header("Location: ../reset-password.html?error=" . urlencode("Token atau password tidak boleh kosong."));
        $conn->close();
        exit();
    }

    if ($new_password !== $confirm_new_password) {
        header("Location: ../reset-password.html?error=" . urlencode("Password baru dan konfirmasi password tidak cocok."));
        $conn->close();
        exit();
    }

    if (strlen($new_password) < 6) {
        header("Location: ../reset-password.html?error=" . urlencode("Password minimal 6 karakter."));
        $conn->close();
        exit();
    }

    // 1. Verifikasi token: apakah ada di database dan belum kedaluwarsa
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()");
    if (!$stmt) {
        header("Location: ../reset-password.html?error=" . urlencode("Terjadi kesalahan sistem (prepare verify token): " . $conn->error));
        $conn->close();
        exit();
    }
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        header("Location: ../reset-password.html?error=" . urlencode("Tautan reset password tidak valid atau sudah kedaluwarsa."));
        $stmt->close();
        $conn->close();
        exit();
    }
    $stmt->bind_result($user_id);
    $stmt->fetch();
    $stmt->close();

    // 2. Hash password baru
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // 3. Update password pengguna dan hapus token reset
    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
    if (!$stmt) {
        header("Location: ../reset-password.html?error=" . urlencode("Terjadi kesalahan sistem (prepare update password): " . $conn->error));
        $conn->close();
        exit();
    }
    $stmt->bind_param("si", $hashed_password, $user_id);

    if ($stmt->execute()) {
        header("Location: ../login2.html?success=" . urlencode("Password Anda berhasil direset! Silakan login dengan password baru."));
        exit();
    } else {
        header("Location: ../reset-password.html?error=" . urlencode("Gagal memperbarui password: " . $stmt->error));
        $conn->close();
        exit();
    }

} else {
    // Jika diakses langsung tanpa POST request, alihkan kembali
    header("Location: ../forgot-password.html"); // Atau ke halaman login
    exit();
}


?>