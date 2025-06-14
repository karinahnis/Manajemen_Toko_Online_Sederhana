<?php
// api/login.php
session_start(); // Mulai sesi PHP

require_once '../config/database.php';

$conn = get_db_connection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        if ($conn) $conn->close();
        header("Location: ../login.html?error=" . urlencode("Email dan password harus diisi."));
        exit();
    }

    // Query SELECT TIDAK MENGAMBIL 'role'
    $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ?");

    if (!$stmt) {
        if ($conn) $conn->close();
        header("Location: ../login.html?error=" . urlencode("Terjadi kesalahan sistem (prepare login): " . $conn->error));
        exit();
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Login Berhasil!
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            // TIDAK ADA $_SESSION['user_role'] = $user['role']; lagi

            $stmt->close();
            if ($conn) $conn->close();

            // Selalu arahkan ke admin dashboard karena semua adalah admin
            header("Location: ../admin/index.php");
            exit();

        } else {
            // Password Salah
            $stmt->close();
            if ($conn) $conn->close();
            header("Location: ../login.html?error=" . urlencode("Email atau password salah."));
            exit();
        }
    } else {
        // User tidak ditemukan
        $stmt->close();
        if ($conn) $conn->close();
        header("Location: ../login.html?error=" . urlencode("Email atau password salah."));
        exit();
    }
} else {
    // Jika bukan POST request
    if ($conn) {
        $conn->close();
    }
    header("Location: ../login.html");
    exit();
}
?>