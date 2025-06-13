<?php
session_start();

// Aktifkan pelaporan error untuk debugging (HANYA SAAT PENGEMBANGAN)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Periksa apakah user sudah login dan memiliki role 'user'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
    header("Location: ../login.html?error=" . urlencode("Akses tidak diizinkan. Silakan login sebagai user."));
    exit();
}

require_once '../config/database.php'; // Pastikan path ini benar

$conn = get_db_connection();

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Pengguna SkinGlow!');
$user_email = htmlspecialchars($_SESSION['user_email'] ?? 'email@example.com');

$selected_items = [];
$grand_total = 0;
$checkout_error = ''; // Untuk error dari proses validasi di halaman ini
$process_order_error = ''; // Untuk error yang dikirim dari process_order.php

// Cek jika ada error dari process_order.php
if (isset($_GET['error'])) {
    $process_order_error = htmlspecialchars($_GET['error']);
}

// Ambil data item dari parameter URL 'items' yang dikirim dari cart.php
if (isset($_GET['items'])) {
    $items_json = $_GET['items'];
    $decoded_items = json_decode($items_json, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_items)) {
        $product_ids = array_column($decoded_items, 'product_id');

        if (!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));

            // Ambil detail produk dari database, termasuk stok
            $query_products = "SELECT id, name, price, stock, image_url FROM products WHERE id IN ($placeholders)";
            $stmt_products = $conn->prepare($query_products);

            if ($stmt_products) {
                $types = str_repeat('i', count($product_ids));
                $stmt_products->bind_param($types, ...$product_ids);
                $stmt_products->execute();
                $result_products = $stmt_products->get_result();

                $db_products = [];
                while ($row = $result_products->fetch_assoc()) {
                    $db_products[$row['id']] = $row;
                }
                $stmt_products->close();

                foreach ($decoded_items as $item) {
                    $product_id = intval($item['product_id']);
                    $quantity = intval($item['quantity']);

                    if (isset($db_products[$product_id])) {
                        $db_product = $db_products[$product_id];

                        // Validasi stok di sisi server
                        if ($quantity > $db_product['stock']) {
                            $checkout_error = "Kuantitas produk '{$db_product['name']}' melebihi stok yang tersedia ({$db_product['stock']}). Harap sesuaikan di keranjang.";
                            $selected_items = []; // Kosongkan daftar item jika ada error stok
                            $grand_total = 0;
                            break; // Hentikan pemrosesan jika ada masalah stok
                        }

                        $subtotal = $quantity * $db_product['price'];
                        $selected_items[] = [
                            'product_id' => $product_id,
                            'name' => htmlspecialchars($db_product['name']),
                            'quantity' => $quantity,
                            'price' => $db_product['price'],
                            'image_url' => htmlspecialchars($db_product['image_url']),
                            'subtotal' => $subtotal
                        ];
                        $grand_total += $subtotal;
                    } else {
                        $checkout_error = "Produk dengan ID {$product_id} tidak ditemukan atau tidak valid.";
                        $selected_items = [];
                        $grand_total = 0;
                        break;
                    }
                }
            } else {
                $checkout_error = "Gagal menyiapkan query produk: " . $conn->error;
            }
        } else {
            $checkout_error = "Tidak ada item yang dipilih untuk checkout. Silakan kembali ke keranjang.";
        }
    } else {
        $checkout_error = "Data item keranjang tidak valid.";
    }
} else {
    $checkout_error = "Tidak ada item yang dipilih untuk checkout. Silakan kembali ke keranjang.";
}

