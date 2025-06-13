<?php
session_start();

// Periksa apakah user sudah login dan memiliki role 'user'
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'user') {
    header("Location: ../login.html?error=" . urlencode("Akses tidak diizinkan. Silakan login sebagai user."));
    exit();
}

require_once '../config/database.php'; // Pastikan path ini benar

$conn = get_db_connection();

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Pengguna SkinGlow!');

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$order_details = null;
$order_items = [];
$error_message = '';

if ($order_id > 0) {
    // Query untuk mengambil detail pesanan utama
    // Pastikan user_id juga cocok untuk keamanan
    $query_order = "SELECT id, user_id, order_date, total_amount, payment_method, recipient_name, phone_number, shipping_address, status
                    FROM orders
                    WHERE id = ? AND user_id = ?";
    $stmt_order = $conn->prepare($query_order);

    if ($stmt_order) {
        $stmt_order->bind_param("ii", $order_id, $user_id);
        $stmt_order->execute();
        $result_order = $stmt_order->get_result();
        $order_details = $result_order->fetch_assoc();
        $stmt_order->close();

        // Jika pesanan ditemukan dan milik user ini, ambil item-itemnya
        if ($order_details) {
            $query_items = "SELECT oi.quantity, oi.price, p.name, p.image_url
                            FROM order_items oi
                            JOIN products p ON oi.product_id = p.id
                            WHERE oi.order_id = ?";
            $stmt_items = $conn->prepare($query_items);
            if ($stmt_items) {
                $stmt_items->bind_param("i", $order_id);
                $stmt_items->execute();
                $result_items = $stmt_items->get_result();
                while ($row = $result_items->fetch_assoc()) {
                    $order_items[] = $row;
                }
                $stmt_items->close();
            } else {
                $error_message = "Gagal menyiapkan query item pesanan: " . $conn->error;
            }
        } else {
            $error_message = "Pesanan tidak ditemukan atau Anda tidak memiliki akses.";
        }
    } else {
        $error_message = "Gagal menyiapkan query detail pesanan: " . $conn->error;
    }
} else {
    $error_message = "ID Pesanan tidak valid.";
}

$conn->close();

// Fungsi helper untuk status badge (sama seperti di my_orders.php)
function get_status_badge($status) {
    switch (strtolower($status)) {
        case 'pending':
            return '<span class="badge badge-warning">Menunggu Pembayaran</span>';
        case 'paid':
            return '<span class="badge badge-info">Dibayar</span>';
        case 'processing':
            return '<span class="badge badge-primary">Diproses</span>';
        case 'shipped':
            return '<span class="badge badge-secondary">Dikirim</span>';
        case 'completed':
            return '<span class="badge badge-success">Selesai</span>';
        case 'cancelled':
            return '<span class="badge badge-danger">Dibatalkan</span>';
        default:
            return '<span class="badge badge-light">' . htmlspecialchars(ucfirst($status)) . '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Detail Pesanan - SkinGlow!">
    <meta name="author" content="Tim SkinGlow">
    <title>Detail Pesanan <?php echo ($order_details ? '#'.htmlspecialchars($order_details['id']) : ''); ?> - SkinGlow!</title>

    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="../css/index_admin.css" rel="stylesheet">
    <link href="../css/dashboard_customer.css" rel="stylesheet">
    <link href="css/checkout.css" rel="stylesheet"> <style>
        /* Gaya tambahan yang mungkin dibutuhkan untuk detail */
        .order-detail-info .card-header {
            background-color: #f8c0d1; /* Mengikuti warna dari my_orders.php */
            color: #fff;
            font-weight: bold;
        }
        .product-item-detail {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .product-item-detail:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .product-item-detail img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            margin-right: 15px;
            border-radius: 5px;
        }
        .product-info {
            flex-grow: 1;
        }
        .product-name {
            font-weight: bold;
            color: #333;
        }
        .product-qty-price {
            font-size: 0.9em;
            color: #666;
        }
        .product-subtotal {
            font-weight: bold;
            color: #e74a3b; /* Warna pink untuk subtotal */
            text-align: right;
            min-width: 100px; /* Agar rapi */
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 1.1em;
            margin-top: 10px;
        }
        .summary-row.total {
            font-weight: bold;
            font-size: 1.2em;
            color: #e74a3b;
        }
    </style>
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
            <li class="nav-item active"> <a class="nav-link" href="my_orders.php">
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
                    <h1 class="h3 mb-4 text-gray-800">Detail Pesanan <?php echo ($order_details ? '#'.htmlspecialchars($order_details['id']) : ''); ?></h1>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $error_message; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <p class="mb-0 mt-2"><a href="my_orders.php" class="alert-link">Kembali ke Daftar Pesanan</a></p>
                        </div>
                    <?php elseif (!$order_details): // Ini harusnya tidak terjadi jika ada error_message di atas, tapi untuk jaga-jaga ?>
                        <div class="alert alert-info text-center" role="alert">
                            Pesanan tidak ditemukan atau ID Pesanan tidak valid. Silakan <a href="my_orders.php" class="alert-link">kembali ke daftar pesanan Anda</a>.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-lg-7 mb-4">
                                <div class="card shadow h-100 py-2 order-detail-info">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="m-0 font-weight-bold">Informasi Pesanan</h6>
                                        <?php echo get_status_badge($order_details['status']); ?>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>ID Pesanan:</strong> <?php echo htmlspecialchars($order_details['id']); ?></p>
                                        <p><strong>Tanggal Pesanan:</strong> <?php echo htmlspecialchars(date('d M Y H:i', strtotime($order_details['order_date']))); ?></p>
                                        <p><strong>Metode Pembayaran:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order_details['payment_method']))); ?></p>
                                        <hr>
                                        <p><strong>Nama Penerima:</strong> <?php echo htmlspecialchars($order_details['recipient_name']); ?></p>
                                        <p><strong>Nomor Telepon:</strong> <?php echo htmlspecialchars($order_details['phone_number']); ?></p>
                                        <p><strong>Alamat Pengiriman:</strong> <?php echo nl2br(htmlspecialchars($order_details['shipping_address'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-5 mb-4">
                                <div class="card shadow h-100 py-2 order-detail-info">
                                    <div class="card-header">
                                        <h6 class="m-0 font-weight-bold">Ringkasan Produk</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php $total_items_price = 0; ?>
                                        <?php if (empty($order_items)): ?>
                                            <p class="text-center text-muted">Tidak ada produk dalam pesanan ini.</p>
                                        <?php else: ?>
                                            <?php foreach ($order_items as $item): ?>
                                                <div class="product-item-detail">
                                                    <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                    <div class="product-info">
                                                        <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                                        <div class="product-qty-price"><?php echo $item['quantity']; ?> x Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></div>
                                                    </div>
                                                    <div class="product-subtotal">Rp <?php echo number_format($item['quantity'] * $item['price'], 0, ',', '.'); ?></div>
                                                </div>
                                                <?php $total_items_price += ($item['quantity'] * $item['price']); ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <hr>
                                        <div class="summary-row">
                                            <span>Subtotal Produk:</span>
                                            <span>Rp <?php echo number_format($total_items_price, 0, ',', '.'); ?></span>
                                        </div>
                                        <div class="summary-row total">
                                            <span>Total Pembayaran:</span>
                                            <span>Rp <?php echo number_format($order_details['total_amount'], 0, ',', '.'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-right mt-3">
                            <a href="my_orders.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali ke Pesanan Saya</a>
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
</body>
</html>