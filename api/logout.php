<?php
// api/logout.php
session_start(); // Mulai sesi

// Hapus semua variabel sesi
$_SESSION = array();

// Jika ingin menghancurkan sesi secara permanen, juga hapus cookie sesi.
// Catatan: Ini akan menghancurkan sesi, dan bukan hanya data sesi!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Akhirnya, hancurkan sesi.
session_destroy();

// Redirect ke halaman login dengan pesan sukses (opsional)
header("Location: ../login.html?success=" . urlencode("Anda telah berhasil logout."));
exit();
?>