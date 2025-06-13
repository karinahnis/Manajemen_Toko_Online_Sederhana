<?php
// basic_online_store/includes/header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Untuk mengelola pesan sukses/error
$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Basic Online Store'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css"> </head>
<body>
    <div class="wrapper">
        <header class="topbar">
            <div class="logo">Basic Online Store</div>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php">Beranda</a></li>
                    <li><a href="categories.php">Kategori</a></li>
                    <li><a href="cart.php">Keranjang</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="dashboard_pelanggan.php">Akun Saya</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Daftar</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </header>

        <main class="content">
            <?php if ($message): ?>
                <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>