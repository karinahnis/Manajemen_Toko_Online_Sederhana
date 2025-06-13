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

// Inisialisasi variabel statement dan koneksi
$stmt = null;
$conn = null;

// Buat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi di awal
if ($conn->connect_error) {
    // Jika koneksi gagal, alihkan ke halaman register dengan pesan error
    header("Location: ../register.html?error=" . urlencode("Koneksi database gagal: " . $conn->connect_error));
    exit();
}

// Hanya proses jika request adalah POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari formulir
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validasi input - Mengalihkan ke register.html dengan pesan error
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        header("Location: ../register.html?error=" . urlencode("Semua kolom harus diisi."));
        $conn->close(); // Tutup koneksi sebelum exit
        exit();
    }

    // TAMBAH: Validasi format email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../register.html?error=" . urlencode("Format email tidak valid."));
        $conn->close();
        exit();
    }

    if ($password !== $confirm_password) {
        header("Location: ../register.html?error=" . urlencode("Password dan konfirmasi password tidak cocok."));
        $conn->close(); // Tutup koneksi sebelum exit
        exit();
    }

    if (strlen($password) < 6) {
        header("Location: ../register.html?error=" . urlencode("Password minimal 6 karakter."));
        $conn->close(); // Tutup koneksi sebelum exit
        exit();
    }

    // Cek apakah email sudah terdaftar
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        header("Location: ../register.html?error=" . urlencode("Terjadi kesalahan sistem (prepare check email): " . $conn->error));
        $conn->close();
        exit();
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        header("Location: ../register.html?error=" . urlencode("Email sudah terdaftar. Silakan gunakan email lain."));
        $stmt->close(); // Tutup statement sebelum exit
        $conn->close(); // Tutup koneksi sebelum exit
        exit();
    }
    $stmt->close(); // Tutup statement setelah digunakan

    // Hash password sebelum disimpan ke database
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Siapkan query untuk menyimpan data pengguna baru
    // *** PERHATIAN: Ubah 'name' menjadi 'full_name' JIKA kolom di DB Anda bernama 'full_name' ***
    // Jika kolom di DB Anda memang 'name', maka kode ini sudah benar.
    $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    if (!$stmt) {
        header("Location: ../register.html?error=" . urlencode("Terjadi kesalahan sistem (prepare insert user): " . $conn->error));
        $conn->close();
        exit();
    }
    $stmt->bind_param("sss", $full_name, $email, $hashed_password);

    if ($stmt->execute()) {
        // Registrasi berhasil!
        // Alihkan pengguna ke halaman login2.html
        header("Location: ../login.html?success=" . urlencode("Registrasi berhasil! Silakan login."));
        exit();
    } else {
        // Terjadi kesalahan saat menyimpan ke database
        // Pesan error ini sangat membantu untuk debugging
        header("Location: ../register.html?error=" . urlencode("Terjadi kesalahan saat menyimpan data: " . $stmt->error));
        // Tidak perlu $stmt->close() di sini karena akan ditutup oleh $conn->close()
        $conn->close();
        exit();
    }

} else {
    // Jika diakses langsung tanpa POST request, alihkan kembali ke halaman register
    header("Location: ../register.html");
    exit();
}

// Tutup koneksi database di akhir skrip
if ($conn) {
    $conn->close();
}

?>