// Ambil alamat dan nomor telepon dari profil pengguna untuk isian default
$user_address = '';
$user_phone = '';
// *** BARIS INI: PASTIKAN HANYA 'shipping_address' dan 'phone_number' ***
$query_user_info = "SELECT shipping_address, phone_number FROM users WHERE id = ?";
$stmt_user_info = $conn->prepare($query_user_info);
if ($stmt_user_info) {
    $stmt_user_info->bind_param("i", $user_id);
    $stmt_user_info->execute();
    $result_user_info = $stmt_user_info->get_result();
    if ($row_user_info = $result_user_info->fetch_assoc()) {
        // *** BARIS INI: PASTIKAN HANYA 'shipping_address' ***
        $user_address = htmlspecialchars($row_user_info['shipping_address'] ?? '');
        $user_phone = htmlspecialchars($row_user_info['phone_number'] ?? '');
    }
    $stmt_user_info->close();
} else {
    error_log("Error preparing user info query: " . $conn->error);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Checkout Pesanan SkinGlow!">
    <meta name="author" content="Tim SkinGlow">
    <title>Checkout - SkinGlow!</H1></title>

    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../css/index_admin.css" rel="stylesheet">
    <link href="../css/dashboard_customer.css" rel="stylesheet">
    <link href="../css/checkout.css" rel="stylesheet">
</head>
<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="dashboard_customer.php">
                <div class="sidebar-brand-icon">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="sidebar-brand-text mx-3">SkinGlow!</div>
            </a>
            <hr class="sidebar-divider my-0">
            <li class="nav-item">
                <a class="nav-link" href="dashboard_customer.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">
                Belanja & Akun
            </div>
            <li class="nav-item">
                <a class="nav-link" href="cart.php">
                    <i class="fas fa-fw fa-shopping-cart"></i>
                    <span>Keranjang Belanja</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="my_orders.php">
                    <i class="fas fa-fw fa-receipt"></i>
                    <span>Pesanan Saya</span>
                </a>
            </li>
            <hr class="sidebar-divider d-none d-md-block">
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>
        </ul>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                    <?php echo $user_name; ?>
                                </span>
                                <img class="img-profile rounded-circle" src="../img/undraw_profile.svg">
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i> Profil
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Checkout Pesanan</h1>

                    <?php if (!empty($checkout_error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $checkout_error; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <?php if (strpos($checkout_error, 'stok') !== false || strpos($checkout_error, 'Tidak ada item yang dipilih') !== false): ?>
                                <p class="mb-0 mt-2"><a href="cart.php" class="alert-link">Kembali ke Keranjang</a></p>
                            <?php endif; ?>
                        </div>
                    <?php elseif (!empty($process_order_error)): // Tampilkan error dari process_order.php ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Gagal Memproses Pesanan:</strong> <?php echo $process_order_error; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <p class="mb-0 mt-2"><a href="cart.php" class="alert-link">Kembali ke Keranjang</a></p>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($selected_items) && empty($checkout_error) && empty($process_order_error)): ?>
                        <div class="alert alert-info text-center" role="alert">
                            Tidak ada item yang dipilih untuk checkout. Silakan <a href="cart.php" class="alert-link">kembali ke keranjang</a>.
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($selected_items)): ?>
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card shadow mb-4">
                                    <div class="card-header py-3 card-header-checkout">
                                        <h6 class="m-0 font-weight-bold">Informasi Pengiriman</h6>
                                    </div>
                                    <div class="card-body">
                                        <form id="checkoutForm" action="../api/process_order.php" method="POST">
                                            <div class="form-group">
                                                <label for="customerName">Nama Lengkap</label>
                                                <input type="text" class="form-control" id="customerName" name="customer_name" value="<?php echo $user_name; ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="customerEmail">Email</label>
                                                <input type="email" class="form-control" id="customerEmail" name="customer_email" value="<?php echo $user_email; ?>" required readonly>
                                            </div>
                                            <div class="form-group">
                                                <label for="customerAddress">Alamat Lengkap</label>
                                                <textarea class="form-control" id="customerAddress" name="customer_address" rows="3" required><?php echo $user_address; ?></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label for="customerPhone">Nomor Telepon</label>
                                                <input type="tel" class="form-control" id="customerPhone" name="customer_phone" value="<?php echo $user_phone; ?>" required>
                                            </div>
                                            <hr>
                                            <div class="form-group">
                                                <label for="paymentMethod">Metode Pembayaran</label>
                                                <select class="form-control" id="paymentMethod" name="payment_method" required>
                                                    <option value="">Pilih Metode Pembayaran</option>
                                                    <option value="transfer_bank">Transfer Bank (BCA, Mandiri)</option>
                                                    <option value="qris">QRIS</option>
                                                    <option value="e_wallet">E-Wallet (OVO, GoPay, Dana)</option>
                                                </select>
                                                <small class="form-text text-muted">Detail pembayaran akan diberikan setelah konfirmasi pesanan.</small>
                                            </div>

                                            <input type="hidden" name="grand_total" value="<?php echo $grand_total; ?>">
                                            <input type="hidden" name="selected_items_json" value='<?php echo htmlspecialchars(json_encode($selected_items), ENT_QUOTES, 'UTF-8'); ?>'>

                                            <button type="submit" class="btn btn-primary btn-lg btn-block mt-4 btn-pink">Konfirmasi Pesanan & Bayar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card shadow mb-4">
                                    <div class="card-header py-3 card-header-checkout">
                                        <h6 class="m-0 font-weight-bold">Ringkasan Pesanan</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($selected_items as $item): ?>
                                            <div class="checkout-summary-item">
                                                <img src="../<?php echo $item['image_url']; ?>" alt="<?php echo $item['name']; ?>">
                                                <div class="checkout-item-details">
                                                    <div class="checkout-item-name"><?php echo $item['name']; ?></div>
                                                    <div class="checkout-item-qty-price"><?php echo $item['quantity']; ?> x Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></div>
                                                </div>
                                                <div class="checkout-item-subtotal">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="order-summary-footer">
                                            <div class="grand-total-label">Total Pembayaran</div>
                                            <div class="grand-total-amount" id="checkoutGrandTotalPrice">Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; SkinGlow! 2025</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Siap untuk Keluar?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Pilih "Logout" di bawah jika Anda siap untuk mengakhiri sesi Anda saat ini.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Batal</button>
                    <a class="btn btn-primary" href="../logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="../vendor/jquery/jquery.min.js"></script>
    <script src="../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="../js/sb-admin-2.min.js"></script>

    <script>
        $(document).ready(function() {
            <?php if (!empty($process_order_error)): ?>
                // This alert is already handled by PHP, so no JS alert needed here unless you want a fancy one like SweetAlert
            <?php endif; ?>

            $('#checkoutForm').on('submit', function(e) {
                const customerName = $('#customerName').val().trim();
                const customerAddress = $('#customerAddress').val().trim();
                const customerPhone = $('#customerPhone').val().trim();
                const paymentMethod = $('#paymentMethod').val();

                if (!customerName || !customerAddress || !customerPhone || !paymentMethod) {
                    alert('Mohon lengkapi semua informasi pengiriman dan metode pembayaran.');
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>